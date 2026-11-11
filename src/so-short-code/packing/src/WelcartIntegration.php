<?php
/**
 * Welcart hooks for packing.
 *
 * @package ShonanOnlineShortCode
 */

namespace ShonanOnline\ShortCode\Packing;

final class WelcartIntegration {
	private static $result;
	private static $error;
	private static $fingerprint;

	public static function register() {
		if ( ! defined( 'USCES_VERSION' ) && ! function_exists( 'usces_the_item' ) ) {
			return;
		}
		add_action( 'usces_action_reg_orderdata', array( __CLASS__, 'register_order' ), 50 );
		add_action( 'usces_action_order_edit_form_detail_bottom', array( __CLASS__, 'order_panel' ), 20, 3 );
		add_action( 'usces_action_del_orderdata', array( __CLASS__, 'delete_order_data' ), 20 );
		add_action( 'usces_action_collective_order_delete_each', array( __CLASS__, 'delete_order_data' ), 20 );
		if ( ! Settings::enabled() ) {
			return;
		}
		add_filter( 'usces_filter_getShippingCharge', array( __CLASS__, 'shipping' ), 20, 3 );
		add_filter( 'usces_filter_cart_check', array( __CLASS__, 'cart_check' ), 20 );
		add_filter( 'usces_filter_get_available_delivery_method', array( __CLASS__, 'available_delivery_methods' ), 20 );
		add_filter( 'usces_is_item_zaiko', array( __CLASS__, 'sku_is_purchasable' ), 20, 5 );
		add_filter( 'usces_filter_delivery_check', array( __CLASS__, 'delivery_check' ), 20 );
		add_filter( 'usces_filter_confirm_shipping_info', array( __CLASS__, 'confirm_shipping' ), 20 );
		add_filter( 'usces_filter_confirm_page_notes', array( __CLASS__, 'confirm_notes' ), 20 );
		add_filter( 'usces_filter_confirm_nextbutton', array( __CLASS__, 'confirm_button_attributes' ), 20 );
		add_filter( 'usces_filter_confirm_inform', array( __CLASS__, 'confirm_purchase_form' ), 20, 5 );
		add_filter( 'usces_filter_send_order_mail_shipping', array( __CLASS__, 'mail_shipping' ), 20, 3 );
		add_action( 'template_redirect', array( __CLASS__, 'validate_purchase_consent' ), 1 );
	}

	public static function cart_check( $message ) {
		global $usces;
		if ( ! isset( $usces->cart ) ) {
			return $message;
		}
		$quantity = 0;
		foreach ( (array) $usces->cart->get_cart() as $row ) {
			$post_id = isset( $row['post_id'] ) ? absint( $row['post_id'] ) : 0;
			if ( ! $post_id || ( method_exists( $usces, 'getItemDivision' ) && 'shipped' !== $usces->getItemDivision( $post_id ) ) || ( method_exists( $usces, 'getItemIndividualSCharge' ) && $usces->getItemIndividualSCharge( $post_id ) ) ) {
				continue;
			}
			$quantity += isset( $row['quantity'] ) ? absint( $row['quantity'] ) : 0;
		}
		$limit = (int) Settings::get( 'quantity_limit' );
		if ( $quantity > $limit ) {
			$message .= sprintf( '箱詰め対象商品は合計%d点まで同時に購入できます。数量を減らすか、注文を分けてください。', $limit );
		}
		return $message;
	}

