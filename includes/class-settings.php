<?php
/**
 * Settings page and admin POST handlers.
 *
 * @package PluginStage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PluginStage_Settings
 */
class PluginStage_Settings {

	/**
	 * Menu slug.
	 */
	const SLUG = 'pluginstage';

	/**
	 * Instance.
	 *
	 * @var PluginStage_Settings|null
	 */
	private static $instance = null;

	/**
	 * Instance.
	 *
	 * @return PluginStage_Settings
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Tabs.
	 *
	 * @return string[]
	 */
	public static function tabs() {
		return array(
			'general'   => __( 'General', 'pluginstage' ),
			'access'    => __( 'Access', 'pluginstage' ),
			'analytics' => __( 'Analytics', 'pluginstage' ),
			'reset'     => __( 'Reset', 'pluginstage' ),
			'branding'  => __( 'Branding', 'pluginstage' ),
			'tours'     => __( 'Tours', 'pluginstage' ),
			'security'  => __( 'Security', 'pluginstage' ),
			'profiles'  => __( 'Profiles', 'pluginstage' ),
		);
	}

	/**
	 * Init.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 5 );
		add_filter( 'plugin_action_links_' . PLUGINSTAGE_BASENAME, array( $this, 'plugin_action_links' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_pluginstage_save_tab', array( $this, 'handle_save_tab' ) );
		add_action( 'admin_post_pluginstage_create_snapshot', array( $this, 'handle_create_snapshot' ) );
		add_action( 'admin_post_pluginstage_run_reset', array( $this, 'handle_run_reset' ) );
		add_action( 'admin_post_pluginstage_generate_magic', array( $this, 'handle_generate_magic' ) );
		add_action( 'admin_post_pluginstage_set_active_profile', array( $this, 'handle_set_active_profile' ) );
		add_action( 'admin_post_pluginstage_delete_snapshot', array( $this, 'handle_delete_snapshot' ) );
		add_action( 'admin_post_pluginstage_revoke_token', array( $this, 'handle_revoke_token' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Admin notices from transients.
	 */
	public function admin_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$msg = get_transient( 'pluginstage_admin_notice_' . get_current_user_id() );
		if ( ! $msg || ! is_array( $msg ) ) {
			return;
		}
		delete_transient( 'pluginstage_admin_notice_' . get_current_user_id() );
		$class = isset( $msg['type'] ) && 'error' === $msg['type'] ? 'notice-error' : 'notice-success';
		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $class ),
			wp_kses_post( $msg['text'] )
		);
	}

	/**
	 * Flash notice.
	 *
	 * @param string $text Message.
	 * @param string $type success|error.
	 */
	public static function flash_notice( $text, $type = 'success' ) {
		set_transient(
			'pluginstage_admin_notice_' . get_current_user_id(),
			array(
				'text' => $text,
				'type' => $type,
			),
			60
		);
	}

	/**
	 * Register top-level menu (before CPT submenu).
	 *
	 * Do not register a second screen with the same $menu_slug (e.g. add_options_page),
	 * or WordPress overwrites $admin_page_hooks and core Settings screens can break.
	 *
	 * Do not pass a numeric menu position: core reserves 80 for Settings, 59/60/65/70/75/85/99
	 * for other top-level items; a fixed number can overwrite them or collide with other plugins.
	 * Omitting position appends to the menu (WordPress handles ordering safely).
	 */
	public function register_menu() {
		add_menu_page(
			__( 'PluginStage', 'pluginstage' ),
			__( 'PluginStage', 'pluginstage' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render_page' ),
			'dashicons-performance'
		);
	}

	/**
	 * Link to settings from Plugins list.
	 *
	 * @param string[] $links Action links.
	 * @return string[]
	 */
	public function plugin_action_links( $links ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $links;
		}
		$url   = admin_url( 'admin.php?page=' . self::SLUG );
		$links = (array) $links;
		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'pluginstage' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Register settings (whitelist for reading in templates).
	 */
	public function register_settings() {
		$opts = array(
			'pluginstage_public_name',
			'pluginstage_admin_alert_email',
			'pluginstage_magic_token_minutes',
			'pluginstage_magic_token_never_expire',
			'pluginstage_session_idle_minutes',
			'pluginstage_max_concurrent_sessions',
			'pluginstage_session_stale_minutes',
			'pluginstage_reset_schedule',
			'pluginstage_countdown_enabled',
			'pluginstage_banner_message',
			'pluginstage_banner_bg',
			'pluginstage_banner_text',
			'pluginstage_banner_dismissible',
			'pluginstage_admin_bar_logo_url',
			'pluginstage_admin_bar_links',
			'pluginstage_admin_bar_hide_nodes',
			'pluginstage_footer_enabled',
			'pluginstage_footer_tagline',
			'pluginstage_footer_logo_url',
			'pluginstage_footer_social',
			'pluginstage_footer_purchase_url',
			'pluginstage_cta_label',
			'pluginstage_cta_url',
			'pluginstage_cta_bg',
			'pluginstage_cta_position',
			'pluginstage_upload_max_bytes',
			'pluginstage_upload_mimes',
			'pluginstage_banned_ips',
			'pluginstage_auto_ban_threshold',
			'pluginstage_uninstall_delete_data',
			'pluginstage_uninstall_delete_snapshots',
			'pluginstage_tour_enabled_global',
			'pluginstage_tour_steps_global',
		);
		foreach ( $opts as $opt ) {
			register_setting( 'pluginstage_all', $opt );
		}
	}

	/**
	 * Render main settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'pluginstage' ) );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		$tabs = self::tabs();
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'general';
		}

		echo '<div class="wrap"><h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$url = add_query_arg(
				array(
					'page' => self::SLUG,
					'tab'  => $slug,
				),
				admin_url( 'admin.php' )
			);
			$cls = $slug === $tab ? ' nav-tab-active' : '';
			echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . esc_attr( $cls ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</h2>';

		$file = PLUGINSTAGE_PATH . 'templates/settings/tab-' . $tab . '.php';
		if ( is_readable( $file ) ) {
			include $file;
		} else {
			echo '<p>' . esc_html__( 'Tab not found.', 'pluginstage' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Save tab handler.
	 */
	public function handle_save_tab() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'pluginstage' ) );
		}
		$tab = isset( $_POST['pluginstage_tab'] ) ? sanitize_key( wp_unslash( $_POST['pluginstage_tab'] ) ) : '';
		check_admin_referer( 'pluginstage_save_' . $tab );

		switch ( $tab ) {
			case 'general':
				update_option( 'pluginstage_public_name', sanitize_text_field( wp_unslash( $_POST['pluginstage_public_name'] ?? '' ) ) );
				update_option( 'pluginstage_admin_alert_email', sanitize_email( wp_unslash( $_POST['pluginstage_admin_alert_email'] ?? '' ) ) );
				update_option( 'pluginstage_uninstall_delete_data', isset( $_POST['pluginstage_uninstall_delete_data'] ) ? 1 : 0 );
				update_option( 'pluginstage_uninstall_delete_snapshots', isset( $_POST['pluginstage_uninstall_delete_snapshots'] ) ? 1 : 0 );
				break;
			case 'access':
				update_option( 'pluginstage_magic_token_never_expire', isset( $_POST['pluginstage_magic_token_never_expire'] ) ? 1 : 0 );
				update_option( 'pluginstage_magic_token_minutes', max( 1, absint( $_POST['pluginstage_magic_token_minutes'] ?? 60 ) ) );
				update_option( 'pluginstage_session_idle_minutes', max( 1, absint( $_POST['pluginstage_session_idle_minutes'] ?? 30 ) ) );
				update_option( 'pluginstage_max_concurrent_sessions', max( 0, absint( $_POST['pluginstage_max_concurrent_sessions'] ?? 5 ) ) );
				update_option( 'pluginstage_session_stale_minutes', max( 1, absint( $_POST['pluginstage_session_stale_minutes'] ?? 5 ) ) );
				break;
			case 'reset':
				$sched = isset( $_POST['pluginstage_reset_schedule'] ) ? sanitize_key( wp_unslash( $_POST['pluginstage_reset_schedule'] ) ) : 'manual';
				$allowed = array( 'manual', '15min', '30min', '1hour', '2hours', '6hours', 'daily' );
				if ( in_array( $sched, $allowed, true ) ) {
					update_option( 'pluginstage_reset_schedule', $sched );
				}
				update_option( 'pluginstage_countdown_enabled', isset( $_POST['pluginstage_countdown_enabled'] ) ? 1 : 0 );
				PluginStage_Reset::instance()->reschedule_cron();
				PluginStage_Reset::instance()->update_next_reset_timestamp_from_schedule();
				break;
			case 'branding':
				update_option( 'pluginstage_banner_message', wp_kses_post( wp_unslash( $_POST['pluginstage_banner_message'] ?? '' ) ) );
				update_option( 'pluginstage_banner_bg', sanitize_text_field( wp_unslash( $_POST['pluginstage_banner_bg'] ?? '' ) ) );
				update_option( 'pluginstage_banner_text', sanitize_text_field( wp_unslash( $_POST['pluginstage_banner_text'] ?? '' ) ) );
				update_option( 'pluginstage_banner_dismissible', isset( $_POST['pluginstage_banner_dismissible'] ) ? 1 : 0 );
				update_option( 'pluginstage_admin_bar_logo_url', esc_url_raw( wp_unslash( $_POST['pluginstage_admin_bar_logo_url'] ?? '' ) ) );
				update_option( 'pluginstage_admin_bar_links', sanitize_textarea_field( wp_unslash( $_POST['pluginstage_admin_bar_links'] ?? '' ) ) );
				update_option( 'pluginstage_admin_bar_hide_nodes', isset( $_POST['pluginstage_admin_bar_hide_nodes'] ) ? 1 : 0 );
				update_option( 'pluginstage_footer_enabled', isset( $_POST['pluginstage_footer_enabled'] ) ? 1 : 0 );
				update_option( 'pluginstage_footer_tagline', wp_kses_post( wp_unslash( $_POST['pluginstage_footer_tagline'] ?? '' ) ) );
				update_option( 'pluginstage_footer_logo_url', esc_url_raw( wp_unslash( $_POST['pluginstage_footer_logo_url'] ?? '' ) ) );
				update_option( 'pluginstage_footer_social', wp_kses_post( wp_unslash( $_POST['pluginstage_footer_social'] ?? '' ) ) );
				update_option( 'pluginstage_footer_purchase_url', esc_url_raw( wp_unslash( $_POST['pluginstage_footer_purchase_url'] ?? '' ) ) );
				update_option( 'pluginstage_cta_label', sanitize_text_field( wp_unslash( $_POST['pluginstage_cta_label'] ?? '' ) ) );
				update_option( 'pluginstage_cta_url', esc_url_raw( wp_unslash( $_POST['pluginstage_cta_url'] ?? '' ) ) );
				update_option( 'pluginstage_cta_bg', sanitize_text_field( wp_unslash( $_POST['pluginstage_cta_bg'] ?? '' ) ) );
				$pos = sanitize_key( wp_unslash( $_POST['pluginstage_cta_position'] ?? 'bottom-right' ) );
				update_option( 'pluginstage_cta_position', in_array( $pos, array( 'bottom-right', 'bottom-left' ), true ) ? $pos : 'bottom-right' );
				break;
			case 'tours':
				update_option( 'pluginstage_tour_enabled_global', isset( $_POST['pluginstage_tour_enabled_global'] ) ? 1 : 0 );
				$raw = isset( $_POST['pluginstage_tour_steps_global'] ) ? wp_unslash( $_POST['pluginstage_tour_steps_global'] ) : '[]';
				$dec = json_decode( (string) $raw, true );
				update_option( 'pluginstage_tour_steps_global', wp_json_encode( is_array( $dec ) ? $dec : array() ), false );
				break;
			case 'security':
				update_option( 'pluginstage_upload_max_bytes', max( 0, absint( $_POST['pluginstage_upload_max_bytes'] ?? 1048576 ) ) );
				update_option( 'pluginstage_upload_mimes', sanitize_text_field( wp_unslash( $_POST['pluginstage_upload_mimes'] ?? '' ) ) );
				update_option( 'pluginstage_banned_ips', sanitize_textarea_field( wp_unslash( $_POST['pluginstage_banned_ips'] ?? '' ) ) );
				update_option( 'pluginstage_auto_ban_threshold', max( 0, absint( $_POST['pluginstage_auto_ban_threshold'] ?? 0 ) ) );
				break;
			default:
				break;
		}

		self::flash_notice( __( 'Settings saved.', 'pluginstage' ) );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => self::SLUG,
					'tab'  => $tab,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Create snapshot.
	 */
	public function handle_create_snapshot() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'pluginstage' ) );
		}
		check_admin_referer( 'pluginstage_create_snapshot' );
		$res = PluginStage_Reset::instance()->create_snapshot();
		if ( is_wp_error( $res ) ) {
			self::flash_notice( $res->get_error_message(), 'error' );
		} else {
			self::flash_notice( sprintf( /* translators: %s snapshot id */ __( 'Snapshot created: %s', 'pluginstage' ), $res ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=reset' ) );
		exit;
	}

	/**
	 * Run reset now.
	 */
	public function handle_run_reset() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'pluginstage' ) );
		}
		check_admin_referer( 'pluginstage_run_reset' );
		$id = isset( $_POST['pluginstage_reset_snapshot_id'] ) ? sanitize_file_name( wp_unslash( $_POST['pluginstage_reset_snapshot_id'] ) ) : get_option( 'pluginstage_current_snapshot_id', '' );
		$res = PluginStage_Reset::instance()->perform_reset( $id );
		if ( is_wp_error( $res ) ) {
			self::flash_notice( $res->get_error_message(), 'error' );
		} else {
			update_option( 'pluginstage_current_snapshot_id', $id, false );
			self::flash_notice( __( 'Site reset completed.', 'pluginstage' ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=reset' ) );
		exit;
	}

	/**
	 * Generate magic URL.
	 */
	public function handle_generate_magic() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'pluginstage' ) );
		}
		check_admin_referer( 'pluginstage_generate_magic' );
		$profile_id = isset( $_POST['pluginstage_magic_profile_id'] ) ? absint( $_POST['pluginstage_magic_profile_id'] ) : 0;
		if ( 0 === $profile_id ) {
			$profile_id = (int) get_option( 'pluginstage_active_profile_id', 0 );
		}
		list( $raw, $url ) = PluginStage_Access::instance()->create_magic_token( $profile_id );
		if ( ! $raw || ! $url ) {
			self::flash_notice( __( 'Could not create link (concurrent limit or database error).', 'pluginstage' ), 'error' );
		} else {
			set_transient( 'pluginstage_last_magic_url_' . get_current_user_id(), $url, 300 );
			self::flash_notice( __( 'Magic link generated. Copy it from the Access tab (shown once below).', 'pluginstage' ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=access' ) );
		exit;
	}

	/**
	 * Set active profile for magic links / scheduled reset.
	 */
	public function handle_set_active_profile() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'pluginstage' ) );
		}
		check_admin_referer( 'pluginstage_set_active_profile' );
		$pid = isset( $_POST['pluginstage_active_profile_id'] ) ? absint( $_POST['pluginstage_active_profile_id'] ) : 0;
		update_option( 'pluginstage_active_profile_id', $pid, false );
		self::flash_notice( __( 'Active profile updated.', 'pluginstage' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=profiles' ) );
		exit;
	}

	/**
	 * Delete a snapshot.
	 */
	public function handle_delete_snapshot() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'pluginstage' ) );
		}
		$sid = isset( $_POST['pluginstage_snapshot_id'] ) ? sanitize_file_name( wp_unslash( $_POST['pluginstage_snapshot_id'] ) ) : '';
		check_admin_referer( 'pluginstage_delete_snapshot_' . $sid );

		if ( '' === $sid ) {
			self::flash_notice( __( 'No snapshot specified.', 'pluginstage' ), 'error' );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=reset' ) );
			exit;
		}

		$index = json_decode( (string) get_option( 'pluginstage_snapshots_index', '[]' ), true );
		if ( ! is_array( $index ) ) {
			$index = array();
		}

		$new_index = array();
		$found     = false;
		foreach ( $index as $item ) {
			if ( isset( $item['id'] ) && $item['id'] === $sid ) {
				$found = true;
				continue;
			}
			$new_index[] = $item;
		}

		if ( ! $found ) {
			self::flash_notice( __( 'Snapshot not found in index.', 'pluginstage' ), 'error' );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=reset' ) );
			exit;
		}

		$dir = trailingslashit( PLUGINSTAGE_SNAPSHOT_DIR ) . $sid;
		if ( is_dir( $dir ) ) {
			$this->recursive_delete_directory( $dir );
		}

		update_option( 'pluginstage_snapshots_index', wp_json_encode( $new_index ), false );

		$current = (string) get_option( 'pluginstage_current_snapshot_id', '' );
		if ( $current === $sid ) {
			update_option( 'pluginstage_current_snapshot_id', '', false );
		}

		self::flash_notice( sprintf( __( 'Snapshot %s deleted.', 'pluginstage' ), $sid ) );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=reset' ) );
		exit;
	}

	/**
	 * Recursively delete a directory.
	 *
	 * @param string $dir Directory path.
	 */
	private function recursive_delete_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		if ( false === $items ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			if ( is_dir( $path ) ) {
				$this->recursive_delete_directory( $path );
			} else {
				wp_delete_file( $path );
			}
		}
		rmdir( $dir );
	}

	/**
	 * Revoke a magic token.
	 */
	public function handle_revoke_token() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'pluginstage' ) );
		}
		$tid = isset( $_POST['pluginstage_token_id'] ) ? absint( $_POST['pluginstage_token_id'] ) : 0;
		check_admin_referer( 'pluginstage_revoke_token_' . $tid );

		if ( ! $tid ) {
			self::flash_notice( __( 'No token specified.', 'pluginstage' ), 'error' );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=access' ) );
			exit;
		}

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'pluginstage_tokens',
			array( 'revoked' => 1 ),
			array( 'id' => $tid ),
			array( '%d' ),
			array( '%d' )
		);

		self::flash_notice( __( 'Token revoked.', 'pluginstage' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=access' ) );
		exit;
	}
}
