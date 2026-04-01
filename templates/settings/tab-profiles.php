<?php
/**
 * Settings tab: Profiles.
 *
 * @package PluginStage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active = (int) get_option( 'pluginstage_active_profile_id', 0 );
$edit   = add_query_arg( 'post_type', PluginStage_Profiles::CPT, admin_url( 'edit.php' ) );
?>
<h2><?php esc_html_e( 'Active profile', 'pluginstage' ); ?></h2>
<p><?php esc_html_e( 'Used when generating magic links (default selection) and for scheduled resets (snapshot override).', 'pluginstage' ); ?></p>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'pluginstage_set_active_profile' ); ?>
	<input type="hidden" name="action" value="pluginstage_set_active_profile" />
	<select name="pluginstage_active_profile_id" id="pluginstage_active_profile_id">
		<option value="0" <?php selected( $active, 0 ); ?>><?php esc_html_e( 'Default (no profile)', 'pluginstage' ); ?></option>
		<?php
		$profiles = get_posts(
			array(
				'post_type'      => PluginStage_Profiles::CPT,
				'post_status'    => 'any',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		foreach ( $profiles as $p ) {
			printf(
				'<option value="%1$d" %3$s>%2$s</option>',
				(int) $p->ID,
				esc_html( get_the_title( $p ) ),
				selected( $active, (int) $p->ID, false )
			);
		}
		?>
	</select>
	<?php submit_button( __( 'Set active profile', 'pluginstage' ), 'secondary', 'submit', false ); ?>
</form>

<hr />

<h2><?php esc_html_e( 'Manage demo profiles', 'pluginstage' ); ?></h2>
<p>
	<a class="button button-primary" href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'Open Demo Profiles', 'pluginstage' ); ?></a>
</p>
<p class="description"><?php esc_html_e( 'Each profile can override branding, capabilities, snapshot, and guided tour steps.', 'pluginstage' ); ?></p>
