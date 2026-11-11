<?php
/** Plugin-bundled, read-only manual in the WordPress administration area. */
namespace ShonanOnline\ShortCode\Manual;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {
	private const PAGE = 'sosc_manual';
	private static $hook = '';

	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'register' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function register() {
		self::$hook = add_submenu_page( 'sosc_menu', '使い方マニュアル', '使い方マニュアル', 'manage_options', self::PAGE, array( __CLASS__, 'page' ) );
	}

	public static function enqueue( $hook ) {
		if ( self::$hook && self::$hook === $hook ) {
			wp_enqueue_style( 'sosc-manual', plugins_url( 'manual/manual.css', SOSC_MAIN_FILE ), array(), SOSC_CSS_JS_TIME );
			wp_enqueue_script( 'sosc-manual', plugins_url( 'manual/manual.js', SOSC_MAIN_FILE ), array(), SOSC_CSS_JS_TIME, true );
		}
	}

	private static function url( $topic = '' ) {
		$args = array( 'page' => self::PAGE );
		if ( $topic ) {
			$args['topic'] = $topic;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'so-short-code' ) );
		}
		$chapters = require __DIR__ . '/chapters.php';
		$topic    = isset( $_GET['topic'] ) ? sanitize_key( wp_unslash( $_GET['topic'] ) ) : '';
		?>
		<div class="wrap sosc-manual">
			<nav class="sosc-manual-breadcrumb" aria-label="パンくずリスト">
				<a href="<?php echo esc_url( self::url() ); ?>">使い方マニュアル</a>
				<?php if ( $topic && isset( $chapters[ $topic ] ) ) : ?>
					<span aria-hidden="true">›</span><span aria-current="page"><?php echo esc_html( $chapters[ $topic ]['title'] ); ?></span>
				<?php endif; ?>
			</nav>
			<?php
			if ( '' === $topic ) {
				self::index( $chapters );
			} elseif ( isset( $chapters[ $topic ] ) ) {
				self::chapter( $topic, $chapters[ $topic ], $chapters );
			} else {
				echo '<h1>ページが見つかりません</h1><p>目次から機能を選び直してください。</p>';
			}
			?>
		</div>
		<?php
	}

	private static function index( $chapters ) {
		$groups = array();
		foreach ( $chapters as $slug => $item ) {
			$groups[ $item['group'] ][ $slug ] = $item;
		}
		?>
		<header class="sosc-manual-hero">
			<h1>湘南オンラインプラグイン 使い方マニュアル</h1>
			<p>このプラグインで何ができるか、準備から使い方まで機能別に案内します。見たい機能を選んでください。</p>
			<p class="sosc-manual-meta">作成日: 2026年11月10日 ／ 第2版 ／ 最終更新日: 2026年11月11日 ／ 対象: プラグイン 3.8.5 の現行機能</p>
		</header>
		<nav class="sosc-manual-jump" aria-label="機能分類">
			<?php foreach ( array_keys( $groups ) as $group_index => $group ) : ?>
				<a href="#sosc-manual-group-<?php echo esc_attr( (string) $group_index ); ?>"><?php echo esc_html( $group ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php foreach ( array_keys( $groups ) as $group_index => $group ) :
			$items = $groups[ $group ]; ?>
			<section id="sosc-manual-group-<?php echo esc_attr( (string) $group_index ); ?>" class="sosc-manual-section">
				<h2><?php echo esc_html( $group ); ?></h2>
				<div class="sosc-manual-grid">
					<?php foreach ( $items as $slug => $item ) : ?>
						<a class="sosc-manual-card" href="<?php echo esc_url( self::url( $slug ) ); ?>">
							<?php if ( ! empty( $item['image'] ) ) : ?>
								<img src="<?php echo esc_url( plugins_url( 'manual/images/' . basename( $item['image'] ), SOSC_MAIN_FILE ) ); ?>" alt="" />
							<?php elseif ( ! empty( $item['preview'] ) ) : ?>
								<span class="sosc-manual-card-preview" aria-hidden="true"><?php echo esc_html( 'vertical' === $item['preview'] ? '縦書き' : '見出しの例' ); ?></span>
							<?php elseif ( ! empty( $item['visual_steps'] ) ) : ?>
								<span class="sosc-manual-card-flow" aria-hidden="true"><?php echo esc_html( implode( ' → ', $item['visual_steps'] ) ); ?></span>
							<?php else : ?>
								<span class="sosc-manual-card-icon" aria-hidden="true">● → ● → ●</span>
							<?php endif; ?>
							<strong><?php echo esc_html( $item['title'] ); ?></strong>
							<span><?php echo esc_html( $item['summary'] ); ?></span>
							<span class="sosc-manual-card-more">使い方を見る <span aria-hidden="true">→</span></span>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach;
	}

	private static function chapter( $slug, $item, $chapters ) {
		$keys = array_keys( $chapters );
		$pos  = array_search( $slug, $keys, true );
		?>
		<header class="sosc-manual-hero">
			<p class="sosc-manual-eyebrow"><?php echo esc_html( $item['group'] ); ?></p>
			<h1><?php echo esc_html( $item['title'] ); ?></h1>
			<p class="sosc-manual-lead"><?php echo esc_html( $item['summary'] ); ?></p>
		</header>
		<main class="sosc-manual-body">
			<figure class="sosc-manual-visual">
				<?php if ( ! empty( $item['image'] ) ) : ?>
					<a class="sosc-manual-zoom" href="<?php echo esc_url( plugins_url( 'manual/images/' . basename( $item['image'] ), SOSC_MAIN_FILE ) ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $item['title'] . 'の画像を原寸で開く' ); ?>"><img src="<?php echo esc_url( plugins_url( 'manual/images/' . basename( $item['image'] ), SOSC_MAIN_FILE ) ); ?>" alt="<?php echo esc_attr( $item['title'] . ( isset( $item['image_kind'] ) ? 'の設定画面' : 'の表示例' ) ); ?>" /></a>
					<?php if ( isset( $item['image_kind'] ) && 'settings' === $item['image_kind'] ) : ?>
						<figcaption>管理画面の設定例です。クリックまたはタップすると原寸で開けます。選択状態は実際のサイトの設定とは異なる場合があります。</figcaption>
					<?php else : ?>
						<figcaption>表示例はクリックまたはタップすると拡大できます。青い見出し帯は検証用で、通常のページには出ません。サイトのテーマや登録内容により見え方は変わります。</figcaption>
					<?php endif; ?>
				<?php elseif ( ! empty( $item['preview'] ) ) : ?>
					<?php if ( 'vertical' === $item['preview'] ) : ?>
						<div class="sosc-manual-preview-banner" role="img" aria-label="バナー画像の中央に縦書きで文字を配置する例"><span>お知らせ</span></div>
					<?php else : ?>
						<div class="sosc-manual-preview-heading" role="img" aria-label="青い左線と下線を付けた見出しの例">サービスのご案内</div>
					<?php endif; ?>
					<figcaption>装飾の模式図です。実際の画像や余白はテーマとページの設定により変わります。</figcaption>
				<?php else : ?>
					<?php self::flow_image( $item['visual_steps'] ); ?>
					<figcaption>操作の流れを示す図です。実際の画面表示とは異なります。</figcaption>
				<?php endif; ?>
			</figure>
			<section><h2>使う前の準備</h2><p><?php echo esc_html( $item['preparation'] ); ?></p></section>
			<section><h2>使い方</h2><ol class="sosc-manual-steps">
				<?php foreach ( $item['steps'] as $step ) : ?><li><?php echo esc_html( $step ); ?></li><?php endforeach; ?>
			</ol></section>
			<?php if ( ! empty( $item['code'] ) ) : ?>
				<section><h2>記述例</h2><p>次の例をコピーし、スラッグや件数を自分のサイトに合わせて変更してください。</p><pre><code><?php echo esc_html( $item['code'] ); ?></code></pre><p class="sosc-manual-copy-row"><button type="button" class="button sosc-manual-copy" data-code="<?php echo esc_attr( $item['code'] ); ?>">ショートコードをコピー</button></p></section>
			<?php endif; ?>
			<section class="sosc-manual-note"><h2>確認・注意点</h2><p><?php echo esc_html( $item['note'] ); ?></p></section>
			<?php if ( 'updates' === $slug || 'backup' === $slug ) : ?>
				<?php self::runtime_status( $slug ); ?>
			<?php endif; ?>
		</main>
		<p class="sosc-manual-meta">作成日: 2026年11月10日 ／ 第2版 ／ 最終更新日: 2026年11月11日</p>
		<nav class="sosc-manual-next" aria-label="マニュアル内の移動">
			<?php if ( $pos > 0 ) : ?><a href="<?php echo esc_url( self::url( $keys[ $pos - 1 ] ) ); ?>">← <?php echo esc_html( $chapters[ $keys[ $pos - 1 ] ]['title'] ); ?></a><?php endif; ?>
			<a href="<?php echo esc_url( self::url() ); ?>">目次へ戻る</a>
			<?php if ( $pos < count( $keys ) - 1 ) : ?><a href="<?php echo esc_url( self::url( $keys[ $pos + 1 ] ) ); ?>"><?php echo esc_html( $chapters[ $keys[ $pos + 1 ] ]['title'] ); ?> →</a><?php endif; ?>
		</nav>
		<?php
	}

	private static function runtime_status( $slug ) {
		$cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$next = wp_next_scheduled( 'backup' === $slug ? 'sosc_monthly_backup' : 'sosc_packing_daily' );
		$next_text = $next ? wp_date( 'Y-m-d H:i', $next ) : '予約なし';
		if ( $next && $next < time() ) {
			$next_text .= '（予定時刻を過ぎています）';
		}
		?>
		<section class="sosc-manual-runtime" aria-labelledby="sosc-manual-runtime-title">
			<h2 id="sosc-manual-runtime-title">このサイトの動作状態</h2>
			<p>表示時点のWordPress設定と予約を読み取っています。外部Cronの実行状況と更新の成功は、この表示だけでは確認できません。</p>
			<dl>
				<div><dt>WP-Cron</dt><dd><?php echo esc_html( $cron_disabled ? '内部実行は停止中。外部Cronの設定・実行をサーバー管理者へ確認してください。' : '内部実行は有効。アクセス状況により遅れる場合があります。' ); ?></dd></div>
				<?php if ( 'updates' === $slug ) : ?>
					<?php
					$global_disabled = defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED;
					$plugin_allowed = (bool) apply_filters( 'auto_update_plugin', false, (object) array( 'plugin' => SOSC_PLUGIN_BASENAME ) );
					?>
					<div><dt>自動更新</dt><dd><?php echo esc_html( $global_disabled ? 'WordPress全体で停止中' : ( $plugin_allowed ? 'このプラグインは対象。定期処理と更新権限が必要です。' : 'このプラグインは対象外' ) ); ?></dd></div>
				<?php else : ?>
					<div><dt>次回バックアップ</dt><dd><?php echo esc_html( $next_text ); ?></dd></div>
				<?php endif; ?>
			</dl>
		</section>
		<?php
	}

	private static function flow_image( $labels ) {
		$description = implode( '、', $labels ) . 'の順に進む操作の流れ';
		?>
		<svg class="sosc-manual-flow" viewBox="0 0 720 150" role="img" aria-label="<?php echo esc_attr( $description ); ?>" xmlns="http://www.w3.org/2000/svg">
			<rect x="5" y="20" width="205" height="105" rx="12" fill="#f2f6f8" stroke="#9fb9c7" />
			<rect x="257" y="20" width="205" height="105" rx="12" fill="#f2f6f8" stroke="#9fb9c7" />
			<rect x="509" y="20" width="205" height="105" rx="12" fill="#f2f6f8" stroke="#9fb9c7" />
			<path d="M220 73h27m-8-8 8 8-8 8M472 73h27m-8-8 8 8-8 8" fill="none" stroke="#477c97" stroke-width="3" />
			<text x="107" y="82" text-anchor="middle">1. <?php echo esc_html( $labels[0] ); ?></text><text x="359" y="82" text-anchor="middle">2. <?php echo esc_html( $labels[1] ); ?></text><text x="611" y="82" text-anchor="middle">3. <?php echo esc_html( $labels[2] ); ?></text>
		</svg>
		<svg class="sosc-manual-flow-mobile" viewBox="0 0 300 320" role="img" aria-label="<?php echo esc_attr( $description ); ?>" xmlns="http://www.w3.org/2000/svg">
			<rect x="10" y="8" width="280" height="78" rx="10" fill="#f2f6f8" stroke="#9fb9c7" />
			<rect x="10" y="121" width="280" height="78" rx="10" fill="#f2f6f8" stroke="#9fb9c7" />
			<rect x="10" y="234" width="280" height="78" rx="10" fill="#f2f6f8" stroke="#9fb9c7" />
			<path d="M150 92v23m-8-8 8 8 8-8M150 205v23m-8-8 8 8 8-8" fill="none" stroke="#477c97" stroke-width="3" />
			<text x="150" y="57" text-anchor="middle">1. <?php echo esc_html( $labels[0] ); ?></text><text x="150" y="170" text-anchor="middle">2. <?php echo esc_html( $labels[1] ); ?></text><text x="150" y="283" text-anchor="middle">3. <?php echo esc_html( $labels[2] ); ?></text>
		</svg>
		<?php
	}
}
