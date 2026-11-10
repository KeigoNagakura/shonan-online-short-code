<?php
if (!defined('ABSPATH')) exit;
/**
 * フロント：CSS/JS読み込み
 * ※基本的に先読み込み(テンプレート優先)
 */
function sosc_enqueue_first_front_assets() {
    if (!sosc_front_assets_required()) {
        return;
    }
    sosc_enqueue_first_assets();
}
add_action('wp_enqueue_scripts', 'sosc_enqueue_first_front_assets', 0);

/**
 * Make the jQuery `$` alias available throughout WordPress.
 */
function sosc_enqueue_jquery_dollar_alias() {
    wp_enqueue_script('sosc-jquery-dollar', SOSC_JS_PATH . 'jquery-dollar.js', ['jquery'], SOSC_CSS_JS_TIME, false);
}
add_action('wp_enqueue_scripts', 'sosc_enqueue_jquery_dollar_alias', 0);
add_action('admin_enqueue_scripts', 'sosc_enqueue_jquery_dollar_alias', 0);

/**
 * 管理画面：特定ページだけCSS/JS読み込み
 * ※基本的に先読み込み(テンプレート優先)
 */
function sosc_enqueue_admin_first_assets($hook) {
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if (!$page || !in_array($page, SOSC_ADMIN_PAGES, true)) {
        return;
    }
    sosc_enqueue_first_assets();
}
add_action('admin_enqueue_scripts', 'sosc_enqueue_admin_first_assets', 0);

/**
 * フロント：CSS/JS読み込み
 * ※後読み込み
 */
function sosc_enqueue_last_front_assets() {
    if (!sosc_front_assets_required()) {
        return;
    }
    // 基本読み込み
    sosc_enqueue_last_assets();
    // 機能用読み込み
    sosc_function_assets();
}
add_action('wp_enqueue_scripts', 'sosc_enqueue_last_front_assets', 10);

/**
 * Ensure assets are available for shortcodes rendered outside post content.
 *
 * @param mixed  $return Early shortcode return value.
 * @param string $tag    Shortcode tag.
 * @return mixed
 */
function sosc_enqueue_dynamic_shortcode_assets($return, $tag) {
    if (0 === strpos((string) $tag, 'sosc_')) {
        sosc_enqueue_first_assets();
        sosc_enqueue_last_assets();
        sosc_function_assets();
        if (did_action('wp_head') && !did_action('wp_footer')) {
            wp_print_styles(['bootstrap-scoped', 'sosc-common-css', 'sosc-cf-add-css', 'sosc-cw-cus-css', 'sosc-code-css', 'sosc-other-css']);
        }
    }
    return $return;
}
add_filter('pre_do_shortcode_tag', 'sosc_enqueue_dynamic_shortcode_assets', 10, 2);

/**
 * 管理画面：特定ページだけCSS/JS読み込み
 * ※基本的に先読み込み(テンプレート優先)
 */
function sosc_enqueue_admin_last_assets($hook) {
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if (!$page || !in_array($page, SOSC_ADMIN_PAGES, true)) {
        return;
    }
    sosc_enqueue_last_assets();
    wp_enqueue_script('sosc-admin-tabs', SOSC_JS_PATH . 'admin-tabs.js', [], SOSC_CSS_JS_TIME, true);
}
add_action('admin_enqueue_scripts', 'sosc_enqueue_admin_last_assets', 10);

/**
 * Save the main plugin settings and redirect back to the settings tab.
 */
