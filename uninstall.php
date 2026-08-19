<?php
/**
 * Uninstall — remove every trace of the plugin.
 *
 * The plugin deliberately owns exactly one option row and creates no posts,
 * pages, taxonomies, custom tables, uploaded files, user meta, or cron events.
 * That design is what makes a complete removal possible here: deleting the
 * option (on every site of a network) leaves nothing behind. The defensive
 * sweeps below catch anything a future version might add, and any orphaned
 * transients left by a crashed request.
 *
 * @package MMP_Coming_Soon
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove all plugin data for the current site.
 *
 * @return void
 */
function mmpcs_purge_site() {
	global $wpdb;

	delete_option( 'mmpcs_settings' );

	// Any option or transient this plugin could have created.
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE 'mmpcs\_%'
			OR option_name LIKE '\_transient\_mmpcs\_%'
			OR option_name LIKE '\_transient\_timeout\_mmpcs\_%'"
	);

	// User meta, in case a future version stores a per-user dismissal.
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'mmpcs\_%'"
	);

	// Scheduled events, in case a future version registers one.
	$crons = _get_cron_array();

	if ( is_array( $crons ) ) {
		foreach ( $crons as $timestamp => $hooks ) {
			if ( ! is_array( $hooks ) ) {
				continue;
			}

			foreach ( $hooks as $hook => $events ) {
				if ( 0 === strpos( (string) $hook, 'mmpcs_' ) ) {
					wp_clear_scheduled_hook( $hook );
				}
			}
		}
	}
}

if ( is_multisite() ) {
	$mmpcs_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $mmpcs_sites as $mmpcs_site_id ) {
		switch_to_blog( $mmpcs_site_id );
		mmpcs_purge_site();
		restore_current_blog();
	}

	delete_site_option( 'mmpcs_settings' );
	delete_site_transient( 'mmpcs_settings' );
} else {
	mmpcs_purge_site();
}

wp_cache_flush();
