<?php
/**
 * Plugin explanation tab.
 *
 * @package ShonanOnlineShortCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="p-2">
	<h3><?php esc_html_e( '月次バックアップ機能', 'so-short-code' ); ?></h3>
	<p><?php esc_html_e( 'WordPressのファイルとデータベースを1つのバックアップとして保存する機能です。復元はサーバー管理者が手動で行います。', 'so-short-code' ); ?></p>

	<h4><?php esc_html_e( '利用開始と実行時期', 'so-short-code' ); ?></h4>
	<ul>
		<li><?php esc_html_e( 'Windowsで展開する場合は、パス長制限を避けるためC:\\soscなど浅い場所へZIPを置いて展開してください。', 'so-short-code' ); ?></li>
		<li><?php esc_html_e( '「その他」タブで「月次バックアップ」の「利用」を有効にして保存します。', 'so-short-code' ); ?></li>
		<li><?php esc_html_e( '自動バックアップはWordPressのサイト時間で毎月末日の午前3時以降に実行されます。', 'so-short-code' ); ?></li>
		<li><?php esc_html_e( 'バックアップ処理中はファイル更新との競合を避けるため、サイトが一時的にメンテナンス表示となります。', 'so-short-code' ); ?></li>
		<li><?php esc_html_e( 'WP-Cronはサイトへのアクセス時に動くため、アクセス状況によって実行が遅れる場合があります。', 'so-short-code' ); ?></li>
		<li><?php esc_html_e( '利用を有効にした場合だけ表示される「月次バックアップ」タブから、手動実行、結果確認、ダウンロードができます。', 'so-short-code' ); ?></li>
		<li><?php esc_html_e( '利用を有効にすると暗号化パスワード欄が表示されます。パスワードを入力した場合だけ、対応環境でAES-256暗号化します。', 'so-short-code' ); ?></li>
		<li><?php esc_html_e( 'パスワード欄を空欄で保存した場合は、登録済みのパスワードを維持します。暗号化を解除する場合は「登録済みパスワードを削除」を選択してください。パスワード未登録時は暗号化なしで作成し、登録済みで暗号化できない場合は平文ZIPを作らず中止します。', 'so-short-code' ); ?></li>
		<li><?php esc_html_e( '暗号化パスワードは画面へ再表示されません。暗号化を利用する場合は必ずサーバー外へ保管してください。パスワード変更前のバックアップには以前のパスワードが必要です。', 'so-short-code' ); ?></li>
		<li><?php esc_html_e( '入力パスワードはWordPress認証ソルト由来の鍵で暗号化してDBへ保存します。認証ソルトが変わった場合はパスワードを再入力してください。', 'so-short-code' ); ?></li>
		<li><?php esc_html_e( 'バックアップ内のdatabase.sqlには暗号化パスワード設定を含めません。復元後にAES-256を継続する場合は再入力してください。', 'so-short-code' ); ?></li>
	</ul>

	<h4><?php esc_html_e( '保存先と作成タイミング', 'so-short-code' ); ?></h4>
	<p><?php esc_html_e( '利用チェックを保存しただけでは保存ディレクトリは作成されません。最初の手動バックアップ、または最初の自動バックアップが実行されたときに次の構成を自動作成します。', 'so-short-code' ); ?></p>
	<pre class="bg-light border rounded p-3"><code><?php echo esc_html( "wp-content/sosc-backups/\n├── .htaccess\n├── web.config\n├── index.php\n└── private-{ランダム識別子}/\n    ├── index.php\n    └── sosc-YYYYMMDD-HHMMSS.zip.php" ); ?></code></pre>
	<p><?php esc_html_e( '保存先はWordPressディレクトリ内なので、WordPress一式を別サーバーへ移設した場合も一緒に引き継げます。', 'so-short-code' ); ?></p>

	<h4><?php esc_html_e( '外部アクセス防止', 'so-short-code' ); ?></h4>
	<ul>
		<li><?php esc_html_e( 'バックアップ専用のsosc-backupsディレクトリにだけアクセス拒否ファイルを作成します。', 'so-short-code' ); ?></li>
		<li><?php esc_html_e( 'WordPress直下、wp-content直下、その他の既存.htaccessは変更しません。', 'so-short-code' ); ?></li>
		<li><?php esc_html_e( '同名のアクセス拒否ファイルが既にある場合は上書きしません。', 'so-short-code' ); ?></li>
		<li><?php esc_html_e( 'サーバー上ではPHP実行停止ガード付きの.zip.phpとして保管し、直接URLからZIP内容を取得できないようにします。', 'so-short-code' ); ?></li>
		<li><?php esc_html_e( '管理者が管理画面からダウンロードした場合は、通常の.zipファイルとして保存されます。', 'so-short-code' ); ?></li>
	</ul>

	<h4><?php esc_html_e( '保存内容と世代管理', 'so-short-code' ); ?></h4>
	<ul>
		<li><?php esc_html_e( 'WordPress設置ディレクトリのファイルとデータベースを保存します。', 'so-short-code' ); ?></li>
		<li><?php esc_html_e( 'キャッシュ、一時ファイル、ログ、バックアップ保存先、代表的な他バックアッププラグインの保存先は除外します。', 'so-short-code' ); ?></li>
		<li><?php esc_html_e( '正常に完成したバックアップを6世代保持し、7世代目の完成後に最古の1件を削除します。', 'so-short-code' ); ?></li>
		<li><?php esc_html_e( '必要な空き容量が不足する場合や、非InnoDBテーブルが存在する場合はバックアップを作成しません。', 'so-short-code' ); ?></li>
	</ul>

	<h4><?php esc_html_e( '手動復元', 'so-short-code' ); ?></h4>
	<ol>
		<li><?php esc_html_e( '管理画面からバックアップZIPをダウンロードします。', 'so-short-code' ); ?></li>
		<li><?php esc_html_e( '現在のファイルとDBを別途退避し、サイトをメンテナンス状態にします。', 'so-short-code' ); ?></li>
		<li><?php esc_html_e( 'ZIP内のファイル一式をWordPress設置ディレクトリへ配置します。', 'so-short-code' ); ?></li>
		<li><?php esc_html_e( '復元先DBを空にしてからdatabase.sqlをインポートします。', 'so-short-code' ); ?></li>
		<li><?php esc_html_e( 'wp-config.phpのDB接続情報、ファイル権限、ログイン、ページ表示、プラグイン状態、PHPログを確認します。', 'so-short-code' ); ?></li>
	</ol>
	<div class="alert alert-warning" role="alert">
		<?php esc_html_e( '復元処理はプラグインから自動実行されません。database.sqlにはテーブルを削除して再作成するSQLが含まれるため、投入先DBを必ず確認してください。', 'so-short-code' ); ?>
	</div>
</div>
