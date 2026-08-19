<?php
/**
 * Document renderer.
 *
 * Emits a complete, self-contained HTML document and exits. wp_head() and
 * wp_footer() are deliberately NOT called: nothing from the active theme or
 * any other plugin is allowed to enqueue into this page, which is what makes
 * it render identically on any site.
 *
 * @package MMP_Coming_Soon
 */

defined( 'ABSPATH' ) || exit;

/**
 * Coming soon page renderer.
 */
class MMPCS_Renderer {

	/**
	 * Render the page and stop.
	 *
	 * @param bool $is_preview Whether this is an admin preview.
	 * @return void
	 */
	public static function render( $is_preview = false ) {
		$settings = MMPCS_Settings::get();

		if ( ! headers_sent() ) {
			status_header( 200 );
			header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
			header( 'X-Robots-Tag: noindex, nofollow', true );

			if ( $is_preview ) {
				nocache_headers();
			} else {
				header( 'Cache-Control: public, max-age=0, must-revalidate' );
			}
		}

		echo self::document( $settings, $is_preview ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- assembled from escaped parts.
		exit;
	}

	/**
	 * Build the whole document.
	 *
	 * @param array $s          Settings.
	 * @param bool  $is_preview Whether this is an admin preview.
	 * @return string
	 */
	public static function document( array $s, $is_preview = false ) {
		$title = trim( wp_strip_all_tags( $s['badge_text'] ) );
		$name  = wp_strip_all_tags( get_bloginfo( 'name' ) );

		if ( '' !== $name && '' !== $title ) {
			$title = $name . ' — ' . $title;
		} elseif ( '' === $title ) {
			$title = $name;
		}

		$version = MMPCS_VERSION;

		ob_start();
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $title ); ?></title>
<?php if ( '' !== $s['description'] ) : ?>
<meta name="description" content="<?php echo esc_attr( wp_trim_words( $s['description'], 30, '' ) ); ?>">
<?php endif; ?>
<link rel="preload" as="font" type="font/woff2" crossorigin href="<?php echo esc_url( MMPCS_URL . 'assets/fonts/syne-latin.woff2' ); ?>">
<link rel="preload" as="font" type="font/woff2" crossorigin href="<?php echo esc_url( MMPCS_URL . 'assets/fonts/plus-jakarta-sans-latin.woff2' ); ?>">
<link rel="stylesheet" href="<?php echo esc_url( add_query_arg( 'ver', $version, MMPCS_URL . 'assets/css/coming-soon.css' ) ); ?>">
<?php if ( ! empty( $s['aurora']['enabled'] ) ) : ?>
<link rel="stylesheet" href="<?php echo esc_url( add_query_arg( 'ver', $version, MMPCS_URL . 'assets/css/aurora.css' ) ); ?>">
<?php endif; ?>
<style><?php echo self::inline_css( $s ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- values sanitised as CSS colours. ?></style>
</head>
<body class="mmpcs-body">
<?php echo MMPCS_Aurora::markup( $s['aurora'] ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- escaped in builder. ?>
<section class="coming-soon">
	<main class="coming-soon__main">
		<?php echo self::logo( $s['logo'] ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- escaped in builder. ?>
		<?php if ( '' !== $s['badge_text'] ) : ?>
		<h1 class="coming-soon__badge"><?php echo esc_html( $s['badge_text'] ); ?></h1>
		<?php endif; ?>
		<?php if ( '' !== $s['heading'] ) : ?>
		<p class="coming-soon__title"><?php echo esc_html( $s['heading'] ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $s['description'] ) : ?>
		<p class="coming-soon__subtitle"><?php echo esc_html( $s['description'] ); ?></p>
		<?php endif; ?>
		<div class="coming-soon__divider"></div>
		<div class="coming-soon__actions">
			<?php echo self::button_row( $s['buttons_main'], false ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- escaped in builder. ?>
			<?php echo self::button_row( $s['buttons_support'], true ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- escaped in builder. ?>
		</div>
	</main>
	<?php echo self::footer( $s['footer'] ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- escaped in builder. ?>
</section>
<script src="<?php echo esc_url( add_query_arg( 'ver', $version, MMPCS_URL . 'assets/js/coming-soon.js' ) ); ?>" defer></script>
<?php if ( $is_preview ) : ?>
<div class="mmpcs-preview-flag" role="status"><?php
	echo empty( $s['enabled'] )
		? esc_html__( 'Preview — visitors are still seeing your site.', 'mmp-coming-soon' )
		: esc_html__( 'Preview — this page is live for visitors who are not signed in.', 'mmp-coming-soon' );
?></div>
<?php endif; ?>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Settings-driven custom properties.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	private static function inline_css( array $s ) {
		$p = $s['palette'];

		$declarations = array(
			'--mmpcs-accent'       => $p['accent'],
			'--mmpcs-accent-hover' => $p['accent_hover'],
			'--mmpcs-ink'          => $p['ink'],
			'--mmpcs-navy'         => $p['navy'],
			'--mmpcs-crimson'      => $p['crimson'],
			'--mmpcs-offwhite'     => $p['offwhite'],
			'--mmpcs-logo-width'   => (int) $s['logo']['width'] . 'px',
			'--mmpcs-page-bg'      => ! empty( $s['aurora']['enabled'] ) ? $s['aurora']['base'] : $p['ink'],
		);

		$css = ':root{';
		foreach ( $declarations as $prop => $value ) {
			$css .= $prop . ':' . $value . ';';
		}
		$css .= '}';

		return $css;
	}

	/**
	 * Logo block. Renders nothing when no image is configured.
	 *
	 * @param array $logo Logo settings.
	 * @return string
	 */
	private static function logo( array $logo ) {
		if ( empty( $logo['url'] ) ) {
			return '';
		}

		$img = sprintf(
			'<img class="coming-soon__logo-img" src="%s" alt="%s" width="%d" decoding="async">',
			esc_url( $logo['url'] ),
			esc_attr( $logo['alt'] ),
			(int) $logo['width']
		);

		if ( empty( $logo['link'] ) ) {
			return '<div class="coming-soon__logo">' . $img . '</div>';
		}

		return sprintf(
			'<div class="coming-soon__logo"><a class="coming-soon__logo-link" href="%s"%s>%s</a></div>',
			esc_url( $logo['link'] ),
			'' !== $logo['aria'] ? ' aria-label="' . esc_attr( $logo['aria'] ) . '"' : '',
			$img
		);
	}

	/**
	 * A row of buttons. Renders nothing when the repeater is empty.
	 *
	 * @param array $buttons    Button rows.
	 * @param bool  $is_support Whether this is the smaller support row.
	 * @return string
	 */
	private static function button_row( array $buttons, $is_support ) {
		if ( empty( $buttons ) ) {
			return '';
		}

		$html = '<div class="coming-soon__action-row"' . ( $is_support ? ' data-support-row' : '' ) . '>';

		foreach ( $buttons as $button ) {
			$html .= sprintf(
				'<a class="coming-soon__button coming-soon__button--%s" href="%s" rel="noopener noreferrer" target="_blank">%s</a>',
				esc_attr( $button['style'] ),
				esc_url( $button['url'] ),
				esc_html( $button['label'] )
			);
		}

		return $html . '</div>';
	}

	/**
	 * Footer block.
	 *
	 * @param array $footer Footer settings.
	 * @return string
	 */
	private static function footer( array $footer ) {
		$parts = array();

		$company = '';
		if ( '' !== $footer['company_name'] ) {
			$company = '' !== $footer['company_url']
				? sprintf(
					'<a href="%s" aria-label="%s">%s</a>',
					esc_url( $footer['company_url'] ),
					/* translators: %s: company name. */
					esc_attr( sprintf( __( '%s home page', 'mmp-coming-soon' ), $footer['company_name'] ) ),
					esc_html( $footer['company_name'] )
				)
				: esc_html( $footer['company_name'] );
		}

		$lead = '&copy; <span data-mmpcs-year>' . esc_html( gmdate( 'Y' ) ) . '</span>';

		if ( '' !== $company ) {
			$lead .= ' ' . $company;
		}

		if ( '' !== $footer['rights_text'] ) {
			$lead .= '. ' . esc_html( $footer['rights_text'] );
		}

		$parts[] = $lead;

		foreach ( $footer['legal_links'] as $link ) {
			$parts[] = sprintf(
				'<span class="coming-soon__legal"> &middot; <a href="%s" rel="noopener noreferrer" target="_blank">%s</a></span>',
				esc_url( $link['url'] ),
				esc_html( $link['label'] )
			);
		}

		return '<footer class="coming-soon__footer">' . implode( '', $parts ) . '</footer>';
	}
}
