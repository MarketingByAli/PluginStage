<?php
/**
 * Settings tab: Access.
 *
 * @package PluginStage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$last_magic = get_transient( 'pluginstage_last_magic_url_' . get_current_user_id() );
if ( $last_magic ) {
	delete_transient( 'pluginstage_last_magic_url_' . get_current_user_id() );
}
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pluginstage-settings-form">
	<?php wp_nonce_field( 'pluginstage_save_access' ); ?>
	<input type="hidden" name="action" value="pluginstage_save_tab" />
	<input type="hidden" name="pluginstage_tab" value="access" />
	<table class="form-table" role="presentation">
		<?php $never_expire = (int) get_option( 'pluginstage_magic_token_never_expire', 0 ); ?>
		<tr>
			<th scope="row"><label for="pluginstage_magic_token_minutes"><?php esc_html_e( 'Magic link validity (minutes)', 'pluginstage' ); ?></label></th>
			<td>
				<input type="number" min="1" class="small-text" name="pluginstage_magic_token_minutes" id="pluginstage_magic_token_minutes" value="<?php echo esc_attr( (string) (int) get_option( 'pluginstage_magic_token_minutes', 60 ) ); ?>"<?php echo $never_expire ? ' readonly style="opacity:.5;"' : ''; ?> />
				<label style="margin-left:10px;"><input type="checkbox" name="pluginstage_magic_token_never_expire" id="pluginstage_magic_token_never_expire" value="1" <?php checked( $never_expire, 1 ); ?> /> <?php esc_html_e( 'Never expire (valid until deleted)', 'pluginstage' ); ?></label>
				<script>
				(function(){
					var cb = document.getElementById('pluginstage_magic_token_never_expire');
					var inp = document.getElementById('pluginstage_magic_token_minutes');
					if(cb && inp){
						cb.addEventListener('change', function(){
							inp.readOnly = this.checked;
							inp.style.opacity = this.checked ? '.5' : '1';
						});
					}
				})();
				</script>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="pluginstage_session_idle_minutes"><?php esc_html_e( 'Auto-logout after inactivity (minutes)', 'pluginstage' ); ?></label></th>
			<td><input type="number" min="1" class="small-text" name="pluginstage_session_idle_minutes" id="pluginstage_session_idle_minutes" value="<?php echo esc_attr( (string) (int) get_option( 'pluginstage_session_idle_minutes', 30 ) ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="pluginstage_max_concurrent_sessions"><?php esc_html_e( 'Maximum concurrent demo sessions', 'pluginstage' ); ?></label></th>
			<td><input type="number" min="0" class="small-text" name="pluginstage_max_concurrent_sessions" id="pluginstage_max_concurrent_sessions" value="<?php echo esc_attr( (string) (int) get_option( 'pluginstage_max_concurrent_sessions', 5 ) ); ?>" />
				<p class="description"><?php esc_html_e( 'Use 0 for no limit.', 'pluginstage' ); ?></p></td>
		</tr>
		<tr>
			<th scope="row"><label for="pluginstage_session_stale_minutes"><?php esc_html_e( 'Session “active” window (minutes)', 'pluginstage' ); ?></label></th>
			<td><input type="number" min="1" class="small-text" name="pluginstage_session_stale_minutes" id="pluginstage_session_stale_minutes" value="<?php echo esc_attr( (string) (int) get_option( 'pluginstage_session_stale_minutes', 5 ) ); ?>" />
				<p class="description"><?php esc_html_e( 'Used to count concurrent sessions (heartbeat / last seen).', 'pluginstage' ); ?></p></td>
		</tr>
	</table>
	<?php submit_button( __( 'Save changes', 'pluginstage' ) ); ?>
</form>

<hr />

<h2><?php esc_html_e( 'Generate magic login URL', 'pluginstage' ); ?></h2>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'pluginstage_generate_magic' ); ?>
	<input type="hidden" name="action" value="pluginstage_generate_magic" />
	<?php $active_profile = (int) get_option( 'pluginstage_active_profile_id', 0 ); ?>
	<p>
		<label for="pluginstage_magic_profile_id"><?php esc_html_e( 'Profile', 'pluginstage' ); ?></label><br />
		<select name="pluginstage_magic_profile_id" id="pluginstage_magic_profile_id">
			<option value="0" <?php selected( $active_profile, 0 ); ?>><?php esc_html_e( 'Default (no profile)', 'pluginstage' ); ?></option>
			<?php
			$profiles = get_posts(
				array(
					'post_type'      => PluginStage_Profiles::CPT,
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);
			foreach ( $profiles as $p ) {
				printf(
					'<option value="%1$d" %3$s>%2$s</option>',
					(int) $p->ID,
					esc_html( get_the_title( $p ) ),
					selected( $active_profile, (int) $p->ID, false )
				);
			}
			?>
		</select>
	</p>
	<?php submit_button( __( 'Generate link', 'pluginstage' ), 'secondary' ); ?>
</form>

<?php if ( ! empty( $last_magic ) ) : ?>
	<div class="notice notice-info inline"><p><strong><?php esc_html_e( 'Magic URL (copy now):', 'pluginstage' ); ?></strong><br /><code style="word-break:break-all;"><?php echo esc_html( $last_magic ); ?></code></p></div>
<?php endif; ?>

<?php
global $wpdb;
$token_table = $wpdb->prefix . 'pluginstage_tokens';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$permanent_rows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT id, token_raw, profile_id, created_at FROM {$token_table} WHERE revoked = 0 AND expires_at = %s ORDER BY id DESC",
		'9999-12-31 23:59:59'
	),
	ARRAY_A
);
if ( ! empty( $permanent_rows ) ) :
?>
<hr />
<h2><?php esc_html_e( 'Active permanent demo links', 'pluginstage' ); ?></h2>
<p class="description"><?php esc_html_e( 'These links stay valid forever until you delete them. Copy the URL and use it anywhere — your plugin page, documentation, social media, etc.', 'pluginstage' ); ?></p>
<table class="widefat striped">
	<thead>
		<tr>
			<th style="width:50px;">#</th>
			<th><?php esc_html_e( 'Demo URL', 'pluginstage' ); ?></th>
			<th style="width:130px;"><?php esc_html_e( 'Profile', 'pluginstage' ); ?></th>
			<th style="width:160px;"><?php esc_html_e( 'Created', 'pluginstage' ); ?></th>
			<th style="width:160px;"><?php esc_html_e( 'Actions', 'pluginstage' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $permanent_rows as $prow ) :
			$pid   = (int) $prow['profile_id'];
			$pname = $pid > 0 ? get_the_title( $pid ) : __( 'Default', 'pluginstage' );
			$date  = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $prow['created_at'] ) );
			$raw   = (string) $prow['token_raw'];
			$url   = '' !== $raw ? add_query_arg( PluginStage_Access::QUERY_ARG, rawurlencode( $raw ), home_url( '/' ) ) : '';
		?>
			<tr>
				<td><?php echo esc_html( (string) (int) $prow['id'] ); ?></td>
				<td>
					<?php if ( '' !== $url ) : ?>
						<input type="text" readonly value="<?php echo esc_attr( $url ); ?>" class="large-text code" id="ps-url-<?php echo esc_attr( (string) (int) $prow['id'] ); ?>" onclick="this.select();" style="font-size:12px;" />
						<button type="button" class="button button-small" style="margin-top:4px;" onclick="var i=document.getElementById('ps-url-<?php echo esc_js( (string) (int) $prow['id'] ); ?>');i.select();document.execCommand('copy');this.textContent='<?php echo esc_js( __( 'Copied!', 'pluginstage' ) ); ?>';var b=this;setTimeout(function(){b.textContent='<?php echo esc_js( __( 'Copy URL', 'pluginstage' ) ); ?>';},1500);"><?php esc_html_e( 'Copy URL', 'pluginstage' ); ?></button>
					<?php else : ?>
						<span class="description"><?php esc_html_e( 'Created before URL storage was available. Delete and generate a new one.', 'pluginstage' ); ?></span>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( $pname ); ?></td>
				<td><?php echo esc_html( $date ); ?></td>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
						<?php wp_nonce_field( 'pluginstage_revoke_token_' . (int) $prow['id'] ); ?>
						<input type="hidden" name="action" value="pluginstage_revoke_token" />
						<input type="hidden" name="pluginstage_token_id" value="<?php echo esc_attr( (string) (int) $prow['id'] ); ?>" />
						<button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Delete this permanent link? It will stop working immediately.', 'pluginstage' ) ); ?>');"><?php esc_html_e( 'Delete', 'pluginstage' ); ?></button>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<?php endif; ?>
