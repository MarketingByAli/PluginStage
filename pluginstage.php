<?php
/**
 * Plugin Name:       PluginStage
 * Plugin URI:        https://wordpress.org/plugins/pluginstage
 * Description:       Live, self-resetting demo environments for showcasing WordPress plugins.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            PluginStage
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pluginstage
 *
 * @package PluginStage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PLUGINSTAGE_VERSION', '1.0.0' );
define( 'PLUGINSTAGE_PATH', plugin_dir_path( __FILE__ ) );
define( 'PLUGINSTAGE_URL', plugin_dir_url( __FILE__ ) );
define( 'PLUGINSTAGE_BASENAME', plugin_basename( __FILE__ ) );
define( 'PLUGINSTAGE_SNAPSHOT_DIR', trailingslashit( WP_CONTENT_DIR ) . 'pluginstage-snapshots' );

/**
 * Autoload PluginStage_* classes from includes/class-{name}.php.
 *
 * @param string $class Class name.
 */
function pluginstage_autoload( $class ) {
	if ( strpos( $class, 'PluginStage_' ) !== 0 ) {
		return;
	}
	$short = substr( $class, 12 );
	$slug  = strtolower( str_replace( '_', '-', $short ) );
	$file  = PLUGINSTAGE_PATH . 'includes/class-' . $slug . '.php';
	if ( is_readable( $file ) ) {
		require_once $file;
	}
}
spl_autoload_register( 'pluginstage_autoload' );

/**
 * Run plugin activation (tables, role, options, directories).
 */
function pluginstage_activate() {
	require_once PLUGINSTAGE_PATH . 'includes/activation.php';
	pluginstage_run_activation();
}

/**
 * Clear scheduled events on deactivation.
 */
function pluginstage_deactivate() {
	wp_clear_scheduled_hook( 'pluginstage_scheduled_reset' );
}

register_activation_hook( __FILE__, 'pluginstage_activate' );
register_deactivation_hook( __FILE__, 'pluginstage_deactivate' );

/**
 * Bootstrap PluginStage after plugins loaded.
 */
function pluginstage_init() {
	load_plugin_textdomain( 'pluginstage', false, dirname( PLUGINSTAGE_BASENAME ) . '/languages' );

	if ( class_exists( 'PluginStage_Access' ) ) {
		PluginStage_Access::instance()->init();
	}
	if ( class_exists( 'PluginStage_Reset' ) ) {
		PluginStage_Reset::instance()->init();
	}
	if ( class_exists( 'PluginStage_Profiles' ) ) {
		PluginStage_Profiles::instance()->init();
	}
	if ( class_exists( 'PluginStage_Security' ) ) {
		PluginStage_Security::instance()->init();
	}
	if ( class_exists( 'PluginStage_Session_Log' ) ) {
		PluginStage_Session_Log::instance()->init();
	}
	if ( class_exists( 'PluginStage_Branding' ) ) {
		PluginStage_Branding::instance()->init();
	}
	if ( class_exists( 'PluginStage_Settings' ) ) {
		PluginStage_Settings::instance()->init();
	}
	if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
		WP_CLI::add_hook( 'cli_init', array( 'PluginStage_CLI', 'register' ) );
	}
}
add_action( 'plugins_loaded', 'pluginstage_init', 5 );
