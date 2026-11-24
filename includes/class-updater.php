<?php
/**
 * Updates from GitHub releases.
 *
 * The plugin header's "Update URI: https://github.com/minimalio-theme/minimalio-seo"
 * tells WordPress (5.8+) not to look for this plugin on wordpress.org and to ask
 * the update_plugins_github.com filter instead. Updates then show up on
 * Dashboard > Updates and the Plugins screen like any other plugin, including
 * automatic updates.
 *
 * Each GitHub release has two files, built by .github/workflows/release.yml:
 * - minimalio-seo.zip (the plugin, in a minimalio-seo/ folder),
 * - update.json (version, download URL, requirements, changelog).
 *
 * update.json is read from the "latest release" download URL, which is not
 * subject to GitHub's API rate limit (60 requests/hour per IP, quickly hit on
 * shared hosting such as WordPress.com). The answer is cached for 12 hours.
 *
 * @package minimalio-seo
 */

defined( 'ABSPATH' ) || exit;

class Minimalio_SEO_Updater {

	const REPO      = 'minimalio-theme/minimalio-seo';
	const MANIFEST  = 'https://github.com/' . self::REPO . '/releases/latest/download/update.json';
	const TRANSIENT = 'minimalio_seo_update';
	const SLUG      = 'minimalio-seo';

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_filter( 'update_plugins_github.com', [ $this, 'update' ], 10, 3 );
		add_filter( 'plugins_api', [ $this, 'details' ], 20, 3 );
		add_action( 'upgrader_process_complete', [ $this, 'flush' ], 10, 0 );
		add_action( 'load-update-core.php', [ $this, 'force_check' ] );
	}

	/**
	 * Tell WordPress about a newer version.
	 *
	 * @param array|false $update      Update data from other filters.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin basename.
	 * @return array|false
	 */
	public function update( $update, $plugin_data, $plugin_file ) {
		if ( plugin_basename( MINIMALIO_SEO_FILE ) !== $plugin_file ) {
			return $update;
		}

		$release = $this->release();
		if ( ! $release ) {
			return $update;
		}

		// WordPress only offers the update when this version is newer.
		return [
			'slug'         => self::SLUG,
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'tested'       => $release['tested'],
			'requires'     => $release['requires'],
			'requires_php' => $release['requires_php'],
		];
	}

	/**
	 * "View details" popup on the Plugins and Updates screens.
	 *
	 * @param false|object|array $result Result from other filters.
	 * @param string             $action plugins_api action.
	 * @param object             $args   Request arguments.
	 * @return false|object|array
	 */
	public function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! isset( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$release = $this->release();
		if ( ! $release ) {
			return $result;
		}

		return (object) [
			'name'          => 'Minimalio SEO',
			'slug'          => self::SLUG,
			'version'       => $release['version'],
			'author'        => '<a href="https://minimalio.org">Minimalio</a>',
			'homepage'      => 'https://github.com/' . self::REPO,
			'download_link' => $release['package'],
			'requires'      => $release['requires'],
			'requires_php'  => $release['requires_php'],
			'tested'        => $release['tested'],
			'last_updated'  => $release['last_updated'],
			'sections'      => [
				'description' => esc_html__( 'Lightweight SEO for Minimalio Premium: titles, meta descriptions, robots, canonical URLs, Open Graph, structured data and XML sitemaps.', 'minimalio-seo' ),
				'changelog'   => $release['changelog'],
			],
		];
	}

	/**
	 * Latest release, cached.
	 *
	 * @return array|null
	 */
	private function release() {
		$cached = get_site_transient( self::TRANSIENT );
		if ( is_array( $cached ) ) {
			return empty( $cached['version'] ) ? null : $cached;
		}

		$response = wp_remote_get(
			self::MANIFEST,
			[
				'timeout'    => 10,
				'user-agent' => 'WordPress; Minimalio SEO/' . MINIMALIO_SEO_VERSION,
			]
		);

		$data = null;
		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$json = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $json ) && ! empty( $json['version'] ) && ! empty( $json['package'] ) ) {
				$data = [
					'version'      => sanitize_text_field( $json['version'] ),
					'package'      => esc_url_raw( $json['package'] ),
					'url'          => esc_url_raw( isset( $json['url'] ) ? $json['url'] : 'https://github.com/' . self::REPO . '/releases' ),
					'requires'     => sanitize_text_field( isset( $json['requires'] ) ? $json['requires'] : '' ),
					'requires_php' => sanitize_text_field( isset( $json['requires_php'] ) ? $json['requires_php'] : '' ),
					'tested'       => sanitize_text_field( isset( $json['tested'] ) ? $json['tested'] : '' ),
					'last_updated' => sanitize_text_field( isset( $json['last_updated'] ) ? $json['last_updated'] : '' ),
					'changelog'    => wp_kses_post( isset( $json['changelog'] ) ? $json['changelog'] : '' ),
				];
			}
		}

		// Failed checks are cached for an hour so a GitHub outage is not retried on every page load.
		set_site_transient( self::TRANSIENT, $data ? $data : [], $data ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
		return $data;
	}

	/**
	 * Forget the cached release (after updating, or on "Check again").
	 */
	public function flush() {
		delete_site_transient( self::TRANSIENT );
	}

	/**
	 * Dashboard > Updates > "Check again" also re-checks GitHub.
	 */
	public function force_check() {
		if ( isset( $_GET['force-check'] ) && current_user_can( 'update_plugins' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$this->flush();
		}
	}
}
