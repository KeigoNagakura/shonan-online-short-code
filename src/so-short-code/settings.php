<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** ================================
 * パス類
 * ================================ */
// APIサイト
define( 'SOSC_API_URL', 'https://api.shonan-online.com/' );
// プラグインパス
define( 'SOSC_PLUGIN_FILE', SOSC_MAIN_FILE );
// プラグインbasename（← 自動更新UIで必須）
define( 'SOSC_PLUGIN_BASENAME', plugin_basename( SOSC_MAIN_FILE ) );
// プラグインディレクトリ
define( 'SOSC_PLUGIN_PATH', plugin_dir_url( SOSC_MAIN_FILE ) );
// プラグインCSSパス取得
define( 'SOSC_CSS_PATH', SOSC_PLUGIN_PATH . 'css/' );
// プラグインJSパス取得
define( 'SOSC_JS_PATH', SOSC_PLUGIN_PATH . 'js/' );
// プラグインIMGパス取得
define( 'SOSC_IMG_PATH', SOSC_PLUGIN_PATH . 'img/' );

/** ================================
 * 更新設定
 * ================================ */
// 更新日時
define( 'SOSC_CSS_JS_TIME', '2026111001' );
// 更新ログ
define(
	'CHANGELOG', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Existing public constant retained for backward compatibility.
	'
    <ul>
    <li>2026/09/11 - 03.08.05 - 一覧・カード表示、フォーム・店頭受取、設定保全、LP循環参照と更新検証の不具合を修正</li>
    <li>2026/09/09 - 03.08.04 - WordPressのフロント画面と管理画面でjQueryの$エイリアスを利用可能化</li>
    <li>2026/09/03 - 03.08.03 - 更新パッケージ検証、梱包保守Cron、JSON保存失敗時の安全性を改善</li>
    <li>2026/09/03 - 03.08.02 - バックアップ暗号化の必須化、仕様整合性および開発時の改行規則を改善</li>
    <li>2026/09/02 - 03.08.01 - 全体監査に基づくセキュリティ、バックアップ、梱包、UI・UX、互換性およびテストの改善</li>
    <li>2026/09/01 - 03.08.00 - 月次バックアップ画面、暗号化判定、保存先権限、Windows向けZIP構造を改善</li>
    <li>2026/09/01 - 03.07.02 - 月次バックアップの暗号化設定をパスワード入力だけで判定する方式へ簡略化</li>
    <li>2026/09/01 - 03.07.01 - 湘南オンラインプラグインの既存機能一覧への管理メニュー導線を復元</li>
    <li>2026/09/01 - 03.07.00 - 月次バックアップ、任意指定パスワードによるAES-256暗号化、説明タブを追加</li>
    <li>2026/05/24 - 03.06.02 - Swellデザイン追加 - WelCart用機能修正</li>
    <li>2026/05/24 - 03.05.01 - WelCart用機能の追加</li>
    <li>2026/04/21 - 03.04.03 - WelCart用機能の追加</li>
    <li>2026/03/20 - 03.04.02 - WelCart用機能の追加</li>
    <li>2026/03/15 - 03.04.01 - WelCart用機能の追加</li>
    <li>2026/03/02 - 03.03.01 - 機能追加</li>
    <li>2026/03/02 - 03.02.01 - 機能追加</li>
    <li>2026/02/25 - 03.01.01 - 対応クラスを表示</li>
    <li>2026/02/24 - 03.01.00 - テンプレートとの競合回避</li>
    <li>2026/01/26 - 03.00.02 - 画像が取れないバグ修正</li>
    <li>2026/01/26 - 03.00.01 - リリース</li>
    </ul>
'
);
// 現在のプラグインバージョン（ヘッダのVersionと揃える）
define( 'SOSC_VERSION', '3.8.5' );
// 更新サーバ：メタ情報JSONのURL
define( 'SOSC_UPDATE_META_URL', SOSC_API_URL . 'out_source/so-short-code.json' );

/** ================================
 * その他設定
 * ================================ */
// このプラグインのslug
define( 'SOSC_UPDATE_SLUG', 'so-short-code' );

// /**
//  * アーカイブページの「カテゴリ：」等、余計な文字を削除
//  */
// define('SoOutOfTitleTypeFlg', True);

// /**
//  * 自動Pタグ挿入
//  */
// define('SoAutoPBr', false);

// /**
//  * メニュー編集表示・非表示
//  */
// define('SoIsMenuView', True);

// /**
//  *  キーワード・ディスクリプション表示・非表示
//  */
// define('SoIsKeywordDiscription', True);

// /**
//  * タブレット・スマホ使用状況
//  */
// define('SoIsTB', True);
// define('SoIsSP', True);

/** ================================
 * 管理ページ一覧
 * ================================ */
define(
	'SOSC_ADMIN_PAGES',
	array(
		'sosc_menu',
		'sosc_packing',
		'sosc_packing_individual',
	)
);

/** ================================
 * 画像パス
 * ================================ */
// バナー画像
define( 'SOSC_BANNER', SOSC_IMG_PATH . 'logo.svg' );
// アイコン
define( 'SOSC_ICON', SOSC_IMG_PATH . 'logo.svg' );
// NoImage画像
define( 'SOSC_NO_IMG', SOSC_IMG_PATH . 'no-image.svg' );
/** ================================
 * プラグイン説明文(プラグインページ詳細よりアクセス)
 * ================================ */
// 説明
define(
	'SOSC_DESCRIPTION',
	'
湘南オンラインの提供するショートコード集です。<br>
不明点はお気軽にお問合せください。
'
);
// インストール
define(
	'INSTALLATION', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Existing public constant retained for backward compatibility.
	'
<ul>
<li>WordPress の管理画面で「プラグイン」->「プラグインを追加」とクリックします。</li>
<li>プラグインのアップロードをクリック</li>
<li>指定されたzipファイルを選択</li>
<li>プラグインを有効化する。</li>
</ul>
'
);
