<?php
/**
 * XML sitemaps.
 *
 * WordPress (5.5+) already generates sitemaps at /wp-sitemap.xml and lists them
 * in robots.txt. We only tune them:
 * - turn them on/off,
 * - leave out anything that is noindexed (post types, taxonomies, single items, authors),
 * - add <lastmod>, which Google uses to schedule recrawls.
 *
 * @package minimalio-seo
 */

defined( 'ABSPATH' ) || exit;

class Minimalio_SEO_Sitemap {

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_filter( 'wp_sitemaps_enabled', [ $this, 'enabled' ] );
		add_filter( 'wp_sitemaps_add_provider', [ $this, 'providers' ], 10, 2 );
		add_filter( 'wp_sitemaps_post_types', [ $this, 'post_types' ] );
		add_filter( 'wp_sitemaps_taxonomies', [ $this, 'taxonomies' ] );
		add_filter( 'wp_sitemaps_posts_query_args', [ $this, 'posts_query_args' ], 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies_query_args', [ $this, 'terms_query_args' ], 10, 2 );
		add_filter( 'wp_sitemaps_posts_entry', [ $this, 'post_entry' ], 10, 2 );
		add_filter( 'wp_sitemaps_posts_show_on_front_entry', [ $this, 'front_entry' ] );
	}

	/**
	 * Enable / disable.
	 *
	 * @param bool $enabled Core value.
	 * @return bool
	 */
	public function enabled( $enabled ) {
		// Never for private (-1) or discouraged (0) sites, which core's own check misses for -1.
		return $enabled && minimalio_seo_is_public() && (bool) Minimalio_SEO_Options::get( 'sitemap_enabled' );
	}

	/**
	 * Drop the users sitemap when author archives are noindexed.
	 *
	 * @param WP_Sitemaps_Provider|false $provider Provider.
	 * @param string                     $name     Provider name.
	 * @return WP_Sitemaps_Provider|false
	 */
	public function providers( $provider, $name ) {
		if ( 'users' === $name && Minimalio_SEO_Options::get( 'noindex_author' ) ) {
			return false;
		}
		return $provider;
	}

	/**
	 * Remove post types that are noindexed by default.
	 *
	 * @param WP_Post_Type[] $post_types Post types.
	 * @return WP_Post_Type[]
	 */
	public function post_types( $post_types ) {
		foreach ( array_keys( $post_types ) as $post_type ) {
			if ( Minimalio_SEO_Options::post_type_noindex( $post_type ) ) {
				unset( $post_types[ $post_type ] );
			}
		}
		return $post_types;
	}

	/**
	 * Remove taxonomies that are noindexed by default.
	 *
	 * @param WP_Taxonomy[] $taxonomies Taxonomies.
	 * @return WP_Taxonomy[]
	 */
	public function taxonomies( $taxonomies ) {
		unset( $taxonomies['post_format'] );
		foreach ( array_keys( $taxonomies ) as $taxonomy ) {
			if ( Minimalio_SEO_Options::taxonomy_noindex( $taxonomy ) ) {
				unset( $taxonomies[ $taxonomy ] );
			}
		}
		return $taxonomies;
	}

	/**
	 * Exclude individual posts set to noindex or with a canonical pointing elsewhere.
	 *
	 * @param array  $args      WP_Query args.
	 * @param string $post_type Post type.
	 * @return array
	 */
	public function posts_query_args( $args, $post_type ) {
		$args['meta_query'] = isset( $args['meta_query'] ) ? (array) $args['meta_query'] : []; // phpcs:ignore WordPress.DB.SlowDBQuery
		$args['meta_query'][] = [
			'relation' => 'OR',
			[
				'key'     => Minimalio_SEO_Post_Meta_Registry::ROBOTS,
				'compare' => 'NOT EXISTS',
			],
			[
				'key'     => Minimalio_SEO_Post_Meta_Registry::ROBOTS,
				'value'   => 'noindex',
				'compare' => '!=',
			],
		];
		$args['meta_query'][] = [
			'relation' => 'OR',
			[
				'key'     => Minimalio_SEO_Post_Meta_Registry::CANONICAL,
				'compare' => 'NOT EXISTS',
			],
			[
				'key'     => Minimalio_SEO_Post_Meta_Registry::CANONICAL,
				'value'   => '',
				'compare' => '=',
			],
		];
		return $args;
	}

	/**
	 * Exclude individual terms set to noindex.
	 *
	 * @param array  $args     WP_Term_Query args.
	 * @param string $taxonomy Taxonomy.
	 * @return array
	 */
	public function terms_query_args( $args, $taxonomy ) {
		$args['meta_query'] = isset( $args['meta_query'] ) ? (array) $args['meta_query'] : []; // phpcs:ignore WordPress.DB.SlowDBQuery
		$args['meta_query'][] = [
			'relation' => 'OR',
			[
				'key'     => Minimalio_SEO_Post_Meta_Registry::ROBOTS,
				'compare' => 'NOT EXISTS',
			],
			[
				'key'     => Minimalio_SEO_Post_Meta_Registry::ROBOTS,
				'value'   => 'noindex',
				'compare' => '!=',
			],
		];
		return $args;
	}

	/**
	 * Add <lastmod> to post entries.
	 *
	 * @param array   $entry Entry.
	 * @param WP_Post $post  Post.
	 * @return array
	 */
	public function post_entry( $entry, $post ) {
		$entry['lastmod'] = get_post_modified_time( 'c', true, $post );
		return $entry;
	}

	/**
	 * Add <lastmod> to the home page entry (latest published post).
	 *
	 * @param array $entry Entry.
	 * @return array
	 */
	public function front_entry( $entry ) {
		$latest = get_posts(
			[
				'numberposts'      => 1,
				'post_status'      => 'publish',
				'orderby'          => 'modified',
				'suppress_filters' => true,
			]
		);
		if ( $latest ) {
			$entry['lastmod'] = get_post_modified_time( 'c', true, $latest[0] );
		}
		return $entry;
	}
}
