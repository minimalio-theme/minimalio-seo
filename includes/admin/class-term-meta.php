<?php
/**
 * SEO fields on category, tag and portfolio category screens.
 *
 * @package minimalio-seo
 */

defined( 'ABSPATH' ) || exit;

class Minimalio_SEO_Term_Meta {

	const NONCE = 'minimalio_seo_term_nonce';

	/**
	 * Hooks (taxonomies are registered on init, so attach after that).
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'hook_taxonomies' ] );
	}

	/**
	 * Attach fields to each public taxonomy.
	 */
	public function hook_taxonomies() {
		foreach ( array_keys( Minimalio_SEO_Options::taxonomies() ) as $taxonomy ) {
			add_action( "{$taxonomy}_edit_form_fields", [ $this, 'render' ], 20, 2 );
			add_action( "edited_{$taxonomy}", [ $this, 'save' ] );
		}
	}

	/**
	 * Fields on the "Edit term" screen.
	 *
	 * @param WP_Term $term     Term.
	 * @param string  $taxonomy Taxonomy.
	 */
	public function render( $term, $taxonomy ) {
		$get = function ( $key ) use ( $term ) {
			return (string) get_term_meta( $term->term_id, $key, true );
		};

		$robots        = $get( Minimalio_SEO_Post_Meta_Registry::ROBOTS );
		$default_label = Minimalio_SEO_Options::taxonomy_noindex( $taxonomy ) ? __( 'Default (noindex)', 'minimalio-seo' ) : __( 'Default (index)', 'minimalio-seo' );
		?>
		<tr class="form-field">
			<th scope="row" colspan="2"><h2 style="margin:1em 0 0;"><?php esc_html_e( 'SEO', 'minimalio-seo' ); ?></h2><?php wp_nonce_field( 'minimalio_seo_save_term', self::NONCE ); ?></th>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="minimalio-seo-title"><?php esc_html_e( 'SEO title', 'minimalio-seo' ); ?></label></th>
			<td>
				<input type="text" id="minimalio-seo-title" name="minimalio_seo[title]" value="<?php echo esc_attr( $get( Minimalio_SEO_Post_Meta_Registry::TITLE ) ); ?>" placeholder="<?php echo esc_attr( Minimalio_SEO_Options::taxonomy_title( $taxonomy ) ); ?>" />
				<p class="description"><?php esc_html_e( 'Leave empty to use the default. Variables: %title%, %sitename%, %sep%, %page%', 'minimalio-seo' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="minimalio-seo-description"><?php esc_html_e( 'Meta description', 'minimalio-seo' ); ?></label></th>
			<td>
				<textarea id="minimalio-seo-description" name="minimalio_seo[description]" rows="3"><?php echo esc_textarea( $get( Minimalio_SEO_Post_Meta_Registry::DESCRIPTION ) ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Leave empty to use the term description.', 'minimalio-seo' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="minimalio-seo-robots"><?php esc_html_e( 'Search engine indexing', 'minimalio-seo' ); ?></label></th>
			<td>
				<select id="minimalio-seo-robots" name="minimalio_seo[robots]">
					<option value="" <?php selected( $robots, '' ); ?>><?php echo esc_html( $default_label ); ?></option>
					<option value="index" <?php selected( $robots, 'index' ); ?>><?php esc_html_e( 'Index: show in search results', 'minimalio-seo' ); ?></option>
					<option value="noindex" <?php selected( $robots, 'noindex' ); ?>><?php esc_html_e( 'Noindex: hide from search results', 'minimalio-seo' ); ?></option>
				</select>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="minimalio-seo-canonical"><?php esc_html_e( 'Canonical URL', 'minimalio-seo' ); ?></label></th>
			<td>
				<input type="url" id="minimalio-seo-canonical" name="minimalio_seo[canonical]" value="<?php echo esc_attr( $get( Minimalio_SEO_Post_Meta_Registry::CANONICAL ) ); ?>" />
				<p class="description"><?php esc_html_e( 'Only needed when this archive duplicates another URL.', 'minimalio-seo' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save.
	 *
	 * @param int $term_id Term ID.
	 */
	public function save( $term_id ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE ] ), 'minimalio_seo_save_term' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}
		if ( ! isset( $_POST['minimalio_seo'] ) || ! is_array( $_POST['minimalio_seo'] ) ) {
			return;
		}

		$data = wp_unslash( $_POST['minimalio_seo'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Sanitized per field below.
		$get  = function ( $key ) use ( $data ) {
			return isset( $data[ $key ] ) ? $data[ $key ] : '';
		};

		$values = [
			Minimalio_SEO_Post_Meta_Registry::TITLE       => sanitize_text_field( $get( 'title' ) ),
			Minimalio_SEO_Post_Meta_Registry::DESCRIPTION => sanitize_textarea_field( $get( 'description' ) ),
			Minimalio_SEO_Post_Meta_Registry::ROBOTS      => Minimalio_SEO_Post_Meta_Registry::sanitize_robots( $get( 'robots' ) ),
			Minimalio_SEO_Post_Meta_Registry::CANONICAL   => esc_url_raw( trim( $get( 'canonical' ) ) ),
		];

		foreach ( $values as $key => $value ) {
			if ( '' === $value ) {
				delete_term_meta( $term_id, $key );
			} else {
				update_term_meta( $term_id, $key, $value );
			}
		}
	}
}
