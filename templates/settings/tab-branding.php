<?php
/**
 * Settings tab: Branding.
 *
 * @package PluginStage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pluginstage-settings-form">
	<?php wp_nonce_field( 'pluginstage_save_branding' ); ?>
	<input type="hidden" name="action" value="pluginstage_save_tab" />
	<input type="hidden" name="pluginstage_tab" value="branding" />
	<h2><?php esc_html_e( 'Top banner', 'pluginstage' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="pluginstage_banner_message"><?php esc_html_e( 'Message', 'pluginstage' ); ?></label></th>
			<td><textarea class="large-text" rows="3" name="pluginstage_banner_message" id="pluginstage_banner_message"><?php echo esc_textarea( (string) get_option( 'pluginstage_banner_message', '' ) ); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Colors', 'pluginstage' ); ?></th>
			<td>
				<input type="text" name="pluginstage_banner_bg" placeholder="#1d2327" value="<?php echo esc_attr( (string) get_option( 'pluginstage_banner_bg', '#1d2327' ) ); ?>" />
				<input type="text" name="pluginstage_banner_text" placeholder="#f0f0f1" value="<?php echo esc_attr( (string) get_option( 'pluginstage_banner_text', '#f0f0f1' ) ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Dismissible', 'pluginstage' ); ?></th>
			<td><label><input type="checkbox" name="pluginstage_banner_dismissible" value="1" <?php checked( (int) get_option( 'pluginstage_banner_dismissible', 1 ), 1 ); ?> /> <?php esc_html_e( 'Allow demo users to dismiss for the session', 'pluginstage' ); ?></label></td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Admin bar', 'pluginstage' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="pluginstage_admin_bar_logo_url"><?php esc_html_e( 'Logo URL (replaces WP logo)', 'pluginstage' ); ?></label></th>
			<td><input type="url" class="large-text" name="pluginstage_admin_bar_logo_url" id="pluginstage_admin_bar_logo_url" value="<?php echo esc_attr( (string) get_option( 'pluginstage_admin_bar_logo_url', '' ) ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="pluginstage_admin_bar_links"><?php esc_html_e( 'Custom links', 'pluginstage' ); ?></label></th>
			<td>
				<textarea class="large-text code" rows="5" name="pluginstage_admin_bar_links" id="pluginstage_admin_bar_links"><?php echo esc_textarea( (string) get_option( 'pluginstage_admin_bar_links', '' ) ); ?></textarea>
				<p class="description"><?php esc_html_e( 'One per line: Label|https://example.com', 'pluginstage' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Simplify bar', 'pluginstage' ); ?></th>
			<td><label><input type="checkbox" name="pluginstage_admin_bar_hide_nodes" value="1" <?php checked( (int) get_option( 'pluginstage_admin_bar_hide_nodes', 1 ), 1 ); ?> /> <?php esc_html_e( 'Hide comments, New menu, and WP logo submenu for demo users', 'pluginstage' ); ?></label></td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Footer bar', 'pluginstage' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enabled', 'pluginstage' ); ?></th>
			<td><label><input type="checkbox" name="pluginstage_footer_enabled" value="1" <?php checked( (int) get_option( 'pluginstage_footer_enabled', 1 ), 1 ); ?> /> <?php esc_html_e( 'Show footer bar for demo users', 'pluginstage' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><label for="pluginstage_footer_logo_url"><?php esc_html_e( 'Footer logo URL', 'pluginstage' ); ?></label></th>
			<td><input type="url" class="large-text" name="pluginstage_footer_logo_url" id="pluginstage_footer_logo_url" value="<?php echo esc_attr( (string) get_option( 'pluginstage_footer_logo_url', '' ) ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="pluginstage_footer_tagline"><?php esc_html_e( 'Tagline', 'pluginstage' ); ?></label></th>
			<td><textarea class="large-text" rows="2" name="pluginstage_footer_tagline" id="pluginstage_footer_tagline"><?php echo esc_textarea( (string) get_option( 'pluginstage_footer_tagline', '' ) ); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><label for="pluginstage_footer_social"><?php esc_html_e( 'Social / extra HTML', 'pluginstage' ); ?></label></th>
			<td><textarea class="large-text" rows="2" name="pluginstage_footer_social" id="pluginstage_footer_social"><?php echo esc_textarea( (string) get_option( 'pluginstage_footer_social', '' ) ); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><label for="pluginstage_footer_purchase_url"><?php esc_html_e( 'Purchase URL', 'pluginstage' ); ?></label></th>
			<td><input type="url" class="large-text" name="pluginstage_footer_purchase_url" id="pluginstage_footer_purchase_url" value="<?php echo esc_attr( (string) get_option( 'pluginstage_footer_purchase_url', '' ) ); ?>" /></td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Floating CTA', 'pluginstage' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="pluginstage_cta_label"><?php esc_html_e( 'Button label', 'pluginstage' ); ?></label></th>
			<td><input type="text" class="regular-text" name="pluginstage_cta_label" id="pluginstage_cta_label" value="<?php echo esc_attr( (string) get_option( 'pluginstage_cta_label', '' ) ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="pluginstage_cta_url"><?php esc_html_e( 'Button URL', 'pluginstage' ); ?></label></th>
			<td><input type="url" class="large-text" name="pluginstage_cta_url" id="pluginstage_cta_url" value="<?php echo esc_attr( (string) get_option( 'pluginstage_cta_url', '' ) ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="pluginstage_cta_bg"><?php esc_html_e( 'Background color', 'pluginstage' ); ?></label></th>
			<td><input type="text" name="pluginstage_cta_bg" id="pluginstage_cta_bg" value="<?php echo esc_attr( (string) get_option( 'pluginstage_cta_bg', '#2271b1' ) ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="pluginstage_cta_text_color"><?php esc_html_e( 'Text color', 'pluginstage' ); ?></label></th>
			<td><input type="text" name="pluginstage_cta_text_color" id="pluginstage_cta_text_color" value="<?php echo esc_attr( (string) get_option( 'pluginstage_cta_text_color', '#ffffff' ) ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="pluginstage_cta_position"><?php esc_html_e( 'Position', 'pluginstage' ); ?></label></th>
			<td>
				<select name="pluginstage_cta_position" id="pluginstage_cta_position">
					<option value="bottom-right" <?php selected( (string) get_option( 'pluginstage_cta_position', 'bottom-right' ), 'bottom-right' ); ?>><?php esc_html_e( 'Bottom right', 'pluginstage' ); ?></option>
					<option value="bottom-left" <?php selected( (string) get_option( 'pluginstage_cta_position', 'bottom-right' ), 'bottom-left' ); ?>><?php esc_html_e( 'Bottom left', 'pluginstage' ); ?></option>
				</select>
			</td>
		</tr>
	</table>
	<?php submit_button( __( 'Save changes', 'pluginstage' ) ); ?>
</form>
