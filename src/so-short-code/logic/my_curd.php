<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * =======================================
 * プラグイン用テーブル読み書き機能
 * =======================================
 */
/**
 * 読み込み処理
 */
function sosc_get_value( $param_name, $default = null ) {
	global $wpdb;
	$table = $wpdb->prefix . 'so_short_code';

	$param_name = sanitize_key( $param_name ); // 英数_ハイフン想定（より自由なら sanitize_text_field に）
	if ( '' === $param_name ) {
		return $default;
	}

	$sql = "SELECT param_value FROM {$table} WHERE param_name = %s ORDER BY id DESC LIMIT 1";
	$val = $wpdb->get_var( $wpdb->prepare( $sql, $param_name ) );

	// null のとき default
	return ( null === $val ) ? $default : $val;
}

/**
 * upsert処理
 * ※あれば更新・なければ追加
 */
function sosc_set_value( $param_name, $param_value ) {
	global $wpdb;
	$table = $wpdb->prefix . 'so_short_code';

	$param_name = sanitize_key( $param_name );
	if ( '' === $param_name ) {
		return new WP_Error( 'invalid_param_name', 'param_name が不正です' );
	}

	// param_value は text 想定。HTML を許容するなら wp_kses_post() にする
	if ( is_array( $param_value ) || is_object( $param_value ) ) {
		// 配列/オブジェクトなら JSON で保存する運用に（必要なければ削ってOK）
		$param_value = wp_json_encode( $param_value, JSON_UNESCAPED_UNICODE );
		if ( false === $param_value ) {
			return new WP_Error( 'invalid_param_value', __( 'The setting could not be encoded as JSON.', 'so-short-code' ) );
		}
	} else {
		$param_value = (string) $param_value;
	}

	// 既存チェック（同名が複数あり得る設計なので "最新1件" を想定）
	$exists = $wpdb->get_var(
		$wpdb->prepare( "SELECT id FROM {$table} WHERE param_name = %s ORDER BY id DESC LIMIT 1", $param_name )
	);

	if ( $exists ) {
		$ok = $wpdb->update(
			$table,
			array( 'param_value' => $param_value ),
			array( 'id' => (int) $exists ),
			array( '%s' ),
			array( '%d' )
		);
		if ( false === $ok ) {
			return new WP_Error( 'db_update_failed', $wpdb->last_error ? $wpdb->last_error : '更新に失敗しました' );
		}
		return 'updated';
	} else {
		$ok = $wpdb->insert(
			$table,
			array(
				'param_name'  => $param_name,
				'param_value' => $param_value,
			),
			array( '%s', '%s' )
		);
		if ( false === $ok ) {
			return new WP_Error( 'db_insert_failed', $wpdb->last_error ? $wpdb->last_error : '登録に失敗しました' );
		}
		return 'inserted';
	}
}
/**
 * 削除処理
 */
function sosc_delete_by_name( $param_name ) {
	global $wpdb;
	$table = $wpdb->prefix . 'so_short_code';

	$param_name = sanitize_key( $param_name );
	if ( '' === $param_name ) {
		return new WP_Error( 'invalid_param_name', 'param_name が不正です' );
	}

	// 同名が複数ある可能性があるので、全部消すならこれでOK
	$ok = $wpdb->delete( $table, array( 'param_name' => $param_name ), array( '%s' ) );

	if ( false === $ok ) {
		return new WP_Error( 'db_delete_failed', $wpdb->last_error ? $wpdb->last_error : '削除に失敗しました' );
	}
	return (int) $ok; // 削除件数
}
