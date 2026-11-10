<?php
/**
 * Backup module coordinator.
 *
 * @package ShonanOnlineShortCode
 */

namespace ShonanOnline\ShortCode\Backup;

final class Plugin {
	const CRON_HOOK   = 'sosc_monthly_backup';
	const RETRY_HOOK  = 'sosc_monthly_backup_retry';
	const RETRY_KEY   = 'sosc_backup_retry_count';
	const MAX_RETRIES = 3;

	private static $booted = false;

	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'init', array( __CLASS__, 'reconcile_schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_scheduled_backup' ) );
		add_action( self::RETRY_HOOK, array( __CLASS__, 'run_retry_backup' ) );
		add_action( 'admin_post_sosc_backup_run', array( Admin::class, 'run_now' ) );
		add_action( 'admin_post_sosc_backup_download', array( Admin::class, 'download' ) );
	}

	public static function enabled() {
		return '1' === sosc_get_value( 'monthly_backup_is_check', '0' );
	}

	public static function reconcile_schedule() {
		if ( ! self::enabled() ) {
			self::unschedule();
			return;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			self::schedule_event( self::next_timestamp(), self::CRON_HOOK );
		}
	}

	public static function reschedule() {
		self::unschedule();
		delete_option( self::RETRY_KEY );
		if ( self::enabled() ) {
			self::schedule_event( self::next_timestamp(), self::CRON_HOOK );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( self::RETRY_HOOK );
		delete_option( self::RETRY_KEY );
	}

	public static function run_scheduled_backup() {
		if ( ! self::enabled() ) {
			self::unschedule();
			return;
		}

		// Register both the next month and a retry watchdog before starting.
		// A hard PHP timeout cannot otherwise schedule a retry after failure.
		wp_clear_scheduled_hook( self::CRON_HOOK );
		self::schedule_event( self::next_timestamp(), self::CRON_HOOK );
		self::schedule_retry( 0 );
		$result = Manager::create( 'cron' );
		if ( ! is_wp_error( $result ) ) {
			self::clear_retry();
			return;
		}
	}

	public static function run_retry_backup() {
		if ( ! self::enabled() ) {
			self::clear_retry();
			return;
		}
		$retry_state = get_option( self::RETRY_KEY, array() );
		$month       = wp_date( 'Y-m' );
		$retry_count = is_array( $retry_state ) && isset( $retry_state['month'], $retry_state['count'] ) && $month === $retry_state['month'] ? (int) $retry_state['count'] : 0;
		if ( $retry_count >= self::MAX_RETRIES ) {
			self::clear_retry();
			return;
		}

		++$retry_count;
		if ( $retry_count < self::MAX_RETRIES ) {
			self::schedule_retry( $retry_count );
		} else {
			update_option(
				self::RETRY_KEY,
				array(
					'month' => $month,
					'count' => $retry_count,
				),
				false
			);
		}
		$result = Manager::create( 'cron-retry' );
		if ( ! is_wp_error( $result ) ) {
			self::clear_retry();
		}
	}

	private static function schedule_retry( $retry_count ) {
		wp_clear_scheduled_hook( self::RETRY_HOOK );
		update_option(
			self::RETRY_KEY,
			array(
				'month' => wp_date( 'Y-m' ),
				'count' => (int) $retry_count,
			),
			false
		);
		self::schedule_event( time() + DAY_IN_SECONDS, self::RETRY_HOOK );
	}

	private static function schedule_event( $timestamp, $hook ) {
		$result = wp_schedule_single_event( $timestamp, $hook, array(), true );
		if ( is_wp_error( $result ) || ! $result ) {
			$message = is_wp_error( $result ) ? $result->get_error_message() : __( '不明なエラー', 'so-short-code' );
			update_option( 'sosc_backup_schedule_error', sanitize_text_field( $message ), false );
			return false;
		}
		delete_option( 'sosc_backup_schedule_error' );
		return true;
	}

	private static function clear_retry() {
		wp_clear_scheduled_hook( self::RETRY_HOOK );
		delete_option( self::RETRY_KEY );
	}

	public static function next_timestamp() {
		$timezone = wp_timezone();
		$now      = new \DateTimeImmutable( 'now', $timezone );
		$target   = $now->modify( 'last day of this month' )->setTime( 3, 0, 0 );
		if ( $target <= $now ) {
			$target = $now->modify( 'first day of next month' )->modify( 'last day of this month' )->setTime( 3, 0, 0 );
		}

		return $target->getTimestamp();
	}

	public static function deactivate() {
		self::unschedule();
		delete_option( Manager::LOCK_KEY );
		delete_option( self::RETRY_KEY );
		delete_option( 'sosc_backup_schedule_error' );
	}
}
