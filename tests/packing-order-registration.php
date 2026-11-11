<?php
// Run with wp eval-file in the isolated local WordPress DB. All fixture writes roll back.
if ( ! defined( 'ABSPATH' ) || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Local WordPress only.' );
}
global $wpdb;
$orders = \ShonanOnline\ShortCode\Packing\Database::table( 'orders' );
$logs = \ShonanOnline\ShortCode\Packing\Database::table( 'logs' );
$pre_id = 'sosc-test-' . wp_generate_uuid4();
$order_id = random_int( 1000000000, 1999999999 );
if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
	throw new RuntimeException( 'Could not start fixture transaction.' );
}
try {
	$now = \ShonanOnline\ShortCode\Packing\Database::now();
	$inserted = $wpdb->insert( $orders, array(
		'pre_order_id' => $pre_id,
		'status' => 'temporary',
		'created_at' => $now,
		'updated_at' => $now,
		'history' => '[]',
	) );
	if ( 1 !== $inserted ) {
		throw new RuntimeException( 'Could not create provisional fixture.' );
	}
	$args = array( 'order_id' => $order_id, 'entry' => array( 'reserve' => array( 'pre_order_id' => $pre_id ) ) );
	\ShonanOnline\ShortCode\Packing\WelcartIntegration::register_order( $args );
	\ShonanOnline\ShortCode\Packing\WelcartIntegration::register_order( $args );
	$record = $wpdb->get_row( $wpdb->prepare( "SELECT order_id,status FROM {$orders} WHERE pre_order_id=%s", $pre_id ), ARRAY_A );
	$errors = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$logs} WHERE order_id=%d AND reason_code=%s", $order_id, 'order_registration_failed' ) );
	if ( ! $record || (int) $record['order_id'] !== $order_id || 'confirmed' !== $record['status'] || 0 !== $errors ) {
		throw new RuntimeException( 'Repeated order registration did not remain confirmed without failure logs.' );
	}
	echo "Packing order registration retry: OK\n";
} finally {
	$wpdb->query( 'ROLLBACK' );
}
