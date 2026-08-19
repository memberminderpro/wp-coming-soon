<?php
/**
 * Settings schema, defaults, retrieval and sanitisation.
 *
 * Every value the plugin owns lives in one option row so that uninstall can
 * remove the plugin completely by deleting a single key.
 *
 * @package MMP_Coming_Soon
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings store.
 */
class MMPCS_Settings {

	/**
	 * Allowed button style variants.
	 *
	 * @var array<string,string>
	 */
	const BUTTON_STYLES = array(
		'gold'    => 'Gold',
		'ghost'   => 'Ghost',
		'navy'    => 'Navy',
		'crimson' => 'Crimson',
	);

	/**
	 * Runtime cache of the merged settings.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default settings. These reproduce the approved Etch design exactly.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'     => false,
			'allowlist'   => '',
			'auto_update' => true,
			'update_channel' => 'stable',

			'logo'      => array(
				'url'   => 'https://www.memberminderpro.com/wp-content/uploads/MMP-LOGO-WHITE.svg',
				'alt'   => 'Member Minder Pro',
				'link'  => 'https://memberminderpro.com/',
				'aria'  => 'Member Minder Pro home page',
				'width' => 260,
			),

			'badge_text'  => 'Website Under Development',
			'heading'     => 'Something Great is Being Built Here',
			'description' => "This site is being built on Member Minder Pro's managed WordPress platform — optimized for speed, security, and elite performance.",

			'buttons_main'    => array(
				array(
					'label' => 'Get Your Own WordPress Site',
					'url'   => 'https://www.memberminderpro.com/product/wordpress-hosting/',
					'style' => 'gold',
				),
				array(
					'label' => 'Book a Strategy Meeting',
					'url'   => 'https://calendly.com/d/2nj-psr-b7k/memberminderpro-sales-team-round-robin',
					'style' => 'ghost',
				),
			),
			'buttons_support' => array(
				array(
					'label' => 'Support for DACdb Customers',
					'url'   => 'https://www.dacdbsupport.com/',
					'style' => 'navy',
				),
				array(
					'label' => 'Support for iMembersDB Customers',
					'url'   => 'https://www.imemberssupport.com/',
					'style' => 'crimson',
				),
			),

			'footer' => array(
				'company_name' => 'Member Minder Pro, LLC',
				'company_url'  => 'https://memberminderpro.com/',
				'rights_text'  => 'All Rights Reserved.',
				'legal_links'  => array(
					array(
						'label' => 'Managed WordPress Hosting',
						'url'   => 'https://www.memberminderpro.com/product/wordpress-hosting/',
					),
				),
			),

			'palette' => array(
				'accent'      => '#c09641',
				'accent_hover' => '#a37f37',
				'ink'         => '#101c2d',
				'navy'        => '#1e224e',
				'crimson'     => '#841c30',
				'offwhite'    => '#f8f8f8',
			),

			'aurora' => array(
				'enabled'   => true,
				'motion'    => true,
				'base'      => '#010104',
				'colors'    => array( '#650c1d', '#ce3e57', '#c89c59', '#4d9f78', '#84aac7', '#5862a7' ),
				'size'      => 68,
				'blur'      => 72,
				'duration'  => 24,
				'intensity' => 0.82,
			),
		);
	}

	/**
	 * Read the merged settings.
	 *
	 * @return array
	 */
	public static function get() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = get_option( MMPCS_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$defaults = self::defaults();
		$merged   = $defaults;

		foreach ( $defaults as $key => $default ) {
			if ( ! array_key_exists( $key, $stored ) ) {
				continue;
			}

			// Repeaters replace wholesale; associative groups merge key by key.
			if ( is_array( $default ) && ! self::is_list( $default ) && is_array( $stored[ $key ] ) ) {
				$merged[ $key ] = array_merge( $default, $stored[ $key ] );
				continue;
			}

			$merged[ $key ] = $stored[ $key ];
		}

		self::$cache = $merged;

		return $merged;
	}

