<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sosc_requested_tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'shortcodes';
$sosc_backup_enabled = \ShonanOnline\ShortCode\Backup\Plugin::enabled();
$sosc_allowed_tabs   = array( 'shortcodes', 'welcart', 'other' );
if ( $sosc_backup_enabled ) {
	$sosc_allowed_tabs[] = 'backup';
}
$sosc_active_tab = in_array( $sosc_requested_tab, $sosc_allowed_tabs, true ) ? $sosc_requested_tab : 'shortcodes';
?>

<style>
	/* タブに隙間ができるので調整 */
	.tab-content {
		margin-top: -7px;
	}
</style>

<div class="wrap sosc-bs-scope">
	<h1>湘南オンラインプラグイン</h1>
	<p><a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=sosc_manual' ) ); ?>">使い方マニュアルを見る</a></p>
	<hr class="w-100">

	<ul class="nav nav-tabs border-bottom-0" id="setTab" role="tablist">
		<li class="nav-item" role="presentation">
			<button class="nav-link <?php echo esc_attr( 'shortcodes' === $sosc_active_tab ? 'active' : '' ); ?>" id="panel-tab1" data-sosc-tab-key="shortcodes"
				data-sosc-tab-target="tab-pane1"
				type="button" role="tab" tabindex="<?php echo 'shortcodes' === $sosc_active_tab ? '0' : '-1'; ?>" aria-controls="tab-pane1" aria-selected="<?php echo esc_attr( 'shortcodes' === $sosc_active_tab ? 'true' : 'false' ); ?>">
				ショートコード
			</button>
		</li>
		<li class="nav-item" role="presentation">
			<button class="nav-link <?php echo esc_attr( 'welcart' === $sosc_active_tab ? 'active' : '' ); ?>" id="panel-tab2" data-sosc-tab-key="welcart"
				data-sosc-tab-target="tab-pane2"
				type="button" role="tab" tabindex="<?php echo 'welcart' === $sosc_active_tab ? '0' : '-1'; ?>" aria-controls="tab-pane2" aria-selected="<?php echo esc_attr( 'welcart' === $sosc_active_tab ? 'true' : 'false' ); ?>">
				Welcart用ショートコード
			</button>
		</li>
		<li class="nav-item" role="presentation">
			<button class="nav-link <?php echo esc_attr( 'other' === $sosc_active_tab ? 'active' : '' ); ?>" id="panel-tab4" data-sosc-tab-key="other"
				data-sosc-tab-target="tab-pane4"
				type="button" role="tab" tabindex="<?php echo 'other' === $sosc_active_tab ? '0' : '-1'; ?>" aria-controls="tab-pane4" aria-selected="<?php echo esc_attr( 'other' === $sosc_active_tab ? 'true' : 'false' ); ?>">
				その他
			</button>
		</li>
		<li class="nav-item" id="sosc-backup-tab-item" role="presentation" <?php echo $sosc_backup_enabled ? '' : 'hidden'; ?>>
			<button class="nav-link <?php echo esc_attr( 'backup' === $sosc_active_tab ? 'active' : '' ); ?>" id="panel-tab6" data-sosc-tab-key="backup"
				data-sosc-tab-target="tab-pane6"
				type="button" role="tab" tabindex="<?php echo 'backup' === $sosc_active_tab ? '0' : '-1'; ?>" aria-controls="tab-pane6" aria-selected="<?php echo esc_attr( 'backup' === $sosc_active_tab ? 'true' : 'false' ); ?>">
				月次バックアップ
			</button>
		</li>
	</ul>
	<!-- Tab panes -->
	<div class="tab-content border p-3" id="setTabContent">
		<div class="tab-pane sosc-tab-pane fade <?php echo esc_attr( 'shortcodes' === $sosc_active_tab ? 'show active' : '' ); ?>" id="tab-pane1" role="tabpanel" aria-labelledby="panel-tab1">
			<?php require_once __DIR__ . '/panel/short_code_view.php'; ?>
		</div>
		<div class="tab-pane sosc-tab-pane fade <?php echo esc_attr( 'welcart' === $sosc_active_tab ? 'show active' : '' ); ?>" id="tab-pane2" role="tabpanel" aria-labelledby="panel-tab2">
			<?php require_once __DIR__ . '/panel/short_code_wc_view.php'; ?>
		</div>
		<div class="tab-pane sosc-tab-pane fade <?php echo esc_attr( 'other' === $sosc_active_tab ? 'show active' : '' ); ?>" id="tab-pane4" role="tabpanel" aria-labelledby="panel-tab4">
			<?php require_once __DIR__ . '/panel/other_view.php'; ?>
		</div>
		<div class="tab-pane sosc-tab-pane fade <?php echo esc_attr( 'backup' === $sosc_active_tab ? 'show active' : '' ); ?>" id="tab-pane6" role="tabpanel" aria-labelledby="panel-tab6">
			<?php require_once __DIR__ . '/panel/backup_view.php'; ?>
		</div>
	</div>
