<?php
// Read-only WP-CLI check for the plugin's scheduled jobs and automatic updates.
if ( ! defined( 'ABSPATH' ) || ! function_exists( 'wp_next_scheduled' ) ) {
	throw new RuntimeException( 'Run with wp eval-file in the target WordPress environment.' );
}
$plugin = 'so-short-code/so_short_code.php';
$packing = wp_next_scheduled( 'sosc_packing_daily' );
$backup_enabled = function_exists( 'sosc_get_value' ) && '1' === sosc_get_value( 'monthly_backup_is_check', '0' );
$backup = wp_next_scheduled( 'sosc_monthly_backup' );
$global_auto_disabled = defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED;
$cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
$report = array(
	'environment' => wp_get_environment_type(),
	'plugin_active' => is_plugin_active( $plugin ),
	'plugin_version' => defined( 'SOSC_VERSION' ) ? SOSC_VERSION : null,
	'wp_cron_disabled' => $cron_disabled,
	'external_cron_required' => $cron_disabled,
	'automatic_updater_disabled' => $global_auto_disabled,
	'plugin_auto_update_filter' => (bool) apply_filters( 'auto_update_plugin', false, (object) array( 'plugin' => $plugin ) ),
	'packing_daily_next_utc' => $packing ? gmdate( 'c', $packing ) : null,
	'packing_daily_overdue' => $packing && $packing < time(),
	'backup_enabled' => $backup_enabled,
	'backup_next_utc' => $backup ? gmdate( 'c', $backup ) : null,
	'backup_overdue' => $backup && $backup < time(),
);
echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
