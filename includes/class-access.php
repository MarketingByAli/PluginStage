<?php
/**
 * Magic login, demo role, session limits, admin restrictions.
 *
 * @package PluginStage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PluginStage_Access
 */
class PluginStage_Access {

	/**
	 * Query argument for magic login.
	 */
	const QUERY_ARG = 'pluginstage_magic';

	/**
	 * Singleton instance.
	 *
	 * @var PluginStage_Access|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return PluginStage_Access
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'init', array( $this, 'maybe_magic_login' ), 1 );
		add_action( 'admin_init', array( $this, 'enforce_demo_restrictions' ), 1 );
		add_action( 'admin_init', array( $this, 'maybe_idle_logout' ), 2 );
		add_filter( 'user_has_cap', array( $this, 'filter_demo_caps' ), 10, 4 );
		add_filter( 'user_has_cap', array( $this, 'filter_demo_plugin_manage_cap' ), 20, 4 );
		add_action( 'wp_login', array( $this, 'on_wp_login' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_heartbeat' ) );
		add_filter( 'heartbeat_received', array( $this, 'heartbeat_received' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'register_restricted_page' ), 99 );
		add_action( 'admin_menu', array( $this, 'strip_demo_plugin_menus' ), 99999 );
	}

	/**
	 * Register hidden admin page for restricted notice.
	 *
	 * Avoid parent slug null (fragile in PHP 8+). Register under Dashboard, then remove
	 * the submenu row so nothing extra appears in the sidebar.
	 */
	public function register_restricted_page() {
		add_submenu_page(
			'index.php',
			__( 'Demo restriction', 'pluginstage' ),
			' ',
			'read',
			'pluginstage-restricted',
			array( $this, 'render_restricted_page' )
		);
		remove_submenu_page( 'index.php', 'pluginstage-restricted' );
	}

	/**
	 * Render restricted template.
	 */
	public function render_restricted_page() {
		if ( ! $this->is_demo_user() ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
		require PLUGINSTAGE_PATH . 'templates/restricted-page.php';
		exit;
	}

	/**
	 * Whether current user is the restricted demo account (not admins).
	 *
	 * Administrators and super admins are never treated as demo users, even if
	 * capabilities were corrupted to include the demo_user role key.
	 *
	 * @return bool
	 */
	public function is_demo_user() {
		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return false;
		}
		// Only the dedicated demo role, as the user's single role (avoids bad multi-role meta).
		$roles = array_values( array_filter( (array) $user->roles ) );
		return 1 === count( $roles ) && 'demo_user' === $roles[0];
	}

	/**
	 * Hash raw token for storage.
	 *
	 * @param string $raw Raw token.
	 * @return string
	 */
	public static function hash_token( $raw ) {
		return hash_hmac( 'sha256', $raw, wp_salt( 'pluginstage_tokens' ) );
	}

