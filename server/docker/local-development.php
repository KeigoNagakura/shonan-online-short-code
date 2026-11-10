<?php
/** Plugin Name: WP local development safeguards */
if (!defined('ABSPATH')) { exit; }
// No mail leaves the development copy. Run scheduled tasks explicitly through WP-CLI.
add_filter('pre_wp_mail', '__return_true');
add_filter('http_request_args', static function ($args) {
    $args['sslcertificates'] = '/etc/ssl/certs/ca-certificates.crt';
    return $args;
});
add_filter('auto_update_plugin', '__return_false');
add_filter('auto_update_theme', '__return_false');
add_filter('site_transient_update_plugins', static function ($value) {
    if (is_object($value) && isset($value->response['so-short-code/so_short_code.php'])) {
        unset($value->response['so-short-code/so_short_code.php']);
    }
    return $value;
}, 100);
add_filter('upgrader_pre_install', static function ($result, $extra) {
    if (isset($extra['plugin']) && $extra['plugin'] === 'so-short-code/so_short_code.php') {
        return new WP_Error('local_source', '開発中のプラグインはソースコード側で更新してください。');
    }
    return $result;
}, 10, 2);
