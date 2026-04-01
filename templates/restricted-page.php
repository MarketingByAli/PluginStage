<?php
/**
 * Friendly restricted-area screen for demo users.
 *
 * @package PluginStage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap pluginstage-restricted-wrap">
	<h1><?php esc_html_e( 'This area is restricted in demo mode', 'pluginstage' ); ?></h1>
	<p><?php esc_html_e( 'You cannot change these settings during the live demo. Explore the showcased features from the dashboard and allowed menus instead.', 'pluginstage' ); ?></p>
	<p>
		<a class="button button-primary" href="<?php echo esc_url( admin_url() ); ?>">
			<?php esc_html_e( 'Back to Dashboard', 'pluginstage' ); ?>
		</a>
	</p>
</div>