	/**
	 * Client IP.
	 *
	 * @return string
	 */
	public static function get_request_ip() {
		if ( function_exists( 'rest_get_ip_address' ) ) {
			$ip = rest_get_ip_address();
			if ( $ip ) {
				return sanitize_text_field( $ip );
			}
		}
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/**
	 * Count active demo sessions (recent last_seen).
	 *
	 * @return int
	 */
	public function count_active_demo_sessions() {
		global $wpdb;
		$table  = $wpdb->prefix . 'pluginstage_sessions';
		$stale  = max( 1, (int) get_option( 'pluginstage_session_stale_minutes', 5 ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $stale * MINUTE_IN_SECONDS ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = %s AND last_seen_at >= %s",
				'active',
				$cutoff
			)
		);
		// phpcs:enable
		return $count;
	}

	/**
	 * Whether a new demo session is allowed.
	 *
	 * @return bool
	 */
	public function can_start_new_demo_session() {
		$max = (int) get_option( 'pluginstage_max_concurrent_sessions', 5 );
		if ( $max <= 0 ) {
			return true;
		}
		return $this->count_active_demo_sessions() < $max;
	}

	/**
	 * Create magic token and return raw token (show once) and full URL.
	 *
	 * @param int $profile_id Profile post ID (0 = default).
	 * @return array{0: string|false, 1: string|false} raw token and URL, or false on failure.
	 */
	public function create_magic_token( $profile_id = 0 ) {
		global $wpdb;

		if ( ! $this->can_start_new_demo_session() ) {
			return array( false, false );
		}

		$never_expire = (int) get_option( 'pluginstage_magic_token_never_expire', 0 );
		$raw          = wp_generate_password( 48, false, false );
		$hash         = self::hash_token( $raw );
		if ( $never_expire ) {
			$expires = '9999-12-31 23:59:59';
		} else {
			$minutes = max( 1, (int) get_option( 'pluginstage_magic_token_minutes', 60 ) );
			$expires = gmdate( 'Y-m-d H:i:s', time() + ( $minutes * MINUTE_IN_SECONDS ) );
		}
		$now     = current_time( 'mysql', true );
		$ip      = self::get_request_ip();

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'pluginstage_tokens',
			array(
				'token_hash' => $hash,
				'token_raw'  => $never_expire ? $raw : '',
				'user_id'    => 0,
				'profile_id' => (int) $profile_id,
				'expires_at' => $expires,
				'created_at' => $now,
				'ip_created' => $ip,
				'revoked'    => 0,
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d' )
		);

		if ( ! $inserted ) {
			return array( false, false );
		}

		$url = add_query_arg( self::QUERY_ARG, rawurlencode( $raw ), home_url( '/' ) );
		return array( $raw, $url );
	}

	/**
	 * Magic login on front end.
	 */
	public function maybe_magic_login() {
		if ( is_admin() || ! isset( $_GET[ self::QUERY_ARG ] ) ) {
			return;
		}

		$raw = isset( $_GET[ self::QUERY_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::QUERY_ARG ] ) ) : '';
		if ( '' === $raw ) {
			return;
		}

		$ip = self::get_request_ip();
		if ( class_exists( 'PluginStage_Security' ) && PluginStage_Security::instance()->is_ip_banned( $ip ) ) {
			wp_die( esc_html__( 'Access denied.', 'pluginstage' ), esc_html__( 'Demo', 'pluginstage' ), array( 'response' => 403 ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pluginstage_tokens';
		$hash  = self::hash_token( $raw );
		$now_gmt = current_time( 'mysql', true );

		// Permanent tokens are reusable (skip used_at check); normal tokens are single-use.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE token_hash = %s AND revoked = 0 AND expires_at > %s ORDER BY id DESC LIMIT 1",
				$hash,
				$now_gmt
			),
			ARRAY_A
		);
		// phpcs:enable

		$token_is_permanent = $row && '9999-12-31 23:59:59' === $row['expires_at'];

		if ( ! $row || ( ! $token_is_permanent && null !== $row['used_at'] ) ) {
			if ( class_exists( 'PluginStage_Security' ) ) {
				PluginStage_Security::instance()->record_abuse_event( $ip );
			}
			wp_die( esc_html__( 'This demo link is invalid or has expired.', 'pluginstage' ), esc_html__( 'Demo', 'pluginstage' ), array( 'response' => 403 ) );
		}

		if ( ! $this->can_start_new_demo_session() ) {
			wp_die( esc_html__( 'The maximum number of demo sessions is reached. Please try again later.', 'pluginstage' ), esc_html__( 'Demo', 'pluginstage' ), array( 'response' => 429 ) );
		}

		if ( ! $token_is_permanent ) {
			// Atomically claim this token; if another request raced us, affected rows = 0.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$claimed = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET used_at = %s WHERE id = %d AND used_at IS NULL",
					$now_gmt,
					(int) $row['id']
				)
			);
			if ( ! $claimed ) {
				wp_die( esc_html__( 'This demo link has already been used.', 'pluginstage' ), esc_html__( 'Demo', 'pluginstage' ), array( 'response' => 403 ) );
			}
		}