function sosc_handle_save_settings() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You are not allowed to perform this action.', 'so-short-code'));
    }
    check_admin_referer('sosc_save_settings', 'sosc_nonce');

    global $wpdb;
    if (false === $wpdb->query('START TRANSACTION')) {
        sosc_redirect_settings_notice('設定保存用のトランザクションを開始できませんでした。データベースの状態を確認してください。', true);
    }
    $results = [];
    foreach (['h1_is_check', 'contents_tm_is_check', 'contents_bm_is_check', 'contact_form_is_check', 'welcart_pick_up_in_store_is_check', 'welcart_service_item_store_is_check', 'monthly_backup_is_check'] as $key) {
        $results[] = sosc_set_value($key, isset($_POST[$key]) ? '1' : '0');
    }
    $font = isset($_POST['font_change_select']) ? sanitize_key(wp_unslash($_POST['font_change_select'])) : 'default';
    $results[] = sosc_set_value('font_change_select', in_array($font, ['default', 'noto-serif-jp'], true) ? $font : 'default');

    $backup_enabled = isset($_POST['monthly_backup_is_check']);
    $password = $backup_enabled && \ShonanOnline\ShortCode\Backup\Manager::encryption_available() && isset($_POST['monthly_backup_password']) && is_string($_POST['monthly_backup_password'])
        ? wp_unslash($_POST['monthly_backup_password'])
        : '';
    $remove_password = isset($_POST['monthly_backup_password_remove']);
    if ($remove_password) {
        $results[] = \ShonanOnline\ShortCode\Backup\Manager::clear_encryption_password();
    } elseif ('' !== $password) {
        $length = strlen($password);
        if ($length < 12 || $length > 128 || preg_match('/[\\x00-\\x1F\\x7F]/', $password)) {
            $results[] = new WP_Error('invalid_backup_password', 'バックアップ暗号化パスワードは、制御文字を含まない12文字以上128文字以下で入力してください。');
        } else {
            $results[] = \ShonanOnline\ShortCode\Backup\Manager::set_encryption_password($password);
        }
    }
    $results[] = sosc_set_value('monthly_backup_encryption', $remove_password ? 'none' : (\ShonanOnline\ShortCode\Backup\Manager::has_encryption_password() || '' !== $password ? 'aes256' : 'none'));

    $packing_enabled = isset($_POST['packing_feature_is_check']) ? '1' : '0';
    $previous_packing = sosc_get_value('packing_feature_is_check', '0');
    $results[] = sosc_set_value('packing_feature_is_check', $packing_enabled);
    if ((string) $previous_packing !== $packing_enabled || '0' === $packing_enabled) {
        $results[] = \ShonanOnline\ShortCode\Packing\Settings::set_runtime_ready(false);
    }

    $error = null;
    foreach ($results as $result) {
        if (is_wp_error($result)) {
            $error = $result;
            break;
        }
    }
    if ($error || false === $wpdb->query('COMMIT')) {
        $wpdb->query('ROLLBACK');
        $message = $error ? $error->get_error_message() : '設定を保存できませんでした。';
        sosc_redirect_settings_notice($message, true);
    }
    \ShonanOnline\ShortCode\Backup\Plugin::reschedule();
    sosc_redirect_settings_notice('設定を保存しました。', false);
}
add_action('admin_post_sosc_save_settings', 'sosc_handle_save_settings');

/**
 * Redirect to the settings tab with a one-time result notice.
 */
function sosc_redirect_settings_notice($message, $error) {
    $key = 'sosc_settings_notice_' . get_current_user_id();
    set_transient($key, ['message' => sanitize_text_field($message), 'error' => (bool) $error], MINUTE_IN_SECONDS);
    wp_safe_redirect(add_query_arg(['page' => 'sosc_menu', 'tab' => 'other'], admin_url('admin.php')));
    exit;
}

/**
 * 基本読み込み
 * ※基本的に先読み込み(テンプレート優先)
 */
function sosc_enqueue_first_assets() {
    // CSS読み込み
    wp_enqueue_style('bootstrap-scoped',    SOSC_CSS_PATH . 'bootstrap.scoped.min.css',                                     [],                     SOSC_CSS_JS_TIME, 'all');
}

/**
 * 基本読み込み
 */
