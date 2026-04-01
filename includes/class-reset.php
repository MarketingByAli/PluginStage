<?php
/**
 * Snapshots, site reset, scheduling.
 *
 * @package PluginStage
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PluginStage_Reset
 */
class PluginStage_Reset {

	/**
	 * Cron hook name.
	 */
	const CRON_HOOK = 'pluginstage_scheduled_reset';

	/**
	 * Instance.
	 *
	 * @var PluginStage_Reset|null
	 */
	private static $instance = null;

	/**
	 * Instance.
	 *
	 * @return PluginStage_Reset
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Init hooks.
	 */
	public function init() {
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_scheduled_reset' ) );
	}

	/**
	 * Custom cron intervals.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public function add_cron_schedules( $schedules ) {
		$schedules['pluginstage_15min'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 minutes', 'pluginstage' ),
		);
		$schedules['pluginstage_30min'] = array(
			'interval' => 30 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 30 minutes', 'pluginstage' ),
		);
		$schedules['pluginstage_1hour'] = array(
			'interval' => HOUR_IN_SECONDS,
			'display'  => __( 'Every hour', 'pluginstage' ),
		);
		$schedules['pluginstage_2hours'] = array(
			'interval' => 2 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 2 hours', 'pluginstage' ),
		);
		$schedules['pluginstage_6hours'] = array(
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 6 hours', 'pluginstage' ),
		);
		return $schedules;
	}

	/**
	 * Map setting slug to wp_schedule_event recurrence.
	 *
	 * @param string $schedule Schedule key.
	 * @return string|false
	 */
	public function get_cron_recurrence( $schedule ) {
		$map = array(
			'15min'  => 'pluginstage_15min',
			'30min'  => 'pluginstage_30min',
			'1hour'  => 'pluginstage_1hour',
			'2hours' => 'pluginstage_2hours',
			'6hours' => 'pluginstage_6hours',
			'daily'  => 'daily',
		);
		return isset( $map[ $schedule ] ) ? $map[ $schedule ] : false;
	}

