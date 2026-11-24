<?php
/**
 * Use SEO data previously entered in Jetpack SEO Tools.
 *
 * Jetpack (and WordPress.com) store a custom title, meta description and
 * "hide from search engines" flag per post, plus a homepage description.
 * Minimalio SEO switches Jetpack's own output off, so without this those
 * values would silently disappear from the site.
 *
 * Nothing is copied: Jetpack's value is only used while the matching
 * Minimalio SEO field is empty. Filling in our field overrides it. Works
 * whether or not Jetpack is still active, because the data stays in the
 * database.
 *
 * @package minimalio-seo
 */

defined( 'ABSPATH' ) || exit;

class Minimalio_SEO_Jetpack_Data {

	const DESCRIPTION = 'advanced_seo_description';
	const TITLE       = 'jetpack_seo_html_title';
	const NOINDEX     = 'jetpack_seo_noindex';
	const FRONT_PAGE  = 'advanced_seo_front_page_description';

	/**
	 * Hooks. Priority 5, so developer filters at the default priority still
	 * see (and can change) the final value.
	 */
	public function __construct() {
		add_filter( 'minimalio_seo_title', [ $this, 'title' ], 5, 2 );
		add_filter( 'minimalio_seo_description', [ $this, 'description' ], 5, 2 );
		add_filter( 'minimalio_seo_robots', [ $this, 'robots' ], 5, 2 );
		add_filter( 'wp_sitemaps_posts_query_args', [ $this, 'sitemap_query_args' ], 20 );
	}

	/**
	 * Jetpack values for one post, for the editor's Google preview.
	 *
	 * @param int  $post_id  Post ID.
	 * @param bool $is_front Is this the static homepage?
	 * @return array{title: string, description: string, noindex: bool}
	 */
	public static function for_post( $post_id, $is_front = false ) {
		$description = Minimalio_SEO_Context::clean( get_post_meta( $post_id, self::DESCRIPTION, true ) );
		if ( '' === $description && $is_front ) {
			$description = Minimalio_SEO_Context::clean( get_option( self::FRONT_PAGE, '' ) );
		}
		return [
			'title'       => Minimalio_SEO_Context::clean( get_post_meta( $post_id, self::TITLE, true ) ),
			'description' => $description,
			'noindex'     => ! $is_front && (bool) get_post_meta( $post_id, self::NOINDEX, true ),
		];
	}

	/**
	 * Does the page show a single post (or a static homepage / blog page)?
	 *
	 * @param Minimalio_SEO_Context $ctx Context.
	 * @return bool
	 */
	private function is_post( $ctx ) {
		return $ctx->post && in_array( $ctx->type, [ 'singular', 'front_page', 'blog' ], true );
	}

	/**
	 * Jetpack's custom title is the complete <title>, as in Jetpack.
	 *
	 * @param string                $title Resolved title.
	 * @param Minimalio_SEO_Context $ctx   Context.
	 * @return string
	 */
	public function title( $title, $ctx ) {
		if ( ! $this->is_post( $ctx ) || '' !== $ctx->meta( Minimalio_SEO_Post_Meta_Registry::TITLE ) ) {
			return $title;
		}
		$jetpack = Minimalio_SEO_Context::clean( get_post_meta( $ctx->post->ID, self::TITLE, true ) );
		return '' !== $jetpack ? $jetpack : $title;
	}

	/**
	 * Post description, then (homepage only) Jetpack's front page description.
	 *
	 * @param string                $desc Resolved description.
	 * @param Minimalio_SEO_Context $ctx  Context.
	 * @return string
	 */
	public function description( $desc, $ctx ) {
		if ( $this->is_post( $ctx ) ) {
			if ( '' !== $ctx->meta( Minimalio_SEO_Post_Meta_Registry::DESCRIPTION ) ) {
				return $desc;
			}
			$jetpack = Minimalio_SEO_Context::clean( get_post_meta( $ctx->post->ID, self::DESCRIPTION, true ) );
			if ( '' !== $jetpack ) {
				return $jetpack;
			}
		}

		$is_home = 'front_page' === $ctx->type
			|| ( 'home' === $ctx->type && '' === (string) Minimalio_SEO_Options::get( 'home_description' ) );
		if ( $is_home ) {
			$jetpack = Minimalio_SEO_Context::clean( get_option( self::FRONT_PAGE, '' ) );
			if ( '' !== $jetpack ) {
				return $jetpack;
			}
		}

		return $desc;
	}

	/**
	 * Jetpack's "hide from search engines" applies while our per-post setting
	 * is on "default". The homepage is never noindexed.
	 *
	 * @param array                 $robots noindex / nofollow.
	 * @param Minimalio_SEO_Context $ctx    Context.
	 * @return array
	 */
	public function robots( $robots, $ctx ) {
		if ( 'singular' !== $ctx->type && 'blog' !== $ctx->type ) {
			return $robots;
		}
		if ( ! $ctx->post || '' !== $ctx->meta( Minimalio_SEO_Post_Meta_Registry::ROBOTS ) ) {
			return $robots;
		}
		if ( get_post_meta( $ctx->post->ID, self::NOINDEX, true ) ) {
			$robots['noindex'] = true;
		}
		return $robots;
	}

	/**
	 * Leave posts hidden in Jetpack out of the sitemap too (unless our
	 * per-post setting says "index"). Only adds the query clause when the
	 * site actually has such posts.
	 *
	 * @param array $args WP_Query args.
	 * @return array
	 */
	public function sitemap_query_args( $args ) {
		if ( ! $this->has_jetpack_noindex() ) {
			return $args;
		}
		$args['meta_query']   = isset( $args['meta_query'] ) ? (array) $args['meta_query'] : []; // phpcs:ignore WordPress.DB.SlowDBQuery
		$args['meta_query'][] = [
			'relation' => 'OR',
			[
				'key'     => self::NOINDEX,
				'compare' => 'NOT EXISTS',
			],
			[
				'key'     => self::NOINDEX,
				'value'   => [ '', '0' ],
				'compare' => 'IN',
			],
			[
				'key'   => Minimalio_SEO_Post_Meta_Registry::ROBOTS,
				'value' => 'index',
			],
		];
		return $args;
	}

	/**
	 * Is any post flagged noindex in Jetpack?
	 *
	 * @return bool
	 */
	private function has_jetpack_noindex() {
		global $wpdb;
		static $has = null;
		if ( null === $has ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$has = (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value NOT IN ('', '0') LIMIT 1",
					self::NOINDEX
				)
			);
		}
		return $has;
	}
}
