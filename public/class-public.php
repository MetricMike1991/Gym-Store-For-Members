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
		add_shortcode( 'gym_countdown', array( $this, 'shortcode_countdown' ) );
		add_shortcode( 'gym_access', array( $this, 'shortcode_access' ) );
		add_shortcode( 'gym_my_requests', array( $this, 'shortcode_my_requests' ) );
		add_shortcode( 'gym_logo', array( $this, 'shortcode_logo' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_ajax_gsfm_toggle', array( $this, 'ajax_toggle' ) );
		add_action( 'wp_ajax_gsfm_my_requests', array( $this, 'ajax_my_requests' ) );
		add_action( 'wp_ajax_nopriv_gsfm_my_requests', array( $this, 'ajax_my_requests' ) );
		add_action( 'wp_ajax_nopriv_gsfm_login', array( $this, 'ajax_login' ) );
		add_action( 'wp_ajax_nopriv_gsfm_register', array( $this, 'ajax_register' ) );

		// Keep the front end from feeling like WordPress: hide the admin bar for non-admins.
		add_filter( 'show_admin_bar', array( $this, 'maybe_hide_admin_bar' ) );

		// Keep our scripts out of SiteGround Optimizer's JS combine/minify/defer so a broken bundle can't disable them.
		add_filter( 'sgo_javascript_combine_exclude', array( $this, 'sg_exclude_js' ) );
		add_filter( 'sgo_js_minify_exclude', array( $this, 'sg_exclude_js' ) );
		add_filter( 'sgo_javascript_combine_exclude_ids', array( $this, 'sg_exclude_js' ) );
		add_filter( 'sgo_js_async_exclude', array( $this, 'sg_exclude_js' ) );
	}

	/**
	 * Exclude plugin scripts from SiteGround Optimizer JS optimisation.
	 *
	 * @param array $handles Excluded handles/ids.
	 * @return array
	 */
	public function sg_exclude_js( $handles ) {
		$handles   = is_array( $handles ) ? $handles : array();
		$handles[] = 'gsfm-public';
		$handles[] = 'jquery';
		$handles[] = 'jquery-core';
		return $handles;
	}

	/**
	 * Hide the admin toolbar for members who cannot edit content.
	 *
	 * @param bool $show Current visibility.
	 * @return bool
	 */
	public function maybe_hide_admin_bar( $show ) {
		return current_user_can( 'edit_posts' ) ? $show : false;
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
			return $this->logo_html() . ob_get_clean() . $this->my_requests_panel();
		}

		$categories = GSFM_Products::get_categories();
		ob_start();
		require GSFM_DIR . 'public/views/categories.php';
		return $this->logo_html() . ob_get_clean() . $this->my_requests_panel();
	}

	/**
	 * Build the member's request panel (server-rendered, refreshed via AJAX).
	 * Wrapped in a mount div so JS can keep it in sync and it survives caching.
	 *
	 * @return string
	 */
	private function my_requests_panel() {
		$inner = '';
		if ( is_user_logged_in() ) {
			$requests = GSFM_Wishlist::get_for_user( get_current_user_id() );
			ob_start();
			require GSFM_DIR . 'public/views/my-requests.php';
			$inner = ob_get_clean();
		}
		return '<div class="gsfm-myreq-mount" aria-live="polite">' . $inner . '</div>';
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
	 * [gym_countdown] — drop countdown banner.
	 *
	 * @return string
	 */
	public function shortcode_countdown() {
		$c = GSFM_Admin::get_countdown();

		if ( empty( $c['enabled'] ) || empty( $c['deadline'] ) ) {
			return '';
		}

		$ts = strtotime( $c['deadline'] );
		if ( ! $ts ) {
			return '';
		}

		ob_start();
		require GSFM_DIR . 'public/views/countdown.php';
		return ob_get_clean();
	}

	/**
	 * Logo HTML for the top of member-facing pages.
	 *
	 * @return string
	 */
	public function logo_html() {
		$settings = GSFM_Scraper::get_settings();
		if ( empty( $settings['logo_url'] ) ) {
			return '';
		}
		return '<div class="gsfm-logo"><img src="' . esc_url( $settings['logo_url'] ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" /></div>';
	}

	/**
	 * [gym_logo] — the gym logo, placeable anywhere.
	 *
	 * @return string
	 */
	public function shortcode_logo() {
		return $this->logo_html();
	}

	/**
	 * [gym_my_requests] — compact list of the logged-in member's requests.
	 * Ideal below the category grid on the shop page.
	 *
	 * @return string
	 */
	public function shortcode_my_requests() {
		// Server-rendered immediately, then refreshed via AJAX for live accuracy.
		return $this->my_requests_panel();
	}

	/**
	 * AJAX: return the current member's requests panel HTML.
	 */
	public function ajax_my_requests() {
		check_ajax_referer( 'gsfm_public', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_success( array( 'html' => '' ) );
		}

		$requests = GSFM_Wishlist::get_for_user( get_current_user_id() );

		ob_start();
		require GSFM_DIR . 'public/views/my-requests.php';
		wp_send_json_success( array( 'html' => ob_get_clean() ) );
	}

	/**
	 * [gym_access] — branded login + registration panel.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_access( $atts ) {
		$atts = shortcode_atts(
			array( 'redirect' => '' ),
			$atts,
			'gym_access'
		);

		$redirect = $atts['redirect'];
		if ( '' === $redirect ) {
			$settings = GSFM_Scraper::get_settings();
			if ( ! empty( $settings['shop_page_url'] ) ) {
				$redirect = $settings['shop_page_url'];
			} else {
				// Never leave this empty, or the "Go to the shop" link does nothing.
				$redirect = home_url( '/' );
			}
		}

		$logged_in = is_user_logged_in();
		$current   = wp_get_current_user();

		ob_start();
		require GSFM_DIR . 'public/views/access.php';
		return $this->logo_html() . ob_get_clean();
	}

	/**
	 * AJAX: log a member in with their email + password.
	 */
	public function ajax_login() {
		check_ajax_referer( 'gsfm_public', 'nonce' );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$pass  = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

		if ( ! is_email( $email ) || '' === $pass ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your email and password.', 'gym-store-for-members' ) ) );
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'No account found with that email. Please create one.', 'gym-store-for-members' ) ) );
		}

		$signed = wp_signon(
			array(
				'user_login'    => $user->user_login,
				'user_password' => $pass,
				'remember'      => true,
			),
			is_ssl()
		);

		if ( is_wp_error( $signed ) ) {
			wp_send_json_error( array( 'message' => __( 'That password is incorrect. Please try again.', 'gym-store-for-members' ) ) );
		}

		wp_set_current_user( $signed->ID );
		wp_send_json_success( array( 'redirect' => $this->safe_redirect_target() ) );
	}

	/**
	 * AJAX: register a new member using their membership email.
	 */
	public function ajax_register() {
		check_ajax_referer( 'gsfm_public', 'nonce' );

		// Honeypot: bots fill hidden fields.
		if ( ! empty( $_POST['website'] ) ) {
			wp_send_json_success( array( 'redirect' => $this->safe_redirect_target() ) );
		}

		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$pass  = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your name.', 'gym-store-for-members' ) ) );
		}
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'gym-store-for-members' ) ) );
		}
		if ( strlen( $pass ) < 8 ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a password of at least 8 characters.', 'gym-store-for-members' ) ) );
		}
		if ( email_exists( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'An account with that email already exists — please log in instead.', 'gym-store-for-members' ) ) );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $email,
				'user_email'   => $email,
				'user_pass'    => $pass,
				'display_name' => $name,
				'first_name'   => $name,
				'role'         => 'subscriber',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not create your account. Please try again.', 'gym-store-for-members' ) ) );
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );
		wp_send_json_success( array( 'redirect' => $this->safe_redirect_target() ) );
	}

	/**
	 * Validate the posted redirect URL against this site.
	 *
	 * @return string
	 */
	private function safe_redirect_target() {
		$requested = isset( $_POST['redirect'] ) ? esc_url_raw( wp_unslash( $_POST['redirect'] ) ) : '';
		$fallback  = home_url( '/' );
		if ( '' === $requested ) {
			return $fallback;
		}
		return wp_validate_redirect( $requested, $fallback );
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
