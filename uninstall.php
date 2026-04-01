<?php
/**
 * Uninstall PluginStage.
 *
 * @package PluginStage
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$delete_data = (int) get_option( 'pluginstage_uninstall_delete_data', 0 );
if ( ! $delete_data ) {
	return;
}

$delete_snapshots = (int) get_option( 'pluginstage_uninstall_delete_snapshots', 0 );

wp_clear_scheduled_hook( 'pluginstage_scheduled_reset' );

$tokens_table   = $wpdb->prefix . 'pluginstage_tokens';
$sessions_table = $wpdb->prefix . 'pluginstage_sessions';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are controlled.
$wpdb->query( "DROP TABLE IF EXISTS {$tokens_table}" );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$sessions_table}" );

$like_main     = $wpdb->esc_like( 'pluginstage_' ) . '%';
$like_trans    = $wpdb->esc_like( '_transient_pluginstage_' ) . '%';
$like_trans_to = $wpdb->esc_like( '_transient_timeout_pluginstage_' ) . '%';
// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPlaceholder
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$like_main,
		$like_trans,
		$like_trans_to
	)
);

remove_role( 'demo_user' );

if ( $delete_snapshots ) {
	$snap_dir = trailingslashit( WP_CONTENT_DIR ) . 'pluginstage-snapshots';
	if ( is_dir( $snap_dir ) ) {
		pluginstage_uninstall_rmdir( $snap_dir );
	}
}

/**
 * Recursively remove a directory.
 *
 * @param string $dir Directory path.
 */
function pluginstage_uninstall_rmdir( $dir ) {
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
			pluginstage_uninstall_rmdir( $path );
		} else {
			wp_delete_file( $path );
		}
	}
	rmdir( $dir );
}
