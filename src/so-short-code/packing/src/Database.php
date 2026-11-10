<?php
/**
 * Packing database schema.
 *
 * @package ShonanOnlineShortCode
 */

namespace ShonanOnline\ShortCode\Packing;

final class Database {
	const VERSION = '3';

	public static function table( $suffix ) {
		global $wpdb;
		return $wpdb->prefix . 'sosc_packing_' . $suffix;
	}

	public static function maybe_install() {
		if ( self::VERSION !== get_option( 'sosc_packing_db_version', '' ) ) {
			self::install();
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collate = $wpdb->get_charset_collate();

		$sql   = array();
		$sql[] = 'CREATE TABLE ' . self::table( 'boxes' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			name_key varbinary(400) NOT NULL,
			inner_width int unsigned NOT NULL,
			inner_length int unsigned NOT NULL,
			inner_depth int unsigned NOT NULL,
			delivery_method_id bigint(20) unsigned NOT NULL,
			shipping_charge_id bigint(20) unsigned NOT NULL,
			priority smallint unsigned NOT NULL DEFAULT 1,
			enabled tinyint unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY name_key (name_key),
			KEY enabled_method (enabled,delivery_method_id),
			KEY priority (priority)
		) {$collate};";
		$sql[] = 'CREATE TABLE ' . self::table( 'products' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			cool_required tinyint unsigned NOT NULL DEFAULT 0,
			keep_flat tinyint unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY post_id (post_id)
		) {$collate};";
		$sql[] = 'CREATE TABLE ' . self::table( 'skus' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			sku_meta_id bigint(20) unsigned NOT NULL,
			width int unsigned NOT NULL,
			length int unsigned NOT NULL,
			depth int unsigned NOT NULL,
			margin_required tinyint unsigned NOT NULL DEFAULT 0,
			margin int unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY post_sku (post_id,sku_meta_id),
			KEY sku_meta_id (sku_meta_id)
		) {$collate};";
		$sql[] = 'CREATE TABLE ' . self::table( 'orders' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NULL,
			pre_order_id varchar(100) NULL,
			origin varchar(32) NOT NULL DEFAULT 'checkout',
			fingerprint char(64) NULL,
			status varchar(32) NOT NULL DEFAULT 'temporary',
			reason_code varchar(64) NULL,
			shipping_total decimal(14,2) NULL,
			individual_state varchar(16) NULL,
			assignee_user_id bigint(20) unsigned NULL,
			actual_shipping_method varchar(255) NULL,
			outcome varchar(32) NULL,
			price_difference_state varchar(32) NULL,
			contacted_at datetime NULL,
			checkout_consented_at datetime NULL,
			consented_at datetime NULL,
			adjustment_completed_at datetime NULL,
			completed_at datetime NULL,
			operation_note text NULL,
			business_revision bigint(20) unsigned NOT NULL DEFAULT 1,
			setting_revision bigint(20) unsigned NOT NULL DEFAULT 1,
			expires_at datetime NULL,
			snapshot longtext NULL,
			history longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY order_id (order_id),
			UNIQUE KEY pre_order_id (pre_order_id),
			KEY state_updated (individual_state,updated_at),
			KEY status_expiry (status,expires_at)
		) {$collate};";
		$sql[] = 'CREATE TABLE ' . self::table( 'logs' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			error_id char(36) NULL,
			reason_code varchar(64) NOT NULL,
			level varchar(16) NOT NULL DEFAULT 'warning',
			order_id bigint(20) unsigned NULL,
			context longtext NULL,
			created_at datetime NOT NULL,
			expires_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY error_id (error_id),
			KEY reason_created (reason_code,created_at),
			KEY expires_at (expires_at)
		) {$collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
		foreach ( array( 'boxes', 'products', 'skus', 'orders', 'logs' ) as $suffix ) {
			$table = self::table( $suffix );
			if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				return false;
			}
		}
		update_option( 'sosc_packing_db_version', self::VERSION, false );
		return true;
	}

	public static function now() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Encode data for persistent JSON storage.
	 *
	 * @param mixed $value Value to encode.
	 * @return string|\WP_Error
	 */
	public static function encode_json( $value ) {
		$json = wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return new \WP_Error( 'packing_json_encode_failed', 'JSON data could not be encoded.' );
		}
		return $json;
	}

	public static function log( $reason_code, $context = array(), $level = 'warning', $order_id = null, $error_id = null, $days = null ) {
		global $wpdb;
		$days            = null === $days ? (int) Settings::get( 'log_retention_days' ) : (int) $days;
		$encoded_context = self::encode_json( $context );
		if ( is_wp_error( $encoded_context ) ) {
			$encoded_context = '{"encoding_error":true}';
		}
		$result = $wpdb->insert(
			self::table( 'logs' ),
			array(
				'error_id'    => $error_id,
				'reason_code' => sanitize_key( $reason_code ),
				'level'       => sanitize_key( $level ),
				'order_id'    => $order_id ? absint( $order_id ) : null,
				'context'     => $encoded_context,
				'created_at'  => self::now(),
				'expires_at'  => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS * max( 1, $days ) ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		if ( false === $result ) {
			error_log( 'ShonanOnlineShortCode packing log failure: ' . sanitize_key( $reason_code ) . ' / ' . $wpdb->last_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new \WP_Error( 'packing_log_failed', '箱詰めログを保存できませんでした。' );
		}
		return true;
	}
}
