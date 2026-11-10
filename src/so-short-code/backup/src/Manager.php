<?php
/**
 * WordPress file and database backup service.
 *
 * @package ShonanOnlineShortCode
 */

namespace ShonanOnline\ShortCode\Backup;

final class Manager {
	const ARCHIVE_GUARD                = "<?php http_response_code(404); exit; __halt_compiler(); ?>\n";
	const LOCK_KEY                     = 'sosc_backup_creation_lock';
	const RETAIN_COUNT                 = 6;
	const SQL_EXPANSION                = 2.2;
	const ARCHIVE_OVERHEAD             = 1.1;
	const MINIMUM_RESERVE              = 104857600;
	const PASSWORD_CIPHER              = 'aes-256-gcm';
	const PASSWORD_PREFIX              = 'v1:';
	private static $maintenance_handle = null;

	public static function create( $source ) {
		$layout = self::validate_layout();
		if ( is_wp_error( $layout ) ) {
			self::save_status( $layout );
			return $layout;
		}
		$password = self::encryption_password();
		if ( is_wp_error( $password ) ) {
			self::save_status( $password );
			return $password;
		}
		$lock_time = (int) get_option( self::LOCK_KEY, 0 );
		if ( $lock_time && $lock_time < time() - 6 * HOUR_IN_SECONDS ) {
			delete_option( self::LOCK_KEY );
		}
		if ( ! add_option( self::LOCK_KEY, time(), '', false ) ) {
			return new \WP_Error( 'backup_locked', __( '別のバックアップ処理が実行中です。', 'so-short-code' ) );
		}
		@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		$maintenance_created = self::begin_maintenance();
		if ( is_wp_error( $maintenance_created ) ) {
			delete_option( self::LOCK_KEY );
			self::save_status( $maintenance_created );
			return $maintenance_created;
		}
		try {
			$result = self::create_archive( sanitize_key( $source ) );
		} catch ( \Throwable $error ) {
			$result = new \WP_Error( 'backup_unexpected_error', __( '予期しないエラーによりバックアップを作成できませんでした。', 'so-short-code' ) );
		} finally {
			if ( $maintenance_created ) {
				self::end_maintenance();
			}
			delete_option( self::LOCK_KEY );
		}
		self::save_status( $result );

		return $result;
	}

