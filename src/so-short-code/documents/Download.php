<?php
/**
 * Read-only customer access to Welcart documents.
 *
 * @package ShonanOnlineShortCode
 */

namespace ShonanOnline\ShortCode\Documents;

defined( 'ABSPATH' ) || exit;

final class Download {

	public static function boot() {
		add_action( 'init', array( __CLASS__, 'register_shortcode' ), 20 );
		add_action( 'admin_post_sosc_wc_documents', array( __CLASS__, 'download' ) );
		add_action( 'admin_post_nopriv_sosc_wc_documents', array( __CLASS__, 'download' ) );
		add_action( 'delete_expired_transients', array( __CLASS__, 'cleanup' ) );
	}

	public static function register_shortcode() {
		if ( self::available() ) {
			add_shortcode( 'sosc_wc_documents', array( __CLASS__, 'form' ) );
		}
	}

	/** Remove only our database counters, including when an external object cache is used. */
	public static function cleanup() {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE counter, expiry FROM {$wpdb->options} counter INNER JOIN {$wpdb->options} expiry ON counter.option_name = REPLACE(expiry.option_name, '_transient_timeout_', '_transient_') WHERE expiry.option_name LIKE %s AND CAST(expiry.option_value AS UNSIGNED) < %d",
				$wpdb->esc_like( '_transient_timeout_sosc_doc_' ) . '%',
				time()
			)
		);
	}

	public static function available() {
		return defined( 'USCES_PLUGIN_DIR' ) && isset( $GLOBALS['usces'] ) && function_exists( 'usces_get_deco_order_id' ) && function_exists( 'usces_is_reduced_taxrate' );
	}

	public static function form() {
		if ( ! self::available() ) {
			return '<p>' . esc_html__( '現在、請求書・領収書のダウンロードは利用できません。', 'so-short-code' ) . '</p>';
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Cache plugin interoperability constant.
		}
		if ( ! headers_sent() ) {
			nocache_headers();
		}
		ob_start();
		?>
		<div class="sosc-bs-scope">
		<form class="sosc-wc-documents p-4 border rounded-3" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<p class="mb-3"><?php esc_html_e( 'ご注文時のメールアドレスと、注文確認メールに記載された注文番号を入力してください。領収書は入金済みの注文のみ取得できます。', 'so-short-code' ); ?></p>
			<div class="mb-3"><label class="form-label d-block"><?php esc_html_e( 'メールアドレス', 'so-short-code' ); ?><input class="form-control mt-2" type="email" name="sosc_email" maxlength="100" autocomplete="email" required></label></div>
			<div class="mb-3"><label class="form-label d-block"><?php esc_html_e( '注文番号', 'so-short-code' ); ?><input class="form-control mt-2" type="text" name="sosc_order_number" maxlength="100" autocomplete="off" required></label></div>
			<input type="hidden" name="action" value="sosc_wc_documents">
			<?php wp_nonce_field( 'sosc_wc_documents', 'sosc_nonce', false ); ?>
			<div class="d-grid gap-2 d-sm-flex">
				<button class="btn btn-primary" type="submit" name="sosc_document" value="bill"><?php esc_html_e( '請求書をダウンロード', 'so-short-code' ); ?></button>
				<button class="btn btn-outline-primary" type="submit" name="sosc_document" value="receipt"><?php esc_html_e( '領収書をダウンロード', 'so-short-code' ); ?></button>
			</div>
		</form>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function input( $key ) {
		if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) {
			return '';
		}
		$value = trim( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw credentials are compared with their sanitized form below and rejected if different.
		// Credentials must match as entered; stripping markup must not authorize them.
		return strlen( $value ) <= 100 && sanitize_text_field( $value ) === $value ? $value : '';
	}

	/**
	 * Look up the customer-visible number, never authorize an internal ID alone.
	 * Only read queries are issued against Welcart tables.
	 */
	public static function find_order( $email, $number ) {
		global $wpdb;
		if ( ! is_string( $email ) || ! is_string( $number ) || ! is_email( $email ) || strlen( $email ) > 100 || '' === $number || strlen( $number ) > 100 || ! self::available() ) {
			return null;
		}
		// Narrow stored numbers in SQL; only legacy orders without a number need fallback.
		$orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT o.ID, o.order_email, o.order_status FROM {$wpdb->prefix}usces_order o LEFT JOIN {$wpdb->prefix}usces_order_meta m ON m.order_id = o.ID AND m.meta_key = 'dec_order_id' WHERE o.order_email = %s AND (m.meta_value IS NULL OR m.meta_value IN ('', '0', %s))",
				$email,
				$number
			),
			ARRAY_A
		);
		$match  = null;
		foreach ( (array) $orders as $order ) {
			if ( ! hash_equals( strtolower( $order['order_email'] ), strtolower( $email ) ) || ! hash_equals( (string) usces_get_deco_order_id( $order['ID'] ), $number ) ) {
				continue;
			}
			if ( null !== $match ) {
				return null;
			}
			$match = $order;
		}
		return $match;
	}

	public static function allowed( $order, $type ) {
		if ( ! is_array( $order ) || ! isset( $order['order_status'] ) || ! in_array( $type, array( 'bill', 'receipt' ), true ) ) {
			return false;
		}
		$status = array_map( 'trim', explode( ',', $order['order_status'] ) );
		if ( array_intersect( array( 'cancel', 'estimate' ), $status ) ) {
			return false;
		}
		// Settlement/point helpers also accept COD and unpaid orders; do not use them.
		return 'bill' === $type || ( in_array( 'receipted', $status, true ) && ! array_intersect( array( 'noreceipt', 'pending' ), $status ) );
	}

	/**
	 * Atomic fixed-window counters. No raw email or IP is persisted.
	 */
	private static function consume_attempt( $identity, $limit ) {
		global $wpdb;
		$window = (int) floor( time() / ( 15 * MINUTE_IN_SECONDS ) );
		$key    = '_transient_sosc_doc_' . hash_hmac( 'sha256', $identity . '|' . $window, wp_salt( 'nonce' ) );
		// These transients deliberately use SQL so concurrent requests cannot lose increments.
		$timeout = str_replace( '_transient_', '_transient_timeout_', $key );
		add_option( $timeout, ( $window + 2 ) * 15 * MINUTE_IN_SECONDS, '', false );
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'no') ON DUPLICATE KEY UPDATE option_value = CAST(option_value AS UNSIGNED) + 1",
				$key
			)
		);
		if ( false === $result ) {
			return false;
		}
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
		return null !== $count && (int) $count <= $limit;
	}

	private static function fail( $code = 403 ) {
		wp_die(
			esc_html__( 'ダウンロードできませんでした。入力内容と入金状況をご確認ください。繰り返し操作した場合は、15分ほど待ってから再度お試しください。', 'so-short-code' ),
			esc_html__( '帳票ダウンロード', 'so-short-code' ),
			array(
				'response'  => (int) $code,
				'back_link' => true,
			)
		);
	}

	public static function download() {
		nocache_headers();
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
		header( 'Referrer-Policy: no-referrer' );
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || ! wp_verify_nonce( self::input( 'sosc_nonce' ), 'sosc_wc_documents' ) || ! self::available() ) {
			self::fail();
			return;
		}
		$email  = self::input( 'sosc_email' );
		$number = self::input( 'sosc_order_number' );
		$type   = self::input( 'sosc_document' );
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		if ( ! self::consume_attempt( 'ip:' . $ip, 20 ) || ! self::consume_attempt( 'email:' . strtolower( $email ), 10 ) ) {
			self::fail( 429 );
			return;
		}
		$order = self::find_order( $email, $number );
		if ( ! self::allowed( $order, $type ) ) {
			self::fail();
			return;
		}
		self::output( (int) $order['ID'], $type );
	}

	private static function output( $order_id, $type ) {
		global $usces;
		$reduced = isset( $usces->options['tax_display'] ) && 'activate' === $usces->options['tax_display'] && usces_is_reduced_taxrate( $order_id );
		$path    = USCES_PLUGIN_DIR . '/includes/' . ( $reduced ? 'order_print_ex.php' : 'order_print.php' );
		if ( ! is_readable( $path ) || function_exists( 'usces_pdf_out' ) || headers_sent() ) {
			self::fail( 503 );
			return;
		}
		// The stock renderer consumes only these two request fields and terminates.
		// Do not set order_action, change the current user, or grant Welcart capabilities.
		$_REQUEST = array(
			'order_id' => $order_id,
			'type'     => $type,
		);
		ob_start(
			static function ( $buffer ) use ( $order_id, $type ) {
				if ( 0 === strpos( $buffer, '%PDF-' ) && false !== strpos( $buffer, '%%EOF' ) ) {
					header( 'Content-Disposition: attachment; filename="' . $type . '-' . $order_id . '.pdf"' );
					header( 'Cache-Control: private, no-store, max-age=0' );
					header( 'X-Content-Type-Options: nosniff' );
					return $buffer;
				}
				// Never expose renderer diagnostics or partial documents to a customer.
				status_header( 503 );
				header_remove( 'Content-Length' );
				header_remove( 'Content-Disposition' );
				header( 'Content-Type: text/plain; charset=UTF-8' );
				header( 'Cache-Control: private, no-store, max-age=0' );
				return __( '帳票を生成できませんでした。店舗へお問い合わせください。', 'so-short-code' );
			}
		);
		// Welcart closes one buffer before streaming the PDF; preserve our outer buffer.
		ob_start();
		require $path;
	}
}
