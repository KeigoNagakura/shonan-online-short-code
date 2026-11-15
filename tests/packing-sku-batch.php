<?php
// Run with wp eval-file in the local WordPress DB. The owned SKU is rolled back.
if ( ! defined( 'ABSPATH' ) || 'local' !== wp_get_environment_type() || ! class_exists( 'ShonanOnline\ShortCode\Packing\Admin' ) ) {
	throw new RuntimeException( 'Local WordPress with packing module is required.' );
}
global $wpdb;
$sku_table = $wpdb->prefix . 'usces_skus';
$post_id = random_int( 1500000000, 1900000000 );
if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
	throw new RuntimeException( 'Could not start fixture transaction.' );
}
try {
	if ( 1 !== $wpdb->insert( $sku_table, array( 'post_id' => $post_id, 'code' => 'sosc-sku-batch-' . wp_generate_uuid4(), 'name' => 'fixture', 'price' => 1000 ) ) ) {
		throw new RuntimeException( 'Could not create owned SKU fixture.' );
	}
	$meta_id = (int) $wpdb->insert_id;
	$method = new ReflectionMethod( 'ShonanOnline\ShortCode\Packing\Admin', 'load_import_skus' );
	$rows = array_fill( 0, 5000, array( 'post_id' => $post_id, 'sku_meta_id' => $meta_id ) );
	$key = $post_id . ':' . $meta_id;
	$before = $wpdb->num_queries;
	$result = $method->invoke( null, $rows, false );
	if ( is_wp_error( $result ) || ! isset( $result[ $key ] ) || 1 !== $wpdb->num_queries - $before ) {
		throw new RuntimeException( 'Batch lookup did not resolve repeated SKU rows in one query.' );
	}
	$rows[] = array( 'post_id' => 0, 'sku_meta_id' => PHP_INT_MAX );
	$before = $wpdb->num_queries;
	$result = $method->invoke( null, $rows, false );
	if ( is_wp_error( $result ) || isset( $result[ '0:' . PHP_INT_MAX ] ) || 1 !== $wpdb->num_queries - $before ) {
		throw new RuntimeException( 'Batch lookup did not handle an absent SKU.' );
	}
	for ( $i = 1; $i <= 250; ++$i ) {
		$rows[] = array( 'post_id' => 0, 'sku_meta_id' => PHP_INT_MAX - $i );
	}
	$before = $wpdb->num_queries;
	$result = $method->invoke( null, $rows, false );
	if ( is_wp_error( $result ) || ! isset( $result[ $key ] ) || 2 !== $wpdb->num_queries - $before ) {
		throw new RuntimeException( 'Batch lookup did not respect the 250-ID chunk boundary.' );
	}
	$before = $wpdb->num_queries;
	$result = $method->invoke( null, array( $rows[0] ), true );
	if ( is_wp_error( $result ) || ! isset( $result[ $key ] ) || 1 !== $wpdb->num_queries - $before ) {
		throw new RuntimeException( 'Locked SKU lookup failed.' );
	}
	echo "Packing SKU batch lookup: OK\n";
} finally {
	$wpdb->query( 'ROLLBACK' );
}
