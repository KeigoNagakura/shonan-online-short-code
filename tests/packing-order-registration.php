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
	$conflicting_order_id = $order_id + 1;
	\ShonanOnline\ShortCode\Packing\WelcartIntegration::register_order( array( 'order_id' => $conflicting_order_id, 'entry' => array( 'reserve' => array( 'pre_order_id' => $pre_id ) ) ) );
	$linked_order_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM {$orders} WHERE pre_order_id=%s", $pre_id ) );
	if ( $order_id !== $linked_order_id ) {
		throw new RuntimeException( 'Conflicting order registration replaced the linked order.' );
	}
	$conflicts = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$logs} WHERE order_id=%d AND reason_code=%s", $conflicting_order_id, 'order_registration_conflict' ) );
	if ( 1 !== $conflicts ) {
		throw new RuntimeException( 'Conflicting order registration was not logged.' );
	}
	$wpdb->update( $orders, array( 'status' => 'uncalculated', 'history' => '[{"event":"keep"}]' ), array( 'pre_order_id' => $pre_id ) );
	\ShonanOnline\ShortCode\Packing\WelcartIntegration::register_order( $args );
	$restored = $wpdb->get_row( $wpdb->prepare( "SELECT status,history FROM {$orders} WHERE pre_order_id=%s", $pre_id ), ARRAY_A );
	if ( ! $restored || 'confirmed' !== $restored['status'] || '[{"event":"keep"}]' !== $restored['history'] ) {
		throw new RuntimeException( 'Retry did not restore linked state while preserving history.' );
	}
	$manual_id = $order_id + 2;
	$history = '[{"event":"keep"}]';
	$manual = $wpdb->insert( $orders, array(
		'order_id' => $manual_id,
		'status' => 'confirmed',
		'history' => $history,
		'operation_note' => 'keep',
		'created_at' => $now,
		'updated_at' => $now,
	) );
	if ( 1 !== $manual ) {
		throw new RuntimeException( 'Could not create manual order fixture.' );
	}
	\ShonanOnline\ShortCode\Packing\WelcartIntegration::register_order( array( 'order_id' => $manual_id, 'entry' => array() ) );
	$preserved = $wpdb->get_row( $wpdb->prepare( "SELECT status,history,operation_note FROM {$orders} WHERE order_id=%d", $manual_id ), ARRAY_A );
	if ( ! $preserved || 'confirmed' !== $preserved['status'] || $history !== $preserved['history'] || 'keep' !== $preserved['operation_note'] ) {
		throw new RuntimeException( 'Repeated manual order registration erased existing state.' );
	}
	if ( is_wp_error( sosc_set_value( 'packing_feature_is_check', '1' ) ) || is_wp_error( sosc_set_value( 'packing_runtime_ready', '1' ) ) ) {
		throw new RuntimeException( 'Could not enable packing inside fixture transaction.' );
	}
	$fresh_id = $order_id + 3;
	\ShonanOnline\ShortCode\Packing\WelcartIntegration::register_order( array( 'order_id' => $fresh_id, 'entry' => array() ) );
	$fresh = $wpdb->get_row( $wpdb->prepare( "SELECT id,status FROM {$orders} WHERE order_id=%d", $fresh_id ), ARRAY_A );
	if ( ! $fresh || 'uncalculated' !== $fresh['status'] ) {
		throw new RuntimeException( 'Fresh manual order was not registered.' );
	}
	$wpdb->update( $orders, array( 'status' => 'confirmed', 'history' => $history ), array( 'id' => $fresh['id'] ) );
	\ShonanOnline\ShortCode\Packing\WelcartIntegration::register_order( array( 'order_id' => $fresh_id, 'entry' => array() ) );
	$fresh_retry = $wpdb->get_row( $wpdb->prepare( "SELECT id,status,history FROM {$orders} WHERE order_id=%d", $fresh_id ), ARRAY_A );
	if ( ! $fresh_retry || $fresh_retry['id'] !== $fresh['id'] || 'confirmed' !== $fresh_retry['status'] || $history !== $fresh_retry['history'] ) {
		throw new RuntimeException( 'Fresh manual order retry replaced its history.' );
	}
	$late_pre_id = 'sosc-test-' . wp_generate_uuid4();
	\ShonanOnline\ShortCode\Packing\WelcartIntegration::register_order( array( 'order_id' => $fresh_id, 'entry' => array( 'reserve' => array( 'pre_order_id' => $late_pre_id ) ) ) );
	$late_link = $wpdb->get_row( $wpdb->prepare( "SELECT id,pre_order_id,history FROM {$orders} WHERE order_id=%d", $fresh_id ), ARRAY_A );
	if ( ! $late_link || $late_link['id'] !== $fresh['id'] || $late_pre_id !== $late_link['pre_order_id'] || $history !== $late_link['history'] ) {
		throw new RuntimeException( 'Late pre-order ID was not linked without losing history.' );
	}
	\ShonanOnline\ShortCode\Packing\WelcartIntegration::register_order( array( 'order_id' => $fresh_id, 'entry' => array( 'reserve' => array( 'pre_order_id' => $pre_id . '-other' ) ) ) );
	$unchanged_pre_id = $wpdb->get_var( $wpdb->prepare( "SELECT pre_order_id FROM {$orders} WHERE order_id=%d", $fresh_id ) );
	if ( $late_pre_id !== $unchanged_pre_id ) {
		throw new RuntimeException( 'Conflicting pre-order ID replaced an existing link.' );
	}
	echo "Packing order registration retry: OK\n";
} finally {
	$wpdb->query( 'ROLLBACK' );
}
