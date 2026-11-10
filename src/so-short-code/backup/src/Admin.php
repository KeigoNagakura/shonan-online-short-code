<?php
/**
 * Backup administration actions and view helpers.
 *
 * @package ShonanOnlineShortCode
 */

namespace ShonanOnline\ShortCode\Backup;

final class Admin {
	public static function run_now() {
		self::authorize( 'sosc_backup_run' );
		$result = Manager::create( 'manual' );
		self::redirect_with_result( $result );
	}

	public static function download() {
		self::authorize( 'sosc_backup_download' );
		$file_name = isset( $_GET['file'] ) && is_string( $_GET['file'] ) ? sanitize_file_name( wp_unslash( $_GET['file'] ) ) : '';
		if ( ! Manager::is_backup_file_name( $file_name ) ) {
			wp_die( esc_html__( 'バックアップファイル名が不正です。', 'so-short-code' ), '', array( 'response' => 400 ) );
		}

		$directory = Manager::storage_directory();
		$file      = realpath( $directory . '/' . $file_name );
		$base      = realpath( $directory );
		if ( false === $file || false === $base || 0 !== strpos( $file, $base . DIRECTORY_SEPARATOR ) || ! is_file( $file ) ) {
			wp_die( esc_html__( 'バックアップファイルが見つかりません。', 'so-short-code' ) );
		}

		$download_name = substr( $file_name, 0, -4 );
		$payload_size  = (int) filesize( $file ) - strlen( Manager::ARCHIVE_GUARD );
		if ( $payload_size < 1 ) {
			wp_die( esc_html__( 'バックアップファイルが破損しています。', 'so-short-code' ) );
		}
		$handle = fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			wp_die( esc_html__( 'バックアップファイルを開けません。', 'so-short-code' ) );
		}
		$guard = fread( $handle, strlen( Manager::ARCHIVE_GUARD ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		if ( Manager::ARCHIVE_GUARD !== $guard ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			wp_die( esc_html__( 'バックアップファイルの保護情報が不正です。', 'so-short-code' ) );
		}
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $download_name ) . '"' );
		header( 'Content-Length: ' . (string) $payload_size );
		while ( ! feof( $handle ) ) {
			echo fread( $handle, 1024 * 1024 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.WP.AlternativeFunctions.file_system_operations_fread
			flush();
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	public static function render_status() {
		$password = Manager::encryption_password();
		if ( is_wp_error( $password ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $password->get_error_message() ) . '</p></div>';
		}

		$notice = get_transient( 'sosc_backup_admin_notice_' . get_current_user_id() );
		if ( is_array( $notice ) ) {
			delete_transient( 'sosc_backup_admin_notice_' . get_current_user_id() );
			echo '<div class="notice notice-' . esc_attr( $notice['type'] ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
		}
		$status         = get_option( 'sosc_backup_last_status', array() );
		$encryption     = get_option( 'sosc_backup_last_encryption', '' );
		$prune_warning  = get_option( 'sosc_backup_prune_warning', '' );
		$next           = wp_next_scheduled( Plugin::CRON_HOOK );
		$files          = Manager::list_backups();
		$schedule_error = get_option( 'sosc_backup_schedule_error', '' );
		?>
		<div class="mt-4">
			<h3><?php esc_html_e( '月次バックアップ', 'so-short-code' ); ?></h3>
			<p><strong><?php esc_html_e( '保存先:', 'so-short-code' ); ?></strong> <code><?php echo esc_html( Manager::storage_directory() ); ?></code></p>
			<p><?php esc_html_e( 'パスワードを登録するとAES-256暗号化を必須にします。暗号化を利用できない場合は平文ZIPを作らず中止します。パスワードがない場合は暗号化なしの通常ZIPを作成します。', 'so-short-code' ); ?></p>
			<p><strong><?php echo esc_html( '直近のバックアップ: ' . $encryption ); ?></strong></p>
			<?php if ( $schedule_error ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( '次回バックアップを予約できませんでした: ' . $schedule_error ); ?></p></div>
			<?php endif; ?>
			<?php if ( $prune_warning ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html( $prune_warning ); ?></p></div>
			<?php endif; ?>
			<p>
				<?php
				echo esc_html( $next ? '次回実行予定: ' . wp_date( 'Y-m-d H:i:s', $next ) : '次回実行予定: なし' );
				?>
			</p>
			<?php if ( is_array( $status ) && ! empty( $status['finished_at'] ) ) : ?>
				<p><?php echo esc_html( '最終実行: ' . $status['finished_at'] . ' / ' . ( ! empty( $status['success'] ) ? '成功' : '失敗' ) . ' / ' . ( $status['message'] ?? '' ) ); ?></p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-sosc-busy-label="バックアップ作成中…">
				<input type="hidden" name="action" value="sosc_backup_run">
				<?php wp_nonce_field( 'sosc_backup_run' ); ?>
				<?php submit_button( __( '今すぐバックアップ', 'so-short-code' ), 'secondary', 'submit', false ); ?>
			</form>
			<table class="widefat striped mt-3">
				<thead><tr><th scope="col"><?php esc_html_e( '作成日時', 'so-short-code' ); ?></th><th scope="col"><?php esc_html_e( 'サイズ', 'so-short-code' ); ?></th><th scope="col"><?php esc_html_e( '操作', 'so-short-code' ); ?></th></tr></thead>
				<tbody>
				<?php if ( empty( $files ) ) : ?>
					<tr><td colspan="3"><?php esc_html_e( 'バックアップはありません。', 'so-short-code' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $files as $file ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $file['mtime'] ) ); ?></td>
							<td><?php echo esc_html( size_format( $file['size'], 2 ) ); ?></td>
							<td><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sosc_backup_download&file=' . rawurlencode( $file['name'] ) ), 'sosc_backup_download' ) ); ?>"><?php esc_html_e( 'ダウンロード', 'so-short-code' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function authorize( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '権限がありません。', 'so-short-code' ) );
		}
		check_admin_referer( $nonce_action );
	}

	private static function redirect_with_result( $result ) {
		$type    = is_wp_error( $result ) ? 'error' : 'success';
		$message = is_wp_error( $result ) ? $result->get_error_message() : __( 'バックアップを作成しました。', 'so-short-code' );
		set_transient(
			'sosc_backup_admin_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);
		wp_safe_redirect( admin_url( 'admin.php?page=sosc_menu&tab=backup' ) );
		exit;
	}
}
