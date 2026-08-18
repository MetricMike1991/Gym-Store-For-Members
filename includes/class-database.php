<?php
/**
 * Database installer and table name helpers.
 *
 * @package GymStoreForMembers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSFM_Database {

	/**
	 * Fully-qualified products table name.
	 *
	 * @return string
	 */
	public static function products_table() {
		global $wpdb;
		return $wpdb->prefix . GSFM_TABLE_PRODUCTS;
	}

	/**
	 * Fully-qualified wishlist table name.
	 *
	 * @return string
	 */
	public static function wishlist_table() {
		global $wpdb;
		return $wpdb->prefix . GSFM_TABLE_WISHLIST;
	}

	/**
	 * Create custom tables on activation.
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$products        = self::products_table();
		$wishlist        = self::wishlist_table();

		$sql_products = "CREATE TABLE {$products} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			supplier_ref VARCHAR(191) NOT NULL DEFAULT '',
			title VARCHAR(255) NOT NULL DEFAULT '',
			image_url TEXT NULL,
			supplier_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			display_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			in_stock TINYINT(1) NOT NULL DEFAULT 0,
			visible TINYINT(1) NOT NULL DEFAULT 1,
			last_scraped DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY supplier_ref (supplier_ref)
		) {$charset_collate};";

		$sql_wishlist = "CREATE TABLE {$wishlist} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			product_id BIGINT UNSIGNED NOT NULL,
			requested_at DATETIME NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			notes TEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_product (user_id, product_id),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql_products );
		dbDelta( $sql_wishlist );

		add_option( 'gsfm_db_version', GSFM_VERSION );
	}
}
