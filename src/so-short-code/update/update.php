<?php
if (!defined('ABSPATH')) exit;

/**
 * Validate update metadata before WordPress is allowed to consume it.
 *
 * Update packages must remain on the configured update host and use HTTPS.
 */
function sosc_validate_update_meta($meta)
{
    if (!is_array($meta) || empty($meta['version']) || empty($meta['download_url']) || empty($meta['sha256'])) {
        return false;
    }
    foreach (['version', 'download_url', 'sha256', 'name', 'author', 'homepage', 'author_profile', 'tested', 'requires', 'requires_php', 'last_updated'] as $field) {
        if (isset($meta[$field]) && !is_string($meta[$field])) {
            return false;
        }
    }
    if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', (string) $meta['version'])) {
        return false;
    }
    if (!preg_match('/^[a-f0-9]{64}$/i', (string) $meta['sha256'])) {
        return false;
    }
    $meta_url = wp_parse_url(SOSC_UPDATE_META_URL);
    $package_url = wp_parse_url($meta['download_url']);
    if (!is_array($meta_url) || !is_array($package_url)) {
        return false;
    }
    if ('https' !== strtolower($package_url['scheme'] ?? '') || empty($package_url['host']) || !hash_equals(strtolower($meta_url['host'] ?? ''), strtolower($package_url['host']))) {
        return false;
    }
    return sosc_is_managed_update_package($meta['download_url']);
}

/**
 * Return a display URL only when it uses HTTPS.
 */
function sosc_update_display_url($value)
{
    if (!is_string($value)) return '';
    $url = esc_url_raw((string) $value, ['https']);
    return 'https' === strtolower((string) wp_parse_url($url, PHP_URL_SCHEME)) ? $url : '';
}

/**
 * Determine whether a package URL belongs to this plugin's update channel.
 */
function sosc_is_managed_update_package($package)
{
    if (!is_string($package)) return false;
    $meta_url = wp_parse_url(SOSC_UPDATE_META_URL);
    $package_url = wp_parse_url((string) $package);
    if (!is_array($meta_url) || !is_array($package_url)) {
        return false;
    }
    if ('https' !== strtolower($package_url['scheme'] ?? '') || empty($package_url['host'])) {
        return false;
    }
    if (!hash_equals(strtolower($meta_url['host'] ?? ''), strtolower($package_url['host']))) {
        return false;
    }
    return 1 === preg_match('#/so-short-code-[0-9]{2}\.[0-9]{2}\.[0-9]{2}\.zip$#', $package_url['path'] ?? '');
}

/**
 * Download this plugin's package and reject it unless its SHA-256 matches metadata.
 */
function sosc_verify_update_package($reply, $package)
{
    if (is_wp_error($reply)) {
        return $reply;
    }
    if (!sosc_is_managed_update_package($package)) {
        return $reply;
    }
    $meta = sosc_fetch_update_meta();
    if (!$meta) {
        return sosc_is_managed_update_package($package)
            ? new WP_Error('sosc_update_metadata_unavailable', __('Update package metadata could not be verified.', 'so-short-code'))
            : $reply;
    }
    if (!hash_equals((string) $meta['download_url'], (string) $package)) {
        return sosc_is_managed_update_package($package)
            ? new WP_Error('sosc_update_package_mismatch', __('The update package URL does not match its metadata.', 'so-short-code'))
            : $reply;
    }
    if (!sosc_is_managed_update_package($package)) {
        return $reply;
    }
    if (!function_exists('download_url')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    $temporary_file = download_url($package, 300, true);
    if (is_wp_error($temporary_file)) {
        return $temporary_file;
    }
    $actual_hash = hash_file('sha256', $temporary_file);
    if (!$actual_hash || !hash_equals(strtolower((string) $meta['sha256']), strtolower($actual_hash))) {
        wp_delete_file($temporary_file);
        return new WP_Error('sosc_update_hash_mismatch', __('更新パッケージの整合性を確認できませんでした。', 'so-short-code'));
    }
    return $temporary_file;
}
add_filter('upgrader_pre_download', 'sosc_verify_update_package', 10, 2);
/** ================================
 * 更新用データ
 ==================================*/
/**
 * メタ情報を取得（キャッシュあり）
 */
function sosc_fetch_update_meta()
{
    // URLに対してキャッシュキー取得
    $cache_key = 'sosc_update_meta_' . md5(SOSC_UPDATE_META_URL);
    $cached = get_site_transient($cache_key);
    if ($cached !== false) return sosc_validate_update_meta($cached) ? $cached : null;
    // 更新メタ情報JSONを取得
    $args = [
        'timeout' => 10,
        'headers' => [
            'Accept' => 'application/json',
        ],
    ];
    // 通信自体が失敗した場合（DNS/タイムアウト等）
    $res = wp_remote_get(SOSC_UPDATE_META_URL, $args);
    if (is_wp_error($res)) {
        set_site_transient($cache_key, null, 10 * MINUTE_IN_SECONDS);
        return null;
    }
    // HTTPステータスをチェック（200系以外は失敗扱い）
    $code = wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) {
        set_site_transient($cache_key, null, 10 * MINUTE_IN_SECONDS);
        return null;
    }
    // レスポンスボディ（JSON文字列）を取得して配列に変換
    $body = wp_remote_retrieve_body($res);
    $meta = json_decode($body, true);
    // JSONの最低要件チェック
    if (!sosc_validate_update_meta($meta)) {
        set_site_transient($cache_key, null, 10 * MINUTE_IN_SECONDS);
        return null;
    }
    // キャッシュ（30分）
    set_site_transient($cache_key, $meta, 30 * MINUTE_IN_SECONDS);
    return $meta;
}

