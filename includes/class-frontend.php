<?php
/**
 * Front-end output: <title>, meta description, robots, canonical, Open Graph,
 * Twitter card, verification tags and attachment redirects.
 *
 * @package minimalio-seo
 */

defined( 'ABSPATH' ) || exit;

class Minimalio_SEO_Frontend {

	/**
	 * Hook everything up.
	 */
	public function __construct() {
		add_filter( 'pre_get_document_title', [ $this, 'document_title' ], 15 );
		add_filter( 'wp_robots', [ $this, 'robots' ], 20 );
		add_action( 'wp_head', [ $this, 'head' ], 1 );
		add_action( 'template_redirect', [ $this, 'redirect_attachment' ], 1 );

		// We print our own canonical (it also covers archives and pagination).
		remove_action( 'wp_head', 'rel_canonical' );
	}

	/**
	 * Replace the document title.
	 *
	 * Returning a non-empty string from pre_get_document_title bypasses
	 * WordPress' own escaping, so we escape here.
	 *
	 * @param string $title Title from earlier filters.
	 * @return string
	 */
	public function document_title( $title ) {
		$ours = Minimalio_SEO_Context::current()->title();
		return '' !== $ours ? esc_html( $ours ) : $title;
	}

	/**
	 * Robots meta via the core wp_robots API (WP 5.7+), so we never print a second robots tag.
	 *
	 * @param array $robots Directives.
	 * @return array
	 */
	public function robots( $robots ) {
		// Respect "Discourage search engines" in Settings > Reading.
		if ( ! minimalio_seo_is_public() ) {
			return $robots;
		}

		$state = Minimalio_SEO_Context::current()->robots();

		if ( $state['noindex'] ) {
			unset( $robots['max-image-preview'], $robots['max-snippet'], $robots['max-video-preview'] );
			$robots['noindex'] = true;
			if ( ! $state['nofollow'] ) {
				$robots['follow'] = true;
			}
		} else {
			// Allow large image previews and full snippets in Google Search, Discover and Images.
			$robots['max-snippet']       = '-1';
			$robots['max-image-preview'] = 'large';
			$robots['max-video-preview'] = '-1';
		}

		if ( $state['nofollow'] ) {
			unset( $robots['follow'] );
			$robots['nofollow'] = true;
		}

		return $robots;
	}

	/**
	 * Print all head tags.
	 */
	public function head() {
		$ctx  = Minimalio_SEO_Context::current();
		$tags = [];

		// Meta description.
		$description = $ctx->description();
		if ( '' !== $description ) {
			$tags[] = $this->meta( 'name', 'description', $description );
		}

		// Canonical.
		$canonical = $ctx->canonical();
		if ( '' !== $canonical ) {
			$tags[] = '<link rel="canonical" href="' . esc_url( $canonical ) . '" />';
		}

		// Open Graph (Facebook, LinkedIn, Pinterest, WhatsApp, Slack, iMessage...).
		if ( ! in_array( $ctx->type, [ '404', 'search' ], true ) ) {
			$tags = array_merge( $tags, $this->open_graph( $ctx ) );
		}

		// Verification.
		$verify = [
			'google-site-verification'     => 'verify_google',
			'msvalidate.01'                => 'verify_bing',
			'p:domain_verify'              => 'verify_pinterest',
			'facebook-domain-verification' => 'verify_facebook',
		];
		foreach ( $verify as $name => $option ) {
			$code = (string) Minimalio_SEO_Options::get( $option );
			if ( '' !== $code ) {
				$tags[] = $this->meta( 'name', $name, $code );
			}
		}

		// Structured data.
		$schema = Minimalio_SEO_Schema::script( $ctx );
		if ( $schema ) {
			$tags[] = $schema;
		}

		$tags = apply_filters( 'minimalio_seo_head_tags', $tags, $ctx );
		if ( ! $tags ) {
			return;
		}

		echo "\n<!-- Minimalio SEO -->\n" . implode( "\n", $tags ) . "\n<!-- /Minimalio SEO -->\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- Each tag is escaped when built.
	}

