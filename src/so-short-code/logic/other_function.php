<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * --------------------------
 * H1非表示
 * メニューにnone_h1作成し、その対象ページのh1を非表示
 * ※PHPはフラグだけ渡す
 * --------------------------
 */
function sosc_none_h1() {
	// 利用状況を取得
	$h1_check_val = sosc_get_value( 'h1_is_check', '0' );
	if ( '1' !== (string) $h1_check_val ) {
		// 利用チェックが入っていなければ終了
		return false;
	}
	// 対象を取得
	$target_page_list = wp_get_nav_menu_items( 'none_h1' );
	if ( false === $target_page_list ) {
		// 無ければ戻る
		return false;
	}
	$is_target = false;
	foreach ( $target_page_list as $val ) {
		// 対象URLだったら
		if ( get_the_permalink() === $val->url ) {
			$is_target = true;
			break;
		}
	}
	echo '<div class="sosc_none_h1" style="display:none;">' . ( $is_target ? 'true' : 'false' ) . '</div>';
}
add_action( 'wp_footer', 'sosc_none_h1' );

/**
 * --------------------------
 * コンテンツ上マージン非表示
 * メニューにnone_contents_top_margin作成し、その対象ページのコンテンツ上マージンを非表示
 * ※PHPはフラグだけ渡す
 * --------------------------
 */
function sosc_none_contents_tm() {
	// 利用状況を取得
	$contents_tm_check_val = sosc_get_value( 'contents_tm_is_check', '0' );
	if ( '1' !== (string) $contents_tm_check_val ) {
		// 利用チェックが入っていなければ終了
		return false;
	}
	// 対象を取得
	$target_page_list = wp_get_nav_menu_items( 'none_contents_top_margin' );
	if ( false === $target_page_list ) {
		// 無ければ戻る
		return false;
	}
	$is_target = false;
	foreach ( $target_page_list as $val ) {
		// 対象URLだったら
		if ( get_the_permalink() === $val->url ) {
			$is_target = true;
			break;
		}
	}
	echo '<div class="sosc_none_contents_tm" style="display:none;">' . ( $is_target ? 'true' : 'false' ) . '</div>';
}
add_action( 'wp_footer', 'sosc_none_contents_tm' );

/**
 * --------------------------
 * コンテンツ下マージン非表示
 * メニューにnone_contents_bottom_margin作成し、その対象ページのコンテンツ上マージンを非表示
 * ※PHPはフラグだけ渡す
 * --------------------------
 */
function sosc_none_contents_bm() {
	// 利用状況を取得
	$contents_bm_check_val = sosc_get_value( 'contents_bm_is_check', '0' );
	if ( '1' !== (string) $contents_bm_check_val ) {
		// 利用チェックが入っていなければ終了
		return false;
	}
	// 対象を取得
	$target_page_list = wp_get_nav_menu_items( 'none_contents_bottom_margin' );
	if ( false === $target_page_list ) {
		// 無ければ戻る
		return false;
	}
	$is_target = false;
	foreach ( $target_page_list as $val ) {
		// 対象URLだったら
		if ( get_the_permalink() === $val->url ) {
			$is_target = true;
			break;
		}
	}
	echo '<div class="sosc_none_contents_bm" style="display:none;">' . ( $is_target ? 'true' : 'false' ) . '</div>';
}
add_action( 'wp_footer', 'sosc_none_contents_bm' );

/**
 * --------------------------
 * Font変更
 * ※PHPはフラグだけ渡す
 * --------------------------
 */
function sosc_change_font() {
	// 利用状況を取得
	$font_select_val = sosc_get_value( 'font_change_select', 'default' );
	if ( 'default' === $font_select_val ) {
		// defaultの場合終了
		return false;
	}
	echo '<div class="sosc_change_font" style="display:none;">' . esc_html( $font_select_val ) . '</div>';
}
add_action( 'wp_footer', 'sosc_change_font' );

/**
 * --------------------------
 * Font変更(GoogleFont使用)
 * --------------------------
 */
/**
 * --------------------------
 * コンタクトフォーム基本追加設定
 * --------------------------
 */
function sosc_contact_form_add() {
	// 利用状況を取得
	$contact_form_check_val = sosc_get_value( 'contact_form_is_check', '0' );
	if ( '1' !== (string) $contact_form_check_val ) {
		// 利用チェックが入っていなければ終了
		return false;
	}
	echo '<div class="sosc_contact_form_add" style="display:none;">true</div>';
}
add_action( 'wp_footer', 'sosc_contact_form_add' );
