<?php
/**
 * Demo profiles (CPT) and admin profile switcher.
 *
 * @package PluginStage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PluginStage_Profiles
 */
class PluginStage_Profiles {

	/**
	 * CPT slug.
	 */
	const CPT = 'pluginstage_profile';

	/**
	 * Instance.
	 *
	 * @var PluginStage_Profiles|null
	 */
	private static $instance = null;

	/**
	 * Instance.
	 *
	 * @return PluginStage_Profiles
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Built-in demo capability presets (merged for users of this profile).
	 *
	 * @return array<string, string[]> Preset key => capability names.
	 */
	public static function demo_preset_caps() {
		$presets = array(
			'posts' => array(
				'edit_posts',
				'publish_posts',
				'delete_posts',
			),
			'pages' => array(
				'edit_pages',
				'publish_pages',
				'delete_pages',
			),
			'woocommerce' => array(
				'manage_woocommerce',
				'view_admin_woocommerce',
			),
		);

		/**
		 * Add or adjust demo capability presets (per profile checkboxes in the editor).
		 *
		 * @param array<string, string[]> $presets Preset key => list of capability strings.
		 */
		return apply_filters( 'pluginstage_demo_cap_preset_definitions', $presets );
	}

	/**
	 * Init.
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_filter( 'map_meta_cap', array( $this, 'map_profile_meta_cap' ), 10, 4 );
		add_action( 'add_meta_boxes', array( $this, 'meta_boxes' ) );
		add_action( 'save_post_' . self::CPT, array( $this, 'save_meta' ), 10, 2 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_switcher' ), 95 );
	}

	/**
	 * Register custom post type.
	 */
	public function register_cpt() {
		$labels = array(
			'name'          => __( 'Demo Profiles', 'pluginstage' ),
			'singular_name' => __( 'Demo Profile', 'pluginstage' ),
			'add_new_item'  => __( 'Add New Demo Profile', 'pluginstage' ),
			'edit_item'     => __( 'Edit Demo Profile', 'pluginstage' ),
			'menu_name'     => __( 'Demo Profiles', 'pluginstage' ),
		);

		register_post_type(
			self::CPT,
			array(
				'labels'              => $labels,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'pluginstage',
				'capability_type'     => 'pluginstage_profile',
				'map_meta_cap'        => true,
				'capabilities'        => array(
					'edit_posts'               => 'manage_options',
					'edit_others_posts'        => 'manage_options',
					'edit_published_posts'     => 'manage_options',
					'edit_private_posts'       => 'manage_options',
					'publish_posts'            => 'manage_options',
					'read_private_posts'       => 'manage_options',
					'delete_posts'             => 'manage_options',
					'delete_others_posts'      => 'manage_options',
					'delete_published_posts'   => 'manage_options',
					'delete_private_posts'     => 'manage_options',
					'create_posts'             => 'manage_options',
				),
				'supports'            => array( 'title' ),
				'has_archive'         => false,
			)
		);
	}

	/**
	 * Map the custom CPT meta caps to manage_options so administrators
	 * can edit/delete/read individual profile posts.
	 *
	 * @param string[] $caps    Required primitive caps.
	 * @param string   $cap     Meta cap being checked.
	 * @param int      $user_id User ID.
	 * @param array    $args    Additional args (post ID, etc.).
	 * @return string[]
	 */
	public function map_profile_meta_cap( $caps, $cap, $user_id, $args ) {
		$meta_caps = array(
			'edit_pluginstage_profile',
			'read_pluginstage_profile',
			'delete_pluginstage_profile',
		);
		if ( in_array( $cap, $meta_caps, true ) ) {
			$caps = array( 'manage_options' );
		}
		return $caps;
	}

	/**
	 * Meta boxes.
	 */
	public function meta_boxes() {
		add_meta_box(
			'pluginstage_profile_data',
			__( 'Profile settings', 'pluginstage' ),
			array( $this, 'render_meta_box' ),
			self::CPT,
			'normal',
			'high'
		);
	}

