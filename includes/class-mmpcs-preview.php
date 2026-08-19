<?php
/**
 * Live preview endpoint.
 *
 * Renders the coming soon page from settings that have been typed but not
 * saved, so the pane beside the settings form shows what a save would produce.
 *
 * The posted values go through MMPCS_Settings::sanitize() -- the same call the
 * options form uses -- and are then handed straight to the renderer. Nothing is
 * written. Reusing the sanitiser is the point: a preview built from raw input
 * would slowly drift away from what saving actually stores, and the drift would
 * only ever be discovered by someone trusting the preview and being wrong.
 *
 * @package MMP_Coming_Soon
 */

defined( 'ABSPATH' ) || exit;

/**
 * Unsaved-state preview.
 */
class MMPCS_Preview {

	/**
	 * The admin-post action, and the nonce that guards it.
	 */
	const ACTION = 'mmpcs_preview';

	/**
	 * Register hooks.
	 *
	 * Deliberately no admin_post_nopriv_ counterpart: there is nothing here for
	 * a logged-out request.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * The URL the preview form posts to.
	 *
	 * @return string
	 */
	public static function endpoint() {
		return add_query_arg( 'action', self::ACTION, admin_url( 'admin-post.php' ) );
	}

	/**
	 * Render the posted settings and stop.
	 *
	 * @return void
	 */
	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to preview this page.', 'mmp-coming-soon' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ACTION );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- sanitize() below is the sanitiser.
		$raw = isset( $_POST[ MMPCS_OPTION ] ) ? wp_unslash( $_POST[ MMPCS_OPTION ] ) : array();

		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		/*
		 * sanitize() emits every key it owns, filling anything absent with a
		 * default, and the settings form posts all of its tabs at once -- so
		 * the sanitised array is the whole picture. The merge only carries
		 * across the handful of keys sanitize() does not own, presets and the
		 * undo slot, keeping the array the shape the rest of the code expects.
		 */
		$settings = array_merge( MMPCS_Settings::get(), MMPCS_Settings::sanitize( $raw ) );

		if ( ! headers_sent() ) {
			status_header( 200 );
			header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
			header( 'X-Robots-Tag: noindex, nofollow', true );
			// Never let a preview of unsaved work be cached anywhere.
			nocache_headers();
			// It is framed by the settings screen, and only by that screen.
			header( 'X-Frame-Options: SAMEORIGIN', true );
		}

		echo MMPCS_Renderer::document( $settings, true ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- assembled from escaped parts.
		exit;
	}
}
