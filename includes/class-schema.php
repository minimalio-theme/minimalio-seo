<?php
/**
 * JSON-LD structured data.
 *
 * Only the types Google actually uses:
 * - Organization / Person (Knowledge Panel, logo, social profiles)
 * - WebSite (site name in search results)
 * - WebPage / CollectionPage
 * - BlogPosting for posts (article rich results, author, dates)
 * - BreadcrumbList (breadcrumb trail in search results)
 *
 * @package minimalio-seo
 */

defined( 'ABSPATH' ) || exit;

class Minimalio_SEO_Schema {

	/**
	 * Script tag for the current page, or empty string.
	 *
	 * @param Minimalio_SEO_Context $ctx Context.
	 * @return string
	 */
	public static function script( $ctx ) {
		if ( ! Minimalio_SEO_Options::get( 'schema_enabled' ) || '404' === $ctx->type ) {
			return '';
		}

		$graph = apply_filters( 'minimalio_seo_schema_graph', self::graph( $ctx ), $ctx );
		if ( ! $graph ) {
			return '';
		}

		$data = [
			'@context' => 'https://schema.org',
			'@graph'   => array_values( $graph ),
		];

		return '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . '</script>';
	}

	/**
	 * Build the @graph.
	 *
	 * @param Minimalio_SEO_Context $ctx Context.
	 * @return array
	 */
	public static function graph( $ctx ) {
		$home      = home_url( '/' );
		$entity_id = $home . '#' . ( 'person' === Minimalio_SEO_Options::get( 'entity_type' ) ? 'person' : 'organization' );
		$graph     = [];

		$graph['entity']  = self::entity( $entity_id, $home );
		$graph['website'] = array_filter(
			[
				'@type'       => 'WebSite',
				'@id'         => $home . '#website',
				'url'         => $home,
				'name'        => Minimalio_SEO_Context::clean( get_bloginfo( 'name' ) ),
				'description' => Minimalio_SEO_Context::clean( get_bloginfo( 'description' ) ),
				'publisher'   => [ '@id' => $entity_id ],
				'inLanguage'  => get_bloginfo( 'language' ),
			]
		);

		if ( 'search' === $ctx->type ) {
			return $graph;
		}

		$url = $ctx->url();
		if ( '' === $url ) {
			return $graph;
		}

		$page_id     = $url . '#webpage';
		$image       = $ctx->image();
		$crumbs      = self::breadcrumbs( $ctx );
		$is_singular = in_array( $ctx->type, [ 'singular', 'front_page' ], true );

		$webpage = [
			'@type'       => $is_singular || 'home' === $ctx->type ? 'WebPage' : 'CollectionPage',
			'@id'         => $page_id,
			'url'         => $url,
			'name'        => $ctx->title(),
			'description' => $ctx->description(),
			'isPartOf'    => [ '@id' => $home . '#website' ],
			'inLanguage'  => get_bloginfo( 'language' ),
		];
		if ( 'home' === $ctx->type || 'front_page' === $ctx->type ) {
			$webpage['about'] = [ '@id' => $entity_id ];
		}
		if ( $is_singular ) {
			$webpage['datePublished'] = get_post_time( 'c', true, $ctx->post );
			$webpage['dateModified']  = get_post_modified_time( 'c', true, $ctx->post );
		}
		if ( $image ) {
			$webpage['primaryImageOfPage'] = [ '@id' => $url . '#primaryimage' ];
			$graph['image']                = self::image_object( $image, $url . '#primaryimage' );
		}
		if ( count( $crumbs ) > 1 ) {
			$webpage['breadcrumb'] = [ '@id' => $url . '#breadcrumb' ];
		}
		$graph['webpage'] = array_filter( $webpage );

		// Blog posts.
		if ( 'singular' === $ctx->type && 'post' === $ctx->post->post_type ) {
			$author_id = (int) $ctx->post->post_author;
			$article   = [
				'@type'            => 'BlogPosting',
				'@id'              => $url . '#article',
				'headline'         => mb_substr( Minimalio_SEO_Context::clean( $ctx->post->post_title ), 0, 110 ),
				'description'      => $ctx->description(),
				'datePublished'    => get_post_time( 'c', true, $ctx->post ),
				'dateModified'     => get_post_modified_time( 'c', true, $ctx->post ),
				'mainEntityOfPage' => [ '@id' => $page_id ],
				'isPartOf'         => [ '@id' => $page_id ],
				'publisher'        => [ '@id' => $entity_id ],
				'inLanguage'       => get_bloginfo( 'language' ),
			];
			if ( $author_id ) {
				$article['author'] = [
					'@type' => 'Person',
					'name'  => get_the_author_meta( 'display_name', $author_id ),
					'url'   => get_author_posts_url( $author_id ),
				];
			}
			if ( $image ) {
				$article['image'] = [ '@id' => $url . '#primaryimage' ];
			}
			$graph['article'] = array_filter( $article );
		}

		if ( count( $crumbs ) > 1 ) {
			$items = [];
			foreach ( $crumbs as $i => $crumb ) {
				$item = [
					'@type'    => 'ListItem',
					'position' => $i + 1,
					'name'     => $crumb['name'],
				];
				// The last item (current page) may omit "item" per Google's guidelines.
				if ( $i < count( $crumbs ) - 1 ) {
					$item['item'] = $crumb['url'];
				}
				$items[] = $item;
			}
			$graph['breadcrumb'] = [
				'@type'           => 'BreadcrumbList',
				'@id'             => $url . '#breadcrumb',
				'itemListElement' => $items,
			];
		}

		return $graph;
	}

