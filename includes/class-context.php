<?php
/**
 * Resolves SEO data (title, description, robots, canonical, image) for the current request.
 *
 * Every front-end output (meta tags, Open Graph, schema) reads from here,
 * so all of them always agree with each other.
 *
 * @package minimalio-seo
 */

defined( 'ABSPATH' ) || exit;

class Minimalio_SEO_Context {

	/**
	 * Singleton for the current request.
	 *
	 * @var Minimalio_SEO_Context|null
	 */
	private static $instance = null;

	/**
	 * Page type: home, front_page, blog, singular, term, pt_archive, author, date, search, 404, other.
	 *
	 * @var string
	 */
	public $type = 'other';

	/**
	 * Post for singular / front_page / blog.
	 *
	 * @var WP_Post|null
	 */
	public $post = null;

	/**
	 * Term for taxonomy archives.
	 *
	 * @var WP_Term|null
	 */
	public $term = null;

	/**
	 * Post type for post type archives.
	 *
	 * @var string
	 */
	public $archive_post_type = '';

	/**
	 * Memoized values.
	 *
	 * @var array
	 */
	private $memo = [];

	/**
	 * Get the context for the current request.
	 *
	 * @return Minimalio_SEO_Context
	 */
	public static function current() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Detect what kind of page we are on.
	 */
	private function __construct() {
		if ( is_front_page() && is_home() ) {
			$this->type = 'home';
		} elseif ( is_front_page() ) {
			$this->type = 'front_page';
			$this->post = get_queried_object() instanceof WP_Post ? get_queried_object() : null;
		} elseif ( is_home() ) {
			$page_for_posts = (int) get_option( 'page_for_posts' );
			$this->post     = $page_for_posts ? get_post( $page_for_posts ) : null;
			$this->type     = $this->post ? 'blog' : 'home';
		} elseif ( is_singular() ) {
			$this->type = 'singular';
			$this->post = get_queried_object() instanceof WP_Post ? get_queried_object() : null;
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$this->type = 'term';
			$this->term = get_queried_object() instanceof WP_Term ? get_queried_object() : null;
		} elseif ( is_post_type_archive() ) {
			$this->type              = 'pt_archive';
			$post_type               = get_query_var( 'post_type' );
			$this->archive_post_type = is_array( $post_type ) ? (string) reset( $post_type ) : (string) $post_type;
		} elseif ( is_author() ) {
			$this->type = 'author';
		} elseif ( is_date() ) {
			$this->type = 'date';
		} elseif ( is_search() ) {
			$this->type = 'search';
		} elseif ( is_404() ) {
			$this->type = '404';
		}

		// Safety: singular-like types without a post fall back to generic handling.
		if ( in_array( $this->type, [ 'singular', 'front_page' ], true ) && ! $this->post ) {
			$this->type = 'other';
		}
		if ( 'term' === $this->type && ! $this->term ) {
			$this->type = 'other';
		}
	}

	/* ------------------------------------------------------------------
	 * Meta access
	 * ------------------------------------------------------------------ */

	/**
	 * Read a per-object meta value (post or term).
	 *
	 * @param string $key Meta key.
	 * @return string
	 */
	public function meta( $key ) {
		if ( $this->post ) {
			return (string) get_post_meta( $this->post->ID, $key, true );
		}
		if ( $this->term ) {
			return (string) get_term_meta( $this->term->term_id, $key, true );
		}
		return '';
	}

	/**
	 * Current page number (archives and multi-page posts).
	 *
	 * @return int
	 */
	public function paged() {
		return max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
	}

	/* ------------------------------------------------------------------
	 * Variables
	 * ------------------------------------------------------------------ */

	/**
	 * Value of %title% for the current page.
	 *
	 * @return string
	 */
	public function title_var() {
		switch ( $this->type ) {
			case 'front_page':
			case 'blog':
			case 'singular':
				return $this->post->post_title;
			case 'term':
				return $this->term->name;
			case 'pt_archive':
				return (string) post_type_archive_title( '', false );
			case 'author':
				$author = get_queried_object();
				return $author instanceof WP_User ? $author->display_name : '';
			case 'date':
				if ( is_day() ) {
					return get_the_date();
				}
				if ( is_month() ) {
					return get_the_date( _x( 'F Y', 'monthly archives date format', 'minimalio-seo' ) );
				}
				return get_the_date( _x( 'Y', 'yearly archives date format', 'minimalio-seo' ) );
			case 'search':
				/* translators: %s: search query */
				return sprintf( __( 'Search results for “%s”', 'minimalio-seo' ), get_search_query( false ) );
			case '404':
				return __( 'Page not found', 'minimalio-seo' );
			default:
				return get_bloginfo( 'name' );
		}
	}

