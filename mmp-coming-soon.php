<?php
/**
 * Plugin Name:       MMP Coming Soon
 * Plugin URI:        https://memberminderpro.com/
 * Description:       A self-contained coming soon page with an animated aurora background. Replaces the front end for anonymous visitors while the site is being built. No theme, plugin, or framework dependencies.
 * x-release-please-start-version
 * Version:           1.2.0-beta.1
 * x-release-please-end
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Member Minder Pro, LLC
 * Author URI:        https://memberminderpro.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mmp-coming-soon
 * Update URI:        https://github.com/memberminderpro/wp-coming-soon
 *
 * @package MMP_Coming_Soon
 */

defined( 'ABSPATH' ) || exit;

// x-release-please-start-version
define( 'MMPCS_VERSION', '1.2.0-beta.1' );
// x-release-please-end
define( 'MMPCS_FILE', __FILE__ );
define( 'MMPCS_DIR', plugin_dir_path( __FILE__ ) );
define( 'MMPCS_URL', plugin_dir_url( __FILE__ ) );
define( 'MMPCS_OPTION', 'mmpcs_settings' );

/**
 * The options page slug. Defined here rather than on MMPCS_Admin because the
 * admin bar renders on the front end too, where that class is not loaded.
 */
define( 'MMPCS_MENU_SLUG', 'mmp-coming-soon' );

/**
 * Where update checks look. {channel} becomes "stable" or "beta".
 *
 * Manifests live on a dedicated "manifests" branch that main and develop never
 * merge into, so a develop-to-main merge can never briefly advertise a beta
 * build to stable sites. Change this one line when forking or renaming the
 * repository; nothing else references the repo. The plugin header's Update URI
 * must point at the same repository, so that WordPress.org can never serve an
 * unrelated plugin sharing this slug.
 */
if ( ! defined( 'MMPCS_UPDATE_MANIFEST' ) ) {
	define( 'MMPCS_UPDATE_MANIFEST', 'https://raw.githubusercontent.com/memberminderpro/wp-coming-soon/manifests/{channel}.json' );
}

/**
 * Optional: force a site onto an update channel from wp-config.php, overriding
 * whatever the options page says.
 *
 *     define( 'MMPCS_UPDATE_CHANNEL', 'beta' );
 */

require_once MMPCS_DIR . 'includes/class-mmpcs-settings.php';
require_once MMPCS_DIR . 'includes/class-mmpcs-aurora.php';
require_once MMPCS_DIR . 'includes/class-mmpcs-renderer.php';
require_once MMPCS_DIR . 'includes/class-mmpcs-frontend.php';
require_once MMPCS_DIR . 'includes/class-mmpcs-updater.php';
require_once MMPCS_DIR . 'includes/class-mmpcs-adminbar.php';

if ( is_admin() ) {
	require_once MMPCS_DIR . 'includes/class-mmpcs-admin.php';
	require_once MMPCS_DIR . 'includes/class-mmpcs-tools.php';
}

/**
 * Boot the plugin.
 *
 * @return void
 */
function mmpcs_bootstrap() {
	MMPCS_Frontend::init();
	MMPCS_Updater::init();
	MMPCS_Admin_Bar::init();

	if ( is_admin() ) {
		MMPCS_Admin::init();
		MMPCS_Tools::init();
	}
}
add_action( 'plugins_loaded', 'mmpcs_bootstrap' );

/**
 * Deactivation: drop caches, but keep settings so reactivating restores them.
 * Full data removal happens in uninstall.php when the plugin is deleted.
 *
 * @return void
 */
function mmpcs_on_deactivate() {
	wp_cache_delete( MMPCS_OPTION, 'options' );
	delete_transient( MMPCS_Updater::CACHE_KEY );
}
register_deactivation_hook( __FILE__, 'mmpcs_on_deactivate' );