		if ( ! $this->can_start_new_demo_session() ) {
			if ( ! $token_is_permanent ) {
				$wpdb->update(
					$table,
					array( 'used_at' => null ),
					array( 'id' => (int) $row['id'] ),
					array( '%s' ),
					array( '%d' )
				);
			}
			wp_die( esc_html__( 'The maximum number of demo sessions is reached. Please try again later.', 'pluginstage' ), esc_html__( 'Demo', 'pluginstage' ), array( 'response' => 429 ) );
		}

		$user = $this->get_or_create_demo_user();
		if ( is_wp_error( $user ) || ! $user ) {
			if ( ! $token_is_permanent ) {
				$wpdb->update(
					$table,
					array( 'used_at' => null ),
					array( 'id' => (int) $row['id'] ),
					array( '%s' ),
					array( '%d' )
				);
			}
			wp_die( esc_html__( 'Could not start the demo session.', 'pluginstage' ), esc_html__( 'Demo', 'pluginstage' ), array( 'response' => 500 ) );
		}

		$wpdb->update(
			$table,
			array( 'user_id' => $user->ID ),
			array( 'id' => (int) $row['id'] ),
			array( '%d' ),
			array( '%d' )
		);

		$profile_id = (int) $row['profile_id'];
		update_user_meta( $user->ID, 'pluginstage_profile_id', $profile_id );
		update_user_meta( $user->ID, 'pluginstage_last_activity', time() );
		update_user_meta( $user->ID, 'pluginstage_session_kill_seen', (int) get_option( 'pluginstage_session_kill_version', 0 ) );

		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user );

		$session_id = $this->start_session_log( $user->ID, $profile_id, $ip );
		update_user_meta( $user->ID, 'pluginstage_session_row_id', $session_id );

		wp_safe_redirect( admin_url() );
		exit;
	}

	/**
	 * Login prefix for per-session demo accounts.
	 */
	const USER_PREFIX = 'pluginstage_se_';

	/**
	 * Create a fresh WP user with the demo_user role for this session.
	 *
	 * Each magic-link login gets its own account so user meta and session
	 * state never collide across concurrent visitors.
	 *
	 * @return WP_User|WP_Error
	 */
	private function get_or_create_demo_user() {
		$attempts = 0;
		while ( $attempts < 5 ) {
			$suffix = wp_generate_password( 8, false, false );
			$login  = self::USER_PREFIX . $suffix;
			if ( ! username_exists( $login ) ) {
				break;
			}
			++$attempts;
		}

		$pass = wp_generate_password( 32, true, true );
		$id   = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => $pass,
				'role'       => 'demo_user',
			)
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return get_user_by( 'id', $id );
	}

	/**
	 * Delete orphaned demo_user accounts that have no active session.
	 *
	 * Called from reset/terminate flows and optionally from cron to keep
	 * the users table bounded.
	 */
	public static function cleanup_orphaned_demo_users() {
		global $wpdb;
		$table   = $wpdb->prefix . 'pluginstage_sessions';
		$prefix  = self::USER_PREFIX;
		$like    = $wpdb->esc_like( $prefix ) . '%';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$active_user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT user_id FROM {$table} WHERE status = %s",
				'active'
			)
		);
		$active_user_ids = array_map( 'intval', $active_user_ids );

		$users = get_users(
			array(
				'role'       => 'demo_user',
				'login__in'  => array(),
				'search'     => $prefix . '*',
				'fields'     => 'ID',
				'number'     => 200,
			)
		);

		foreach ( $users as $uid ) {
			$uid = (int) $uid;
			if ( in_array( $uid, $active_user_ids, true ) ) {
				continue;
			}
			wp_delete_user( $uid );
		}
	}

	/**
	 * Insert session log row.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $profile_id Profile ID.
	 * @param string $ip IP.
	 * @return int Session row ID.
	 */
	private function start_session_log( $user_id, $profile_id, $ip ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pluginstage_sessions';
		$now   = current_time( 'mysql', true );
		$ua    = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$wpdb->insert(
			$table,
			array(
				'user_id'        => $user_id,
				'profile_id'     => $profile_id,
				'ip'             => $ip,
				'user_agent'     => $ua,
				'started_at'     => $now,
				'ended_at'       => null,
				'last_seen_at'   => $now,
				'pages_visited'  => wp_json_encode( array() ),
				'status'         => 'active',
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Track login for non-magic flows (unused for demo).
	 *
	 * @param string  $user_login Login.
	 * @param WP_User $user User.
	 */
	public function on_wp_login( $user_login, $user ) {
		unset( $user_login );
		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return;
		}
		$roles = array_values( array_filter( (array) $user->roles ) );
		if ( 1 !== count( $roles ) || 'demo_user' !== $roles[0] ) {
			return;
		}
		update_user_meta( $user->ID, 'pluginstage_last_activity', time() );
	}

	/**
	 * Idle logout for demo users in admin.
	 */
	public function maybe_idle_logout() {
		if ( ! $this->is_demo_user() ) {
			return;
		}

		$idle_min = max( 1, (int) get_option( 'pluginstage_session_idle_minutes', 30 ) );
		$last     = (int) get_user_meta( get_current_user_id(), 'pluginstage_last_activity', true );
		if ( $last && ( time() - $last ) > ( $idle_min * MINUTE_IN_SECONDS ) ) {
			wp_logout();
			wp_safe_redirect( add_query_arg( 'pluginstage_demo_ended', '1', home_url( '/' ) ) );
			exit;
		}
	}

	/**
	 * Block sensitive admin screens for demo users.
	 */
	public function enforce_demo_restrictions() {
		if ( ! $this->is_demo_user() ) {
			return;
		}

		if ( class_exists( 'PluginStage_Reset' ) ) {
			$ver = (int) get_option( 'pluginstage_session_kill_version', 0 );
			$uver = (int) get_user_meta( get_current_user_id(), 'pluginstage_session_kill_seen', true );
			if ( $ver !== $uver ) {
				update_user_meta( get_current_user_id(), 'pluginstage_session_kill_seen', $ver );
				wp_logout();
				wp_safe_redirect( home_url( '/' ) );
				exit;
			}
		}

		$pagenow = $GLOBALS['pagenow'] ?? '';
		$page    = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'pluginstage-restricted' === $page ) {
			return;
		}

		$blocked_scripts = array(
			'options-general.php',
			'options-permalink.php',
			'users.php',
			'user-new.php',
			'themes.php',
			'theme-install.php',
			'plugins.php',
			'plugin-install.php',
			'plugin-editor.php',
			'tools.php',
			'export.php',
			'import.php',
			'update-core.php',
		);

		if ( in_array( $pagenow, $blocked_scripts, true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=pluginstage-restricted' ) );
			exit;
		}

		if ( 'user-edit.php' === $pagenow ) {
			$edit_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
			if ( $edit_id && get_current_user_id() !== $edit_id ) {
				wp_safe_redirect( admin_url( 'admin.php?page=pluginstage-restricted' ) );
				exit;
			}
		}

		if ( 'customize.php' === $pagenow ) {
			wp_safe_redirect( admin_url( 'admin.php?page=pluginstage-restricted' ) );
			exit;
		}

		if ( 'options.php' === $pagenow ) {
			wp_safe_redirect( admin_url( 'admin.php?page=pluginstage-restricted' ) );
			exit;
		}
	}

	/**
	 * Capabilities that must never be granted to demo_user, regardless of
	 * profile "allowed" overrides. Applied as the final enforcement step.
	 *
	 * @var string[]
	 */
	private static $never_grant = array(
		'manage_options',
		'install_plugins',
		'activate_plugins',
		'edit_plugins',
		'delete_plugins',
		'switch_themes',
		'edit_themes',
		'edit_theme_options',
		'install_themes',
		'delete_themes',
		'edit_users',
		'delete_users',
		'create_users',
		'list_users',
		'promote_users',
		'manage_categories',
		'export',
		'import',
		'update_core',
		'update_plugins',
		'update_themes',
	);

	public function filter_demo_caps( $allcaps, $caps, $args, $user ) {
		unset( $caps, $args );
		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return $allcaps;
		}
		$roles = array_values( array_filter( (array) $user->roles ) );
		if ( 1 !== count( $roles ) || 'demo_user' !== $roles[0] ) {
			return $allcaps;
		}

		$profile_id = (int) get_user_meta( $user->ID, 'pluginstage_profile_id', true );

		if ( $profile_id > 0 ) {
			$preset_keys = get_post_meta( $profile_id, '_pluginstage_demo_presets', true );
			if ( is_array( $preset_keys ) && class_exists( 'PluginStage_Profiles' ) ) {
				$preset_defs = PluginStage_Profiles::demo_preset_caps();
				foreach ( $preset_keys as $pkey ) {
					$pkey = sanitize_key( $pkey );
					if ( ! $pkey || ! isset( $preset_defs[ $pkey ] ) ) {
						continue;
					}
					foreach ( $preset_defs[ $pkey ] as $cap ) {
						$cap = sanitize_key( $cap );
						if ( $cap ) {
							$allcaps[ $cap ] = true;
						}
					}
				}
			}

			$extra_allowed = get_post_meta( $profile_id, '_pluginstage_allowed_caps', true );
			if ( is_array( $extra_allowed ) ) {
				foreach ( $extra_allowed as $cap ) {
					$cap = sanitize_key( $cap );
					if ( $cap ) {
						$allcaps[ $cap ] = true;
					}
				}
			}
			$extra_blocked = get_post_meta( $profile_id, '_pluginstage_blocked_caps', true );
			if ( is_array( $extra_blocked ) ) {
				foreach ( $extra_blocked as $cap ) {
					$cap = sanitize_key( $cap );
					if ( $cap ) {
						$allcaps[ $cap ] = false;
					}
				}
			}
		}

		foreach ( self::$never_grant as $cap ) {
			$allcaps[ $cap ] = false;
		}

		return $allcaps;
	}

	/**
	 * Grant manage_options only for allowlisted plugin admin screens / AJAX (runs after filter_demo_caps).
	 *
	 * @param bool[]   $allcaps All caps for user.
	 * @param string[] $caps    Caps being checked.
	 * @param array    $args    Extra args.
	 * @param WP_User  $user    User.
	 * @return bool[]
	 */
	public function filter_demo_plugin_manage_cap( $allcaps, $caps, $args, $user ) {
		unset( $args );
		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return $allcaps;
		}
		if ( ! $this->user_is_demo_role( $user ) ) {
			return $allcaps;
		}
		if ( ! in_array( 'manage_options', (array) $caps, true ) ) {
			return $allcaps;
		}

		$plugins = $this->get_demo_allowed_plugin_files_for_user( $user );
		if ( empty( $plugins ) || ! class_exists( 'PluginStage_Profiles' ) ) {
			return $allcaps;
		}

		/*
		 * Full wp-admin (not AJAX): grant manage_options so WordPress can register plugin menus in
		 * wp-admin/includes/menu.php (it checks caps after admin_menu). We remove disallowed menus in
		 * strip_demo_plugin_menus(); URLs like Settings are still blocked in enforce_demo_restrictions().
		 * AJAX: only grant when the action matches an allowed plugin prefix (never all of admin-ajax).
		 */
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			$allow = $this->demo_ajax_matches_allowed_plugins( $plugins );
		} else {
			$allow = true;
		}

		if ( ! $allow ) {
			return $allcaps;
		}

		$allcaps['manage_options'] = true;
		return $allcaps;
	}

	/**
	 * Whether the WP_User has exactly the demo_user role.
	 *
	 * @param WP_User $user User.
	 * @return bool
	 */
	private function user_is_demo_role( $user ) {
		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}
		$roles = array_values( array_filter( (array) $user->roles ) );
		return 1 === count( $roles ) && 'demo_user' === $roles[0];
	}

	/**
	 * Plugin files allowed for this demo profile (post meta on profile).
	 *
	 * @param WP_User $user User.
	 * @return string[]
	 */
	private function get_demo_allowed_plugin_files_for_user( $user ) {
		$profile_id = (int) get_user_meta( $user->ID, 'pluginstage_profile_id', true );
		if ( $profile_id <= 0 ) {
			return array();
		}
		$raw = get_post_meta( $profile_id, '_pluginstage_demo_plugin_access', true );
		return is_array( $raw ) ? array_values( array_filter( array_map( 'sanitize_text_field', $raw ) ) ) : array();
	}

	/**
	 * AJAX action allowed if it matches a prefix derived from an allowed plugin folder.
	 *
	 * @param string[] $plugin_files Allowed plugin paths.
	 * @return bool
	 */
	private function demo_ajax_matches_allowed_plugins( array $plugin_files ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		if ( '' === $action ) {
			return false;
		}
		foreach ( $plugin_files as $p ) {
			foreach ( PluginStage_Profiles::ajax_prefixes_for_plugin_file( $p ) as $prefix ) {
				if ( $prefix && str_starts_with( $action, $prefix ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Remove manage_options menus that are not part of allowlisted plugins (sidebar cleanup).
	 */
	public function strip_demo_plugin_menus() {
		if ( ! $this->is_demo_user() || ! class_exists( 'PluginStage_Profiles' ) ) {
			return;
		}

		$user    = wp_get_current_user();
		$plugins = $this->get_demo_allowed_plugin_files_for_user( $user );
		if ( empty( $plugins ) ) {
			return;
		}

		$folders = array();
		foreach ( $plugins as $file ) {
			$d = dirname( $file );
			if ( $d && '.' !== $d ) {
				$folders[] = strtolower( $d );
			}
		}
		$folders = array_unique( $folders );
		if ( empty( $folders ) ) {
			return;
		}

		global $menu, $submenu;

		foreach ( (array) $menu as $item ) {
			if ( empty( $item[1] ) || empty( $item[2] ) ) {
				continue;
			}
			if ( 'manage_options' !== $item[1] ) {
				continue;
			}
			$slug = $item[2];
			if ( PluginStage_Profiles::menu_slug_matches_any_allowed_folder( $slug, $folders ) ) {
				continue;
			}
			remove_menu_page( $slug );
		}

		foreach ( (array) $submenu as $parent => $items ) {
			foreach ( (array) $items as $item ) {
				if ( empty( $item[1] ) || empty( $item[2] ) ) {
					continue;
				}
				if ( 'manage_options' !== $item[1] ) {
					continue;
				}
				$slug = $item[2];
				if ( PluginStage_Profiles::menu_slug_matches_any_allowed_folder( $slug, $folders ) ) {
					continue;
				}
				remove_submenu_page( $parent, $slug );
			}
		}
	}

	/**
	 * Bump activity via Heartbeat for demo users.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_heartbeat( $hook ) {
		if ( ! $this->is_demo_user() ) {
			return;
		}
		wp_enqueue_script( 'heartbeat' );
		$handle = 'pluginstage-admin-heartbeat';
		wp_register_script( $handle, false, array( 'jquery', 'heartbeat' ), PLUGINSTAGE_VERSION, true );
		wp_enqueue_script( $handle );
		wp_add_inline_script(
			$handle,
			'jQuery(function($){$(document).on("heartbeat-send",function(e,data){data.pluginstage_demo=1;});});'
		);
	}

	/**
	 * Heartbeat: refresh demo activity and page log.
	 *
	 * @param array $response Response.
	 * @param array $data     Data.
	 * @return array
	 */
	public function heartbeat_received( $response, $data ) {
		if ( empty( $data['pluginstage_demo'] ) || ! is_user_logged_in() || ! $this->is_demo_user() ) {
			return $response;
		}
		update_user_meta( get_current_user_id(), 'pluginstage_last_activity', time() );
		if ( class_exists( 'PluginStage_Session_Log' ) ) {
			PluginStage_Session_Log::instance()->touch_current_page();
		}
		return $response;
	}
}
