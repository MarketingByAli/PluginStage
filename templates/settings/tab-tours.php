<?php
/**
 * Settings tab: Tours (global fallback).
 *
 * @package PluginStage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$steps = (string) get_option( 'pluginstage_tour_steps_global', '[]' );
$dec   = json_decode( $steps, true );
if ( ! is_array( $dec ) ) {
	$dec = array();
}
$pretty = wp_json_encode( $dec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pluginstage-settings-form">
	<?php wp_nonce_field( 'pluginstage_save_tours' ); ?>
	<input type="hidden" name="action" value="pluginstage_save_tab" />
	<input type="hidden" name="pluginstage_tab" value="tours" />
	<p><?php esc_html_e( 'Per-profile tours are edited on each Demo Profile. This is a global fallback when no profile tour is defined.', 'pluginstage' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable global tour', 'pluginstage' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="pluginstage_tour_enabled_global" value="1" <?php checked( (int) get_option( 'pluginstage_tour_enabled_global', 0 ), 1 ); ?> />
					<?php esc_html_e( 'Run Shepherd.js tour for demo users when steps are valid JSON', 'pluginstage' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="pluginstage_tour_steps_global"><?php esc_html_e( 'Steps (JSON)', 'pluginstage' ); ?></label></th>
			<td>
				<textarea class="large-text code" rows="14" name="pluginstage_tour_steps_global" id="pluginstage_tour_steps_global"><?php echo esc_textarea( $pretty ? $pretty : '[]' ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Example step: { "title": "Hi", "text": "Welcome", "attachTo": { "element": "#wpadminbar", "on": "bottom" } }', 'pluginstage' ); ?></p>
			</td>
		</tr>
	</table>
	<?php submit_button( __( 'Save changes', 'pluginstage' ) ); ?>
</form>
