<?php
// Exercise the real HTTPS/login flow without disabling certificate validation.
$cookies = tempnam(sys_get_temp_dir(), 'wp-local-cookies-');
$origin = isset($argv[1]) && $argv[1] === 'http' ? 'http://localhost' : 'https://localhost';
if ($origin === 'http://localhost') {
    $handle = curl_init($origin . '/');
    curl_setopt_array($handle, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 45,
    ));
    curl_exec($handle);
    $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $location = curl_getinfo($handle, CURLINFO_REDIRECT_URL);
    $error = curl_error($handle);
    curl_close($handle);
    if (!in_array($status, array(301, 302, 307, 308), true) || strpos($location, 'https://localhost/') !== 0) {
        throw new RuntimeException('HTTP did not redirect to HTTPS: status=' . $status . ' location=' . $location . ' ' . $error);
    }
    echo "Verified http://localhost redirects to https://localhost/\n";
    if (is_file($cookies)) { unlink($cookies); }
    return;
}
function fetch_local($path, $post = null) {
    global $cookies, $origin;
    $handle = curl_init($origin . $path);
    curl_setopt_array($handle, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CAINFO => '/etc/apache2/local-ssl/localhost.crt',
        CURLOPT_CONNECT_TO => array('localhost:443:127.0.0.1:443'),
        CURLOPT_COOKIEFILE => $cookies,
        CURLOPT_COOKIEJAR => $cookies,
    ));
    if ($post !== null) { curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $body = curl_exec($handle);
    $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    if ($body === false || $status !== 200) {
        throw new RuntimeException('HTTPS request failed: ' . $path . ' status=' . $status . ' ' . $error);
    }
    return $body;
}
try {
    fetch_local('/');
    fetch_local('/wp-login.php');
    $body = fetch_local('/wp-login.php', array(
        'log' => 'wp_local_admin', 'pwd' => getenv('WP_LOCAL_ADMIN_PASSWORD'),
        'wp-submit' => 'Log In', 'testcookie' => '1',
        'redirect_to' => $origin . '/wp-admin/',
    ));
    if (strpos($body, 'id="wpadminbar"') === false) { throw new RuntimeException('Administrator login failed'); }
    $body = fetch_local('/wp-admin/admin.php?page=sosc_menu');
    if (strpos($body, 'sosc_') === false) { throw new RuntimeException('Plugin page not found'); }
    fetch_local('/wp-json/');
    echo "Verified {$origin}: homepage, administrator login, plugin page and REST permalink\n";
} finally {
    if (is_file($cookies)) { unlink($cookies); }
}