/**
 * 更新通知（プラグイン一覧の「更新があります」）
 */
add_filter('site_transient_update_plugins', function ($transient) {
    // WordPressが更新チェックをしていない状態なら何もしない
    if (!is_object($transient) || empty($transient->checked)) {
        return $transient;
    }
    // 自分の情報
    $plugin_file = SOSC_PLUGIN_BASENAME;    // 自分のパス
    $current_ver = SOSC_VERSION;            // 自分のバージョン情報
    // 更新メタ取得
    $meta = sosc_fetch_update_meta();
    // 通信障害時はWordPressが既に保持している更新状態を壊さない。
    if (!$meta) {
        return $transient;
    }
    // 更新サーバが返してきた最新バージョン
    $new_ver = $meta['version'];
    // バージョン比較
    if (version_compare($current_ver, $new_ver, '<')) {
        $update = (object) [
            'id'          => 'w.org/plugins/' . SOSC_UPDATE_SLUG,
            'slug'        => SOSC_UPDATE_SLUG,              // プラグインスラッグ
            'plugin'      => $plugin_file,                  // pluginsディレクトリから見たファイルパス
            'new_version' => $new_ver,                      // 更新後最新バージョン
            'package'     => $meta['download_url'],         // ダウンロードURL
            'url'         => sosc_update_display_url($meta['homepage'] ?? ''), // プラグインの説明ページURL。
            'tested'      => $meta['tested'] ?? '',         // 動作確認済みWPバージョン
            'requires'    => $meta['requires'] ?? '',       // 最低WPバージョン
            'requires_php' => $meta['requires_php'] ?? '',  // 最低PHPバージョン
        ];
        // このプラグインに「更新あり」を差し込む
        $transient->response[$plugin_file] = $update;
        unset($transient->no_update[$plugin_file]);
    } else {
        // 更新不要なら「なし」にしておく（任意）
        $item = (object) [
            'id'          => 'w.org/plugins/' . SOSC_UPDATE_SLUG,
            'slug'        => SOSC_UPDATE_SLUG,
            'plugin'      => $plugin_file,
            'new_version' => $current_ver,            // 更新なしなので現在のバージョン
            'url'         => sosc_update_display_url($meta['homepage'] ?? ''),
            'package'     => '',                      // 更新なしなので空
            'icons'       => $meta['icons'] ?? [],
            'banners'     => $meta['banners'] ?? [],
            'tested'      => $meta['tested'] ?? '',
            'requires'    => $meta['requires'] ?? '',
            'requires_php' => $meta['requires_php'] ?? '',
        ];

        // 「更新なし」リストに入れる
        $transient->no_update[$plugin_file] = $item;

        // 念のため「更新あり」側に残ってたら消す
        if (isset($transient->response[$plugin_file])) {
            unset($transient->response[$plugin_file]);
        }
    }
    return $transient;
});

/**
 * プラグイン詳細（「詳細を表示」リンクの画面）
 */
add_filter('plugins_api', function ($result, $action, $args) {
    // 「プラグイン情報取得」以外のアクションは無視
    if ($action !== 'plugin_information') return $result;
    // うちのプラグイン以外は無視
    if (empty($args->slug) || $args->slug !== SOSC_UPDATE_SLUG) return $result;
    // 自前更新メタ取得（失敗なら既定の挙動に任せる）
    $meta = sosc_fetch_update_meta();
    if (!$meta) return $result;
    // WordPressプラグイン情報オブジェクト
    $info = (object) [
        'name'          => $meta['name'] ?? 'ShonanOnlineShortCode',    // プラグインタイトル
        'slug'          => SOSC_UPDATE_SLUG,                            // プラグインスラッグ
        'version'       => $meta['version'],                            // 最新バージョン番号
        // 表示用情報
        'author'        => $meta['author'] ?? '',           // 作者名
        'author_profile' => sosc_update_display_url($meta['author_profile'] ?? ''), // 作者プロフィールURL(なしでもOK)
        'homepage'      => sosc_update_display_url($meta['homepage'] ?? ''), // プラグイン公式ページURL
        // 互換性情報（表示・判断用）
        'requires'      => $meta['requires'] ?? '',         // 最低WPバージョン
        'tested'        => $meta['tested'] ?? '',           // 動作確認済みWPバージョン
        'requires_php'  => $meta['requires_php'] ?? '',     // 最低PHPバージョン
        'download_link' => $meta['download_url'],           // ダウンロードURL
        'last_updated'  => $meta['last_updated'] ?? '',     // 更新日時（表示用）
        // 詳細画面のタブ内容(settings.phpから取得) 
        'sections'      => [
            'description'  => SOSC_DESCRIPTION, // 説明文
            'installation' => INSTALLATION,     // インストール
            'changelog'    => CHANGELOG,        // 更新ログ
        ],
        // 上部のバナー画像
        'banners' => [
            'low' => SOSC_BANNER,
            'high'=> SOSC_BANNER
        ],  
        // プラグイン一覧のアイコン
        'icons'   => [
            '1x' => SOSC_ICON,
            '2x' => SOSC_ICON
        ],
    ];
    return $info;
}, 10, 3);
