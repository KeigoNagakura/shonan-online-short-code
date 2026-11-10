<?php
/**
 * Packing activation readiness checks.
 *
 * @package ShonanOnlineShortCode
 */

namespace ShonanOnline\ShortCode\Packing;

final class Readiness {
	public static function check( $settings = null ) {
		global $wpdb, $usces;
		$settings = is_array( $settings ) ? $settings : Settings::all();
		$errors   = new \WP_Error();
		if ( ! isset( $usces ) || ! is_object( $usces ) ) {
			$errors->add( 'welcart_missing', 'Welcartを有効化してください。' );
			return $errors;
		}
		$boxes = $wpdb->get_results( 'SELECT * FROM ' . Database::table( 'boxes' ) . ' WHERE enabled=1 AND inner_width BETWEEN 1 AND 10000 AND inner_length BETWEEN 1 AND 10000 AND inner_depth BETWEEN 1 AND 10000 ORDER BY priority,id' );
		if ( ! is_array( $boxes ) || '' !== $wpdb->last_error ) {
			$errors->add( 'boxes_read_failed', '箱設定を確認できませんでした。データベースの状態を確認してください。' );
			return $errors;
		}
		$valid_boxes = array();
		foreach ( $boxes as $box ) {
			if ( false !== $usces->get_delivery_method_index( $box->delivery_method_id ) && false !== $usces->get_shipping_charge_index( $box->shipping_charge_id ) ) {
				$valid_boxes[] = $box;
			}
		}
		if ( ! $valid_boxes ) {
			$errors->add( 'boxes_missing', '有効な外箱を1件以上登録してください。' );
		}
		$skus = $wpdb->get_results( "SELECT s.meta_id,s.post_id,s.weight FROM {$wpdb->prefix}usces_skus s INNER JOIN {$wpdb->posts} p ON p.ID=s.post_id WHERE p.post_mime_type='item' AND p.post_status='publish'" );
		if ( ! is_array( $skus ) || '' !== $wpdb->last_error ) {
			$errors->add( 'skus_read_failed', '公開中SKUを確認できませんでした。データベースの状態を確認してください。' );
			return $errors;
		}
		$config_rows = $wpdb->get_results( 'SELECT c.* FROM ' . Database::table( 'skus' ) . " c INNER JOIN {$wpdb->prefix}usces_skus s ON s.post_id=c.post_id AND s.meta_id=c.sku_meta_id INNER JOIN {$wpdb->posts} p ON p.ID=s.post_id WHERE p.post_mime_type='item' AND p.post_status='publish' AND c.width BETWEEN 1 AND 10000 AND c.length BETWEEN 1 AND 10000 AND c.depth BETWEEN 1 AND 10000" );
		if ( ! is_array( $config_rows ) || '' !== $wpdb->last_error ) {
			$errors->add( 'sku_settings_read_failed', 'SKU梱包設定を確認できませんでした。データベースの状態を確認してください。' );
			return $errors;
		}
		$configs = array();
		foreach ( $config_rows as $config_row ) {
			$configs[ (int) $config_row->post_id . ':' . (int) $config_row->sku_meta_id ] = $config_row;
		}
		$missing = 0;
		foreach ( $skus as $sku ) {
			if ( method_exists( $usces, 'getItemDivision' ) && 'shipped' !== $usces->getItemDivision( $sku->post_id ) ) {
				continue;
			}
			if ( method_exists( $usces, 'getItemIndividualSCharge' ) && $usces->getItemIndividualSCharge( $sku->post_id ) ) {
				continue;
			}
			$config         = isset( $configs[ (int) $sku->post_id . ':' . (int) $sku->meta_id ] ) ? $configs[ (int) $sku->post_id . ':' . (int) $sku->meta_id ] : null;
			$margin         = $config && '1' === (string) $settings['use_margin'] && $config->margin_required ? (int) $config->margin : 0;
			$invalid_margin = $config && ( $margin < 0 || $margin > 1000 || ( $config->margin_required && '1' === (string) $settings['use_margin'] && $margin < 1 ) || $config->width + 2 * $margin > 10000 || $config->length + 2 * $margin > 10000 || $config->depth + 2 * $margin > 10000 );
			if ( ! $config || $invalid_margin || ( '1' === (string) $settings['use_weight'] && absint( $sku->weight ) < 1 ) ) {
				++$missing;
			}
		}
		if ( $missing ) {
			$errors->add( 'sku_settings_missing', sprintf( '公開中の箱詰め対象SKUに必須設定不足が%d件あります。', $missing ) );
		}
		if ( empty( $settings['individual_delivery_id'] ) || empty( $settings['individual_shipping_id'] ) || false === $usces->get_delivery_method_index( $settings['individual_delivery_id'] ) || false === $usces->get_shipping_charge_index( $settings['individual_shipping_id'] ) ) {
			$errors->add( 'individual_delivery_missing', '個別対応配送用の配送方法と送料を選択してください。' );
		}
		return $errors;
	}
}
