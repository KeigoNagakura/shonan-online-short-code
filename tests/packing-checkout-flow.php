<?php
/** Local DB transaction smoke test: shipping, pre-order snapshot, and order confirmation. */
if ( ! defined( 'ABSPATH' ) || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Local WordPress only.' );
}
require_once WP_PLUGIN_DIR . '/so-short-code/vendor/autoload.php';
global $wpdb, $usces;
$original_usces = $usces;
$sku_table = $wpdb->prefix . 'usces_skus';
$packing_skus = \ShonanOnline\ShortCode\Packing\Database::table( 'skus' );
$boxes = \ShonanOnline\ShortCode\Packing\Database::table( 'boxes' );
$orders = \ShonanOnline\ShortCode\Packing\Database::table( 'orders' );
$post_id = random_int( 1500000000, 1900000000 );
$code = 'sosc-checkout-' . wp_generate_uuid4();
if ( $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$sku_table} WHERE post_id=%d", $post_id ) ) ) {
	throw new RuntimeException( 'Fixture post ID already exists.' );
}
if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
	throw new RuntimeException( 'Could not start checkout transaction.' );
}
try {
	$now = \ShonanOnline\ShortCode\Packing\Database::now();
	if ( 1 !== $wpdb->insert( $sku_table, array( 'post_id' => $post_id, 'code' => $code, 'name' => 'fixture', 'price' => 1000, 'weight' => '100' ) ) ) {
		throw new RuntimeException( 'Could not insert fixture SKU.' );
	}
	$meta_id = (int) $wpdb->insert_id;
	if ( 1 !== $wpdb->insert( $packing_skus, array( 'post_id' => $post_id, 'sku_meta_id' => $meta_id, 'width' => 10, 'length' => 10, 'depth' => 10, 'created_at' => $now, 'updated_at' => $now ) ) ) {
		throw new RuntimeException( 'Could not insert fixture packing dimensions.' );
	}
	if ( 1 !== $wpdb->insert( $boxes, array( 'name' => 'fixture', 'name_key' => random_bytes( 20 ), 'inner_width' => 30, 'inner_length' => 30, 'inner_depth' => 30, 'delivery_method_id' => 101, 'shipping_charge_id' => 201, 'priority' => 1, 'enabled' => 1, 'created_at' => $now, 'updated_at' => $now ) ) ) {
		throw new RuntimeException( 'Could not insert fixture box.' );
	}
	sosc_set_value( 'packing_feature_is_check', '1' );
	sosc_set_value( 'packing_runtime_ready', '1' );
	if ( ! \ShonanOnline\ShortCode\Packing\Settings::enabled() ) {
		throw new RuntimeException( 'Packing did not become ready inside transaction.' );
	}
	$usces = new class {
		public $cart;
		public $options = array(
			'postage_privilege' => 0,
			'delivery_method' => array( array( 'id' => 101, 'name' => 'Fixture delivery', 'charge' => 201 ) ),
			'shipping_charge' => array( array( 'id' => 201, 'name' => 'Fixture charge', 'JP' => array( '東京都' => 880 ) ) ),
		);
		public $pre_id;
		public function get_delivery_method_index( $id ) { return 101 === (int) $id ? 0 : false; }
		public function get_shipping_charge_index( $id ) { return 201 === (int) $id ? 0 : false; }
		public function getItemDivision( $id ) { return 'shipped'; }
		public function getItemIndividualSCharge( $id ) { return false; }
		public function get_current_pre_order_id() { return $this->pre_id; }
		public function getPayments( $name ) { return array( 'settlement' => 'fixture-external' === $name ? 'test_gateway' : 'transfer' ); }
	};
	$usces->cart = new class {
		public $entry;
		public function get_entry() { return $this->entry; }
		public function set_order_entry( $values ) { $this->entry['order'] = array_merge( $this->entry['order'], $values ); }
	};
	$cart = array( array( 'post_id' => $post_id, 'quantity' => 1, 'sku' => $code ) );
	foreach ( array( 'fixture-transfer' => false, 'fixture-external' => true ) as $payment => $external ) {
		$usces->pre_id = 'sosc-checkout-' . wp_generate_uuid4();
		$entry = array( 'delivery' => array( 'country' => 'JP', 'pref' => '東京都' ), 'order' => array( 'delivery_method' => 101, 'payment_name' => $payment ) );
		$usces->cart->entry = $entry;
		$charge = \ShonanOnline\ShortCode\Packing\WelcartIntegration::shipping( $external ? 881 : 880, $cart, $entry );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT status,snapshot,shipping_total FROM {$orders} WHERE pre_order_id=%s", $usces->pre_id ), ARRAY_A );
		$snapshot = $row ? json_decode( $row['snapshot'], true ) : null;
		if ( 880.0 !== (float) $charge || ! $row || 'temporary' !== $row['status'] || ! is_array( $snapshot ) || 'packed' !== $snapshot['result']['status'] || $external !== $snapshot['external_payment'] ) {
			throw new RuntimeException( 'Checkout snapshot or payment classification failed: ' . $payment . ' charge=' . (string) $charge . ' row=' . wp_json_encode( $row ) . ' snapshot=' . wp_json_encode( $snapshot ) );
		}
		$order_id = random_int( 1000000000, 1999999999 );
		$args = array( 'order_id' => $order_id, 'entry' => array( 'reserve' => array( 'pre_order_id' => $usces->pre_id ) ) );
		\ShonanOnline\ShortCode\Packing\WelcartIntegration::register_order( $args );
		\ShonanOnline\ShortCode\Packing\WelcartIntegration::register_order( $args );
		$confirmed = $wpdb->get_row( $wpdb->prepare( "SELECT order_id,status FROM {$orders} WHERE pre_order_id=%s", $usces->pre_id ), ARRAY_A );
		if ( ! $confirmed || (int) $confirmed['order_id'] !== $order_id || 'confirmed' !== $confirmed['status'] ) {
			throw new RuntimeException( 'Order registration failed: ' . $payment );
		}
	}
	echo "Packing local checkout: transfer and external-payment snapshots OK\n";
} finally {
	$usces = $original_usces;
	$wpdb->query( 'ROLLBACK' );
}
