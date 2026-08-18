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
			$wpdb->prepare( "SELECT id, display_price, image_url FROM {$table} WHERE supplier_ref = %s", $supplier_ref )
		);

		// Sideload image to local media library if it is still an external URL.
		$remote_image = isset( $data['image_url'] ) ? esc_url_raw( $data['image_url'] ) : '';
		$local_image  = $existing ? $existing->image_url : '';
		if ( '' !== $remote_image && ! self::is_local_url( $remote_image ) ) {
			// Only re-download if we don't already have a local copy stored.
			if ( '' === $local_image || ! self::is_local_url( $local_image ) ) {
				$title       = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';
				$sideloaded  = self::sideload_image( $remote_image, $title );
				$local_image = '' !== $sideloaded ? $sideloaded : $remote_image;
			}
		} elseif ( '' !== $remote_image ) {
			$local_image = $remote_image;
		}

		$row = array(
			'supplier_ref'   => $supplier_ref,
			'title'          => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '',
			'image_url'      => $local_image,
			'supplier_price' => isset( $data['supplier_price'] ) ? (float) $data['supplier_price'] : 0,
			'in_stock'       => ! empty( $data['in_stock'] ) ? 1 : 0,
			'last_scraped'   => current_time( 'mysql' ),
		);

		$formats = array( '%s', '%s', '%s', '%f', '%d', '%s' );

		if ( $existing ) {
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
	 * Download a remote image into the WP media library and return its local URL.
	 *
	 * @param string $url   Remote image URL.
	 * @param string $title Product title (used as attachment title).
	 * @return string Local URL on success, empty string on failure.
	 */
	public static function sideload_image( $url, $title ) {
		if ( '' === $url ) {
			return '';
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return '';
		}

		// Derive a filename from the URL, preserving extension.
		$filename = basename( wp_parse_url( $url, PHP_URL_PATH ) );
		if ( '' === $filename ) {
			$filename = 'product-image.jpg';
		}

		$file = array(
			'name'     => $filename,
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file, 0, sanitize_text_field( $title ) );

		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( is_wp_error( $attachment_id ) ) {
			return '';
		}

		return wp_get_attachment_url( $attachment_id );
	}

	/**
	 * Check whether a URL belongs to this WordPress installation.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private static function is_local_url( $url ) {
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return $home && $host && $host === $home;
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
