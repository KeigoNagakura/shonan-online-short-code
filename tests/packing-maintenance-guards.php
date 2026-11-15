<?php
// Run with wp eval-file in the isolated local WordPress DB. All fixture writes roll back.
if ( ! defined( 'ABSPATH' ) || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Local WordPress only.' );
}
global $wpdb;
$table = \ShonanOnline\ShortCode\Packing\Database::table( 'orders' );
$now = \ShonanOnline\ShortCode\Packing\Database::now();
$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status=%s AND expires_at IS NOT NULL AND expires_at < %s", 'temporary', $now ) );
if ( 0 !== $existing ) {
	throw new RuntimeException( 'Existing expired temporary orders prevent an isolated maintenance test.' );
}
if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
	throw new RuntimeException( 'Could not start fixture transaction.' );
}
try {
	$expired = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
	$ids = array();
	foreach ( array( 'temporary' => '{}', 'confirmed' => '{}' ) as $status => $snapshot ) {
		$inserted = $wpdb->insert( $table, array(
			'pre_order_id' => 'sosc-maintenance-' . wp_generate_uuid4(),
			'status' => $status,
			'snapshot' => $snapshot,
			'expires_at' => $expired,
			'created_at' => $expired,
			'updated_at' => $expired,
			'history' => '[]',
		) );
		if ( 1 !== $inserted ) {
			throw new RuntimeException( 'Could not create maintenance fixture.' );
		}
		$ids[ $status ] = (int) $wpdb->insert_id;
	}
	$result = \ShonanOnline\ShortCode\Packing\Maintenance::run();
	if ( is_wp_error( $result ) || true !== $result ) {
		throw new RuntimeException( 'Maintenance failed on isolated fixture.' );
	}
	$temporary = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id=%d", $ids['temporary'] ) );
	$confirmed = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE id=%d", $ids['confirmed'] ) );
	if ( null !== $temporary || 'confirmed' !== $confirmed ) {
		throw new RuntimeException( 'Maintenance did not preserve confirmed orders while removing expired temporary orders.' );
	}
	echo "Packing maintenance guarded cleanup: OK\n";
} finally {
	$wpdb->query( 'ROLLBACK' );
}