	public static function available_delivery_methods( $methods ) {
		global $wpdb, $usces;
		if ( '1' !== (string) Settings::get( 'use_cool' ) || ! isset( $usces->cart ) ) {
			return $methods;
		}
		$post_ids = array();
		foreach ( (array) $usces->cart->get_cart() as $row ) {
			$post_id = isset( $row['post_id'] ) ? absint( $row['post_id'] ) : 0;
			if ( $post_id ) {
				$post_ids[] = $post_id;
			}
		}
		$post_ids      = array_values( array_unique( $post_ids ) );
		$requires_cool = false;
		if ( $post_ids ) {
			$placeholders  = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
			$requires_cool = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . Database::table( 'products' ) . " WHERE cool_required=1 AND post_id IN ({$placeholders}) LIMIT 1", $post_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table and placeholders are generated internally.
		}
		if ( ! $requires_cool ) {
			return $methods;
		}
		$entry   = $usces->cart->get_entry();
		$pref    = isset( $entry['delivery']['pref'] ) ? $entry['delivery']['pref'] : ( isset( $entry['customer']['pref'] ) ? $entry['customer']['pref'] : '' );
		$country = ! empty( $entry['delivery']['country'] ) ? $entry['delivery']['country'] : ( ! empty( $entry['customer']['country'] ) ? $entry['customer']['country'] : 'JP' );
		$boxes   = $wpdb->get_results( 'SELECT delivery_method_id,shipping_charge_id FROM ' . Database::table( 'boxes' ) . ' WHERE enabled=1 AND inner_width BETWEEN 1 AND 10000 AND inner_length BETWEEN 1 AND 10000 AND inner_depth BETWEEN 1 AND 10000 ORDER BY priority,id' );
		$valid   = array();
		foreach ( $boxes as $box ) {
			$method_index = $usces->get_delivery_method_index( $box->delivery_method_id );
			$charge_index = $usces->get_shipping_charge_index( $box->shipping_charge_id );
			if ( false === $method_index || false === $charge_index || empty( $usces->options['delivery_method'][ $method_index ]['cool_category'] ) || ! isset( $usces->options['shipping_charge'][ $charge_index ][ $country ][ $pref ] ) ) {
				continue;
			}
			$valid[] = (int) $box->delivery_method_id;
		}
		$valid = array_unique( $valid );
		return array_filter(
			(array) $methods,
			static function ( $method, $key ) use ( $valid ) {
				$method_id = is_object( $method ) && isset( $method->id ) ? $method->id : ( is_array( $method ) && isset( $method['id'] ) ? $method['id'] : ( is_numeric( $method ) ? $method : $key ) );
				return in_array( (int) $method_id, $valid, true );
			},
			ARRAY_FILTER_USE_BOTH
		);
	}

	public static function mail_shipping( $message, $data, $entry ) {
		$result = self::$result;
		if ( ! is_array( $result ) ) {
			global $wpdb;
			$order_id = is_array( $data ) && isset( $data['ID'] ) ? absint( $data['ID'] ) : ( is_object( $data ) && isset( $data->ID ) ? absint( $data->ID ) : 0 );
			if ( $order_id ) {
				$snapshot = $wpdb->get_var( $wpdb->prepare( 'SELECT snapshot FROM ' . Database::table( 'orders' ) . ' WHERE order_id=%d', $order_id ) );
				$decoded  = $snapshot ? json_decode( $snapshot, true ) : null;
				$result   = is_array( $decoded ) && isset( $decoded['result'] ) ? $decoded['result'] : null;
			}
		}
		if ( ! is_array( $result ) ) {
			return $message;
		}
		$message .= "\r\n【梱包・配送について】\r\n";
		if ( 'packed' === $result['status'] && count( $result['boxes'] ) > 1 ) {
			$message .= 'このご注文は' . count( $result['boxes'] ) . "箱での発送予定です。複数の荷物に分かれて届く場合があります。\r\n";
		}
		if ( ! empty( $result['cool'] ) ) {
			$message .= "このご注文はクール便対象商品を含みます。\r\n";
		}
		if ( 'individual' === $result['status'] ) {
			$message .= "店舗で梱包・配送方法を確認し、必要な場合はご連絡します。\r\n";
			if ( 'fixed' === Settings::get( 'individual_price_policy' ) ) {
				$message .= "ご注文時に表示された送料が確定送料です。\r\n";
			} else {
				$message .= "送料差額が生じる場合は、増額前にご連絡して同意を確認します。\r\n";
			}
		}
		$message .= 'ご注文時の送料: ' . $result['shipping'] . "\r\n";
		return $message;
	}

