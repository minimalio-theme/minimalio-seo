<?php
/**
 * SEO meta box for posts, pages, portfolio items and any other public post type.
 *
 * A classic meta box works in both the block editor and the classic editor.
 *
 * @package minimalio-seo
 */

defined( 'ABSPATH' ) || exit;

class Minimalio_SEO_Post_Meta {

	const NONCE = 'minimalio_seo_post_nonce';

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'add' ] );
		add_action( 'save_post', [ $this, 'save' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
	}

	/**
	 * Register the box.
	 */
	public function add() {
		foreach ( array_keys( Minimalio_SEO_Options::post_types() ) as $post_type ) {
			// Classic editor only: the block editor gets a native sidebar panel instead.
			add_meta_box( 'minimalio-seo', __( 'SEO', 'minimalio-seo' ), [ $this, 'render' ], $post_type, 'normal', 'high', [ '__back_compat_meta_box' => true ] );
		}
	}

	/**
	 * Assets on edit screens.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function assets( $hook ) {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! array_key_exists( $screen->post_type, Minimalio_SEO_Options::post_types() ) ) {
			return;
		}

		global $post;

		wp_enqueue_media();
		wp_enqueue_style( 'minimalio-seo-admin', MINIMALIO_SEO_URL . 'assets/admin.css', [], MINIMALIO_SEO_VERSION );
		wp_enqueue_script( 'minimalio-seo-admin', MINIMALIO_SEO_URL . 'assets/admin.js', [ 'jquery' ], MINIMALIO_SEO_VERSION, true );

		$is_front = $post && Minimalio_SEO_Options::static_front_page_id() === (int) $post->ID;
		$jetpack  = $post ? self::jetpack( $post->ID, $is_front ) : [ 'title' => '', 'description' => '', 'noindex' => false ];

		wp_localize_script(
			'minimalio-seo-admin',
			'minimalioSeo',
			[
				'i18n'     => Minimalio_SEO_Settings_Page::media_i18n(),
				'snippet'  => [
					'template'    => $is_front ? Minimalio_SEO_Options::FRONT_PAGE_TITLE : Minimalio_SEO_Options::post_type_title( $screen->post_type ),
					'siteName'    => Minimalio_SEO_Context::clean( get_bloginfo( 'name' ) ),
					'tagline'     => Minimalio_SEO_Context::clean( get_bloginfo( 'description' ) ),
					'sep'         => Minimalio_SEO_Options::get( 'separator' ),
					'autoDesc'    => $post ? Minimalio_SEO_Context::auto_description( $post ) : '',
					'jetpackTitle' => $jetpack['title'],
					'jetpackDesc'  => $jetpack['description'],
					'fallbackDesc' => $is_front ? Minimalio_SEO_Context::clean( get_bloginfo( 'description' ) ) : '',
					'url'         => $post ? get_permalink( $post ) : home_url( '/' ),
					'noDesc'      => __( 'No meta description. Google will pick text from the page.', 'minimalio-seo' ),
				],
			]
		);
	}

	/**
	 * Jetpack SEO values that apply while our fields are empty.
	 * The class is only loaded while our front-end output is active.
	 *
	 * @param int  $post_id  Post ID.
	 * @param bool $is_front Is this the static homepage?
	 * @return array{title: string, description: string, noindex: bool}
	 */
	private static function jetpack( $post_id, $is_front ) {
		if ( ! class_exists( 'Minimalio_SEO_Jetpack_Data' ) ) {
			return [ 'title' => '', 'description' => '', 'noindex' => false ];
		}
		return Minimalio_SEO_Jetpack_Data::for_post( $post_id, $is_front );
	}

	/**
	 * Box output.
	 *
	 * @param WP_Post $post Post.
	 */
	public function render( $post ) {
		wp_nonce_field( 'minimalio_seo_save_post', self::NONCE );

		$m = function ( $key ) use ( $post ) {
			return (string) get_post_meta( $post->ID, $key, true );
		};

		$is_front        = Minimalio_SEO_Options::static_front_page_id() === (int) $post->ID;
		$jetpack         = self::jetpack( $post->ID, $is_front );
		$title_template  = $is_front ? Minimalio_SEO_Options::FRONT_PAGE_TITLE : Minimalio_SEO_Options::post_type_title( $post->post_type );
		if ( '' !== $jetpack['title'] ) {
			$title_template = $jetpack['title'];
		}
		$desc_fallback = '' !== $jetpack['description'] ? $jetpack['description'] : Minimalio_SEO_Context::auto_description( $post );
		if ( $is_front && '' === $desc_fallback ) {
			$desc_fallback = Minimalio_SEO_Context::clean( get_bloginfo( 'description' ) );
		}
		$robots          = $m( Minimalio_SEO_Post_Meta_Registry::ROBOTS );
		$default_noindex = ! $is_front && ( Minimalio_SEO_Options::post_type_noindex( $post->post_type ) || $jetpack['noindex'] );
		$default_label   = $default_noindex ? __( 'Default (noindex)', 'minimalio-seo' ) : __( 'Default (index)', 'minimalio-seo' );
		$advanced_open   = '' !== $robots || '' !== $m( Minimalio_SEO_Post_Meta_Registry::CANONICAL ) || '' !== $m( Minimalio_SEO_Post_Meta_Registry::NOFOLLOW );
		$social_open     = '' !== $m( Minimalio_SEO_Post_Meta_Registry::OG_TITLE ) || '' !== $m( Minimalio_SEO_Post_Meta_Registry::OG_DESCRIPTION ) || '' !== $m( Minimalio_SEO_Post_Meta_Registry::OG_IMAGE );
		?>
		<div class="minimalio-seo-box">

			<?php if ( $is_front ) : ?>
				<p class="minimalio-seo-front-badge">
					<strong><?php esc_html_e( 'This is your homepage.', 'minimalio-seo' ); ?></strong>
					<?php esc_html_e( 'The SEO title, description and share image set here are what Google and social networks show for your site.', 'minimalio-seo' ); ?>
				</p>
			<?php endif; ?>

			<div class="minimalio-seo-snippet" aria-live="polite">
				<div class="minimalio-seo-snippet__label"><?php esc_html_e( 'Google preview', 'minimalio-seo' ); ?></div>
				<div class="minimalio-seo-snippet__url"></div>
				<div class="minimalio-seo-snippet__title"></div>
				<div class="minimalio-seo-snippet__desc"></div>
			</div>

			<p class="minimalio-seo-field">
				<label for="minimalio-seo-title"><strong><?php esc_html_e( 'SEO title', 'minimalio-seo' ); ?></strong></label>
				<input type="text" id="minimalio-seo-title" name="minimalio_seo[title]" class="widefat" value="<?php echo esc_attr( $m( Minimalio_SEO_Post_Meta_Registry::TITLE ) ); ?>" placeholder="<?php echo esc_attr( $title_template ); ?>" data-count="60" />
				<span class="description"><?php esc_html_e( 'Leave empty to use the default. Variables: %title%, %sitename%, %tagline%, %sep%', 'minimalio-seo' ); ?></span>
			</p>

			<p class="minimalio-seo-field">
				<label for="minimalio-seo-description"><strong><?php esc_html_e( 'Meta description', 'minimalio-seo' ); ?></strong></label>
				<textarea id="minimalio-seo-description" name="minimalio_seo[description]" class="widefat" rows="3" data-count="160" placeholder="<?php echo esc_attr( $desc_fallback ); ?>"><?php echo esc_textarea( $m( Minimalio_SEO_Post_Meta_Registry::DESCRIPTION ) ); ?></textarea>
				<span class="description"><?php esc_html_e( 'A short, compelling summary of this page. Leave empty to use the excerpt.', 'minimalio-seo' ); ?></span>
			</p>

			<details class="minimalio-seo-details"<?php echo $social_open ? ' open' : ''; ?>>
				<summary><?php esc_html_e( 'Social sharing', 'minimalio-seo' ); ?></summary>
				<p class="minimalio-seo-field">
					<label for="minimalio-seo-og-title"><?php esc_html_e( 'Social title', 'minimalio-seo' ); ?></label>
					<input type="text" id="minimalio-seo-og-title" name="minimalio_seo[og_title]" class="widefat" value="<?php echo esc_attr( $m( Minimalio_SEO_Post_Meta_Registry::OG_TITLE ) ); ?>" placeholder="<?php esc_attr_e( 'Same as the page title', 'minimalio-seo' ); ?>" />
				</p>
				<p class="minimalio-seo-field">
					<label for="minimalio-seo-og-description"><?php esc_html_e( 'Social description', 'minimalio-seo' ); ?></label>
					<textarea id="minimalio-seo-og-description" name="minimalio_seo[og_description]" class="widefat" rows="2" placeholder="<?php esc_attr_e( 'Same as the meta description', 'minimalio-seo' ); ?>"><?php echo esc_textarea( $m( Minimalio_SEO_Post_Meta_Registry::OG_DESCRIPTION ) ); ?></textarea>
				</p>
				<div class="minimalio-seo-field">
					<label><?php esc_html_e( 'Social image', 'minimalio-seo' ); ?></label>
					<?php Minimalio_SEO_Settings_Page::image_field( 'minimalio-seo-og-image', 'minimalio_seo[og_image]', (int) $m( Minimalio_SEO_Post_Meta_Registry::OG_IMAGE ) ); ?>
					<span class="description"><?php esc_html_e( 'Shown when this page is shared on Facebook, LinkedIn, X, Pinterest, WhatsApp… Defaults to the featured image. Recommended: 1200 × 630 px.', 'minimalio-seo' ); ?></span>
				</div>
			</details>

			<details class="minimalio-seo-details"<?php echo $advanced_open ? ' open' : ''; ?>>
				<summary><?php esc_html_e( 'Advanced', 'minimalio-seo' ); ?></summary>
				<?php if ( ! $is_front ) : ?>
				<p class="minimalio-seo-field">
					<label for="minimalio-seo-robots"><?php esc_html_e( 'Search engine indexing', 'minimalio-seo' ); ?></label>
					<select id="minimalio-seo-robots" name="minimalio_seo[robots]">
						<option value="" <?php selected( $robots, '' ); ?>><?php echo esc_html( $default_label ); ?></option>
						<option value="index" <?php selected( $robots, 'index' ); ?>><?php esc_html_e( 'Index: show in search results', 'minimalio-seo' ); ?></option>
						<option value="noindex" <?php selected( $robots, 'noindex' ); ?>><?php esc_html_e( 'Noindex: hide from search results', 'minimalio-seo' ); ?></option>
					</select>
				</p>
				<?php endif; ?>
				<p class="minimalio-seo-field">
					<label>
						<input type="checkbox" name="minimalio_seo[nofollow]" value="1" <?php checked( $m( Minimalio_SEO_Post_Meta_Registry::NOFOLLOW ), '1' ); ?> />
						<?php esc_html_e( 'Nofollow: ask search engines not to follow links on this page', 'minimalio-seo' ); ?>
					</label>
				</p>
				<p class="minimalio-seo-field">
					<label for="minimalio-seo-canonical"><?php esc_html_e( 'Canonical URL', 'minimalio-seo' ); ?></label>
					<input type="url" id="minimalio-seo-canonical" name="minimalio_seo[canonical]" class="widefat" value="<?php echo esc_attr( $m( Minimalio_SEO_Post_Meta_Registry::CANONICAL ) ); ?>" placeholder="<?php echo esc_attr( get_permalink( $post ) ); ?>" />
					<span class="description"><?php esc_html_e( 'Only needed when this content is a duplicate of another URL.', 'minimalio-seo' ); ?></span>
				</p>
			</details>
		</div>
		<?php
	}

	/**
	 * Save.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE ] ), 'minimalio_seo_save_post' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['minimalio_seo'] ) || ! is_array( $_POST['minimalio_seo'] ) ) {
			return;
		}

		$data = wp_unslash( $_POST['minimalio_seo'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Sanitized per field below.
		$get  = function ( $key ) use ( $data ) {
			return isset( $data[ $key ] ) ? $data[ $key ] : '';
		};

		$values = [
			Minimalio_SEO_Post_Meta_Registry::TITLE          => sanitize_text_field( $get( 'title' ) ),
			Minimalio_SEO_Post_Meta_Registry::DESCRIPTION    => sanitize_textarea_field( $get( 'description' ) ),
			Minimalio_SEO_Post_Meta_Registry::OG_TITLE       => sanitize_text_field( $get( 'og_title' ) ),
			Minimalio_SEO_Post_Meta_Registry::OG_DESCRIPTION => sanitize_textarea_field( $get( 'og_description' ) ),
			Minimalio_SEO_Post_Meta_Registry::OG_IMAGE       => absint( $get( 'og_image' ) ),
			Minimalio_SEO_Post_Meta_Registry::ROBOTS         => Minimalio_SEO_Post_Meta_Registry::sanitize_robots( $get( 'robots' ) ),
			Minimalio_SEO_Post_Meta_Registry::NOFOLLOW       => Minimalio_SEO_Post_Meta_Registry::sanitize_flag( $get( 'nofollow' ) ),
			Minimalio_SEO_Post_Meta_Registry::CANONICAL      => esc_url_raw( trim( $get( 'canonical' ) ) ),
		];

		// The homepage is always indexable.
		if ( Minimalio_SEO_Options::static_front_page_id() === (int) $post_id ) {
			$values[ Minimalio_SEO_Post_Meta_Registry::ROBOTS ] = '';
		}

		// Store only what is set, so the database stays clean and defaults keep working.
		foreach ( $values as $key => $value ) {
			if ( '' === $value || 0 === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
	}
}