	/**
	 * Reset the runtime cache. Used after a save.
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * Is this array a sequential list rather than an associative map?
	 *
	 * @param array $value Array to test.
	 * @return bool
	 */
	private static function is_list( $value ) {
		if ( array() === $value ) {
			return true;
		}

		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * Sanitise the whole settings payload coming from the options form.
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();

		if ( ! is_array( $input ) ) {
			return $defaults;
		}

		$out = array();

		$out['enabled']     = ! empty( $input['enabled'] );
		$out['allowlist']   = isset( $input['allowlist'] ) ? self::sanitize_allowlist( $input['allowlist'] ) : '';
		$out['auto_update'] = ! empty( $input['auto_update'] );

		$channel                = isset( $input['update_channel'] ) ? sanitize_key( $input['update_channel'] ) : 'stable';
		$out['update_channel']  = isset( MMPCS_Updater::CHANNELS[ $channel ] ) ? $channel : 'stable';

		$logo             = isset( $input['logo'] ) && is_array( $input['logo'] ) ? $input['logo'] : array();
		$out['logo']      = array(
			'url'   => isset( $logo['url'] ) ? esc_url_raw( trim( $logo['url'] ) ) : '',
			'alt'   => isset( $logo['alt'] ) ? sanitize_text_field( $logo['alt'] ) : '',
			'link'  => isset( $logo['link'] ) ? esc_url_raw( trim( $logo['link'] ) ) : '',
			'aria'  => isset( $logo['aria'] ) ? sanitize_text_field( $logo['aria'] ) : '',
			'width' => isset( $logo['width'] ) ? self::clamp_int( $logo['width'], 40, 800, 260 ) : 260,
		);

		$out['badge_text']  = isset( $input['badge_text'] ) ? sanitize_text_field( $input['badge_text'] ) : '';
		$out['heading']     = isset( $input['heading'] ) ? sanitize_text_field( $input['heading'] ) : '';
		$out['description'] = isset( $input['description'] ) ? sanitize_textarea_field( $input['description'] ) : '';

		$out['buttons_main']    = self::sanitize_buttons( isset( $input['buttons_main'] ) ? $input['buttons_main'] : array() );
		$out['buttons_support'] = self::sanitize_buttons( isset( $input['buttons_support'] ) ? $input['buttons_support'] : array() );

		$footer         = isset( $input['footer'] ) && is_array( $input['footer'] ) ? $input['footer'] : array();
		$out['footer']  = array(
			'company_name' => isset( $footer['company_name'] ) ? sanitize_text_field( $footer['company_name'] ) : '',
			'company_url'  => isset( $footer['company_url'] ) ? esc_url_raw( trim( $footer['company_url'] ) ) : '',
			'rights_text'  => isset( $footer['rights_text'] ) ? sanitize_text_field( $footer['rights_text'] ) : '',
			'legal_links'  => self::sanitize_links( isset( $footer['legal_links'] ) ? $footer['legal_links'] : array() ),
		);

		$palette         = isset( $input['palette'] ) && is_array( $input['palette'] ) ? $input['palette'] : array();
		$out['palette']  = array();
		foreach ( $defaults['palette'] as $key => $fallback ) {
			$out['palette'][ $key ] = isset( $palette[ $key ] ) ? self::sanitize_color( $palette[ $key ], $fallback ) : $fallback;
		}

		$aurora = isset( $input['aurora'] ) && is_array( $input['aurora'] ) ? $input['aurora'] : array();
		$colors = array();
		if ( isset( $aurora['colors'] ) && is_array( $aurora['colors'] ) ) {
			foreach ( $aurora['colors'] as $color ) {
				$clean = self::sanitize_color( $color, '' );
				if ( '' !== $clean ) {
					$colors[] = $clean;
				}
			}
		}
		if ( empty( $colors ) ) {
			$colors = $defaults['aurora']['colors'];
		}

		$out['aurora'] = array(
			'enabled'   => ! empty( $aurora['enabled'] ),
			'motion'    => ! empty( $aurora['motion'] ),
			'base'      => isset( $aurora['base'] ) ? self::sanitize_color( $aurora['base'], $defaults['aurora']['base'] ) : $defaults['aurora']['base'],
			'colors'    => array_values( $colors ),
			'size'      => self::clamp_int( isset( $aurora['size'] ) ? $aurora['size'] : 68, 20, 140, 68 ),
			'blur'      => self::clamp_int( isset( $aurora['blur'] ) ? $aurora['blur'] : 72, 0, 200, 72 ),
			'duration'  => self::clamp_int( isset( $aurora['duration'] ) ? $aurora['duration'] : 24, 4, 180, 24 ),
			'intensity' => self::clamp_float( isset( $aurora['intensity'] ) ? $aurora['intensity'] : 0.82, 0.05, 1.0, 0.82 ),
		);

		self::flush_cache();

		return $out;
	}

