<?php
/**
 * Admin menu, settings, and AJAX handlers.
 *
 * @package GymStoreForMembers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSFM_Admin {

	const CAP = 'manage_options';

	/**
	 * Register admin hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'handle_posts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_ajax_gsfm_scrape', array( $this, 'ajax_scrape' ) );
	}

	/**
	 * Register the admin menu and sub-pages.
	 */
	public function menu() {
		add_menu_page(
			__( 'Supplement Shop', 'gym-store-for-members' ),
			__( 'Supplement Shop', 'gym-store-for-members' ),
			self::CAP,
			'gsfm-products',
			array( $this, 'render_products' ),
			'dashicons-cart',
			56
		);
		add_submenu_page( 'gsfm-products', __( 'Products', 'gym-store-for-members' ), __( 'Products', 'gym-store-for-members' ), self::CAP, 'gsfm-products', array( $this, 'render_products' ) );
		add_submenu_page( 'gsfm-products', __( 'Requests', 'gym-store-for-members' ), __( 'Requests', 'gym-store-for-members' ), self::CAP, 'gsfm-requests', array( $this, 'render_requests' ) );
		add_submenu_page( 'gsfm-products', __( 'Settings', 'gym-store-for-members' ), __( 'Settings', 'gym-store-for-members' ), self::CAP, 'gsfm-settings', array( $this, 'render_settings' ) );
	}

	/**
	 * Enqueue admin JS on plugin pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function assets( $hook ) {
		if ( false === strpos( $hook, 'gsfm-' ) ) {
			return;
		}
		wp_enqueue_script( 'gsfm-admin', GSFM_URL . 'admin/js/admin.js', array( 'jquery' ), GSFM_VERSION, true );
		wp_localize_script(
			'gsfm-admin',
			'GSFM_ADMIN',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'gsfm_admin' ),
			)
		);
	}

	/**
	 * Handle non-AJAX admin form posts (settings, price/visibility, status, export).
	 */
	public function handle_posts() {
		if ( ! is_admin() || ! current_user_can( self::CAP ) ) {
			return;
		}

		// Save settings.
		if ( isset( $_POST['gsfm_save_settings'] ) ) {
			check_admin_referer( 'gsfm_settings' );
			$this->save_settings();
			wp_safe_redirect( add_query_arg( array( 'page' => 'gsfm-settings', 'updated' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// Update a product's display price / visibility.
		if ( isset( $_POST['gsfm_save_product'] ) ) {
			check_admin_referer( 'gsfm_product' );
			$id      = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
			$price   = isset( $_POST['display_price'] ) ? (float) $_POST['display_price'] : 0;
			$visible = ! empty( $_POST['visible'] );
			GSFM_Products::set_display_price( $id, $price );
			GSFM_Products::set_visible( $id, $visible );
			wp_safe_redirect( add_query_arg( array( 'page' => 'gsfm-products', 'updated' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// Update a request status.
		if ( isset( $_POST['gsfm_save_request'] ) ) {
			check_admin_referer( 'gsfm_request' );
			$id     = isset( $_POST['request_id'] ) ? (int) $_POST['request_id'] : 0;
			$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'pending';
			GSFM_Wishlist::set_status( $id, $status );
			wp_safe_redirect( add_query_arg( array( 'page' => 'gsfm-requests', 'updated' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// Export CSV.
		if ( isset( $_GET['gsfm_export'] ) && check_admin_referer( 'gsfm_export' ) ) {
			$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
			GSFM_Export::stream( $status );
		}
	}

	/**
	 * Persist settings from the form.
	 */
	private function save_settings() {
		$current = GSFM_Scraper::get_settings();

		$fields = array( 'login_url', 'listing_url', 'page_param', 'username_field', 'password_field', 'username', 'xpath_item', 'xpath_title', 'xpath_image', 'xpath_price', 'xpath_stock', 'xpath_ref', 'in_stock_text' );

		$out = $current;
		foreach ( $fields as $f ) {
			if ( isset( $_POST[ $f ] ) ) {
				$out[ $f ] = sanitize_text_field( wp_unslash( $_POST[ $f ] ) );
			}
		}
		$out['pages'] = isset( $_POST['pages'] ) ? max( 1, (int) $_POST['pages'] ) : 1;

		// Only replace the stored password when a new one is typed.
		if ( ! empty( $_POST['password'] ) ) {
			$out['password_enc'] = GSFM_Scraper::encrypt( (string) wp_unslash( $_POST['password'] ) );
		}

		update_option( 'gsfm_settings', $out );
	}

	/**
	 * AJAX: run a scrape.
	 */
	public function ajax_scrape() {
		check_ajax_referer( 'gsfm_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gym-store-for-members' ) ), 403 );
		}

		$result = ( new GSFM_Scraper() )->run();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Render the products page.
	 */
	public function render_products() {
		$products = GSFM_Products::get_all();
		require GSFM_DIR . 'admin/views/products.php';
	}

	/**
	 * Render the requests page.
	 */
	public function render_requests() {
		$status   = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$requests = GSFM_Wishlist::get_all( $status );
		require GSFM_DIR . 'admin/views/requests.php';
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings() {
		$s = GSFM_Scraper::get_settings();
		require GSFM_DIR . 'admin/views/settings.php';
	}
}