	/**
	 * Render meta box.
	 *
	 * @param WP_Post $post Post.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'pluginstage_profile_save', 'pluginstage_profile_nonce' );

		$snapshot_id = get_post_meta( $post->ID, '_pluginstage_snapshot_id', true );
		$plugins     = get_post_meta( $post->ID, '_pluginstage_showcase_plugins', true );
		$allowed     = get_post_meta( $post->ID, '_pluginstage_allowed_caps', true );
		$blocked     = get_post_meta( $post->ID, '_pluginstage_blocked_caps', true );
		$bmsg        = get_post_meta( $post->ID, '_pluginstage_banner_message', true );
		$bbg         = get_post_meta( $post->ID, '_pluginstage_banner_bg', true );
		$bfg         = get_post_meta( $post->ID, '_pluginstage_banner_text', true );
		$logo        = get_post_meta( $post->ID, '_pluginstage_admin_bar_logo_url', true );
		$tour_on     = (int) get_post_meta( $post->ID, '_pluginstage_tour_enabled', true );
		$tour_steps  = get_post_meta( $post->ID, '_pluginstage_tour_steps', true );
		$demo_presets = get_post_meta( $post->ID, '_pluginstage_demo_presets', true );
		if ( ! is_array( $demo_presets ) ) {
			$demo_presets = array();
		}
		$preset_defs = self::demo_preset_caps();

		if ( ! is_string( $plugins ) ) {
			$plugins = '';
		}
		if ( is_array( $allowed ) ) {
			$allowed = implode( "\n", $allowed );
		}
		if ( ! is_string( $allowed ) ) {
			$allowed = '';
		}
		if ( is_array( $blocked ) ) {
			$blocked = implode( "\n", $blocked );
		}
		if ( ! is_string( $blocked ) ) {
			$blocked = '';
		}
		if ( ! is_string( $tour_steps ) ) {
			$tour_steps = '[]';
		}

		$index = json_decode( (string) get_option( 'pluginstage_snapshots_index', '[]' ), true );
		if ( ! is_array( $index ) ) {
			$index = array();
		}
		?>
		<p>
			<label for="pluginstage_snapshot_id"><strong><?php esc_html_e( 'Snapshot ID', 'pluginstage' ); ?></strong></label><br />
			<select name="pluginstage_snapshot_id" id="pluginstage_snapshot_id" class="widefat">
				<option value=""><?php esc_html_e( '— Use site default —', 'pluginstage' ); ?></option>
				<?php foreach ( $index as $item ) : ?>
					<?php
					if ( empty( $item['id'] ) ) {
						continue;
					}
					$sid = sanitize_text_field( $item['id'] );
					?>
					<option value="<?php echo esc_attr( $sid ); ?>" <?php selected( (string) $snapshot_id, $sid ); ?>><?php echo esc_html( $sid ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="pluginstage_showcase_plugins"><strong><?php esc_html_e( 'Showcased plugin slugs (comma-separated)', 'pluginstage' ); ?></strong></label><br />
			<input type="text" class="widefat" name="pluginstage_showcase_plugins" id="pluginstage_showcase_plugins" value="<?php echo esc_attr( $plugins ); ?>" />
		</p>
		<p>
			<label for="pluginstage_banner_message"><strong><?php esc_html_e( 'Banner message (override)', 'pluginstage' ); ?></strong></label><br />
			<textarea class="widefat" name="pluginstage_banner_message" id="pluginstage_banner_message" rows="2"><?php echo esc_textarea( (string) $bmsg ); ?></textarea>
		</p>
		<p>
			<label><?php esc_html_e( 'Banner colors (override)', 'pluginstage' ); ?></label><br />
			<input type="text" name="pluginstage_banner_bg" placeholder="#1d2327" value="<?php echo esc_attr( (string) $bbg ); ?>" />
			<input type="text" name="pluginstage_banner_text" placeholder="#f0f0f1" value="<?php echo esc_attr( (string) $bfg ); ?>" />
		</p>
		<p>
			<label for="pluginstage_admin_bar_logo_url"><strong><?php esc_html_e( 'Admin bar logo URL (override)', 'pluginstage' ); ?></strong></label><br />
			<input type="url" class="widefat" name="pluginstage_admin_bar_logo_url" id="pluginstage_admin_bar_logo_url" value="<?php echo esc_url( (string) $logo ); ?>" />
		</p>
		<?php
		$demo_plugin_access = get_post_meta( $post->ID, '_pluginstage_demo_plugin_access', true );
		if ( ! is_array( $demo_plugin_access ) ) {
			$demo_plugin_access = array();
		}
		$active_plugins_ui = self::get_active_plugins_for_profile_ui();
		?>
		<fieldset class="pluginstage-demo-plugin-access" style="margin:1em 0;padding:12px;border:1px solid #c3c4c7;background:#fff;">
			<legend><strong><?php esc_html_e( 'Plugin admin for demos (simple)', 'pluginstage' ); ?></strong></legend>
			<p class="description" style="margin-top:0;">
				<?php esc_html_e( 'Tick plugins visitors may use in wp-admin (e.g. your product’s screens). PluginStage detects menus that use “manage options” and match the plugin folder name. WordPress Settings / Plugins / Themes / Tools stay blocked. If something still fails, use “Extra allowed capabilities” below or a preset.', 'pluginstage' ); ?>
			</p>
			<?php if ( empty( $active_plugins_ui ) ) : ?>
				<p class="description"><?php esc_html_e( 'No active plugins found to list.', 'pluginstage' ); ?></p>
			<?php else : ?>
				<?php foreach ( $active_plugins_ui as $file => $name ) : ?>
					<label style="display:block;margin:6px 0;">
						<input type="checkbox" name="pluginstage_demo_plugin_access[]" value="<?php echo esc_attr( $file ); ?>" <?php checked( in_array( $file, $demo_plugin_access, true ) ); ?> />
						<?php echo esc_html( $name ); ?>
						<span class="description">(<?php echo esc_html( $file ); ?>)</span>
					</label>
				<?php endforeach; ?>
			<?php endif; ?>
		</fieldset>
		<fieldset class="pluginstage-demo-presets" style="margin:1em 0;padding:12px;border:1px solid #c3c4c7;background:#fff;">
			<legend><strong><?php esc_html_e( 'Demo permissions (quick presets)', 'pluginstage' ); ?></strong></legend>
			<p class="description" style="margin-top:0;">
				<?php esc_html_e( 'Turn on common tasks for visitors using this profile. Dangerous capabilities (WordPress settings, installing plugins/themes, editing files, etc.) stay blocked regardless.', 'pluginstage' ); ?>
			</p>
			<?php foreach ( $preset_defs as $preset_key => $caps_list ) : ?>
				<?php
				$preset_key = sanitize_key( $preset_key );
				if ( '' === $preset_key ) {
					continue;
				}
				$label = self::demo_preset_label( $preset_key );
				?>
				<label style="display:block;margin:6px 0;">
					<input type="checkbox" name="pluginstage_demo_presets[]" value="<?php echo esc_attr( $preset_key ); ?>" <?php checked( in_array( $preset_key, $demo_presets, true ) ); ?> />
					<?php echo esc_html( $label ); ?>
					<span class="description">— <?php echo esc_html( implode( ', ', array_map( 'strval', $caps_list ) ) ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<p>
			<label for="pluginstage_allowed_caps"><strong><?php esc_html_e( 'Extra allowed capabilities (one per line)', 'pluginstage' ); ?></strong></label><br />
			<span class="description"><?php esc_html_e( 'For other plugins, add the capability their admin screens require (see that plugin’s docs). This does not override blocked WordPress core areas above.', 'pluginstage' ); ?></span><br />
			<textarea class="widefat code" name="pluginstage_allowed_caps" id="pluginstage_allowed_caps" rows="4"><?php echo esc_textarea( $allowed ); ?></textarea>
		</p>
		<p>
			<label for="pluginstage_blocked_caps"><strong><?php esc_html_e( 'Extra blocked capabilities (one per line)', 'pluginstage' ); ?></strong></label><br />
			<textarea class="widefat code" name="pluginstage_blocked_caps" id="pluginstage_blocked_caps" rows="4"><?php echo esc_textarea( $blocked ); ?></textarea>
		</p>
		<p>
			<label>
				<input type="checkbox" name="pluginstage_tour_enabled" value="1" <?php checked( $tour_on, 1 ); ?> />
				<?php esc_html_e( 'Enable guided tour for this profile', 'pluginstage' ); ?>
			</label>
		</p>
		<p>
			<label for="pluginstage_tour_steps"><strong><?php esc_html_e( 'Tour steps (JSON)', 'pluginstage' ); ?></strong></label><br />
			<textarea class="widefat code" name="pluginstage_tour_steps" id="pluginstage_tour_steps" rows="8"><?php echo esc_textarea( $tour_steps ); ?></textarea>
		</p>
		<?php
	}

	/**
	 * Save meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public function save_meta( $post_id, $post ) {
		unset( $post );
		if ( ! isset( $_POST['pluginstage_profile_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pluginstage_profile_nonce'] ) ), 'pluginstage_profile_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$snapshot = isset( $_POST['pluginstage_snapshot_id'] ) ? sanitize_text_field( wp_unslash( $_POST['pluginstage_snapshot_id'] ) ) : '';
		update_post_meta( $post_id, '_pluginstage_snapshot_id', $snapshot );

		$plugins = isset( $_POST['pluginstage_showcase_plugins'] ) ? sanitize_text_field( wp_unslash( $_POST['pluginstage_showcase_plugins'] ) ) : '';
		update_post_meta( $post_id, '_pluginstage_showcase_plugins', $plugins );

		$allowed_raw = isset( $_POST['pluginstage_allowed_caps'] ) ? wp_unslash( $_POST['pluginstage_allowed_caps'] ) : '';
		$allowed     = array_filter(
			array_map(
				static function ( $c ) {
					return preg_replace( '/[^a-z0-9_]/', '', strtolower( trim( (string) $c ) ) );
				},
				explode( "\n", (string) $allowed_raw )
			)
		);
		update_post_meta( $post_id, '_pluginstage_allowed_caps', $allowed );

		$presets_in = isset( $_POST['pluginstage_demo_presets'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['pluginstage_demo_presets'] ) ) : array();
		$valid_keys = array_keys( self::demo_preset_caps() );
		$presets_in = array_values( array_intersect( $presets_in, $valid_keys ) );
		update_post_meta( $post_id, '_pluginstage_demo_presets', $presets_in );

		$allowed_plugin_keys = array_keys( self::get_active_plugins_for_profile_ui() );
		$plugins_in          = isset( $_POST['pluginstage_demo_plugin_access'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['pluginstage_demo_plugin_access'] ) ) : array();
		$plugins_in          = array_values( array_intersect( $plugins_in, $allowed_plugin_keys ) );
		update_post_meta( $post_id, '_pluginstage_demo_plugin_access', $plugins_in );

		$blocked_raw = isset( $_POST['pluginstage_blocked_caps'] ) ? wp_unslash( $_POST['pluginstage_blocked_caps'] ) : '';
		$blocked     = array_filter(
			array_map(
				static function ( $c ) {
					return preg_replace( '/[^a-z0-9_]/', '', strtolower( trim( (string) $c ) ) );
				},
				explode( "\n", (string) $blocked_raw )
			)
		);
		update_post_meta( $post_id, '_pluginstage_blocked_caps', $blocked );

		$bmsg = isset( $_POST['pluginstage_banner_message'] ) ? wp_kses_post( wp_unslash( $_POST['pluginstage_banner_message'] ) ) : '';
		$bbg  = isset( $_POST['pluginstage_banner_bg'] ) ? sanitize_text_field( wp_unslash( $_POST['pluginstage_banner_bg'] ) ) : '';
		$bfg  = isset( $_POST['pluginstage_banner_text'] ) ? sanitize_text_field( wp_unslash( $_POST['pluginstage_banner_text'] ) ) : '';
		update_post_meta( $post_id, '_pluginstage_banner_message', $bmsg );
		update_post_meta( $post_id, '_pluginstage_banner_bg', $bbg );
		update_post_meta( $post_id, '_pluginstage_banner_text', $bfg );

		$logo = isset( $_POST['pluginstage_admin_bar_logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['pluginstage_admin_bar_logo_url'] ) ) : '';
		update_post_meta( $post_id, '_pluginstage_admin_bar_logo_url', $logo );

		$tour_on = isset( $_POST['pluginstage_tour_enabled'] ) ? 1 : 0;
		update_post_meta( $post_id, '_pluginstage_tour_enabled', $tour_on );

		$steps_raw = isset( $_POST['pluginstage_tour_steps'] ) ? wp_unslash( $_POST['pluginstage_tour_steps'] ) : '[]';
		$decoded   = json_decode( (string) $steps_raw, true );
		update_post_meta( $post_id, '_pluginstage_tour_steps', wp_json_encode( is_array( $decoded ) ? $decoded : array() ) );
	}

	/**
	 * Human label for a demo preset key.
	 *
	 * @param string $key Preset key.
	 * @return string
	 */
	private static function demo_preset_label( $key ) {
		$labels = array(
			'posts'       => __( 'Blog posts (create, edit, publish, delete)', 'pluginstage' ),
			'pages'       => __( 'Pages (create, edit, publish, delete)', 'pluginstage' ),
			'woocommerce' => __( 'WooCommerce (store admin & settings screens)', 'pluginstage' ),
		);
		if ( isset( $labels[ $key ] ) ) {
			return $labels[ $key ];
		}
		return $key;
	}

