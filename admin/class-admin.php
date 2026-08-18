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

	const CAP        = 'manage_options';
	const JOB_OPTION = 'gsfm_scrape_job';
	const BATCH      = 5;

	/**
	 * Register admin hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'handle_posts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_ajax_gsfm_scrape_start', array( $this, 'ajax_scrape_start' ) );
		add_action( 'wp_ajax_gsfm_scrape_step', array( $this, 'ajax_scrape_step' ) );
		add_action( 'wp_ajax_gsfm_scrape_status', array( $this, 'ajax_scrape_status' ) );
		add_action( 'wp_ajax_gsfm_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_gsfm_lookup_rrp', array( $this, 'ajax_lookup_rrp' ) );
		add_action( 'wp_ajax_gsfm_rrp_pending', array( $this, 'ajax_rrp_pending' ) );
		add_action( 'wp_ajax_gsfm_rrp_batch', array( $this, 'ajax_rrp_batch' ) );
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
		add_submenu_page( 'gsfm-products', __( 'Drop Countdown', 'gym-store-for-members' ), __( 'Drop Countdown', 'gym-store-for-members' ), self::CAP, 'gsfm-countdown', array( $this, 'render_countdown' ) );
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
		if ( false !== strpos( $hook, 'gsfm-settings' ) ) {
			wp_enqueue_media();
		}
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

		// Update a product's prices / visibility.
		if ( isset( $_POST['gsfm_save_product'] ) ) {
			check_admin_referer( 'gsfm_product' );
			$id         = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
			$rrp        = isset( $_POST['rrp'] ) ? (float) $_POST['rrp'] : 0;
			$sale_price = isset( $_POST['sale_price'] ) ? (float) $_POST['sale_price'] : 0;
			$vat_rate   = isset( $_POST['vat_rate'] ) ? (float) $_POST['vat_rate'] : 23;
			$visible    = ! empty( $_POST['visible'] );
			$hide_price = ! empty( $_POST['hide_price'] );
			$button     = isset( $_POST['button_label'] ) ? sanitize_text_field( wp_unslash( $_POST['button_label'] ) ) : '';
			GSFM_Products::set_prices( $id, $rrp, $sale_price, $vat_rate );
			GSFM_Products::set_visible( $id, $visible );
			GSFM_Products::set_display_options( $id, $hide_price, $button );
			wp_safe_redirect( add_query_arg( array( 'page' => 'gsfm-products', 'updated' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// Save countdown / drop banner.
		if ( isset( $_POST['gsfm_save_countdown'] ) ) {
			check_admin_referer( 'gsfm_countdown' );
			$this->save_countdown();
			wp_safe_redirect( add_query_arg( array( 'page' => 'gsfm-countdown', 'updated' => 1 ), admin_url( 'admin.php' ) ) );
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

		$fields = array( 'product_link_pattern', 'openai_model', 'login_url', 'listing_url', 'page_param', 'username_field', 'password_field', 'username', 'xpath_item', 'xpath_title', 'xpath_image', 'xpath_price', 'xpath_stock', 'xpath_ref', 'in_stock_text' );

		$out = $current;
		foreach ( $fields as $f ) {
			if ( isset( $_POST[ $f ] ) ) {
				$out[ $f ] = sanitize_text_field( wp_unslash( $_POST[ $f ] ) );
			}
		}

		// Multi-line / raw fields.
		if ( isset( $_POST['category_urls'] ) ) {
			$out['category_urls'] = sanitize_textarea_field( wp_unslash( $_POST['category_urls'] ) );
		}
		if ( isset( $_POST['session_cookie'] ) ) {
			// Cookie header may contain characters sanitize_text_field would keep; trim only.
			$out['session_cookie'] = trim( (string) wp_unslash( $_POST['session_cookie'] ) );
		}

		$out['pages']     = isset( $_POST['pages'] ) ? max( 1, (int) $_POST['pages'] ) : 1;
		$out['max_pages'] = isset( $_POST['max_pages'] ) ? max( 1, (int) $_POST['max_pages'] ) : 20;
		$out['use_ai']    = ! empty( $_POST['use_ai'] ) ? 1 : 0;

		// Only replace stored secrets when a new value is typed.
		if ( ! empty( $_POST['password'] ) ) {
			$out['password_enc'] = GSFM_Scraper::encrypt( (string) wp_unslash( $_POST['password'] ) );
		}
		if ( ! empty( $_POST['openai_key'] ) ) {
			$out['openai_key_enc'] = GSFM_Scraper::encrypt( trim( (string) wp_unslash( $_POST['openai_key'] ) ) );
		}

		update_option( 'gsfm_settings', $out );

		// Save custom category images.
		if ( isset( $_POST['cat_image'] ) && is_array( $_POST['cat_image'] ) ) {
			$images = array();
			foreach ( $_POST['cat_image'] as $slug => $url ) {
				$slug = sanitize_key( $slug );
				$url  = esc_url_raw( (string) wp_unslash( $url ) );
				if ( '' !== $slug && '' !== $url ) {
					$images[ $slug ] = $url;
				}
			}
			update_option( 'gsfm_cat_images', $images );
		}
	}

	/**
	 * AJAX: start a scrape job. For category crawls this begins the discovery
	 * phase; with no category URLs it falls back to a synchronous legacy scrape.
	 */
	public function ajax_scrape_start() {
		check_ajax_referer( 'gsfm_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gym-store-for-members' ) ), 403 );
		}

		$scraper    = new GSFM_Scraper();
		$categories = GSFM_Scraper::parse_category_lines( GSFM_Scraper::get_settings()['category_urls'] );

		// No categories: run the legacy one-shot scrape immediately.
		if ( empty( $categories ) ) {
			$result = $scraper->run();
			delete_option( self::JOB_OPTION );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			$result['status'] = 'done';
			$result['phase']  = 'process';
			wp_send_json_success( $result );
		}

		update_option( 'gsfm_categories', $categories );

		$job = array(
			'status'          => 'running',
			'phase'           => 'discover',
			'categories'      => $categories,
			'product_url_cats' => array(),
			'total_products'  => 0,
			'processed'       => 0,
			'new'             => 0,
			'updated'         => 0,
			'skipped'         => 0,
			'updated_at'      => time(),
		);
		update_option( self::JOB_OPTION, $job, false );

		wp_send_json_success( $this->progress( $job ) );
	}

	/**
	 * AJAX: process one batch of the running job.
	 */
	public function ajax_scrape_step() {
		check_ajax_referer( 'gsfm_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gym-store-for-members' ) ), 403 );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$job = get_option( self::JOB_OPTION );
		if ( ! is_array( $job ) || 'running' !== $job['status'] ) {
			wp_send_json_success( array( 'status' => 'done' ) );
		}

		$scraper = new GSFM_Scraper();

		if ( 'discover' === $job['phase'] ) {
			$category = array_shift( $job['categories'] );
			if ( null !== $category ) {
				$found = $scraper->discover( $category );
				foreach ( $found as $url => $slug ) {
					if ( ! isset( $job['product_url_cats'][ $url ] ) ) {
						$job['product_url_cats'][ $url ] = $slug;
					}
				}
			}
			if ( empty( $job['categories'] ) ) {
				$job['phase']          = 'process';
				$job['total_products'] = count( $job['product_url_cats'] );
				if ( 0 === $job['total_products'] ) {
					$job['status'] = 'done';
				}
			}
		} elseif ( 'process' === $job['phase'] ) {
			$batch     = array_slice( $job['product_url_cats'], 0, self::BATCH, true );
			$remaining = array_slice( $job['product_url_cats'], self::BATCH, null, true );
			$job['product_url_cats'] = $remaining;
			foreach ( $batch as $url => $cat_slug ) {
				$status = $scraper->handle_product( $url, $cat_slug );
				$job['processed']++;
				if ( isset( $job[ $status ] ) ) {
					$job[ $status ]++;
				}
			}
			if ( empty( $job['product_url_cats'] ) ) {
				$job['status'] = 'done';
			}
		}

		$job['updated_at'] = time();
		update_option( self::JOB_OPTION, $job, false );

		wp_send_json_success( $this->progress( $job ) );
	}

	/**
	 * AJAX: return the current job status (for resuming after a page reload).
	 */
	public function ajax_scrape_status() {
		check_ajax_referer( 'gsfm_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gym-store-for-members' ) ), 403 );
		}

		$job = get_option( self::JOB_OPTION );
		if ( ! is_array( $job ) ) {
			wp_send_json_success( array( 'status' => 'idle' ) );
		}
		wp_send_json_success( $this->progress( $job ) );
	}

	/**
	 * AJAX: fetch one category URL and report what was returned, for diagnosing
	 * cookie/access issues before running a full scrape.
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'gsfm_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gym-store-for-members' ) ), 403 );
		}

		$s          = GSFM_Scraper::get_settings();
		$categories = GSFM_Scraper::parse_category_lines( $s['category_urls'] );
		$first_url  = ! empty( $categories[0]['url'] ) ? $categories[0]['url'] : '';
		$url        = ! empty( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : $first_url;

		if ( ! $url ) {
			wp_send_json_error( array( 'message' => 'No URL to test. Add a category URL in Settings first.' ) );
		}

		$cookie = trim( $s['session_cookie'] );
		$parsed = wp_parse_url( $url );
		$origin = ( isset( $parsed['scheme'] ) ? $parsed['scheme'] : 'https' ) . '://' . ( isset( $parsed['host'] ) ? $parsed['host'] : '' );

		$headers = array_merge(
			GSFM_Scraper::BROWSER_HEADERS,
			array( 'Referer' => $origin . '/' )
		);
		if ( '' !== $cookie ) {
			$headers['Cookie'] = $cookie;
		}

		$args = array(
			'timeout'     => 30,
			'redirection' => 5,
			'user-agent'  => GSFM_Scraper::UA,
			'headers'     => $headers,
		);

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		// Page title.
		preg_match( '/<title[^>]*>(.*?)<\/title>/is', $body, $tm );
		$title = isset( $tm[1] ) ? html_entity_decode( strip_tags( $tm[1] ) ) : '(no title)';

		// All hrefs.
		preg_match_all( '/href=["\']([^"\'#?][^"\']*)["\']/', $body, $lm );
		$all_links     = array_unique( $lm[1] );
		$pattern       = $s['product_link_pattern'] ? $s['product_link_pattern'] : '/product/';
		$product_links = array_values( array_filter( $all_links, function ( $l ) use ( $pattern ) {
			return false !== strpos( $l, $pattern );
		} ) );

		// Check for a login form — reliable signal the cookie was rejected.
		$has_login_form = (bool) preg_match( '/<form[^>]*action=["\'][^"\']*my-account["\']/', $body );

		wp_send_json_success( array(
			'url'             => $url,
			'http_status'     => $code,
			'page_title'      => trim( $title ),
			'total_links'     => count( $all_links ),
			'product_links'   => count( $product_links ),
			'sample_products' => array_slice( $product_links, 0, 5 ),
			'login_wall'      => $has_login_form,
			'cookie_set'      => '' !== $cookie,
		) );
	}

	/**
	 * Build a compact progress payload from job state.
	 *
	 * @param array $job Job state.
	 * @return array
	 */
	private function progress( $job ) {
		return array(
			'status'     => $job['status'],
			'phase'      => $job['phase'],
			'discovered' => count( $job['product_url_cats'] ) + $job['processed'],
			'total'      => $job['total_products'],
			'processed'  => $job['processed'],
			'new'        => $job['new'],
			'updated'    => $job['updated'],
			'skipped'    => $job['skipped'],
		);
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

	/**
	 * Render the drop countdown page.
	 */
	public function render_countdown() {
		$c = self::get_countdown();
		require GSFM_DIR . 'admin/views/countdown.php';
	}

	/**
	 * Countdown settings with defaults.
	 *
	 * @return array
	 */
	public static function get_countdown() {
		$defaults = array(
			'enabled'     => 0,
			'deadline'    => '',
			'headline'    => 'Next group order closes in',
			'subtext'     => "Let us know what you'd like us to order into the gym. You won't pay until it arrives — you'll get a text when it's in, usually within a few days.",
			'closed_text' => 'This order has closed — the next drop is coming soon. Stay tuned!',
			'bg_color'    => '#1a7f37',
			'text_color'  => '#ffffff',
		);
		$saved = get_option( 'gsfm_countdown', array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	/**
	 * Persist countdown settings.
	 */
	private function save_countdown() {
		$c = self::get_countdown();

		$c['enabled']     = ! empty( $_POST['enabled'] ) ? 1 : 0;
		$c['deadline']    = isset( $_POST['deadline'] ) ? sanitize_text_field( wp_unslash( $_POST['deadline'] ) ) : '';
		$c['headline']    = isset( $_POST['headline'] ) ? sanitize_text_field( wp_unslash( $_POST['headline'] ) ) : '';
		$c['subtext']     = isset( $_POST['subtext'] ) ? sanitize_textarea_field( wp_unslash( $_POST['subtext'] ) ) : '';
		$c['closed_text'] = isset( $_POST['closed_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['closed_text'] ) ) : '';
		$c['bg_color']    = isset( $_POST['bg_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['bg_color'] ) ) : '#1a7f37';
		$c['text_color']  = isset( $_POST['text_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['text_color'] ) ) : '#ffffff';

		update_option( 'gsfm_countdown', $c );
	}

	/**
	 * AJAX: suggest an RRP for a product via OpenAI.
	 */
	public function ajax_lookup_rrp() {
		check_ajax_referer( 'gsfm_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gym-store-for-members' ) ), 403 );
		}

		$id      = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		$product = GSFM_Products::get( $id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Product not found.', 'gym-store-for-members' ) ) );
		}

		$rrp = $this->openai_rrp( $product->title );
		if ( is_wp_error( $rrp ) ) {
			wp_send_json_error( array( 'message' => $rrp->get_error_message() ) );
		}

		wp_send_json_success( $rrp );
	}

	/**
	 * AJAX: return IDs of products that still need an RRP (rrp <= 0).
	 */
	public function ajax_rrp_pending() {
		check_ajax_referer( 'gsfm_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gym-store-for-members' ) ), 403 );
		}

		global $wpdb;
		$table = GSFM_Database::products_table();
		$ids   = $wpdb->get_col( "SELECT id FROM {$table} WHERE rrp <= 0 ORDER BY id ASC" );

		wp_send_json_success( array( 'ids' => array_map( 'intval', (array) $ids ) ) );
	}

	/**
	 * AJAX: look up and save RRP for a small batch of product IDs.
	 */
	public function ajax_rrp_batch() {
		check_ajax_referer( 'gsfm_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gym-store-for-members' ) ), 403 );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$ids     = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : array();
		$results = array();

		foreach ( $ids as $id ) {
			$product = GSFM_Products::get( $id );
			if ( ! $product ) {
				continue;
			}
			$rrp = $this->openai_rrp( $product->title );
			if ( is_wp_error( $rrp ) ) {
				$results[] = array( 'id' => $id, 'error' => $rrp->get_error_message() );
				continue;
			}
			GSFM_Products::set_prices( $id, $rrp['rrp'], (float) $product->sale_price, $rrp['vat'] );
			$results[] = array( 'id' => $id, 'rrp' => $rrp['rrp'], 'vat' => $rrp['vat'] );
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * Query OpenAI for a product's RRP and Irish VAT rate.
	 *
	 * @param string $title Product title.
	 * @return array|WP_Error  {rrp: float, vat: float}
	 */
	private function openai_rrp( $title ) {
		$s   = GSFM_Scraper::get_settings();
		$key = GSFM_Scraper::decrypt( $s['openai_key_enc'] );
		if ( '' === $key ) {
			return new WP_Error( 'gsfm_no_key', __( 'Add your OpenAI API key in Settings first.', 'gym-store-for-members' ) );
		}

		$body = array(
			'model'           => $s['openai_model'] ? $s['openai_model'] : 'gpt-4o-mini',
			'temperature'     => 0,
			'response_format' => array( 'type' => 'json_object' ),
			'messages'        => array(
				array(
					'role'    => 'system',
					'content' => 'You are a supplement pricing assistant for the Irish (Ireland) market. Given a product name, return JSON: {"rrp": number, "vat_rate": number}. "rrp" is the typical retail price in EUR, VAT inclusive. "vat_rate" is the Irish VAT percentage most likely to apply: food supplements are usually 23; some sports nutrition foods (e.g. protein bars/powders classed as food) may be 13.5 or 0. If unsure, use 23. Return only the JSON.',
				),
				array(
					'role'    => 'user',
					'content' => $title,
				),
			),
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 45,
				'headers' => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $decoded['choices'][0]['message']['content'] ) ) {
			$err = isset( $decoded['error']['message'] ) ? $decoded['error']['message'] : __( 'No response from OpenAI.', 'gym-store-for-members' );
			return new WP_Error( 'gsfm_openai', $err );
		}

		$parsed = json_decode( $decoded['choices'][0]['message']['content'], true );
		if ( ! isset( $parsed['rrp'] ) ) {
			return new WP_Error( 'gsfm_openai', __( 'Could not determine an RRP.', 'gym-store-for-members' ) );
		}

		$vat = isset( $parsed['vat_rate'] ) ? (float) $parsed['vat_rate'] : 23.0;
		if ( $vat < 0 || $vat > 40 ) {
			$vat = 23.0;
		}

		return array(
			'rrp' => round( (float) $parsed['rrp'], 2 ),
			'vat' => $vat,
		);
	}
}
