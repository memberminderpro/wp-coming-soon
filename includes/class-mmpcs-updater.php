<?php
/**
 * Self-hosted updates from GitHub releases.
 *
 * Plugs into WordPress's own update pipeline, so customer sites get the normal
 * "update available" badge and the normal update button — and, when automatic
 * updates are on, WordPress's own cron installs the release unattended.
 *
 * Deliberately reads a STATIC manifest (update.json) rather than the GitHub
 * releases API. That API allows 60 anonymous requests per hour per IP, which
 * customer sites sharing a host IP would exhaust; raw.githubusercontent.com is
 * CDN-served and has no comparable ceiling.
 *
 * @package MMP_Coming_Soon
 */

defined( 'ABSPATH' ) || exit;

/**
 * Update checker.
 */
class MMPCS_Updater {

	/**
	 * Update channels.
	 *
	 * Manifests are published to a dedicated "manifests" branch that no release
	 * branch ever merges into. Serving them from main and develop instead would
	 * mean a develop-to-main merge briefly advertised a beta build to every
	 * stable site.
	 *
	 * @var array<string,string>
	 */
	const CHANNELS = array(
		'stable' => 'stable',
		'beta'   => 'beta',
	);

	/**
	 * Transient prefix. The channel is appended, so switching channels cannot
	 * read a manifest cached for the other one.
	 */
	const CACHE_KEY = 'mmpcs_update_manifest';

	/**
	 * How long to trust a fetched manifest.
	 */
	const CACHE_TTL = 21600; // 6 hours.

