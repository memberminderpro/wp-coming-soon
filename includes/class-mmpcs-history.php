<?php
/**
 * Recent history of the design, and stepping back through it.
 *
 * Every destructive action -- applying a preset, resetting a section, importing
 * a file, restoring an earlier entry -- records what the design looked like
 * immediately beforehand. Getting lost is then a matter of stepping backwards
 * until it looks right again, rather than remembering to have saved something.
 *
 * This replaces the single undo slot, which held one change and cleared itself
 * the moment it was used: enough to take back the click you just made, useless
 * for finding your way out of half an hour of experimenting.
 *
 * @package MMP_Coming_Soon
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings history.
 */
class MMPCS_History {

	/**
	 * Its own option, deliberately.
	 *
	 * MMPCS_Settings::get() runs on the front end -- the gate calls it on every
	 * request it evaluates -- so the settings row is read constantly and must
	 * stay small. Twenty-five entries is tens of kilobytes that the front end
	 * has no use for. Stored separately and never autoloaded, it is read only
	 * on the settings screen. uninstall.php sweeps every mmpcs_* option, so the
	 * plugin still removes itself completely.
	 */
	const OPTION = 'mmpcs_history';

	/**
	 * How many entries are kept.
	 *
	 * Enough to step back through an evening's work; short enough that the list
	 * stays something you read rather than search.
	 */
	const LIMIT = 25;

	/**
	 * Read the history, newest first.
	 *
	 * @return array<int,array>
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) || empty( $stored['entries'] ) || ! is_array( $stored['entries'] ) ) {
			return array();
		}

		return $stored['entries'];
	}

	/**
	 * Record the design as it stands, labelled with what is about to happen.
	 *
	 * Called before the change, not after: an entry is a way back to the state
	 * that preceded it, and labelling it with the cause is what makes the list
	 * readable -- "Before applying preset Rotary" says more than a timestamp.
	 *
	 * @param string $label What is about to happen.
	 * @return void
	 */
	public static function record( $label ) {
		$stored  = get_option( self::OPTION, array() );
		$stored  = is_array( $stored ) ? $stored : array();
		$entries = isset( $stored['entries'] ) && is_array( $stored['entries'] ) ? $stored['entries'] : array();
		$next_id = isset( $stored['next_id'] ) ? (int) $stored['next_id'] : 1;

		array_unshift(
			$entries,
			array(
				'id'       => $next_id,
				'at'       => time(),
				'label'    => sanitize_text_field( $label ),
				'settings' => MMPCS_Settings::portable(),
			)
		);

		self::write( array_slice( $entries, 0, self::LIMIT ), $next_id + 1 );
	}

	/**
	 * One entry by id.
	 *
	 * @param int $id Entry id.
	 * @return array|false
	 */
	public static function get( $id ) {
		foreach ( self::all() as $entry ) {
			if ( (int) $entry['id'] === (int) $id ) {
				return $entry;
			}
		}

		return false;
	}

	/**
	 * Step back to an entry.
	 *
	 * Records the current design first, so stepping back is itself reversible
	 * and stepping too far is not a dead end.
	 *
	 * @param int $id Entry id.
	 * @return array|false The restored entry, or false when it is gone.
	 */
	public static function restore( $id ) {
		$entry = self::get( $id );

		if ( ! $entry || empty( $entry['settings'] ) ) {
			return false;
		}

		/* translators: %s: how long ago the entry was recorded, e.g. "2 hours". */
		self::record( sprintf( __( 'Before stepping back %s', 'mmp-coming-soon' ), human_time_diff( $entry['at'] ) ) );

		MMPCS_Settings::apply_portable( $entry['settings'] );

		return $entry;
	}

	/**
	 * Persist, never autoloaded.
	 *
	 * @param array $entries Entries, newest first.
	 * @param int   $next_id Next id to hand out.
	 * @return void
	 */
	private static function write( array $entries, $next_id ) {
		update_option(
			self::OPTION,
			array(
				'next_id' => (int) $next_id,
				'entries' => $entries,
			),
			false
		);
	}
}
