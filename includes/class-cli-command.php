<?php
/**
 * WP-CLI `wp pluginstage` subcommands.
 *
 * @package PluginStage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PluginStage_CLI_Command
 */
class PluginStage_CLI_Command {

	/**
	 * Create a clean-state snapshot (database + uploads manifest).
	 *
	 * ## EXAMPLES
	 *
	 *     wp pluginstage snapshot
	 */
	public function snapshot() {
		$reset = PluginStage_Reset::instance();
		$id    = $reset->create_snapshot();
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( $id->get_error_message() );
		}
		WP_CLI::success( sprintf( /* translators: %s snapshot id */ __( 'Snapshot created: %s', 'pluginstage' ), $id ) );
	}

	/**
	 * Restore site from a snapshot.
	 *
	 * ## OPTIONS
	 *
	 * [--snapshot=<id>]
	 * : Snapshot ID. Defaults to current snapshot option.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pluginstage reset
	 *     wp pluginstage reset --snapshot=snap_abc123
	 *
	 * @param array $args Positional args.
	 * @param array $assoc_args Associative args.
	 */
	public function reset( $args, $assoc_args ) {
		unset( $args );
		$snapshot = isset( $assoc_args['snapshot'] ) ? sanitize_file_name( $assoc_args['snapshot'] ) : get_option( 'pluginstage_current_snapshot_id', '' );
		if ( '' === $snapshot ) {
			WP_CLI::error( __( 'No snapshot ID set. Create a snapshot first.', 'pluginstage' ) );
		}
		$res = PluginStage_Reset::instance()->perform_reset( $snapshot );
		if ( is_wp_error( $res ) ) {
			WP_CLI::error( $res->get_error_message() );
		}
		WP_CLI::success( __( 'Reset completed.', 'pluginstage' ) );
	}
}
