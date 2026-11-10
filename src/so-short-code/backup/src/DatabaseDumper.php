<?php
/**
 * Consistent MySQL database dump service.
 *
 * @package ShonanOnlineShortCode
 */

namespace ShonanOnline\ShortCode\Backup;

final class DatabaseDumper {
	const PAGE_SIZE              = 500;
	private static $write_failed = false;

	public static function dump( $file ) {
		global $wpdb;
		$time_zone = $wpdb->get_var( 'SELECT @@SESSION.time_zone' );
		if ( ! is_string( $time_zone ) || '' === $time_zone || false === $wpdb->query( "SET SESSION time_zone='+00:00'" ) ) {
			return new \WP_Error( 'database_timezone_failed', __( 'DBの時刻をUTCで取得する準備ができません。', 'so-short-code' ) );
		}
		try {
			$result = self::dump_utc( $file );
		} finally {
			$restored = $wpdb->query( $wpdb->prepare( 'SET SESSION time_zone=%s', $time_zone ) );
		}
		if ( false === $restored ) {
			return new \WP_Error( 'database_timezone_restore_failed', __( 'DB接続のタイムゾーンを元に戻せませんでした。', 'so-short-code' ) );
		}
		return $result;
	}

	private static function dump_utc( $file ) {
		global $wpdb;
		self::$write_failed = false;

		$objects = self::database_objects();
		if ( is_wp_error( $objects ) ) {
			return $objects;
		}
		$engine_check = self::validate_engines( $objects['tables'] );
		if ( is_wp_error( $engine_check ) ) {
			return $engine_check;
		}

		$handle = fopen( $file, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			return new \WP_Error( 'database_file_failed', __( 'DBダンプの一時ファイルを作成できません。', 'so-short-code' ) );
		}

		self::write(
			$handle,
			"SET @SOSC_OLD_TIME_ZONE=@@SESSION.time_zone;\n"
			. "SET @SOSC_OLD_SQL_MODE=@@SESSION.sql_mode;\n"
			. "SET @SOSC_OLD_FOREIGN_KEY_CHECKS=@@SESSION.foreign_key_checks;\n"
			. "SET @SOSC_OLD_CHARACTER_SET_CLIENT=@@SESSION.character_set_client;\n"
			. "SET @SOSC_OLD_CHARACTER_SET_RESULTS=@@SESSION.character_set_results;\n"
			. "SET @SOSC_OLD_CHARACTER_SET_CONNECTION=@@SESSION.character_set_connection;\n"
			. "SET @SOSC_OLD_COLLATION_CONNECTION=@@SESSION.collation_connection;\n"
			. "SET SESSION time_zone='+00:00';\nSET NAMES utf8mb4;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\nSET FOREIGN_KEY_CHECKS=0;\n\n"
		);
		if ( false === $wpdb->query( 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ' ) || false === $wpdb->query( 'START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY' ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new \WP_Error( 'database_snapshot_failed', __( 'DBの整合性スナップショットを開始できません。', 'so-short-code' ) );
		}

		$result = self::dump_tables( $handle, $objects['tables'] );
		if ( ! is_wp_error( $result ) ) {
			$result = self::dump_view_placeholders( $handle, $objects['views'] );
		}
		if ( is_wp_error( $result ) ) {
			$wpdb->query( 'ROLLBACK' );
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return $result;
		}
		if ( self::$write_failed ) {
			$wpdb->query( 'ROLLBACK' );
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new \WP_Error( 'database_write_failed', __( 'DBダンプを書き込めませんでした。空き容量とファイル権限を確認してください。', 'so-short-code' ) );
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new \WP_Error( 'database_snapshot_commit_failed', __( 'DBの整合性スナップショットを終了できません。', 'so-short-code' ) );
		}

		$result = self::dump_stored_objects( $handle, $objects['views'] );
		if ( is_wp_error( $result ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return $result;
		}
		self::write(
			$handle,
			"SET FOREIGN_KEY_CHECKS=@SOSC_OLD_FOREIGN_KEY_CHECKS;\n"
			. "SET SESSION SQL_MODE=@SOSC_OLD_SQL_MODE;\n"
			. "SET SESSION character_set_client=@SOSC_OLD_CHARACTER_SET_CLIENT;\n"
			. "SET SESSION character_set_results=@SOSC_OLD_CHARACTER_SET_RESULTS;\n"
			. "SET SESSION character_set_connection=@SOSC_OLD_CHARACTER_SET_CONNECTION;\n"
			. "SET SESSION collation_connection=@SOSC_OLD_COLLATION_CONNECTION;\n"
			. "SET SESSION time_zone=@SOSC_OLD_TIME_ZONE;\n"
		);
		if ( self::$write_failed || ! fflush( $handle ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fflush
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new \WP_Error( 'database_write_failed', __( 'DBダンプを書き込めませんでした。空き容量とファイル権限を確認してください。', 'so-short-code' ) );
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return true;
	}

	private static function database_objects() {
		global $wpdb;

		$rows = $wpdb->get_results( 'SHOW FULL TABLES', ARRAY_N );
		if ( ! is_array( $rows ) || '' !== $wpdb->last_error ) {
			return new \WP_Error( 'database_objects_failed', __( 'DBオブジェクト一覧を取得できません。', 'so-short-code' ) );
		}
		$objects = array(
			'tables' => array(),
			'views'  => array(),
		);
		foreach ( $rows as $row ) {
			if ( 'VIEW' === strtoupper( (string) $row[1] ) ) {
				$objects['views'][] = (string) $row[0];
			} else {
				$objects['tables'][] = (string) $row[0];
			}
		}
		return $objects;
	}

	private static function validate_engines( $tables ) {
		global $wpdb;

		if ( empty( $tables ) ) {
			return true;
		}
		$placeholders = implode( ',', array_fill( 0, count( $tables ), '%s' ) );
		$sql          = $wpdb->prepare(
			"SELECT table_name, engine FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The placeholder list is generated to match the database table name array.
			$tables
		);
		$engines      = $wpdb->get_results( $sql, ARRAY_N );
		if ( ! is_array( $engines ) || '' !== $wpdb->last_error ) {
			return new \WP_Error( 'database_engine_check_failed', __( 'DBテーブルのストレージエンジンを確認できません。', 'so-short-code' ) );
		}
		$unsupported = array();
		foreach ( $engines as $engine ) {
			if ( 'INNODB' !== strtoupper( (string) $engine[1] ) ) {
				$unsupported[] = (string) $engine[0];
			}
		}
		if ( $unsupported ) {
			return new \WP_Error(
				'database_nontransactional_tables',
				sprintf(
					/* translators: %s: comma-separated list of non-InnoDB tables. */
					__( '整合性を保証できない非InnoDBテーブルがあります: %s', 'so-short-code' ),
					implode( ', ', $unsupported )
				)
			);
		}
		return true;
	}

	private static function dump_tables( $handle, $tables ) {
		global $wpdb;

		foreach ( $tables as $table ) {
			$identifier = self::identifier( $table );
			$order_data = self::table_order_data( $table );
			if ( is_wp_error( $order_data ) ) {
				return $order_data;
			}
			$order_by    = $order_data['sql'];
			$definitions = $wpdb->get_results( 'SHOW FULL COLUMNS FROM ' . $identifier, ARRAY_A );
			if ( ! $definitions || '' !== $wpdb->last_error ) {
				return new \WP_Error( 'database_columns_failed', __( 'DBテーブルの列定義を取得できません。', 'so-short-code' ) );
			}
			$select_columns    = array();
			$generated_columns = array();
			$column_types      = array();
			foreach ( $definitions as $definition ) {
				$column_types[ $definition['Field'] ] = $definition['Type'];
				$select_columns[]                     = self::identifier( $definition['Field'] );
				if ( preg_match( '/\b(?:STORED|VIRTUAL) GENERATED\b/i', $definition['Extra'] ) ) {
					$generated_columns[ $definition['Field'] ] = true;
				}
			}
			// Explicit projection includes invisible columns; generated values are
			// read for ordering but must be recomputed by MySQL during restoration.
			$select_columns = implode( ',', $select_columns );
			$create         = $wpdb->get_row( "SHOW CREATE TABLE {$identifier}", ARRAY_N );
			if ( empty( $create[1] ) ) {
				return new \WP_Error( 'database_schema_failed', __( 'DBテーブル定義を取得できません。', 'so-short-code' ) );
			}
			self::write( $handle, "DROP TABLE IF EXISTS {$identifier};\n" . $create[1] . ";\n" );
			$offset   = 0;
			$last_row = null;
			do {
				if ( $order_data['keyset'] && is_array( $last_row ) ) {
					$where = self::keyset_where( $order_data['columns'], $last_row );
					$rows  = $wpdb->get_results( "SELECT {$select_columns} FROM {$identifier} WHERE {$where} ORDER BY {$order_by} LIMIT " . self::PAGE_SIZE, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Identifiers come from INFORMATION_SCHEMA and values are escaped by sql_value().
				} else {
					$rows = $wpdb->get_results( $wpdb->prepare( "SELECT {$select_columns} FROM {$identifier} ORDER BY {$order_by} LIMIT %d OFFSET %d", self::PAGE_SIZE, $offset ), ARRAY_A );
				}
				if ( ! is_array( $rows ) || '' !== $wpdb->last_error ) {
					/* translators: %s: database table name. */
					return new \WP_Error( 'database_table_read_failed', sprintf( __( 'DBテーブルのデータを読み取れません: %s', 'so-short-code' ), $table ) );
				}
				$row_count = count( $rows );
				foreach ( $rows as $row ) {
					if ( self::row_is_excluded( $table, $row ) ) {
						continue;
					}
					$row     = array_diff_key( $row, $generated_columns );
					$columns = array_map( array( __CLASS__, 'identifier' ), array_keys( $row ) );
					$values  = array();
					foreach ( $row as $column => $value ) {
						$values[] = self::sql_value( $value, $column_types[ $column ] );
					}
					self::write( $handle, "INSERT INTO {$identifier} (" . implode( ',', $columns ) . ') VALUES (' . implode( ',', $values ) . ");\n" );
				}
				if ( $order_data['keyset'] && $rows ) {
					$last_row = $rows[ $row_count - 1 ];
				} else {
					$offset += self::PAGE_SIZE;
				}
			} while ( self::PAGE_SIZE === $row_count );
			self::write( $handle, "\n" );
		}
		return true;
	}

	private static function table_order_data( $table ) {
		global $wpdb;

		$columns         = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT column_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = %s AND index_name = %s ORDER BY seq_in_index',
				$table,
				'PRIMARY'
			)
		);
		$primary         = ! empty( $columns );
		$primary_columns = $primary ? $columns : array();
		$keyset          = $primary;
		if ( $primary ) {
			$primary_types = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT data_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = %s AND column_name IN (' . implode( ',', array_fill( 0, count( $columns ), '%s' ) ) . ')', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholder count matches the primary-key column list.
					array_merge( array( $table ), $columns )
				)
			);
			if ( count( $primary_types ) !== count( $columns ) || array_intersect( array( 'enum', 'set' ), array_map( 'strtolower', $primary_types ) ) ) {
				// ENUM and SET sort by their definition order, while comparisons with
				// string literals use different semantics. OFFSET remains stable inside
				// the consistent snapshot and cannot skip those primary-key values.
				$keyset = false;
			}
		}
		if ( ! $primary ) {
			$columns = $wpdb->get_col( 'SHOW COLUMNS FROM ' . self::identifier( $table ), 0 );
			if ( empty( $columns ) || '' !== $wpdb->last_error ) {
				return new \WP_Error( 'database_order_columns_failed', __( 'DBテーブルの安定した出力順を確認できません。', 'so-short-code' ) );
			}
			$columns = array_map(
				static function ( $column ) {
					return 'COALESCE(HEX(' . self::identifier( $column ) . "),'')";
				},
				$columns
			);
		} else {
			$columns = array_map( array( __CLASS__, 'identifier' ), $columns );
		}
		if ( empty( $columns ) || '' !== $wpdb->last_error ) {
			return new \WP_Error( 'database_order_columns_failed', __( 'DBテーブルの安定した出力順を確認できません。', 'so-short-code' ) );
		}
		return array(
			'sql'     => implode( ',', $columns ),
			'columns' => $primary_columns,
			'keyset'  => $keyset,
		);
	}

	private static function keyset_where( $columns, $last_row ) {
		$parts = array();
		foreach ( $columns as $position => $column ) {
			$comparisons = array();
			for ( $index = 0; $index < $position; ++$index ) {
				$previous      = $columns[ $index ];
				$comparisons[] = self::identifier( $previous ) . '=' . self::comparison_value( $last_row[ $previous ] );
			}
			$comparisons[] = self::identifier( $column ) . '>' . self::comparison_value( $last_row[ $column ] );
			$parts[]       = '(' . implode( ' AND ', $comparisons ) . ')';
		}
		return '(' . implode( ' OR ', $parts ) . ')';
	}

	/**
	 * Format a primary-key boundary without changing the column collation.
	 *
	 * @param mixed $value Boundary value.
	 * @return string
	 */
	private static function comparison_value( $value ) {
		global $wpdb;

		return $wpdb->prepare( '%s', (string) $value );
	}

	private static function row_is_excluded( $table, $row ) {
		global $wpdb;

		if ( $wpdb->options === $table && isset( $row['option_name'] ) && Manager::LOCK_KEY === $row['option_name'] ) {
			return true;
		}
		return $wpdb->prefix . 'so_short_code' === $table
			&& isset( $row['param_name'] )
			&& 'monthly_backup_password' === $row['param_name'];
	}

	private static function dump_view_placeholders( $handle, $views ) {
		global $wpdb;

		foreach ( $views as $view ) {
			$identifier = self::identifier( $view );
			$columns    = $wpdb->get_col( "SHOW COLUMNS FROM {$identifier}", 0 );
			if ( empty( $columns ) ) {
				return new \WP_Error( 'database_view_columns_failed', __( 'DBビューの列定義を取得できません。', 'so-short-code' ) );
			}
			$definitions = array_map(
				static function ( $column ) {
					return self::identifier( $column ) . ' TEXT NULL';
				},
				$columns
			);
			self::write( $handle, "DROP VIEW IF EXISTS {$identifier};\nDROP TABLE IF EXISTS {$identifier};\nCREATE TABLE {$identifier} (" . implode( ',', $definitions ) . ");\n\n" );
		}
		return true;
	}

	private static function dump_stored_objects( $handle, $views ) {
		$result = self::dump_routines( $handle );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result = self::dump_views( $handle, $views );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result = self::dump_triggers( $handle );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::dump_events( $handle );
	}

	private static function dump_routines( $handle ) {
		global $wpdb;

		$routines = $wpdb->get_results( 'SELECT routine_name, routine_type FROM information_schema.routines WHERE routine_schema = DATABASE() ORDER BY routine_type, routine_name', ARRAY_N );
		if ( ! is_array( $routines ) || '' !== $wpdb->last_error ) {
			return new \WP_Error( 'database_routine_list_failed', __( 'DBルーチン一覧を取得できません。権限を確認してください。', 'so-short-code' ) );
		}
		foreach ( $routines as $routine ) {
			$type   = 'FUNCTION' === strtoupper( (string) $routine[1] ) ? 'FUNCTION' : 'PROCEDURE';
			$name   = (string) $routine[0];
			$create = $wpdb->get_row( "SHOW CREATE {$type} " . self::identifier( $name ), ARRAY_A );
			$key    = 'FUNCTION' === $type ? 'Create Function' : 'Create Procedure';
			if ( empty( $create[ $key ] ) ) {
				return new \WP_Error( 'database_routine_failed', __( 'DBルーチン定義を取得できません。権限を確認してください。', 'so-short-code' ) );
			}
			self::write_delimited_object(
				$handle,
				"DROP {$type} IF EXISTS " . self::identifier( $name ),
				self::portable_definition( $create[ $key ] ),
				isset( $create['sql_mode'] ) ? $create['sql_mode'] : ''
			);
		}
		return true;
	}

	private static function dump_views( $handle, $views ) {
		global $wpdb;

		foreach ( $views as $view ) {
			$identifier = self::identifier( $view );
			$create     = $wpdb->get_row( "SHOW CREATE VIEW {$identifier}", ARRAY_A );
			if ( empty( $create['Create View'] ) ) {
				return new \WP_Error( 'database_view_failed', __( 'DBビュー定義を取得できません。権限を確認してください。', 'so-short-code' ) );
			}
			self::write( $handle, "DROP TABLE IF EXISTS {$identifier};\nDROP VIEW IF EXISTS {$identifier};\n" . self::portable_definition( $create['Create View'] ) . ";\n\n" );
		}
		return true;
	}

	private static function dump_triggers( $handle ) {
		global $wpdb;

		$triggers = $wpdb->get_col( 'SELECT trigger_name FROM information_schema.triggers WHERE trigger_schema = DATABASE() ORDER BY trigger_name' );
		if ( ! is_array( $triggers ) || '' !== $wpdb->last_error ) {
			return new \WP_Error( 'database_trigger_list_failed', __( 'DBトリガー一覧を取得できません。権限を確認してください。', 'so-short-code' ) );
		}
		foreach ( $triggers as $trigger ) {
			$create = $wpdb->get_row( 'SHOW CREATE TRIGGER ' . self::identifier( $trigger ), ARRAY_A );
			if ( empty( $create['SQL Original Statement'] ) ) {
				return new \WP_Error( 'database_trigger_failed', __( 'DBトリガー定義を取得できません。権限を確認してください。', 'so-short-code' ) );
			}
			self::write_delimited_object(
				$handle,
				'DROP TRIGGER IF EXISTS ' . self::identifier( $trigger ),
				self::portable_definition( $create['SQL Original Statement'] ),
				isset( $create['sql_mode'] ) ? $create['sql_mode'] : ''
			);
		}
		return true;
	}

	private static function dump_events( $handle ) {
		global $wpdb;

		$events = $wpdb->get_col( 'SELECT event_name FROM information_schema.events WHERE event_schema = DATABASE() ORDER BY event_name' );
		if ( ! is_array( $events ) || '' !== $wpdb->last_error ) {
			return new \WP_Error( 'database_event_list_failed', __( 'DBイベント一覧を取得できません。権限を確認してください。', 'so-short-code' ) );
		}
		foreach ( $events as $event ) {
			$create = $wpdb->get_row( 'SHOW CREATE EVENT ' . self::identifier( $event ), ARRAY_A );
			if ( empty( $create['Create Event'] ) ) {
				return new \WP_Error( 'database_event_failed', __( 'DBイベント定義を取得できません。権限を確認してください。', 'so-short-code' ) );
			}
			self::write_delimited_object(
				$handle,
				'DROP EVENT IF EXISTS ' . self::identifier( $event ),
				self::portable_definition( $create['Create Event'] ),
				isset( $create['sql_mode'] ) ? $create['sql_mode'] : '',
				isset( $create['time_zone'] ) ? $create['time_zone'] : ''
			);
		}
		return true;
	}

	private static function write_delimited_object( $handle, $drop, $create, $sql_mode = '', $time_zone = '' ) {
		global $wpdb;
		self::write( $handle, "SET @SOSC_OBJECT_SQL_MODE=@@SESSION.sql_mode;\n" );
		self::write( $handle, 'SET SESSION sql_mode=' . $wpdb->prepare( '%s', (string) $sql_mode ) . ";\n" );
		if ( '' !== $time_zone ) {
			self::write( $handle, "SET @SOSC_OBJECT_TIME_ZONE=@@SESSION.time_zone;\n" );
			self::write( $handle, 'SET SESSION time_zone=' . $wpdb->prepare( '%s', (string) $time_zone ) . ";\n" );
		}
		self::write( $handle, "DELIMITER ;;\n{$drop};;\n{$create};;\nDELIMITER ;\n" );
		if ( '' !== $time_zone ) {
			self::write( $handle, "SET SESSION time_zone=@SOSC_OBJECT_TIME_ZONE;\n" );
		}
		self::write( $handle, "SET SESSION sql_mode=@SOSC_OBJECT_SQL_MODE;\n\n" );
	}

	private static function portable_definition( $definition ) {
		return (string) preg_replace(
			'/^(\s*CREATE(?:\s+ALGORITHM\s*=\s*\S+)?\s+)DEFINER\s*=\s*(?:`(?:``|[^`])+`|\'(?:\'\'|[^\'])+\'|[^\s@]+)@(?:`(?:``|[^`])+`|\'(?:\'\'|[^\'])+\'|[^\s]+)\s+(?=(?:SQL\s+SECURITY\s+(?:DEFINER|INVOKER)\s+)?(?:FUNCTION|PROCEDURE|VIEW|TRIGGER|EVENT)\b)/i',
			'$1',
			(string) $definition
		);
	}

	private static function identifier( $name ) {
		return '`' . str_replace( '`', '``', (string) $name ) . '`';
	}

	private static function sql_value( $value, $type ) {
		if ( null === $value ) {
			return 'NULL';
		}
		// A bare hex literal becomes a number in numeric/ENUM destinations.
		// Preserve numeric precision without converting through PHP float/int.
		if ( preg_match( '/^(?:tinyint|smallint|mediumint|int|bigint|decimal|numeric|float|double|real|bit|year)\b/i', $type )
			&& preg_match( '/^-?[0-9]+(?:\.[0-9]+)?(?:e[+-]?[0-9]+)?$/i', (string) $value ) ) {
			return (string) $value;
		}
		$hex = "X'" . bin2hex( (string) $value ) . "'";
		if ( preg_match( '/^(?:geometry|point|linestring|polygon|multipoint|multilinestring|multipolygon|geometrycollection|geomcollection)\b/i', $type ) ) {
			// Convert MySQL's internal geometry to WKB and reconstruct it with its
			// original SRID. Both functions use the same SRS axis order.
			return 'ST_GeomFromWKB(ST_AsWKB(' . $hex . '),ST_SRID(' . $hex . '))';
		}
		if ( preg_match( '/^(?:tinyblob|blob|mediumblob|longblob|binary|varbinary)\b/i', $type ) ) {
			return $hex;
		}
		// Text/JSON are returned in the WordPress connection character set.
		global $wpdb;
		$charset = preg_replace( '/[^a-zA-Z0-9_]/', '', $wpdb->charset );
		return 'CONVERT(' . $hex . ' USING ' . ( $charset ? $charset : 'utf8mb4' ) . ')';
	}

	private static function write( $handle, $contents ) {
		$length  = strlen( $contents );
		$written = fwrite( $handle, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		if ( false === $written || $written !== $length ) {
			self::$write_failed = true;
		}
	}
}