	/**
	 * Replace %variables% in a template and tidy up separators.
	 *
	 * Supported: %title%, %sitename%, %tagline%, %sep%, %page%.
	 *
	 * @param string $template Template.
	 * @return string Plain text (not escaped).
	 */
	public function replace( $template ) {
		$paged = $this->paged();
		$sep   = (string) Minimalio_SEO_Options::get( 'separator', '–' );
		$vars  = [
			'%title%'    => $this->title_var(),
			'%sitename%' => get_bloginfo( 'name' ),
			'%tagline%'  => get_bloginfo( 'description' ),
			'%sep%'      => $sep,
			/* translators: %d: page number */
			'%page%'     => $paged > 1 ? sprintf( __( 'Page %d', 'minimalio-seo' ), $paged ) : '',
		];

		return self::clean( strtr( (string) $template, $vars ), $sep );
	}

	/**
	 * Normalize text: decode entities, strip tags, collapse whitespace and
	 * remove dangling or doubled separators left by empty variables.
	 *
	 * @param string $text Text.
	 * @param string $sep  Separator.
	 * @return string
	 */
	public static function clean( $text, $sep = '' ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
		$text = wp_strip_all_tags( $text );
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );

		if ( '' !== $sep ) {
			$q = preg_quote( $sep, '/' );
			// "A – – B" -> "A – B".
			$text = preg_replace( '/\s' . $q . '(?:\s' . $q . ')+\s/u', ' ' . $sep . ' ', $text );
			// Leading "– A" or trailing "A –".
			$text = preg_replace( '/^(?:' . $q . '\s)+|(?:\s' . $q . ')+$/u', '', $text );
			if ( $text === $sep ) {
				$text = '';
			}
		}

