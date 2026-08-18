<?php
/**
 * Member wishlist / product requests.
 *
 * @package GymStoreForMembers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSFM_Wishlist {

	/**
	 * Add a product request for a user. Idempotent (unique user_product key).
	 *
	 * @param int $user_id    WP user ID.
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function add( $user_id, $product_id ) {
		global $wpdb;
		$table = GSFM_Database::wishlist_table();

		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$table} (user_id, product_id, requested_at, status)
				 VALUES (%d, %d, %s, %s)",
				(int) $user_id,
				(int) $product_id,
				current_time( 'mysql' ),
				'pending'
			)
		);

		return false !== $result;
	}

	/**
	 * Remove a user's request for a product.
	 *
	 * @param int $user_id    WP user ID.
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function remove( $user_id, $product_id ) {
		global $wpdb;
		$table = GSFM_Database::wishlist_table();
		return (bool) $wpdb->delete(
			$table,
			array(
				'user_id'    => (int) $user_id,
				'product_id' => (int) $product_id,
			),
			array( '%d', '%d' )
		);
	}

	/**
	 * Product IDs a user has requested.
	 *
	 * @param int $user_id WP user ID.
	 * @return int[]
	 */
	public static function get_product_ids( $user_id ) {
		global $wpdb;
		$table = GSFM_Database::wishlist_table();
		$ids   = $wpdb->get_col(
			$wpdb->prepare( "SELECT product_id FROM {$table} WHERE user_id = %d", (int) $user_id )
		);
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * A user's requests joined with product data.
	 *
	 * @param int $user_id WP user ID.
	 * @return array
	 */
	public static function get_for_user( $user_id ) {
		global $wpdb;
		$w = GSFM_Database::wishlist_table();
		$p = GSFM_Database::products_table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT w.*, p.title, p.image_url, p.display_price, p.rrp, p.sale_price, p.supplier_price
				 FROM {$w} w
				 INNER JOIN {$p} p ON p.id = w.product_id
				 WHERE w.user_id = %d
				 ORDER BY w.requested_at DESC",
				(int) $user_id
			)
		);
	}

	/**
	 * All requests joined with product + user data for admin/export.
	 *
	 * @param string $status Optional status filter.
	 * @return array
	 */
	public static function get_all( $status = '' ) {
		global $wpdb;
		$w = GSFM_Database::wishlist_table();
		$p = GSFM_Database::products_table();
		$u = $wpdb->users;

		$sql = "SELECT w.id, w.status, w.requested_at, w.notes,
					u.display_name, u.user_email,
					p.title, p.supplier_price, p.display_price
				FROM {$w} w
				INNER JOIN {$p} p ON p.id = w.product_id
				INNER JOIN {$u} u ON u.ID = w.user_id";

		if ( $status ) {
			return $wpdb->get_results(
				$wpdb->prepare( $sql . ' WHERE w.status = %s ORDER BY w.requested_at DESC', $status )
			);
		}

		return $wpdb->get_results( $sql . ' ORDER BY w.requested_at DESC' );
	}

	/**
	 * Update a request's status.
	 *
	 * @param int    $id     Wishlist row ID.
	 * @param string $status New status.
	 */
	public static function set_status( $id, $status ) {
		global $wpdb;
		$allowed = array( 'pending', 'arrived', 'collected' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return;
		}
		$wpdb->update(
			GSFM_Database::wishlist_table(),
			array( 'status' => $status ),
			array( 'id' => (int) $id ),
			array( '%s' ),
			array( '%d' )
		);
	}
}
