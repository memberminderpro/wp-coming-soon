<?php
/**
 * Admin bar status button.
 *
 * A persistent, always-visible indicator of whether the coming soon page is
 * live, on every admin and front-end screen. It replaces a dismissible notice
 * that repeated itself on every page of the dashboard.
 *
 * @package MMP_Coming_Soon
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin bar integration.
 */
class MMPCS_Admin_Bar {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		// Admin bar order is insertion order, not hook priority, so adding the
		// node on admin_bar_menu leaves it wherever this plugin happens to load
		// relative to others. wp_before_admin_bar_render fires once every other
		// node is registered, so appending here puts the button last in the
		// left-hand group regardless of plugin load order.
		add_action( 'wp_before_admin_bar_render', array( __CLASS__, 'render_node' ), PHP_INT_MAX );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Should the button be shown to this user?
	 *
	 * @return bool
	 */
	private static function visible() {
		return is_admin_bar_showing() && current_user_can( 'manage_options' );
	}

	/**
	 * Append the button once every other node exists.
	 *
	 * @return void
	 */
	public static function render_node() {
		global $wp_admin_bar;

		if ( ! $wp_admin_bar instanceof WP_Admin_Bar ) {
			return;
		}

		self::add_node( $wp_admin_bar );
	}

	/**
	 * Add the button.
	 *
	 * @param WP_Admin_Bar $bar Admin bar instance.
	 * @return void
	 */
	public static function add_node( $bar ) {
		if ( ! self::visible() ) {
			return;
		}

		$settings = MMPCS_Settings::get();
		$enabled  = ! empty( $settings['enabled'] );

		$title = $enabled
			? __( 'Coming Soon is live. Visitors who are not signed in see the holding page.', 'mmp-coming-soon' )
			: __( 'Coming Soon is off. Visitors see your site.', 'mmp-coming-soon' );

		$bar->add_node(
			array(
				'id'    => 'mmpcs-status',
				'title' => '<span class="ab-icon" aria-hidden="true"></span><span class="ab-label">'
					. esc_html__( 'Coming Soon', 'mmp-coming-soon' ) . '</span>',
				'href'  => admin_url( 'admin.php?page=' . MMPCS_MENU_SLUG ),
				'meta'  => array(
					'class' => 'mmpcs-ab ' . ( $enabled ? 'mmpcs-ab--on' : 'mmpcs-ab--off' ),
					'title' => $title,
				),
			)
		);

	}

	/**
	 * Load the button's styles wherever the admin bar renders.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! self::visible() ) {
			return;
		}

		wp_enqueue_style(
			'mmpcs-admin-bar',
			MMPCS_URL . 'assets/css/admin-bar.css',
			array( 'admin-bar' ),
			MMPCS_VERSION
		);
	}
}
