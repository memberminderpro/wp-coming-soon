<?php
/**
 * Page cache purging.
 *
 * Switching the gate on changes what every anonymous visitor should see, but a
 * page cache holding a copy of the real site will keep serving it without ever
 * running PHP -- so the plugin never gets the chance to intervene. The site
 * looks live to the world while the settings screen says it is not, which is
 * the most alarming way for this to fail.
 *
 * Observed in the wild on a managed host: the homepage answered from the host's
 * cache with "x-cache-status: STALE", while the same URL with a query string
 * appended -- a cache miss -- returned the coming soon page correctly.
 *
 * So flipping the switch purges what it can reach. Nothing here can promise to
 * clear a cache that lives in front of PHP entirely; the settings screen says
 * so, and the mmpcs_purge_caches action is there for hosts that expose their
 * own way.
 *
 * @package MMP_Coming_Soon
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cache purging.
 */
class MMPCS_Cache {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'update_option_' . MMPCS_OPTION, array( __CLASS__, 'on_settings_change' ), 10, 2 );
	}

	/**
	 * Does a settings change alter what anonymous visitors are served?
	 *
	 * Only two things do: the switch itself, and the allowlist -- everything
	 * else changes what the coming soon page looks like, not who sees it. A
	 * pure comparison so it can be tested without a cache plugin present.
	 *
	 * @param mixed $old Settings before the write.
	 * @param mixed $new Settings after the write.
	 * @return bool
	 */
	public static function should_purge( $old, $new ) {
		$old = is_array( $old ) ? $old : array();
		$new = is_array( $new ) ? $new : array();

		$was_on = ! empty( $old['enabled'] );
		$is_on  = ! empty( $new['enabled'] );

		if ( $was_on !== $is_on ) {
			return true;
		}

		$old_list = isset( $old['allowlist'] ) ? (string) $old['allowlist'] : '';
		$new_list = isset( $new['allowlist'] ) ? (string) $new['allowlist'] : '';

		return $old_list !== $new_list;
	}

	/**
	 * Purge after a change that alters who sees what.
	 *
	 * @param mixed $old Settings before the write.
	 * @param mixed $new Settings after the write.
	 * @return void
	 */
	public static function on_settings_change( $old, $new ) {
		if ( self::should_purge( $old, $new ) ) {
			self::purge();
		}
	}

	/**
	 * Ask every page cache we know how to ask.
	 *
	 * Deliberately not wp_cache_flush(): that is the object cache, which is not
	 * what serves a stale page, and on shared Redis it can empty a cache other
	 * sites are using. The page cache is the one that matters here.
	 *
	 * @return void
	 */
	public static function purge() {
		// LiteSpeed, Nginx Helper, Breeze, Cache Enabler, Swift: all listen for
		// an action rather than exposing a function.
		do_action( 'litespeed_purge_all' );
		do_action( 'rt_nginx_helper_purge_all' );
		do_action( 'breeze_clear_all_cache' );
		do_action( 'cache_enabler_clear_complete_cache' );
		do_action( 'swift_performance_clear_all_cache' );

		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			// WP Super Cache.
			wp_cache_clear_cache();
		}

		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}

		if ( class_exists( 'WpeCommon' ) && method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
			WpeCommon::purge_varnish_cache();
		}

		/**
		 * Purge anything else in front of this site.
		 *
		 * Fires when the coming soon switch or the allowlist changes -- the two
		 * changes that alter what an anonymous visitor should be served. Hosts
		 * with their own cache can hook this.
		 */
		do_action( 'mmpcs_purge_caches' );
	}
}
