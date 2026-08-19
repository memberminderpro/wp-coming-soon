<?php
/**
 * Front-end gate.
 *
 * Decides whether a request should be answered with the coming soon page
 * instead of the site, then hands off to the renderer.
 *
 * @package MMP_Coming_Soon
 */

defined( 'ABSPATH' ) || exit;

/**
 * Request gate.
 */
class MMPCS_Frontend {

	/**
	 * Paths that are never gated, whatever the settings say. Keeping these
	 * hard-coded means an administrator cannot lock themselves out and the
	 * plugin never breaks logins, the REST API, feeds, or sitemaps.
	 *
	 * @var string[]
	 */
	const ALWAYS_OPEN = array(
		'/wp-admin',
		'/wp-login.php',
		'/wp-register.php',
		'/wp-signup.php',
		'/wp-activate.php',
		'/wp-cron.php',
		'/wp-json',
		'/xmlrpc.php',
		'/wp-content',
		'/wp-includes',
		'/robots.txt',
		'/favicon.ico',
		'/wp-sitemap',
		'/sitemap',
		'/.well-known',
	);

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ), 0 );
	}

	/**
	 * Render the coming soon page when the request should be gated.
	 *
	 * @return void
	 */
	public static function maybe_render() {
		if ( self::is_preview_request() ) {
			MMPCS_Renderer::render( true );
		}

		if ( ! self::should_gate() ) {
			return;
		}

		MMPCS_Renderer::render( false );
	}

	/**
	 * An administrator previewing the page while the gate is switched off.
	 *
	 * @return bool
	 */
	public static function is_preview_request() {
		if ( ! isset( $_GET['mmpcs_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		return (bool) wp_verify_nonce( $nonce, 'mmpcs_preview' );
	}

	/**
	 * Should this request be answered with the coming soon page?
	 *
	 * @return bool
	 */
	public static function should_gate() {
		$settings = MMPCS_Settings::get();

		$gate = true;

		if ( empty( $settings['enabled'] ) ) {
			$gate = false;
		} elseif ( is_user_logged_in() ) {
			// Signed-in users always get the real site, including the front
			// page registered in Settings -> Reading.
			$gate = false;
		} elseif ( self::is_system_request() ) {
			$gate = false;
		} elseif ( self::is_allowlisted( self::current_path() ) ) {
			$gate = false;
		}

		/**
		 * Filter the gate decision.
		 *
		 * @param bool $gate Whether to show the coming soon page.
		 */
		return (bool) apply_filters( 'mmpcs_should_gate', $gate );
	}

	/**
	 * Requests that must never be intercepted.
	 *
	 * @return bool
	 */
	private static function is_system_request() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return true;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		if ( is_feed() || is_robots() || is_trackback() ) {
			return true;
		}

		if ( function_exists( 'is_favicon' ) && is_favicon() ) {
			return true;
		}

		$path = self::current_path();

		foreach ( self::ALWAYS_OPEN as $prefix ) {
			if ( 0 === strpos( $path, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The current request path, normalised with a leading slash and no query.
	 *
	 * @return string
	 */
	public static function current_path() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$uri = is_string( $uri ) ? $uri : '/';

		$path = wp_parse_url( $uri, PHP_URL_PATH );
		$path = is_string( $path ) ? $path : '/';

		// Strip the subdirectory install prefix so allowlist entries are
		// written relative to the site root on every install layout.
		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( is_string( $home_path ) && '/' !== $home_path && 0 === strpos( $path, $home_path ) ) {
			$path = '/' . ltrim( substr( $path, strlen( $home_path ) ), '/' );
		}

		if ( '' === $path || '/' !== substr( $path, 0, 1 ) ) {
			$path = '/' . $path;
		}

		return $path;
	}

	/**
	 * Does the path match an allowlist entry? Supports a trailing "*" wildcard
	 * and ignores trailing-slash differences.
	 *
	 * @param string $path Normalised request path.
	 * @return bool
	 */
	public static function is_allowlisted( $path ) {
		$settings = MMPCS_Settings::get();
		$raw      = isset( $settings['allowlist'] ) ? $settings['allowlist'] : '';

		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return false;
		}

		$needle = untrailingslashit( $path );
		if ( '' === $needle ) {
			$needle = '/';
		}

		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $entry ) {
			$entry = trim( $entry );

			if ( '' === $entry ) {
				continue;
			}

			if ( '*' === substr( $entry, -1 ) ) {
				$prefix = untrailingslashit( rtrim( substr( $entry, 0, -1 ), '/' ) );

				if ( '' === $prefix || 0 === strpos( $needle, $prefix ) ) {
					return true;
				}

				continue;
			}

			if ( untrailingslashit( $entry ) === $needle ) {
				return true;
			}
		}

		return false;
	}
}
