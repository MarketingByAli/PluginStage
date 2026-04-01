<?php
/**
 * Demo session logging and page touches.
 *
 * @package PluginStage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PluginStage_Session_Log
 */
class PluginStage_Session_Log {

	/**
	 * Max JSON-encoded pages per session.
	 */
	const MAX_PAGES = 200;

	/**
	 * Instance.
	 *
	 * @var PluginStage_Session_Log|null
	 */
	private static $instance = null;

	/**
	 * Instance.
	 *
	 * @return PluginStage_Session_Log
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hooks.
	 */
	public function init() {
		add_action( 'admin_footer', array( $this, 'maybe_log_page' ), 5 );
	}

	/**
	 * Append current admin screen to session log (demo users).
	 */
	public function maybe_log_page() {
		if ( ! is_user_logged_in() || ! PluginStage_Access::instance()->is_demo_user() ) {
			return;
		}
		$this->touch_current_page();
	}

	/**
	 * Update last_seen and pages list for active session row.
	 */
	public function touch_current_page() {
		$uid = get_current_user_id();
		$sid = (int) get_user_meta( $uid, 'pluginstage_session_row_id', true );
		if ( ! $sid ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pluginstage_sessions';
		$now   = current_time( 'mysql', true );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT pages_visited, status FROM {$table} WHERE id = %d", $sid ),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! $row || 'active' !== $row['status'] ) {
			return;
		}

		$pages = json_decode( (string) $row['pages_visited'], true );
		if ( ! is_array( $pages ) ) {
			$pages = array();
		}

		$slug = '';
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen ) {
				$slug = $screen->id;
			}
		}
		if ( '' === $slug ) {
			$slug = isset( $GLOBALS['pagenow'] ) ? sanitize_key( $GLOBALS['pagenow'] ) : 'unknown';
		}

		$entry = array(
			't' => time(),
			'p' => $slug,
		);
		$pages[] = $entry;
		if ( count( $pages ) > self::MAX_PAGES ) {
			$pages = array_slice( $pages, -1 * self::MAX_PAGES );
		}

		$wpdb->update(
			$table,
			array(
				'last_seen_at'  => $now,
				'pages_visited' => wp_json_encode( $pages ),
			),
			array( 'id' => $sid ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Paginated sessions for admin UI.
	 *
	 * @param int $page Page (1-based).
	 * @param int $per  Per page.
	 * @return array{rows: array, total: int}
	 */
	public function get_sessions_page( $page = 1, $per = 20 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pluginstage_sessions';
		$page  = max( 1, (int) $page );
		$per   = min( 100, max( 1, (int) $per ) );
		$off   = ( $page - 1 ) * $per;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY started_at DESC LIMIT %d OFFSET %d",
				$per,
				$off
			),
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Analytics summary data.
	 *
	 * @param int $days Number of days to look back (0 = all time).
	 * @return array
	 */
	public function get_analytics( $days = 30 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pluginstage_sessions';

		$where = '';
		$args  = array();
		if ( $days > 0 ) {
			$where = ' WHERE started_at >= %s';
			$args[] = gmdate( 'Y-m-d 00:00:00', strtotime( "-{$days} days" ) );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$sql_base = "FROM {$table}" . $where;
		$prep = $where ? $wpdb->prepare( $sql_base, $args ) : $sql_base;

		$total   = (int) $wpdb->get_var( "SELECT COUNT(*) " . $prep );
		$active  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s" . ( $where ? ' AND started_at >= %s' : '' ), ...array_merge( array( 'active' ), $args ) ) );
		$unique_ips = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT ip) " . $prep );

		$avg_duration_sql = "SELECT AVG(TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, last_seen_at))) " . $prep;
		$avg_duration = (float) $wpdb->get_var( $avg_duration_sql );

		$avg_pages_sql = "SELECT id, pages_visited " . $prep . " ORDER BY started_at DESC LIMIT 500";
		$page_rows = $wpdb->get_results( $avg_pages_sql, ARRAY_A );
		$total_pages = 0;
		$page_freq   = array();
		$sessions_with_pages = 0;
		if ( is_array( $page_rows ) ) {
			foreach ( $page_rows as $pr ) {
				$pv = json_decode( (string) $pr['pages_visited'], true );
				if ( is_array( $pv ) && ! empty( $pv ) ) {
					$sessions_with_pages++;
					$total_pages += count( $pv );
					foreach ( $pv as $entry ) {
						$slug = isset( $entry['p'] ) ? (string) $entry['p'] : 'unknown';
						if ( ! isset( $page_freq[ $slug ] ) ) {
							$page_freq[ $slug ] = 0;
						}
						$page_freq[ $slug ]++;
					}
				}
			}
		}
		arsort( $page_freq );

		$daily_sql = "SELECT DATE(started_at) AS day, COUNT(*) AS cnt " . $prep . " GROUP BY DATE(started_at) ORDER BY day ASC";
		$daily_rows = $wpdb->get_results( $daily_sql, ARRAY_A );

		$profile_sql = "SELECT profile_id, COUNT(*) AS cnt " . $prep . " GROUP BY profile_id ORDER BY cnt DESC";
		$profile_rows = $wpdb->get_results( $profile_sql, ARRAY_A );

		$hourly_sql = "SELECT HOUR(started_at) AS hr, COUNT(*) AS cnt " . $prep . " GROUP BY HOUR(started_at) ORDER BY hr ASC";
		$hourly_rows = $wpdb->get_results( $hourly_sql, ARRAY_A );

		$browser_map = array();
		$ua_sql = "SELECT user_agent " . $prep;
		$ua_rows = $wpdb->get_col( $ua_sql );
		if ( is_array( $ua_rows ) ) {
			foreach ( $ua_rows as $ua ) {
				$browser_map[ $this->parse_browser( (string) $ua ) ][] = 1;
			}
		}
		$browser_stats = array();
		foreach ( $browser_map as $name => $hits ) {
			$browser_stats[ $name ] = count( $hits );
		}
		arsort( $browser_stats );

		$top_ips_sql = "SELECT ip, COUNT(*) AS cnt " . $prep . " GROUP BY ip ORDER BY cnt DESC LIMIT 10";
		$top_ips = $wpdb->get_results( $top_ips_sql, ARRAY_A );

		$country_map = array();
		if ( is_array( $top_ips ) ) {
			foreach ( $top_ips as $tip ) {
				$country_map[ $tip['ip'] ] = $tip['cnt'];
			}
		}
		// phpcs:enable

		return array(
			'total_sessions'     => $total,
			'active_now'         => $active,
			'unique_ips'         => $unique_ips,
			'avg_duration_sec'   => round( $avg_duration ),
			'avg_pages'          => $sessions_with_pages > 0 ? round( $total_pages / $sessions_with_pages, 1 ) : 0,
			'total_pageviews'    => $total_pages,
			'top_pages'          => array_slice( $page_freq, 0, 15, true ),
			'daily'              => is_array( $daily_rows ) ? $daily_rows : array(),
			'by_profile'         => is_array( $profile_rows ) ? $profile_rows : array(),
			'by_hour'            => is_array( $hourly_rows ) ? $hourly_rows : array(),
			'browsers'           => array_slice( $browser_stats, 0, 10, true ),
			'top_ips'            => is_array( $top_ips ) ? $top_ips : array(),
			'days'               => $days,
		);
	}

	/**
	 * Simple browser name from user-agent string.
	 *
	 * @param string $ua User agent.
	 * @return string
	 */
	private function parse_browser( $ua ) {
		if ( '' === $ua ) {
			return 'Unknown';
		}
		if ( stripos( $ua, 'Edg/' ) !== false ) {
			return 'Edge';
		}
		if ( stripos( $ua, 'OPR/' ) !== false || stripos( $ua, 'Opera' ) !== false ) {
			return 'Opera';
		}
		if ( stripos( $ua, 'Chrome/' ) !== false ) {
			return 'Chrome';
		}
		if ( stripos( $ua, 'Firefox/' ) !== false ) {
			return 'Firefox';
		}
		if ( stripos( $ua, 'Safari/' ) !== false ) {
			return 'Safari';
		}
		if ( stripos( $ua, 'MSIE' ) !== false || stripos( $ua, 'Trident/' ) !== false ) {
			return 'IE';
		}
		return 'Other';
	}
}
