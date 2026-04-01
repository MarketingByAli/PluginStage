<?php
/**
 * Settings tab: Reset.
 *
 * @package PluginStage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$index   = json_decode( (string) get_option( 'pluginstage_snapshots_index', '[]' ), true );
$current = (string) get_option( 'pluginstage_current_snapshot_id', '' );
if ( ! is_array( $index ) ) {
	$index = array();
}
$sched = (string) get_option( 'pluginstage_reset_schedule', 'manual' );
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pluginstage-settings-form">
	<?php wp_nonce_field( 'pluginstage_save_reset' ); ?>
	<input type="hidden" name="action" value="pluginstage_save_tab" />
	<input type="hidden" name="pluginstage_tab" value="reset" />
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="pluginstage_reset_schedule"><?php esc_html_e( 'Reset schedule', 'pluginstage' ); ?></label></th>
			<td>
				<select name="pluginstage_reset_schedule" id="pluginstage_reset_schedule">
					<option value="manual" <?php selected( $sched, 'manual' ); ?>><?php esc_html_e( 'Manual only', 'pluginstage' ); ?></option>
					<option value="15min" <?php selected( $sched, '15min' ); ?>><?php esc_html_e( 'Every 15 minutes', 'pluginstage' ); ?></option>
					<option value="30min" <?php selected( $sched, '30min' ); ?>><?php esc_html_e( 'Every 30 minutes', 'pluginstage' ); ?></option>
					<option value="1hour" <?php selected( $sched, '1hour' ); ?>><?php esc_html_e( 'Every hour', 'pluginstage' ); ?></option>
					<option value="2hours" <?php selected( $sched, '2hours' ); ?>><?php esc_html_e( 'Every 2 hours', 'pluginstage' ); ?></option>
					<option value="6hours" <?php selected( $sched, '6hours' ); ?>><?php esc_html_e( 'Every 6 hours', 'pluginstage' ); ?></option>
					<option value="daily" <?php selected( $sched, 'daily' ); ?>><?php esc_html_e( 'Daily', 'pluginstage' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Countdown', 'pluginstage' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="pluginstage_countdown_enabled" value="1" <?php checked( (int) get_option( 'pluginstage_countdown_enabled', 1 ), 1 ); ?> />
					<?php esc_html_e( 'Show next reset countdown to demo users (banner / admin bar area)', 'pluginstage' ); ?>
				</label>
			</td>
		</tr>
	</table>
	<?php submit_button( __( 'Save schedule', 'pluginstage' ) ); ?>
</form>

<hr />

<h2><?php esc_html_e( 'Snapshots', 'pluginstage' ); ?></h2>
<p><?php esc_html_e( 'Create a clean-state snapshot before enabling scheduled resets. Requires mysqldump or WP-CLI on the server.', 'pluginstage' ); ?></p>
<p><strong><?php esc_html_e( 'Current snapshot:', 'pluginstage' ); ?></strong> <code><?php echo esc_html( $current ? $current : __( '(none)', 'pluginstage' ) ); ?></code></p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
	<?php wp_nonce_field( 'pluginstage_create_snapshot' ); ?>
	<input type="hidden" name="action" value="pluginstage_create_snapshot" />
	<?php submit_button( __( 'Create snapshot now', 'pluginstage' ), 'primary', 'submit', false ); ?>
</form>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'pluginstage_run_reset' ); ?>
	<input type="hidden" name="action" value="pluginstage_run_reset" />
	<p>
		<label for="pluginstage_reset_snapshot_id"><?php esc_html_e( 'Reset using snapshot', 'pluginstage' ); ?></label><br />
		<select name="pluginstage_reset_snapshot_id" id="pluginstage_reset_snapshot_id">
			<?php foreach ( $index as $item ) : ?>
				<?php
				if ( empty( $item['id'] ) ) {
					continue;
				}
				$sid = sanitize_text_field( $item['id'] );
				?>
				<option value="<?php echo esc_attr( $sid ); ?>" <?php selected( $current, $sid ); ?>><?php echo esc_html( $sid ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<?php submit_button( __( 'Run reset now', 'pluginstage' ), 'secondary', 'submit', false, array( 'onclick' => "return confirm('" . esc_js( __( 'This will restore the database and prune uploads. Continue?', 'pluginstage' ) ) . "');" ) ); ?>
</form>

<?php if ( ! empty( $index ) ) : ?>
	<h3><?php esc_html_e( 'Known snapshots', 'pluginstage' ); ?></h3>
	<table class="widefat striped" style="max-width:700px;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Snapshot ID', 'pluginstage' ); ?></th>
				<th><?php esc_html_e( 'Created', 'pluginstage' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'pluginstage' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $index as $item ) : ?>
				<?php if ( empty( $item['id'] ) ) { continue; } ?>
				<?php
				$sid  = sanitize_text_field( $item['id'] );
				$date = ! empty( $item['created'] ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $item['created'] ) : '—';
				$is_current = ( $current === $sid );
				?>
				<tr>
					<td>
						<code><?php echo esc_html( $sid ); ?></code>
						<?php if ( $is_current ) : ?>
							<span style="color:#2271b1;font-weight:600;margin-left:6px;"><?php esc_html_e( '(active)', 'pluginstage' ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $date ); ?></td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
							<?php wp_nonce_field( 'pluginstage_delete_snapshot_' . $sid ); ?>
							<input type="hidden" name="action" value="pluginstage_delete_snapshot" />
							<input type="hidden" name="pluginstage_snapshot_id" value="<?php echo esc_attr( $sid ); ?>" />
							<button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js( sprintf( __( 'Delete snapshot %s? This cannot be undone.', 'pluginstage' ), $sid ) ); ?>');"><?php esc_html_e( 'Delete', 'pluginstage' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