	/**
	 * Organization or Person.
	 *
	 * @param string $id   @id.
	 * @param string $home Home URL.
	 * @return array
	 */
	private static function entity( $id, $home ) {
		$is_person = 'person' === Minimalio_SEO_Options::get( 'entity_type' );
		$name      = (string) Minimalio_SEO_Options::get( 'entity_name' );
		$logo_id   = (int) Minimalio_SEO_Options::get( 'entity_logo' );
		if ( ! $logo_id ) {
			$logo_id = (int) get_theme_mod( 'custom_logo' );
		}

		$entity = [
			'@type' => $is_person ? 'Person' : 'Organization',
			'@id'   => $id,
			'name'  => '' !== $name ? $name : Minimalio_SEO_Context::clean( get_bloginfo( 'name' ) ),
			'url'   => $home,
		];

		$logo = $logo_id ? Minimalio_SEO_Context::image_data( $logo_id ) : null;
		if ( $logo ) {
			$image_object                         = self::image_object( $logo, $home . '#logo' );
			$entity[ $is_person ? 'image' : 'logo' ] = $image_object;
		}

		$profiles = array_values(
			array_filter(
				array_map( 'esc_url_raw', array_map( 'trim', preg_split( '/\R/', (string) Minimalio_SEO_Options::get( 'social_profiles' ) ) ) )
			)
		);
		if ( $profiles ) {
			$entity['sameAs'] = $profiles;
		}

		// Contact & location.
		$phone   = (string) Minimalio_SEO_Options::get( 'contact_phone' );
		$address = self::address();
		if ( '' !== $phone ) {
			$entity['telephone'] = $phone;
		}
		if ( $address ) {
			$entity['address'] = $address;
		}

		// An organization with a street address and city is a local business
		// (LocalBusiness is a subtype of Organization). Google recommends an
		// image for it, so the logo is reused.
		if ( ! $is_person && isset( $address['streetAddress'], $address['addressLocality'] ) ) {
			$entity['@type'] = 'LocalBusiness';
			if ( isset( $entity['logo'] ) ) {
				$entity['image'] = [ '@id' => $home . '#logo' ];
			}
		}

		return $entity;
	}

