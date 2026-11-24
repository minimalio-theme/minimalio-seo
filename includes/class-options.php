<?php
/**
 * Options storage and meta key registry.
 *
 * @package minimalio-seo
 */

defined( 'ABSPATH' ) || exit;

/**
 * All global settings live in one option: minimalio_seo_options.
 */
class Minimalio_SEO_Options {

	const KEY = 'minimalio_seo_options';

	/**
	 * Default title for a static front page whose SEO title is empty.
	 * ("Home – Site name" is never what anyone wants in Google.)
	 */
	const FRONT_PAGE_TITLE = '%sitename% %sep% %tagline%';

	/**
	 * ID of the page set as homepage in Settings > Reading, or 0 when the
	 * homepage shows the latest posts.
	 *
	 * When this returns an ID, that page's editor is the single place to edit
	 * homepage SEO; the homepage fields in our settings are hidden and ignored.
	 *
	 * @return int
	 */
	public static function static_front_page_id() {
		if ( 'page' !== get_option( 'show_on_front' ) ) {
			return 0;
		}
		$id = (int) get_option( 'page_on_front' );
		return $id && get_post( $id ) ? $id : 0;
	}

	/**
	 * Cached merged options.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Static defaults. Per post type / taxonomy keys are resolved in the helpers below.
	 *
	 * @return array
	 */
	public static function defaults() {
		return [
			// Titles.
			'separator'           => '–',
			'home_title'          => '%sitename% %sep% %tagline%',
			'home_description'    => '',
			'author_title'        => '%title% %sep% %sitename%',
			'date_title'          => '%title% %sep% %sitename%',
			'search_title'        => '%title% %sep% %sitename%',
			'notfound_title'      => '%title% %sep% %sitename%',

			// Indexing.
			'noindex_author'      => true,
			'noindex_date'        => true,
			'redirect_attachment' => true,

			// Social.
			'default_image'       => 0,
			'twitter_site'        => '',

			// Structured data / Knowledge Graph.
			'schema_enabled'      => true,
			'entity_type'         => 'organization',
			'entity_name'         => '',
			'entity_logo'         => 0,
			'social_profiles'     => '',

			// Contact & location (optional).
			'contact_phone'       => '',
			'address_street'      => '',
			'address_city'        => '',
			'address_postcode'    => '',
			'address_region'      => '',
			'address_country'     => '',

			// Sitemap.
			'sitemap_enabled'     => true,

			// Webmaster verification.
			'verify_google'       => '',
			'verify_bing'         => '',
			'verify_pinterest'    => '',
			'verify_facebook'     => '',
		];
	}

	/**
	 * All options merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::KEY, [] );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : [], self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * Get one option.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Reset the in-memory cache (after saving).
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Title template for a single post type.
	 *
	 * @param string $post_type Post type.
	 * @return string
	 */
	public static function post_type_title( $post_type ) {
		$value = self::get( 'pt_title_' . $post_type, '' );
		return '' !== $value ? $value : '%title% %sep% %sitename%';
	}

	/**
	 * Is a post type noindexed by default?
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public static function post_type_noindex( $post_type ) {
		return (bool) self::get( 'pt_noindex_' . $post_type, false );
	}

	/**
	 * Title template for a post type archive.
	 *
	 * @param string $post_type Post type.
	 * @return string
	 */
	public static function archive_title( $post_type ) {
		$value = self::get( 'pta_title_' . $post_type, '' );
		return '' !== $value ? $value : '%title% %sep% %page% %sep% %sitename%';
	}

	/**
	 * Title template for a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return string
	 */
	public static function taxonomy_title( $taxonomy ) {
		$value = self::get( 'tax_title_' . $taxonomy, '' );
		return '' !== $value ? $value : '%title% %sep% %page% %sep% %sitename%';
	}

	/**
	 * Is a taxonomy noindexed by default?
	 *
	 * Taxonomy archives (categories, tags…) are hidden from search engines
	 * until the user unticks them in the settings. Single terms can still be
	 * set to "index" individually.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return bool
	 */
	public static function taxonomy_noindex( $taxonomy ) {
		return (bool) self::get( 'tax_noindex_' . $taxonomy, true );
	}

	/**
	 * Public post types that get SEO controls (attachments excluded).
	 *
	 * @return WP_Post_Type[]
	 */
	public static function post_types() {
		$types = get_post_types( [ 'public' => true ], 'objects' );
		unset( $types['attachment'] );
		return apply_filters( 'minimalio_seo_post_types', $types );
	}

