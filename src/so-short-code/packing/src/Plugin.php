<?php
/**
 * Packing plugin coordinator.
 *
 * @package ShonanOnlineShortCode
 */

namespace ShonanOnline\ShortCode\Packing;

final class Plugin {
	const DAILY_HOOK = 'sosc_packing_daily';

	private static $booted = false;

	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'plugins_loaded', array( __CLASS__, 'init' ), 20 );
		add_action( 'admin_menu', array( Admin::class, 'register_menu' ), 30 );
		add_action( 'admin_init', array( Admin::class, 'handle_actions' ) );
		add_action( 'admin_post_sosc_packing_export_skus', array( Admin::class, 'export_skus' ) );
		add_action( 'admin_post_sosc_packing_import_skus', array( Admin::class, 'import_skus' ) );
		add_action( 'admin_post_sosc_packing_commit_skus', array( Admin::class, 'commit_skus' ) );
		add_action( 'wp_ajax_sosc_packing_checkout_consent', array( WelcartIntegration::class, 'record_checkout_consent' ) );
		add_action( 'wp_ajax_nopriv_sosc_packing_checkout_consent', array( WelcartIntegration::class, 'record_checkout_consent' ) );
		add_action( self::DAILY_HOOK, array( Maintenance::class, 'run' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	public static function init() {
		if ( function_exists( 'sosc_migrate_settings_uniqueness' ) ) {
			sosc_migrate_settings_uniqueness();
		}
		Database::maybe_install();
		if ( Settings::feature_selected() ) {
			Admin::register_product_hooks();
		}
		if ( Settings::enabled() ) {
			self::load_dependencies();
		}
		WelcartIntegration::register();
	}

	private static function load_dependencies() {
		$autoload = dirname( __DIR__, 2 ) . '/vendor/autoload.php';
		if ( file_exists( $autoload ) ) {
			require_once $autoload;
		}
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::DAILY_HOOK ) ) {
			$result = wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::DAILY_HOOK, array(), true );
			if ( is_wp_error( $result ) || ! $result ) {
				$message = is_wp_error( $result ) ? $result->get_error_message() : '不明なエラー';
				update_option( 'sosc_packing_schedule_error', sanitize_text_field( $message ), false );
				return;
			}
			delete_option( 'sosc_packing_schedule_error' );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::DAILY_HOOK );
		delete_option( 'sosc_packing_maintenance_lock' );
		delete_option( 'sosc_packing_schedule_error' );
	}
}