	private static function begin_maintenance() {
		$file = ABSPATH . '.maintenance';
		if ( file_exists( $file ) ) {
			$contents = file_get_contents( $file, false, null, 0, 256 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( is_string( $contents ) && false !== strpos( $contents, 'Created temporarily by Shonan Online monthly backup.' ) && filemtime( $file ) < time() - 15 * MINUTE_IN_SECONDS ) {
				wp_delete_file( $file );
			} else {
				return new \WP_Error( 'backup_maintenance_active', __( 'WordPressまたは別の処理がメンテナンス中のため、バックアップを開始できません。', 'so-short-code' ) );
			}
		}
		// The live process owns a file lock even while ZipArchive::close() blocks.
		// WordPress evaluates this file before loading plugins. A lost process lock
		// expires maintenance immediately; a live lock keeps it active beyond 10 minutes.
		$contents = <<<'PHP'
<?php
// Created temporarily by Shonan Online monthly backup.
$upgrading = 0;
$sosc_maintenance_probe = fopen( __FILE__, 'r' );
if ( $sosc_maintenance_probe ) {
	if ( ! flock( $sosc_maintenance_probe, LOCK_SH | LOCK_NB ) ) {
		$upgrading = time();
	}
	fclose( $sosc_maintenance_probe );
}
unset( $sosc_maintenance_probe );
PHP;
		$handle   = fopen( $file, 'x+' );
		if ( false === $handle ) {
			return new \WP_Error( 'backup_maintenance_failed', __( 'バックアップ中のファイル更新を停止するメンテナンス状態を開始できません。', 'so-short-code' ) );
		}
		if ( ! flock( $handle, LOCK_EX | LOCK_NB ) || strlen( $contents ) !== fwrite( $handle, $contents ) || ! fflush( $handle ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_flock,WordPress.WP.AlternativeFunctions.file_system_operations_fflush -- Process-owned maintenance lock.
			fclose( $handle );
			wp_delete_file( $file );
			return new \WP_Error( 'backup_maintenance_failed', __( 'メンテナンス状態を保持できません。', 'so-short-code' ) );
		}
		self::$maintenance_handle = $handle;
		return true;
	}

	private static function end_maintenance() {
		if ( is_resource( self::$maintenance_handle ) ) {
			fclose( self::$maintenance_handle );
			self::$maintenance_handle = null;
		}
		$file = ABSPATH . '.maintenance';
		if ( ! is_file( $file ) ) {
			return;
		}
		$contents = file_get_contents( $file, false, null, 0, 256 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( is_string( $contents ) && false !== strpos( $contents, 'Created temporarily by Shonan Online monthly backup.' ) ) {
			wp_delete_file( $file );
		}
	}

	private static function create_archive( $source ) {
		if ( ! class_exists( '\\ZipArchive' ) ) {
			return new \WP_Error( 'zip_unavailable', __( 'PHPのZipArchive拡張が利用できません。', 'so-short-code' ) );
		}

		$directory = self::storage_directory();
		$prepared  = self::prepare_storage( $directory );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}
		self::cleanup_stale_work_files( $directory );

		$space = self::estimated_space_details();
		if ( is_wp_error( $space ) ) {
			return $space;
		}
		$estimated = $space['peak_additional'];
		$free      = disk_free_space( $directory );
		if ( false === $free || $free < $estimated ) {
			/* translators: 1: estimated required disk space, 2: available disk space. */
			return new \WP_Error( 'insufficient_space', sprintf( __( '空き容量が不足しています。必要容量の目安: %1$s、空き容量: %2$s', 'so-short-code' ), size_format( $estimated, 2 ), false === $free ? __( '取得不能', 'so-short-code' ) : size_format( $free, 2 ) ) );
		}

		$now       = current_datetime();
		$token     = substr( hash( 'sha256', wp_generate_password( 32, true, true ) . microtime( true ) ), 0, 8 );
		$file_name = 'sosc-' . $now->format( 'Ymd-His' ) . '.zip';
		$partial   = $directory . '/.' . $file_name . '.partial';
		$protected = $directory . '/.' . $file_name . '.protected.php';
		$final     = $directory . '/' . $file_name . '.php';
		$sql_file  = $directory . '/.database-' . $token . '.sql';

		$sql_result = DatabaseDumper::dump( $sql_file );
		if ( is_wp_error( $sql_result ) ) {
			self::remove_file( $sql_file );
			return $sql_result;
		}
		$sql_size       = (int) filesize( $sql_file );
		$estimated_zip  = (int) ceil( ( $space['file_bytes'] + $sql_size ) * self::ARCHIVE_OVERHEAD );
		$remaining_peak = ( 2 * $estimated_zip ) + self::MINIMUM_RESERVE;
		$free           = disk_free_space( $directory );
		if ( false === $free || $free < $remaining_peak ) {
			self::remove_file( $sql_file );
			/* translators: 1: estimated required disk space, 2: available disk space. */
			return new \WP_Error( 'insufficient_space_after_dump', sprintf( __( 'DBダンプ作成後の空き容量が不足しています。ZIP完成までの必要容量の目安: %1$s、空き容量: %2$s', 'so-short-code' ), size_format( $remaining_peak, 2 ), false === $free ? __( '取得不能', 'so-short-code' ) : size_format( $free, 2 ) ) );
		}

		$password = self::encryption_password();
		if ( is_wp_error( $password ) ) {
			self::remove_file( $sql_file );
			return $password;
		}
		$encrypted = '' !== $password && self::aes_encryption_available( $directory, $password );
		if ( '' !== $password && ! $encrypted ) {
			self::remove_file( $sql_file );
			return new \WP_Error( 'backup_encryption_required', __( '暗号化パスワードが設定されていますが、AES-256暗号化を利用できないためバックアップを中止しました。', 'so-short-code' ) );
		}
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $partial, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			self::remove_file( $sql_file );
			return new \WP_Error( 'zip_open_failed', __( 'ZIPファイルを作成できません。', 'so-short-code' ) );
		}

		if ( ! $zip->addFile( $sql_file, 'database.sql' ) ) {
			$zip->close();
			self::remove_file( $sql_file );
			self::remove_file( $partial );
			return new \WP_Error( 'database_zip_add_failed', __( 'DBダンプをZIPへ追加できません。', 'so-short-code' ) );
		}
		if ( $encrypted && ! self::encrypt_entry( $zip, 'database.sql', $password ) ) {
			$zip->close();
			self::remove_file( $sql_file );
			self::remove_file( $partial );
			return new \WP_Error( 'database_zip_encrypt_failed', __( 'DBダンプをAES-256で暗号化できません。', 'so-short-code' ) );
		}
		$info = array(
			'created_at'        => $now->format( DATE_ATOM ),
			'source'            => $source,
			'wordpress_version' => get_bloginfo( 'version' ),
			'php_version'       => PHP_VERSION,
			'plugin_version'    => SOSC_VERSION,
			'home_url'          => home_url( '/' ),
			'encryption'        => $encrypted ? 'AES-256' : 'none',
		);
		if ( ! $zip->addFromString( 'backup-info.json', (string) wp_json_encode( $info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ) ) {
			$zip->close();
			self::remove_file( $sql_file );
			self::remove_file( $partial );
			return new \WP_Error( 'backup_info_zip_add_failed', __( 'バックアップ情報をZIPへ追加できません。', 'so-short-code' ) );
		}
		if ( $encrypted && ! self::encrypt_entry( $zip, 'backup-info.json', $password ) ) {
			$zip->close();
			self::remove_file( $sql_file );
			self::remove_file( $partial );
			return new \WP_Error( 'backup_info_zip_encrypt_failed', __( 'バックアップ情報をAES-256で暗号化できません。', 'so-short-code' ) );
		}
		$restore_instructions  = "湘南オンラインプラグイン バックアップ復元手順\n\n"
			. "Windowsで展開する場合は、パス長制限を避けるためC:\\soscなど浅い場所へZIPを置いて展開します。\n"
			. "1. 復元先サイトをメンテナンス状態にし、現在のファイルとDBを別途退避します。\n"
			. "2. ZIP内の wp-admin、wp-content、wp-includesなどを、WordPress設置ディレクトリへ配置します。\n"
			. "3. 復元先DBを空にしてから、database.sqlをインポートします。\n"
			. "4. DB接続先がバックアップ元と異なる場合は、wp-config.phpの接続情報を復元先に合わせます。\n"
			. "5. ファイルの所有者・権限、サイトURL、ログイン、表示、プラグイン、PHPログを確認します。\n\n"
			. "注意:\n"
			. "- ZIPの直下がWordPress設置ディレクトリに対応しています。\n"
			. "- database.sqlにはバックアップ対象テーブルのDROP TABLEが含まれます。実行前に対象DBを必ず確認してください。\n"
			. "- 復元先DBに余分なテーブルを残さないため、空のDBへのインポートを基本とします。\n"
			. "- ビュー、ルーチン、トリガー、イベントを復元する場合は、それらを作成できるDB権限が必要です。\n"
			. "- 復元作業はこのプラグインから自動実行されません。サーバー管理者が手動で行います。\n";
		$restore_instructions .= "- 暗号化パスワードはDBダンプに含めません。暗号化を利用していた場合、復元後は管理画面でパスワードを再登録するまでバックアップを停止します。\n";
		if ( ! $zip->addFromString( 'RESTORE-INSTRUCTIONS.txt', $restore_instructions ) ) {
			$zip->close();
			self::remove_file( $sql_file );
			self::remove_file( $partial );
			return new \WP_Error( 'restore_instructions_zip_add_failed', __( '復元手順をZIPへ追加できません。', 'so-short-code' ) );
		}
		if ( $encrypted && ! self::encrypt_entry( $zip, 'RESTORE-INSTRUCTIONS.txt', $password ) ) {
			$zip->close();
			self::remove_file( $sql_file );
			self::remove_file( $partial );
			return new \WP_Error( 'restore_instructions_zip_encrypt_failed', __( '復元手順をAES-256で暗号化できません。', 'so-short-code' ) );
		}

		$file_result = self::add_wordpress_files( $zip, $directory, $encrypted, $password );
		$closed      = $zip->close();
		self::remove_file( $sql_file );
		if ( is_wp_error( $file_result ) || ! $closed ) {
			self::remove_file( $partial );
			return is_wp_error( $file_result ) ? $file_result : new \WP_Error( 'zip_close_failed', __( 'ZIPファイルを完成できません。', 'so-short-code' ) );
		}
		if ( ! self::archive_is_valid( $partial, $encrypted, $password ) ) {
			self::remove_file( $partial );
			return new \WP_Error( 'backup_validation_failed', __( '完成したバックアップZIPの整合性を確認できませんでした。', 'so-short-code' ) );
		}
		$protection_result = self::protect_archive( $partial, $protected );
		if ( is_wp_error( $protection_result ) ) {
			self::remove_file( $partial );
			self::remove_file( $protected );
			return $protection_result;
		}
		self::remove_file( $partial );
		if ( ! rename( $protected, $final ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			self::remove_file( $protected );
			return new \WP_Error( 'backup_finalize_failed', __( 'バックアップファイルを確定できません。', 'so-short-code' ) );
		}
		update_option( 'sosc_backup_last_encryption', $encrypted ? 'AES-256' : 'none', false );

		$pruned = self::prune();
		if ( is_wp_error( $pruned ) ) {
			update_option( 'sosc_backup_prune_warning', $pruned->get_error_message(), false );
		} else {
			delete_option( 'sosc_backup_prune_warning' );
		}
		return $final;
	}

	public static function storage_directory() {
		$root  = self::storage_root();
		$token = get_option( 'sosc_backup_storage_token', '' );
		if ( ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
			$token = substr( hash( 'sha256', wp_generate_password( 64, true, true ) . microtime( true ) ), 0, 32 );
			update_option( 'sosc_backup_storage_token', $token, false );
		}
		return $root . '/private-' . $token;
	}

	public static function storage_root() {
		return untrailingslashit( wp_normalize_path( self::default_storage_root() ) );
	}

	public static function encryption_password() {
		$stored = (string) sosc_get_value( 'monthly_backup_password', '' );
		if ( '' === $stored && 'aes256' !== (string) sosc_get_value( 'monthly_backup_encryption', 'none' ) ) {
			return '';
		}
		$error = new \WP_Error( 'backup_password_unreadable', __( '暗号化パスワードを読み取れません。バックアップを停止しました。「その他」でパスワードを再登録するか、暗号化を解除してください。', 'so-short-code' ) );
		if ( 0 !== strpos( $stored, self::PASSWORD_PREFIX ) || ! function_exists( 'openssl_decrypt' ) ) {
			return $error;
		}
		$payload   = base64_decode( substr( $stored, strlen( self::PASSWORD_PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes a binary authenticated-encryption envelope, not executable code.
		$iv_length = openssl_cipher_iv_length( self::PASSWORD_CIPHER );
		if ( false === $payload || false === $iv_length || strlen( $payload ) <= $iv_length + 16 ) {
			return $error;
		}
		$iv         = substr( $payload, 0, $iv_length );
		$tag        = substr( $payload, $iv_length, 16 );
		$ciphertext = substr( $payload, $iv_length + 16 );
		$password   = openssl_decrypt( $ciphertext, self::PASSWORD_CIPHER, self::password_storage_key(), OPENSSL_RAW_DATA, $iv, $tag );

		return is_string( $password ) && '' !== $password ? $password : $error;
	}

	public static function has_encryption_password() {
		return '' !== (string) sosc_get_value( 'monthly_backup_password', '' ) || 'aes256' === (string) sosc_get_value( 'monthly_backup_encryption', 'none' );
	}

	public static function set_encryption_password( $password ) {

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return new \WP_Error( 'backup_password_encryption_unavailable', __( '暗号化パスワードを安全に保存できないため、AES-256を設定できません。', 'so-short-code' ) );
		}
		$iv_length = openssl_cipher_iv_length( self::PASSWORD_CIPHER );
		if ( false === $iv_length || $iv_length < 1 ) {
			return new \WP_Error( 'backup_password_cipher_unavailable', __( '暗号化パスワードの保存方式を利用できません。', 'so-short-code' ) );
		}
		try {
			$iv = random_bytes( $iv_length );
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'backup_password_random_failed', __( '暗号化パスワードの保存に必要な乱数を生成できません。', 'so-short-code' ) );
		}
		$tag        = '';
		$ciphertext = openssl_encrypt( $password, self::PASSWORD_CIPHER, self::password_storage_key(), OPENSSL_RAW_DATA, $iv, $tag );
		if ( false === $ciphertext || 16 !== strlen( $tag ) ) {
			return new \WP_Error( 'backup_password_encrypt_failed', __( '暗号化パスワードを安全に保存できません。', 'so-short-code' ) );
		}

		$result = sosc_set_value( 'monthly_backup_password', self::PASSWORD_PREFIX . base64_encode( $iv . $tag . $ciphertext ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encodes a binary authenticated-encryption envelope for text storage.
		return is_wp_error( $result ) ? $result : sosc_set_value( 'monthly_backup_encryption', 'aes256' );
	}

	public static function clear_encryption_password() {
		$result = sosc_set_value( 'monthly_backup_password', '' );
		return is_wp_error( $result ) ? $result : sosc_set_value( 'monthly_backup_encryption', 'none' );
	}

	public static function encryption_available() {

		$temporary_directory = untrailingslashit( wp_normalize_path( get_temp_dir() ) );
		if ( '' === $temporary_directory || ! is_dir( $temporary_directory ) || ! wp_is_writable( $temporary_directory ) ) {
			return false;
		}

		return self::aes_encryption_available( $temporary_directory, wp_generate_password( 32, true, true ) );
	}
	private static function password_storage_key() {
		return hash( 'sha256', AUTH_KEY . '|' . SECURE_AUTH_KEY, true );
	}

	private static function default_storage_root() {
		return WP_CONTENT_DIR . '/sosc-backups';
	}

	public static function list_backups() {
		$directory = self::storage_directory();
		if ( ! is_dir( $directory ) ) {
			return array();
		}
		$paths = glob( $directory . '/*.zip.php' );
		if ( false === $paths ) {
			return array();
		}
		$files = array();
		foreach ( $paths as $path ) {
			if ( is_file( $path ) && self::is_backup_file_name( basename( $path ) ) ) {
				$files[] = array(
					'name'  => basename( $path ),
					'size'  => (int) filesize( $path ),
					'mtime' => (int) filemtime( $path ),
				);
			}
		}
		usort(
			$files,
			static function ( $left, $right ) {
				return $right['mtime'] <=> $left['mtime'];
			}
		);
		return $files;
	}

	public static function is_backup_file_name( $file_name ) {
		return 1 === preg_match( '/^(?:sosc-[0-9]{8}-[0-9]{6}|shonan-online-backup-[0-9]{8}_[0-9]{6}-[a-f0-9]{8})\.zip\.php$/', (string) $file_name );
	}

	private static function prepare_storage( $directory ) {
		if ( ! wp_mkdir_p( $directory ) ) {
			return new \WP_Error( 'backup_directory_failed', __( 'バックアップ保存ディレクトリを作成できません。', 'so-short-code' ) );
		}
		if ( ! wp_is_writable( $directory ) ) {
			return new \WP_Error( 'backup_directory_not_writable', __( 'バックアップ保存先へ書き込めません。ディレクトリの所有者と権限を確認してください。', 'so-short-code' ) );
		}
		$root        = dirname( $directory );
		$protections = array(
			$root . '/index.php'      => "<?php\n// Silence is golden.\n",
			$root . '/.htaccess'      => "Require all denied\nDeny from all\n",
			$root . '/web.config'     => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\"/><add accessType=\"Deny\" users=\"*\"/></authorization></security></system.webServer></configuration>\n",
			$directory . '/index.php' => "<?php\n// Silence is golden.\n",
		);
		foreach ( $protections as $path => $contents ) {
			if ( ! file_exists( $path ) && false === file_put_contents( $path, $contents, LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				return new \WP_Error( 'backup_protection_failed', __( 'バックアップ保存先を保護できません。', 'so-short-code' ) );
			}
		}
		return true;
	}

	private static function protect_archive( $source, $target ) {
		$input = fopen( $source, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $input ) {
			return new \WP_Error( 'backup_protection_source_failed', __( '完成したZIPを保護処理のために開けません。', 'so-short-code' ) );
		}
		$output = fopen( $target, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $output ) {
			fclose( $input ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new \WP_Error( 'backup_protection_target_failed', __( '保護されたバックアップファイルを作成できません。', 'so-short-code' ) );
		}
		$source_size = (int) filesize( $source );
		$guard       = self::ARCHIVE_GUARD;
		$written     = fwrite( $output, $guard ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		$copied      = false !== $written && strlen( $guard ) === $written ? stream_copy_to_stream( $input, $output ) : false;
		$flushed     = fflush( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fflush
		fclose( $input ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		if ( false === $copied || $source_size !== $copied || ! $flushed || (int) filesize( $target ) !== strlen( $guard ) + $source_size ) {
			return new \WP_Error( 'backup_protection_copy_failed', __( 'ZIPへ直接アクセス防止ガードを付与できません。', 'so-short-code' ) );
		}
		return true;
	}

	private static function archive_is_valid( $file, $encrypted, $password ) {
		$zip    = new \ZipArchive();
		$opened = $zip->open( $file, \ZipArchive::CHECKCONS );
		if ( true !== $opened ) {
			return false;
		}
		if ( $encrypted ) {
			$zip->setPassword( $password );
			if ( false === $zip->getFromName( 'backup-info.json' ) ) {
				$zip->close();
				return false;
			}
		}
		$zip->close();
		return true;
	}

	private static function aes_encryption_available( $directory, $password ) {
		if ( ! method_exists( '\\ZipArchive', 'setEncryptionName' ) || ! defined( 'ZipArchive::EM_AES_256' ) ) {
			return false;
		}
		if ( ! is_dir( $directory ) ) {
			return false;
		}
		$probe = $directory . '/.encryption-probe-' . wp_generate_password( 12, false, false ) . '.zip';
		$zip   = new \ZipArchive();
		if ( true !== $zip->open( $probe, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			return false;
		}
		$created = $zip->addFromString( 'probe.txt', 'sosc-encryption-probe' )
			&& self::encrypt_entry( $zip, 'probe.txt', $password );
		$created = $zip->close() && $created;
		if ( ! $created ) {
			self::remove_file( $probe );
			return false;
		}
		$reader = new \ZipArchive();
		$opened = true === $reader->open( $probe );
		if ( $opened ) {
			$reader->setPassword( $password );
			$opened = 'sosc-encryption-probe' === $reader->getFromName( 'probe.txt' );
			$reader->close();
		}
		self::remove_file( $probe );
		return $opened;
	}

	private static function encrypt_entry( $zip, $name, $password ) {
		return $zip->setEncryptionName( $name, \ZipArchive::EM_AES_256, $password );
	}

	private static function cleanup_stale_work_files( $directory ) {
		$patterns = array(
			$directory . '/.sosc-*.partial',
			$directory . '/.sosc-*.protected.php',
			$directory . '/.shonan-online-backup-*.partial',
			$directory . '/.shonan-online-backup-*.protected.php',
			$directory . '/.database-*.sql',
		);
		foreach ( $patterns as $pattern ) {
			$paths = glob( $pattern );
			if ( false === $paths ) {
				continue;
			}
			foreach ( $paths as $path ) {
				if ( is_file( $path ) && filemtime( $path ) < time() - 6 * HOUR_IN_SECONDS ) {
					self::remove_file( $path );
				}
			}
		}
	}

	private static function estimated_space_details() {
		global $wpdb;
		$file_bytes = self::directory_size( ABSPATH, self::excluded_paths() );
		$db_result  = $wpdb->get_var( 'SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = DATABASE()' );
		if ( null === $db_result || '' !== $wpdb->last_error || ! is_numeric( $db_result ) ) {
			return new \WP_Error( 'database_size_failed', __( '容量判定に必要なDBサイズを取得できません。', 'so-short-code' ) );
		}
		$db_bytes  = (int) $db_result;
		$sql_bytes = (int) ceil( $db_bytes * self::SQL_EXPANSION );
		$zip_bytes = (int) ceil( ( $file_bytes + $sql_bytes ) * self::ARCHIVE_OVERHEAD );
		return array(
			'file_bytes'      => $file_bytes,
			'database_bytes'  => $db_bytes,
			'sql_bytes'       => $sql_bytes,
			'zip_bytes'       => $zip_bytes,
			'peak_additional' => $sql_bytes + ( 2 * $zip_bytes ) + self::MINIMUM_RESERVE,
		);
	}

	private static function directory_size( $root, $excluded ) {
		$size     = 0;
		$iterator = self::file_iterator( $root, $excluded );
		foreach ( $iterator as $item ) {
			if ( $item->isFile() && ! $item->isLink() ) {
				$size += $item->getSize();
			}
		}
		return $size;
	}

	private static function validate_layout() {
		$root    = realpath( ABSPATH );
		$content = realpath( WP_CONTENT_DIR );
		$config  = realpath( ABSPATH . 'wp-config.php' );
		if ( false === $root || false === $content || 0 !== strpos( wp_normalize_path( $content ), trailingslashit( wp_normalize_path( $root ) ) ) ) {
			return new \WP_Error( 'backup_content_outside_root', __( 'WordPress設置先の外にあるwp-contentには対応していません。欠落を防ぐためバックアップを中止しました。サイト全体を保存できる別の方法を使用してください。', 'so-short-code' ) );
		}
		if ( false === $config || 0 !== strpos( wp_normalize_path( $config ), trailingslashit( wp_normalize_path( $root ) ) ) || ! is_readable( $config ) ) {
			return new \WP_Error( 'backup_config_outside_root', __( 'WordPress設置先内のwp-config.phpを読み取れません。親ディレクトリ等への配置には対応していないため、バックアップを中止しました。', 'so-short-code' ) );
		}
		return true;
	}

	private static function add_wordpress_files( $zip, $storage_directory, $encrypted, $password ) {
		$layout = self::validate_layout();
		if ( is_wp_error( $layout ) ) {
			return $layout;
		}
		$root     = wp_normalize_path( ABSPATH );
		$excluded = self::excluded_paths();
		$scan     = new \RecursiveIteratorIterator(
			new \RecursiveCallbackFilterIterator(
				new \RecursiveDirectoryIterator( ABSPATH, \FilesystemIterator::SKIP_DOTS ),
				static function ( $item ) use ( $excluded ) {
					return ! self::is_excluded( wp_normalize_path( $item->getPathname() ), $excluded );
				}
			),
			\RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ( $scan as $item ) {
			$path = wp_normalize_path( $item->getPathname() );
			if ( $item->isLink() && ! self::is_excluded( $path, $excluded ) ) {
				/* translators: %s: relative symbolic-link path. */
				return new \WP_Error( 'backup_symlink_unsupported', sprintf( __( 'シンボリックリンクはバックアップできません。実体ファイルへ置き換えるか除外してください: %s', 'so-short-code' ), ltrim( substr( $path, strlen( $root ) ), '/' ) ) );
			}
		}
		$iterator = self::file_iterator( ABSPATH, $excluded );
		foreach ( $iterator as $item ) {
			$path = wp_normalize_path( $item->getPathname() );
			if ( ! $item->isFile() || $item->isLink() || 0 === strpos( $path, wp_normalize_path( $storage_directory ) . '/' ) ) {
				continue;
			}
			$relative = ltrim( substr( $path, strlen( $root ) ), '/' );
			if ( in_array( $relative, array( 'database.sql', 'backup-info.json', 'RESTORE-INSTRUCTIONS.txt' ), true ) ) {
				/* translators: %s: conflicting file name in the WordPress root directory. */
				return new \WP_Error( 'backup_entry_name_conflict', sprintf( __( 'WordPress設置ディレクトリ直下のファイル名がバックアップ管理ファイルと重複しています: %s', 'so-short-code' ), $relative ) );
			}
			$entry = $relative;
			if ( ! $zip->addFile( $item->getPathname(), $entry ) ) {
				/* translators: %s: relative path of the file that could not be archived. */
				return new \WP_Error( 'zip_add_failed', sprintf( __( 'ファイルをZIPへ追加できません: %s', 'so-short-code' ), $relative ) );
			}
			if ( $encrypted && ! self::encrypt_entry( $zip, $entry, $password ) ) {
				/* translators: %s: relative path of the file that could not be encrypted. */
				return new \WP_Error( 'zip_encrypt_failed', sprintf( __( 'ファイルをAES-256で暗号化できません: %s', 'so-short-code' ), $relative ) );
			}
		}
		return true;
	}

	private static function file_iterator( $root, $excluded ) {
		$directory = new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS );
		$filter    = new \RecursiveCallbackFilterIterator(
			$directory,
			static function ( $item ) use ( $excluded ) {
				return ! $item->isLink() && ! self::is_excluded( wp_normalize_path( $item->getPathname() ), $excluded );
			}
		);
		return new \RecursiveIteratorIterator( $filter, \RecursiveIteratorIterator::LEAVES_ONLY );
	}

	private static function excluded_paths() {
		$paths = array(
			self::storage_root(),
			ABSPATH . '.maintenance',
			WP_CONTENT_DIR . '/sosc-backups',
			WP_CONTENT_DIR . '/cache',
			WP_CONTENT_DIR . '/upgrade',
			WP_CONTENT_DIR . '/updraft',
			WP_CONTENT_DIR . '/ai1wm-backups',
			WP_CONTENT_DIR . '/wpvividbackups',
		);
		return array_map( 'wp_normalize_path', $paths );
	}

	private static function is_excluded( $path, $excluded ) {
		foreach ( $excluded as $base ) {
			if ( $path === $base || 0 === strpos( $path, trailingslashit( $base ) ) ) {
				return true;
			}
		}
		// Exclusion names describe site content, never the site's parent directories.
		$relative = wp_normalize_path( $path );
		$root     = trailingslashit( wp_normalize_path( ABSPATH ) );
		if ( 0 === strpos( $relative, $root ) ) {
			$relative = substr( $relative, strlen( $root ) );
		}
		$segments = explode( '/', strtolower( $relative ) );
		if ( array_intersect( $segments, array( 'cache', 'caches', 'tmp', 'temp', 'logs' ) ) ) {
			return true;
		}
		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		return in_array( $extension, array( 'log', 'tmp', 'temp' ), true );
	}

	private static function prune() {
		$files = self::list_backups();
		foreach ( array_slice( $files, self::RETAIN_COUNT ) as $file ) {
			self::remove_file( self::storage_directory() . '/' . $file['name'] );
			if ( is_file( self::storage_directory() . '/' . $file['name'] ) ) {
				return new \WP_Error( 'backup_prune_failed', __( '古いバックアップを削除できないため、新しいバックアップを保持できません。', 'so-short-code' ) );
			}
		}
		return true;
	}

	private static function save_status( $result ) {
		update_option(
			'sosc_backup_last_status',
			array(
				'finished_at' => current_time( 'mysql' ),
				'success'     => ! is_wp_error( $result ),
				'message'     => is_wp_error( $result ) ? $result->get_error_message() : __( 'バックアップを作成しました。', 'so-short-code' ),
			),
			false
		);
	}

	private static function remove_file( $path ) {
		if ( is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}
}
