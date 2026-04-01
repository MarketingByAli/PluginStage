<?php
/**
 * Email blocking, uploads, IP rate limit and bans.
 *
 * @package PluginStage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PluginStage_Security
 */
class PluginStage_Security {

	/**
	 * Instance.
	 *
	 * @var PluginStage_Security|null
	 */
	private static $instance = null;

	/**
	 * Instance.
	 *
	 * @return PluginStage_Security
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
		add_filter( 'pre_wp_mail', array( $this, 'block_demo_mail' ), 10, 2 );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'upload_prefilter' ) );
		add_filter( 'upload_mimes', array( $this, 'filter_mimes_demo' ), 10, 2 );
	}

	/**
	 * Block outgoing mail when initiated in a demo user context.
	 *
	 * @param null|bool $short_circuit Short circuit.
	 * @param array     $atts          Mail atts.
	 * @return null|bool
	 */
	public function block_demo_mail( $short_circuit, $atts ) {
		unset( $atts );
		if ( ! is_user_logged_in() ) {
			return $short_circuit;
		}
		if ( PluginStage_Access::instance()->is_demo_user() ) {
			return false;
		}
		return $short_circuit;
	}

	/**
	 * Whether IP is banned (list in option).
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	public function is_ip_banned( $ip ) {
		$ip = sanitize_text_field( $ip );
		if ( '' === $ip ) {
			return false;
		}
		$list = $this->get_banned_ips();
		return in_array( $ip, $list, true );
	}

	/**
	 * Parsed banned IPs.
	 *
	 * @return string[]
	 */
	public function get_banned_ips() {
		$raw  = (string) get_option( 'pluginstage_banned_ips', '' );
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$out   = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( $line ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	/**
	 * Record failed/abusive event for auto-ban threshold.
	 *
	 * @param string $ip IP.
	 */
	public function record_abuse_event( $ip ) {
		$threshold = (int) get_option( 'pluginstage_auto_ban_threshold', 0 );
		if ( $threshold <= 0 ) {
			return;
		}
		$key = 'pluginstage_abuse_' . md5( $ip );
		$n   = (int) get_transient( $key );
		++$n;
		set_transient( $key, $n, DAY_IN_SECONDS );
		if ( $n >= $threshold ) {
			$this->ban_ip( $ip );
			delete_transient( $key );
		}
	}

	/**
	 * Append IP to banned list.
	 *
	 * @param string $ip IP.
	 */
	public function ban_ip( $ip ) {
		$ip = sanitize_text_field( $ip );
		if ( '' === $ip || $this->is_ip_banned( $ip ) ) {
			return;
		}
		$list   = $this->get_banned_ips();
		$list[] = $ip;
		update_option( 'pluginstage_banned_ips', implode( "\n", array_unique( $list ) ), false );
	}

	/**
	 * Upload size/type for demo users.
	 *
	 * @param array $file File array.
	 * @return array
	 */
	public function upload_prefilter( $file ) {
		if ( ! PluginStage_Access::instance()->is_demo_user() ) {
			return $file;
		}
		$max = (int) get_option( 'pluginstage_upload_max_bytes', 1048576 );
		if ( $max > 0 && isset( $file['size'] ) && (int) $file['size'] > $max ) {
			$file['error'] = sprintf(
				/* translators: %s human size */
				__( 'File exceeds demo upload limit (%s).', 'pluginstage' ),
				size_format( $max )
			);
		}
		return $file;
	}

	/**
	 * Restrict MIME types for demo users.
	 *
	 * @param array         $mimes Mimes.
	 * @param WP_User|false $user  User.
	 * @return array
	 */
	public function filter_mimes_demo( $mimes, $user = null ) {
		unset( $user );
		if ( ! PluginStage_Access::instance()->is_demo_user() ) {
			return $mimes;
		}
		$allowed = $this->get_allowed_extensions();
		if ( empty( $allowed ) ) {
			return $mimes;
		}
		$out = array();
		foreach ( $mimes as $ext => $mime ) {
			if ( in_array( strtolower( $ext ), $allowed, true ) ) {
				$out[ $ext ] = $mime;
			}
		}
		return $out;
	}

	/**
	 * Allowed extensions from settings.
	 *
	 * @return string[]
	 */
	public function get_allowed_extensions() {
		$raw = (string) get_option( 'pluginstage_upload_mimes', 'jpg,jpeg,png,gif,webp,pdf' );
		$parts = array_map( 'trim', explode( ',', $raw ) );
		$out   = array();
		foreach ( $parts as $p ) {
			$p = strtolower( preg_replace( '/[^a-z0-9]/', '', $p ) );
			if ( $p ) {
				$out[] = $p;
			}
		}
		return array_unique( $out );
	}
}
