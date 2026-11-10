<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * --------------------------
 * init_plugin_table.php
 * 有効時テーブル作成
 * --------------------------
 */

/**
 * 有効化時：テーブル作成
 */
function sosc_create_table() {

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table_name      = $wpdb->prefix . 'so_short_code';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table_name} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        param_name varchar(255) NOT NULL,
        param_value text NULL,
        PRIMARY KEY (id),
        KEY param_name (param_name)
    ) {$charset_collate};";

	dbDelta( $sql );
	sosc_migrate_settings_uniqueness();
}

/**
 * Safely migrate the settings key to a unique index.
 *
 * Different duplicate values are never selected automatically. The packing
 * feature remains blocked until an administrator resolves them.
 */
function sosc_migrate_settings_uniqueness() {

	global $wpdb;
	if ( '1' === get_option( 'sosc_settings_unique_migration_version', '' ) ) {
		return;
	}
	$previous_block = get_option( 'sosc_settings_migration_blocked', false );
	if ( is_array( $previous_block ) && $previous_block ) {
		return;
	}

	$table      = $wpdb->prefix . 'so_short_code';
	$duplicates = $wpdb->get_results(
		"SELECT param_name, COUNT(*) AS row_count, COUNT(DISTINCT BINARY param_value) + MAX(param_value IS NULL) AS value_count
         FROM {$table}
         GROUP BY param_name
         HAVING COUNT(*) > 1",
		ARRAY_A
	);
	if ( ! empty( $duplicates ) ) {
		$backup = $table . '_backup_' . gmdate( 'YmdHis' );
		$wpdb->query( "CREATE TABLE {$backup} LIKE {$table}" );
		if ( $wpdb->last_error ) {
			update_option( 'sosc_settings_migration_blocked', 'backup_failed', false );
			return;
		}
		$wpdb->query( "INSERT INTO {$backup} SELECT * FROM {$table}" );
		if ( $wpdb->last_error ) {
			update_option( 'sosc_settings_migration_blocked', 'backup_failed', false );
			return;
		}

		$conflicts = array();
		foreach ( $duplicates as $duplicate ) {
			if ( (int) $duplicate['value_count'] > 1 ) {
				$conflicts[] = sanitize_key( $duplicate['param_name'] );
				continue;
			}
			$max_id = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(id) FROM {$table} WHERE param_name = %s", $duplicate['param_name'] ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE param_name = %s AND id <> %d", $duplicate['param_name'], $max_id ) );
		}
		if ( ! empty( $conflicts ) ) {
			update_option( 'sosc_settings_migration_blocked', $conflicts, false );
			update_option( 'sosc_settings_migration_backup', $backup, false );
			return;
		}
	}

	$has_unique = $wpdb->get_var(
		"SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = DATABASE()
         AND table_name = '" . esc_sql( $table ) . "'
         AND index_name = 'param_name_unique'
         AND non_unique = 0"
	);
	if ( ! $has_unique ) {
		$wpdb->query( "ALTER TABLE {$table} ADD UNIQUE KEY param_name_unique (param_name)" );
		if ( $wpdb->last_error ) {
			update_option( 'sosc_settings_migration_blocked', 'unique_index_failed', false );
			return;
		}
	}
	delete_option( 'sosc_settings_migration_blocked' );
	update_option( 'sosc_settings_unique_migration_version', '1', false );
}
