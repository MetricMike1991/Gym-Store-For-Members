<?php
/**
 * Front-end shortcodes and member request AJAX.
 *
 * @package GymStoreForMembers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSFM_Public {

	/**
	 * Register front-end hooks.
	 */
	public function init() {
		add_shortcode( 'gym_shop', array( $this, 'shortcode_shop' ) );
		add_shortcode( 'gym_account', array( $this, 'shortcode_account' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_ajax_gsfm_toggle', array( $this, 'ajax_toggle' ) );
	}

	/**
	 * Enqueue front-end assets.
	 */
	public function assets() {
		wp_enqueue_style( 'gsfm-public', GSFM_URL . 'public/css/style.css', array(), GSFM_VERSION );
		wp_enqueue_script( 'gsfm-public', GSFM_URL . 'public/js/shop.js', array( 'jquery' ), GSFM_VERSION, true );
		wp_localize_script(
			'gsfm-public',
			'GSFM',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'gsfm_public' ),
			)
		);
	}

	/**
	 * [gym_shop] — category grid or product list for a selected category.
	 *
	 * @return string
	 */
	public function shortcode_shop() {
		$logged_in   = is_user_logged_in();
		$my_products = $logged_in ? GSFM_Wishlist::get_product_ids( get_current_user_id() ) : array();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$cat_slug = isset( $_GET['gsfm_cat'] ) ? sanitize_key( wp_unslash( $_GET['gsfm_cat'] ) ) : '';

		if ( '' !== $cat_slug ) {
			$products = GSFM_Products::get_shop_products( $cat_slug );
			$cats     = GSFM_Products::get_categories();
			$cat_name = $cat_slug;
			foreach ( $cats as $c ) {
				if ( $c->category_slug === $cat_slug ) {
					$cat_name = $c->category_name;
					break;
				}
			}
			ob_start();
			require GSFM_DIR . 'public/views/shop.php';
			return ob_get_clean();
		}

		$categories = GSFM_Products::get_categories();
		ob_start();
		require GSFM_DIR . 'public/views/categories.php';
		return ob_get_clean();
	}

	/**
	 * [gym_account] — the member's own requests.
	 *
	 * @return string
	 */
	public function shortcode_account() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your requests.', 'gym-store-for-members' ) . '</p>';
		}

		$requests = GSFM_Wishlist::get_for_user( get_current_user_id() );

		ob_start();
		require GSFM_DIR . 'public/views/account.php';
		return ob_get_clean();
	}

	/**
	 * AJAX: toggle a product request for the current user.
	 */
	public function ajax_toggle() {
		check_ajax_referer( 'gsfm_public', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in first.', 'gym-store-for-members' ) ), 401 );
		}

		$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		$want       = isset( $_POST['want'] ) && '1' === $_POST['want'];
		$user_id    = get_current_user_id();

		if ( ! GSFM_Products::get( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Product not found.', 'gym-store-for-members' ) ) );
		}

		if ( $want ) {
			GSFM_Wishlist::add( $user_id, $product_id );
		} else {
			GSFM_Wishlist::remove( $user_id, $product_id );
		}

		wp_send_json_success( array( 'requested' => $want ) );
	}
}
