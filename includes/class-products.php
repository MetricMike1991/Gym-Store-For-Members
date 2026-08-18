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
			'category_slug'  => isset( $data['category_slug'] ) ? sanitize_key( $data['category_slug'] ) : '',
			'last_scraped'   => current_time( 'mysql' ),
		);

		$formats = array( '%s', '%s', '%s', '%f', '%d', '%s', '%s' );

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
	 * @param string $category_slug Optional category filter.
	 * @return array
	 */
	public static function get_shop_products( $category_slug = '' ) {
		global $wpdb;
		$table = GSFM_Database::products_table();
		if ( '' !== $category_slug ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE in_stock = 1 AND visible = 1 AND category_slug = %s ORDER BY title ASC",
					$category_slug
				)
			);
		}
		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE in_stock = 1 AND visible = 1 ORDER BY title ASC"
		);
	}

	/**
	 * Return distinct categories with a cover image and product count.
	 *
	 * @return array  Each entry: {category_slug, category_name, cover_image, count}
	 */
	public static function get_categories() {
		global $wpdb;
		$table = GSFM_Database::products_table();

		$rows = $wpdb->get_results(
			"SELECT category_slug, COUNT(*) as count,
			        MAX(CASE WHEN image_url != '' THEN image_url ELSE NULL END) as cover_image
			 FROM {$table}
			 WHERE in_stock = 1 AND visible = 1 AND category_slug != ''
			 GROUP BY category_slug
			 ORDER BY category_slug ASC"
		);

		// Merge in human-readable names and custom images from stored settings.
		$names   = self::category_name_map();
		$customs = get_option( 'gsfm_cat_images', array() );
		$result  = array();
		foreach ( $rows as $row ) {
			$row->category_name = isset( $names[ $row->category_slug ] ) ? $names[ $row->category_slug ] : ucwords( str_replace( '-', ' ', $row->category_slug ) );
			if ( ! empty( $customs[ $row->category_slug ] ) ) {
				$row->cover_image = $customs[ $row->category_slug ];
			}
			$result[] = $row;
		}
		return $result;
	}

	/**
	 * Map of category_slug → display name from stored scraper settings.
	 *
	 * @return array
	 */
	private static function category_name_map() {
		$cats = get_option( 'gsfm_categories', array() );
		$map  = array();
		if ( is_array( $cats ) ) {
			foreach ( $cats as $cat ) {
				if ( ! empty( $cat['slug'] ) && ! empty( $cat['name'] ) ) {
					$map[ $cat['slug'] ] = $cat['name'];
				}
			}
		}
		return $map;
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
	 * Update RRP, sale price and (optionally) VAT rate for a product.
	 *
	 * @param int        $id         Product ID.
	 * @param float      $rrp        Regular retail price (VAT inclusive).
	 * @param float      $sale_price Sale price (0 = no sale).
	 * @param float|null $vat_rate   VAT rate %, or null to leave unchanged.
	 */
	public static function set_prices( $id, $rrp, $sale_price, $vat_rate = null ) {
		global $wpdb;

		$data    = array(
			'rrp'        => (float) $rrp,
			'sale_price' => (float) $sale_price,
		);
		$formats = array( '%f', '%f' );

		if ( null !== $vat_rate ) {
			$data['vat_rate'] = (float) $vat_rate;
			$formats[]        = '%f';
		}

		$wpdb->update(
			GSFM_Database::products_table(),
			$data,
			array( 'id' => (int) $id ),
			$formats,
			array( '%d' )
		);
	}

	/**
	 * Compute VAT-aware pricing for a product.
	 *
	 * Consumer prices (RRP / sale) are treated as VAT-inclusive, per Irish law.
	 * Supplier cost is treated as the net cost basis (input VAT reclaimed).
	 *
	 * @param object $p Product row.
	 * @return array
	 */
	public static function pricing( $p ) {
		$rrp     = (float) $p->rrp;
		$regular = $rrp > 0 ? $rrp : (float) $p->display_price;
		$sale    = (float) $p->sale_price;
		$on_sale = $sale > 0 && $sale < $regular;

		$effective = $on_sale ? $sale : $regular;               // gross, VAT inclusive
		$rate      = isset( $p->vat_rate ) ? (float) $p->vat_rate : 23.0;
		$cost      = (float) $p->supplier_price;                 // net cost

		$vat_amount = $rate > 0 ? $effective * ( $rate / ( 100 + $rate ) ) : 0.0;
		$net        = $effective - $vat_amount;                  // revenue kept after VAT
		$profit     = $net - $cost;
		$margin     = $effective > 0 ? ( $profit / $effective ) * 100 : 0;

		return array(
			'regular'    => $regular,
			'sale'       => $sale,
			'effective'  => $effective,
			'on_sale'    => $on_sale,
			'vat_rate'   => $rate,
			'vat_amount' => $vat_amount,
			'net'        => $net,
			'profit'     => $profit,
			'margin_pct' => $margin,
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

	/**
	 * Update display options: hide price and custom button label.
	 *
	 * @param int    $id           Product ID.
	 * @param bool   $hide_price   Whether to hide the price on the shop.
	 * @param string $button_label Custom CTA label (empty = default).
	 */
	public static function set_display_options( $id, $hide_price, $button_label ) {
		global $wpdb;
		$wpdb->update(
			GSFM_Database::products_table(),
			array(
				'hide_price'   => $hide_price ? 1 : 0,
				'button_label' => sanitize_text_field( $button_label ),
			),
			array( 'id' => (int) $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}
}
