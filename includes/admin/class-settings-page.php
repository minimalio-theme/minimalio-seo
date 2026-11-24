<?php
/**
 * Settings screen: Minimalio > SEO.
 *
 * @package minimalio-seo
 */

defined( 'ABSPATH' ) || exit;

class Minimalio_SEO_Settings_Page {

	const SLUG = 'minimalio-seo';

	/**
	 * Page hook suffix.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'menu' ], 20 );
		add_action( 'admin_init', [ $this, 'register' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
		add_action( 'update_option_' . Minimalio_SEO_Options::KEY, [ 'Minimalio_SEO_Options', 'flush' ] );
	}

	/**
	 * Top-level menu item placed directly below Settings (position 80).
	 */
	public function menu() {
		$title = __( 'SEO - Minimalio', 'minimalio-seo' );

		$this->hook = (string) add_menu_page( $title, $title, 'manage_options', self::SLUG, [ $this, 'render' ], 'dashicons-search', 81 );
	}

	/**
	 * Register the option.
	 */
	public function register() {
		register_setting(
			'minimalio_seo',
			Minimalio_SEO_Options::KEY,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize' ],
				'default'           => [],
			]
		);
	}

	/**
	 * Enqueue admin assets on our page only.
	 *
	 * @param string $hook Current hook suffix.
	 */
	public function assets( $hook ) {
		if ( $hook !== $this->hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'minimalio-seo-admin', MINIMALIO_SEO_URL . 'assets/admin.css', [], MINIMALIO_SEO_VERSION );
		wp_enqueue_script( 'minimalio-seo-admin', MINIMALIO_SEO_URL . 'assets/admin.js', [ 'jquery' ], MINIMALIO_SEO_VERSION, true );
		wp_localize_script( 'minimalio-seo-admin', 'minimalioSeo', [ 'i18n' => self::media_i18n() ] );
	}

	/**
	 * Strings for the media picker.
	 *
	 * @return array
	 */
	public static function media_i18n() {
		return [
			'chooseImage' => __( 'Choose image', 'minimalio-seo' ),
			'useImage'    => __( 'Use this image', 'minimalio-seo' ),
		];
	}

	/* ------------------------------------------------------------------
	 * Field definitions
	 * ------------------------------------------------------------------ */

	/**
	 * Tabs > sections > fields.
	 *
	 * @return array
	 */
	public function tabs() {
		$vars_help = __( 'Variables: %title%, %sitename%, %tagline%, %sep%, %page%', 'minimalio-seo' );

		$separators = [];
		foreach ( Minimalio_SEO_Options::separators() as $sep ) {
			$separators[ $sep ] = $sep;
		}

		// Titles.
		$content_fields = [];
		foreach ( Minimalio_SEO_Options::post_types() as $post_type ) {
			$content_fields[ 'pt_title_' . $post_type->name ]   = [
				'type'        => 'text',
				/* translators: %s: post type name */
				'label'       => sprintf( __( '%s title', 'minimalio-seo' ), $post_type->labels->singular_name ),
				'placeholder' => '%title% %sep% %sitename%',
			];
			$content_fields[ 'pt_noindex_' . $post_type->name ] = [
				'type'  => 'checkbox',
				'label' => '',
				/* translators: %s: post type name (plural) */
				'text'  => sprintf( __( 'Hide all %s from search engines (noindex)', 'minimalio-seo' ), $post_type->labels->name ),
			];
			if ( $post_type->has_archive ) {
				$content_fields[ 'pta_title_' . $post_type->name ] = [
					'type'        => 'text',
					/* translators: %s: post type name (plural) */
					'label'       => sprintf( __( '%s archive title', 'minimalio-seo' ), $post_type->labels->name ),
					'placeholder' => '%title% %sep% %page% %sep% %sitename%',
				];
			}
		}

		$taxonomy_fields = [];
		foreach ( Minimalio_SEO_Options::taxonomies() as $taxonomy ) {
			$taxonomy_fields[ 'tax_title_' . $taxonomy->name ]   = [
				'type'        => 'text',
				/* translators: %s: taxonomy name */
				'label'       => sprintf( __( '%s title', 'minimalio-seo' ), $taxonomy->labels->singular_name ),
				'placeholder' => '%title% %sep% %page% %sep% %sitename%',
			];
			$taxonomy_fields[ 'tax_noindex_' . $taxonomy->name ] = [
				'type'  => 'checkbox',
				'label' => '',
				/* translators: %s: taxonomy name (plural) */
				'text'  => sprintf( __( 'Hide all %s archives from search engines (noindex)', 'minimalio-seo' ), $taxonomy->labels->name ),
			];
		}

		// Site identity first, then the homepage, then the title separator.
		$site_title = Minimalio_SEO_Context::clean( get_bloginfo( 'name' ) );
		$general    = [
			'entity_type' => [
				'type'    => 'radio',
				'label'   => __( 'This website represents', 'minimalio-seo' ),
				'options' => [
					'organization' => __( 'An organization or studio', 'minimalio-seo' ),
					'person'       => __( 'A person', 'minimalio-seo' ),
				],
			],
			'entity_name' => [
				'type'        => 'text',
				'label'       => __( 'Name', 'minimalio-seo' ),
				'placeholder' => $site_title,
				'desc'        => sprintf(
					/* translators: %s: site title */
					__( 'If empty, the site title is used: <strong>%s</strong>', 'minimalio-seo' ),
					esc_html( $site_title )
				),
			],
			'entity_logo' => [
				'type'  => 'image',
				'label' => __( 'Logo / photo', 'minimalio-seo' ),
				'desc'  => __( 'Square image, at least 112 × 112 px. If empty, the site logo is used.', 'minimalio-seo' ),
			],
		];

		$front_id = Minimalio_SEO_Options::static_front_page_id();
		if ( $front_id ) {
			// One source of truth: a static homepage is edited in its own page editor.
			$general['home_notice'] = [
				'type'  => 'notice',
				'label' => __( 'Homepage', 'minimalio-seo' ),
				'html'  => sprintf(
					/* translators: 1: page title, 2: opening link tag, 3: closing link tag */
					__( 'Your homepage is the page <strong>%1$s</strong>. Set its SEO title, description and share image in the SEO panel of that page. %2$sEdit homepage SEO →%3$s', 'minimalio-seo' ),
					esc_html( get_the_title( $front_id ) ),
					'<a class="button" href="' . esc_url( (string) get_edit_post_link( $front_id ) ) . '">',
					'</a>'
				),
			];
		} else {
			$general += [
						'home_title'       => [
							'type'        => 'text',
							'label'       => __( 'Homepage title', 'minimalio-seo' ),
							'placeholder' => '%sitename% %sep% %tagline%',
							'desc'        => $vars_help,
						],
						'home_description' => [
							'type'  => 'textarea',
							'label' => __( 'Homepage meta description', 'minimalio-seo' ),
							'desc'  => __( 'Shown under your site name in Google. About 120–160 characters. If empty, the site tagline is used.', 'minimalio-seo' ),
							'count' => 160,
						],
			];
		}

		$general['separator'] = [
			'type'    => 'radio',
			'label'   => __( 'Title separator', 'minimalio-seo' ),
			'options' => $separators,
		];

		return [
			'general'       => [
				'label'    => __( 'General', 'minimalio-seo' ),
				'sections' => [
					__( 'General', 'minimalio-seo' ) => $general,
					__( 'Contact & location', 'minimalio-seo' ) => [
						'contact_phone'    => [
							'type'        => 'text',
							'label'       => __( 'Phone', 'minimalio-seo' ),
							'placeholder' => '+420 123 456 789',
							'desc'        => __( 'Optional. Fill in if clients contact or visit you, for example a photography or design studio. With a street address and city, Google sees your business as a local business, which can help in local and Maps searches. Everything here is public in the page code, so leave the address empty if you work from home.', 'minimalio-seo' ),
						],
						'address_street'   => [
							'type'  => 'text',
							'label' => __( 'Street address', 'minimalio-seo' ),
						],
						'address_city'     => [
							'type'  => 'text',
							'label' => __( 'City', 'minimalio-seo' ),
						],
						'address_postcode' => [
							'type'  => 'text',
							'label' => __( 'Postal code', 'minimalio-seo' ),
						],
						'address_region'   => [
							'type'  => 'text',
							'label' => __( 'State / region', 'minimalio-seo' ),
						],
						'address_country'  => [
							'type'        => 'text',
							'label'       => __( 'Country', 'minimalio-seo' ),
							'placeholder' => __( 'e.g. CZ or Czech Republic', 'minimalio-seo' ),
						],
					],
					__( 'Social', 'minimalio-seo' )  => [
						'default_image'   => [
							'type'  => 'image',
							'label' => __( 'Default share image', 'minimalio-seo' ),
							'desc'  => __( 'Used when a page has no featured image. Recommended: 1200 × 630 px.', 'minimalio-seo' ),
						],
						'twitter_site'    => [
							'type'        => 'text',
							'label'       => __( 'X (Twitter) username', 'minimalio-seo' ),
							'placeholder' => '@username',
						],
						'social_profiles' => [
							'type'        => 'textarea',
							'label'       => __( 'Social profiles', 'minimalio-seo' ),
							'placeholder' => "https://instagram.com/…\nhttps://www.behance.net/…\nhttps://www.linkedin.com/in/…",
							'desc'        => __( 'One URL per line. Helps Google connect your profiles to your site.', 'minimalio-seo' ),
						],
					],
				],
			],
			'content_types' => [
				'label'    => __( 'Content Types', 'minimalio-seo' ),
				'sections' => [
					__( 'Content types', 'minimalio-seo' ) => $content_fields,
					__( 'Taxonomies', 'minimalio-seo' )    => $taxonomy_fields,
					__( 'Other archives', 'minimalio-seo' ) => [
						'author_title'   => [
							'type'        => 'text',
							'label'       => __( 'Author archive title', 'minimalio-seo' ),
							'placeholder' => '%title% %sep% %sitename%',
						],
						'noindex_author' => [
							'type'  => 'checkbox',
							'label' => '',
							'text'  => __( 'Hide author archives from search engines (noindex)', 'minimalio-seo' ),
						],
						'date_title'     => [
							'type'        => 'text',
							'label'       => __( 'Date archive title', 'minimalio-seo' ),
							'placeholder' => '%title% %sep% %sitename%',
						],
						'noindex_date'   => [
							'type'  => 'checkbox',
							'label' => '',
							'text'  => __( 'Hide date archives from search engines (noindex)', 'minimalio-seo' ),
						],
						'search_title'   => [
							'type'        => 'text',
							'label'       => __( 'Search results title', 'minimalio-seo' ),
							'placeholder' => '%title% %sep% %sitename%',
						],
						'notfound_title' => [
							'type'        => 'text',
							'label'       => __( '404 page title', 'minimalio-seo' ),
							'placeholder' => '%title% %sep% %sitename%',
							'desc'        => __( 'Search results and 404 pages are always hidden from search engines.', 'minimalio-seo' ),
						],
					],
					__( 'Media', 'minimalio-seo' )         => [
						'redirect_attachment' => [
							'type'  => 'checkbox',
							'label' => __( 'Attachment pages', 'minimalio-seo' ),
							'text'  => __( 'Redirect media attachment pages to their parent post (or to the file)', 'minimalio-seo' ),
							'desc'  => __( 'WordPress creates a separate page for every uploaded image. These pages have no real content, so they are redirected instead of indexed.', 'minimalio-seo' ),
						],
					],
				],
			],
			'sitemap'      => [
				'label'    => __( 'Sitemap', 'minimalio-seo' ),
				'sections' => [
					__( 'XML Sitemap', 'minimalio-seo' ) => [
						'sitemap_enabled' => [
							'type' => 'checkbox',
							'label' => __( 'Sitemap', 'minimalio-seo' ),
							'text' => __( 'Enable the XML sitemap', 'minimalio-seo' ),
							'desc' => minimalio_seo_is_public()
								? sprintf(
									/* translators: %s: sitemap URL */
									__( 'Your sitemap: %s — submit it in Google Search Console and Bing Webmaster Tools. Noindexed content is left out automatically.', 'minimalio-seo' ),
									'<a href="' . esc_url( home_url( '/wp-sitemap.xml' ) ) . '" target="_blank" rel="noopener">' . esc_html( home_url( '/wp-sitemap.xml' ) ) . '</a>'
								)
								: sprintf(
									/* translators: %s: link to Settings > Reading */
									__( '<strong>The sitemap is currently off</strong> because search engines are discouraged from indexing this site (%s). WordPress disables the sitemap in that case; it will appear at /wp-sitemap.xml as soon as the site is public.', 'minimalio-seo' ),
									'<a href="' . esc_url( admin_url( 'options-reading.php' ) ) . '">' . esc_html__( 'Settings → Reading', 'minimalio-seo' ) . '</a>'
								),
						],
					],
				],
			],
			'schema'       => [
				'label'    => __( 'Structured Data', 'minimalio-seo' ),
				'sections' => [
					__( 'Knowledge Graph', 'minimalio-seo' ) => [
						'schema_enabled'  => [
							'type'  => 'checkbox',
							'label' => __( 'Structured data', 'minimalio-seo' ),
							'text'  => __( 'Output JSON-LD structured data (website, organization/person, articles, breadcrumbs)', 'minimalio-seo' ),
						],
					],
				],
			],
			'verification' => [
				'label'    => __( 'Verification', 'minimalio-seo' ),
				'sections' => [
					__( 'Webmaster tools', 'minimalio-seo' ) => [
						'verify_google'    => [
							'type'  => 'text',
							'label' => __( 'Google Search Console', 'minimalio-seo' ),
							'desc'  => __( 'Paste the content value or the whole meta tag.', 'minimalio-seo' ),
						],
						'verify_bing'      => [
							'type'  => 'text',
							'label' => __( 'Bing Webmaster Tools', 'minimalio-seo' ),
						],
						'verify_pinterest' => [
							'type'  => 'text',
							'label' => __( 'Pinterest', 'minimalio-seo' ),
						],
						'verify_facebook'  => [
							'type'  => 'text',
							'label' => __( 'Facebook (Meta)', 'minimalio-seo' ),
							'desc'  => __( 'Domain verification for Meta Business Suite. Only needed for Facebook and Instagram ads or link ownership.', 'minimalio-seo' ),
						],
					],
				],
			],
		];
	}

	/**
	 * Flattened key => field map.
	 *
	 * @return array
	 */
	private function fields() {
		$fields = [];
		foreach ( $this->tabs() as $tab ) {
			foreach ( $tab['sections'] as $section ) {
				$fields += $section;
			}
		}
		return $fields;
	}

	/* ------------------------------------------------------------------
	 * Sanitizing
	 * ------------------------------------------------------------------ */

	/**
	 * Sanitize the whole option. Every visible field is in one form, so a missing
	 * checkbox means "off". Values of fields that are currently hidden (e.g. the
	 * homepage title while a static page is the homepage) are kept as they were,
	 * so switching Settings > Reading back restores them.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input  = is_array( $input ) ? wp_unslash( $input ) : [];
		$saved  = get_option( Minimalio_SEO_Options::KEY, [] );
		$output = is_array( $saved ) ? $saved : [];

		foreach ( $this->fields() as $key => $field ) {
			if ( 'notice' === $field['type'] ) {
				continue;
			}

			$value = isset( $input[ $key ] ) ? $input[ $key ] : '';

			switch ( $field['type'] ) {
				case 'checkbox':
					$output[ $key ] = ! empty( $value );
					break;
				case 'image':
					$output[ $key ] = absint( $value );
					break;
				case 'radio':
					$output[ $key ] = array_key_exists( (string) $value, $field['options'] ) ? (string) $value : (string) key( $field['options'] );
					break;
				case 'textarea':
					$output[ $key ] = 'social_profiles' === $key ? $this->sanitize_urls( $value ) : sanitize_textarea_field( $value );
					break;
				default:
					$output[ $key ] = sanitize_text_field( $value );
			}
		}

		// Specific cleanups.
		$output['twitter_site'] = $this->sanitize_twitter( isset( $output['twitter_site'] ) ? $output['twitter_site'] : '' );
		foreach ( [ 'verify_google', 'verify_bing', 'verify_pinterest', 'verify_facebook' ] as $key ) {
			$output[ $key ] = $this->sanitize_verification( isset( $input[ $key ] ) ? $input[ $key ] : '' );
		}

		return $output;
	}

	/**
	 * One URL per line.
	 *
	 * @param string $value Raw.
	 * @return string
	 */
	private function sanitize_urls( $value ) {
		$urls = array_filter( array_map( 'esc_url_raw', array_map( 'trim', preg_split( '/\R/', (string) $value ) ) ) );
		return implode( "\n", $urls );
	}

	/**
	 * Accept "@name", "name" or a profile URL.
	 *
	 * @param string $value Raw.
	 * @return string
	 */
	private function sanitize_twitter( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '#(?:twitter\.com|x\.com)/@?([A-Za-z0-9_]{1,15})#i', $value, $m ) ) {
			return $m[1];
		}
		$value = ltrim( $value, '@' );
		return preg_match( '/^[A-Za-z0-9_]{1,15}$/', $value ) ? $value : '';
	}

	/**
	 * Accept the bare code or a full <meta ... content="code"> tag.
	 *
	 * @param string $value Raw.
	 * @return string
	 */
	private function sanitize_verification( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/content=["\']([^"\']+)["\']/i', $value, $m ) ) {
			$value = $m[1];
		}
		return sanitize_text_field( $value );
	}

	/* ------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------ */

	/**
	 * Page output.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tabs    = $this->tabs();
		$options = Minimalio_SEO_Options::all();
		$first   = key( $tabs );
		?>
		<div class="wrap minimalio-seo-settings">
			<h1><?php esc_html_e( 'SEO - Minimalio', 'minimalio-seo' ); ?></h1>

			<?php if ( ! minimalio_seo_is_public() ) : ?>
				<div class="notice notice-warning inline"><p>
					<?php
					printf(
						/* translators: %s: link to Reading settings */
						esc_html__( 'Search engines are currently discouraged from indexing this site. Change this in %s.', 'minimalio-seo' ),
						'<a href="' . esc_url( admin_url( 'options-reading.php' ) ) . '">' . esc_html__( 'Settings → Reading', 'minimalio-seo' ) . '</a>'
					);
					?>
				</p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper minimalio-seo-tabs">
				<?php foreach ( $tabs as $id => $tab ) : ?>
					<a href="#<?php echo esc_attr( $id ); ?>" class="nav-tab<?php echo $id === $first ? ' nav-tab-active' : ''; ?>" data-tab="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $tab['label'] ); ?></a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php">
				<?php settings_fields( 'minimalio_seo' ); ?>

				<?php foreach ( $tabs as $id => $tab ) : ?>
					<div class="minimalio-seo-panel" data-panel="<?php echo esc_attr( $id ); ?>"<?php echo $id === $first ? '' : ' hidden'; ?>>
						<?php foreach ( $tab['sections'] as $section_title => $fields ) : ?>
							<?php
							if ( ! $fields ) {
								continue;
							}
							?>
							<h2><?php echo esc_html( $section_title ); ?></h2>
							<table class="form-table" role="presentation">
								<?php
								foreach ( $fields as $key => $field ) {
									if ( array_key_exists( $key, $options ) ) {
										$value = $options[ $key ];
									} elseif ( 0 === strpos( $key, 'tax_noindex_' ) ) {
										$value = Minimalio_SEO_Options::taxonomy_noindex( substr( $key, 12 ) ); // Default: hidden.
									} else {
										$value = '';
									}
									$this->render_field( $key, $field, $value );
								}
								?>
							</table>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * One table row.
	 *
	 * @param string $key   Option key.
	 * @param array  $field Field definition.
	 * @param mixed  $value Current value.
	 */
	private function render_field( $key, $field, $value ) {
		$name = Minimalio_SEO_Options::KEY . '[' . $key . ']';
		$id   = 'minimalio-seo-' . $key;
		?>
		<tr>
			<th scope="row"><?php if ( '' !== $field['label'] ) : ?><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label><?php endif; ?></th>
			<td>
				<?php
				switch ( $field['type'] ) {
					case 'notice':
						echo '<p class="minimalio-seo-front-notice">' . wp_kses( $field['html'], [ 'strong' => [], 'a' => [ 'href' => [], 'class' => [] ] ] ) . '</p>';
						break;

					case 'checkbox':
						printf(
							'<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
							esc_attr( $id ),
							esc_attr( $name ),
							checked( (bool) $value, true, false ),
							esc_html( $field['text'] )
						);
						break;

					case 'radio':
						echo '<fieldset class="minimalio-seo-radios">';
						foreach ( $field['options'] as $option_value => $option_label ) {
							printf(
								'<label><input type="radio" name="%1$s" value="%2$s" %3$s /> <span>%4$s</span></label>',
								esc_attr( $name ),
								esc_attr( $option_value ),
								checked( (string) $value, (string) $option_value, false ),
								esc_html( $option_label )
							);
						}
						echo '</fieldset>';
						break;

					case 'textarea':
						printf(
							'<textarea id="%1$s" name="%2$s" rows="4" class="large-text" placeholder="%3$s"%5$s>%4$s</textarea>',
							esc_attr( $id ),
							esc_attr( $name ),
							esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ),
							esc_textarea( (string) $value ),
							isset( $field['count'] ) ? ' data-count="' . (int) $field['count'] . '"' : ''
						);
						break;

					case 'image':
						self::image_field( $id, $name, (int) $value );
						break;

					default:
						printf(
							'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" placeholder="%4$s" />',
							esc_attr( $id ),
							esc_attr( $name ),
							esc_attr( (string) $value ),
							esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' )
						);
				}

				if ( ! empty( $field['desc'] ) ) {
					echo '<p class="description">' . wp_kses( $field['desc'], [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ], 'strong' => [] ] ) . '</p>';
				}
				?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Media picker (shared with the post meta box).
	 *
	 * @param string $id    Input ID.
	 * @param string $name  Input name.
	 * @param int    $value Attachment ID.
	 */
	public static function image_field( $id, $name, $value ) {
		$src = $value ? wp_get_attachment_image_url( $value, 'medium' ) : '';
		?>
		<div class="minimalio-seo-image">
			<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo $value ? (int) $value : ''; ?>" />
			<div class="minimalio-seo-image__preview"><?php if ( $src ) : ?><img src="<?php echo esc_url( $src ); ?>" alt="" /><?php endif; ?></div>
			<button type="button" class="button minimalio-seo-image__select"><?php esc_html_e( 'Select image', 'minimalio-seo' ); ?></button>
			<button type="button" class="button-link minimalio-seo-image__remove"<?php echo $value ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'minimalio-seo' ); ?></button>
		</div>
		<?php
	}
}
