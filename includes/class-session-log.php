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
}
