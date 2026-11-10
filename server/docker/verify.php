<?php
foreach (array('mysqli', 'mbstring', 'zip', 'openssl', 'gd', 'imagick') as $extension) {
    if (!extension_loaded($extension)) { WP_CLI::error('Missing extension: ' . $extension); }
}
$required = json_decode(file_get_contents('/opt/wp-local/required-plugins.json'), true);
$plugins = get_plugins();
foreach ($required as $slug => $version) {
    $found = false;
    foreach ($plugins as $file => $data) {
        if (strpos($file, $slug . '/') !== 0) { continue; }
        if (!is_plugin_active($file)) { WP_CLI::error('Inactive: ' . $slug); }
        if ($data['Version'] !== $version && strpos($data['Version'], $version . '.') !== 0) {
            WP_CLI::error('Unexpected version: ' . $slug . ' ' . $data['Version']);
        }
        $found = true;
        break;
    }
    if (!$found) { WP_CLI::error('Missing plugin: ' . $slug); }
}
if (!is_plugin_active('so-short-code/so_short_code.php')) { WP_CLI::error('Development plugin inactive'); }
if (!defined('SOSC_VERSION') || SOSC_VERSION !== '3.8.5') { WP_CLI::error('Unexpected development plugin version'); }
foreach (array('sosc_info_list01', 'sosc_wc_item_list01', 'sosc_wc_documents') as $shortcode) {
    if (!shortcode_exists($shortcode)) { WP_CLI::error('Shortcode missing: ' . $shortcode); }
}
if (!is_writable(ABSPATH) || !is_writable(WP_CONTENT_DIR . '/sosc-backups')) {
    WP_CLI::error('Backup paths are not writable');
}
if (!get_user_by('login', 'wp_local_admin')) { WP_CLI::error('Local administrator missing'); }
if (is_wp_error(wp_authenticate('wp_local_admin', getenv('WP_LOCAL_ADMIN_PASSWORD')))) {
    WP_CLI::error('Local administrator password authentication failed');
}
if (get_option('home') !== 'https://localhost' || get_option('siteurl') !== 'https://localhost') {
    WP_CLI::error('Database URL mismatch');
}
WP_CLI::success('Extensions, plugins, versions, shortcodes, paths and local administrator verified.');
