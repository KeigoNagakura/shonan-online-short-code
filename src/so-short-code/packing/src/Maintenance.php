<?php
/**
 * Packing maintenance.
 *
 * @package ShonanOnlineShortCode
 */

namespace ShonanOnline\ShortCode\Packing;

final class Maintenance {
	public static function run() {
		global $wpdb;
		$lock      = 'sosc_packing_maintenance_lock';
		$lock_time = (int) get_option( $lock, 0 );
		if ( $lock_time && $lock_time < time() - 10 * MINUTE_IN_SECONDS ) {
			delete_option( $lock );
		}
		if ( ! add_option( $lock, time(), '', false ) ) {
			return new \WP_Error( 'maintenance_locked', '別の保守処理が実行中です。しばらく待ってから再実行してください。' );
		}
		try {
			$now          = Database::now();
			$deleted_logs = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Database::table( 'logs' ) . ' WHERE expires_at IS NOT NULL AND expires_at < %s LIMIT 10000', $now ) );
			if ( false === $deleted_logs ) {
				delete_option( $lock );
				return new \WP_Error( 'maintenance_log_cleanup_failed', 'ログの整理に失敗しました。' );
			}
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'orders' ) . " WHERE status='temporary' AND expires_at IS NOT NULL AND expires_at < %s ORDER BY expires_at LIMIT 2000", $now ) );
			if ( null === $rows ) {
				delete_option( $lock );
				return new \WP_Error( 'maintenance_order_read_failed', '仮注文の取得に失敗しました。' );
			}
			foreach ( $rows as $row ) {
				$order_id = isset( $GLOBALS['usces'] ) && method_exists( $GLOBALS['usces'], 'get_order_id_by_pre_order_id' ) ? absint( $GLOBALS['usces']->get_order_id_by_pre_order_id( $row->pre_order_id ) ) : 0;
				if ( $order_id ) {
					$result = $wpdb->update(
						Database::table( 'orders' ),
						array(
							'order_id'   => $order_id,
							'status'     => 'confirmed',
							'expires_at' => null,
							'updated_at' => $now,
						),
						array( 'id' => $row->id )
					);
					if ( false === $result ) {
						delete_option( $lock );
						return new \WP_Error( 'maintenance_order_update_failed', '確定注文への更新に失敗しました。' );
					}
					continue;
				}
				$snapshot = json_decode( $row->snapshot, true );
				if ( ! is_array( $snapshot ) ) {
					$result = $wpdb->update(
						Database::table( 'orders' ),
						array(
							'status'     => 'external_review',
							'updated_at' => $now,
						),
						array( 'id' => $row->id )
					);
					if ( false === $result ) {
						delete_option( $lock );
						return new \WP_Error( 'maintenance_review_update_failed', '破損データの隔離に失敗しました。' );
					}
					Database::log(
						'snapshot_corrupted',
						array(
							'record_id'      => (int) $row->id,
							'pre_order_hash' => hash( 'sha256', (string) $row->pre_order_id ),
						),
						'error',
						null,
						wp_generate_uuid4(),
						365
					);
					continue;
				}
				if ( ! empty( $snapshot['external_payment'] ) ) {
					$review_at = strtotime( $row->created_at . ' UTC' ) + 30 * DAY_IN_SECONDS;
					if ( time() >= $review_at ) {
						$result = $wpdb->update(
							Database::table( 'orders' ),
							array(
								'status'     => 'external_review',
								'updated_at' => $now,
							),
							array( 'id' => $row->id )
						);
						if ( false === $result ) {
							delete_option( $lock );
							return new \WP_Error( 'maintenance_external_update_failed', '外部決済データの隔離に失敗しました。' );
						}
					} else {
						$result = $wpdb->update(
							Database::table( 'orders' ),
							array( 'expires_at' => gmdate( 'Y-m-d H:i:s', $review_at ) ),
							array( 'id' => $row->id ),
							array( '%s' ),
							array( '%d' )
						);
						if ( false === $result ) {
							return new \WP_Error( 'maintenance_external_reschedule_failed', '外部決済データの再確認日時を更新できませんでした。' );
						}
					}
					continue;
				}
				if ( false === $wpdb->delete( Database::table( 'orders' ), array( 'id' => $row->id ) ) ) {
					delete_option( $lock );
					return new \WP_Error( 'maintenance_order_delete_failed', '期限切れ仮注文の削除に失敗しました。' );
				}
			}
			if ( 10000 === (int) $deleted_logs || 2000 === count( $rows ) ) {
				wp_schedule_single_event( time() + MINUTE_IN_SECONDS, Plugin::DAILY_HOOK );
			}
			update_option( 'sosc_packing_last_cron_success', $now, false );
			return true;
		} finally {
			delete_option( $lock );
		}
	}
}