	/**
	 * Hosts a package may be downloaded from. A manifest pointing anywhere else
	 * is ignored rather than trusted.
	 *
	 * @var string[]
	 */
	const ALLOWED_HOSTS = array(
		'github.com',
		'www.github.com',
		'objects.githubusercontent.com',
		'release-assets.githubusercontent.com',
		'codeload.github.com',
	);

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'inject' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_filter( 'auto_update_plugin', array( __CLASS__, 'auto_update' ), 10, 2 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'normalize_folder' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'flush' ), 10, 0 );
	}

	/**
	 * This plugin's basename, e.g. "mmp-coming-soon/mmp-coming-soon.php".
	 *
	 * @return string
	 */
	public static function basename() {
		return plugin_basename( MMPCS_FILE );
	}

	/**
	 * The plugin directory slug.
	 *
	 * @return string
	 */
	public static function slug() {
		return dirname( self::basename() );
	}

	/**
	 * The channel this site follows.
	 *
	 * A wp-config constant wins over the stored setting, so a site can be
	 * pinned to a channel regardless of what its administrator picks.
	 *
	 * @return string "stable" or "beta".
	 */
	public static function channel() {
		if ( defined( 'MMPCS_UPDATE_CHANNEL' ) ) {
			$forced = strtolower( (string) MMPCS_UPDATE_CHANNEL );

			if ( isset( self::CHANNELS[ $forced ] ) ) {
				return $forced;
			}
		}

		$settings = MMPCS_Settings::get();
		$channel  = isset( $settings['update_channel'] ) ? $settings['update_channel'] : 'stable';

		return isset( self::CHANNELS[ $channel ] ) ? $channel : 'stable';
	}

	/**
	 * Is the channel pinned from wp-config.php?
	 *
	 * @return bool
	 */
	public static function channel_is_forced() {
		return defined( 'MMPCS_UPDATE_CHANNEL' )
			&& isset( self::CHANNELS[ strtolower( (string) MMPCS_UPDATE_CHANNEL ) ] );
	}

	/**
	 * The cache key for the active channel.
	 *
	 * @return string
	 */
	private static function cache_key() {
		return self::CACHE_KEY . '_' . self::channel();
	}

	/**
	 * Where the manifest lives.
	 *
	 * @return string
	 */
	public static function manifest_url() {
		$channel = self::channel();
		$url     = str_replace( '{channel}', self::CHANNELS[ $channel ], MMPCS_UPDATE_MANIFEST );

		/**
		 * Filter the update manifest URL, for forks and private mirrors.
		 *
		 * @param string $url     Manifest URL.
		 * @param string $channel Active channel.
		 */
		return apply_filters( 'mmpcs_update_manifest_url', $url, $channel );
	}

	/**
	 * Resolve a manifest download URL, expanding the {version} placeholder.
	 *
	 * Keeping the version out of the stored URL means release-please only has
	 * to update one JSON field per release.
	 *
	 * @param array $manifest Manifest data.
	 * @return string
	 */
	public static function package_url( array $manifest ) {
		return str_replace( '{version}', $manifest['version'], $manifest['download_url'] );
	}

	/**
	 * Fetch the manifest, using the cache unless a fresh read is forced.
	 *
	 * @param bool $force Bypass the cache.
	 * @return array|null Manifest data, or null when unavailable.
	 */
	public static function manifest( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::cache_key() );

			if ( is_array( $cached ) ) {
				return isset( $cached['error'] ) ? null : $cached;
			}
		}

		$response = wp_remote_get(
			add_query_arg( 'cb', time(), self::manifest_url() ),
			array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			// Cache the failure briefly, so a broken endpoint cannot stall every
			// admin page load with a ten-second timeout.
			set_transient( self::cache_key(), array( 'error' => true ), 1800 );

			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || empty( $data['version'] ) || empty( $data['download_url'] ) ) {
			set_transient( self::cache_key(), array( 'error' => true ), 1800 );

			return null;
		}

		if ( ! self::host_allowed( str_replace( '{version}', $data['version'], $data['download_url'] ) ) ) {
			set_transient( self::cache_key(), array( 'error' => true ), 1800 );

			return null;
		}

		// Defence in depth: a manifest must declare the channel it belongs to,
		// so a misrouted or mis-published file can never move a stable site
		// onto a beta build.
		if ( isset( $data['channel'] ) && $data['channel'] !== self::channel() ) {
			set_transient( self::cache_key(), array( 'error' => true ), 1800 );

			return null;
		}

		$data['checked_at'] = time();

		set_transient( self::cache_key(), $data, self::CACHE_TTL );

		return $data;
	}

	/**
	 * Is the package URL somewhere we are willing to install code from?
	 *
	 * @param string $url Package URL.
	 * @return bool
	 */
	private static function host_allowed( $url ) {
		$parts = wp_parse_url( $url );

		if ( empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) ) {
			return false;
		}

		if ( empty( $parts['host'] ) ) {
			return false;
		}

		return in_array( strtolower( $parts['host'] ), self::ALLOWED_HOSTS, true );
	}

	/**
	 * Tell WordPress whether an update is waiting.
	 *
	 * @param mixed $transient The update_plugins site transient.
	 * @return mixed
	 */
	public static function inject( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		$manifest = self::manifest();

		if ( ! $manifest ) {
			return $transient;
		}

		$basename = self::basename();

		$item = (object) array(
			'id'           => self::manifest_url(),
			'slug'         => self::slug(),
			'plugin'       => $basename,
			'new_version'  => $manifest['version'],
			'url'          => isset( $manifest['homepage'] ) ? $manifest['homepage'] : '',
			'package'      => self::package_url( $manifest ),
			'icons'        => isset( $manifest['icons'] ) ? (array) $manifest['icons'] : array(),
			'banners'      => isset( $manifest['banners'] ) ? (array) $manifest['banners'] : array(),
			'banners_rtl'  => array(),
			'requires'     => isset( $manifest['requires'] ) ? $manifest['requires'] : '',
			'requires_php' => isset( $manifest['requires_php'] ) ? $manifest['requires_php'] : '',
			'tested'       => isset( $manifest['tested'] ) ? $manifest['tested'] : '',
		);

		if ( version_compare( $manifest['version'], MMPCS_VERSION, '>' ) ) {
			$transient->response[ $basename ] = $item;
			unset( $transient->no_update[ $basename ] );
		} else {
			// WordPress only offers the per-plugin "enable auto-updates" control
			// for plugins listed in no_update, so this branch matters.
			$transient->no_update[ $basename ] = $item;
			unset( $transient->response[ $basename ] );
		}

		return $transient;
	}

	/**
	 * Supply the "View details" modal.
	 *
	 * @param mixed  $result The result object or array.
	 * @param string $action The API action being performed.
	 * @param object $args   Request arguments.
	 * @return mixed
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( empty( $args->slug ) || self::slug() !== $args->slug ) {
			return $result;
		}

		$manifest = self::manifest();

		if ( ! $manifest ) {
			return $result;
		}

		return (object) array(
			'name'          => isset( $manifest['name'] ) ? $manifest['name'] : 'MMP Coming Soon',
			'slug'          => self::slug(),
			'version'       => $manifest['version'],
			'author'        => isset( $manifest['author'] ) ? $manifest['author'] : '',
			'homepage'      => isset( $manifest['homepage'] ) ? $manifest['homepage'] : '',
			'requires'      => isset( $manifest['requires'] ) ? $manifest['requires'] : '',
			'requires_php'  => isset( $manifest['requires_php'] ) ? $manifest['requires_php'] : '',
			'tested'        => isset( $manifest['tested'] ) ? $manifest['tested'] : '',
			'last_updated'  => isset( $manifest['last_updated'] ) ? $manifest['last_updated'] : '',
			'download_link' => self::package_url( $manifest ),
			'trunk'         => self::package_url( $manifest ),
			'sections'      => self::sections( $manifest ),
			'banners'       => isset( $manifest['banners'] ) ? (array) $manifest['banners'] : array(),
			'icons'         => isset( $manifest['icons'] ) ? (array) $manifest['icons'] : array(),
		);
	}

	/**
	 * Build the "View details" sections, folding in the released CHANGELOG.
	 *
	 * @param array $manifest Manifest data.
	 * @return array
	 */
	private static function sections( array $manifest ) {
		$sections = isset( $manifest['sections'] ) ? (array) $manifest['sections'] : array();

		if ( empty( $manifest['changelog_url'] ) ) {
			return $sections;
		}

		$response = wp_remote_get( $manifest['changelog_url'], array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return $sections;
		}

		$markdown = wp_remote_retrieve_body( $response );

		if ( '' !== trim( $markdown ) ) {
			$sections['changelog'] = self::render_markdown( $markdown );
		}

		return $sections;
	}

	/**
	 * Render the small subset of Markdown that release-please emits.
	 *
	 * Everything is escaped first, so the output cannot inject markup even if
	 * the changelog is tampered with upstream.
	 *
	 * @param string $markdown Raw Markdown.
	 * @return string HTML.
	 */
	private static function render_markdown( $markdown ) {
		$lines = preg_split( '/\r\n|\r|\n/', $markdown );
		$html  = '';
		$list  = false;

		foreach ( (array) $lines as $line ) {
			$line = esc_html( rtrim( $line ) );

			// Links, then inline code.
			$line = preg_replace( '/\[([^\]]+)\]\((https?:[^) ]+)\)/', '<a href="$2" rel="nofollow">$1</a>', $line );
			$line = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $line );

			if ( preg_match( '/^(#{1,6})\s+(.*)$/', $line, $m ) ) {
				if ( $list ) {
					$html .= '</ul>';
					$list  = false;
				}

				$level = min( 6, max( 3, strlen( $m[1] ) + 2 ) );
				$html .= '<h' . $level . '>' . $m[2] . '</h' . $level . '>';
				continue;
			}

			if ( preg_match( '/^\s*[*-]\s+(.*)$/', $line, $m ) ) {
				if ( ! $list ) {
					$html .= '<ul>';
					$list  = true;
				}

				$html .= '<li>' . $m[1] . '</li>';
				continue;
			}

			if ( $list ) {
				$html .= '</ul>';
				$list  = false;
			}

			if ( '' !== trim( $line ) ) {
				$html .= '<p>' . $line . '</p>';
			}
		}

		if ( $list ) {
			$html .= '</ul>';
		}

		return $html;
	}

	/**
	 * Opt this plugin into WordPress's unattended background updates.
	 *
	 * @param bool|null $update Whether to update.
	 * @param object    $item   The update offer.
	 * @return bool|null
	 */
	public static function auto_update( $update, $item ) {
		if ( empty( $item->plugin ) || self::basename() !== $item->plugin ) {
			return $update;
		}

		$settings = MMPCS_Settings::get();

		return ! empty( $settings['auto_update'] );
	}

	/**
	 * Keep the unpacked folder named after the plugin.
	 *
	 * Release assets built by bin/release.sh already unpack to the right folder,
	 * but a GitHub source archive unpacks to "repo-1.2.3", which WordPress would
	 * install alongside the original as a second copy.
	 *
	 * @param string      $source        Path to the unpacked source.
	 * @param string      $remote_source Path to the download.
	 * @param WP_Upgrader $upgrader      Upgrader instance.
	 * @param array       $args          Extra arguments.
	 * @return string|WP_Error
	 */
	public static function normalize_folder( $source, $remote_source, $upgrader, $args = array() ) {
		global $wp_filesystem;

		if ( empty( $args['plugin'] ) || self::basename() !== $args['plugin'] ) {
			return $source;
		}

		if ( ! $wp_filesystem ) {
			return $source;
		}

		$desired = trailingslashit( dirname( untrailingslashit( $source ) ) ) . self::slug();

		if ( untrailingslashit( $source ) === untrailingslashit( $desired ) ) {
			return $source;
		}

		if ( $wp_filesystem->exists( $desired ) ) {
			$wp_filesystem->delete( $desired, true );
		}

		if ( ! $wp_filesystem->move( $source, $desired ) ) {
			return new WP_Error(
				'mmpcs_rename_failed',
				__( 'Could not rename the downloaded package folder.', 'mmp-coming-soon' )
			);
		}

		return trailingslashit( $desired );
	}

	/**
	 * Drop the cached manifest.
	 *
	 * @return void
	 */
	public static function flush() {
		foreach ( array_keys( self::CHANNELS ) as $channel ) {
			delete_transient( self::CACHE_KEY . '_' . $channel );
		}
	}

	/**
	 * Force a fresh check and return what it found.
	 *
	 * @return array|null
	 */
	public static function check_now() {
		self::flush();

		$manifest = self::manifest( true );

		// Make WordPress re-evaluate its own update state too.
		delete_site_transient( 'update_plugins' );

		return $manifest;
	}
}
