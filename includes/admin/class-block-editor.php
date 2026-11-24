<?php
/**
 * Block editor integration: an "SEO" panel in the document sidebar
 * (next to Featured image, Excerpt, etc.).
 *
 * Fields are saved as registered post meta through the REST API together
 * with the post, so no extra save handler is needed.
 *
 * @package minimalio-seo
 */

defined( 'ABSPATH' ) || exit;

class Minimalio_SEO_Block_Editor {

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'assets' ] );
	}

	/**
	 * Enqueue the panel script on post edit screens.
	 */
	public function assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base ) {
			return; // Not the post editor (e.g. site editor, widgets).
		}

		$post_types = Minimalio_SEO_Options::post_types();
		if ( ! array_key_exists( $screen->post_type, $post_types ) ) {
			return;
		}

		global $post;

		wp_enqueue_style( 'minimalio-seo-admin', MINIMALIO_SEO_URL . 'assets/admin.css', [], MINIMALIO_SEO_VERSION );
		wp_enqueue_script(
			'minimalio-seo-editor',
			MINIMALIO_SEO_URL . 'assets/editor.js',
			[ 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-block-editor', 'wp-i18n', 'wp-url' ],
			MINIMALIO_SEO_VERSION,
			true
		);
		wp_set_script_translations( 'minimalio-seo-editor', 'minimalio-seo', MINIMALIO_SEO_DIR . 'languages' );

		$templates = [];
		$noindex   = [];
		foreach ( array_keys( $post_types ) as $post_type ) {
			$templates[ $post_type ] = Minimalio_SEO_Options::post_type_title( $post_type );
			$noindex[ $post_type ]   = Minimalio_SEO_Options::post_type_noindex( $post_type );
		}

		$front_page_id = Minimalio_SEO_Options::static_front_page_id();

		wp_localize_script(
			'minimalio-seo-editor',
			'minimalioSeoEditor',
			[
				'postTypes'       => array_keys( $post_types ),
				'templates'       => $templates,
				'noindexDefaults' => $noindex,
				'frontPageId'     => $front_page_id,
				'frontTitle'      => Minimalio_SEO_Options::FRONT_PAGE_TITLE,
				'siteName'        => Minimalio_SEO_Context::clean( get_bloginfo( 'name' ) ),
				'tagline'         => Minimalio_SEO_Context::clean( get_bloginfo( 'description' ) ),
				'sep'             => Minimalio_SEO_Options::get( 'separator' ),
				'autoDesc'        => $post instanceof WP_Post ? Minimalio_SEO_Context::auto_description( $post ) : '',
				// Values from Jetpack SEO, used while our fields are empty (see Minimalio_SEO_Jetpack_Data).
				'jetpack'         => $post instanceof WP_Post && class_exists( 'Minimalio_SEO_Jetpack_Data' ) ? Minimalio_SEO_Jetpack_Data::for_post( $post->ID, $post->ID === $front_page_id ) : [],
				'keys'            => [
					'title'         => Minimalio_SEO_Post_Meta_Registry::TITLE,
					'description'   => Minimalio_SEO_Post_Meta_Registry::DESCRIPTION,
					'canonical'     => Minimalio_SEO_Post_Meta_Registry::CANONICAL,
					'robots'        => Minimalio_SEO_Post_Meta_Registry::ROBOTS,
					'nofollow'      => Minimalio_SEO_Post_Meta_Registry::NOFOLLOW,
					'ogTitle'       => Minimalio_SEO_Post_Meta_Registry::OG_TITLE,
					'ogDescription' => Minimalio_SEO_Post_Meta_Registry::OG_DESCRIPTION,
					'ogImage'       => Minimalio_SEO_Post_Meta_Registry::OG_IMAGE,
				],
			]
		);
	}
}
