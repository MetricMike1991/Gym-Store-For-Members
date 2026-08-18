<?php
/**
 * Cleanup on plugin deletion.
 *
 * @package GymStoreForMembers
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$products = $wpdb->prefix . 'gss_products';
$wishlist = $wpdb->prefix . 'gss_wishlist';

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wishlist}" );
$wpdb->query( "DROP TABLE IF EXISTS {$products}" );
// phpcs:enable

delete_option( 'gsfm_settings' );
