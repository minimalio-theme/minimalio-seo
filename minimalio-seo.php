<?php
/**
 * Plugin Name:       Minimalio SEO
 * Plugin URI:        https://minimalio.org
 * Description:       Lightweight SEO for Minimalio Premium: titles, meta descriptions, robots, canonical URLs, Open Graph, structured data and XML sitemaps. Only what search engines and social networks actually use.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            mikulas-k
 * Author URI:        https://minimalio.org
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       minimalio-seo
 * Domain Path:       /languages
 * Update URI:        https://github.com/minimalio-theme/minimalio-seo
 *
 * @package minimalio-seo
 */

defined( 'ABSPATH' ) || exit;

define( 'MINIMALIO_SEO_VERSION', '1.1.0' );
define( 'MINIMALIO_SEO_FILE', __FILE__ );
define( 'MINIMALIO_SEO_DIR', plugin_dir_path( __FILE__ ) );
define( 'MINIMALIO_SEO_URL', plugin_dir_url( __FILE__ ) );

// Updates from GitHub releases. Loaded before the Premium check, so a site
// always receives fixes, even while Minimalio Premium is inactive.
require_once MINIMALIO_SEO_DIR . 'includes/class-updater.php';
new Minimalio_SEO_Updater();

/**
 * Is the Minimalio Premium (minimalio-portfolio) plugin active?
 *
 * The premium plugin defines MINIMALIO_PORTFOLIO_DATA_VERSION as soon as it loads.
 * Checked on plugins_loaded, so load order does not matter.
 *
 * @return bool
 */
function minimalio_seo_has_premium() {
	return defined( 'MINIMALIO_PORTFOLIO_DATA_VERSION' );
}

/**
 * Is the site open to search engines?
 *
 * blog_public is '1' for public sites, '0' for "Discourage search engines"
 * (and WordPress.com "Coming soon") and '-1' for WordPress.com private sites.
 * '-1' is truthy, so a plain boolean check would treat private sites as public.
 *
 * @return bool
 */
function minimalio_seo_is_public() {
	return '1' === (string) get_option( 'blog_public' );
}

/**
 * Returns the name of another active SEO plugin, if any.
 * Running two SEO plugins produces duplicate titles, canonicals and schema.
 *
 * @return string Empty string when no conflict.
 */
function minimalio_seo_conflicting_plugin() {
	if ( defined( 'WPSEO_VERSION' ) ) {
		return 'Yoast SEO';
	}
	if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
		return 'Rank Math';
	}
	if ( defined( 'AIOSEO_VERSION' ) ) {
		return 'All in One SEO';
	}
	if ( defined( 'SEOPRESS_VERSION' ) ) {
		return 'SEOPress';
	}
	if ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) ) {
		return 'The SEO Framework';
	}
	return '';
}

add_action( 'plugins_loaded', 'minimalio_seo_bootstrap' );

/**
 * Load the plugin only for Minimalio Premium users.
 */
function minimalio_seo_bootstrap() {
	load_plugin_textdomain( 'minimalio-seo', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	if ( ! minimalio_seo_has_premium() ) {
		add_action( 'admin_notices', 'minimalio_seo_notice_premium_required' );
		return;
	}

	require_once MINIMALIO_SEO_DIR . 'includes/class-options.php';
	require_once MINIMALIO_SEO_DIR . 'includes/class-context.php';
	require_once MINIMALIO_SEO_DIR . 'includes/class-frontend.php';
	require_once MINIMALIO_SEO_DIR . 'includes/class-schema.php';
	require_once MINIMALIO_SEO_DIR . 'includes/class-sitemap.php';
	require_once MINIMALIO_SEO_DIR . 'includes/class-sitemap-images.php';

	$conflict = minimalio_seo_conflicting_plugin();

	if ( is_admin() ) {
		require_once MINIMALIO_SEO_DIR . 'includes/admin/class-settings-page.php';
		require_once MINIMALIO_SEO_DIR . 'includes/admin/class-post-meta.php';
		require_once MINIMALIO_SEO_DIR . 'includes/admin/class-term-meta.php';
		require_once MINIMALIO_SEO_DIR . 'includes/admin/class-block-editor.php';

		new Minimalio_SEO_Settings_Page();
		new Minimalio_SEO_Post_Meta();
		new Minimalio_SEO_Block_Editor();
		new Minimalio_SEO_Term_Meta();

		if ( $conflict ) {
			add_action(
				'admin_notices',
				function () use ( $conflict ) {
					echo '<div class="notice notice-warning"><p>';
					printf(
						/* translators: %s: name of the other SEO plugin */
						esc_html__( 'Minimalio SEO: %s is also active. To avoid duplicate meta tags, Minimalio SEO does not output anything on the front end until the other SEO plugin is deactivated.', 'minimalio-seo' ),
						esc_html( $conflict )
					);
					echo '</p></div>';
				}
			);
		}
	}

	Minimalio_SEO_Post_Meta_Registry::register();

	// Sitemaps are core WordPress sitemaps; we only tune them.
	new Minimalio_SEO_Sitemap();
	new Minimalio_SEO_Sitemap_Images();

	if ( ! $conflict ) {
		new Minimalio_SEO_Frontend();

		// Titles, descriptions and noindex entered earlier in Jetpack SEO Tools
		// (still in the database even after Jetpack is removed).
		require_once MINIMALIO_SEO_DIR . 'includes/class-jetpack-data.php';
		new Minimalio_SEO_Jetpack_Data();

		// Jetpack: switch off its duplicate SEO output so ours takes priority.
		if ( defined( 'JETPACK__VERSION' ) ) {
			require_once MINIMALIO_SEO_DIR . 'includes/class-jetpack.php';
			new Minimalio_SEO_Jetpack();
		}
	}
}

/**
 * Admin notice shown when Minimalio Premium is missing.
 */
function minimalio_seo_notice_premium_required() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>';
	esc_html_e( 'Minimalio SEO requires the Minimalio Premium plugin (minimalio-portfolio) to be installed and active.', 'minimalio-seo' );
	echo ' <a href="https://minimalio.org" target="_blank" rel="noopener">' . esc_html__( 'Get Minimalio Premium', 'minimalio-seo' ) . '</a>';
	echo '</p></div>';
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'minimalio_seo_action_links' );

/**
 * "Settings" link on the Plugins screen.
 *
 * @param array $links Existing links.
 * @return array
 */
function minimalio_seo_action_links( $links ) {
	if ( minimalio_seo_has_premium() ) {
		$url = admin_url( 'admin.php?page=minimalio-seo' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'minimalio-seo' ) . '</a>' );
	}
	return $links;
}
