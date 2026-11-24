<?php
/**
 * Jetpack compatibility: Minimalio SEO owns the SEO output.
 *
 * Jetpack can print its own Open Graph tags, Twitter cards, SEO titles and meta
 * descriptions, verification tags and an XML sitemap. With both plugins active
 * that would mean duplicate tags, so Jetpack's copies are switched off through
 * Jetpack's own filters. Jetpack's other features (stats, security, CDN, …)
 * are untouched.
 *
 * Only loaded while Minimalio SEO's front-end output is active.
 *
 * @package minimalio-seo
 */

defined( 'ABSPATH' ) || exit;

class Minimalio_SEO_Jetpack {

	/**
	 * Hooks. Registered on plugins_loaded (priority 10), before Jetpack reads
	 * any of these filters (plugins_loaded 90/999, after_setup_theme, wp_head).
	 */
	public function __construct() {
		// Open Graph: Jetpack adds __return_true at 0 and __return_false at 99.
		add_filter( 'jetpack_enable_open_graph', '__return_false', 100 );

		// Twitter / X cards.
		add_filter( 'jetpack_disable_twitter_cards', '__return_true', 100 );

		// SEO Tools (custom titles, meta descriptions, editor SEO panel). Naming
		// ourselves as the conflicting plugin also makes Jetpack's SEO dashboard
		// say that Minimalio SEO manages SEO.
		add_filter( 'jetpack_seo_conflicting_plugin', [ $this, 'conflicting_plugin' ], 100 );
		add_filter( 'jetpack_disable_seo_tools', '__return_true', 100 );
		add_filter( 'jetpack_seo_meta_tags_enabled', '__return_false', 100 );

		// Sitemaps.
		add_filter( 'jetpack_active_modules', [ $this, 'active_modules' ], 100 );
		add_filter( 'wp_sitemaps_enabled', [ $this, 'core_sitemaps_enabled' ], 100 );

		// Site verification: drop Jetpack's tag for services we already output.
		add_filter( 'jetpack_site_verification_output', [ $this, 'verification_output' ], 100 );
	}

	/**
	 * Name shown by Jetpack as the plugin handling SEO.
	 *
	 * @param string|null $plugin Plugin name set by Jetpack or another plugin.
	 * @return string
	 */
	public function conflicting_plugin( $plugin ) {
		return is_string( $plugin ) && '' !== trim( $plugin ) ? $plugin : 'Minimalio SEO';
	}

	/**
	 * Is our sitemap switched on?
	 *
	 * @return bool
	 */
	private function our_sitemap() {
		return (bool) Minimalio_SEO_Options::get( 'sitemap_enabled' );
	}

	/**
	 * While our sitemap is on, Jetpack's sitemap module does not run
	 * (it would replace /wp-sitemap.xml with its own /sitemap.xml).
	 *
	 * @param array $modules Active module slugs.
	 * @return array
	 */
	public function active_modules( $modules ) {
		if ( ! is_array( $modules ) || ! $this->our_sitemap() ) {
			return $modules;
		}
		return array_values( array_diff( $modules, [ 'sitemaps' ] ) );
	}

	/**
	 * Jetpack turns WordPress core sitemaps off (when its sitemap module is on,
	 * or after it was deliberately turned off). Our sitemap is the core one, so
	 * while our setting is on, core sitemaps follow WordPress's own default:
	 * on for public sites (never private ones). Runs after Minimalio_SEO_Sitemap::enabled().
	 *
	 * @param bool $enabled Current value.
	 * @return bool
	 */
	public function core_sitemaps_enabled( $enabled ) {
		if ( ! $this->our_sitemap() ) {
			return $enabled;
		}
		return minimalio_seo_is_public();
	}

	/**
	 * Remove one Jetpack verification tag if Minimalio SEO prints the same service.
	 *
	 * @param string $tag <meta name="…" content="…"> tag.
	 * @return string
	 */
	public function verification_output( $tag ) {
		$ours = [
			'google-site-verification'     => 'verify_google',
			'msvalidate.01'                => 'verify_bing',
			'p:domain_verify'              => 'verify_pinterest',
			'facebook-domain-verification' => 'verify_facebook',
		];
		foreach ( $ours as $meta_name => $option ) {
			if ( false !== strpos( (string) $tag, 'name="' . $meta_name . '"' ) && '' !== (string) Minimalio_SEO_Options::get( $option ) ) {
				return '';
			}
		}
		return $tag;
	}
}
