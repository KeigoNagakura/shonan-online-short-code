<?php
/** Owned local fixture for the real 5,000-row CSV upload test. Run with wp eval-file. */
if ( ! defined( 'ABSPATH' ) || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Local WordPress only.' );
}
global $wpdb;
$mode = $args[0] ?? '';
$marker = 'sosc-csv-5000-20261111';
$visual_code = 'SOSC-VISUAL-20261020-001';
$option = 'sosc_csv_5000_fixture_state';
$sku_table = $wpdb->prefix . 'usces_skus';
$packing_table = \ShonanOnline\ShortCode\Packing\Database::table( 'skus' );
$log_table = \ShonanOnline\ShortCode\Packing\Database::table( 'logs' );
$visual = $wpdb->get_row( $wpdb->prepare( "SELECT post_id,meta_id FROM {$sku_table} WHERE code=%s", $visual_code ), ARRAY_A );
$state = get_option( $option, null );
if ( 'create' === $mode ) {
	if ( ! $visual || 'sosc-visual-20261020' !== get_post_meta( (int) $visual['post_id'], '_sosc_visual_fixture', true ) ) {
		throw new RuntimeException( 'Owned visual product fixture is required.' );
	}
	$post_id = (int) $visual['post_id'];
	$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sku_table} WHERE post_id=%d AND code LIKE %s", $post_id, $wpdb->esc_like( $marker ) . '%' ) );
	$packing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$packing_table} WHERE post_id=%d", $post_id ) );
	if ( null !== $state || $existing || $packing ) {
		throw new RuntimeException( 'Fixture marker or packing data already exists; inspect before proceeding.' );
	}
	$revision = sosc_get_value( 'packing_revision', null );
	if ( ! add_option( $option, array( 'post_id' => $post_id, 'revision_exists' => null !== $revision, 'revision' => $revision ), '', false ) ) {
		throw new RuntimeException( 'Could not record fixture ownership.' );
	}
	for ( $start = 2; $start <= 5000; $start += 250 ) {
		$values = array();
		$params = array();
		for ( $i = $start; $i < min( $start + 250, 5001 ); ++$i ) {
			$values[] = '(%d,%s,%s,%d,%d,%s,%d)';
			array_push( $params, $post_id, sprintf( '%s-%04d', $marker, $i ), 'fixture', 1000, 1000, '個', $i );
		}
		$sql = "INSERT INTO {$sku_table} (post_id,code,name,price,cprice,unit,sort) VALUES " . implode( ',', $values );
		if ( false === $wpdb->query( $wpdb->prepare( $sql, $params ) ) ) {
			throw new RuntimeException( 'Could not create the owned SKU batch.' );
		}
	}
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id,meta_id,code,name,weight FROM {$sku_table} WHERE post_id=%d AND (code=%s OR code LIKE %s) ORDER BY meta_id", $post_id, $visual_code, $wpdb->esc_like( $marker ) . '%' ), ARRAY_A );
	if ( 5000 !== count( $rows ) ) {
		throw new RuntimeException( 'Fixture SKU count is not 5000.' );
	}
	$file = '/tmp/sosc-csv-5000.csv';
	$handle = fopen( $file, 'w' );
	if ( ! $handle ) {
		throw new RuntimeException( 'Could not create fixture CSV.' );
	}
	fputcsv( $handle, array( 'post_id','sku_meta_id','product_name','sku_code','sku_name','width','length','depth','margin_required','margin','welcart_weight' ) );
	foreach ( $rows as $row ) {
		fputcsv( $handle, array( $row['post_id'], $row['meta_id'], 'fixture', $row['code'], $row['name'], '10', '10', '10', '0', '0', $row['weight'] ) );
	}
	fclose( $handle );
	echo "fixture_skus=5000 csv=/tmp/sosc-csv-5000.csv\n";
} elseif ( 'status' === $mode ) {
	$post_id = is_array( $state ) ? (int) $state['post_id'] : ( $visual ? (int) $visual['post_id'] : 0 );
	$extra = $post_id ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sku_table} WHERE post_id=%d AND code LIKE %s", $post_id, $wpdb->esc_like( $marker ) . '%' ) ) : 0;
	$packing = $post_id ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$packing_table} WHERE post_id=%d", $post_id ) ) : 0;
	echo 'owned=' . ( is_array( $state ) ? '1' : '0' ) . ' extra_skus=' . $extra . ' packing_skus=' . $packing . PHP_EOL;
} elseif ( 'cleanup' === $mode ) {
	if ( ! is_array( $state ) || ! isset( $state['post_id'] ) ) {
		throw new RuntimeException( 'No owned fixture state; refusing cleanup.' );
	}
	$post_id = (int) $state['post_id'];
	if ( 'sosc-visual-20261020' !== get_post_meta( $post_id, '_sosc_visual_fixture', true ) ) {
		throw new RuntimeException( 'Fixture post ownership differs; refusing cleanup.' );
	}
	$wpdb->delete( $packing_table, array( 'post_id' => $post_id ), array( '%d' ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$sku_table} WHERE post_id=%d AND code LIKE %s", $post_id, $wpdb->esc_like( $marker ) . '%' ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$log_table} WHERE reason_code=%s AND context LIKE %s", 'sku_csv_imported', '%' . $wpdb->esc_like( $marker ) . '%' ) );
	if ( $state['revision_exists'] ) {
		sosc_set_value( 'packing_revision', $state['revision'] );
	} else {
		sosc_delete_by_name( 'packing_revision' );
	}
	delete_option( $option );
	if ( file_exists( '/tmp/sosc-csv-5000.csv' ) ) {
		unlink( '/tmp/sosc-csv-5000.csv' );
	}
	echo "csv fixture cleaned\n";
} else {
	throw new RuntimeException( 'Usage: wp eval-file tests/packing-csv-5000-fixture.php create|status|cleanup' );
}
