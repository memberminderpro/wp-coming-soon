<?php
/**
 * Admin options page.
 *
 * One form, one option row, one sanitise callback. Tabs are a client-side
 * convenience so a single Save covers every field.
 *
 * @package MMP_Coming_Soon
 */

defined( 'ABSPATH' ) || exit;

/**
 * Options screen.
 */
class MMPCS_Admin {

	const MENU_SLUG   = 'mmp-coming-soon';
	const OPTION_PAGE = 'mmpcs_options';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MMPCS_FILE ), array( __CLASS__, 'action_links' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_update_check' ) );
	}

	/**
	 * Add the top-level menu.
	 *
	 * @return void
	 */
	public static function add_menu() {
		add_menu_page(
			__( 'Coming Soon', 'mmp-coming-soon' ),
			__( 'Coming Soon', 'mmp-coming-soon' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render' ),
			'dashicons-megaphone',
			80
		);
	}

	/**
	 * Register the single setting.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			self::OPTION_PAGE,
			MMPCS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'MMPCS_Settings', 'sanitize' ),
				'default'           => MMPCS_Settings::defaults(),
			)
		);
	}

	/**
	 * Add a Settings link on the plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public static function action_links( $links ) {
		$url = admin_url( 'admin.php?page=' . self::MENU_SLUG );

		$check = wp_nonce_url(
			add_query_arg(
				array(
					'page'         => self::MENU_SLUG,
					'mmpcs_action' => 'check',
				),
				admin_url( 'admin.php' )
			),
			'mmpcs_check'
		);

		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'mmp-coming-soon' ) . '</a>',
			'<a href="' . esc_url( $check ) . '">' . esc_html__( 'Check for updates', 'mmp-coming-soon' ) . '</a>'
		);

		return $links;
	}

	/**
	 * Enqueue assets on our screen only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );

		wp_enqueue_style(
			'mmpcs-admin',
			MMPCS_URL . 'assets/css/admin.css',
			array( 'wp-color-picker' ),
			MMPCS_VERSION
		);

		wp_enqueue_script(
			'mmpcs-admin',
			MMPCS_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			MMPCS_VERSION,
			true
		);

		wp_localize_script(
			'mmpcs-admin',
			'mmpcsAdmin',
			array(
				'mediaTitle'  => __( 'Choose a logo', 'mmp-coming-soon' ),
				'mediaButton' => __( 'Use this image', 'mmp-coming-soon' ),
				'confirmRow'  => __( 'Remove this row?', 'mmp-coming-soon' ),
			)
		);
	}

	/**
	 * Render the options screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s          = MMPCS_Settings::get();
		$preview_url = wp_nonce_url( add_query_arg( 'mmpcs_preview', '1', home_url( '/' ) ), 'mmpcs_preview' );
		?>
		<div class="wrap mmpcs-wrap">
			<h1><?php esc_html_e( 'Coming Soon', 'mmp-coming-soon' ); ?></h1>

			<?php if ( isset( $_GET['mmpcs_result'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<?php if ( 'ok' === $_GET['mmpcs_result'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
					<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Checked for updates.', 'mmp-coming-soon' ); ?></p></div>
				<?php else : ?>
					<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Could not reach the update server. The coming soon page is unaffected.', 'mmp-coming-soon' ); ?></p></div>
				<?php endif; ?>
			<?php endif; ?>

			<p class="mmpcs-lede">
				<?php esc_html_e( 'A self-contained holding page for visitors who are not signed in. Everything on it is configured here.', 'mmp-coming-soon' ); ?>
				<a class="button button-secondary" href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Preview page', 'mmp-coming-soon' ); ?>
				</a>
			</p>

			<form method="post" action="options.php" class="mmpcs-form">
				<?php settings_fields( self::OPTION_PAGE ); ?>

				<nav class="mmpcs-tabs" role="tablist">
					<button type="button" class="mmpcs-tab is-active" data-tab="visibility"><?php esc_html_e( 'Visibility', 'mmp-coming-soon' ); ?></button>
					<button type="button" class="mmpcs-tab" data-tab="content"><?php esc_html_e( 'Content', 'mmp-coming-soon' ); ?></button>
					<button type="button" class="mmpcs-tab" data-tab="buttons"><?php esc_html_e( 'Buttons', 'mmp-coming-soon' ); ?></button>
					<button type="button" class="mmpcs-tab" data-tab="footer"><?php esc_html_e( 'Footer', 'mmp-coming-soon' ); ?></button>
					<button type="button" class="mmpcs-tab" data-tab="background"><?php esc_html_e( 'Background', 'mmp-coming-soon' ); ?></button>
					<button type="button" class="mmpcs-tab" data-tab="colors"><?php esc_html_e( 'Colors', 'mmp-coming-soon' ); ?></button>
					<button type="button" class="mmpcs-tab" data-tab="updates"><?php esc_html_e( 'Updates', 'mmp-coming-soon' ); ?></button>
				</nav>

				<?php
				self::panel_visibility( $s );
				self::panel_content( $s );
				self::panel_buttons( $s );
				self::panel_footer( $s );
				self::panel_background( $s );
				self::panel_colors( $s );
				self::panel_updates( $s );
				?>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
		self::templates();
	}

	/**
	 * Field name helper.
	 *
	 * @param string $path Bracketed path fragment.
	 * @return string
	 */
	private static function name( $path ) {
		return MMPCS_OPTION . $path;
	}

	/**
	 * Visibility panel.
	 *
	 * @param array $s Settings.
	 * @return void
	 */
	private static function panel_visibility( array $s ) {
		?>
		<section class="mmpcs-panel is-active" data-panel="visibility">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable coming soon page', 'mmp-coming-soon' ); ?></th>
					<td>
						<label class="mmpcs-switch">
							<input type="checkbox" name="<?php echo esc_attr( self::name( '[enabled]' ) ); ?>" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?>>
							<span><?php esc_html_e( 'Show the coming soon page to visitors who are not signed in', 'mmp-coming-soon' ); ?></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Signed-in WordPress users always see the real site, including the front page set in Settings → Reading. Logins, the admin, the REST API, feeds, and sitemaps are never blocked.', 'mmp-coming-soon' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mmpcs-allowlist"><?php esc_html_e( 'Always-public paths', 'mmp-coming-soon' ); ?></label></th>
					<td>
						<textarea id="mmpcs-allowlist" class="large-text code" rows="6" name="<?php echo esc_attr( self::name( '[allowlist]' ) ); ?>"><?php echo esc_textarea( $s['allowlist'] ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'One path per line, relative to your site root. Anyone can reach these while the coming soon page is on. End a line with * to match everything beneath it.', 'mmp-coming-soon' ); ?><br>
							<code>/privacy-policy/</code> &nbsp; <code>/legal/*</code>
						</p>
					</td>
				</tr>
			</table>
		</section>
		<?php
	}

	/**
	 * Content panel.
	 *
	 * @param array $s Settings.
	 * @return void
	 */
	private static function panel_content( array $s ) {
		?>
		<section class="mmpcs-panel" data-panel="content">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="mmpcs-logo-url"><?php esc_html_e( 'Logo image URL', 'mmp-coming-soon' ); ?></label></th>
					<td>
						<div class="mmpcs-media">
							<input type="url" id="mmpcs-logo-url" class="regular-text code" name="<?php echo esc_attr( self::name( '[logo][url]' ) ); ?>" value="<?php echo esc_attr( $s['logo']['url'] ); ?>">
							<button type="button" class="button mmpcs-media-pick"><?php esc_html_e( 'Choose image', 'mmp-coming-soon' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'Pick from the media library or paste any image URL. Leave blank to hide the logo.', 'mmp-coming-soon' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mmpcs-logo-alt"><?php esc_html_e( 'Logo alt text', 'mmp-coming-soon' ); ?></label></th>
					<td><input type="text" id="mmpcs-logo-alt" class="regular-text" name="<?php echo esc_attr( self::name( '[logo][alt]' ) ); ?>" value="<?php echo esc_attr( $s['logo']['alt'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="mmpcs-logo-link"><?php esc_html_e( 'Logo link URL', 'mmp-coming-soon' ); ?></label></th>
					<td>
						<input type="url" id="mmpcs-logo-link" class="regular-text code" name="<?php echo esc_attr( self::name( '[logo][link]' ) ); ?>" value="<?php echo esc_attr( $s['logo']['link'] ); ?>">
						<p class="description"><?php esc_html_e( 'Leave blank to render the logo without a link.', 'mmp-coming-soon' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mmpcs-logo-aria"><?php esc_html_e( 'Logo link ARIA label', 'mmp-coming-soon' ); ?></label></th>
					<td><input type="text" id="mmpcs-logo-aria" class="regular-text" name="<?php echo esc_attr( self::name( '[logo][aria]' ) ); ?>" value="<?php echo esc_attr( $s['logo']['aria'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="mmpcs-logo-width"><?php esc_html_e( 'Logo width', 'mmp-coming-soon' ); ?></label></th>
					<td>
						<input type="number" id="mmpcs-logo-width" class="small-text" min="40" max="800" step="1" name="<?php echo esc_attr( self::name( '[logo][width]' ) ); ?>" value="<?php echo esc_attr( $s['logo']['width'] ); ?>"> px
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mmpcs-badge"><?php esc_html_e( 'Badge text', 'mmp-coming-soon' ); ?></label></th>
					<td>
						<input type="text" id="mmpcs-badge" class="regular-text" name="<?php echo esc_attr( self::name( '[badge_text]' ) ); ?>" value="<?php echo esc_attr( $s['badge_text'] ); ?>">
						<p class="description"><?php esc_html_e( 'The outlined label above the heading. This is the page heading for search engines and screen readers.', 'mmp-coming-soon' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mmpcs-heading"><?php esc_html_e( 'Heading', 'mmp-coming-soon' ); ?></label></th>
					<td><input type="text" id="mmpcs-heading" class="large-text" name="<?php echo esc_attr( self::name( '[heading]' ) ); ?>" value="<?php echo esc_attr( $s['heading'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="mmpcs-description"><?php esc_html_e( 'Description', 'mmp-coming-soon' ); ?></label></th>
					<td><textarea id="mmpcs-description" class="large-text" rows="4" name="<?php echo esc_attr( self::name( '[description]' ) ); ?>"><?php echo esc_textarea( $s['description'] ); ?></textarea></td>
				</tr>
			</table>
		</section>
		<?php
	}

	/**
	 * Buttons panel.
	 *
	 * @param array $s Settings.
	 * @return void
	 */
	private static function panel_buttons( array $s ) {
		?>
		<section class="mmpcs-panel" data-panel="buttons">
			<h2><?php esc_html_e( 'Main buttons', 'mmp-coming-soon' ); ?></h2>
			<p class="description"><?php esc_html_e( 'The primary row, shown at full size.', 'mmp-coming-soon' ); ?></p>
			<?php self::repeater( 'buttons_main', $s['buttons_main'], 'button' ); ?>

			<h2><?php esc_html_e( 'Support buttons', 'mmp-coming-soon' ); ?></h2>
			<p class="description"><?php esc_html_e( 'A quieter secondary row, shown smaller and dimmed until hovered.', 'mmp-coming-soon' ); ?></p>
			<?php self::repeater( 'buttons_support', $s['buttons_support'], 'button' ); ?>
		</section>
		<?php
	}

	/**
	 * Footer panel.
	 *
	 * @param array $s Settings.
	 * @return void
	 */
	private static function panel_footer( array $s ) {
		?>
		<section class="mmpcs-panel" data-panel="footer">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="mmpcs-company"><?php esc_html_e( 'Company name', 'mmp-coming-soon' ); ?></label></th>
					<td><input type="text" id="mmpcs-company" class="regular-text" name="<?php echo esc_attr( self::name( '[footer][company_name]' ) ); ?>" value="<?php echo esc_attr( $s['footer']['company_name'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="mmpcs-company-url"><?php esc_html_e( 'Company link', 'mmp-coming-soon' ); ?></label></th>
					<td><input type="url" id="mmpcs-company-url" class="regular-text code" name="<?php echo esc_attr( self::name( '[footer][company_url]' ) ); ?>" value="<?php echo esc_attr( $s['footer']['company_url'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="mmpcs-rights"><?php esc_html_e( 'Legal text', 'mmp-coming-soon' ); ?></label></th>
					<td>
						<input type="text" id="mmpcs-rights" class="regular-text" name="<?php echo esc_attr( self::name( '[footer][rights_text]' ) ); ?>" value="<?php echo esc_attr( $s['footer']['rights_text'] ); ?>">
						<p class="description"><?php esc_html_e( 'Follows the company name. The year is inserted automatically.', 'mmp-coming-soon' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Legal links', 'mmp-coming-soon' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Appended after the legal text, separated by middots. These may point anywhere, including external legal pages.', 'mmp-coming-soon' ); ?></p>
			<?php self::repeater( 'footer][legal_links', $s['footer']['legal_links'], 'link' ); ?>
		</section>
		<?php
	}

	/**
	 * Background panel.
	 *
	 * @param array $s Settings.
	 * @return void
	 */
	private static function panel_background( array $s ) {
		$a = $s['aurora'];
		?>
		<section class="mmpcs-panel" data-panel="background">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Animated background', 'mmp-coming-soon' ); ?></th>
					<td>
						<label class="mmpcs-switch">
							<input type="checkbox" name="<?php echo esc_attr( self::name( '[aurora][enabled]' ) ); ?>" value="1" <?php checked( ! empty( $a['enabled'] ) ); ?>>
							<span><?php esc_html_e( 'Show the drifting gradient blobs', 'mmp-coming-soon' ); ?></span>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Motion', 'mmp-coming-soon' ); ?></th>
					<td>
						<label class="mmpcs-switch">
							<input type="checkbox" name="<?php echo esc_attr( self::name( '[aurora][motion]' ) ); ?>" value="1" <?php checked( ! empty( $a['motion'] ) ); ?>>
							<span><?php esc_html_e( 'Animate the blobs', 'mmp-coming-soon' ); ?></span>
						</label>
						<p class="description"><?php esc_html_e( 'Turning this off keeps the same colours but freezes them. Visitors who ask their system to reduce motion always get the frozen version.', 'mmp-coming-soon' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mmpcs-aurora-base"><?php esc_html_e( 'Base color', 'mmp-coming-soon' ); ?></label></th>
					<td>
						<input type="text" id="mmpcs-aurora-base" class="mmpcs-color" name="<?php echo esc_attr( self::name( '[aurora][base]' ) ); ?>" value="<?php echo esc_attr( $a['base'] ); ?>">
						<p class="description"><?php esc_html_e( 'The flat colour behind every blob.', 'mmp-coming-soon' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Blob colors', 'mmp-coming-soon' ); ?></th>
					<td>
						<div class="mmpcs-repeater" data-repeater="aurora_colors">
							<div class="mmpcs-rows">
								<?php foreach ( $a['colors'] as $i => $color ) : ?>
									<div class="mmpcs-row mmpcs-row--color">
										<span class="mmpcs-move">
											<button type="button" class="button-link mmpcs-up" aria-label="<?php esc_attr_e( 'Move up', 'mmp-coming-soon' ); ?>"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
											<button type="button" class="button-link mmpcs-down" aria-label="<?php esc_attr_e( 'Move down', 'mmp-coming-soon' ); ?>"><span class="dashicons dashicons-arrow-down-alt2"></span></button>
										</span>
										<input type="text" class="mmpcs-color" name="<?php echo esc_attr( self::name( '[aurora][colors][]' ) ); ?>" value="<?php echo esc_attr( $color ); ?>">
										<button type="button" class="button-link mmpcs-remove" aria-label="<?php esc_attr_e( 'Remove color', 'mmp-coming-soon' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
									</div>
								<?php endforeach; ?>
							</div>
							<button type="button" class="button mmpcs-add" data-template="tmpl-mmpcs-aurora_colors"><?php esc_html_e( 'Add color', 'mmp-coming-soon' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'Each colour becomes one drifting blob. Add or remove as many as you like; positions cycle through six presets.', 'mmp-coming-soon' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mmpcs-aurora-size"><?php esc_html_e( 'Blob size', 'mmp-coming-soon' ); ?></label></th>
					<td>
						<input type="range" id="mmpcs-aurora-size" class="mmpcs-range" min="20" max="140" step="1" name="<?php echo esc_attr( self::name( '[aurora][size]' ) ); ?>" value="<?php echo esc_attr( $a['size'] ); ?>" data-suffix="vmax">
						<output class="mmpcs-output"></output>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mmpcs-aurora-blur"><?php esc_html_e( 'Blur', 'mmp-coming-soon' ); ?></label></th>
					<td>
						<input type="range" id="mmpcs-aurora-blur" class="mmpcs-range" min="0" max="200" step="1" name="<?php echo esc_attr( self::name( '[aurora][blur]' ) ); ?>" value="<?php echo esc_attr( $a['blur'] ); ?>" data-suffix="px">
						<output class="mmpcs-output"></output>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mmpcs-aurora-duration"><?php esc_html_e( 'Cycle duration', 'mmp-coming-soon' ); ?></label></th>
					<td>
						<input type="range" id="mmpcs-aurora-duration" class="mmpcs-range" min="4" max="180" step="1" name="<?php echo esc_attr( self::name( '[aurora][duration]' ) ); ?>" value="<?php echo esc_attr( $a['duration'] ); ?>" data-suffix="s">
						<output class="mmpcs-output"></output>
						<p class="description"><?php esc_html_e( 'Longer is slower and calmer.', 'mmp-coming-soon' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mmpcs-aurora-intensity"><?php esc_html_e( 'Intensity', 'mmp-coming-soon' ); ?></label></th>
					<td>
						<input type="range" id="mmpcs-aurora-intensity" class="mmpcs-range" min="0.05" max="1" step="0.01" name="<?php echo esc_attr( self::name( '[aurora][intensity]' ) ); ?>" value="<?php echo esc_attr( $a['intensity'] ); ?>" data-suffix="">
						<output class="mmpcs-output"></output>
					</td>
				</tr>
			</table>
		</section>
		<?php
	}

	/**
	 * Colours panel.
	 *
	 * @param array $s Settings.
	 * @return void
	 */
	private static function panel_colors( array $s ) {
		$fields = array(
			'accent'       => __( 'Accent (badge, divider, gold buttons)', 'mmp-coming-soon' ),
			'accent_hover' => __( 'Accent hover', 'mmp-coming-soon' ),
			'ink'          => __( 'Accent button text', 'mmp-coming-soon' ),
			'navy'         => __( 'Navy button', 'mmp-coming-soon' ),
			'crimson'      => __( 'Crimson button', 'mmp-coming-soon' ),
			'offwhite'     => __( 'Navy and crimson button text', 'mmp-coming-soon' ),
		);
		?>
		<section class="mmpcs-panel" data-panel="colors">
			<table class="form-table" role="presentation">
				<?php foreach ( $fields as $key => $label ) : ?>
				<tr>
					<th scope="row"><label for="mmpcs-color-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
					<td><input type="text" id="mmpcs-color-<?php echo esc_attr( $key ); ?>" class="mmpcs-color" name="<?php echo esc_attr( self::name( '[palette][' . $key . ']' ) ); ?>" value="<?php echo esc_attr( $s['palette'][ $key ] ); ?>"></td>
				</tr>
				<?php endforeach; ?>
			</table>
		</section>
		<?php
	}

	/**
	 * Updates panel.
	 *
	 * @param array $s Settings.
	 * @return void
	 */
	private static function panel_updates( array $s ) {
		$manifest = MMPCS_Updater::manifest();
		$latest   = $manifest && ! empty( $manifest['version'] ) ? $manifest['version'] : null;
		$behind   = $latest && version_compare( $latest, MMPCS_VERSION, '>' );

		$check_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'         => self::MENU_SLUG,
					'mmpcs_action' => 'check',
				),
				admin_url( 'admin.php' )
			),
			'mmpcs_check'
		);
		?>
		<section class="mmpcs-panel" data-panel="updates">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Installed version', 'mmp-coming-soon' ); ?></th>
					<td><code><?php echo esc_html( MMPCS_VERSION ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Latest release', 'mmp-coming-soon' ); ?></th>
					<td>
						<?php if ( $latest ) : ?>
							<code><?php echo esc_html( $latest ); ?></code>
							<?php if ( $behind ) : ?>
								<strong class="mmpcs-behind"><?php esc_html_e( 'An update is available.', 'mmp-coming-soon' ); ?></strong>
								<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Go to Plugins to install it', 'mmp-coming-soon' ); ?></a>
							<?php else : ?>
								<span class="mmpcs-current"><?php esc_html_e( 'Up to date.', 'mmp-coming-soon' ); ?></span>
							<?php endif; ?>
						<?php else : ?>
							<em><?php esc_html_e( 'Could not reach the update server. This does not affect the coming soon page.', 'mmp-coming-soon' ); ?></em>
						<?php endif; ?>
						<p><a class="button button-secondary" href="<?php echo esc_url( $check_url ); ?>"><?php esc_html_e( 'Check for updates now', 'mmp-coming-soon' ); ?></a></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Automatic updates', 'mmp-coming-soon' ); ?></th>
					<td>
						<label class="mmpcs-switch">
							<input type="checkbox" name="<?php echo esc_attr( self::name( '[auto_update]' ) ); ?>" value="1" <?php checked( ! empty( $s['auto_update'] ) ); ?>>
							<span><?php esc_html_e( 'Install new releases of this plugin automatically', 'mmp-coming-soon' ); ?></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'WordPress installs the update in the background on its regular schedule, so this site stays current without anyone visiting it. Turn this off to review each release first and update by hand.', 'mmp-coming-soon' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mmpcs-channel"><?php esc_html_e( 'Update channel', 'mmp-coming-soon' ); ?></label></th>
					<td>
						<?php $forced = MMPCS_Updater::channel_is_forced(); ?>
						<select id="mmpcs-channel" name="<?php echo esc_attr( self::name( '[update_channel]' ) ); ?>" <?php disabled( $forced ); ?>>
							<option value="stable" <?php selected( $s['update_channel'], 'stable' ); ?>><?php esc_html_e( 'Stable — production releases', 'mmp-coming-soon' ); ?></option>
							<option value="beta" <?php selected( $s['update_channel'], 'beta' ); ?>><?php esc_html_e( 'Beta — pre-release builds', 'mmp-coming-soon' ); ?></option>
						</select>
						<?php if ( $forced ) : ?>
							<input type="hidden" name="<?php echo esc_attr( self::name( '[update_channel]' ) ); ?>" value="<?php echo esc_attr( $s['update_channel'] ); ?>">
							<p class="description">
								<strong><?php esc_html_e( 'Pinned from wp-config.php.', 'mmp-coming-soon' ); ?></strong>
								<?php
								printf(
									/* translators: %s: channel name. */
									esc_html__( 'MMPCS_UPDATE_CHANNEL is set, so this site follows the %s channel regardless of this setting.', 'mmp-coming-soon' ),
									'<code>' . esc_html( MMPCS_Updater::channel() ) . '</code>'
								);
								?>
							</p>
						<?php else : ?>
							<p class="description"><?php esc_html_e( 'Keep customer sites on Stable. Put one site you control on Beta so a bad release is caught before it reaches everyone.', 'mmp-coming-soon' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</section>
		<?php
	}

	/**
	 * Handle the "check for updates now" link.
	 *
	 * @return void
	 */
	public static function handle_update_check() {
		if ( ! isset( $_GET['mmpcs_action'] ) || 'check' !== $_GET['mmpcs_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'mmpcs_check' );

		$manifest = MMPCS_Updater::check_now();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => self::MENU_SLUG,
					'mmpcs_result' => $manifest ? 'ok' : 'fail',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render a repeater.
	 *
	 * @param string $key  Option sub-key (already bracket-safe).
	 * @param array  $rows Existing rows.
	 * @param string $type "button" or "link".
	 * @return void
	 */
	private static function repeater( $key, array $rows, $type ) {
		$base = self::name( '[' . $key . ']' );
		?>
		<div class="mmpcs-repeater" data-repeater="<?php echo esc_attr( $type ); ?>">
			<div class="mmpcs-rows">
				<?php foreach ( $rows as $index => $row ) : ?>
					<?php self::row( $base, $row, $type, (int) $index ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button mmpcs-add" data-template="tmpl-mmpcs-<?php echo esc_attr( $type ); ?>" data-base="<?php echo esc_attr( $base ); ?>">
				<?php echo 'button' === $type ? esc_html__( 'Add button', 'mmp-coming-soon' ) : esc_html__( 'Add link', 'mmp-coming-soon' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render one repeater row.
	 *
	 * @param string     $base  Field name base.
	 * @param array      $row   Row values.
	 * @param string     $type  "button" or "link".
	 * @param string|int $index Array index, or the __i__ placeholder in templates.
	 * @return void
	 */
	private static function row( $base, array $row, $type, $index = '__i__' ) {
		$label = isset( $row['label'] ) ? $row['label'] : '';
		$url   = isset( $row['url'] ) ? $row['url'] : '';
		$style = isset( $row['style'] ) ? $row['style'] : 'ghost';
		?>
		<div class="mmpcs-row">
			<span class="mmpcs-move">
				<button type="button" class="button-link mmpcs-up" aria-label="<?php esc_attr_e( 'Move up', 'mmp-coming-soon' ); ?>"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
				<button type="button" class="button-link mmpcs-down" aria-label="<?php esc_attr_e( 'Move down', 'mmp-coming-soon' ); ?>"><span class="dashicons dashicons-arrow-down-alt2"></span></button>
			</span>
			<label class="mmpcs-field">
				<span><?php esc_html_e( 'Label', 'mmp-coming-soon' ); ?></span>
				<input type="text" name="<?php echo esc_attr( $base ); ?>[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $label ); ?>">
			</label>
			<label class="mmpcs-field mmpcs-field--wide">
				<span><?php esc_html_e( 'Link', 'mmp-coming-soon' ); ?></span>
				<input type="url" class="code" name="<?php echo esc_attr( $base ); ?>[<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_attr( $url ); ?>">
			</label>
			<?php if ( 'button' === $type ) : ?>
			<label class="mmpcs-field mmpcs-field--narrow">
				<span><?php esc_html_e( 'Style', 'mmp-coming-soon' ); ?></span>
				<select name="<?php echo esc_attr( $base ); ?>[<?php echo esc_attr( $index ); ?>][style]">
					<?php foreach ( MMPCS_Settings::BUTTON_STYLES as $value => $name ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $style, $value ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<?php endif; ?>
			<button type="button" class="button-link mmpcs-remove" aria-label="<?php esc_attr_e( 'Remove row', 'mmp-coming-soon' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
		</div>
		<?php
	}

	/**
	 * Row templates cloned by the admin script.
	 *
	 * @return void
	 */
	private static function templates() {
		?>
		<script type="text/template" id="tmpl-mmpcs-button">
			<?php self::row( '__base__', array( 'style' => 'ghost' ), 'button' ); ?>
		</script>
		<script type="text/template" id="tmpl-mmpcs-link">
			<?php self::row( '__base__', array(), 'link' ); ?>
		</script>
		<script type="text/template" id="tmpl-mmpcs-aurora_colors">
			<div class="mmpcs-row mmpcs-row--color">
				<span class="mmpcs-move">
					<button type="button" class="button-link mmpcs-up" aria-label="<?php esc_attr_e( 'Move up', 'mmp-coming-soon' ); ?>"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
					<button type="button" class="button-link mmpcs-down" aria-label="<?php esc_attr_e( 'Move down', 'mmp-coming-soon' ); ?>"><span class="dashicons dashicons-arrow-down-alt2"></span></button>
				</span>
				<input type="text" class="mmpcs-color" name="<?php echo esc_attr( self::name( '[aurora][colors][]' ) ); ?>" value="#5862a7">
				<button type="button" class="button-link mmpcs-remove" aria-label="<?php esc_attr_e( 'Remove color', 'mmp-coming-soon' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
			</div>
		</script>
		<?php
	}
}