	/**
	 * Build Open Graph and Twitter tags.
	 *
	 * X/Twitter falls back to og:title, og:description and og:image,
	 * so only twitter:card and twitter:site are needed.
	 *
	 * @param Minimalio_SEO_Context $ctx Context.
	 * @return string[]
	 */
	private function open_graph( $ctx ) {
		$tags       = [];
		$is_article = 'singular' === $ctx->type && 'post' === $ctx->post->post_type;

		$tags[] = $this->meta( 'property', 'og:locale', get_locale() );
		$tags[] = $this->meta( 'property', 'og:type', $is_article ? 'article' : 'website' );
		$tags[] = $this->meta( 'property', 'og:title', $ctx->social_title() );

		$description = $ctx->social_description();
		if ( '' !== $description ) {
			$tags[] = $this->meta( 'property', 'og:description', $description );
		}

		$url = $ctx->url();
		if ( '' !== $url ) {
			$tags[] = '<meta property="og:url" content="' . esc_url( $url ) . '" />';
		}

		$tags[] = $this->meta( 'property', 'og:site_name', Minimalio_SEO_Context::clean( get_bloginfo( 'name' ) ) );

		if ( $is_article ) {
			$tags[] = $this->meta( 'property', 'article:published_time', get_post_time( 'c', true, $ctx->post ) );
			$tags[] = $this->meta( 'property', 'article:modified_time', get_post_modified_time( 'c', true, $ctx->post ) );
		}

		$image = $ctx->image();
		if ( $image ) {
			$tags[] = '<meta property="og:image" content="' . esc_url( $image['url'] ) . '" />';
			if ( 0 === strpos( $image['url'], 'https://' ) ) {
				$tags[] = '<meta property="og:image:secure_url" content="' . esc_url( $image['url'] ) . '" />';
			}
			if ( $image['width'] && $image['height'] ) {
				$tags[] = $this->meta( 'property', 'og:image:width', (string) $image['width'] );
				$tags[] = $this->meta( 'property', 'og:image:height', (string) $image['height'] );
			}
			if ( '' !== $image['alt'] ) {
				$tags[] = $this->meta( 'property', 'og:image:alt', $image['alt'] );
			}
			if ( '' !== $image['type'] ) {
				$tags[] = $this->meta( 'property', 'og:image:type', $image['type'] );
			}
		}

		$tags[] = $this->meta( 'name', 'twitter:card', $image ? 'summary_large_image' : 'summary' );

		$handle = ltrim( (string) Minimalio_SEO_Options::get( 'twitter_site' ), '@' );
		if ( '' !== $handle ) {
			$tags[] = $this->meta( 'name', 'twitter:site', '@' . $handle );
		}

		return $tags;
	}

	/**
	 * Build a single escaped meta tag.
	 *
	 * @param string $attr    name|property.
	 * @param string $key     Attribute value.
	 * @param string $content Content.
	 * @return string
	 */
	private function meta( $attr, $key, $content ) {
		return sprintf( '<meta %s="%s" content="%s" />', $attr, esc_attr( $key ), esc_attr( $content ) );
	}

	/**
	 * Attachment pages are thin content: send visitors (and Google) to the
	 * parent post, or to the file itself when there is no parent.
	 */
	public function redirect_attachment() {
		if ( ! is_attachment() || ! Minimalio_SEO_Options::get( 'redirect_attachment' ) ) {
			return;
		}

		$attachment = get_queried_object();
		if ( ! $attachment instanceof WP_Post ) {
			return;
		}

		$target = '';
		if ( $attachment->post_parent && 'publish' === get_post_status( $attachment->post_parent ) ) {
			$target = get_permalink( $attachment->post_parent );
		}
		if ( ! $target ) {
			$target = wp_get_attachment_url( $attachment->ID );
		}

		if ( $target ) {
			// Targets come from WordPress itself; plain wp_redirect allows CDN-hosted files.
			wp_redirect( $target, 301, 'Minimalio SEO' ); // phpcs:ignore WordPress.Security.SafeRedirect
			exit;
		}
	}
}
