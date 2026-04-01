<?php
/**
 * Settings tab: General.
 *
 * @package PluginStage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pluginstage-settings-form">
	<?php wp_nonce_field( 'pluginstage_save_general' ); ?>
	<input type="hidden" name="action" value="pluginstage_save_tab" />
	<input type="hidden" name="pluginstage_tab" value="general" />
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="pluginstage_public_name"><?php esc_html_e( 'Public name', 'pluginstage' ); ?></label></th>
			<td>
				<input type="text" class="regular-text" name="pluginstage_public_name" id="pluginstage_public_name" value="<?php echo esc_attr( (string) get_option( 'pluginstage_public_name', 'PluginStage' ) ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="pluginstage_admin_alert_email"><?php esc_html_e( 'Admin email for alerts', 'pluginstage' ); ?></label></th>
			<td>
				<input type="email" class="regular-text" name="pluginstage_admin_alert_email" id="pluginstage_admin_alert_email" value="<?php echo esc_attr( (string) get_option( 'pluginstage_admin_alert_email', '' ) ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Uninstall', 'pluginstage' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="pluginstage_uninstall_delete_data" value="1" <?php checked( (int) get_option( 'pluginstage_uninstall_delete_data', 0 ), 1 ); ?> />
					<?php esc_html_e( 'Delete PluginStage database tables and options when the plugin is deleted', 'pluginstage' ); ?>
				</label>
				<p class="description">
					<label>
						<input type="checkbox" name="pluginstage_uninstall_delete_snapshots" value="1" <?php checked( (int) get_option( 'pluginstage_uninstall_delete_snapshots', 0 ), 1 ); ?> />
						<?php esc_html_e( 'Also delete snapshot files under wp-content/pluginstage-snapshots/', 'pluginstage' ); ?>
					</label>
				</p>
			</td>
		</tr>
	</table>
	<?php submit_button( __( 'Save changes', 'pluginstage' ) ); ?>
</form>