	/**
	 * Sanitise a repeater of buttons.
	 *
	 * @param mixed $rows Raw rows.
	 * @return array
	 */
	private static function sanitize_buttons( $rows ) {
		$out = array();

		if ( ! is_array( $rows ) ) {
			return $out;
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			$url   = isset( $row['url'] ) ? esc_url_raw( trim( $row['url'] ) ) : '';
			$style = isset( $row['style'] ) ? sanitize_key( $row['style'] ) : 'ghost';

			// Drop empty rows entirely so the page never emits a bare anchor.
			if ( '' === $label || '' === $url ) {
				continue;
			}

			if ( ! array_key_exists( $style, self::BUTTON_STYLES ) ) {
				$style = 'ghost';
			}

			$out[] = array(
				'label' => $label,
				'url'   => $url,
				'style' => $style,
			);
		}

		return $out;
	}

	/**
	 * Sanitise a repeater of label/url links.
	 *
	 * @param mixed $rows Raw rows.
	 * @return array
	 */
	private static function sanitize_links( $rows ) {
		$out = array();

		if ( ! is_array( $rows ) ) {
			return $out;
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			$url   = isset( $row['url'] ) ? esc_url_raw( trim( $row['url'] ) ) : '';

			if ( '' === $label || '' === $url ) {
				continue;
			}

			$out[] = array(
				'label' => $label,
				'url'   => $url,
			);
		}

		return $out;
	}

	/**
	 * Sanitise the path allowlist textarea.
	 *
	 * @param mixed $raw Raw textarea value.
	 * @return string
	 */
	private static function sanitize_allowlist( $raw ) {
		if ( ! is_string( $raw ) ) {
			return '';
		}

		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$clean = array();

		foreach ( (array) $lines as $line ) {
			$line = trim( wp_strip_all_tags( $line ) );

			if ( '' === $line ) {
				continue;
			}

			// Accept a full URL by reducing it to its path.
			if ( preg_match( '#^https?://#i', $line ) ) {
				$path = wp_parse_url( $line, PHP_URL_PATH );
				$line = is_string( $path ) ? $path : '';
			}

			if ( '' === $line ) {
				continue;
			}

			if ( '/' !== substr( $line, 0, 1 ) ) {
				$line = '/' . $line;
			}

			$clean[] = $line;
		}

		return implode( "\n", array_unique( $clean ) );
	}

	/**
	 * Sanitise a CSS colour. Accepts hex, rgb(), rgba(), hsl(), and oklch().
	 *
	 * @param mixed  $value    Raw colour.
	 * @param string $fallback Value to use when the input is unusable.
	 * @return string
	 */
	public static function sanitize_color( $value, $fallback = '' ) {
		if ( ! is_string( $value ) ) {
			return $fallback;
		}

		$value = trim( $value );

		if ( '' === $value ) {
			return $fallback;
		}

		$hex = sanitize_hex_color( $value );
		if ( $hex ) {
			return $hex;
		}

		if ( preg_match( '/^(rgb|rgba|hsl|hsla|oklch|oklab|lab|lch|color)\(\s*[0-9a-z%.,\/\s+-]+\)$/i', $value ) ) {
			return $value;
		}

		return $fallback;
	}

	/**
	 * Clamp an integer.
	 *
	 * @param mixed $value    Raw value.
	 * @param int   $min      Minimum.
	 * @param int   $max      Maximum.
	 * @param int   $fallback Fallback when non-numeric.
	 * @return int
	 */
	private static function clamp_int( $value, $min, $max, $fallback ) {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		return (int) max( $min, min( $max, (int) $value ) );
	}

	/**
	 * Clamp a float.
	 *
	 * @param mixed $value    Raw value.
	 * @param float $min      Minimum.
	 * @param float $max      Maximum.
	 * @param float $fallback Fallback when non-numeric.
	 * @return float
	 */
	private static function clamp_float( $value, $min, $max, $fallback ) {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		return (float) max( $min, min( $max, (float) $value ) );
	}
}
