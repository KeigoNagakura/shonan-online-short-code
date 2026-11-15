<?php
// Run with wp eval-file in the local WordPress environment; no data is changed.
if ( ! defined( 'ABSPATH' ) || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Local WordPress only.' );
}
$method = new ReflectionMethod( 'ShonanOnline\ShortCode\Packing\Admin', 'csv_identifier' );
foreach ( array( '1' => 1, '123' => 123, (string) PHP_INT_MAX => PHP_INT_MAX ) as $input => $expected ) {
	if ( $expected !== $method->invoke( null, (string) $input ) ) {
		throw new RuntimeException( 'Valid CSV identifier was rejected.' );
	}
}
$integer = new ReflectionMethod( 'ShonanOnline\ShortCode\Packing\Admin', 'csv_integer' );
if ( 12 !== $integer->invoke( null, '12', 0, 10000 ) || 0 !== $integer->invoke( null, '000', 0, 10000 ) || PHP_INT_MAX !== $integer->invoke( null, (string) PHP_INT_MAX, 0, PHP_INT_MAX ) || false !== $integer->invoke( null, (string) PHP_INT_MAX . '0', 0, PHP_INT_MAX ) || false !== $integer->invoke( null, '12xyz', 0, 10000 ) || false !== $integer->invoke( null, '-1', 0, 10000 ) ) {
	throw new RuntimeException( 'Product dimension validation did not reject malformed integers.' );
}
foreach ( array( '', '0', '01', '-1', '1xyz', '1.0', ' 1', (string) PHP_INT_MAX . '0' ) as $input ) {
	if ( false !== $method->invoke( null, $input ) ) {
		throw new RuntimeException( 'Invalid CSV identifier was accepted.' );
	}
}
$settings = \ShonanOnline\ShortCode\Packing\Settings::validate( array( 'individual_delivery_id' => '12xyz' ) );
if ( ! is_wp_error( $settings ) || ! $settings->get_error_message( 'individual_delivery_id' ) ) {
	throw new RuntimeException( 'Malformed delivery method ID was accepted.' );
}
$settings = \ShonanOnline\ShortCode\Packing\Settings::validate( array( 'log_retention_days' => str_repeat( '9', 40 ) ) );
if ( ! is_wp_error( $settings ) || ! $settings->get_error_message( 'log_retention_days' ) ) {
	throw new RuntimeException( 'Oversized integer setting was accepted.' );
}
$settings = \ShonanOnline\ShortCode\Packing\Settings::validate( array( 'individual_delivery_id' => '12' ) );
if ( is_wp_error( $settings ) || '12' !== $settings['individual_delivery_id'] ) {
	throw new RuntimeException( 'Valid delivery method ID was rejected.' );
}
$save_box = new ReflectionMethod( 'ShonanOnline\ShortCode\Packing\Admin', 'save_box' );
$update_individual = new ReflectionMethod( 'ShonanOnline\ShortCode\Packing\Admin', 'update_individual' );
$resolve_duplicate = new ReflectionMethod( 'ShonanOnline\ShortCode\Packing\Admin', 'resolve_settings_duplicate' );
$original_post = $_POST;
try {
	$_POST = array( 'record_id' => '12xyz', 'business_revision' => '0' );
	if ( 'invalid_individual_target' !== $update_individual->invoke( null )->get_error_code() ) {
		throw new RuntimeException( 'Malformed order record ID was accepted.' );
	}
	$_POST = array( 'record_id' => '12', 'business_revision' => '0xyz' );
	if ( 'invalid_individual_target' !== $update_individual->invoke( null )->get_error_code() ) {
		throw new RuntimeException( 'Malformed order revision was accepted.' );
	}
	$_POST = array( 'param_name' => 'test', 'keep_id' => '12xyz' );
	if ( 'invalid_resolution' !== $resolve_duplicate->invoke( null )->get_error_code() ) {
		throw new RuntimeException( 'Malformed settings duplicate ID was accepted.' );
	}
	$_POST = array(
		'name' => 'test box', 'inner_width' => '12xyz', 'inner_length' => '12', 'inner_depth' => '12',
		'delivery_method_id' => '12', 'shipping_charge_id' => '12', 'priority' => '1',
	);
	if ( ! is_wp_error( $save_box->invoke( null ) ) ) {
		throw new RuntimeException( 'Malformed box dimension was accepted.' );
	}
	$_POST['inner_width'] = '12';
	$_POST['delivery_method_id'] = '12xyz';
	if ( ! is_wp_error( $save_box->invoke( null ) ) ) {
		throw new RuntimeException( 'Malformed box delivery method ID was accepted.' );
	}
} finally {
	$_POST = $original_post;
}
echo "Packing CSV identifier validation: OK\n";