	/**
	 * Reschedule cron from settings.
	 */
	public function reschedule_cron() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		$schedule = get_option( 'pluginstage_reset_schedule', 'manual' );
		if ( 'manual' === $schedule ) {
			$this->update_next_reset_timestamp( 0 );
			return;
		}
		$recurrence = $this->get_cron_recurrence( $schedule );
		if ( ! $recurrence ) {
			return;
		}
		$next = wp_schedule_event( time() + 60, $recurrence, self::CRON_HOOK );
		if ( $next ) {
			$this->update_next_reset_timestamp_from_schedule();
		}
	}

	/**
	 * Set next reset option from next scheduled event.
	 */
	public function update_next_reset_timestamp_from_schedule() {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		$this->update_next_reset_timestamp( $ts ? (int) $ts : 0 );
	}

	/**
	 * Store next reset unix timestamp.
	 *
	 * @param int $ts Timestamp.
	 */
	public function update_next_reset_timestamp( $ts ) {
		update_option( 'pluginstage_next_reset_at', (int) $ts, false );
	}

	/**
	 * Cron callback.
	 */
	public function run_scheduled_reset() {
		$snapshot_id = (string) get_option( 'pluginstage_current_snapshot_id', '' );
		$profile_id   = (int) get_option( 'pluginstage_active_profile_id', 0 );
		if ( $profile_id > 0 ) {
			$ps = get_post_meta( $profile_id, '_pluginstage_snapshot_id', true );
			if ( is_string( $ps ) && '' !== $ps ) {
				$snapshot_id = $ps;
			}
		}
		if ( '' === $snapshot_id ) {
			return;
		}
		$this->perform_reset( $snapshot_id );
		$this->update_next_reset_timestamp_from_schedule();
	}

	/**
	 * Backup pluginstage_* options, wp_user_roles, and administrator usermeta.
	 *
	 * The SQL import replaces the entire database; without preserving admin
	 * accounts and role definitions the current admin loses access.
	 *
	 * @return array
	 */
	public function backup_protected_options() {
		global $wpdb;

		$like = $wpdb->esc_like( 'pluginstage_' ) . '%';
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE %s",
				$like
			),
			ARRAY_A
		);
		$out = array();
		foreach ( $rows as $row ) {
			$out[ $row['option_name'] ] = array(
				'value'    => maybe_unserialize( $row['option_value'] ),
				'autoload' => $row['autoload'],
			);
		}

		$roles_key = $wpdb->prefix . 'user_roles';
		$out['__wp_user_roles'] = get_option( $roles_key );

		$admins = get_users( array( 'role' => 'administrator', 'fields' => 'all' ) );
		$admin_backup = array();
		foreach ( $admins as $admin ) {
			$caps_key = $wpdb->prefix . 'capabilities';
			$level_key = $wpdb->prefix . 'user_level';
			$admin_backup[ $admin->ID ] = array(
				'user_login'   => $admin->user_login,
				'user_pass'    => $admin->user_pass,
				'user_email'   => $admin->user_email,
				'user_nicename' => $admin->user_nicename,
				'display_name' => $admin->display_name,
				'caps'         => get_user_meta( $admin->ID, $caps_key, true ),
				'level'        => get_user_meta( $admin->ID, $level_key, true ),
			);
		}
		$out['__admin_users'] = $admin_backup;

		return $out;
	}

	/**
	 * Restore protected options, admin users, and role definitions after DB import.
	 *
	 * @param array $backup Backup from backup_protected_options().
	 */
	public function restore_protected_options( $backup ) {
		if ( ! is_array( $backup ) ) {
			return;
		}

		global $wpdb;

		if ( isset( $backup['__wp_user_roles'] ) && $backup['__wp_user_roles'] ) {
			$roles_key = $wpdb->prefix . 'user_roles';
			update_option( $roles_key, $backup['__wp_user_roles'] );
		}
		unset( $backup['__wp_user_roles'] );

		if ( ! empty( $backup['__admin_users'] ) && is_array( $backup['__admin_users'] ) ) {
			foreach ( $backup['__admin_users'] as $uid => $data ) {
				$uid = (int) $uid;
				$existing = get_user_by( 'id', $uid );
				if ( ! $existing ) {
					$wpdb->insert(
						$wpdb->users,
						array(
							'ID'              => $uid,
							'user_login'      => $data['user_login'],
							'user_pass'       => $data['user_pass'],
							'user_email'      => $data['user_email'],
							'user_nicename'   => $data['user_nicename'],
							'display_name'    => $data['display_name'],
							'user_registered' => current_time( 'mysql', true ),
						),
						array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
					);
				}
				$caps_key  = $wpdb->prefix . 'capabilities';
				$level_key = $wpdb->prefix . 'user_level';
				update_user_meta( $uid, $caps_key, $data['caps'] );
				update_user_meta( $uid, $level_key, $data['level'] );
			}
		}
		unset( $backup['__admin_users'] );

		foreach ( $backup as $name => $data ) {
			if ( ! is_string( $name ) || strpos( $name, 'pluginstage_' ) !== 0 ) {
				continue;
			}
			$value    = is_array( $data ) && isset( $data['value'] ) ? $data['value'] : $data;
			$autoload = is_array( $data ) && isset( $data['autoload'] ) ? $data['autoload'] : 'no';
			update_option( $name, $value, 'yes' === $autoload );
		}
	}

	/**
	 * Create snapshot (SQL + uploads manifest).
	 *
	 * @return string|WP_Error Snapshot ID.
	 */
	public function create_snapshot() {
		$id   = 'snap_' . wp_generate_password( 12, false, false );
		$dir  = trailingslashit( PLUGINSTAGE_SNAPSHOT_DIR ) . $id;
		$sqlf = $dir . '/dump.sql';

		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'mkdir', __( 'Could not create snapshot directory.', 'pluginstage' ) );
		}

		$dump_result = $this->export_database_to_file( $sqlf );
		if ( is_wp_error( $dump_result ) ) {
			return $dump_result;
		}

		$manifest = $this->build_uploads_manifest();
		file_put_contents( $dir . '/uploads-manifest.json', wp_json_encode( $manifest ) );
		file_put_contents(
			$dir . '/meta.json',
			wp_json_encode(
				array(
					'created'    => gmdate( 'c' ),
					'wp_version' => get_bloginfo( 'version' ),
				)
			)
		);

		$index = json_decode( (string) get_option( 'pluginstage_snapshots_index', '[]' ), true );
		if ( ! is_array( $index ) ) {
			$index = array();
		}
		$index[] = array(
			'id'      => $id,
			'created' => time(),
			'path'    => $dir,
		);
		update_option( 'pluginstage_snapshots_index', wp_json_encode( $index ), false );
		update_option( 'pluginstage_current_snapshot_id', $id, false );

		return $id;
	}

	/**
	 * Parse DB_HOST into host, port, socket.
	 *
	 * @return array{host: string, port: int|null, socket: string|null}|WP_Error
	 */
	private function parse_db_host() {
		$raw = DB_HOST;

		if ( strpos( $raw, '/' ) !== false || strpos( $raw, '.sock' ) !== false ) {
			return new WP_Error( 'socket_host', __( 'DB_HOST appears to use a Unix socket; CLI export/import is not supported. Use WP-CLI instead.', 'pluginstage' ) );
		}

		$host = $raw;
		$port = null;
		if ( strpos( $host, ':' ) !== false ) {
			$parts = explode( ':', $host, 2 );
			$host  = $parts[0];
			if ( is_numeric( $parts[1] ) ) {
				$port = (int) $parts[1];
			} else {
				return new WP_Error( 'socket_host', __( 'DB_HOST contains a non-numeric port; CLI export/import cannot be built safely. Use WP-CLI instead.', 'pluginstage' ) );
			}
		}
		return array(
			'host'   => $host,
			'port'   => $port,
			'socket' => null,
		);
	}

	/**
	 * Whether shell_exec / proc_open are available.
	 *
	 * @return bool
	 */
	private function can_shell_exec() {
		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
		return ! in_array( 'proc_open', $disabled, true ) && function_exists( 'proc_open' );
	}

	/**
	 * Whether a path points to the expected mysql / mysqldump binary.
	 *
	 * @param string $path Absolute path.
	 * @param string $name Expected base name without extension: mysql or mysqldump.
	 * @return bool
	 */
	private function is_valid_mysql_cli_path( $path, $name ) {
		if ( ! is_string( $path ) || '' === $path ) {
			return false;
		}
		$base = strtolower( pathinfo( $path, PATHINFO_FILENAME ) );
		if ( $base !== $name ) {
			return false;
		}
		return is_readable( $path );
	}

	/**
	 * Find mysql/mysqldump by walking PATH (no shell_exec; works when PHP inherits system PATH).
	 *
	 * @param string $name mysql or mysqldump.
	 * @return string Normalized path or empty.
	 */
	private function find_mysql_cli_in_path_env( $name ) {
		$exe      = ( 'Windows' === PHP_OS_FAMILY ) ? ( $name . '.exe' ) : $name;
		$path_env = getenv( 'PATH' );
		if ( ! is_string( $path_env ) || '' === $path_env ) {
			return '';
		}
		$sep = ( 'Windows' === PHP_OS_FAMILY ) ? ';' : PATH_SEPARATOR;
		foreach ( explode( $sep, $path_env ) as $dir ) {
			$dir = trim( $dir, " \t\"'" );
			if ( '' === $dir ) {
				continue;
			}
			$candidate = wp_normalize_path( trailingslashit( $dir ) . $exe );
			if ( $this->is_valid_mysql_cli_path( $candidate, $name ) ) {
				return $candidate;
			}
		}
		return '';
	}

	/**
	 * Locate mysql or mysqldump executable (Windows-friendly: PATH is often unset for PHP).
	 *
	 * Set in wp-config.php, e.g.:
	 * define( 'PLUGINSTAGE_MYSQLDUMP_PATH', 'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe' );
	 * define( 'PLUGINSTAGE_MYSQL_PATH', 'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysql.exe' );
	 *
	 * @param string $name Binary base name: mysql or mysqldump.
	 * @return string Full path, or empty if not found.
	 */
	private function resolve_mysql_cli_binary( $name ) {
		$name = strtolower( preg_replace( '/[^a-z]/', '', (string) $name ) );
		if ( ! in_array( $name, array( 'mysql', 'mysqldump' ), true ) ) {
			return '';
		}

		/**
		 * Absolute path to the mysql or mysqldump binary.
		 *
		 * @param string $path Empty when not set by filter.
		 * @param string $name  mysql or mysqldump.
		 */
		$filtered = apply_filters( 'pluginstage_mysql_cli_path', '', $name );
		$filtered = is_string( $filtered ) ? trim( $filtered ) : '';
		if ( $filtered && $this->is_valid_mysql_cli_path( $filtered, $name ) ) {
			return wp_normalize_path( $filtered );
		}

		$const = 'PLUGINSTAGE_' . strtoupper( $name ) . '_PATH';
		if ( defined( $const ) && constant( $const ) && $this->is_valid_mysql_cli_path( constant( $const ), $name ) ) {
			return wp_normalize_path( constant( $const ) );
		}

		$from_path = $this->find_mysql_cli_in_path_env( $name );
		if ( '' !== $from_path ) {
			return $from_path;
		}

		$exe = ( 'Windows' === PHP_OS_FAMILY ) ? ( $name . '.exe' ) : $name;

		if ( 'Windows' === PHP_OS_FAMILY ) {
			$candidates = array();
			$pf         = getenv( 'ProgramFiles' );
			$pf64       = getenv( 'ProgramW6432' );
			$pfx86      = getenv( 'ProgramFiles(x86)' );
			$versions   = array( '8.4', '8.3', '8.2', '8.1', '8.0', '5.7' );
			foreach ( array_filter( array( $pf64, $pf, $pfx86 ) ) as $root ) {
				if ( ! is_string( $root ) || '' === $root ) {
					continue;
				}
				$root_n = wp_normalize_path( trailingslashit( $root ) );
				foreach ( $versions as $ver ) {
					$candidates[] = $root_n . 'MySQL/MySQL Server ' . $ver . '/bin/' . $exe;
				}
				foreach ( glob( $root_n . 'MariaDB */bin/' . $exe ) ?: array() as $p ) {
					$candidates[] = $p;
				}
			}
			$candidates[] = 'C:/Program Files/MySQL/MySQL Server 8.0/bin/' . $exe;
			$candidates[] = 'C:/Program Files/MySQL/MySQL Server 8.4/bin/' . $exe;
			foreach ( glob( 'C:/Program Files/MariaDB */bin/' . $exe ) ?: array() as $p ) {
				$candidates[] = $p;
			}
			$candidates[] = 'C:/xampp/mysql/bin/' . $exe;
			$candidates[] = 'C:/MAMP/Library/bin/mysql80/bin/' . $exe;
			$candidates[] = 'C:/MAMP/bin/mysql/bin/' . $exe;

			$wamp_mysql = 'C:/wamp64/bin/mysql';
			if ( is_dir( $wamp_mysql ) ) {
				$globs = glob( $wamp_mysql . '/mysql*/bin/' . $exe );
				if ( is_array( $globs ) ) {
					$candidates = array_merge( $candidates, $globs );
				}
			}
			foreach ( array( 'C:/laragon/bin/mysql', 'D:/laragon/bin/mysql' ) as $laragon ) {
				if ( is_dir( $laragon ) ) {
					$globs = glob( $laragon . '/mysql-*/bin/' . $exe );
					if ( is_array( $globs ) ) {
						$candidates = array_merge( $candidates, $globs );
					}
				}
			}

			$localdata = getenv( 'LOCALAPPDATA' );
			if ( is_string( $localdata ) && '' !== $localdata ) {
				$local_root = wp_normalize_path( trailingslashit( $localdata ) . 'Programs/Local/resources/extraResources/binaries/mysql' );
				if ( is_dir( $local_root ) ) {
					$globs = glob( $local_root . '/*/bin/' . $exe );
					if ( is_array( $globs ) ) {
						$candidates = array_merge( $candidates, $globs );
					}
				}
			}

			foreach ( $candidates as $path ) {
				$path = wp_normalize_path( $path );
				if ( $this->is_valid_mysql_cli_path( $path, $name ) ) {
					return $path;
				}
			}

			$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
			if ( function_exists( 'shell_exec' ) && ! in_array( 'shell_exec', $disabled, true ) ) {
				$out = shell_exec( 'where ' . escapeshellarg( $exe ) . ' 2>nul' );
				if ( is_string( $out ) && '' !== trim( $out ) ) {
					$line = trim( strtok( $out, "\r\n" ) );
					if ( $line && $this->is_valid_mysql_cli_path( $line, $name ) ) {
						return wp_normalize_path( $line );
					}
				}
			}

			return '';
		}

		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
		if ( function_exists( 'shell_exec' ) && ! in_array( 'shell_exec', $disabled, true ) ) {
			$out = shell_exec( 'command -v ' . escapeshellarg( $name ) . ' 2>/dev/null' );
			if ( is_string( $out ) ) {
				$path = trim( $out );
				if ( $path && $this->is_valid_mysql_cli_path( $path, $name ) ) {
					return wp_normalize_path( $path );
				}
			}
		}

		return '';
	}

	/**
	 * Export full database to SQL using mysqli only (no mysqldump binary).
	 *
	 * Slower than mysqldump on large sites but works when client tools are missing.
	 *
	 * @param string $file Absolute path to .sql file.
	 * @return true|WP_Error
	 */
	private function export_database_via_php( $file ) {
		global $wpdb;

		if ( ! $wpdb->dbh || ! ( $wpdb->dbh instanceof mysqli ) ) {
			return new WP_Error(
				'no_mysqli_export',
				__( 'Built-in database export needs a mysqli connection. Enable mysqli in PHP or install mysqldump / WP-CLI.', 'pluginstage' )
			);
		}

		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}
		if ( function_exists( 'wp_suspend_cache_addition' ) ) {
			wp_suspend_cache_addition( true );
		}
		@set_time_limit( 0 );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$fh = fopen( $file, 'wb' );
		if ( ! $fh ) {
			if ( function_exists( 'wp_suspend_cache_addition' ) ) {
				wp_suspend_cache_addition( false );
			}
			return new WP_Error( 'export_open', __( 'Could not open snapshot SQL file for writing.', 'pluginstage' ) );
		}

		/** @var mysqli $db */
		$db = $wpdb->dbh;

		fwrite( $fh, "-- PluginStage PHP export (no mysqldump)\nSET NAMES utf8mb4;\nSET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';\nSET FOREIGN_KEY_CHECKS=0;\n" );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$tables = $wpdb->get_col( 'SHOW TABLES', 0 );
		if ( empty( $tables ) ) {
			fclose( $fh );
			if ( function_exists( 'wp_suspend_cache_addition' ) ) {
				wp_suspend_cache_addition( false );
			}
			return new WP_Error( 'export_empty', __( 'No database tables found to export.', 'pluginstage' ) );
		}

		$chunk = (int) apply_filters( 'pluginstage_php_export_rows_per_batch', 100 );
		$chunk = max( 10, min( 500, $chunk ) );

		foreach ( $tables as $table ) {
			if ( ! is_string( $table ) || ! preg_match( '/^[0-9A-Za-z_]+$/', $table ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row_create = $wpdb->get_row( 'SHOW CREATE TABLE `' . $table . '`', ARRAY_N );
			if ( ! is_array( $row_create ) || empty( $row_create[1] ) ) {
				continue;
			}

			fwrite( $fh, "\nDROP TABLE IF EXISTS `" . $table . "`;\n" . $row_create[1] . ";\n\n" );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
			if ( $total <= 0 ) {
				continue;
			}

			$offset = 0;
			while ( $offset < $total ) {
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rows = $wpdb->get_results(
					$wpdb->prepare( "SELECT * FROM `{$table}` LIMIT %d OFFSET %d", $chunk, $offset ),
					ARRAY_A
				);
				// phpcs:enable
				if ( empty( $rows ) ) {
					break;
				}

				$cols    = array_keys( $rows[0] );
				$col_sql = array();
				foreach ( $cols as $c ) {
					$col_sql[] = '`' . $this->sql_escape_identifier( $c ) . '`';
				}
				$insert_h = 'INSERT INTO `' . $table . '` (' . implode( ',', $col_sql ) . ') VALUES ';

				$values_sql = array();
				foreach ( $rows as $row ) {
					$vals = array();
					foreach ( $cols as $col ) {
						$vals[] = $this->format_sql_value_for_export( $db, array_key_exists( $col, $row ) ? $row[ $col ] : null );
					}
					$values_sql[] = '(' . implode( ',', $vals ) . ')';
				}

				fwrite( $fh, $insert_h . implode( ",\n", $values_sql ) . ";\n" );
				$offset += count( $rows );
			}
		}

		fwrite( $fh, "\nSET FOREIGN_KEY_CHECKS=1;\n" );
		fclose( $fh );

		if ( function_exists( 'wp_suspend_cache_addition' ) ) {
			wp_suspend_cache_addition( false );
		}

		if ( ! is_readable( $file ) || filesize( $file ) === 0 ) {
			return new WP_Error( 'export_empty_file', __( 'Built-in export produced an empty file.', 'pluginstage' ) );
		}

		return true;
	}

	/**
	 * Backtick-quote a SQL identifier (column name).
	 *
	 * @param string $name Identifier.
	 * @return string
	 */
	private function sql_escape_identifier( $name ) {
		return str_replace( '`', '``', (string) $name );
	}

	/**
	 * Format a cell value for INSERT (mysqli escaping).
	 *
	 * @param mysqli   $db    Connection.
	 * @param mixed    $value Cell value.
	 * @return string SQL fragment.
	 */
	private function format_sql_value_for_export( mysqli $db, $value ) {
		if ( null === $value ) {
			return 'NULL';
		}
		return "'" . mysqli_real_escape_string( $db, (string) $value ) . "'";
	}

	/**
	 * Export DB using WP-CLI, mysqldump, or PHP/mysqli fallback.
	 *
	 * @param string $file Absolute path to .sql file.
	 * @return true|WP_Error
	 */
	private function export_database_to_file( $file ) {
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
			$cmd = 'db export ' . escapeshellarg( $file );
			try {
				WP_CLI::runcommand( $cmd );
				if ( file_exists( $file ) && filesize( $file ) > 0 ) {
					return true;
				}
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Fall through to CLI mysqldump.
			}
		}

		$mysqldump_failed = false;
		$mysqldump_err    = '';

		if ( $this->can_shell_exec() ) {
			$parsed = $this->parse_db_host();
			if ( ! is_wp_error( $parsed ) ) {
				$mysqldump = $this->resolve_mysql_cli_binary( 'mysqldump' );
				if ( '' !== $mysqldump ) {
					$args = array(
						escapeshellarg( $mysqldump ),
						'--user=' . escapeshellarg( DB_USER ),
						'--host=' . escapeshellarg( $parsed['host'] ),
						'--default-character-set=utf8mb4',
						'--single-transaction',
						'--quick',
						'--lock-tables=false',
					);
					if ( $parsed['port'] ) {
						$args[] = '--port=' . escapeshellarg( (string) $parsed['port'] );
					}
					$args[] = escapeshellarg( DB_NAME );
					$cmd    = implode( ' ', $args );

					$descriptors = array(
						0 => array( 'pipe', 'r' ),
						1 => array( 'file', $file, 'w' ),
						2 => array( 'pipe', 'w' ),
					);
					$env         = array( 'MYSQL_PWD' => DB_PASSWORD );

					$proc = proc_open( $cmd, $descriptors, $pipes, null, $env );
					if ( is_resource( $proc ) ) {
						fclose( $pipes[0] );
						$stderr = stream_get_contents( $pipes[2] );
						fclose( $pipes[2] );
						$exit = proc_close( $proc );

						if ( 0 === $exit && file_exists( $file ) && filesize( $file ) > 0 ) {
							return true;
						}

						if ( file_exists( $file ) ) {
							wp_delete_file( $file );
						}
						$mysqldump_failed = true;
						$mysqldump_err    = $stderr ? sanitize_text_field( substr( $stderr, 0, 300 ) ) : '';
					}
				}
			}
		}

		$php = $this->export_database_via_php( $file );
		if ( is_wp_error( $php ) ) {
			if ( $mysqldump_failed && '' !== $mysqldump_err ) {
				return new WP_Error(
					'dump_failed',
					sprintf(
						/* translators: 1: mysqldump stderr 2: PHP export message */
						__( 'mysqldump failed (%1$s). Built-in export also failed: %2$s', 'pluginstage' ),
						$mysqldump_err,
						$php->get_error_message()
					)
				);
			}
			return $php;
		}

		return true;
	}

	/**
	 * Build uploads manifest relative paths -> sha256.
	 *
	 * @return array
	 */
	private function build_uploads_manifest() {
		$uploads = wp_upload_dir();
		$base    = trailingslashit( $uploads['basedir'] );
		$out     = array();
		if ( ! is_dir( $base ) ) {
			return $out;
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $iterator as $file ) {
			/** @var SplFileInfo $file */
			if ( ! $file->isFile() ) {
				continue;
			}
			$path = $file->getPathname();
			$rel  = ltrim( str_replace( wp_normalize_path( $base ), '', wp_normalize_path( $path ) ), '/' );
			if ( '' === $rel || strpos( $rel, '..' ) !== false ) {
				continue;
			}
			$hash = @hash_file( 'sha256', $path );
			if ( $hash ) {
				$out[ $rel ] = $hash;
			}
		}
		return $out;
	}

	/**
	 * Perform full reset from snapshot ID.
	 *
	 * @param string $snapshot_id Snapshot folder id.
	 * @return true|WP_Error
	 */
	public function perform_reset( $snapshot_id ) {
		$snapshot_id = sanitize_file_name( $snapshot_id );
		$dir         = trailingslashit( PLUGINSTAGE_SNAPSHOT_DIR ) . $snapshot_id;
		$sqlf        = $dir . '/dump.sql';
		$manifestf   = $dir . '/uploads-manifest.json';

		if ( ! is_readable( $sqlf ) ) {
			return new WP_Error( 'no_sql', __( 'Snapshot SQL file not found.', 'pluginstage' ) );
		}

		$this->terminate_demo_sessions();

		$protected = $this->backup_protected_options();

		$import = $this->import_sql_file( $sqlf );
		if ( is_wp_error( $import ) ) {
			return $import;
		}

		$this->restore_protected_options( $protected );

		if ( is_readable( $manifestf ) ) {
			$manifest = json_decode( (string) file_get_contents( $manifestf ), true );
			if ( is_array( $manifest ) ) {
				$this->prune_uploads_not_in_manifest( $manifest );
			}
		}

		$this->clear_caches();

		return true;
	}

	/**
	 * Import SQL via mysql CLI or mysqli.
	 *
	 * @param string $file Path.
	 * @return true|WP_Error
	 */
	private function import_sql_file( $file ) {
		$parsed = $this->parse_db_host();

		if ( $this->can_shell_exec() && ! is_wp_error( $parsed ) ) {
			$mysql_bin = $this->resolve_mysql_cli_binary( 'mysql' );
			if ( '' !== $mysql_bin ) {
				$args = array(
					escapeshellarg( $mysql_bin ),
					'--user=' . escapeshellarg( DB_USER ),
					'--host=' . escapeshellarg( $parsed['host'] ),
					'--default-character-set=utf8mb4',
				);
				if ( $parsed['port'] ) {
					$args[] = '--port=' . escapeshellarg( (string) $parsed['port'] );
				}
				$args[] = escapeshellarg( DB_NAME );
				$cmd    = implode( ' ', $args );

				$descriptors = array(
					0 => array( 'file', $file, 'r' ),
					1 => array( 'pipe', 'w' ),
					2 => array( 'pipe', 'w' ),
				);
				$env  = array( 'MYSQL_PWD' => DB_PASSWORD );
				$proc = proc_open( $cmd, $descriptors, $pipes, null, $env );
				if ( is_resource( $proc ) ) {
					stream_get_contents( $pipes[1] );
					fclose( $pipes[1] );
					$stderr = stream_get_contents( $pipes[2] );
					fclose( $pipes[2] );
					$exit = proc_close( $proc );
					if ( 0 === $exit ) {
						return true;
					}
					return new WP_Error(
						'import_failed',
						sprintf(
							/* translators: 1: exit code 2: stderr */
							__( 'mysql import failed (exit %1$d): %2$s', 'pluginstage' ),
							$exit,
							$stderr ? sanitize_text_field( substr( $stderr, 0, 500 ) ) : __( 'no output', 'pluginstage' )
						)
					);
				}
			}
		}

		if ( ! class_exists( 'mysqli', false ) ) {
			return new WP_Error( 'no_import', __( 'SQL import requires mysql client or mysqli.', 'pluginstage' ) );
		}

		$mysqli = $this->mysqli_connect();
		if ( is_wp_error( $mysqli ) ) {
			return $mysqli;
		}

		$sql = file_get_contents( $file );
		if ( false === $sql ) {
			return new WP_Error( 'read', __( 'Could not read SQL file.', 'pluginstage' ) );
		}

		$ok = $mysqli->multi_query( $sql );
		if ( ! $ok ) {
			$err = $mysqli->error;
			$mysqli->close();
			return new WP_Error( 'import_failed', __( 'SQL import error: ', 'pluginstage' ) . sanitize_text_field( $err ) );
		}

		$errors = array();
		do {
			if ( $result = $mysqli->store_result() ) {
				$result->free();
			}
			if ( $mysqli->error ) {
				$errors[] = sanitize_text_field( $mysqli->error );
			}
		} while ( $mysqli->more_results() && $mysqli->next_result() );

		$mysqli->close();

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'import_partial', __( 'SQL import completed with errors: ', 'pluginstage' ) . implode( '; ', array_slice( $errors, 0, 5 ) ) );
		}
		return true;
	}

	/**
	 * Connect mysqli using parse_db_host() for consistent host parsing.
	 *
	 * @return mysqli|WP_Error
	 */
	private function mysqli_connect() {
		$raw  = DB_HOST;
		$host = $raw;
		$port = 3306;

		if ( strpos( $raw, '/' ) !== false || strpos( $raw, '.sock' ) !== false ) {
			$mysqli = mysqli_init();
			if ( ! $mysqli ) {
				return new WP_Error( 'mysqli', __( 'mysqli_init failed.', 'pluginstage' ) );
			}
			$ok = @$mysqli->real_connect( 'localhost', DB_USER, DB_PASSWORD, DB_NAME, 0, $raw );
			if ( ! $ok ) {
				return new WP_Error( 'mysqli', $mysqli->connect_error ? $mysqli->connect_error : __( 'DB connection failed.', 'pluginstage' ) );
			}
			$mysqli->set_charset( 'utf8mb4' );
			return $mysqli;
		}

		if ( strpos( $host, ':' ) !== false ) {
			$parts = explode( ':', $host, 2 );
			$host  = $parts[0];
			if ( is_numeric( $parts[1] ) ) {
				$port = (int) $parts[1];
			}
		}
		$mysqli = mysqli_init();
		if ( ! $mysqli ) {
			return new WP_Error( 'mysqli', __( 'mysqli_init failed.', 'pluginstage' ) );
		}
		$ok = @$mysqli->real_connect( $host, DB_USER, DB_PASSWORD, DB_NAME, $port );
		if ( ! $ok ) {
			return new WP_Error( 'mysqli', $mysqli->connect_error ? $mysqli->connect_error : __( 'DB connection failed.', 'pluginstage' ) );
		}
		$mysqli->set_charset( 'utf8mb4' );
		return $mysqli;
	}

	/**
	 * Delete upload files not present in snapshot manifest.
	 *
	 * @param array $manifest Relative path => hash.
	 */
	private function prune_uploads_not_in_manifest( $manifest ) {
		$uploads = wp_upload_dir();
		$base    = trailingslashit( $uploads['basedir'] );
		$snap_prefix = wp_normalize_path( trailingslashit( WP_CONTENT_DIR ) . 'pluginstage-snapshots' );

		if ( ! is_dir( $base ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $file ) {
			/** @var SplFileInfo $file */
			$path = wp_normalize_path( $file->getPathname() );
			if ( strpos( $path, $snap_prefix ) === 0 ) {
				continue;
			}
			if ( ! $file->isFile() ) {
				continue;
			}
			$rel = ltrim( str_replace( wp_normalize_path( $base ), '', $path ), '/' );
			if ( ! isset( $manifest[ $rel ] ) ) {
				wp_delete_file( $file->getPathname() );
			}
		}
	}

	/**
	 * Clear transients and object cache.
	 */
	public function clear_caches() {
		global $wpdb;

		if ( function_exists( 'delete_expired_transients' ) ) {
			delete_expired_transients();
		}

		$prefixes = array(
			'_transient_pluginstage_',
			'_transient_timeout_pluginstage_',
			'_transient_pluginstage_rl_',
			'_transient_timeout_pluginstage_rl_',
			'_transient_pluginstage_abuse_',
			'_transient_timeout_pluginstage_abuse_',
		);
		$where = array();
		$args  = array();
		foreach ( $prefixes as $prefix ) {
			$where[] = 'option_name LIKE %s';
			$args[]  = $wpdb->esc_like( $prefix ) . '%';
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamic LIKE clauses built safely above.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE " . implode( ' OR ', $where ), $args ) );

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	/**
	 * End demo sessions and invalidate cookies.
	 */
	public function terminate_demo_sessions() {
		$user_ids = get_users(
			array(
				'role'   => 'demo_user',
				'fields' => 'ID',
			)
		);
		foreach ( $user_ids as $uid ) {
			$uid = (int) $uid;
			if ( $uid && function_exists( 'wp_destroy_user_sessions' ) ) {
				wp_destroy_user_sessions( $uid );
			}
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pluginstage_sessions';
		$now   = current_time( 'mysql', true );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s, ended_at = %s WHERE status = %s",
				'ended',
				$now,
				'active'
			)
		);

		$ver = (int) get_option( 'pluginstage_session_kill_version', 0 );
		update_option( 'pluginstage_session_kill_version', $ver + 1, false );

		if ( class_exists( 'PluginStage_Access' ) ) {
			PluginStage_Access::cleanup_orphaned_demo_users();
		}
	}
}
