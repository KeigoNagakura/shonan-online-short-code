<?php
/**
 * Packing settings.
 *
 * @package ShonanOnlineShortCode
 */

namespace ShonanOnline\ShortCode\Packing;

final class Settings {
	const REVISION_KEY                 = 'packing_revision';
	const READY_KEY                    = 'packing_runtime_ready';
	const FIXED_SKU_LIMIT              = '20';
	const FIXED_ATTENTION_HOURS        = '24';
	const FIXED_OVERDUE_HOURS          = '48';
	const FIXED_LOG_RETENTION_DAYS     = '30';
	const FIXED_CUSTOMER_MESSAGE       = 'このご注文は複数の箱で発送する場合があります。';
	const FIXED_PACKING_QUANTITY_GUIDE = 100;

	/**
	 * Defaults and validation metadata.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function schema() {
		return array(
			'enabled'                 => array(
				'default' => '0',
				'type'    => 'bool',
			),
			'use_weight'              => array(
				'default' => '0',
				'type'    => 'bool',
			),
			'use_cool'                => array(
				'default' => '0',
				'type'    => 'bool',
			),
			'use_margin'              => array(
				'default' => '0',
				'type'    => 'bool',
			),
			'use_keep_flat'           => array(
				'default' => '0',
				'type'    => 'bool',
			),
			'sku_limit'               => array(
				'default' => '20',
				'type'    => 'int',
				'min'     => 1,
				'max'     => 100,
			),
			'quantity_limit'          => array(
				'default' => '100',
				'type'    => 'int',
				'min'     => 1,
				'max'     => 200,
			),
			'weight_limit'            => array(
				'default' => '30000',
				'type'    => 'int',
				'min'     => 1,
				'max'     => 1000000,
			),
			'individual_delivery_id'  => array(
				'default' => '',
				'type'    => 'id',
			),
			'individual_shipping_id'  => array(
				'default' => '',
				'type'    => 'id',
			),
			'individual_price_policy' => array(
				'default' => 'fixed',
				'type'    => 'enum',
				'values'  => array( 'fixed', 'adjust' ),
			),
			'customer_message'        => array(
				'default' => 'このご注文は複数の箱で発送する場合があります。',
				'type'    => 'text',
			),
			'log_retention_days'      => array(
				'default' => '30',
				'type'    => 'int',
				'min'     => 1,
				'max'     => 365,
			),
			'attention_hours'         => array(
				'default' => '24',
				'type'    => 'int',
				'min'     => 1,
				'max'     => 168,
			),
			'overdue_hours'           => array(
				'default' => '48',
				'type'    => 'int',
				'min'     => 1,
				'max'     => 168,
			),
		);
	}

	public static function key( $name ) {
		return 'packing_' . sanitize_key( $name );
	}

	public static function get( $name ) {
		$schema = self::schema();
		if ( ! isset( $schema[ $name ] ) ) {
			return null;
		}
		$fixed = self::fixed_values();
		if ( isset( $fixed[ $name ] ) ) {
			return $fixed[ $name ];
		}
		return sosc_get_value( self::key( $name ), $schema[ $name ]['default'] );
	}

	public static function enabled() {
		return self::feature_selected() && '1' === (string) sosc_get_value( self::READY_KEY, '0' );
	}

	public static function feature_selected() {
		return '1' === (string) sosc_get_value( 'packing_feature_is_check', '0' );
	}

	public static function set_runtime_ready( $ready ) {
		return sosc_set_value( self::READY_KEY, $ready ? '1' : '0' );
	}

	public static function all() {
		$values = array();
		foreach ( self::schema() as $name => $unused ) {
			$values[ $name ] = self::get( $name );
		}
		return $values;
	}

	public static function validate( $input ) {
		$clean  = array();
		$errors = new \WP_Error();
		foreach ( self::schema() as $name => $rule ) {
			$value = isset( $input[ $name ] ) ? $input[ $name ] : ( 'bool' === $rule['type'] ? '0' : $rule['default'] );
			if ( ! is_scalar( $value ) ) {
				/* translators: %s: setting name. */
				$errors->add( $name, sprintf( __( '%s must contain a single value.', 'so-short-code' ), $name ) );
				continue;
			}
			switch ( $rule['type'] ) {
				case 'bool':
					$clean[ $name ] = empty( $value ) ? '0' : '1';
					break;
				case 'int':
					if ( ! is_scalar( $value ) || ! preg_match( '/^\d+$/', (string) $value ) || (int) $value < $rule['min'] || (int) $value > $rule['max'] ) {
						$errors->add( $name, sprintf( '%sは%d～%dの整数で入力してください。', $name, $rule['min'], $rule['max'] ) );
					} else {
						$clean[ $name ] = (string) (int) $value;
					}
					break;
				case 'id':
					$clean[ $name ] = '' === (string) $value ? '' : (string) absint( $value );
					break;
				case 'enum':
					$clean[ $name ] = in_array( $value, $rule['values'], true ) ? $value : $rule['default'];
					break;
				case 'url':
					$clean[ $name ] = '' === trim( (string) $value ) ? '' : esc_url_raw( $value, array( 'https' ) );
					break;
				default:
					$clean[ $name ] = sanitize_textarea_field( $value );
			}
		}
		foreach ( self::fixed_values() as $name => $value ) {
			$clean[ $name ] = $value;
		}
		if ( isset( $clean['attention_hours'], $clean['overdue_hours'] ) && (int) $clean['overdue_hours'] <= (int) $clean['attention_hours'] ) {
			$errors->add( 'overdue_hours', '長期滞留時間は注意時間より大きくしてください。' );
		}
		return $errors->has_errors() ? $errors : $clean;
	}

	private static function fixed_values() {
		return array(
			'enabled'            => '1',
			'sku_limit'          => self::FIXED_SKU_LIMIT,
			'customer_message'   => self::FIXED_CUSTOMER_MESSAGE,
			'log_retention_days' => self::FIXED_LOG_RETENTION_DAYS,
			'attention_hours'    => self::FIXED_ATTENTION_HOURS,
			'overdue_hours'      => self::FIXED_OVERDUE_HOURS,
		);
	}

	public static function save( $input ) {
		if ( get_option( 'sosc_settings_migration_blocked', false ) ) {
			return new \WP_Error( 'settings_migration_blocked', '既存設定に重複した異なる値があります。設定移行を解決するまで箱詰め設定を保存できません。' );
		}
		$clean = self::validate( $input );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}
		$readiness = null;
		if ( '1' === $clean['enabled'] ) {
			$readiness = Readiness::check( $clean );
			if ( $readiness->has_errors() ) {
				return $readiness;
			}
		}
		global $wpdb;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new \WP_Error( 'packing_transaction_start_failed', 'データベースのトランザクションを開始できませんでした。' );
		}
		foreach ( $clean as $name => $value ) {
			$result = sosc_set_value( self::key( $name ), $value );
			if ( is_wp_error( $result ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $result;
			}
		}
		$result = self::bump_revision();
		if ( is_wp_error( $result ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $result;
		}
		$result = self::set_runtime_ready( true );
		if ( is_wp_error( $result ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $result;
		}
		if ( $readiness instanceof \WP_Error ) {
			$readiness_values = array(
				'readiness_checked_at'   => Database::now(),
				'readiness_acknowledged' => $readiness->has_errors() ? '1' : '0',
				'readiness_result'       => wp_json_encode( $readiness->get_error_messages(), JSON_UNESCAPED_UNICODE ),
			);
			foreach ( $readiness_values as $name => $value ) {
				$result = sosc_set_value( self::key( $name ), $value );
				if ( is_wp_error( $result ) ) {
					$wpdb->query( 'ROLLBACK' );
					return $result;
				}
			}
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'settings_commit_failed', '設定を確定できませんでした。保存前の状態を維持しています。' );
		}
		return true;
	}

	public static function revision() {
		return max( 1, (int) sosc_get_value( self::REVISION_KEY, '1' ) );
	}

	public static function bump_revision() {
		$result = sosc_set_value( self::REVISION_KEY, (string) ( self::revision() + 1 ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( self::feature_selected() ) {
			$readiness = Readiness::check();
			$result    = self::set_runtime_ready( ! $readiness->has_errors() );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		return true;
	}
}