	public static function sku_is_purchasable( $available, $post_id, $sku_code, $status_num, $stock_num ) {
		if ( ! Settings::enabled() || ! $available ) {
			return $available;
		}
		global $wpdb, $usces;
		if ( method_exists( $usces, 'getItemDivision' ) && 'shipped' !== $usces->getItemDivision( $post_id ) ) {
			return $available;
		}
		if ( method_exists( $usces, 'getItemIndividualSCharge' ) && $usces->getItemIndividualSCharge( $post_id ) ) {
			return $available;
		}
		$sku = $wpdb->get_row( $wpdb->prepare( "SELECT meta_id,weight FROM {$wpdb->prefix}usces_skus WHERE post_id=%d AND code=%s LIMIT 1", $post_id, $sku_code ) );
		if ( ! $sku ) {
			return false;
		}
		$config = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'skus' ) . ' WHERE post_id=%d AND sku_meta_id=%d AND width BETWEEN 1 AND 10000 AND length BETWEEN 1 AND 10000 AND depth BETWEEN 1 AND 10000', $post_id, $sku->meta_id ) );
		if ( ! $config ) {
			return false;
		}
		if ( '1' === (string) Settings::get( 'use_margin' ) && $config->margin_required && ( $config->margin < 1 || $config->margin > 1000 || $config->width + 2 * $config->margin > 10000 || $config->length + 2 * $config->margin > 10000 || $config->depth + 2 * $config->margin > 10000 ) ) {
			return false;
		}
		if ( '1' === (string) Settings::get( 'use_weight' ) && absint( $sku->weight ) < 1 ) {
			return false;
		}
		return $available;
	}

	public static function shipping( $charge, $cart, $entry ) {
		if ( ! Settings::enabled() ) {
			self::$fingerprint = null;
			self::$result      = null;
			self::$error       = null;
			return $charge;
		}
		$destination   = array(
			'country' => ! empty( $entry['delivery']['country'] ) ? $entry['delivery']['country'] : ( ! empty( $entry['customer']['country'] ) ? $entry['customer']['country'] : 'JP' ),
			'pref'    => ! empty( $entry['delivery']['pref'] ) ? $entry['delivery']['pref'] : ( isset( $entry['customer']['pref'] ) ? $entry['customer']['pref'] : '' ),
		);
		$free_shipping = self::has_free_shipping( $cart );
		$fingerprint   = hash(
			'sha256',
			wp_json_encode(
				array(
					$cart,
					$destination,
					isset( $entry['order']['delivery_method'] ) ? $entry['order']['delivery_method'] : null,
					$charge,
					$free_shipping,
					Settings::revision(),
				)
			)
		);
		if ( self::$fingerprint === $fingerprint && is_array( self::$result ) ) {
			return isset( self::$result['shipping'] ) ? self::$result['shipping'] : $charge;
		}
		try {
			$result = ( new Calculator() )->calculate( $cart, $entry );
		} catch ( \Throwable $exception ) {
			$error_id = wp_generate_uuid4();
			Database::log( 'shipping_calculation_error', array( 'class' => get_class( $exception ) ), 'error', null, $error_id );
			self::$fingerprint = $fingerprint;
			self::$result      = null;
			self::$error       = new \WP_Error( 'shipping_calculation_error', '現在、配送方法を計算できません。時間をおいて再度お試しください。エラーID: ' . $error_id );
			return $charge;
		}
		self::$fingerprint = $fingerprint;
		if ( is_wp_error( $result ) ) {
			self::$error  = $result;
			self::$result = null;
			Database::log( $result->get_error_code(), array() );
			return $charge;
		}
		self::$error = null;
		if ( 'not_applicable' === $result['status'] ) {
			self::$result = null;
			return $charge;
		}
		if ( ( null !== $charge && 0.0 === (float) $charge ) || $free_shipping ) {
			$result['shipping']      = 0.0;
			$result['free_shipping'] = true;
		}
		if ( 'individual' === $result['status'] && isset( $GLOBALS['usces']->cart ) ) {
			$individual_delivery_id = absint( Settings::get( 'individual_delivery_id' ) );
			if ( $individual_delivery_id ) {
				$GLOBALS['usces']->cart->set_order_entry( array( 'delivery_method' => $individual_delivery_id ) );
			}
		}
		self::$result = $result;
		Database::log(
			isset( $result['reason'] ) ? $result['reason'] : 'packing_calculated',
			array(
				'status'          => $result['status'],
				'box_count'       => isset( $result['boxes'] ) ? count( $result['boxes'] ) : 0,
				'candidate_count' => isset( $result['candidate_count'] ) ? (int) $result['candidate_count'] : 0,
				'budget'          => isset( $result['budget'] ) ? (int) $result['budget'] : null,
				'shipping'        => isset( $result['shipping'] ) ? (float) $result['shipping'] : null,
			),
			'individual' === $result['status'] ? 'warning' : 'info'
		);
		if ( ! self::save_temporary( $result, $fingerprint ) ) {
			$error_id     = wp_generate_uuid4();
			self::$error  = new \WP_Error( 'packing_result_save_failed', '現在、この配送方法を確定できません。時間をおいて再度お試しください。エラーID: ' . $error_id );
			self::$result = null;
			Database::log( 'packing_result_save_failed', array(), 'error', null, $error_id );
			return $charge;
		}
		return (float) $result['shipping'];
	}

	private static function has_free_shipping( $cart ) {
		global $usces;
		$threshold = isset( $usces->options['postage_privilege'] ) ? (float) $usces->options['postage_privilege'] : 0.0;
		return $threshold > 0.0 && method_exists( $usces, 'get_total_price' ) && (float) $usces->get_total_price( $cart ) >= $threshold;
	}

	private static function ensure_result() {
		global $usces;
		if ( null !== self::$result || null !== self::$error || ! Settings::enabled() || ! isset( $usces->cart ) ) {
			return;
		}
		$cart  = $usces->cart->get_cart();
		$entry = $usces->cart->get_entry();
		self::shipping( null, $cart, $entry );
	}

	public static function delivery_check( $message ) {
		self::ensure_result();
		if ( self::$error ) {
			$message .= '<div class="sosc-packing-error" role="alert"><strong>配送方法を確認してください。</strong><br>' . esc_html( self::$error->get_error_message() ) . '</div>';
		}
		return $message;
	}

	public static function confirm_shipping( $html ) {
		self::ensure_result();
		if ( ! is_array( self::$result ) || 'packed' !== self::$result['status'] ) {
			return $html;
		}
		if ( ! empty( self::$result['cool'] ) ) {
			$html .= '<tr class="sosc-packing-cool"><th>配送温度帯</th><td>このご注文はクール便対象商品を含みます。</td></tr>';
		}
		if ( Settings::get( 'customer_message' ) ) {
			$html .= '<tr class="sosc-packing-message"><th>梱包・配送について</th><td>' . nl2br( esc_html( Settings::get( 'customer_message' ) ) ) . '</td></tr>';
		}
		if ( count( self::$result['boxes'] ) > 1 ) {
			$html  .= '<tr class="sosc-packing-boxes"><th>梱包予定</th><td>このご注文は' . esc_html( count( self::$result['boxes'] ) ) . '箱での発送予定です。<ul>';
			$counts = array();
			foreach ( self::$result['boxes'] as $box ) {
				$counts[ $box['name'] ] = isset( $counts[ $box['name'] ] ) ? $counts[ $box['name'] ] + 1 : 1;
			}
			foreach ( $counts as $name => $count ) {
				$html .= '<li>' . esc_html( $name ) . ' × ' . esc_html( $count ) . '</li>';
			}
			$html .= '</ul><span>複数の荷物に分かれて届く場合があります。</span></td></tr>';
		}
		return $html;
	}

	public static function confirm_notes( $html ) {
		self::ensure_result();
		if ( ! is_array( self::$result ) || 'individual' !== self::$result['status'] ) {
			return $html;
		}
		$message = '<section class="sosc-packing-individual" aria-labelledby="sosc-packing-individual-title"><h3 id="sosc-packing-individual-title">配送方法を店舗で確認します</h3><p>商品の重量、数量または組み合わせにより、注文後に店舗が梱包・配送方法を確認します。</p>';
		if ( 'adjust' === Settings::get( 'individual_price_policy' ) ) {
			$message .= '<p><strong>表示送料と実際の送料に差額が生じる場合があります。</strong>増額が必要な場合は事前にご連絡し、同意と決済手続が完了するまで発送しません。同意いただけない場合は追加料金なしでキャンセルできます。</p>';
			$message .= '<p><label><input type="checkbox" name="sosc_packing_adjust_consent" value="1" id="sosc-packing-adjust-consent"> 上記の送料差額に関する案内を確認しました。</label> <span id="sosc-packing-consent-status" role="status" aria-live="polite"></span></p>';
			$ajax_url = admin_url( 'admin-ajax.php' );
			$nonce    = wp_create_nonce( 'sosc_packing_checkout_consent' );
			$message .= '<script>(function(){var url=' . wp_json_encode( $ajax_url ) . ',nonce=' . wp_json_encode( $nonce ) . ';function init(){var c=document.getElementById("sosc-packing-adjust-consent"),b=document.getElementById("purchase_button"),h=document.querySelector("#purchase_form input[name=sosc_packing_adjust_consent]"),s=document.getElementById("sosc-packing-consent-status"),requestId=0;if(!c||!b||!h){return;}function disable(){h.value="";b.disabled=true;b.setAttribute("aria-disabled","true");}function sync(){var current=++requestId;disable();if(!c.checked){if(s){s.textContent="";}return;}if(s){s.textContent="確認を記録しています…";}var body=new URLSearchParams({action:"sosc_packing_checkout_consent",nonce:nonce});fetch(url,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded;charset=UTF-8"},body:body.toString()}).then(function(r){return r.json();}).then(function(r){if(current!==requestId||!c.checked){return;}if(!r.success){throw new Error();}h.value="1";b.disabled=false;b.setAttribute("aria-disabled","false");if(s){s.textContent="確認済み";}}).catch(function(){if(current!==requestId){return;}c.checked=false;disable();if(s){s.textContent="確認を記録できませんでした。再度お試しください。";}});}c.addEventListener("change",sync);disable();}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",init);}else{init();}}());</script>';
		} else {
			$message .= '<p><strong>確認画面に表示された送料が確定送料です。</strong></p>';
		}
		$message .= '</section>';
		return $html . $message;
	}

	public static function record_checkout_consent() {
		check_ajax_referer( 'sosc_packing_checkout_consent', 'nonce' );
		global $wpdb, $usces;
		if ( ! Settings::enabled() || ! isset( $usces ) || ! method_exists( $usces, 'get_current_pre_order_id' ) ) {
			wp_send_json_error( array( 'message' => 'packing_unavailable' ), 409 );
		}
		$pre_order_id = $usces->get_current_pre_order_id();
		$row          = $pre_order_id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'orders' ) . ' WHERE pre_order_id=%s AND status=%s', $pre_order_id, 'temporary' ) ) : null;
		$snapshot     = $row ? json_decode( $row->snapshot, true ) : null;
		if ( ! $row || ! is_array( $snapshot ) || empty( $snapshot['price_policy'] ) || 'adjust' !== $snapshot['price_policy'] || empty( $snapshot['result']['status'] ) || 'individual' !== $snapshot['result']['status'] ) {
			wp_send_json_error( array( 'message' => 'consent_target_missing' ), 409 );
		}
		$now                             = Database::now();
		$snapshot['checkout_consent_at'] = gmdate( 'c' );
		$encoded_snapshot                = Database::encode_json( $snapshot );
		if ( is_wp_error( $encoded_snapshot ) ) {
			wp_send_json_error( array( 'message' => $encoded_snapshot->get_error_code() ), 500 );
		}
		$updated = $wpdb->update(
			Database::table( 'orders' ),
			array(
				'checkout_consented_at' => $now,
				'snapshot'              => $encoded_snapshot,
				'updated_at'            => $now,
			),
			array(
				'id'          => $row->id,
				'fingerprint' => $row->fingerprint,
				'status'      => 'temporary',
			),
			array( '%s', '%s', '%s' ),
			array( '%d', '%s', '%s' )
		);
		if ( 1 !== $updated ) {
			wp_send_json_error( array( 'message' => 'consent_save_failed' ), 500 );
		}
		wp_send_json_success();
	}

	public static function confirm_purchase_form( $html, $payments, $acting_flag, $rand, $purchase_disabled ) {
		self::ensure_result();
		if ( ! is_array( self::$result ) || 'individual' !== self::$result['status'] || 'adjust' !== Settings::get( 'individual_price_policy' ) ) {
			return $html;
		}
		$field = '<input type="hidden" name="sosc_packing_adjust_consent" value="">';
		return preg_replace( '/(<form\b[^>]*>)/i', '$1' . $field, $html, 1 );
	}

	public static function confirm_button_attributes( $attributes ) {
		self::ensure_result();
		if ( is_array( self::$result ) && 'individual' === self::$result['status'] && 'adjust' === Settings::get( 'individual_price_policy' ) ) {
			$attributes .= ' disabled="disabled" aria-describedby="sosc-packing-individual-title"';
		}
		return $attributes;
	}

	public static function validate_purchase_consent() {
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) || ! self::is_purchase_request() ) {
			return;
		}
		self::ensure_result();
		if ( ! is_array( self::$result ) || 'individual' !== self::$result['status'] || 'adjust' !== Settings::get( 'individual_price_policy' ) ) {
			return;
		}
		global $wpdb, $usces;
		$pre_order_id = isset( $usces ) && method_exists( $usces, 'get_current_pre_order_id' ) ? $usces->get_current_pre_order_id() : '';
		$recorded     = $pre_order_id && self::$fingerprint ? $wpdb->get_var( $wpdb->prepare( 'SELECT checkout_consented_at FROM ' . Database::table( 'orders' ) . ' WHERE pre_order_id=%s AND status=%s AND fingerprint=%s', $pre_order_id, 'temporary', self::$fingerprint ) ) : '';
		if ( empty( $_POST['sosc_packing_adjust_consent'] ) || '1' !== sanitize_text_field( wp_unslash( $_POST['sosc_packing_adjust_consent'] ) ) || ! $recorded ) {
			wp_die(
				esc_html__( '送料差額に関する案内を確認し、チェックを入れてから注文を確定してください。', 'so-short-code' ),
				esc_html__( '確認が必要です', 'so-short-code' ),
				array(
					'response'  => 400,
					'back_link' => true,
				)
			);
		}
	}

	private static function is_purchase_request() {
		foreach ( array_keys( $_POST ) as $key ) {
			if ( 0 === strpos( sanitize_key( $key ), 'purchase' ) ) {
				return true;
			}
		}
		return false;
	}

	private static function save_temporary( $result, $fingerprint ) {
		global $wpdb, $usces;
		$pre_order_id = method_exists( $usces, 'get_current_pre_order_id' ) ? $usces->get_current_pre_order_id() : null;
		if ( ! $pre_order_id ) {
			return true;
		}
		$now              = Database::now();
		$entry            = $usces->cart->get_entry();
		$payment          = isset( $entry['order']['payment_name'] ) ? $usces->getPayments( $entry['order']['payment_name'] ) : array();
		$external         = ! empty( $payment['settlement'] ) && ! in_array( $payment['settlement'], array( 'COD', 'transfer', 'blank' ), true );
		$snapshot         = array(
			'schema_version'   => 1,
			'library_version'  => '3.12.1',
			'setting_revision' => Settings::revision(),
			'price_policy'     => Settings::get( 'individual_price_policy' ),
			'payment_name'     => isset( $entry['order']['payment_name'] ) ? sanitize_text_field( $entry['order']['payment_name'] ) : '',
			'external_payment' => $external,
			'result'           => $result,
			'created_at'       => gmdate( 'c' ),
		);
		$encoded_snapshot = Database::encode_json( $snapshot );
		if ( is_wp_error( $encoded_snapshot ) ) {
			Database::log( $encoded_snapshot->get_error_code(), array(), 'error' );
			return false;
		}
		$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT id,fingerprint,status,checkout_consented_at,created_at FROM ' . Database::table( 'orders' ) . ' WHERE pre_order_id=%s', $pre_order_id ) );
		$data     = array(
			'pre_order_id'     => $pre_order_id,
			'origin'           => 'checkout',
			'fingerprint'      => $fingerprint,
			'status'           => 'temporary',
			'reason_code'      => isset( $result['reason'] ) ? $result['reason'] : null,
			'shipping_total'   => isset( $result['shipping'] ) ? $result['shipping'] : null,
			'individual_state' => 'individual' === $result['status'] ? 'pending' : null,
			'setting_revision' => Settings::revision(),
			'expires_at'       => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			'snapshot'         => $encoded_snapshot,
			'history'          => '[]',
			'updated_at'       => $now,
		);
		if ( $existing ) {
			if ( 'temporary' !== $existing->status ) {
				Database::log(
					'temporary_state_conflict',
					array(
						'record_id' => (int) $existing->id,
						'status'    => sanitize_key( $existing->status ),
					),
					'error'
				);
				return false;
			}
			$data['checkout_consented_at'] = hash_equals( (string) $existing->fingerprint, (string) $fingerprint ) ? $existing->checkout_consented_at : null;
			$updated                       = $wpdb->update(
				Database::table( 'orders' ),
				$data,
				array(
					'id'          => $existing->id,
					'status'      => 'temporary',
					'fingerprint' => $existing->fingerprint,
				)
			);
			if ( 1 === $updated ) {
				return true;
			}
			if ( 0 === $updated ) {
				$current = $wpdb->get_row( $wpdb->prepare( 'SELECT status,fingerprint,snapshot FROM ' . Database::table( 'orders' ) . ' WHERE id=%d', $existing->id ) );
				return $current && 'temporary' === $current->status && hash_equals( (string) $fingerprint, (string) $current->fingerprint ) && hash_equals( (string) $data['snapshot'], (string) $current->snapshot );
			}
			return false;
		} else {
			$data['created_at'] = $now;
			return false !== $wpdb->insert( Database::table( 'orders' ), $data );
		}
	}

	public static function register_order( $args ) {
		global $wpdb;
		$order_id = isset( $args['order_id'] ) ? absint( $args['order_id'] ) : 0;
		$entry    = isset( $args['entry'] ) ? $args['entry'] : array();
		$pre_id   = isset( $entry['reserve']['pre_order_id'] ) ? sanitize_text_field( $entry['reserve']['pre_order_id'] ) : '';
		if ( ! $order_id ) {
			return;
		}
		if ( $pre_id ) {
			$record_id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Database::table( 'orders' ) . ' WHERE pre_order_id=%s', $pre_id ) );
			if ( $record_id ) {
				$result = $wpdb->update(
					Database::table( 'orders' ),
					array(
						'order_id'   => $order_id,
						'status'     => 'confirmed',
						'expires_at' => null,
						'updated_at' => Database::now(),
					),
					array( 'id' => $record_id ),
					array( '%d', '%s', '%s', '%s' ),
					array( '%d' )
				);
				// An unchanged confirmed row is an idempotent retry, not a database failure.
				if ( false === $result ) {
					Database::log(
						'order_registration_failed',
						array(
							'record_id' => (int) $record_id,
							'db_failed' => false === $result,
						),
						'error',
						$order_id,
						wp_generate_uuid4(),
						365
					);
				}
				return;
			}
		}
		if ( ! Settings::enabled() ) {
			return;
		}
		if ( ! $pre_id ) {
			$now    = Database::now();
			$result = $wpdb->replace(
				Database::table( 'orders' ),
				array(
					'order_id'         => $order_id,
					'origin'           => 'manual_or_automated',
					'status'           => 'uncalculated',
					'setting_revision' => Settings::revision(),
					'created_at'       => $now,
					'updated_at'       => $now,
					'history'          => '[]',
				),
				array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
			);
			if ( false === $result ) {
				Database::log( 'order_registration_failed', array( 'db_failed' => true ), 'error', $order_id, wp_generate_uuid4(), 365 );
			}
			return;
		}
		$now    = Database::now();
		$result = $wpdb->replace(
			Database::table( 'orders' ),
			array(
				'order_id'         => $order_id,
				'pre_order_id'     => $pre_id,
				'origin'           => 'recovered_or_automated',
				'status'           => 'uncalculated',
				'setting_revision' => Settings::revision(),
				'created_at'       => $now,
				'updated_at'       => $now,
				'history'          => '[]',
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		if ( false === $result ) {
			Database::log(
				'order_registration_failed',
				array(
					'pre_order_hash' => hash( 'sha256', $pre_id ),
					'db_failed'      => true,
				),
				'error',
				$order_id,
				wp_generate_uuid4(),
				365
			);
		}
	}

	public static function order_panel( $data, $meta, $args ) {
		global $wpdb;
		$order_id = isset( $data['ID'] ) ? absint( $data['ID'] ) : ( isset( $data['order_id'] ) ? absint( $data['order_id'] ) : 0 );
		$row      = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'orders' ) . ' WHERE order_id=%d', $order_id ) );
		echo '<tr><td class="label">箱詰め結果</td><td colspan="3">';
		if ( ! $row ) {
			echo '<strong>箱詰め未計算</strong>（手動受注・復旧受注等の可能性があります）';
		} else {
			$snapshot = json_decode( $row->snapshot, true );
			echo '<strong>' . esc_html( $row->status ) . '</strong>　送料: ' . esc_html( $row->shipping_total );
			if ( $row->individual_state ) {
				echo '<br>個別対応: ' . esc_html( $row->individual_state ) . ' / 理由: ' . esc_html( $row->reason_code );
			}
			if ( isset( $snapshot['result']['boxes'] ) ) {
				echo '<ul>';
				foreach ( $snapshot['result']['boxes'] as $box ) {
					echo '<li>' . esc_html( $box['name'] ) . ' / ' . esc_html( $box['shipping'] ) . '</li>';
				}
				echo '</ul>';
			}
		}
		echo '</td></tr>';
	}

	public static function delete_order_data( $order_id ) {
		global $wpdb;
		if ( is_object( $order_id ) ) {
			$order_id = isset( $order_id->ID ) ? $order_id->ID : ( isset( $order_id->order_id ) ? $order_id->order_id : 0 );
		}
		if ( is_array( $order_id ) ) {
			$order_id = isset( $order_id['ID'] ) ? $order_id['ID'] : ( isset( $order_id['order_id'] ) ? $order_id['order_id'] : 0 );
		}
		$order_id = absint( $order_id );
		if ( $order_id ) {
			$wpdb->delete( Database::table( 'orders' ), array( 'order_id' => $order_id ), array( '%d' ) );
		}
	}
}
