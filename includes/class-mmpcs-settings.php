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
	 * Where a logo may be placed. There is no "main" logo: every logo is a row
	 * in one repeater and can sit in any slot, which is what lets a site build
	 * a sponsor or partner block anywhere on the page.
	 *
	 * @var array<string,string>
	 */
	const LOGO_POSITIONS = array(
		'top'               => 'Top of the page',
		'after_badge'       => 'Below the badge',
		'after_heading'     => 'Below the heading',
		'after_description' => 'Below the description',
		'after_buttons'     => 'Below the buttons',
		'above_footer'      => 'Above the footer text',
	);

	/**
	 * How several logos sharing one slot are arranged.
	 *
	 * @var array<string,string>
	 */
	const LOGO_LAYOUTS = array(
		'row'   => 'Side by side',
		'stack' => 'Stacked',
	);

	/**
	 * Settings grouped by the tab that owns them. Drives the per-section reset
	 * controls, and defines what a preset or an export file carries.
	 *
	 * @var array<string,string[]>
	 */
	const SECTIONS = array(
		'content'    => array( 'logos', 'logo_layout', 'badge_text', 'heading', 'description' ),
		'buttons'    => array( 'buttons_main', 'buttons_support' ),
		'footer'     => array( 'footer' ),
		'background' => array( 'aurora' ),
		'colors'     => array( 'palette' ),
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

			// Saved variations. Recent history lives in its own option, since
			// this row is read on every front-end request the gate evaluates.
			'presets'        => array(),

			/*
			 * Every logo is a row here, ordered by the repeater. Row order is
			 * display order within a slot, so no separate weight is stored.
			 */
			'logos' => array(
				array(
					'url'      => 'https://www.memberminderpro.com/wp-content/uploads/MMP-LOGO-WHITE.svg',
					'alt'      => 'Member Minder Pro',
					'aria'     => 'Member Minder Pro home page',
					'link'     => 'https://memberminderpro.com/',
					'width'    => 260,
					'position' => 'top',
				),
			),

			/*
			 * Arrangement per slot, consulted only where a slot holds more than
			 * one logo. Keyed by position so two slots can differ.
			 */
			'logo_layout' => array(),

			'badge_text'  => 'Website Under Development',
			'heading'     => 'Something Great is Being Built Here',
			'description' => "This site is being built on Member Minder Pro's managed WordPress platform — optimized for speed, security, and elite performance.",

			'buttons_main'    => array(
				array(
					'name'  => 'Get Your Own WordPress Site',
					'url'   => 'https://www.memberminderpro.com/product/wordpress-hosting/',
					'style' => 'gold',
				),
				array(
					'name'  => 'Book a Strategy Meeting',
					'url'   => 'https://calendly.com/d/2nj-psr-b7k/memberminderpro-sales-team-round-robin',
					'style' => 'ghost',
				),
			),
			'buttons_support' => array(
				array(
					'name'  => 'Support for DACdb Customers',
					'url'   => 'https://www.dacdbsupport.com/',
					'style' => 'navy',
				),
				array(
					'name'  => 'Support for iMembersDB Customers',
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

		$stored = self::migrate_legacy_logos( $stored );
		$stored = self::migrate_legacy_buttons( $stored );

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
	 * Every key a preset or an export file carries.
	 *
	 * Deliberately excludes the gate switch, the allowlist, the update channel
	 * and the auto-update flag. Those describe how one site behaves, not how the
	 * page looks: importing a design must never silently take a site offline or
	 * move it onto a different update channel.
	 *
	 * @return string[]
	 */
	public static function portable_keys() {
		$keys = array();

		foreach ( self::SECTIONS as $section_keys ) {
			$keys = array_merge( $keys, $section_keys );
		}

		return $keys;
	}

	/**
	 * The portable subset of the current settings.
	 *
	 * @return array
	 */
	public static function portable() {
		$settings = self::get();
		$out      = array();

		foreach ( self::portable_keys() as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				$out[ $key ] = $settings[ $key ];
			}
		}

		return $out;
	}

	/**
	 * Write settings, preserving the things the options form does not own.
	 *
	 * @param array $settings Full settings.
	 * @return void
	 */
	private static function write( array $settings ) {
		$presets = isset( $settings['presets'] ) ? $settings['presets'] : array();

		$clean            = self::sanitize( $settings );
		$clean['presets'] = self::sanitize_presets( $presets );

		update_option( MMPCS_OPTION, $clean );
		self::flush_cache();
	}




	/**
	 * Reset one section, or everything, to the shipped defaults.
	 *
	 * @param string $section A key of SECTIONS, or "all".
	 * @return bool Whether anything was reset.
	 */
	public static function reset( $section ) {
		$defaults = self::defaults();
		$current  = self::get();

		if ( 'all' === $section ) {
			// Presets are the administrator's own work, not configuration, so
			// a reset must not destroy them. History is a separate option and
			// is not touched by this at all.
			$defaults['presets'] = $current['presets'];

			self::write( $defaults );

			return true;
		}

		if ( ! isset( self::SECTIONS[ $section ] ) ) {
			return false;
		}

		$settings = self::get();

		foreach ( self::SECTIONS[ $section ] as $key ) {
			$settings[ $key ] = $defaults[ $key ];
		}

		self::write( $settings );

		return true;
	}

	/**
	 * Apply a portable payload over the current settings.
	 *
	 * @param array $payload Portable settings.
	 * @return bool Whether anything was applied.
	 */
	public static function apply_portable( array $payload ) {
		// An export or preset written before the repeater carries the old keys.
		$payload = self::migrate_legacy_logos( $payload );
		$payload = self::migrate_legacy_buttons( $payload );

		$allowed = self::portable_keys();
		$usable  = array_intersect_key( $payload, array_flip( $allowed ) );

		if ( empty( $usable ) ) {
			return false;
		}

		$settings = self::get();

		foreach ( $usable as $key => $value ) {
			$settings[ $key ] = $value;
		}

		self::write( $settings );

		return true;
	}

	/**
	 * Save the current design as a named preset, replacing one of the same name.
	 *
	 * @param string $name Preset name.
	 * @return void
	 */
	public static function save_preset( $name ) {
		$name = sanitize_text_field( $name );

		if ( '' === $name ) {
			return;
		}

		$settings = self::get();
		$presets  = array();

		foreach ( $settings['presets'] as $preset ) {
			if ( isset( $preset['name'] ) && strtolower( $preset['name'] ) !== strtolower( $name ) ) {
				$presets[] = $preset;
			}
		}

		$presets[] = array(
			'name'     => $name,
			'saved_at' => time(),
			'data'     => self::portable(),
		);

		$settings['presets'] = $presets;

		self::write( $settings );
	}

	/**
	 * Delete a preset by name.
	 *
	 * @param string $name Preset name.
	 * @return void
	 */
	/**
	 * Stamp a preset with the moment it was applied.
	 *
	 * Kept on the preset rather than inferred from the undo slot, because the
	 * undo slot holds one change and is cleared once used -- it cannot answer
	 * "when was this preset last applied" for a list of them.
	 *
	 * @param string $name Preset name.
	 * @return void
	 */
	public static function mark_preset_applied( $name ) {
		$settings = self::get();
		$touched  = false;

		foreach ( $settings['presets'] as $index => $preset ) {
			if ( isset( $preset['name'] ) && 0 === strcasecmp( $preset['name'], $name ) ) {
				$settings['presets'][ $index ]['applied_at'] = time();
				$touched                                     = true;
			}
		}

		if ( $touched ) {
			self::write( $settings );
		}
	}

	public static function delete_preset( $name ) {
		$settings = self::get();
		$presets  = array();

		foreach ( $settings['presets'] as $preset ) {
			if ( isset( $preset['name'] ) && $preset['name'] !== $name ) {
				$presets[] = $preset;
			}
		}

		$settings['presets'] = $presets;

		self::write( $settings );
	}

	/**
	 * Find a preset by name.
	 *
	 * @param string $name Preset name.
	 * @return array|false
	 */
	public static function get_preset( $name ) {
		foreach ( self::get()['presets'] as $preset ) {
			if ( isset( $preset['name'] ) && $preset['name'] === $name ) {
				return $preset;
			}
		}

		return false;
	}

	/**
	 * Sanitise the saved preset collection.
	 *
	 * @param mixed $presets Raw presets.
	 * @return array
	 */
	public static function sanitize_presets( $presets ) {
		$out = array();

		foreach ( (array) $presets as $preset ) {
			if ( ! is_array( $preset ) || empty( $preset['name'] ) ) {
				continue;
			}

			$name = sanitize_text_field( $preset['name'] );

			if ( '' === $name ) {
				continue;
			}

			$data = isset( $preset['data'] ) && is_array( $preset['data'] ) ? $preset['data'] : array();

			$out[] = array(
				'name'     => $name,
				'saved_at' => isset( $preset['saved_at'] ) ? (int) $preset['saved_at'] : time(),
				// Zero until it has been applied from the settings screen.
				'applied_at' => isset( $preset['applied_at'] ) ? (int) $preset['applied_at'] : 0,
				'data'     => array_intersect_key( $data, array_flip( self::portable_keys() ) ),
			);
		}

		return $out;
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

		$out['logos']       = self::sanitize_logos( isset( $input['logos'] ) ? $input['logos'] : array() );
		$out['logo_layout'] = self::sanitize_logo_layout( isset( $input['logo_layout'] ) ? $input['logo_layout'] : array() );

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

		/*
		 * Presets never come from the options form, so when
		 * this runs as the form's sanitise callback they have to be carried
		 * over from what is stored, or saving the form would drop them.
		 *
		 * But this also runs on every other write. register_setting() hooks it
		 * to sanitize_option_mmpcs_settings, so update_option() calls it again
		 * on whatever write() just built -- and reading the stored value there
		 * means reading the value from *before* the write. Taking the stored
		 * copy unconditionally therefore threw away every preset at the moment
		 * it was saved: the handler reported success, and nothing persisted.
		 * The same fault destroyed the undo slot, now replaced by MMPCS_History.
		 *
		 * So the input wins when it carries them, and the stored copy is the
		 * fallback for when it does not.
		 */
		$existing = get_option( MMPCS_OPTION, array() );
		$existing = is_array( $existing ) ? $existing : array();

		$presets = array_key_exists( 'presets', $input ) ? $input['presets'] : ( isset( $existing['presets'] ) ? $existing['presets'] : array() );

		$out['presets'] = self::sanitize_presets( $presets );

		self::flush_cache();

		return $out;
	}

	/**
	 * Sanitise a repeater of buttons.
	 *
	 * @param mixed $rows Raw rows.
	 * @return array
	 */
	/**
	 * Sanitise the logo repeater.
	 *
	 * A row with no image URL is dropped rather than stored empty, so removing
	 * the URL is the same gesture as deleting the row.
	 *
	 * @param mixed $rows Raw repeater input.
	 * @return array
	 */
	private static function sanitize_logos( $rows ) {
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$url = isset( $row['url'] ) ? esc_url_raw( trim( $row['url'] ) ) : '';

			if ( '' === $url ) {
				continue;
			}

			$position = isset( $row['position'] ) ? sanitize_key( $row['position'] ) : 'top';

			$out[] = array(
				'url'   => $url,
				'alt'   => isset( $row['alt'] ) ? sanitize_text_field( $row['alt'] ) : '',
				// The ARIA label names the link's destination; alt describes the
				// image. They are different jobs, so they stay separate fields.
				'aria'  => isset( $row['aria'] ) ? sanitize_text_field( $row['aria'] ) : '',
				'link'  => isset( $row['link'] ) ? esc_url_raw( trim( $row['link'] ) ) : '',
				'width' => isset( $row['width'] ) ? self::clamp_int( $row['width'], 40, 800, 200 ) : 200,
				// An unknown slot falls back rather than rendering nowhere.
				'position' => isset( self::LOGO_POSITIONS[ $position ] ) ? $position : 'top',
			);
		}

		return $out;
	}

	/**
	 * Sanitise the per-slot arrangement map.
	 *
	 * @param mixed $map Raw input, keyed by position.
	 * @return array<string,string>
	 */
	private static function sanitize_logo_layout( $map ) {
		if ( ! is_array( $map ) ) {
			return array();
		}

		$out = array();

		foreach ( $map as $position => $layout ) {
			$position = sanitize_key( $position );
			$layout   = sanitize_key( $layout );

			if ( ! isset( self::LOGO_POSITIONS[ $position ] ) || ! isset( self::LOGO_LAYOUTS[ $layout ] ) ) {
				continue;
			}

			// "row" is the default, so storing it would be noise.
			if ( 'row' === $layout ) {
				continue;
			}

			$out[ $position ] = $layout;
		}

		return $out;
	}

	/**
	 * Fold the pre-repeater logo settings into the logos list.
	 *
	 * Up to 1.4.0-beta.2 there was a fixed "logo" and an optional
	 * "logo_secondary". Both become ordinary rows: the old primary keeps the
	 * top slot it always rendered in, and the old secondary keeps whatever slot
	 * it was assigned. Runs on read and on import, is idempotent, and writes
	 * nothing -- the legacy keys simply stop being persisted at the next save,
	 * because sanitize() no longer emits them.
	 *
	 * @param array $stored Stored or imported settings.
	 * @return array
	 */
	public static function migrate_legacy_logos( array $stored ) {
		$has_legacy = isset( $stored['logo'] ) || isset( $stored['logo_secondary'] );

		if ( ! $has_legacy ) {
			return $stored;
		}

		// A payload carrying both shapes has already been migrated; the legacy
		// keys are leftovers and the list wins.
		if ( isset( $stored['logos'] ) && is_array( $stored['logos'] ) ) {
			unset( $stored['logo'], $stored['logo_secondary'] );

			return $stored;
		}

		$logos = array();

		foreach ( array( 'logo' => 'top', 'logo_secondary' => 'after_description' ) as $key => $fallback ) {
			if ( empty( $stored[ $key ]['url'] ) ) {
				continue;
			}

			$legacy   = $stored[ $key ];
			$position = isset( $legacy['position'] ) ? sanitize_key( $legacy['position'] ) : $fallback;

			$logos[] = array(
				'url'   => $legacy['url'],
				'alt'   => isset( $legacy['alt'] ) ? $legacy['alt'] : '',
				// The secondary logo had no ARIA field and reused its alt text.
				'aria'  => isset( $legacy['aria'] ) ? $legacy['aria'] : '',
				'link'  => isset( $legacy['link'] ) ? $legacy['link'] : '',
				'width' => isset( $legacy['width'] ) ? $legacy['width'] : 200,
				'position' => isset( self::LOGO_POSITIONS[ $position ] ) ? $position : $fallback,
			);
		}

		$stored['logos'] = $logos;
		unset( $stored['logo'], $stored['logo_secondary'] );

		return $stored;
	}

	/**
	 * Fold pre-name button rows into the name/label shape.
	 *
	 * Up to 1.4.0-beta.5 a row had a required "label" that was both the visible
	 * text and, once image buttons arrived, the alt text. Splitting those jobs
	 * renamed the required field to "name" and made "label" the optional
	 * visible override. An old row's label was its name in everything but
	 * spelling, so it becomes the name and the label is left empty -- which
	 * renders the button exactly as it rendered before.
	 *
	 * Idempotent, writes nothing, and runs on read and on import.
	 *
	 * @param array $stored Stored or imported settings.
	 * @return array
	 */
	public static function migrate_legacy_buttons( array $stored ) {
		foreach ( array( 'buttons_main', 'buttons_support' ) as $key ) {
			if ( empty( $stored[ $key ] ) || ! is_array( $stored[ $key ] ) ) {
				continue;
			}

			foreach ( $stored[ $key ] as $index => $row ) {
				if ( ! is_array( $row ) || isset( $row['name'] ) ) {
					continue;
				}

				$stored[ $key ][ $index ]['name']  = isset( $row['label'] ) ? $row['label'] : '';
				$stored[ $key ][ $index ]['label'] = '';
			}
		}

		return $stored;
	}

	private static function sanitize_buttons( $rows ) {
		$out = array();

		if ( ! is_array( $rows ) ) {
			return $out;
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$name  = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			$url   = isset( $row['url'] ) ? esc_url_raw( trim( $row['url'] ) ) : '';
			$style = isset( $row['style'] ) ? sanitize_key( $row['style'] ) : 'ghost';
			$image = isset( $row['image'] ) ? esc_url_raw( trim( $row['image'] ) ) : '';

			/*
			 * The name is what the link is called: the visible text when there
			 * is no separate label, the alt text when there is an image, and
			 * the accessible name in both cases. A row without one would be a
			 * link nothing can announce, so it is dropped along with rows that
			 * have nowhere to go.
			 */
			if ( '' === $name || '' === $url ) {
				continue;
			}

			// A label identical to the name is not a second thing worth storing.
			if ( 0 === strcasecmp( $label, $name ) ) {
				$label = '';
			}

			if ( ! array_key_exists( $style, self::BUTTON_STYLES ) ) {
				$style = 'ghost';
			}

			$out[] = array(
				'name'  => $name,
				// Optional visible text. Empty means the name is shown.
				'label' => $label,
				'url'   => $url,
				'style' => $style,
				// An image replaces the text on the page; the style variant
				// stops applying, because the image is the button.
				'image' => $image,
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
