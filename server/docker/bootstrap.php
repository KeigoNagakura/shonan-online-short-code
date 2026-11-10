<?php
// Executed with wp eval-file, only against the isolated development DB.
if (wp_get_environment_type() !== 'local' || !in_array(home_url(), array('http://localhost', 'https://localhost'), true)) {
    WP_CLI::error('Unexpected environment');
}
$password = getenv('WP_LOCAL_ADMIN_PASSWORD');
if (!$password || strlen($password) < 24) { WP_CLI::error('Local credentials missing'); }
$local_user = get_user_by('login', 'wp_local_admin');
if (!$local_user) {
    $id = wp_insert_user(array(
        'user_login' => 'wp_local_admin', 'user_pass' => $password,
        'user_email' => 'wp-local-admin@example.invalid', 'role' => 'administrator',
        'display_name' => 'Local development administrator',
    ));
    if (is_wp_error($id)) { WP_CLI::error($id); }
    $local_user = get_user_by('id', $id);
} elseif (!wp_check_password($password, $local_user->user_pass, $local_user->ID)) {
    $result = wp_update_user(array('ID' => $local_user->ID, 'user_pass' => $password));
    if (is_wp_error($result)) { WP_CLI::error($result); }
    $local_user = get_user_by('id', $local_user->ID);
}
if (!in_array('administrator', $local_user->roles, true)) { $local_user->set_role('administrator'); }
update_option('blog_public', '0');
update_option('osaru_wp_initialized', '1', false);
update_option('siteurl', 'https://localhost');
update_option('home', 'https://localhost');
flush_rewrite_rules(true);
WP_CLI::success('Local administrator and permalink setup verified.');