	/**
	 * PostalAddress from the Contact & location settings.
	 *
	 * @return array Empty when no address field is filled in.
	 */
	private static function address() {
		$parts = array_filter(
			[
				'streetAddress'   => (string) Minimalio_SEO_Options::get( 'address_street' ),
				'addressLocality' => (string) Minimalio_SEO_Options::get( 'address_city' ),
				'postalCode'      => (string) Minimalio_SEO_Options::get( 'address_postcode' ),
				'addressRegion'   => (string) Minimalio_SEO_Options::get( 'address_region' ),
				'addressCountry'  => (string) Minimalio_SEO_Options::get( 'address_country' ),
			],
			'strlen'
		);
		return $parts ? [ '@type' => 'PostalAddress' ] + $parts : [];
	}

	/**
	 * ImageObject.
	 *
	 * @param array  $image Image data from Minimalio_SEO_Context::image_data().
	 * @param string $id    @id.
	 * @return array
	 */
	private static function image_object( $image, $id ) {
		return array_filter(
			[
				'@type'      => 'ImageObject',
				'@id'        => $id,
				'url'        => $image['url'],
				'contentUrl' => $image['url'],
				'width'      => $image['width'],
				'height'     => $image['height'],
				'caption'    => $image['alt'],
			]
		);
	}

	/**
	 * Breadcrumb trail: [ [ name, url ], ... ] starting with Home.
	 *
	 * @param Minimalio_SEO_Context $ctx Context.
	 * @return array
	 */
	public static function breadcrumbs( $ctx ) {
		$crumbs = [
			[
				'name' => __( 'Home', 'minimalio-seo' ),
				'url'  => home_url( '/' ),
			],
		];

		switch ( $ctx->type ) {
			case 'singular':
				$post = $ctx->post;

				if ( 'post' === $post->post_type ) {
					$blog_page = (int) get_option( 'page_for_posts' );
					if ( $blog_page && 'page' === get_option( 'show_on_front' ) ) {
						$crumbs[] = self::crumb( get_the_title( $blog_page ), get_permalink( $blog_page ) );
					}
					$categories = get_the_category( $post->ID );
					if ( $categories ) {
						$crumbs = array_merge( $crumbs, self::term_trail( $categories[0] ) );
					}
				} elseif ( is_post_type_hierarchical( $post->post_type ) ) {
					foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor ) {
						$crumbs[] = self::crumb( get_the_title( $ancestor ), get_permalink( $ancestor ) );
					}
				} else {
					// Custom post types such as "portfolio": link to their archive if they have one.
					$archive = get_post_type_archive_link( $post->post_type );
					$object  = get_post_type_object( $post->post_type );
					if ( $archive && $object ) {
						$crumbs[] = self::crumb( $object->labels->name, $archive );
					}
				}
				$crumbs[] = self::crumb( $post->post_title, get_permalink( $post ) );
				break;

			case 'blog':
				$crumbs[] = self::crumb( $ctx->post->post_title, get_permalink( $ctx->post ) );
				break;

			case 'term':
				$crumbs = array_merge( $crumbs, self::term_trail( $ctx->term ) );
				break;

			case 'pt_archive':
			case 'author':
			case 'date':
				$crumbs[] = self::crumb( $ctx->title_var(), $ctx->url() );
				break;
		}

		return apply_filters( 'minimalio_seo_breadcrumbs', $crumbs, $ctx );
	}

	/**
	 * Term with its ancestors.
	 *
	 * @param WP_Term $term Term.
	 * @return array
	 */
	private static function term_trail( $term ) {
		$trail = [];
		foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, $term->taxonomy );
			if ( $ancestor && ! is_wp_error( $ancestor ) ) {
				$trail[] = self::crumb( $ancestor->name, get_term_link( $ancestor ) );
			}
		}
		$trail[] = self::crumb( $term->name, get_term_link( $term ) );
		return $trail;
	}

	/**
	 * One crumb.
	 *
	 * @param string          $name Name.
	 * @param string|WP_Error $url  URL.
	 * @return array
	 */
	private static function crumb( $name, $url ) {
		return [
			'name' => Minimalio_SEO_Context::clean( $name ),
			'url'  => is_wp_error( $url ) ? '' : (string) $url,
		];
	}
}
