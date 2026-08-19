<?php
/**
 * Reset, preset, undo, export and import actions.
 *
 * Each is a separate admin-post endpoint rather than a field on the options
 * form, because each one acts immediately and needs its own nonce and its own
 * confirmation.
 *
 * @package MMP_Coming_Soon
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings tools.
 */
class MMPCS_Tools {

	/**
	 * Marker identifying an export file as ours.
	 */
	const FILE_TYPE = 'mmp-coming-soon-settings';

	/**
	 * Refuse absurd uploads before parsing them.
	 */
	const MAX_IMPORT_BYTES = 512000;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		$actions = array( 'reset', 'undo', 'preset_save', 'preset_apply', 'preset_delete', 'preset_export', 'export', 'import' );

		foreach ( $actions as $action ) {
			add_action( 'admin_post_mmpcs_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
	}

	/**
	 * Verify capability and nonce for an action.
	 *
	 * @param string $action Action name.
	 * @return void
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'mmp-coming-soon' ), 403 );
		}

		check_admin_referer( 'mmpcs_' . $action );
	}

	/**
	 * Send the administrator back to the settings screen with a result code.
	 *
	 * @param string $notice Notice code.
	 * @param string $tab    Tab to open.
	 * @return void
	 */
	private static function back( $notice, $tab = '' ) {
		$args = array(
			'page'         => MMPCS_MENU_SLUG,
			'mmpcs_notice' => $notice,
		);

		if ( '' !== $tab ) {
			$args['mmpcs_tab'] = $tab;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Reset a section, or everything.
	 *
	 * @return void
	 */
	public static function handle_reset() {
		self::guard( 'reset' );

		$section = isset( $_POST['section'] ) ? sanitize_key( wp_unslash( $_POST['section'] ) ) : '';

		if ( 'all' !== $section && ! isset( MMPCS_Settings::SECTIONS[ $section ] ) ) {
			self::back( 'reset_failed' );
		}

		MMPCS_Settings::reset( $section );

		self::back( 'all' === $section ? 'reset_all' : 'reset_section', 'all' === $section ? 'visibility' : $section );
	}

	/**
	 * Undo the last destructive change.
	 *
	 * @return void
	 */
	public static function handle_undo() {
		self::guard( 'undo' );

		self::back( false === MMPCS_Settings::undo() ? 'undo_empty' : 'undone' );
	}

	/**
	 * Save the current design as a named preset.
	 *
	 * @return void
	 */
	public static function handle_preset_save() {
		self::guard( 'preset_save' );

		$name = isset( $_POST['preset_name'] ) ? sanitize_text_field( wp_unslash( $_POST['preset_name'] ) ) : '';

		if ( '' === $name ) {
			self::back( 'preset_needs_name', 'presets' );
		}

		MMPCS_Settings::save_preset( $name );

		self::back( 'preset_saved', 'presets' );
	}

	/**
	 * Apply a saved preset.
	 *
	 * @return void
	 */
	public static function handle_preset_apply() {
		self::guard( 'preset_apply' );

		$name   = isset( $_POST['preset_name'] ) ? sanitize_text_field( wp_unslash( $_POST['preset_name'] ) ) : '';
		$preset = MMPCS_Settings::get_preset( $name );

		if ( ! $preset ) {
			self::back( 'preset_missing', 'presets' );
		}

		/* translators: %s: preset name. */
		$label = sprintf( __( 'Applied preset "%s"', 'mmp-coming-soon' ), $preset['name'] );

		MMPCS_Settings::apply_portable( $preset['data'], $label );

		self::back( 'preset_applied', 'presets' );
	}

	/**
	 * Delete a saved preset.
	 *
	 * @return void
	 */
	public static function handle_preset_delete() {
		self::guard( 'preset_delete' );

		$name = isset( $_POST['preset_name'] ) ? sanitize_text_field( wp_unslash( $_POST['preset_name'] ) ) : '';

		MMPCS_Settings::delete_preset( $name );

		self::back( 'preset_deleted', 'presets' );
	}

	/**
	 * Download the current design as JSON.
	 *
	 * @return void
	 */
	/**
	 * Download one preset as a settings file.
	 *
	 * Deliberately the same shape as the full export, so a downloaded preset is
	 * importable anywhere the full export is -- a preset that can only be read
	 * back by the site that wrote it is not portable, it is just a backup.
	 *
	 * @return void
	 */
	public static function handle_preset_export() {
		self::guard( 'preset_export' );

		$name   = isset( $_POST['preset_name'] ) ? sanitize_text_field( wp_unslash( $_POST['preset_name'] ) ) : '';
		$preset = '' === $name ? false : MMPCS_Settings::get_preset( $name );

		if ( ! $preset || empty( $preset['data'] ) ) {
			self::back( 'preset_missing', 'presets' );
		}

		$payload = array(
			'_type'       => self::FILE_TYPE,
			'_version'    => MMPCS_VERSION,
			'exported_at' => gmdate( 'c' ),
			'site'        => home_url( '/' ),
			// Kept so a downloaded preset arrives back under the name it left.
			'preset'      => $preset['name'],
			'settings'    => $preset['data'],
		);

		$file = sanitize_file_name( 'coming-soon-' . $preset['name'] . '-' . gmdate( 'Y-m-d' ) . '.json' );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $file . '"' );

		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	public static function handle_export() {
		self::guard( 'export' );

		$payload = array(
			'_type'       => self::FILE_TYPE,
			'_version'    => MMPCS_VERSION,
			'exported_at' => gmdate( 'c' ),
			'site'        => home_url( '/' ),
			'settings'    => MMPCS_Settings::portable(),
		);

		$name = sanitize_file_name( wp_parse_url( home_url(), PHP_URL_HOST ) . '-coming-soon-' . gmdate( 'Y-m-d' ) . '.json' );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );

		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * Import a design from an uploaded JSON file.
	 *
	 * The file is read from the temporary upload path and never moved into the
	 * media library, so importing leaves nothing behind on the filesystem.
	 *
	 * @return void
	 */
	public static function handle_import() {
		self::guard( 'import' );

		if ( empty( $_FILES['mmpcs_file']['tmp_name'] ) || ! empty( $_FILES['mmpcs_file']['error'] ) ) {
			self::back( 'import_no_file', 'presets' );
		}

		$size = isset( $_FILES['mmpcs_file']['size'] ) ? (int) $_FILES['mmpcs_file']['size'] : 0;

		if ( $size <= 0 || $size > self::MAX_IMPORT_BYTES ) {
			self::back( 'import_too_big', 'presets' );
		}

		$tmp = sanitize_text_field( $_FILES['mmpcs_file']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( ! is_uploaded_file( $tmp ) ) {
			self::back( 'import_invalid', 'presets' );
		}

		$raw = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- reading a temp upload, not a remote resource.

		if ( false === $raw ) {
			self::back( 'import_invalid', 'presets' );
		}

		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) || empty( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			self::back( 'import_invalid', 'presets' );
		}

		if ( ! isset( $data['_type'] ) || self::FILE_TYPE !== $data['_type'] ) {
			self::back( 'import_wrong_type', 'presets' );
		}

		if ( ! MMPCS_Settings::apply_portable( $data['settings'], __( 'Imported settings from a file', 'mmp-coming-soon' ) ) ) {
			self::back( 'import_empty', 'presets' );
		}

		self::back( 'imported', 'presets' );
	}
}
