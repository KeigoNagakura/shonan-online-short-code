<?php
/**
 * Packing administration screens.
 *
 * @package ShonanOnlineShortCode
 */

namespace ShonanOnline\ShortCode\Packing;

final class Admin {
	public static function register_menu() {
		if ( current_user_can( 'wel_manage_order' ) ) {
			add_submenu_page( 'usces_orderlist', '箱詰め個別対応', '箱詰め個別対応', 'wel_manage_order', 'sosc_packing_individual', array( __CLASS__, 'individual_orders_page' ) );
		}
		if ( ! Settings::feature_selected() ) {
			return;
		}
		add_submenu_page( 'sosc_menu', '箱詰め設定', '箱詰め設定', 'manage_options', 'sosc_packing', array( __CLASS__, 'page' ) );
	}

	public static function page() {
		$tabs = self::tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		if ( ! isset( $tabs[ $tab ] ) || ! current_user_can( $tabs[ $tab ]['capability'] ) ) {
			$tab = current_user_can( 'manage_options' ) ? 'dashboard' : 'individual';
		}

		call_user_func( array( __CLASS__, $tabs[ $tab ]['callback'] ) );
	}

	public static function register_product_hooks() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_product_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_product_box' ), 20, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'product_save_notice' ) );
	}

	public static function add_product_box() {
		add_meta_box( 'sosc-packing-product', '箱詰め設定', array( __CLASS__, 'product_box' ), 'post', 'normal', 'default' );
	}

	private static function is_welcart_item( $post_id ) {
		return 'item' === get_post_mime_type( $post_id );
	}

	public static function product_box( $post ) {
		if ( ! self::is_welcart_item( $post->ID ) ) {
			echo '<p>Welcart商品でのみ使用できます。</p>';
			return;
		}
		global $wpdb;
		$product    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'products' ) . ' WHERE post_id = %d', $post->ID ), ARRAY_A );
		$rows       = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, code, name, weight FROM {$wpdb->prefix}usces_skus WHERE post_id = %d ORDER BY sort, meta_id", $post->ID ), ARRAY_A );
		$saved_rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'skus' ) . ' WHERE post_id = %d', $post->ID ), ARRAY_A );
		$saved      = array();
		foreach ( $saved_rows as $saved_row ) {
			$saved[ (int) $saved_row['sku_meta_id'] ] = $saved_row;
		}
		wp_nonce_field( 'sosc_packing_product_' . $post->ID, 'sosc_packing_product_nonce' );
		echo '<p><label><input type="checkbox" name="sosc_packing_cool" value="1" ' . checked( ! empty( $product['cool_required'] ), true, false ) . '> クール便必須</label>　';
		echo '<label><input type="checkbox" name="sosc_packing_flat" value="1" ' . checked( ! empty( $product['keep_flat'] ), true, false ) . '> 天地固定</label></p>';
		echo '<table class="widefat striped"><thead><tr><th scope="col">SKU</th><th scope="col">幅(mm)</th><th scope="col">奥行(mm)</th><th scope="col">高さ(mm)</th><th scope="col">マージン必要</th><th scope="col">マージン(mm)</th><th scope="col">重量(g)</th></tr></thead><tbody>';
		$dimension_labels = array(
			'width'  => '幅',
			'length' => '奥行',
			'depth'  => '高さ',
		);
		foreach ( $rows as $row ) {
			$id  = (int) $row['meta_id'];
			$sku = isset( $saved[ $id ] ) ? $saved[ $id ] : null;
			echo '<tr><td>' . esc_html( $row['code'] . ' ' . $row['name'] ) . '<input type="hidden" name="sosc_packing_skus[' . esc_attr( $id ) . '][meta_id]" value="' . esc_attr( $id ) . '"></td>';
			foreach ( array( 'width', 'length', 'depth' ) as $field ) {
				$input_id = 'sosc-packing-sku-' . $id . '-' . $field;
				echo '<td><label class="screen-reader-text" for="' . esc_attr( $input_id ) . '">' . esc_html( $row['code'] . ' ' . $dimension_labels[ $field ] ) . '</label><input id="' . esc_attr( $input_id ) . '" class="small-text" type="number" min="1" max="10000" name="sosc_packing_skus[' . esc_attr( $id ) . '][' . esc_attr( $field ) . ']" value="' . esc_attr( $sku ? $sku[ $field ] : '' ) . '"></td>';
			}
			echo '<td><label><span class="screen-reader-text">' . esc_html( $row['code'] . ' マージン必要' ) . '</span><input type="checkbox" name="sosc_packing_skus[' . esc_attr( $id ) . '][margin_required]" value="1" ' . checked( $sku && $sku['margin_required'], true, false ) . '></label></td>';
			echo '<td><label class="screen-reader-text" for="sosc-packing-sku-' . esc_attr( $id ) . '-margin">' . esc_html( $row['code'] . ' マージン' ) . '</label><input id="sosc-packing-sku-' . esc_attr( $id ) . '-margin" class="small-text" type="number" min="0" max="1000" name="sosc_packing_skus[' . esc_attr( $id ) . '][margin]" value="' . esc_attr( $sku ? $sku['margin'] : '0' ) . '"></td>';
			echo '<td>' . esc_html( $row['weight'] ) . '</td></tr>';
		}
		echo '</tbody></table><p class="description">重量はWelcart SKU設定を使用します。寸法は箱詰め対象SKUで必須です。</p>';
		echo '<p><label>箱詰め設定の変更理由 <input class="regular-text" maxlength="500" name="sosc_packing_change_reason"></label></p>';
	}

	public static function save_product_box( $post_id, $post ) {
		if ( ! isset( $_POST['sosc_packing_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sosc_packing_product_nonce'] ) ), 'sosc_packing_product_' . $post_id ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! self::is_welcart_item( $post_id ) ) {
			return;
		}
		$reason = isset( $_POST['sosc_packing_change_reason'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['sosc_packing_change_reason'] ) ), 0, 500 ) : '';
		if ( '' === $reason ) {
			self::set_product_save_error( '箱詰め設定の変更理由を入力してください。' );
			return;
		}
		global $wpdb;
		$before = array(
			'product' => $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'products' ) . ' WHERE post_id=%d', $post_id ), ARRAY_A ),
			'skus'    => $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'skus' ) . ' WHERE post_id=%d ORDER BY sku_meta_id', $post_id ), ARRAY_A ),
		);
		$now    = Database::now();
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			self::set_product_save_error( 'データベースのトランザクションを開始できませんでした。' );
			return;
		}
		$result = $wpdb->replace(
			Database::table( 'products' ),
			array(
				'post_id'       => $post_id,
				'cool_required' => empty( $_POST['sosc_packing_cool'] ) ? 0 : 1,
				'keep_flat'     => empty( $_POST['sosc_packing_flat'] ) ? 0 : 1,
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%d', '%d', '%d', '%s', '%s' )
		);
		if ( false === $result ) {
			$wpdb->query( 'ROLLBACK' );
			self::set_product_save_error( '商品共通の箱詰め設定を保存できませんでした。' );
			return;
		}
		// Values are validated field-by-field below.
		$rows = isset( $_POST['sosc_packing_skus'] ) && is_array( $_POST['sosc_packing_skus'] ) ? wp_unslash( $_POST['sosc_packing_skus'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		foreach ( $rows as $meta_id => $row ) {
			$meta_id = absint( $meta_id );
			$valid   = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->prefix}usces_skus WHERE meta_id = %d AND post_id = %d", $meta_id, $post_id ) );
			if ( ! $valid ) {
				continue;
			}
			$width  = isset( $row['width'] ) ? absint( $row['width'] ) : 0;
			$length = isset( $row['length'] ) ? absint( $row['length'] ) : 0;
			$depth  = isset( $row['depth'] ) ? absint( $row['depth'] ) : 0;
			if ( 0 === $width && 0 === $length && 0 === $depth ) {
				$result = $wpdb->delete(
					Database::table( 'skus' ),
					array(
						'post_id'     => $post_id,
						'sku_meta_id' => $meta_id,
					),
					array( '%d', '%d' )
				);
				if ( false === $result ) {
					$wpdb->query( 'ROLLBACK' );
					self::set_product_save_error( 'SKU設定の解除に失敗しました。' );
					return;
				}
				continue;
			}
			if ( $width < 1 || $width > 10000 || $length < 1 || $length > 10000 || $depth < 1 || $depth > 10000 ) {
				$wpdb->query( 'ROLLBACK' );
				self::set_product_save_error( 'SKU寸法は3辺すべてを1～10000mmで入力してください。設定は保存していません。' );
				return;
			}
			$margin_required = empty( $row['margin_required'] ) ? 0 : 1;
			$margin          = isset( $row['margin'] ) ? absint( $row['margin'] ) : 0;
			if ( $margin_required && ( $margin < 1 || $margin > 1000 || $width + 2 * $margin > 10000 || $length + 2 * $margin > 10000 || $depth + 2 * $margin > 10000 ) ) {
				$wpdb->query( 'ROLLBACK' );
				self::set_product_save_error( '梱包マージンは1～1000mmで、加算後の各辺が10000mm以下になるよう入力してください。設定は保存していません。' );
				return;
			}
			$result = $wpdb->replace(
				Database::table( 'skus' ),
				array(
					'post_id'         => $post_id,
					'sku_meta_id'     => $meta_id,
					'width'           => $width,
					'length'          => $length,
					'depth'           => $depth,
					'margin_required' => $margin_required,
					'margin'          => $margin_required ? $margin : 0,
					'created_at'      => $now,
					'updated_at'      => $now,
				),
				array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
			);
			if ( false === $result ) {
				$wpdb->query( 'ROLLBACK' );
				self::set_product_save_error( 'SKUの箱詰め設定を保存できませんでした。' );
				return;
			}
		}
		$result = Settings::bump_revision();
		if ( is_wp_error( $result ) || false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			self::set_product_save_error( '箱詰め設定を確定できませんでした。設定は保存していません。' );
			return;
		}
		$after = array(
			'product' => $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'products' ) . ' WHERE post_id=%d', $post_id ), ARRAY_A ),
			'skus'    => $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'skus' ) . ' WHERE post_id=%d ORDER BY sku_meta_id', $post_id ), ARRAY_A ),
		);
		Database::log(
			'product_skus_changed',
			array(
				'post_id'  => $post_id,
				'before'   => $before,
				'after'    => $after,
				'reason'   => $reason,
				'user_id'  => get_current_user_id(),
				'revision' => Settings::revision(),
			),
			'info',
			null,
			null,
			365
		);
	}

	public static function handle_actions() {
		if ( ! is_admin() || empty( $_POST['sosc_packing_action'] ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['sosc_packing_action'] ) );
		if ( 'update_individual' === $action ) {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wel_manage_order' ) ) {
				wp_die( esc_html__( 'You are not allowed to perform this action.', 'so-short-code' ) );
			}
			check_admin_referer( 'sosc_packing_individual' );
			$result = self::update_individual();
			self::redirect_notice( is_wp_error( $result ) ? $result->get_error_message() : '個別対応を更新しました。', is_wp_error( $result ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'sosc_packing_admin' );
		if ( 'save_settings' === $action ) {
			$before = Settings::all();
			// Settings::validate() sanitizes and range-checks every supported key.
			$result = Settings::save( isset( $_POST['packing'] ) && is_array( $_POST['packing'] ) ? wp_unslash( $_POST['packing'] ) : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( ! is_wp_error( $result ) ) {
				Database::log(
					'settings_changed',
					array(
						'before'   => $before,
						'after'    => Settings::all(),
						'reason'   => '基本設定画面から更新',
						'user_id'  => get_current_user_id(),
						'revision' => Settings::revision(),
					),
					'info',
					null,
					null,
					365
				);
			}
			self::redirect_notice( is_wp_error( $result ) ? $result->get_error_message() : '設定を保存しました。', is_wp_error( $result ) );
		}
		if ( 'save_box' === $action ) {
			global $wpdb;
			$box_id = isset( $_POST['box_id'] ) ? absint( $_POST['box_id'] ) : 0;
			$before = $box_id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'boxes' ) . ' WHERE id=%d', $box_id ), ARRAY_A ) : null;
			$result = self::save_box();
			if ( ! is_wp_error( $result ) ) {
				$saved_id = $box_id ? $box_id : (int) $wpdb->insert_id;
				$after    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'boxes' ) . ' WHERE id=%d', $saved_id ), ARRAY_A );
				Database::log(
					'box_changed',
					array(
						'before'   => $before,
						'after'    => $after,
						'reason'   => $box_id ? '外箱を更新' : '外箱を追加',
						'user_id'  => get_current_user_id(),
						'revision' => Settings::revision(),
					),
					'info',
					null,
					null,
					365
				);
			}
			self::redirect_notice( is_wp_error( $result ) ? $result->get_error_message() : '外箱を保存しました。', is_wp_error( $result ) );
		}
		if ( 'delete_box' === $action ) {
			global $wpdb;
			$box_id = isset( $_POST['box_id'] ) ? absint( $_POST['box_id'] ) : 0;
			$before = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'boxes' ) . ' WHERE id=%d', $box_id ), ARRAY_A );
			$result = $wpdb->delete( Database::table( 'boxes' ), array( 'id' => $box_id ), array( '%d' ) );
			if ( 1 !== $result ) {
				self::redirect_notice( '削除対象の外箱が見つからないか、削除に失敗しました。', true );
			}
			Settings::bump_revision();
			Database::log(
				'box_deleted',
				array(
					'before'   => $before,
					'reason'   => '外箱を削除',
					'user_id'  => get_current_user_id(),
					'revision' => Settings::revision(),
				),
				'info',
				null,
				null,
				365
			);
			self::redirect_notice( '外箱を削除しました。', false );
		}
		if ( 'duplicate_box' === $action ) {
			$result = self::duplicate_box( isset( $_POST['box_id'] ) ? absint( $_POST['box_id'] ) : 0 );
			if ( ! is_wp_error( $result ) ) {
				Database::log(
					'box_duplicated',
					array(
						'source_id' => absint( $_POST['box_id'] ),
						'new_id'    => (int) $result,
						'reason'    => '外箱を複製',
						'user_id'   => get_current_user_id(),
						'revision'  => Settings::revision(),
					),
					'info',
					null,
					null,
					365
				);
			}
			self::redirect_notice( is_wp_error( $result ) ? $result->get_error_message() : '外箱を複製しました。複製した箱は無効状態です。', is_wp_error( $result ) );
		}
		if ( 'run_maintenance' === $action ) {
			$result = Maintenance::run();
			self::redirect_notice( is_wp_error( $result ) ? $result->get_error_message() : '保守処理を実行しました。', is_wp_error( $result ) );
		}
		if ( 'resolve_settings_duplicate' === $action ) {
			$result = self::resolve_settings_duplicate();
			self::redirect_notice( is_wp_error( $result ) ? $result->get_error_message() : '重複設定を解決しました。', is_wp_error( $result ) );
		}
	}

	private static function resolve_settings_duplicate() {
		global $wpdb;
		$name    = isset( $_POST['param_name'] ) ? sanitize_key( wp_unslash( $_POST['param_name'] ) ) : '';
		$id      = isset( $_POST['keep_id'] ) ? absint( $_POST['keep_id'] ) : 0;
		$blocked = get_option( 'sosc_settings_migration_blocked', array() );
		$backup  = get_option( 'sosc_settings_migration_backup', '' );
		if ( ! is_array( $blocked ) || ! in_array( $name, $blocked, true ) || ! $backup || ! $id ) {
			return new \WP_Error( 'invalid_resolution', '重複設定の解決対象が不正です。' );
		}
		$table  = $wpdb->prefix . 'so_short_code';
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id=%d AND param_name=%s", $id, $name ) );
		if ( ! $exists ) {
			return new \WP_Error( 'invalid_resolution', '保持する設定値が見つかりません。' );
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE param_name=%s AND id<>%d", $name, $id ) );
		$blocked = array_values( array_diff( $blocked, array( $name ) ) );
		if ( $blocked ) {
			update_option( 'sosc_settings_migration_blocked', $blocked, false );
		} else {
			delete_option( 'sosc_settings_migration_blocked' );
			delete_option( 'sosc_settings_unique_migration_version' );
			sosc_migrate_settings_uniqueness();
		}
		return true;
	}

	private static function update_individual() {
		global $wpdb;
		$id       = isset( $_POST['record_id'] ) ? absint( $_POST['record_id'] ) : 0;
		$revision = isset( $_POST['business_revision'] ) ? absint( $_POST['business_revision'] ) : 0;
		$row      = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'orders' ) . ' WHERE id=%d', $id ) );
		if ( ! $row || (int) $row->business_revision !== $revision ) {
			return new \WP_Error( 'individual_conflict', '別の担当者が先に更新しました。画面を再読み込みして確認してください。' );
		}
		$state = isset( $_POST['individual_state'] ) ? sanitize_key( wp_unslash( $_POST['individual_state'] ) ) : 'pending';
		if ( ! in_array( $state, array( 'pending', 'working', 'done' ), true ) ) {
			return new \WP_Error( 'invalid_state', '対応状態が不正です。' );
		}
		$outcome = isset( $_POST['outcome'] ) ? sanitize_key( wp_unslash( $_POST['outcome'] ) ) : '';
		$method  = isset( $_POST['actual_shipping_method'] ) ? sanitize_text_field( wp_unslash( $_POST['actual_shipping_method'] ) ) : '';
		$diff    = isset( $_POST['price_difference_state'] ) ? sanitize_key( wp_unslash( $_POST['price_difference_state'] ) ) : '';
		if ( ! in_array( $outcome, array( '', 'shipped', 'cancelled', 'not_required' ), true ) || ! in_array( $diff, array( '', 'none', 'store_absorbed', 'charged', 'refunded' ), true ) ) {
			return new \WP_Error( 'invalid_individual_result', '対応結果または送料差額状況が不正です。' );
		}
		$contacted            = empty( $_POST['contacted'] ) ? null : Database::now();
		$consented            = empty( $_POST['consented'] ) ? null : Database::now();
		$adjustment_completed = empty( $_POST['adjustment_completed'] ) ? null : Database::now();
		if ( 'fixed' === Settings::get( 'individual_price_policy' ) && 'charged' === $diff ) {
			return new \WP_Error( 'fixed_policy_charge', '確定送料方式では追加請求完了を選択できません。店舗負担等を選択してください。' );
		}
		if ( 'done' === $state && 'adjust' === Settings::get( 'individual_price_policy' ) && 'charged' === $diff && ( ! $contacted || ! $consented || ! $adjustment_completed ) ) {
			return new \WP_Error( 'adjustment_incomplete', '追加請求で対応済みにするには、購入者への連絡、同意、決済手続完了をすべて確認してください。' );
		}
		if ( 'done' === $state && 'refunded' === $diff && ! $adjustment_completed ) {
			return new \WP_Error( 'refund_incomplete', '返金で対応済みにするには、差額決済・返金手続完了を確認してください。' );
		}
		if ( 'done' === $state && ( '' === $outcome || ( 'shipped' === $outcome && ( '' === $method || '' === $diff ) ) ) ) {
			return new \WP_Error( 'incomplete_individual', '対応済みにするには対応結果、実発送方法、送料差額状況を入力してください。' );
		}
		$assignee = isset( $_POST['assignee_user_id'] ) ? absint( $_POST['assignee_user_id'] ) : 0;
		if ( 'working' === $state && ! $assignee ) {
			$assignee = get_current_user_id();
		}
		if ( $assignee ) {
			$user = get_user_by( 'id', $assignee );
			if ( ! $user || ! user_can( $user, 'wel_manage_order' ) ) {
				return new \WP_Error( 'invalid_assignee', '担当者には受注管理権限を持つ有効なユーザーを指定してください。' );
			}
		}
		$data   = array(
			'individual_state'        => $state,
			'assignee_user_id'        => $assignee ? $assignee : null,
			'actual_shipping_method'  => $method,
			'outcome'                 => $outcome,
			'price_difference_state'  => $diff,
			'contacted_at'            => $contacted,
			'consented_at'            => $consented,
			'adjustment_completed_at' => $adjustment_completed,
			'operation_note'          => isset( $_POST['operation_note'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['operation_note'] ) ), 0, 1000 ) : '',
			'completed_at'            => 'done' === $state ? Database::now() : null,
			'business_revision'       => $revision + 1,
			'updated_at'              => Database::now(),
		);
		$result = $wpdb->update(
			Database::table( 'orders' ),
			$data,
			array(
				'id'                => $id,
				'business_revision' => $revision,
			)
		);
		if ( 1 !== $result ) {
			return new \WP_Error( 'individual_conflict', '更新が競合しました。画面を再読み込みしてください。' );
		}
		return true;
	}

	private static function redirect_notice( $message, $error ) {
		$url = wp_get_referer();
		$url = $url ? $url : self::tab_url( 'dashboard' );
		wp_safe_redirect(
			add_query_arg(
				array(
					'sosc_notice' => rawurlencode( $message ),
					'sosc_error'  => $error ? '1' : '0',
				),
				$url
			)
		);
		exit;
	}

	private static function set_product_save_error( $message ) {
		set_transient( 'sosc_packing_product_error_' . get_current_user_id(), sanitize_text_field( $message ), MINUTE_IN_SECONDS );
	}

	public static function product_save_notice() {
		$key     = 'sosc_packing_product_error_' . get_current_user_id();
		$message = get_transient( $key );
		if ( ! is_string( $message ) || '' === $message ) {
			return;
		}
		delete_transient( $key );
		echo '<div class="notice notice-error"><p><strong>箱詰め設定は保存されませんでした。</strong> ' . esc_html( $message ) . '</p></div>';
	}

	private static function save_box() {
		global $wpdb, $usces;
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		if ( '' === $name || mb_strlen( $name ) > 100 ) {
			return new \WP_Error( 'invalid_name', '箱名を1～100文字で入力してください。' );
		}
		$data = array(
			'name'               => $name,
			'name_key'           => $name,
			'inner_width'        => isset( $_POST['inner_width'] ) ? absint( $_POST['inner_width'] ) : 0,
			'inner_length'       => isset( $_POST['inner_length'] ) ? absint( $_POST['inner_length'] ) : 0,
			'inner_depth'        => isset( $_POST['inner_depth'] ) ? absint( $_POST['inner_depth'] ) : 0,
			'delivery_method_id' => isset( $_POST['delivery_method_id'] ) ? absint( $_POST['delivery_method_id'] ) : 0,
			'shipping_charge_id' => isset( $_POST['shipping_charge_id'] ) ? absint( $_POST['shipping_charge_id'] ) : 0,
			'priority'           => isset( $_POST['priority'] ) ? absint( $_POST['priority'] ) : 0,
			'enabled'            => empty( $_POST['enabled'] ) ? 0 : 1,
			'updated_at'         => Database::now(),
		);
		if ( ! $data['delivery_method_id'] || ! $data['shipping_charge_id'] || ! isset( $usces ) || false === $usces->get_delivery_method_index( $data['delivery_method_id'] ) || false === $usces->get_shipping_charge_index( $data['shipping_charge_id'] ) ) {
			return new \WP_Error( 'invalid_shipping', 'Welcart配送方法と送料を選択してください。' );
		}
		if ( $data['inner_width'] < 1 || $data['inner_width'] > 10000 || $data['inner_length'] < 1 || $data['inner_length'] > 10000 || $data['inner_depth'] < 1 || $data['inner_depth'] > 10000 || $data['priority'] < 1 || $data['priority'] > 999 ) {
			return new \WP_Error( 'invalid_box', '寸法は1～10,000mm、優先順位は1～999で入力してください。' );
		}
		$id = empty( $_POST['box_id'] ) ? 0 : absint( $_POST['box_id'] );
		if ( $id ) {
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Database::table( 'boxes' ) . ' WHERE id=%d', $id ) ) ) {
				return new \WP_Error( 'box_not_found', '更新対象の外箱が見つかりません。' );
			}
			$result = $wpdb->update( Database::table( 'boxes' ), $data, array( 'id' => $id ) );
		} else {
			$data['created_at'] = Database::now();
			$result             = $wpdb->insert( Database::table( 'boxes' ), $data );
		}
		if ( false === $result ) {
			return new \WP_Error( 'box_save', '外箱を保存できませんでした。' );
		}
		Settings::bump_revision();
		return true;
	}

	private static function duplicate_box( $id ) {
		global $wpdb;
		$source = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'boxes' ) . ' WHERE id=%d', $id ), ARRAY_A );
		if ( ! $source ) {
			return new \WP_Error( 'box_not_found', '複製元の外箱が見つかりません。' );
		}
		$number = 1;
		do {
			$suffix = 1 === $number ? '（コピー）' : '（コピー' . $number . '）';
			$name   = mb_substr( $source['name'], 0, max( 0, 100 - mb_strlen( $suffix ) ) ) . $suffix;
			$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Database::table( 'boxes' ) . ' WHERE name_key=%s', $name ) );
			++$number;
		} while ( $exists && $number < 10000 );
		if ( $exists ) {
			return new \WP_Error( 'box_duplicate_name', '複製用の箱名を作成できませんでした。' );
		}
		unset( $source['id'] );
		$source['name']       = $name;
		$source['name_key']   = $name;
		$source['enabled']    = 0;
		$source['created_at'] = Database::now();
		$source['updated_at'] = Database::now();
		if ( false === $wpdb->insert( Database::table( 'boxes' ), $source ) ) {
			return new \WP_Error( 'box_duplicate_failed', '外箱を複製できませんでした。' );
		}
		$new_id = (int) $wpdb->insert_id;
		Settings::bump_revision();
		return $new_id;
	}

	private static function notice() {
		if ( empty( $_GET['sosc_notice'] ) ) {
			return;
		}
		$class = empty( $_GET['sosc_error'] ) ? 'notice notice-success' : 'notice notice-error';
		echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['sosc_notice'] ) ) ) ) . '</p></div>';
	}

	public static function dashboard() {
		self::header( '' );
		self::settings_form();
		self::footer();
	}

	public static function settings_form() {
		$v = Settings::all();
		if ( ! Settings::enabled() ) {
			echo '<div class="notice notice-warning inline"><p>設定準備中です。外箱、商品・SKU、個別対応配送を登録し、基本設定の保存に成功すると箱詰め送料計算を開始します。</p></div>';
		}
		echo '<form method="post">';
		wp_nonce_field( 'sosc_packing_admin' );
		echo '<input type="hidden" name="sosc_packing_action" value="save_settings"><table class="form-table">';
		foreach ( array(
			'use_weight'    => '重量判定を利用する',
			'use_cool'      => 'クール必須判定を利用する',
			'use_margin'    => '梱包マージンを利用する',
			'use_keep_flat' => '天地固定を利用する',
		) as $key => $label ) {
			echo '<tr><th>' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="packing[' . esc_attr( $key ) . ']" value="1" ' . checked( '1', $v[ $key ], false ) . '> 有効</label></td></tr>';
		}
		foreach ( array(
			'quantity_limit' => '同時購入数上限',
		) as $key => $label ) {
			echo '<tr><th><label for="packing-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input id="packing-' . esc_attr( $key ) . '" type="number" name="packing[' . esc_attr( $key ) . ']" value="' . esc_attr( $v[ $key ] ) . '"></td></tr>';
		}
		if ( '1' === $v['use_weight'] ) {
			echo '<tr><th><label for="packing-weight_limit">重量上限(g)</label></th><td><input id="packing-weight_limit" type="number" name="packing[weight_limit]" value="' . esc_attr( $v['weight_limit'] ) . '"></td></tr>';
		}
		echo '<tr><th><label for="sosc-packing-individual-delivery-id">個別対応配送方法</label></th><td>' . wp_kses( self::delivery_select( 'packing[individual_delivery_id]', $v['individual_delivery_id'], 'sosc-packing-individual-delivery-id' ), self::select_allowed_html() ) . '</td></tr>';
		echo '<tr><th><label for="sosc-packing-individual-shipping-id">個別対応送料</label></th><td>' . wp_kses( self::shipping_select( 'packing[individual_shipping_id]', $v['individual_shipping_id'], 'sosc-packing-individual-shipping-id' ), self::select_allowed_html() ) . '</td></tr>';
		echo '<tr><th><label for="sosc-packing-individual-price-policy">個別対応送料方針</label></th><td><select id="sosc-packing-individual-price-policy" name="packing[individual_price_policy]"><option value="fixed" ' . selected( 'fixed', $v['individual_price_policy'], false ) . '>表示送料を確定額とする</option><option value="adjust" ' . selected( 'adjust', $v['individual_price_policy'], false ) . '>注文後に差額確認を行う</option></select></td></tr>';
		echo '</table>';
		echo '<p class="submit"><button class="button button-primary" type="submit">設定を保存</button></p>';
		echo '</form>';
	}

	public static function boxes() {
		global $wpdb;
		self::header( '外箱設定' );
		$rows    = $wpdb->get_results( 'SELECT * FROM ' . Database::table( 'boxes' ) . ' ORDER BY priority,id' );
		$edit_id = isset( $_GET['edit_box'] ) ? absint( $_GET['edit_box'] ) : 0;
		$editing = $edit_id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'boxes' ) . ' WHERE id=%d', $edit_id ) ) : null;
		echo '<table class="widefat striped"><thead><tr><th>箱名</th><th>内寸</th><th>配送方法ID</th><th>送料ID</th><th>優先</th><th>状態</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><td>' . esc_html( $row->name ) . '</td><td>' . esc_html( $row->inner_width . '×' . $row->inner_length . '×' . $row->inner_depth ) . '</td><td>' . esc_html( $row->delivery_method_id ) . '</td><td>' . esc_html( $row->shipping_charge_id ) . '</td><td>' . esc_html( $row->priority ) . '</td><td>' . ( $row->enabled ? '有効' : '無効' ) . '</td><td>';
			echo '<a class="button button-small" href="' . esc_url(
				add_query_arg(
					array(
						'page'     => 'sosc_packing',
						'tab'      => 'boxes',
						'edit_box' => $row->id,
					),
					admin_url( 'admin.php' )
				)
			) . '">編集</a> <form method="post" style="display:inline">';
			wp_nonce_field( 'sosc_packing_admin' );
			echo '<input type="hidden" name="sosc_packing_action" value="duplicate_box"><input type="hidden" name="box_id" value="' . esc_attr( $row->id ) . '"><button class="button button-small">複製</button></form> <form method="post" style="display:inline">';
			wp_nonce_field( 'sosc_packing_admin' );
			echo '<input type="hidden" name="sosc_packing_action" value="delete_box"><input type="hidden" name="box_id" value="' . esc_attr( $row->id ) . '"><button class="button-link-delete" type="submit" data-sosc-confirm="' . esc_attr( '外箱「' . $row->name . '」を削除しますか？' ) . '">削除</button></form></td></tr>';
		}
		echo '</tbody></table><h2>' . ( $editing ? '外箱を編集' : '外箱を追加' ) . '</h2><form method="post"><input type="hidden" name="sosc_packing_action" value="save_box"><input type="hidden" name="box_id" value="' . esc_attr( $editing ? $editing->id : 0 ) . '">';
		wp_nonce_field( 'sosc_packing_admin' );
		echo '<table class="form-table"><tr><th><label for="sosc-box-name">箱名</label></th><td><input id="sosc-box-name" required maxlength="100" name="name" value="' . esc_attr( $editing ? $editing->name : '' ) . '"><p class="description">購入者の確認画面に表示されます。倉庫内コード等を含めないでください。</p></td></tr><tr><th>内寸(mm)</th><td><label for="sosc-box-width">幅</label> <input id="sosc-box-width" required type="number" min="1" max="10000" name="inner_width" value="' . esc_attr( $editing ? $editing->inner_width : '' ) . '"> × <label for="sosc-box-length">奥行</label> <input id="sosc-box-length" required type="number" min="1" max="10000" name="inner_length" value="' . esc_attr( $editing ? $editing->inner_length : '' ) . '"> × <label for="sosc-box-depth">高さ</label> <input id="sosc-box-depth" required type="number" min="1" max="10000" name="inner_depth" value="' . esc_attr( $editing ? $editing->inner_depth : '' ) . '"></td></tr><tr><th><label for="sosc-box-delivery">Welcart配送方法</label></th><td>' . wp_kses( self::delivery_select( 'delivery_method_id', $editing ? $editing->delivery_method_id : '', 'sosc-box-delivery' ), self::select_allowed_html() ) . '</td></tr><tr><th><label for="sosc-box-shipping">Welcart送料</label></th><td>' . wp_kses( self::shipping_select( 'shipping_charge_id', $editing ? $editing->shipping_charge_id : '', 'sosc-box-shipping' ), self::select_allowed_html() ) . '</td></tr><tr><th><label for="sosc-box-priority">優先順位</label></th><td><input id="sosc-box-priority" required type="number" min="1" max="999" value="' . esc_attr( $editing ? $editing->priority : 1 ) . '" name="priority"></td></tr><tr><th>状態</th><td><label><input type="checkbox" name="enabled" value="1" ' . checked( $editing && $editing->enabled, true, false ) . '> 有効</label></td></tr></table>';
		echo '<p class="submit"><button class="button button-primary" type="submit">' . ( $editing ? '外箱を更新' : '外箱を追加' ) . '</button></p>';
		echo '</form>';
		self::footer();
	}

	public static function products() {
		global $wpdb;
		self::header( '商品設定状況' );
		$page_number  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page     = 50;
		$offset       = ( $page_number - 1 ) * $per_page;
		$budget_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Database::table( 'logs' ) . ' WHERE reason_code=%s AND created_at >= %s', 'calculation_budget', gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) );
		echo '<div class="notice notice-info inline"><p>直近30日の計算予算超過による個別対応配送: <strong>' . esc_html( $budget_count ) . '件</strong>。継続する場合は登録外箱数、SKU行数、購入数量を確認してください。</p></div>';
		$total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_mime_type='item' AND post_status IN ('publish','draft','pending','private')" );
		$items       = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID,p.post_title,COUNT(s.meta_id) sku_count,COUNT(ps.id) configured FROM {$wpdb->posts} p LEFT JOIN {$wpdb->prefix}usces_skus s ON s.post_id=p.ID LEFT JOIN " . Database::table( 'skus' ) . " ps ON ps.sku_meta_id=s.meta_id WHERE p.post_mime_type='item' AND p.post_status IN ('publish','draft','pending','private') GROUP BY p.ID ORDER BY p.post_title LIMIT %d OFFSET %d", $per_page, $offset ) );
		echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sosc_packing_export_skus' ), 'sosc_packing_export_skus' ) ) . '">SKU設定CSVを出力</a></p>';
		echo '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="sosc_packing_import_skus">';
		wp_nonce_field( 'sosc_packing_import_skus' );
		echo '<label>SKU設定CSV（UTF-8、5MiB・5,000行以下） <input required type="file" name="sku_csv" accept=".csv,text/csv"></label> <button class="button">CSVを事前チェック</button></form>';
		$token = isset( $_GET['import_token'] ) ? sanitize_key( wp_unslash( $_GET['import_token'] ) ) : '';
		if ( $token ) {
			$job = get_transient( 'sosc_packing_import_' . get_current_user_id() . '_' . $token );
			if ( is_array( $job ) ) {
				echo '<div class="notice notice-info inline"><p>CSVの事前チェックが完了しました: ' . esc_html( count( $job['rows'] ) ) . '件。内容を保存する場合は確定してください。</p><form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post"><input type="hidden" name="action" value="sosc_packing_commit_skus"><input type="hidden" name="token" value="' . esc_attr( $token ) . '">';
				wp_nonce_field( 'sosc_packing_commit_skus_' . $token );
				echo '<label>変更理由 <input required maxlength="500" name="change_reason"></label> <button class="button button-primary">CSV取込を確定</button></form></div>';
			}
		}
		echo '<table class="widefat striped"><thead><tr><th>商品</th><th>SKU設定</th><th>状態</th><th></th></tr></thead><tbody>';
		foreach ( $items as $item ) {
			$ok = (int) $item->sku_count > 0 && (int) $item->sku_count === (int) $item->configured;
			echo '<tr><td>' . esc_html( $item->post_title ) . '</td><td>' . esc_html( $item->configured . '/' . $item->sku_count ) . '</td><td>' . ( $ok ? '設定済み' : '<strong>不足</strong>' ) . '</td><td><a href="' . esc_url( get_edit_post_link( $item->ID ) ) . '">編集</a></td></tr>';
		}
		echo '</tbody></table>';
		self::pagination( $page_number, (int) ceil( $total_items / $per_page ) );
		self::footer();
	}

	public static function export_skus() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'so-short-code' ) );
		}
		check_admin_referer( 'sosc_packing_export_skus' );
		global $wpdb;
		$out = fopen( 'php://temp/maxmemory:5242880', 'w+' );
		if ( false === $out || false === fwrite( $out, "\xEF\xBB\xBF" ) || false === fputcsv( $out, array( 'post_id', 'sku_meta_id', 'product_name', 'sku_code', 'sku_name', 'width', 'length', 'depth', 'margin_required', 'margin', 'welcart_weight' ) ) ) {
			wp_die( esc_html__( 'CSV出力を開始できませんでした。', 'so-short-code' ) );
		}
		$offset = 0;
		do {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT s.post_id,s.meta_id,s.code AS sku_code,s.name AS sku_name,s.weight,p.post_title AS product_name,c.width,c.length,c.depth,c.margin_required,c.margin FROM {$wpdb->prefix}usces_skus s INNER JOIN {$wpdb->posts} p ON p.ID=s.post_id LEFT JOIN " . Database::table( 'skus' ) . ' c ON c.post_id=s.post_id AND c.sku_meta_id=s.meta_id ORDER BY s.post_id,s.sort,s.meta_id LIMIT %d OFFSET %d', 500, $offset ), ARRAY_A );
			if ( ! is_array( $rows ) || '' !== $wpdb->last_error ) {
				fclose( $out );
				wp_die( esc_html__( 'SKUデータを取得できなかったため、CSVを出力しませんでした。', 'so-short-code' ) );
			}
			foreach ( $rows as $row ) {
				foreach ( array( 'product_name', 'sku_code', 'sku_name' ) as $field ) {
					if ( isset( $row[ $field ] ) && preg_match( '/^[\x00-\x20]*[=+\-@]/u', $row[ $field ] ) ) {
						$row[ $field ] = "'" . $row[ $field ];
					}
				}
				if ( false === fputcsv( $out, array( $row['post_id'], $row['meta_id'], $row['product_name'], $row['sku_code'], $row['sku_name'], $row['width'], $row['length'], $row['depth'], $row['margin_required'], $row['margin'], $row['weight'] ) ) ) {
					fclose( $out );
					wp_die( esc_html__( 'CSVデータを書き込めませんでした。', 'so-short-code' ) );
				}
			}
			$row_count = count( $rows );
			$offset   += 500;
		} while ( 500 === $row_count );
		rewind( $out );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="packing-skus-' . gmdate( 'Ymd-His' ) . '.csv"' );
		if ( false === fpassthru( $out ) ) {
			fclose( $out );
			wp_die( esc_html__( 'CSVデータを送信できませんでした。', 'so-short-code' ) );
		}
		fclose( $out );
		exit;
	}

	public static function import_skus() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'so-short-code' ) );
		}
		check_admin_referer( 'sosc_packing_import_skus' );
		$file = isset( $_FILES['sku_csv'] ) && is_array( $_FILES['sku_csv'] ) ? array(
			'error'    => isset( $_FILES['sku_csv']['error'] ) ? absint( $_FILES['sku_csv']['error'] ) : UPLOAD_ERR_NO_FILE,
			'size'     => isset( $_FILES['sku_csv']['size'] ) ? absint( $_FILES['sku_csv']['size'] ) : 0,
			'tmp_name' => isset( $_FILES['sku_csv']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['sku_csv']['tmp_name'] ) ) : '',
		) : null;
		if ( ! is_array( $file ) || UPLOAD_ERR_OK !== $file['error'] || $file['size'] > 5 * MB_IN_BYTES || ! is_uploaded_file( $file['tmp_name'] ) ) {
			self::import_error( 'CSVファイルを読み込めません。5MiB以下のファイルを選択してください。' );
		}
		$handle = fopen( $file['tmp_name'], 'r' );
		$header = fgetcsv( $handle );
		if ( isset( $header[0] ) ) {
			$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] );
		}
		$expected = array( 'post_id', 'sku_meta_id', 'product_name', 'sku_code', 'sku_name', 'width', 'length', 'depth', 'margin_required', 'margin', 'welcart_weight' );
		if ( $header !== $expected ) {
			fclose( $handle );
			self::import_error( 'CSVヘッダーが指定形式と一致しません。' );
		}
		global $wpdb;
		$rows = array();
		$seen = array();
		while ( true ) {
			$values = fgetcsv( $handle );
			if ( false === $values ) {
				break;
			}
			if ( 1 === count( $values ) && '' === trim( $values[0] ) ) {
				continue;
			}
			if ( count( $rows ) >= 5000 || count( $values ) !== count( $expected ) ) {
				fclose( $handle );
				self::import_error( 'CSVの行数または列数が上限・指定形式に一致しません。' );
			}
			$row     = array_combine( $expected, $values );
			$post_id = absint( $row['post_id'] );
			$meta_id = absint( $row['sku_meta_id'] );
			$key     = $post_id . ':' . $meta_id;
			if ( isset( $seen[ $key ] ) ) {
				fclose( $handle );
				self::import_error( '同じPost IDとSKU meta_idが複数行あります。' );
			}
			$seen[ $key ] = true;
			$width        = self::csv_integer( $row['width'], 1, 10000 );
			$length       = self::csv_integer( $row['length'], 1, 10000 );
			$depth        = self::csv_integer( $row['depth'], 1, 10000 );
			$margin       = self::csv_integer( '' === $row['margin'] ? '0' : $row['margin'], 0, 1000 );
			if ( false === $width || false === $length || false === $depth || false === $margin || ! in_array( (string) $row['margin_required'], array( '0', '1' ), true ) || ( '1' === (string) $row['margin_required'] && ( $margin < 1 || $width + 2 * $margin > 10000 || $length + 2 * $margin > 10000 || $depth + 2 * $margin > 10000 ) ) ) {
				fclose( $handle );
				self::import_error( 'CSVに存在しないSKUまたは範囲外の値があります。' );
			}
			$rows[] = array(
				'post_id'         => $post_id,
				'sku_meta_id'     => $meta_id,
				'width'           => $width,
				'length'          => $length,
				'depth'           => $depth,
				'margin_required' => (int) $row['margin_required'],
				'margin'          => $margin,
			);
		}
		fclose( $handle );
		$skus = self::load_import_skus( $rows );
		if ( is_wp_error( $skus ) ) {
			self::import_error( 'SKUデータを取得できませんでした。' );
		}
		foreach ( $rows as &$row ) {
			$key = $row['post_id'] . ':' . $row['sku_meta_id'];
			if ( ! isset( $skus[ $key ] ) ) {
				self::import_error( 'CSVに存在しないSKUがあります。' );
			}
			$row['sku_signature'] = self::sku_signature( $skus[ $key ] );
		}
		unset( $row );
		$token = wp_generate_password( 20, false, false );
		set_transient(
			'sosc_packing_import_' . get_current_user_id() . '_' . $token,
			array(
				'rows'     => $rows,
				'revision' => Settings::revision(),
				'hash'     => hash_file( 'sha256', $file['tmp_name'] ),
			),
			15 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'sosc_packing',
					'tab'          => 'products',
					'import_token' => $token,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function commit_skus() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'so-short-code' ) );
		}
		$token = isset( $_POST['token'] ) ? sanitize_key( wp_unslash( $_POST['token'] ) ) : '';
		check_admin_referer( 'sosc_packing_commit_skus_' . $token );
		$key = 'sosc_packing_import_' . get_current_user_id() . '_' . $token;
		$job = get_transient( $key );
		if ( ! is_array( $job ) || Settings::revision() !== (int) $job['revision'] ) {
			self::import_error( 'CSVの事前チェック結果が期限切れ、または設定が変更されました。もう一度事前チェックしてください。' );
		}
		$reason = isset( $_POST['change_reason'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['change_reason'] ) ), 0, 500 ) : '';
		if ( '' === $reason ) {
			self::import_error( 'CSV取込の変更理由を入力してください。' );
		}
		global $wpdb;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			self::import_error( 'データベースのトランザクションを開始できませんでした。' );
		}
		$now = Database::now();
		$skus = self::load_import_skus( $job['rows'], true );
		if ( is_wp_error( $skus ) ) {
			$wpdb->query( 'ROLLBACK' );
			self::import_error( 'SKUデータを取得できませんでした。保存していません。' );
		}
		foreach ( $job['rows'] as $row ) {
			$sku = isset( $skus[ $row['post_id'] . ':' . $row['sku_meta_id'] ] ) ? $skus[ $row['post_id'] . ':' . $row['sku_meta_id'] ] : null;
			if ( ! $sku || empty( $row['sku_signature'] ) || ! hash_equals( (string) $row['sku_signature'], self::sku_signature( $sku ) ) ) {
				$wpdb->query( 'ROLLBACK' );
				self::import_error( 'CSVの事前チェック後にSKUが変更されました。保存していません。もう一度事前チェックしてください。' );
			}
			unset( $row['sku_signature'] );
			$data               = $row;
			$data['created_at'] = $now;
			$data['updated_at'] = $now;
			if ( false === $wpdb->replace( Database::table( 'skus' ), $data ) ) {
				$wpdb->query( 'ROLLBACK' );
				self::import_error( 'CSV保存中にエラーが発生しました。保存していません。' );
			}
		}
		$result = Settings::bump_revision();
		if ( is_wp_error( $result ) || false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			self::import_error( 'CSV保存の確定に失敗しました。保存前の状態を維持しています。' );
		}
		delete_transient( $key );
		Database::log(
			'sku_csv_imported',
			array(
				'row_count' => count( $job['rows'] ),
				'file_hash' => $job['hash'],
				'reason'    => $reason,
				'user_id'   => get_current_user_id(),
				'revision'  => Settings::revision(),
			),
			'info',
			null,
			null,
			365
		);
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'sosc_packing',
					'tab'         => 'products',
					'sosc_notice' => rawurlencode( 'CSV設定を保存しました。' ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Fetch SKU identities in bounded batches. Commit uses row locks until the transaction ends.
	 *
	 * @param array $rows Imported rows.
	 * @param bool  $lock Lock matching SKU rows for commit.
	 * @return array|\WP_Error
	 */
	private static function load_import_skus( $rows, $lock = false ) {
		global $wpdb;
		$ids = array_values( array_unique( array_map( static function ( $row ) {
			return (int) $row['sku_meta_id'];
		}, $rows ) ) );
		$found = array();
		foreach ( array_chunk( $ids, 250 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			$sql = "SELECT meta_id,post_id,code,name,weight FROM {$wpdb->prefix}usces_skus WHERE meta_id IN ({$placeholders})";
			if ( $lock ) {
				$sql .= ' FOR UPDATE';
			}
			$records = $wpdb->get_results( $wpdb->prepare( $sql, $chunk ), ARRAY_A );
			if ( ! is_array( $records ) || '' !== $wpdb->last_error ) {
				return new \WP_Error( 'sosc_sku_lookup_failed', 'SKUデータを取得できませんでした。' );
			}
			foreach ( $records as $record ) {
				$found[ (int) $record['post_id'] . ':' . (int) $record['meta_id'] ] = $record;
			}
		}
		return $found;
	}

	private static function csv_integer( $value, $min, $max ) {
		if ( ! is_scalar( $value ) || ! preg_match( '/^\d+$/', (string) $value ) ) {
			return false;
		}
		$value = (int) $value;
		return $value >= $min && $value <= $max ? $value : false;
	}

	private static function sku_signature( $sku ) {
		return hash(
			'sha256',
			wp_json_encode(
				array(
					'post_id' => (int) $sku['post_id'],
					'meta_id' => (int) $sku['meta_id'],
					'code'    => (string) $sku['code'],
					'name'    => (string) $sku['name'],
					'weight'  => (string) $sku['weight'],
				),
				JSON_UNESCAPED_UNICODE
			)
		);
	}

	private static function import_error( $message ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'sosc_packing',
					'tab'         => 'products',
					'sosc_notice' => rawurlencode( $message ),
					'sosc_error'  => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function individual() {
		self::header( '個別対応配送' );
		self::individual_table();
		self::footer();
	}

	public static function individual_orders_page() {
		if ( ! current_user_can( 'wel_manage_order' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'so-short-code' ) );
		}
		echo '<div class="wrap"><h1>箱詰め個別対応</h1>';
		self::notice();
		self::individual_table();
		echo '</div>';
	}

	private static function individual_table() {
		global $wpdb;
		$page_number = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page    = 50;
		$offset      = ( $page_number - 1 ) * $per_page;
		$total_rows  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Database::table( 'orders' ) . ' WHERE individual_state IS NOT NULL' );
		$rows        = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'orders' ) . " WHERE individual_state IS NOT NULL ORDER BY FIELD(individual_state,'pending','working','done'),created_at LIMIT %d OFFSET %d", $per_page, $offset ) );
		$users       = get_users( array( 'fields' => array( 'ID', 'display_name' ) ) );
		echo '<table class="widefat striped"><thead><tr><th>受注ID</th><th>理由・送料</th><th>対応内容</th><th>更新日時</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><td>' . esc_html( $row->order_id ) . '</td><td>' . esc_html( $row->reason_code ) . '<br>請求送料: ' . esc_html( $row->shipping_total ) . '</td><td><form method="post">';
			wp_nonce_field( 'sosc_packing_individual' );
			echo '<input type="hidden" name="sosc_packing_action" value="update_individual"><input type="hidden" name="record_id" value="' . esc_attr( $row->id ) . '"><input type="hidden" name="business_revision" value="' . esc_attr( $row->business_revision ) . '">';
			echo '<label>状態 <select name="individual_state"><option value="pending" ' . selected( 'pending', $row->individual_state, false ) . '>未対応</option><option value="working" ' . selected( 'working', $row->individual_state, false ) . '>対応中</option><option value="done" ' . selected( 'done', $row->individual_state, false ) . '>対応済み</option></select></label> ';
			echo '<label>担当者 <select name="assignee_user_id"><option value="0">未設定</option>';
			foreach ( $users as $user ) {
				echo '<option value="' . esc_attr( $user->ID ) . '" ' . selected( (int) $row->assignee_user_id, (int) $user->ID, false ) . '>' . esc_html( $user->display_name ) . '</option>';
			}
			echo '</select></label><br><label>対応結果 <select name="outcome"><option value="">選択</option><option value="shipped" ' . selected( 'shipped', $row->outcome, false ) . '>通常発送</option><option value="cancelled" ' . selected( 'cancelled', $row->outcome, false ) . '>キャンセル</option><option value="not_required" ' . selected( 'not_required', $row->outcome, false ) . '>発送不要</option></select></label> ';
			echo '<label>実発送方法 <input name="actual_shipping_method" value="' . esc_attr( $row->actual_shipping_method ) . '"></label><br><label>送料差額 <select name="price_difference_state"><option value="">選択</option><option value="none" ' . selected( 'none', $row->price_difference_state, false ) . '>差額なし</option><option value="store_absorbed" ' . selected( 'store_absorbed', $row->price_difference_state, false ) . '>店舗負担</option><option value="charged" ' . selected( 'charged', $row->price_difference_state, false ) . '>追加請求完了</option><option value="refunded" ' . selected( 'refunded', $row->price_difference_state, false ) . '>返金完了</option></select></label><br>';
			echo '<label><input type="checkbox" name="contacted" value="1" ' . checked( ! empty( $row->contacted_at ), true, false ) . '> 購入者へ連絡済み</label>　<label><input type="checkbox" name="consented" value="1" ' . checked( ! empty( $row->consented_at ), true, false ) . '> 購入者同意済み</label>　<label><input type="checkbox" name="adjustment_completed" value="1" ' . checked( ! empty( $row->adjustment_completed_at ), true, false ) . '> 差額決済・返金手続完了</label><br>';
			echo '<label>運用メモ<br><textarea name="operation_note" maxlength="1000" rows="3" cols="50">' . esc_textarea( $row->operation_note ) . '</textarea></label><br><button class="button button-primary">更新</button></form></td><td>' . esc_html( get_date_from_gmt( $row->updated_at ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		self::pagination( $page_number, (int) ceil( $total_rows / $per_page ) );
	}

	private static function pagination( $current, $total ) {
		if ( $total < 2 ) {
			return;
		}
		$base = str_replace( '999999999', '%#%', esc_url( add_query_arg( 'paged', 999999999 ) ) );
		echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post(
			paginate_links(
				array(
					'base'      => $base,
					'current'   => $current,
					'total'     => $total,
					'prev_text' => '‹',
					'next_text' => '›',
				)
			)
		) . '</div></div>';
	}

	public static function reference() {
		self::header( '箱詰め参考計算' );
		$result = null;
		$lines  = isset( $_POST['reference_items'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reference_items'] ) ) : '';
		if ( isset( $_POST['sosc_reference_calculate'] ) ) {
			check_admin_referer( 'sosc_packing_reference' );
			if ( ! Settings::enabled() ) {
				$result = new \WP_Error( 'packing_disabled', '箱詰め送料計算が停止中のため、参考計算は実行できません。' );
			} else {
				$cart = array();
				foreach ( preg_split( '/\r\n|\r|\n/', $lines ) as $line ) {
					$parts = array_map( 'trim', str_getcsv( $line ) );
					if ( 3 !== count( $parts ) || ! ctype_digit( $parts[0] ) || ! ctype_digit( $parts[2] ) || (int) $parts[2] < 1 ) {
						$result = new \WP_Error( 'invalid_reference_items', '商品は1行につき「post_id,SKUコード,数量」で入力してください。' );
						break;
					}
					$cart[] = array(
						'post_id'  => (int) $parts[0],
						'sku'      => rawurlencode( $parts[1] ),
						'quantity' => (int) $parts[2],
					);
				}
				if ( ! is_wp_error( $result ) ) {
					$pref     = isset( $_POST['reference_pref'] ) ? sanitize_text_field( wp_unslash( $_POST['reference_pref'] ) ) : '';
					$country  = isset( $_POST['reference_country'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['reference_country'] ) ) ) : 'JP';
					$delivery = isset( $_POST['reference_delivery'] ) ? absint( $_POST['reference_delivery'] ) : 0;
					$entry    = array(
						'customer' => array(
							'country' => $country,
							'pref'    => $pref,
						),
						'delivery' => array(
							'country' => $country,
							'pref'    => $pref,
						),
						'order'    => array( 'delivery_method' => $delivery ),
					);
					$result   = ( new Calculator() )->calculate( $cart, $entry );
				}
			}
		}
		if ( is_wp_error( $result ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
		} elseif ( is_array( $result ) ) {
			echo '<div class="notice notice-success inline"><p>参考計算が完了しました。この結果はWelcart受注の送料を変更しません。</p><pre>' . esc_html( wp_json_encode( $result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ) . '</pre></div>';
		}
		echo '<form method="post">';
		wp_nonce_field( 'sosc_packing_reference' );
		echo '<table class="form-table"><tr><th><label for="sosc-reference-items">商品</label></th><td><textarea id="sosc-reference-items" required class="large-text code" rows="8" name="reference_items" placeholder="123,SKU-001,2">' . esc_textarea( $lines ) . '</textarea><p class="description">1行につき post_id,SKUコード,数量</p></td></tr><tr><th><label for="sosc-reference-delivery">配送方法</label></th><td>' . wp_kses( self::delivery_select( 'reference_delivery', isset( $_POST['reference_delivery'] ) ? absint( $_POST['reference_delivery'] ) : '', 'sosc-reference-delivery' ), self::select_allowed_html() ) . '</td></tr><tr><th><label for="sosc-reference-country">国コード</label></th><td><input id="sosc-reference-country" required maxlength="2" name="reference_country" value="' . esc_attr( isset( $_POST['reference_country'] ) ? sanitize_text_field( wp_unslash( $_POST['reference_country'] ) ) : 'JP' ) . '"></td></tr><tr><th><label for="sosc-reference-pref">都道府県</label></th><td><input id="sosc-reference-pref" required name="reference_pref" value="' . esc_attr( isset( $_POST['reference_pref'] ) ? sanitize_text_field( wp_unslash( $_POST['reference_pref'] ) ) : '' ) . '"></td></tr></table><p><button class="button button-primary" name="sosc_reference_calculate" value="1">参考計算</button></p></form>';
		self::footer();
	}

	public static function system() {
		global $wpdb;
		self::header( '箱詰めシステム状態' );
		$schedule_error = get_option( 'sosc_packing_schedule_error', '' );
		if ( $schedule_error ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( '日次保守処理を予約できませんでした: ' . $schedule_error ) . '</p></div>';
		}
		$log_count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Database::table( 'logs' ) );
		echo '<table class="widefat striped"><tbody><tr><th>DBスキーマ</th><td>' . esc_html( get_option( 'sosc_packing_db_version', '未作成' ) ) . '</td></tr><tr><th>BoxPacker</th><td>' . ( class_exists( '\\DVDoug\\BoxPacker\\Packer' ) ? '3.12.1 読込済み' : '<strong>未読込</strong>' ) . '</td></tr><tr><th>Welcart</th><td>' . ( defined( 'USCES_VERSION' ) ? esc_html( USCES_VERSION ) : '未検出' ) . '</td></tr><tr><th>最終Cron成功</th><td>' . esc_html( get_option( 'sosc_packing_last_cron_success', '未実行' ) ) . '</td></tr><tr><th>ログ件数</th><td>' . esc_html( $log_count ) . '</td></tr></tbody></table>';
		$logs = $wpdb->get_results( 'SELECT * FROM ' . Database::table( 'logs' ) . ' ORDER BY created_at DESC,id DESC LIMIT 100' );
		echo '<h2>直近の計算・運用ログ</h2><table class="widefat striped"><thead><tr><th>日時</th><th>レベル</th><th>理由</th><th>受注</th><th>内容</th></tr></thead><tbody>';
		foreach ( $logs as $log ) {
			echo '<tr><td>' . esc_html( get_date_from_gmt( $log->created_at ) ) . '</td><td>' . esc_html( $log->level ) . '</td><td>' . esc_html( $log->reason_code ) . '</td><td>' . esc_html( $log->order_id ) . '</td><td><code>' . esc_html( $log->context ) . '</code></td></tr>';
		}
		echo '</tbody></table>';
		self::settings_migration_panel();
		echo '<form method="post">';
		wp_nonce_field( 'sosc_packing_admin' );
		echo '<input type="hidden" name="sosc_packing_action" value="run_maintenance">';
		echo '<p class="submit"><button class="button button-secondary" type="submit">保守処理を今すぐ実行</button></p>';
		echo '</form>';
		self::footer();
	}

	public static function explanation() {
		self::header( '箱詰め設定の説明' );
		echo '<p>箱詰め送料計算で使用する用語、変更できる設定、固定設定をまとめています。</p>';
		echo '<h2>用語解説</h2>';
		echo '<table class="widefat striped"><thead><tr><th>用語</th><th>説明</th></tr></thead><tbody>';
		echo '<tr><td>SKU</td><td>同じ商品内のサイズや色などを区別する販売単位です。箱詰めではSKUごとに寸法、重量、梱包条件を使用します。</td></tr>';
		echo '<tr><td>内寸</td><td>外箱の内側で実際に商品を入れられる幅・長さ・深さです。外箱の外寸ではありません。</td></tr>';
		echo '<tr><td>梱包マージン</td><td>緩衝材などに必要な余裕を商品寸法へ加算する値です。有効時は実寸にマージンを加えた大きさで判定します。</td></tr>';
		echo '<tr><td>天地固定</td><td>商品を横倒しにせず、指定した上下方向を保ったまま箱へ入れる条件です。</td></tr>';
		echo '<tr><td>クール必須</td><td>冷蔵・冷凍など、クール便対応の外箱と配送方法を必要とする商品条件です。</td></tr>';
		echo '<tr><td>個別対応配送</td><td>設定不足、数量・重量上限超過、計算不能など、自動箱詰めできない注文に適用する配送方法です。</td></tr>';
		echo '<tr><td>参考計算</td><td>入力した商品・SKUと数量で箱詰め結果を確認する管理者向け試算です。実際の受注結果は変更しません。</td></tr>';
		echo '<tr><td>設定リビジョン</td><td>箱詰め条件の変更世代を表す内部番号です。設定変更後に古い計算結果を再利用しないために使用します。</td></tr>';
		echo '</tbody></table>';
		echo '<h2>基本設定</h2>';
		echo '<table class="widefat striped"><thead><tr><th>設定</th><th>説明</th></tr></thead><tbody>';
		echo '<tr><td>重量判定を利用する</td><td>SKU重量を使って注文全体の重量上限を判定します。有効にした場合だけ「重量上限(g)」が表示され、対象SKUの重量設定が必要になります。</td></tr>';
		echo '<tr><td>重量上限(g)</td><td>自動箱詰めできる注文全体の商品重量の上限です。超過した注文は個別対応配送へ切り替えます。外箱の重量は含みません。</td></tr>';
		echo '<tr><td>クール必須判定を利用する</td><td>クール必須の商品を判定し、対象注文ではクール便用として有効な外箱だけを使用します。</td></tr>';
		echo '<tr><td>梱包マージンを利用する</td><td>SKUに設定したマージンを商品寸法へ加えて箱詰めします。無効時は商品の実寸だけを使用します。</td></tr>';
		echo '<tr><td>天地固定を利用する</td><td>SKUの天地固定設定を箱詰め時の向きへ反映します。無効時は箱へ収まるよう商品の向きを変えられます。</td></tr>';
		echo '<tr><td>同時購入数上限</td><td>1注文で自動箱詰めする対象商品の合計数量です。上限を超えた注文は個別対応配送へ切り替えます。</td></tr>';
		echo '<tr><td>個別対応配送方法</td><td>自動箱詰めできない注文へ適用するWelcartの配送方法です。</td></tr>';
		echo '<tr><td>個別対応送料</td><td>個別対応配送へ切り替えた注文に使用するWelcartの送料設定です。</td></tr>';
		echo '<tr><td>個別対応送料方針</td><td>「表示送料を確定額とする」は注文時の表示額を確定送料にします。「注文後に差額確認を行う」は注文後に実送料との差額を確認する運用です。</td></tr>';
		echo '</tbody></table>';
		echo '<h2>外箱設定</h2>';
		echo '<table class="widefat striped"><thead><tr><th>設定</th><th>説明</th></tr></thead><tbody>';
		echo '<tr><td>箱名</td><td>管理画面と購入者の確認画面に表示する名称です。購入者に見せられない倉庫内コードなどは含めません。</td></tr>';
		echo '<tr><td>内寸(mm)</td><td>箱の内側の幅・長さ・深さです。容量が小さい有効な箱から順に調べ、商品がすべて入る箱を採用します。</td></tr>';
		echo '<tr><td>Welcart配送方法・送料</td><td>その箱を使用したときに適用する配送方法と送料です。</td></tr>';
		echo '<tr><td>優先順位</td><td>同じ内寸容量の箱が複数ある場合の判定順です。数値が小さい箱を先に使用します。</td></tr>';
		echo '<tr><td>状態</td><td>有効な箱だけを新しい箱詰め計算の候補にします。無効にしても過去の受注結果は変更しません。</td></tr>';
		echo '</tbody></table>';
		echo '<h2>商品・SKU設定</h2>';
		echo '<table class="widefat striped"><thead><tr><th>設定・操作</th><th>説明</th></tr></thead><tbody>';
		echo '<tr><td>商品共通設定</td><td>商品が箱詰め対象か、クール必須かなど、商品内のSKUに共通する条件です。</td></tr>';
		echo '<tr><td>SKU寸法</td><td>商品の幅・長さ・高さです。箱詰め対象SKUでは必須で、梱包マージンが有効な場合は加算後の寸法を使用します。</td></tr>';
		echo '<tr><td>SKU重量</td><td>WelcartのSKU重量を使用します。重量判定が有効な場合に必須です。</td></tr>';
		echo '<tr><td>CSVを事前チェック</td><td>CSVを保存する前に全行の形式と設定内容を検証します。エラーが1件でもある場合は保存できません。</td></tr>';
		echo '<tr><td>CSV取込の確定</td><td>事前チェックした同じファイルを一括保存します。事前チェック後にファイル、SKU、箱詰め設定が変わった場合は、もう一度事前チェックが必要です。</td></tr>';
		echo '</tbody></table>';
		echo '<h2>固定設定</h2>';
		echo '<p>次の値は安全性と運用基準を統一するため変更できません。</p>';
		echo '<table class="widefat striped"><thead><tr><th>項目</th><th>固定値</th><th>説明</th></tr></thead><tbody>';
		echo '<tr><td>箱詰め送料計算</td><td>準備完了後は常時利用</td><td>「その他」でWelcart箱詰め設定を有効にし、必須設定を登録して基本設定の保存に成功すると送料計算を開始します。設定不足の間は開始しません。</td></tr>';
		echo '<tr><td>SKU行数上限</td><td>' . esc_html( Settings::FIXED_SKU_LIMIT ) . '行</td><td>1回の計算で扱うSKU行数の上限です。超過時は個別対応配送へ切り替えます。</td></tr>';
		echo '<tr><td>注意時間</td><td>' . esc_html( Settings::FIXED_ATTENTION_HOURS ) . '時間</td><td>個別対応が未処理のまま経過した場合に注意対象とする時間です。</td></tr>';
		echo '<tr><td>長期滞留時間</td><td>' . esc_html( Settings::FIXED_OVERDUE_HOURS ) . '時間</td><td>個別対応を長期滞留として扱う時間です。</td></tr>';
		echo '<tr><td>ログ保持日数</td><td>' . esc_html( Settings::FIXED_LOG_RETENTION_DAYS ) . '日</td><td>通常の計算・運用ログを保持する期間です。</td></tr>';
		echo '<tr><td>自動箱詰め数量の目安</td><td>' . esc_html( Settings::FIXED_PACKING_QUANTITY_GUIDE ) . '点</td><td>運用上の目安です。実際の計算結果は商品寸法、外箱、配送先などにより変わります。</td></tr>';
		echo '<tr><td>確認画面の案内文</td><td>' . esc_html( Settings::FIXED_CUSTOMER_MESSAGE ) . '</td><td>購入者の確認画面へ表示する固定案内です。</td></tr>';
		echo '</tbody></table>';
		self::footer();
	}

	private static function settings_migration_panel() {
		global $wpdb;
		$blocked = get_option( 'sosc_settings_migration_blocked', false );
		if ( is_string( $blocked ) && '' !== $blocked ) {
			echo '<div class="notice notice-error inline"><h2>設定テーブルの移行に失敗しました</h2><p>エラー: ' . esc_html( $blocked ) . '</p><p>データベースの権限と空き容量を確認し、問題を解消してから再読み込みしてください。</p></div>';
			return;
		}
		if ( ! is_array( $blocked ) || ! $blocked ) {
			return;
		}
		$table = $wpdb->prefix . 'so_short_code';
		echo '<div class="notice notice-error inline"><h2>既存設定の重複を解決してください</h2><p>バックアップ: ' . esc_html( get_option( 'sosc_settings_migration_backup', '' ) ) . '</p>';
		foreach ( $blocked as $name ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,param_value FROM {$table} WHERE param_name=%s ORDER BY id", $name ) );
			echo '<form method="post"><p><strong>' . esc_html( $name ) . '</strong></p>';
			wp_nonce_field( 'sosc_packing_admin' );
			echo '<input type="hidden" name="sosc_packing_action" value="resolve_settings_duplicate"><input type="hidden" name="param_name" value="' . esc_attr( $name ) . '">';
			foreach ( $rows as $row ) {
				echo '<label><input required type="radio" name="keep_id" value="' . esc_attr( $row->id ) . '"> ID ' . esc_html( $row->id ) . ': ' . esc_html( $row->param_value ) . '</label><br>';
			}
			echo '<button class="button">この値を保持</button></form>';
		}
		echo '</div>';
	}

	private static function header( $title ) {
		echo '<div class="wrap"><h1>箱詰め設定</h1><nav class="nav-tab-wrapper" aria-label="箱詰め設定">';
		$current_tab = self::current_tab();
		foreach ( self::tabs() as $tab => $details ) {
			if ( ! current_user_can( $details['capability'] ) ) {
				continue;
			}
			$class = 'nav-tab' . ( $current_tab === $tab ? ' nav-tab-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( self::tab_url( $tab ) ) . '">' . esc_html( $details['label'] ) . '</a>';
		}
		echo '</nav>';
		if ( '' !== $title ) {
			echo '<h2>' . esc_html( $title ) . '</h2>';
		}
		self::notice();
	}

	private static function tabs() {
		return array(
			'dashboard'   => array(
				'label'      => '基本設定',
				'callback'   => 'dashboard',
				'capability' => 'manage_options',
			),
			'boxes'       => array(
				'label'      => '外箱設定',
				'callback'   => 'boxes',
				'capability' => 'manage_options',
			),
			'products'    => array(
				'label'      => '商品設定状況',
				'callback'   => 'products',
				'capability' => 'manage_options',
			),
			'individual'  => array(
				'label'      => '個別対応配送',
				'callback'   => 'individual',
				'capability' => 'manage_options',
			),
			'reference'   => array(
				'label'      => '参考計算',
				'callback'   => 'reference',
				'capability' => 'manage_options',
			),
			'system'      => array(
				'label'      => 'システム状態',
				'callback'   => 'system',
				'capability' => 'manage_options',
			),
			'explanation' => array(
				'label'      => '説明',
				'callback'   => 'explanation',
				'capability' => 'manage_options',
			),
		);
	}

	private static function current_tab() {
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$tabs = self::tabs();
		if ( isset( $tabs[ $tab ] ) ) {
			return $tab;
		}
		return current_user_can( 'manage_options' ) ? 'dashboard' : 'individual';
	}

	private static function tab_url( $tab ) {
		return add_query_arg(
			array(
				'page' => 'sosc_packing',
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	private static function footer() {
		echo '</div>';
	}

	private static function select_allowed_html() {
		return array(
			'select' => array(
				'id'       => true,
				'name'     => true,
				'required' => true,
			),
			'option' => array(
				'value'    => true,
				'selected' => true,
			),
		);
	}

	private static function delivery_select( $name, $selected_value, $id = '' ) {
		global $usces;
		$html = '<select required' . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . ' name="' . esc_attr( $name ) . '"><option value="">選択してください</option>';
		$rows = isset( $usces->options['delivery_method'] ) ? (array) $usces->options['delivery_method'] : array();
		foreach ( $rows as $row ) {
			if ( ! isset( $row['id'] ) ) {
				continue;
			}
			$html .= '<option value="' . esc_attr( $row['id'] ) . '" ' . selected( (string) $selected_value, (string) $row['id'], false ) . '>' . esc_html( $row['name'] . ' (ID:' . $row['id'] . ')' ) . '</option>';
		}
		return $html . '</select>';
	}

	private static function shipping_select( $name, $selected_value, $id = '' ) {
		global $usces;
		$html = '<select required' . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . ' name="' . esc_attr( $name ) . '"><option value="">選択してください</option>';
		$rows = isset( $usces->options['shipping_charge'] ) ? (array) $usces->options['shipping_charge'] : array();
		foreach ( $rows as $row ) {
			if ( ! isset( $row['id'] ) ) {
				continue;
			}
			$html .= '<option value="' . esc_attr( $row['id'] ) . '" ' . selected( (string) $selected_value, (string) $row['id'], false ) . '>' . esc_html( $row['name'] . ' (ID:' . $row['id'] . ')' ) . '</option>';
		}
		return $html . '</select>';
	}
}
