<?php
/*
 * Plugin Name: 湘南オンラインプラグイン
 * Plugin URI: https://shonan-online.co.jp
 * Description: 湘南オンラインショートコード
 * Author: shonan-online Nagakura
 * Version: 3.8.5
 * Update URI: https://github.com/KeigoNagakura/shonan-online-short-code
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * Author URI: https://shonan-online.co.jp
 * License: GPL2
 */

/*
 * Copyright 2024/11/08 湘南オンライン長倉圭吾 (email : information_a@shonan-online.co.jp)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */

// リリースはmainへのマージ後にGitHub Actionsが版付きZIPを生成する。
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * ====================================================================
 * ファイルの読み込み
 * ====================================================================
 */
// 基準ファイル
define( 'SOSC_MAIN_FILE', __FILE__ );

// DBにテーブルを作成
require_once __DIR__ . '/logic/init_plugin_table.php';

// 設定を読み込み
require_once __DIR__ . '/settings.php';

// 共通メソッドを読み込み
require_once __DIR__ . '/logic/common.php';

// 入出力のセキュリティ互換ヘルパー.
require_once __DIR__ . '/logic/security.php';
require_once __DIR__ . '/logic/FrontDisplay.php';
\ShonanOnline\ShortCode\FrontDisplay::boot();

// プラグイン用テーブル読み書き機能
require_once __DIR__ . '/logic/my_curd.php';
// Welcart箱詰め送料機能.
require_once __DIR__ . '/packing/bootstrap.php';
// 月次バックアップ機能.
require_once __DIR__ . '/backup/bootstrap.php';
// 購入者向けWelcart帳票ダウンロード.
require_once __DIR__ . '/documents/Download.php';
\ShonanOnline\ShortCode\Documents\Download::boot();
// ショートコードを読み込み
require_once __DIR__ . '/logic/short_code.php';

// その他機能を読み込み
require_once __DIR__ . '/logic/other_function.php';

// SQLを読み込み
require_once __DIR__ . '/logic/sql_list.php';

// アクションを読み込み
require_once __DIR__ . '/logic/actions.php';
// 管理者向けの機能別マニュアル。
require_once __DIR__ . '/manual/Admin.php';
\ShonanOnline\ShortCode\Manual\Admin::boot();

// アップデート処理
require_once __DIR__ . '/update/update.php';

/**
 * ====================================================================
 * 実行
 * ====================================================================
 */
/**
 * 有効時テーブル作成
 **/
function sosc_activate() {

	sosc_create_table();
	if ( class_exists( '\\ShonanOnline\\ShortCode\\Packing\\Database' ) ) {
		\ShonanOnline\ShortCode\Packing\Database::install();
	}
}
register_activation_hook( SOSC_MAIN_FILE, 'sosc_activate' );
/**
 * Deactivate scheduled plugin tasks.
 */
function sosc_deactivate() {
	\ShonanOnline\ShortCode\Packing\Plugin::deactivate();
	\ShonanOnline\ShortCode\Backup\Plugin::deactivate();
}
register_deactivation_hook( SOSC_MAIN_FILE, 'sosc_deactivate' );

/*
 * メニューに追加
 */
function sosc_menu() {
	// ショートコード一覧を表示を追加
	add_menu_page( '湘南オンラインプラグイン', '湘南オンラインプラグイン', 'manage_options', 'sosc_menu', 'sosc_view', 'dashicons-admin-plugins', 26 );
}
add_action( 'admin_menu', 'sosc_menu' );

/**
 * 自動生成される親ページのサブメニュー名を分かりやすくする.
 */
function sosc_rename_parent_submenu() {

	global $submenu;

	if ( empty( $submenu['sosc_menu'] ) || ! is_array( $submenu['sosc_menu'] ) ) {
		return;
	}

	foreach ( $submenu['sosc_menu'] as &$item ) {
		if ( isset( $item[2] ) && 'sosc_menu' === $item[2] ) {
			$item[0] = '機能一覧';
			break;
		}
	}
	unset( $item );
}
add_action( 'admin_menu', 'sosc_rename_parent_submenu', 99 );
/*
 * ショートコード一覧を表示
 */
function sosc_view() {
	require_once 'view/sosc_menu.php';
}
