<?php
/**
 * CRUD for scraped products.
 *
 * @package GymStoreForMembers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSFM_Products {

	/**
	 * Insert or update a product matched on supplier_ref.
	 *
	 * @param array $data Product fields.
	 * @return int Product ID.
	 */
	public static function upsert( array $data ) {
		global $wpdb;
		$table = GSFM_Database::products_table();

		$supplier_ref = isset( $data['supplier_ref'] ) ? sanitize_text_field( $data['supplier_ref'] ) : '';

		$existing = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, display_price FROM {$table} WHERE supplier_ref = %s", $supplier_ref )
		);

		$row = array(
			'supplier_ref'   => $supplier_ref,
			'title'          => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '',
			'image_url'      => isset( $data['image_url'] ) ? esc_url_raw( $data['image_url'] ) : '',
			'supplier_price' => isset( $data['supplier_price'] ) ? (float) $data['supplier_price'] : 0,
			'in_stock'       => ! empty( $data['in_stock'] ) ? 1 : 0,
			'last_scraped'   => current_time( 'mysql' ),
		);

		$formats = array( '%s', '%s', '%s', '%f', '%d', '%s' );

		if ( $existing ) {
			// Preserve admin-set display price; default it to supplier price if unset.
			if ( (float) $existing->display_price <= 0 ) {
				$row['display_price'] = (float) $row['supplier_price'];
				$formats[]            = '%f';
			}
			$wpdb->update( $table, $row, array( 'id' => (int) $existing->id ), $formats, array( '%d' ) );
			return (int) $existing->id;
		}

		$row['display_price'] = (float) $row['supplier_price'];
		$formats[]            = '%f';
		$wpdb->insert( $table, $row, $formats );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Get products for the front-end shop (in stock + visible).
	 *
	 * @return array
	 */
	public static function get_shop_products() {
		global $wpdb;
		$table = GSFM_Database::products_table();
		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE in_stock = 1 AND visible = 1 ORDER BY title ASC"
		);
	}

	/**
	 * Get every product for the admin table.
	 *
	 * @return array
	 */
	public static function get_all() {
		global $wpdb;
		$table = GSFM_Database::products_table();
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY title ASC" );
	}

	/**
	 * Get a single product by ID.
	 *
	 * @param int $id Product ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = GSFM_Database::products_table();
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id )
		);
	}

	/**
	 * Get a single product by supplier reference.
	 *
	 * @param string $ref Supplier reference.
	 * @return object|null
	 */
	public static function get_by_ref( $ref ) {
		global $wpdb;
		$table = GSFM_Database::products_table();
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE supplier_ref = %s", $ref )
		);
	}

	/**
	 * Update the admin display price.
	 *
	 * @param int   $id    Product ID.
	 * @param float $price Display price.
	 */
	public static function set_display_price( $id, $price ) {
		global $wpdb;
		$wpdb->update(
			GSFM_Database::products_table(),
			array( 'display_price' => (float) $price ),
			array( 'id' => (int) $id ),
			array( '%f' ),
			array( '%d' )
		);
	}

	/**
	 * Toggle product visibility in the shop.
	 *
	 * @param int  $id      Product ID.
	 * @param bool $visible Visible flag.
	 */
	public static function set_visible( $id, $visible ) {
		global $wpdb;
		$wpdb->update(
			GSFM_Database::products_table(),
			array( 'visible' => $visible ? 1 : 0 ),
			array( 'id' => (int) $id ),
			array( '%d' ),
			array( '%d' )
		);
	}
}