function sosc_enqueue_last_assets() {
    // CSS読み込み
    wp_enqueue_style('sosc-common-css', SOSC_CSS_PATH . 'sosc-common.css', ['bootstrap-scoped'], SOSC_CSS_JS_TIME, 'all');
    wp_enqueue_style('sosc-code-css',       SOSC_CSS_PATH . 'short-code.css',                                               ['sosc-common-css'],    SOSC_CSS_JS_TIME, 'all');
    if ('1' === (string) sosc_get_value('contact_form_is_check', '0')) {
        wp_enqueue_style('sosc-cf-add-css', SOSC_CSS_PATH . 'contact-form-add.css', ['sosc-common-css'], SOSC_CSS_JS_TIME, 'all');
    }
    foreach (\ShonanOnline\ShortCode\FrontDisplay::MARKERS as $marker) {
        if (sosc_queried_content_contains($marker)) {
            \ShonanOnline\ShortCode\FrontDisplay::enqueue_swell();
            break;
        }
    }
    if (sosc_other_front_features_enabled()) {
        wp_enqueue_style('sosc-other-css', SOSC_CSS_PATH . 'other_function.css', ['sosc-common-css'], SOSC_CSS_JS_TIME, 'all');
        wp_enqueue_script('sosc-other-js', SOSC_JS_PATH . 'other_function.js', ['jquery'], SOSC_CSS_JS_TIME, true);
    }
}

/**
 * Check the current singular post content without rendering it.
 *
 * @param string $needle Content marker.
 * @return bool
 */
function sosc_queried_content_contains($needle) {
    if (!is_singular()) {
        return false;
    }
    $post = get_queried_object();
    return $post instanceof WP_Post && \ShonanOnline\ShortCode\FrontDisplay::contains((string) $post->post_content, $needle);
}

/**
 * Determine whether general front-end behavior is enabled.
 *
 * @return bool
 */
function sosc_other_front_features_enabled() {
    foreach (['h1_is_check', 'contents_tm_is_check', 'contents_bm_is_check', 'contact_form_is_check'] as $setting) {
        if ('1' === (string) sosc_get_value($setting, '0')) {
            return true;
        }
    }
    return 'default' !== (string) sosc_get_value('font_change_select', 'default');
}

/**
 * 機能用読み込み
 */
function sosc_function_assets() {
    // WelCart店頭受取機能状況取得
    $welcartPickUpInStore = sosc_get_value('welcart_pick_up_in_store_is_check', '0');
    if ($welcartPickUpInStore == '1') {
        wp_enqueue_style('sosc-wc-pick-up-in-store-css', SOSC_CSS_PATH . 'wc_pick_up_in_store.css', [], SOSC_CSS_JS_TIME, 'all');
        wp_enqueue_script('sosc-wc-pick-up-in-store-js', SOSC_JS_PATH . 'short-code/wc_pick_up_in_store.js', ['jquery'], SOSC_CSS_JS_TIME, true);
    }
    // WelCartサービス商品機能状況取得
    $welcartServiceItemStore = sosc_get_value('welcart_service_item_store_is_check', '0');
    if ($welcartServiceItemStore == '1') {
        wp_enqueue_script('sosc-wc-service-item-store-js', SOSC_JS_PATH . 'short-code/wc_service_item_store.js', ['jquery'], SOSC_CSS_JS_TIME, true);
    }
}

/**
 * Determine whether the current front-end request needs plugin assets.
 */
function sosc_front_assets_required() {
    $required = false;
    if (is_singular()) {
        $post = get_queried_object();
        $content = $post instanceof WP_Post ? (string) $post->post_content : '';
        foreach (array_merge(['[sosc_'], \ShonanOnline\ShortCode\FrontDisplay::MARKERS) as $marker) {
            if (sosc_queried_content_contains($marker)) {
                $required = true;
                break;
            }
        }
    }
    if (!$required) {
        $settings = [
            'h1_is_check',
            'contents_tm_is_check',
            'contents_bm_is_check',
            'contact_form_is_check',
            'welcart_pick_up_in_store_is_check',
            'welcart_service_item_store_is_check',
        ];
        foreach ($settings as $setting) {
            if ('1' === (string) sosc_get_value($setting, '0')) {
                $required = true;
                break;
            }
        }
        $required = $required || 'default' !== (string) sosc_get_value('font_change_select', 'default');
    }
    return (bool) apply_filters('sosc_force_front_assets', $required);
}

// /**
//  * タイトルからカテゴリー:やタグ:を外す
//  */
// add_filter('get_the_archive_title', function ($title) {
//     if (is_category()) {
//         $title = single_cat_title('', false);
//     } elseif (is_tag()) {
//         $title = single_tag_title('', false);
//     } elseif (is_author()) {
//         $title = '<span class="vcard">' . get_the_author() . '</span>';
//     }
//     return $title;
// });
