<?php
/**
 * Images in the XML sitemap.
 *
 * For a portfolio the images are the content, and Google Images is a real
 * source of visitors. Each post, page and portfolio item lists its images:
 * the featured image plus every image in the content (Minimalio gallery
 * block, core Image / Gallery blocks, classic editor images).
 *
 * WordPress core's sitemap renderer only allows loc/lastmod/changefreq/priority,
 * so a small subclass adds the <image:image> elements to core's own output.
 *
 * @see https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps
 *
 * @package minimalio-seo
 */

defined( 'ABSPATH' ) || exit;

class Minimalio_SEO_Sitemap_Images {

	const NS = 'http://www.google.com/schemas/sitemap-image/1.1';

	/**
	 * Google's limit per <url>.
	 */
	const MAX = 1000;

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_filter( 'wp_sitemaps_posts_entry', [ $this, 'entry' ], 20, 2 );
		add_action( 'wp_sitemaps_init', [ $this, 'renderer' ] );
	}

	/**
	 * Use our renderer (core's, plus images).
	 *
	 * @param WP_Sitemaps $sitemaps Sitemaps object.
	 */
	public function renderer( $sitemaps ) {
		if ( class_exists( 'Minimalio_SEO_Sitemap_Renderer' ) && $sitemaps->renderer instanceof WP_Sitemaps_Renderer ) {
			$sitemaps->renderer = new Minimalio_SEO_Sitemap_Renderer();
		}
	}

	/**
	 * Add the post's images to its sitemap entry.
	 *
	 * @param array   $entry Entry (loc, lastmod).
	 * @param WP_Post $post  Post.
	 * @return array
	 */
	public function entry( $entry, $post ) {
		$images = self::images( $post );
		if ( $images ) {
			$entry['images'] = $images;
		}
		return $entry;
	}

	/**
	 * Full-size URLs of the images shown on a post.
	 *
	 * @param WP_Post $post Post.
	 * @return string[]
	 */
	public static function images( $post ) {
		$ids  = [];
		$srcs = [];

		$thumb = (int) get_post_thumbnail_id( $post );
		if ( $thumb ) {
			$ids[] = $thumb;
		}

		// Images in the content. A wp-image-123 class (all image blocks, including
		// the Minimalio gallery, and the classic editor) gives the attachment, so
		// the original file is listed instead of a resized copy.
		if ( false !== stripos( $post->post_content, '<img' ) && preg_match_all( '/<img\s[^>]*>/i', $post->post_content, $tags ) ) {
			$uploads = wp_get_upload_dir();
			foreach ( $tags[0] as $tag ) {
				if ( preg_match( '/\bwp-image-(\d+)\b/', $tag, $m ) ) {
					$ids[] = (int) $m[1];
				} elseif ( preg_match( '/\ssrc=["\']([^"\']+)["\']/i', $tag, $m ) && 0 === strpos( $m[1], $uploads['baseurl'] ) ) {
					$srcs[] = $m[1]; // Image from this site's uploads without an ID.
				}
			}
		}

		$ids = array_values( array_unique( array_filter( $ids ) ) );
		if ( $ids ) {
			_prime_post_caches( $ids, false, true ); // One query for all attachments.
		}

		$urls = [];
		foreach ( $ids as $id ) {
			if ( wp_attachment_is_image( $id ) ) {
				$url = wp_get_attachment_url( $id );
				if ( $url ) {
					$urls[] = $url;
				}
			}
		}

		$urls = array_slice( array_values( array_unique( array_merge( $urls, $srcs ) ) ), 0, self::MAX );

		/**
		 * Filters the image URLs listed for a post in the sitemap.
		 *
		 * @param string[] $urls Image URLs (return an empty array to list none).
		 * @param WP_Post  $post Post.
		 */
		return (array) apply_filters( 'minimalio_seo_sitemap_images', $urls, $post );
	}
}

if ( class_exists( 'WP_Sitemaps_Renderer' ) ) {
	/**
	 * Core renderer plus <image:image> elements.
	 */
	class Minimalio_SEO_Sitemap_Renderer extends WP_Sitemaps_Renderer {

		/**
		 * Let core build the sitemap without the images, then add them.
		 *
		 * @param array $url_list URL entries.
		 * @return string|false
		 */
		public function get_sitemap_xml( $url_list ) {
			$images = [];
			foreach ( $url_list as $i => $item ) {
				$images[ $i ] = isset( $item['images'] ) ? (array) $item['images'] : [];
				unset( $url_list[ $i ]['images'] );
			}

			$xml = parent::get_sitemap_xml( $url_list );
			if ( ! $xml || ! array_filter( $images ) ) {
				return $xml;
			}

			// Declare the image namespace on <urlset>.
			$xml = preg_replace( '/<urlset\b/', '<urlset xmlns:image="' . Minimalio_SEO_Sitemap_Images::NS . '"', $xml, 1 );

			try {
				$urlset = new SimpleXMLElement( $xml );
			} catch ( Exception $e ) {
				return parent::get_sitemap_xml( $url_list );
			}

			// <url> elements are in the same order as $url_list.
			$index = 0;
			$keys  = array_keys( $images );
			foreach ( $urlset->url as $url ) {
				$key = isset( $keys[ $index ] ) ? $keys[ $index ] : null;
				++$index;
				if ( null === $key ) {
					break;
				}
				foreach ( $images[ $key ] as $src ) {
					$image = $url->addChild( 'image:image', null, Minimalio_SEO_Sitemap_Images::NS );
					$image->addChild( 'image:loc', esc_url( $src ), Minimalio_SEO_Sitemap_Images::NS );
				}
			}

			return $urlset->asXML();
		}
	}
}
