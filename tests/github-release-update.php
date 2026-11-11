<?php
// Isolated contract check for the public GitHub release response.
define('ABSPATH', __DIR__ . '/');
define('SOSC_UPDATE_META_URL', 'https://api.github.com/repos/KeigoNagakura/shonan-online-short-code/releases/latest');
define('SOSC_PLUGIN_BASENAME', 'so-short-code/so_short_code.php');
define('SOSC_VERSION', '3.8.5');
define('SOSC_UPDATE_SLUG', 'so-short-code');
define('MINUTE_IN_SECONDS', 60);
function add_filter($name, $callback, $priority = 10, $arguments = 1) {
    $GLOBALS['filters'][$name] = $callback;
}
function wp_parse_url($url, $component = -1) {
    return parse_url($url, $component);
}
function get_site_transient($name) {
    return $GLOBALS['transients'][$name] ?? false;
}
function set_site_transient($name, $value, $expiration) {
    $GLOBALS['transients'][$name] = $value;
}
function wp_remote_get($url, $args) {
    return ['code' => 200, 'body' => json_encode($GLOBALS['release_response'])];
}
function wp_remote_retrieve_response_code($response) {
    return $response['code'];
}
function wp_remote_retrieve_body($response) {
    return $response['body'];
}
function is_wp_error($value) {
    return false;
}
function esc_url_raw($url, $protocols = []) {
    return $url;
}
require dirname(__DIR__) . '/src/so-short-code/update/update.php';
$header = file_get_contents(dirname(__DIR__) . '/src/so-short-code/so_short_code.php');
if (strpos($header, 'Update URI: https://github.com/KeigoNagakura/shonan-online-short-code') === false) {
    throw new RuntimeException('The plugin does not declare its update source');
}

$url = 'https://github.com/KeigoNagakura/shonan-online-short-code/releases/download/v3.8.6/so-short-code-03.08.06.zip';
$release = [
    'tag_name' => 'v3.8.6',
    'draft' => false,
    'prerelease' => false,
    'published_at' => '2026-11-10T09:00:00Z',
    'assets' => [[
        'state' => 'uploaded',
        'browser_download_url' => $url,
        'digest' => 'sha256:' . str_repeat('a', 64),
    ]],
];
$meta = sosc_release_to_update_meta($release);
if (!$meta || $meta['version'] !== '3.8.6' || $meta['download_url'] !== $url || $meta['requires_php'] !== '8.2' || strpos($header, 'Requires PHP: 8.2') === false) {
    throw new RuntimeException('Valid release was rejected');
}
foreach ([
    str_replace('github.com', 'example.com', $url),
    str_replace('/v3.8.6/', '/v3.8.7/', $url),
    str_replace('/so-short-code-03.08.06.zip', '/so-short-code-03.08.07.zip', $url),
    str_replace('github.com', 'user@github.com', $url),
    str_replace('github.com', 'github.com:443', $url),
] as $bad) {
    $release['assets'][0]['browser_download_url'] = $bad;
    if (sosc_release_to_update_meta($release)) throw new RuntimeException('Invalid asset was accepted');
}
$release['assets'][0]['browser_download_url'] = $url;
$release['assets'][0]['digest'] = null;
if (sosc_release_to_update_meta($release)) throw new RuntimeException('Missing digest was accepted');
if (!$GLOBALS['filters']['auto_update_plugin'](false, (object) ['plugin' => SOSC_PLUGIN_BASENAME])) {
    throw new RuntimeException('Plugin auto update was not enabled');
}
if ($GLOBALS['filters']['auto_update_plugin'](false, (object) ['plugin' => 'other/plugin.php'])) {
    throw new RuntimeException('Unrelated plugin auto update changed');
}
$GLOBALS['release_response'] = [
    'tag_name' => 'v3.8.6', 'draft' => false, 'prerelease' => false,
    'published_at' => '2026-11-10T09:00:00Z',
    'assets' => [[
        'state' => 'uploaded', 'browser_download_url' => $url,
        'digest' => 'sha256:' . str_repeat('a', 64),
    ]],
];
$transient = (object) ['checked' => [SOSC_PLUGIN_BASENAME => SOSC_VERSION], 'response' => [], 'no_update' => []];
$checked = $GLOBALS['filters']['site_transient_update_plugins']($transient);
if (($checked->response[SOSC_PLUGIN_BASENAME]->new_version ?? null) !== '3.8.6') {
    throw new RuntimeException('WordPress update offer was not created');
}
if (($checked->response[SOSC_PLUGIN_BASENAME]->id ?? null) !== 'https://github.com/KeigoNagakura/shonan-online-short-code') {
    throw new RuntimeException('Update identity is incorrect');
}
if (isset($checked->response['other/plugin.php'])) {
    throw new RuntimeException('Unrelated plugin update changed');
}
if (!empty($argv[1])) {
    $live = json_decode(file_get_contents($argv[1]), true);
    $live_meta = sosc_release_to_update_meta($live);
    if (!$live_meta || $live_meta['version'] !== substr((string) ($live['tag_name'] ?? ''), 1)) {
        throw new RuntimeException('Actual GitHub release response was rejected');
    }
}
echo "GitHub release update contract: OK\n";
