<?php
/**
 * Plugin activation routines.
 *
 * @package PluginStage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create DB tables, role, default options, snapshot directory.
 */
function pluginstage_run_activation() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$tokens          = $wpdb->prefix . 'pluginstage_tokens';
	$sessions        = $wpdb->prefix . 'pluginstage_sessions';

	$sql_tokens = "CREATE TABLE {$tokens} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		token_hash varchar(64) NOT NULL,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		profile_id bigint(20) unsigned NOT NULL DEFAULT 0,
		expires_at datetime NOT NULL,
		created_at datetime NOT NULL,
		ip_created varchar(45) DEFAULT '',
		used_at datetime DEFAULT NULL,
		revoked tinyint(1) NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY token_hash (token_hash),
		KEY expires_at (expires_at),
		KEY profile_id (profile_id)
	) {$charset_collate};";

	$sql_sessions = "CREATE TABLE {$sessions} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		profile_id bigint(20) unsigned NOT NULL DEFAULT 0,
		ip varchar(45) NOT NULL DEFAULT '',
		user_agent text,
		started_at datetime NOT NULL,
		ended_at datetime DEFAULT NULL,
		last_seen_at datetime NOT NULL,
		pages_visited longtext,
		status varchar(20) NOT NULL DEFAULT 'active',
		PRIMARY KEY (id),
		KEY user_id (user_id),
		KEY status_last_seen (status, last_seen_at),
		KEY profile_id (profile_id)
	) {$charset_collate};";

	dbDelta( $sql_tokens );
	dbDelta( $sql_sessions );

	$caps = array(
		'read'         => true,
		'upload_files' => true,
	);

	$blocked = array(
		'manage_options',
		'install_plugins',
		'activate_plugins',
		'edit_plugins',
		'delete_plugins',
		'switch_themes',
		'edit_themes',
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

	foreach ( $blocked as $cap ) {
		$caps[ $cap ] = false;
	}

	remove_role( 'demo_user' );
	add_role( 'demo_user', __( 'Demo User', 'pluginstage' ), $caps );

	$snap_dir = PLUGINSTAGE_SNAPSHOT_DIR;
	if ( ! is_dir( $snap_dir ) ) {
		wp_mkdir_p( $snap_dir );
	}
	$index_file = trailingslashit( $snap_dir ) . 'index.php';
	if ( ! file_exists( $index_file ) ) {
		file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
	}

	$defaults = array(
		'pluginstage_public_name'           => 'PluginStage',
		'pluginstage_admin_alert_email'     => get_option( 'admin_email' ),
		'pluginstage_magic_token_minutes'   => 60,
		'pluginstage_magic_token_never_expire' => 0,
		'pluginstage_session_idle_minutes'  => 30,
		'pluginstage_max_concurrent_sessions' => 5,
		'pluginstage_session_stale_minutes' => 5,
		'pluginstage_reset_schedule'        => 'manual',
		'pluginstage_countdown_enabled'     => 1,
		'pluginstage_next_reset_at'         => 0,
		'pluginstage_active_profile_id'     => 0,
		'pluginstage_uninstall_delete_data' => 0,
		'pluginstage_uninstall_delete_snapshots' => 0,
		'pluginstage_banner_message'        => __( 'You are on a live demo. This site resets on a schedule.', 'pluginstage' ),
		'pluginstage_banner_bg'             => '#1d2327',
		'pluginstage_banner_text'         => '#f0f0f1',
		'pluginstage_banner_dismissible'    => 1,
		'pluginstage_admin_bar_logo_url'    => '',
		'pluginstage_admin_bar_links'       => '',
		'pluginstage_admin_bar_hide_nodes'  => 1,
		'pluginstage_footer_enabled'        => 1,
		'pluginstage_footer_tagline'        => '',
		'pluginstage_footer_logo_url'       => '',
		'pluginstage_footer_social'         => '',
		'pluginstage_footer_purchase_url'   => '',
		'pluginstage_cta_label'             => '',
		'pluginstage_cta_url'               => '',
		'pluginstage_cta_bg'                => '#2271b1',
		'pluginstage_cta_position'          => 'bottom-right',
		'pluginstage_upload_max_bytes'      => 1048576,
		'pluginstage_upload_mimes'          => 'jpg,jpeg,png,gif,webp,pdf',
		'pluginstage_banned_ips'            => '',
		'pluginstage_auto_ban_threshold'    => 0,
		'pluginstage_current_snapshot_id'   => '',
		'pluginstage_snapshots_index'       => wp_json_encode( array() ),
		'pluginstage_tour_enabled_global'   => 0,
		'pluginstage_tour_steps_global'     => '[]',
		'pluginstage_session_kill_version'  => 0,
	);

	foreach ( $defaults as $key => $value ) {
		if ( false === get_option( $key ) ) {
			add_option( $key, $value, '', 'no' );
		}
	}
}
