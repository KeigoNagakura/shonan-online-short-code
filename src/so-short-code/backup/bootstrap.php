<?php
/**
 * Monthly backup module bootstrap.
 *
 * @package ShonanOnlineShortCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'ShonanOnline\\ShortCode\\Backup\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = __DIR__ . '/src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
);

\ShonanOnline\ShortCode\Backup\Plugin::boot();
