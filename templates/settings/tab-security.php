<?php
/**
 * Settings tab: Security.
 *
 * @package PluginStage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page = isset( $_GET['plog'] ) ? max( 1, absint( wp_unslash( $_GET['plog'] ) ) ) : 1;
$log  = PluginStage_Session_Log::instance()->get_sessions_page( $page, 25 );
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pluginstage-settings-form">
	<?php wp_nonce_field( 'pluginstage_save_security' ); ?>
	<input type="hidden" name="action" value="pluginstage_save_tab" />
	<input type="hidden" name="pluginstage_tab" value="security" />
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="pluginstage_upload_max_bytes"><?php esc_html_e( 'Max upload size for demo users (bytes)', 'pluginstage' ); ?></label></th>
			<td><input type="number" min="0" class="small-text" name="pluginstage_upload_max_bytes" id="pluginstage_upload_max_bytes" value="<?php echo esc_attr( (string) (int) get_option( 'pluginstage_upload_max_bytes', 1048576 ) ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="pluginstage_upload_mimes"><?php esc_html_e( 'Allowed extensions (comma-separated)', 'pluginstage' ); ?></label></th>
			<td><input type="text" class="large-text" name="pluginstage_upload_mimes" id="pluginstage_upload_mimes" value="<?php echo esc_attr( (string) get_option( 'pluginstage_upload_mimes', 'jpg,jpeg,png,gif,webp,pdf' ) ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="pluginstage_auto_ban_threshold"><?php esc_html_e( 'Auto-ban threshold (abuse events / IP)', 'pluginstage' ); ?></label></th>
			<td><input type="number" min="0" class="small-text" name="pluginstage_auto_ban_threshold" id="pluginstage_auto_ban_threshold" value="<?php echo esc_attr( (string) (int) get_option( 'pluginstage_auto_ban_threshold', 0 ) ); ?>" />
				<p class="description"><?php esc_html_e( 'Counts invalid magic link attempts. 0 disables auto-ban.', 'pluginstage' ); ?></p></td>
		</tr>
		<tr>
			<th scope="row"><label for="pluginstage_banned_ips"><?php esc_html_e( 'Banned IPs', 'pluginstage' ); ?></label></th>
			<td><textarea class="large-text code" rows="6" name="pluginstage_banned_ips" id="pluginstage_banned_ips"><?php echo esc_textarea( (string) get_option( 'pluginstage_banned_ips', '' ) ); ?></textarea>
				<p class="description"><?php esc_html_e( 'One IP per line.', 'pluginstage' ); ?></p></td>
		</tr>
	</table>
	<?php submit_button( __( 'Save changes', 'pluginstage' ) ); ?>
</form>

<hr />

<h2><?php esc_html_e( 'Session log', 'pluginstage' ); ?></h2>
<p><?php echo esc_html( sprintf( /* translators: 1: current page total */ __( 'Total sessions logged: %d', 'pluginstage' ), (int) $log['total'] ) ); ?></p>
<table class="widefat striped">
	<thead>
		<tr>
			<th><?php esc_html_e( 'ID', 'pluginstage' ); ?></th>
			<th><?php esc_html_e( 'User', 'pluginstage' ); ?></th>
			<th><?php esc_html_e( 'Profile', 'pluginstage' ); ?></th>
			<th><?php esc_html_e( 'IP', 'pluginstage' ); ?></th>
			<th><?php esc_html_e( 'Started', 'pluginstage' ); ?></th>
			<th><?php esc_html_e( 'Ended', 'pluginstage' ); ?></th>
			<th><?php esc_html_e( 'Status', 'pluginstage' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $log['rows'] ) ) : ?>
			<tr><td colspan="7"><?php esc_html_e( 'No sessions yet.', 'pluginstage' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $log['rows'] as $row ) : ?>
				<tr>
					<td><?php echo esc_html( (string) (int) $row['id'] ); ?></td>
					<td><?php echo esc_html( (string) (int) $row['user_id'] ); ?></td>
					<td><?php echo esc_html( (string) (int) $row['profile_id'] ); ?></td>
					<td><?php echo esc_html( (string) $row['ip'] ); ?></td>
					<td><?php echo esc_html( (string) $row['started_at'] ); ?></td>
					<td><?php echo esc_html( (string) $row['ended_at'] ); ?></td>
					<td><?php echo esc_html( (string) $row['status'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>
<?php
$pages = (int) ceil( $log['total'] / 25 );
if ( $pages > 1 ) {
	echo '<p class="tablenav">';
	for ( $i = 1; $i <= $pages; $i++ ) {
		$url = add_query_arg(
			array(
				'page'  => PluginStage_Settings::SLUG,
				'tab'   => 'security',
				'plog'  => $i,
			),
			admin_url( 'admin.php' )
		);
		if ( $i === $page ) {
			echo ' <strong>' . esc_html( (string) $i ) . '</strong> ';
		} else {
			echo ' <a href="' . esc_url( $url ) . '">' . esc_html( (string) $i ) . '</a> ';
		}
	}
	echo '</p>';
}
?>
