<?php
/**
 * Admin branding: banner, admin bar, footer, CTA, tour.
 *
 * @package PluginStage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PluginStage_Branding
 */
class PluginStage_Branding {

	/**
	 * Instance.
	 *
	 * @var PluginStage_Branding|null
	 */
	private static $instance = null;

	/**
	 * Instance.
	 *
	 * @return PluginStage_Branding
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hooks.
	 */
	public function init() {
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'in_admin_header', array( $this, 'render_top_banner' ), 1 );
		add_action( 'admin_bar_menu', array( $this, 'customize_admin_bar' ), 100 );
		add_action( 'admin_footer', array( $this, 'render_footer_and_cta' ), 5 );
		add_action( 'wp_ajax_pluginstage_dismiss_banner', array( $this, 'ajax_dismiss_banner' ) );
	}

	/**
	 * Effective value for demo user: profile meta or global option.
	 *
	 * @param string $meta_key   Post meta key (e.g. _pluginstage_banner_message).
	 * @param string $option_key Option key without checking - full option name.
	 * @param mixed  $default    Default.
	 * @return mixed
	 */
	public function get_for_demo( $meta_key, $option_key, $default = '' ) {
		if ( ! PluginStage_Access::instance()->is_demo_user() ) {
			return get_option( $option_key, $default );
		}
		$pid = (int) get_user_meta( get_current_user_id(), 'pluginstage_profile_id', true );
		if ( $pid > 0 ) {
			$v = get_post_meta( $pid, $meta_key, true );
			if ( is_string( $v ) && '' !== trim( $v ) ) {
				return $v;
			}
			if ( ! is_string( $v ) && '' !== $v && null !== $v ) {
				return $v;
			}
		}
		return get_option( $option_key, $default );
	}

	/**
	 * Enqueue CSS/JS for demo users in admin.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_assets( $hook ) {
		unset( $hook );
		if ( ! PluginStage_Access::instance()->is_demo_user() ) {
			return;
		}

		wp_enqueue_style(
			'pluginstage-admin',
			PLUGINSTAGE_URL . 'assets/css/admin.css',
			array(),
			PLUGINSTAGE_VERSION
		);

		$next = (int) get_option( 'pluginstage_next_reset_at', 0 );
		wp_enqueue_script(
			'pluginstage-admin',
			PLUGINSTAGE_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			PLUGINSTAGE_VERSION,
			true
		);
		wp_localize_script(
			'pluginstage-admin',
			'pluginstageAdmin',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'pluginstage_banner' ),
				'nextReset'   => $next,
				'showCountdown' => (int) get_option( 'pluginstage_countdown_enabled', 1 ),
				'countdownLabel' => __( 'Next reset:', 'pluginstage' ),
			)
		);

		$this->maybe_enqueue_tour();
	}

	/**
	 * Load Shepherd and tour steps when enabled for profile.
	 */
	private function maybe_enqueue_tour() {
		$pid = (int) get_user_meta( get_current_user_id(), 'pluginstage_profile_id', true );
		$steps = array();
		if ( $pid > 0 ) {
			$enabled = (int) get_post_meta( $pid, '_pluginstage_tour_enabled', true );
			if ( $enabled ) {
				$json = get_post_meta( $pid, '_pluginstage_tour_steps', true );
				$dec  = is_string( $json ) ? json_decode( $json, true ) : array();
				$steps = is_array( $dec ) ? $dec : array();
			}
		}

		if ( empty( $steps ) ) {
			$enabled_g = (int) get_option( 'pluginstage_tour_enabled_global', 0 );
			if ( $enabled_g ) {
				$json = get_option( 'pluginstage_tour_steps_global', '[]' );
				$dec  = json_decode( (string) $json, true );
				$steps = is_array( $dec ) ? $dec : array();
			}
		}

		if ( empty( $steps ) ) {
			return;
		}

		$steps = array_map( array( $this, 'sanitize_tour_step' ), $steps );

		wp_enqueue_script(
			'shepherd',
			PLUGINSTAGE_URL . 'assets/vendor/shepherd.min.js',
			array(),
			'11.2.0',
			true
		);
		wp_enqueue_style(
			'shepherd',
			PLUGINSTAGE_URL . 'assets/vendor/shepherd.min.css',
			array(),
			'11.2.0'
		);

		wp_enqueue_script(
			'pluginstage-tour',
			PLUGINSTAGE_URL . 'assets/js/tour.js',
			array( 'shepherd', 'jquery' ),
			PLUGINSTAGE_VERSION,
			true
		);
		wp_localize_script(
			'pluginstage-tour',
			'pluginstageTour',
			array(
				'steps'   => $steps,
				'start'   => __( 'Start tour', 'pluginstage' ),
				'next'    => __( 'Next', 'pluginstage' ),
				'back'    => __( 'Back', 'pluginstage' ),
				'done'    => __( 'Done', 'pluginstage' ),
				'cancel'  => __( 'Close', 'pluginstage' ),
			)
		);
	}

	/**
	 * Sanitize a single tour step array for safe localization.
	 *
	 * @param mixed $step Step data.
	 * @return array
	 */
	public function sanitize_tour_step( $step ) {
		if ( ! is_array( $step ) ) {
			return array();
		}
		$clean = array();
		if ( isset( $step['title'] ) ) {
			$clean['title'] = wp_kses_post( (string) $step['title'] );
		}
		if ( isset( $step['text'] ) ) {
			$clean['text'] = wp_kses_post( (string) $step['text'] );
		}
		if ( isset( $step['attachTo'] ) && is_array( $step['attachTo'] ) ) {
			$clean['attachTo'] = array(
				'element' => isset( $step['attachTo']['element'] ) ? sanitize_text_field( (string) $step['attachTo']['element'] ) : '',
				'on'      => isset( $step['attachTo']['on'] ) ? sanitize_key( (string) $step['attachTo']['on'] ) : 'bottom',
			);
		}
		return $clean;
	}

	/**
	 * Sticky banner below admin bar area.
	 */
	public function render_top_banner() {
		if ( ! PluginStage_Access::instance()->is_demo_user() ) {
			return;
		}

		$dismissed = get_user_meta( get_current_user_id(), 'pluginstage_banner_dismissed_session', true );
		if ( $dismissed && (int) get_option( 'pluginstage_banner_dismissible', 1 ) ) {
			return;
		}

		$msg  = (string) $this->get_for_demo( '_pluginstage_banner_message', 'pluginstage_banner_message', '' );
		$bg   = (string) $this->get_for_demo( '_pluginstage_banner_bg', 'pluginstage_banner_bg', '#1d2327' );
		$fg   = (string) $this->get_for_demo( '_pluginstage_banner_text', 'pluginstage_banner_text', '#f0f0f1' );
		$show = (int) get_option( 'pluginstage_countdown_enabled', 1 );

		$dismissible = (int) get_option( 'pluginstage_banner_dismissible', 1 );
		?>
		<div id="pluginstage-top-banner" class="pluginstage-top-banner" style="<?php echo esc_attr( 'background-color:' . $bg . ';color:' . $fg . ';' ); ?>" role="region" aria-label="<?php esc_attr_e( 'Demo notice', 'pluginstage' ); ?>">
			<div class="pluginstage-top-banner__inner">
				<span class="pluginstage-top-banner__msg"><?php echo wp_kses_post( $msg ); ?></span>
				<?php if ( $show ) : ?>
					<span class="pluginstage-top-banner__countdown" id="pluginstage-countdown" aria-live="polite"></span>
				<?php endif; ?>
				<?php if ( $dismissible ) : ?>
					<button type="button" class="pluginstage-top-banner__dismiss" id="pluginstage-banner-dismiss" aria-label="<?php esc_attr_e( 'Dismiss notice', 'pluginstage' ); ?>">&times;</button>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Customize admin bar for demo users.
	 *
	 * @param WP_Admin_Bar $bar Bar.
	 */
	public function customize_admin_bar( $bar ) {
		if ( ! PluginStage_Access::instance()->is_demo_user() ) {
			return;
		}

		$logo = (string) $this->get_for_demo( '_pluginstage_admin_bar_logo_url', 'pluginstage_admin_bar_logo_url', '' );
		if ( $logo ) {
			$bar->remove_node( 'wp-logo' );
			$bar->add_node(
				array(
					'id'    => 'pluginstage-logo',
					'title' => '<img src="' . esc_url( $logo ) . '" alt="" class="pluginstage-ab-logo" />',
					'href'  => admin_url(),
					'meta'  => array(
						'class' => 'pluginstage-ab-logo-wrap',
					),
				)
			);
		}

		$links_raw = (string) $this->get_for_demo( '_pluginstage_admin_bar_links', 'pluginstage_admin_bar_links', '' );
		$lines     = preg_split( '/\r\n|\r|\n/', $links_raw );
		$i         = 0;
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || false === strpos( $line, '|' ) ) {
				continue;
			}
			list($label, $url) = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( '' === $label || '' === $url ) {
				continue;
			}
			++$i;
			$bar->add_node(
				array(
					'id'    => 'pluginstage-link-' . $i,
					'title' => esc_html( $label ),
					'href'  => esc_url( $url ),
				)
			);
		}

		if ( (int) get_option( 'pluginstage_admin_bar_hide_nodes', 1 ) ) {
			$bar->remove_node( 'comments' );
			$bar->remove_node( 'new-content' );
			$bar->remove_node( 'wp-logo-external' );
		}
	}

	/**
	 * Body class for layout tweaks.
	 *
	 * @param string $classes Classes.
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		if ( PluginStage_Access::instance()->is_demo_user() ) {
			$classes .= ' pluginstage-demo-user';
		}
		return $classes;
	}

	/**
	 * Footer bar + floating CTA.
	 */
	public function render_footer_and_cta() {
		if ( ! PluginStage_Access::instance()->is_demo_user() ) {
			return;
		}

		if ( (int) get_option( 'pluginstage_footer_enabled', 1 ) ) {
			$tag    = (string) $this->get_for_demo( '_pluginstage_footer_tagline', 'pluginstage_footer_tagline', '' );
			$logo   = (string) $this->get_for_demo( '_pluginstage_footer_logo_url', 'pluginstage_footer_logo_url', '' );
			$social = (string) $this->get_for_demo( '_pluginstage_footer_social', 'pluginstage_footer_social', '' );
			$buy    = (string) $this->get_for_demo( '_pluginstage_footer_purchase_url', 'pluginstage_footer_purchase_url', '' );
			$name   = (string) get_option( 'pluginstage_public_name', 'PluginStage' );
			?>
			<div id="pluginstage-footer-bar" class="pluginstage-footer-bar">
				<div class="pluginstage-footer-bar__inner">
					<?php if ( $logo ) : ?>
						<img src="<?php echo esc_url( $logo ); ?>" alt="" class="pluginstage-footer-logo" />
					<?php endif; ?>
					<span class="pluginstage-footer-name"><?php echo esc_html( $name ); ?></span>
					<?php if ( $tag ) : ?>
						<span class="pluginstage-footer-tag"><?php echo wp_kses_post( $tag ); ?></span>
					<?php endif; ?>
					<?php if ( $social ) : ?>
						<span class="pluginstage-footer-social"><?php echo wp_kses_post( $social ); ?></span>
					<?php endif; ?>
					<?php if ( $buy ) : ?>
						<a class="pluginstage-footer-buy" href="<?php echo esc_url( $buy ); ?>"><?php esc_html_e( 'Purchase', 'pluginstage' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<?php
		}

		$cta_label = (string) $this->get_for_demo( '_pluginstage_cta_label', 'pluginstage_cta_label', '' );
		$cta_url   = (string) $this->get_for_demo( '_pluginstage_cta_url', 'pluginstage_cta_url', '' );
		$cta_bg    = (string) $this->get_for_demo( '_pluginstage_cta_bg', 'pluginstage_cta_bg', '#2271b1' );
		$pos       = (string) get_option( 'pluginstage_cta_position', 'bottom-right' );
		if ( $cta_label && $cta_url ) {
			$class = 'pluginstage-fab pluginstage-fab--' . sanitize_html_class( $pos );
			printf(
				'<a id="pluginstage-fab" class="%1$s" style="background-color:%2$s" href="%3$s">%4$s</a>',
				esc_attr( $class ),
				esc_attr( $cta_bg ),
				esc_url( $cta_url ),
				esc_html( $cta_label )
			);
		}
	}

	/**
	 * AJAX dismiss banner (session via user meta cleared on logout).
	 */
	public function ajax_dismiss_banner() {
		check_ajax_referer( 'pluginstage_banner', 'nonce' );
		if ( ! PluginStage_Access::instance()->is_demo_user() ) {
			wp_send_json_error( null, 403 );
		}
		update_user_meta( get_current_user_id(), 'pluginstage_banner_dismissed_session', 1 );
		wp_send_json_success();
	}
}