	/**
	 * Public taxonomies that get SEO controls.
	 *
	 * @return WP_Taxonomy[]
	 */
	public static function taxonomies() {
		$taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );
		unset( $taxonomies['post_format'] );
		return apply_filters( 'minimalio_seo_taxonomies', $taxonomies );
	}

	/**
	 * Separators offered in the settings.
	 *
	 * @return string[]
	 */
	public static function separators() {
		return [ '–', '—', '-', '|', '·', '•', '/', '»' ];
	}
}

/**
 * Post / term meta keys.
 */
class Minimalio_SEO_Post_Meta_Registry {

	const TITLE          = '_minimalio_seo_title';
	const DESCRIPTION    = '_minimalio_seo_description';
	const CANONICAL      = '_minimalio_seo_canonical';
	const ROBOTS         = '_minimalio_seo_robots';      // '' | 'index' | 'noindex'.
	const NOFOLLOW       = '_minimalio_seo_nofollow';    // '' | '1'.
	const OG_TITLE       = '_minimalio_seo_og_title';
	const OG_DESCRIPTION = '_minimalio_seo_og_description';
	const OG_IMAGE       = '_minimalio_seo_og_image';    // Attachment ID.

	/**
	 * Register post meta so it is protected, typed and available in the REST API
	 * (useful for the block editor and for headless setups).
	 */
	public static function register() {
		add_action( 'init', [ __CLASS__, 'register_meta' ], 20 );

		// The homepage can never be noindexed. If a page that was set to noindex
		// becomes the homepage, drop that value so it also returns to the sitemap.
		add_action( 'update_option_page_on_front', [ __CLASS__, 'clear_front_page_robots' ] );
		add_action( 'update_option_show_on_front', [ __CLASS__, 'clear_front_page_robots' ] );
	}

	/**
	 * Remove a stored noindex/index value from the current static front page.
	 */
	public static function clear_front_page_robots() {
		$front_id = Minimalio_SEO_Options::static_front_page_id();
		if ( $front_id ) {
			delete_post_meta( $front_id, self::ROBOTS );
		}
	}

	/**
	 * Register meta for each public post type.
	 */
	public static function register_meta() {
		$string_keys = [
			self::TITLE          => 'sanitize_text_field',
			self::DESCRIPTION    => 'sanitize_textarea_field',
			self::CANONICAL      => 'esc_url_raw',
			self::ROBOTS         => [ __CLASS__, 'sanitize_robots' ],
			self::NOFOLLOW       => [ __CLASS__, 'sanitize_flag' ],
			self::OG_TITLE       => 'sanitize_text_field',
			self::OG_DESCRIPTION => 'sanitize_textarea_field',
		];

		foreach ( array_keys( Minimalio_SEO_Options::post_types() ) as $post_type ) {
			// Post meta is only exposed to the REST API (and so to the block editor)
			// when the post type supports custom fields. Portfolio items don't by default.
			if ( ! post_type_supports( $post_type, 'custom-fields' ) ) {
				add_post_type_support( $post_type, 'custom-fields' );
			}

			foreach ( $string_keys as $key => $sanitize ) {
				register_post_meta(
					$post_type,
					$key,
					[
						'type'              => 'string',
						'single'            => true,
						'show_in_rest'      => true,
						'sanitize_callback' => $sanitize,
						'auth_callback'     => [ __CLASS__, 'can_edit' ],
					]
				);
			}
			register_post_meta(
				$post_type,
				self::OG_IMAGE,
				[
					'type'              => 'integer',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'absint',
					'auth_callback'     => [ __CLASS__, 'can_edit' ],
				]
			);
		}
	}

	/**
	 * Meta auth callback.
	 *
	 * @param bool   $allowed  Unused.
	 * @param string $meta_key Unused.
	 * @param int    $post_id  Post ID.
	 * @return bool
	 */
	public static function can_edit( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Robots value sanitizer.
	 *
	 * @param string $value Raw.
	 * @return string
	 */
	public static function sanitize_robots( $value ) {
		return in_array( $value, [ 'index', 'noindex' ], true ) ? $value : '';
	}

	/**
	 * Checkbox flag sanitizer.
	 *
	 * @param mixed $value Raw.
	 * @return string
	 */
	public static function sanitize_flag( $value ) {
		return $value ? '1' : '';
	}
}