		return trim( $text );
	}

	/**
	 * Shorten text to a maximum length on a word boundary.
	 *
	 * @param string $text Text.
	 * @param int    $max  Max characters.
	 * @return string
	 */
	public static function shorten( $text, $max = 160 ) {
		$text = self::clean( $text );
		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}
		$cut   = mb_substr( $text, 0, $max );
		$space = mb_strrpos( $cut, ' ' );
		if ( false !== $space && $space > $max * 0.6 ) {
			$cut = mb_substr( $cut, 0, $space );
		}
		return rtrim( $cut, " ,;:.-–—" ) . '…';
	}

	/**
	 * Automatic description from a post's excerpt or content.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function auto_description( $post ) {
		if ( ! $post || post_password_required( $post ) ) {
			return '';
		}
		$text = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
		if ( function_exists( 'excerpt_remove_blocks' ) ) {
			$text = excerpt_remove_blocks( $text );
		}
		$text = strip_shortcodes( $text );
		return self::shorten( $text, 160 );
	}

	/* ------------------------------------------------------------------
	 * Resolved values
	 * ------------------------------------------------------------------ */

	/**
	 * The document <title>. Empty string = let WordPress decide.
	 *
	 * @return string Plain text.
	 */
	public function title() {
		if ( isset( $this->memo['title'] ) ) {
			return $this->memo['title'];
		}

		$custom   = $this->meta( Minimalio_SEO_Post_Meta_Registry::TITLE );
		$template = '';

		switch ( $this->type ) {
			case 'home':
				$template = Minimalio_SEO_Options::get( 'home_title' );
				break;
			case 'front_page':
				// Static homepage: the page editor is the only source. The homepage
				// fields in the settings are not used here.
				$template = '' !== $custom ? $custom : Minimalio_SEO_Options::FRONT_PAGE_TITLE;
				break;
			case 'blog':
			case 'singular':
				$template = '' !== $custom ? $custom : Minimalio_SEO_Options::post_type_title( $this->post->post_type );
				break;
			case 'term':
				$template = '' !== $custom ? $custom : Minimalio_SEO_Options::taxonomy_title( $this->term->taxonomy );
				break;
			case 'pt_archive':
				$template = Minimalio_SEO_Options::archive_title( $this->archive_post_type );
				break;
			case 'author':
				$template = Minimalio_SEO_Options::get( 'author_title' );
				break;
			case 'date':
				$template = Minimalio_SEO_Options::get( 'date_title' );
				break;
			case 'search':
				$template = Minimalio_SEO_Options::get( 'search_title' );
				break;
			case '404':
				$template = Minimalio_SEO_Options::get( 'notfound_title' );
				break;
		}

		$title = '' !== $template ? $this->replace( $template ) : '';

		$this->memo['title'] = (string) apply_filters( 'minimalio_seo_title', $title, $this );
		return $this->memo['title'];
	}

	/**
	 * Meta description.
	 *
	 * @return string Plain text.
	 */
	public function description() {
		if ( isset( $this->memo['description'] ) ) {
			return $this->memo['description'];
		}

		$custom = $this->meta( Minimalio_SEO_Post_Meta_Registry::DESCRIPTION );
		$desc   = '';

		if ( '' !== $custom ) {
			$desc = $this->replace( $custom );
		} else {
			switch ( $this->type ) {
				case 'home':
					$home = Minimalio_SEO_Options::get( 'home_description' );
					$desc = '' !== $home ? $this->replace( $home ) : self::clean( get_bloginfo( 'description' ) );
					break;
				case 'front_page':
					$desc = self::auto_description( $this->post );
					if ( '' === $desc ) {
						$desc = self::clean( get_bloginfo( 'description' ) );
					}
					break;
				case 'blog':
				case 'singular':
					$desc = self::auto_description( $this->post );
					break;
				case 'term':
					$desc = self::shorten( term_description( $this->term ), 160 );
					break;
				case 'pt_archive':
					$object = get_post_type_object( $this->archive_post_type );
					$desc   = $object ? self::shorten( $object->description, 160 ) : '';
					break;
				case 'author':
					$author = get_queried_object();
					$desc   = $author instanceof WP_User ? self::shorten( $author->description, 160 ) : '';
					break;
			}
		}

		$this->memo['description'] = (string) apply_filters( 'minimalio_seo_description', $desc, $this );
		return $this->memo['description'];
	}

	/**
	 * Robots state.
	 *
	 * @return array{noindex: bool, nofollow: bool}
	 */
	public function robots() {
		if ( isset( $this->memo['robots'] ) ) {
			return $this->memo['robots'];
		}

		$noindex  = false;
		$nofollow = '1' === $this->meta( Minimalio_SEO_Post_Meta_Registry::NOFOLLOW );
		$setting  = $this->meta( Minimalio_SEO_Post_Meta_Registry::ROBOTS );

		switch ( $this->type ) {
			case 'front_page':
				// Never noindex the homepage, whatever is stored.
				$noindex = false;
				break;
			case 'blog':
			case 'singular':
				$noindex = '' === $setting ? Minimalio_SEO_Options::post_type_noindex( $this->post->post_type ) : 'noindex' === $setting;
				break;
			case 'term':
				$noindex = '' === $setting ? Minimalio_SEO_Options::taxonomy_noindex( $this->term->taxonomy ) : 'noindex' === $setting;
				break;
			case 'author':
				$noindex = (bool) Minimalio_SEO_Options::get( 'noindex_author' );
				break;
			case 'date':
				$noindex = (bool) Minimalio_SEO_Options::get( 'noindex_date' );
				break;
			case 'search':
			case '404':
				$noindex = true;
				break;
		}

		$this->memo['robots'] = apply_filters(
			'minimalio_seo_robots',
			[
				'noindex'  => $noindex,
				'nofollow' => $nofollow,
			],
			$this
		);
		return $this->memo['robots'];
	}

	/**
	 * The URL of the current page, regardless of indexing (used for og:url).
	 *
	 * @return string
	 */
	public function url() {
		if ( isset( $this->memo['url'] ) ) {
			return $this->memo['url'];
		}

		$custom = $this->meta( Minimalio_SEO_Post_Meta_Registry::CANONICAL );
		$paged  = $this->paged();
		$url    = '';

		if ( '' !== $custom ) {
			$url = $custom;
		} else {
			switch ( $this->type ) {
				case 'home':
					$url = self::paged_url( home_url( '/' ), $paged );
					break;
				case 'front_page':
				case 'singular':
					$url = wp_get_canonical_url( $this->post );
					if ( ! $url ) {
						$url = get_permalink( $this->post );
					}
					break;
				case 'blog':
					$url = self::paged_url( get_permalink( $this->post ), $paged );
					break;
				case 'term':
					$link = get_term_link( $this->term );
					$url  = is_wp_error( $link ) ? '' : self::paged_url( $link, $paged );
					break;
				case 'pt_archive':
					$link = get_post_type_archive_link( $this->archive_post_type );
					$url  = $link ? self::paged_url( $link, $paged ) : '';
					break;
				case 'author':
					$url = self::paged_url( get_author_posts_url( (int) get_queried_object_id() ), $paged );
					break;
				case 'date':
					if ( is_day() ) {
						$link = get_day_link( get_query_var( 'year' ), get_query_var( 'monthnum' ), get_query_var( 'day' ) );
					} elseif ( is_month() ) {
						$link = get_month_link( get_query_var( 'year' ), get_query_var( 'monthnum' ) );
					} else {
						$link = get_year_link( get_query_var( 'year' ) );
					}
					$url = self::paged_url( $link, $paged );
					break;
			}
		}

		$this->memo['url'] = (string) $url;
		return $this->memo['url'];
	}

	/**
	 * Canonical URL. Empty for noindexed pages, search and 404.
	 *
	 * @return string
	 */
	public function canonical() {
		$robots    = $this->robots();
		$canonical = $robots['noindex'] ? '' : $this->url();
		return (string) apply_filters( 'minimalio_seo_canonical', $canonical, $this );
	}

	/**
	 * Add pagination to a base URL.
	 *
	 * @param string $base  Base URL.
	 * @param int    $paged Page number.
	 * @return string
	 */
	public static function paged_url( $base, $paged ) {
		if ( $paged < 2 || ! $base ) {
			return $base;
		}
		global $wp_rewrite;
		if ( $wp_rewrite && $wp_rewrite->using_permalinks() ) {
			return trailingslashit( $base ) . user_trailingslashit( $wp_rewrite->pagination_base . '/' . $paged, 'paged' );
		}
		return add_query_arg( 'paged', $paged, $base );
	}

	/* ------------------------------------------------------------------
	 * Social
	 * ------------------------------------------------------------------ */

	/**
	 * Social (Open Graph) title.
	 *
	 * @return string
	 */
	public function social_title() {
		$custom = $this->meta( Minimalio_SEO_Post_Meta_Registry::OG_TITLE );
		if ( '' !== $custom ) {
			return $this->replace( $custom );
		}
		// og:site_name already carries the site name, so use the plain page name.
		if ( in_array( $this->type, [ 'singular', 'blog', 'term', 'pt_archive', 'author' ], true ) ) {
			$seo_title = $this->meta( Minimalio_SEO_Post_Meta_Registry::TITLE );
			return '' !== $seo_title ? $this->replace( $seo_title ) : self::clean( $this->title_var() );
		}
		return $this->title();
	}

	/**
	 * Social (Open Graph) description.
	 *
	 * @return string
	 */
	public function social_description() {
		$custom = $this->meta( Minimalio_SEO_Post_Meta_Registry::OG_DESCRIPTION );
		return '' !== $custom ? $this->replace( $custom ) : $this->description();
	}

	/**
	 * Social image: custom > featured image > default image.
	 *
	 * @return array|null { url, width, height, alt, type }
	 */
	public function image() {
		if ( array_key_exists( 'image', $this->memo ) ) {
			return $this->memo['image'];
		}

		$id = 0;
		if ( $this->post ) {
			$id = (int) get_post_meta( $this->post->ID, Minimalio_SEO_Post_Meta_Registry::OG_IMAGE, true );
			if ( ! $id && has_post_thumbnail( $this->post ) ) {
				$id = (int) get_post_thumbnail_id( $this->post );
			}
		}
		if ( ! $id ) {
			$id = (int) Minimalio_SEO_Options::get( 'default_image' );
		}

		$this->memo['image'] = apply_filters( 'minimalio_seo_image', $id ? self::image_data( $id ) : null, $this );
		return $this->memo['image'];
	}

	/**
	 * Attachment data for meta tags and schema.
	 *
	 * @param int $id Attachment ID.
	 * @return array|null
	 */
	public static function image_data( $id ) {
		$src = wp_get_attachment_image_src( $id, 'full' );
		if ( ! $src ) {
			return null;
		}
		return [
			'id'     => (int) $id,
			'url'    => $src[0],
			'width'  => (int) $src[1],
			'height' => (int) $src[2],
			'alt'    => self::clean( get_post_meta( $id, '_wp_attachment_image_alt', true ) ),
			'type'   => (string) get_post_mime_type( $id ),
		];
	}
}
