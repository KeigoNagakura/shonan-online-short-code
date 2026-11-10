<?php
// Isolated contract check for the public GitHub release response.
define('ABSPATH', __DIR__ . '/');
define('SOSC_UPDATE_META_URL', 'https://api.github.com/repos/KeigoNagakura/shonan-online-short-code/releases/latest');
define('SOSC_PLUGIN_BASENAME', 'so-short-code/so_short_code.php');
function add_filter($name, $callback, $priority = 10, $arguments = 1) {
    $GLOBALS['filters'][$name] = $callback;
}
function wp_parse_url($url, $component = -1) {
    return parse_url($url, $component);
}
require dirname(__DIR__) . '/src/so-short-code/update/update.php';

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
if (!$meta || $meta['version'] !== '3.8.6' || $meta['download_url'] !== $url) {
    throw new RuntimeException('Valid release was rejected');
}
foreach ([
    str_replace('github.com', 'example.com', $url),
    str_replace('/v3.8.6/', '/v3.8.7/', $url),
    str_replace('/so-short-code-03.08.06.zip', '/so-short-code-03.08.07.zip', $url),
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
echo "GitHub release update contract: OK\n";
