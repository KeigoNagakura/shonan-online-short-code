<?php
// Run with wp eval-file after creating the owned visual fixture.
if ( ! defined( 'ABSPATH' ) || ! class_exists( 'ShonanOnline\ShortCode\Packing\Admin' ) ) {
	throw new RuntimeException( 'Local WordPress with packing module is required.' );
}
global $wpdb;
$sku = $wpdb->get_row( "SELECT post_id, meta_id FROM {$wpdb->prefix}usces_skus ORDER BY meta_id LIMIT 1", ARRAY_A );
if ( ! $sku ) {
	throw new RuntimeException( 'A local fixture SKU is required.' );
}
$method = new ReflectionMethod( 'ShonanOnline\ShortCode\Packing\Admin', 'load_import_skus' );
$rows   = array_fill( 0, 5000, array( 'post_id' => (int) $sku['post_id'], 'sku_meta_id' => (int) $sku['meta_id'] ) );
$before = $wpdb->num_queries;
$result = $method->invoke( null, $rows, false );
$key    = (int) $sku['post_id'] . ':' . (int) $sku['meta_id'];
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
if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
	throw new RuntimeException( 'Could not start read-only locking probe.' );
}
try {
	$before = $wpdb->num_queries;
	$result = $method->invoke( null, array( $rows[0] ), true );
	if ( is_wp_error( $result ) || ! isset( $result[ $key ] ) || 1 !== $wpdb->num_queries - $before ) {
		throw new RuntimeException( 'Locked SKU lookup failed.' );
	}
} finally {
	$wpdb->query( 'ROLLBACK' );
}
echo "Packing SKU batch lookup: OK\n";