	/**
	 * Admin bar: active profile switcher for administrators.
	 *
	 * @param WP_Admin_Bar $bar Bar.
	 */
	public function admin_bar_switcher( $bar ) {
		if ( ! current_user_can( 'manage_options' ) || PluginStage_Access::instance()->is_demo_user() ) {
			return;
		}

		$active = (int) get_option( 'pluginstage_active_profile_id', 0 );
		$bar->add_node(
			array(
				'id'    => 'pluginstage-profile-switch',
				'title' => __( 'PluginStage profile', 'pluginstage' ) . ': ' . ( $active ? get_the_title( $active ) : __( 'Default', 'pluginstage' ) ),
				'href'  => admin_url( 'admin.php?page=pluginstage&tab=profiles' ),
			)
		);
	}

	/**
	 * Active plugins for demo-access checkboxes (excludes this plugin).
	 *
	 * @return array<string, string> plugin_file => plugin name
	 */
	public static function get_active_plugins_for_profile_ui() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all    = get_plugins();
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		$active = array_values( array_unique( $active ) );
		$out    = array();
		foreach ( $active as $file ) {
			if ( ! isset( $all[ $file ] ) ) {
				continue;
			}
			if ( 0 === strpos( $file, 'pluginstage/' ) ) {
				continue;
			}
			$out[ $file ] = $all[ $file ]['Name'];
		}
		return $out;
	}

	/**
	 * Whether a menu slug string belongs to a plugin folder (heuristic).
	 *
	 * @param string $menu_slug Menu slug (e.g. auto-form-builder-dashboard).
	 * @param string $folder    Plugin folder basename (e.g. auto-form-builder).
	 * @return bool
	 */
	public static function menu_slug_belongs_to_plugin_folder( $menu_slug, $folder ) {
		$menu_slug = strtolower( (string) $menu_slug );
		$folder    = strtolower( (string) $folder );
		if ( '' === $menu_slug || '' === $folder || '.' === $folder ) {
			return false;
		}
		if ( false !== strpos( $menu_slug, $folder ) ) {
			return true;
		}
		$folder_us = str_replace( '-', '_', $folder );
		if ( false !== strpos( $menu_slug, $folder_us ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Whether a menu slug matches any allowed plugin folder (see menu_slug_belongs_to_plugin_folder).
	 *
	 * @param string   $slug    Menu slug from $menu / $submenu.
	 * @param string[] $folders Lowercased plugin folder basenames.
	 * @return bool
	 */
	public static function menu_slug_matches_any_allowed_folder( $slug, array $folders ) {
		foreach ( $folders as $folder ) {
			if ( self::menu_slug_belongs_to_plugin_folder( $slug, $folder ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Likely wp_ajax_* action prefixes for a plugin (folder-based guess).
	 *
	 * @param string $plugin_file Plugin main file path.
	 * @return string[]
	 */
	public static function ajax_prefixes_for_plugin_file( $plugin_file ) {
		$dir = dirname( $plugin_file );
		if ( '' === $dir || '.' === $dir ) {
			return array();
		}
		$us = str_replace( '-', '_', $dir );
		$prefixes = array_unique(
			array(
				$us . '_',
				$dir . '_',
			)
		);
		/**
		 * @param string[] $prefixes    Suggested AJAX action prefixes (action must start_with one).
		 * @param string   $plugin_file Plugin path.
		 */
		return array_values( array_filter( apply_filters( 'pluginstage_demo_plugin_ajax_prefixes', $prefixes, $plugin_file ) ) );
	}
}
