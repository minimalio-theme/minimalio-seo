<?php
/**
 * Remove plugin data on uninstall (not on deactivation).
 *
 * @package minimalio-seo
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

delete_option( 'minimalio_seo_options' );

// Post and term meta all share the _minimalio_seo_ prefix.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_minimalio_seo_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_minimalio_seo_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
delete_site_transient( 'minimalio_seo_update' );
