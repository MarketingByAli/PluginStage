<?php
/**
 * WP-CLI registration.
 *
 * @package PluginStage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PluginStage_CLI
 */
class PluginStage_CLI {

	/**
	 * Register on cli_init.
	 */
	public static function register() {
		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}
		WP_CLI::add_command( 'pluginstage', 'PluginStage_CLI_Command' );
	}
}
