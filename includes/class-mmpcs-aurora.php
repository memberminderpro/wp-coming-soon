<?php
/**
 * Animated aurora background.
 *
 * The Etch original positioned its blobs with :nth-child(7n + N) rules, which
 * only worked for a fixed seven-key palette. Here each blob carries its own
 * custom properties, so any number of colours renders correctly.
 *
 * @package MMP_Coming_Soon
 */

defined( 'ABSPATH' ) || exit;

/**
 * Aurora renderer.
 */
class MMPCS_Aurora {

	/**
	 * Drift presets, cycled by blob index. Ratios are relative to the 68vmax
	 * reference size of the original design, so the admin size control scales
	 * every blob proportionally.
	 *
	 * @var array<int,array<string,string|float>>
	 */
	const PRESETS = array(
		array(
			'x'     => '7%',
			'y'     => '4%',
			'ratio' => 1.0882,
			'anim'  => 'a',
			'delay' => '0s',
		),
		array(
			'x'     => '72%',
			'y'     => '-12%',
			'ratio' => 0.9118,
			'anim'  => 'b',
			'delay' => '-9s',
		),
		array(
			'x'     => '86%',
			'y'     => '62%',
			'ratio' => 0.8529,
			'anim'  => 'c',
			'delay' => '-17s',
		),
		array(
			'x'     => '18%',
			'y'     => '76%',
			'ratio' => 1.0294,
			'anim'  => 'd',
			'delay' => '-6s',
		),
		array(
			'x'     => '42%',
			'y'     => '28%',
			'ratio' => 0.7647,
			'anim'  => 'b',
			'delay' => '-14s',
		),
		array(
			'x'     => '-8%',
			'y'     => '42%',
			'ratio' => 0.8235,
			'anim'  => 'c',
			'delay' => '-21s',
		),
	);

	/**
	 * Build the aurora markup.
	 *
	 * @param array $aurora Aurora settings.
	 * @return string
	 */
	public static function markup( array $aurora ) {
		if ( empty( $aurora['enabled'] ) ) {
			return '';
		}

		$colors = isset( $aurora['colors'] ) && is_array( $aurora['colors'] ) ? array_values( $aurora['colors'] ) : array();

		$style = sprintf(
			'--mmpcs-aurora-opacity:%s;--mmpcs-aurora-duration:%ds;--mmpcs-aurora-blur:%dpx;--mmpcs-aurora-size:%dvmax;--mmpcs-aurora-base:%s;',
			rtrim( rtrim( number_format( (float) $aurora['intensity'], 3, '.', '' ), '0' ), '.' ),
			(int) $aurora['duration'],
			(int) $aurora['blur'],
			(int) $aurora['size'],
			$aurora['base']
		);

		$html  = '<div class="mmpcs-aurora" aria-hidden="true" data-motion="' . ( empty( $aurora['motion'] ) ? 'off' : 'on' ) . '" style="' . esc_attr( $style ) . '">';
		$html .= '<div class="mmpcs-aurora__base"></div>';

		$count = count( self::PRESETS );

		foreach ( $colors as $index => $color ) {
			$preset = self::PRESETS[ $index % $count ];

			$blob_style = sprintf(
				'--mmpcs-blob-color:%s;--mmpcs-blob-x:%s;--mmpcs-blob-y:%s;--mmpcs-blob-ratio:%s;animation-name:mmpcs-drift-%s;animation-delay:%s;',
				$color,
				$preset['x'],
				$preset['y'],
				$preset['ratio'],
				$preset['anim'],
				$preset['delay']
			);

			$html .= '<span class="mmpcs-aurora__blob" style="' . esc_attr( $blob_style ) . '"></span>';
		}

		$html .= '</div>';

		return $html;
	}
}
