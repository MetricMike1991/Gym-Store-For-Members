<?php
/**
 * Supplier scraper: login, fetch listing pages, parse, upsert products.
 *
 * @package GymStoreForMembers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSFM_Scraper {

	/**
	 * Default settings shape.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'login_url'        => '',
			'listing_url'      => '',
			'pages'            => 1,
			'page_param'       => 'page',
			'username_field'   => 'username',
			'password_field'   => 'password',
			'username'         => '',
			'password_enc'     => '',
			'xpath_item'       => "//li[contains(concat(' ', normalize-space(@class), ' '), ' product ') and contains(@class,'type-product')]",
			'xpath_title'      => './/h2 | .//h3',
			'xpath_image'      => './/img',
			'xpath_price'      => ".//span[contains(@class,'price')]",
			'xpath_stock'      => '',
			'xpath_ref'        => '',
			'in_stock_text'    => '',
		);
	}

	/**
	 * Read merged settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved = get_option( 'gsfm_settings', array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::default_settings() );
	}

	/**
	 * Encryption key derived from WP salts.
	 *
	 * @return string
	 */
	private static function key() {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}

	/**
	 * Encrypt a secret for storage.
	 *
	 * @param string $plain Plaintext.
	 * @return string Base64 iv:cipher.
	 */
	public static function encrypt( $plain ) {
		if ( '' === $plain ) {
			return '';
		}
		$iv     = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );
		$cipher = openssl_encrypt( $plain, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv );
		return base64_encode( $iv . $cipher );
	}

	/**
	 * Decrypt a stored secret.
	 *
	 * @param string $stored Base64 iv:cipher.
	 * @return string
	 */
	public static function decrypt( $stored ) {
		if ( '' === $stored ) {
			return '';
		}
		$raw     = base64_decode( $stored );
		$iv_len  = openssl_cipher_iv_length( 'aes-256-cbc' );
		$iv      = substr( $raw, 0, $iv_len );
		$cipher  = substr( $raw, $iv_len );
		$plain   = openssl_decrypt( $cipher, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv );
		return false === $plain ? '' : $plain;
	}

	/**
	 * Run a full scrape.
	 *
	 * @return array|WP_Error Counts on success.
	 */
	public function run() {
		$s = self::get_settings();

		if ( empty( $s['listing_url'] ) || empty( $s['xpath_item'] ) ) {
			return new WP_Error( 'gsfm_config', __( 'Listing URL and product item selector are required.', 'gym-store-for-members' ) );
		}

		$cookies = array();
		if ( ! empty( $s['login_url'] ) && ! empty( $s['username'] ) ) {
			$cookies = $this->login( $s );
			if ( is_wp_error( $cookies ) ) {
				return $cookies;
			}
		}

		$new     = 0;
		$updated = 0;
		$seen    = 0;
		$pages   = max( 1, (int) $s['pages'] );

		for ( $page = 1; $page <= $pages; $page++ ) {
			$url  = $this->page_url( $s['listing_url'], $s['page_param'], $page );
			$html = $this->fetch( $url, $cookies );
			if ( is_wp_error( $html ) ) {
				return $html;
			}

			$items = $this->parse( $html, $s );
			foreach ( $items as $item ) {
				$seen++;
				$before = GSFM_Products::get_by_ref( $item['supplier_ref'] );
				GSFM_Products::upsert( $item );
				if ( $before ) {
					$updated++;
				} else {
					$new++;
				}
			}
		}

		return array(
			'seen'    => $seen,
			'new'     => $new,
			'updated' => $updated,
		);
	}

	/**
	 * Authenticate and return session cookies.
	 *
	 * @param array $s Settings.
	 * @return array|WP_Error
	 */
	private function login( $s ) {
		$password = self::decrypt( $s['password_enc'] );

		$response = wp_remote_post(
			$s['login_url'],
			array(
				'timeout'     => 30,
				'redirection' => 5,
				'body'        => array(
					$s['username_field'] => $s['username'],
					$s['password_field'] => $password,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$cookies = wp_remote_retrieve_cookies( $response );
		if ( empty( $cookies ) ) {
			return new WP_Error( 'gsfm_login', __( 'Login returned no session cookies. Check the login URL and field names.', 'gym-store-for-members' ) );
		}

		return $cookies;
	}

	/**
	 * Fetch a page with optional session cookies.
	 *
	 * @param string $url     URL.
	 * @param array  $cookies Session cookies.
	 * @return string|WP_Error
	 */
	private function fetch( $url, $cookies ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 30,
				'redirection' => 5,
				'cookies'     => $cookies,
				'user-agent'  => 'Mozilla/5.0 (compatible; GymStoreBot/1.0)',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'gsfm_fetch', sprintf( /* translators: %d: HTTP status */ __( 'Fetch failed with HTTP %d.', 'gym-store-for-members' ), $code ) );
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Build a paginated URL.
	 *
	 * @param string $base  Listing URL.
	 * @param string $param Page query parameter.
	 * @param int    $page  Page number.
	 * @return string
	 */
	private function page_url( $base, $param, $page ) {
		if ( $page <= 1 ) {
			return $base;
		}
		return add_query_arg( $param, $page, $base );
	}

	/**
	 * Parse a listing page into product rows.
	 *
	 * @param string $html HTML.
	 * @param array  $s    Settings.
	 * @return array
	 */
	private function parse( $html, $s ) {
		$items = array();

		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( $html );
		libxml_clear_errors();

		$xpath = new DOMXPath( $doc );
		$nodes = $xpath->query( $s['xpath_item'] );
		if ( ! $nodes ) {
			return $items;
		}

		foreach ( $nodes as $node ) {
			$title = $this->extract( $xpath, $node, $s['xpath_title'] );
			if ( '' === $title ) {
				continue;
			}

			$price_raw  = $this->extract( $xpath, $node, $s['xpath_price'] );
			$ref        = $this->extract( $xpath, $node, $s['xpath_ref'] );
			$image      = $this->extract_attr( $xpath, $node, $s['xpath_image'] );
			$item_class = ( $node instanceof DOMElement ) ? $node->getAttribute( 'class' ) : '';

			$items[] = array(
				'supplier_ref'   => $this->resolve_ref( $ref, $item_class, $title ),
				'title'          => $title,
				'image_url'      => $image,
				'supplier_price' => $this->parse_price( $price_raw ),
				'in_stock'       => $this->resolve_stock( $xpath, $node, $s, $item_class ),
			);
		}

		return $items;
	}

	/**
	 * Determine stock from the WooCommerce product class, then optional text.
	 *
	 * @param DOMXPath $xpath      XPath.
	 * @param DOMNode  $node       Product item node.
	 * @param array    $s          Settings.
	 * @param string   $item_class Product item class attribute.
	 * @return bool
	 */
	private function resolve_stock( $xpath, $node, $s, $item_class ) {
		if ( false !== stripos( $item_class, 'outofstock' ) ) {
			return false;
		}
		if ( false !== stripos( $item_class, 'instock' ) ) {
			return true;
		}
		if ( '' !== $s['xpath_stock'] && '' !== $s['in_stock_text'] ) {
			$stock_raw = $this->extract( $xpath, $node, $s['xpath_stock'] );
			if ( '' !== $stock_raw ) {
				return false !== stripos( $stock_raw, $s['in_stock_text'] );
			}
		}
		return true;
	}

	/**
	 * Resolve a stable product reference: explicit SKU, WooCommerce post ID, then title hash.
	 *
	 * @param string $ref        Extracted SKU/ref text.
	 * @param string $item_class Product item class attribute.
	 * @param string $title      Product title.
	 * @return string
	 */
	private function resolve_ref( $ref, $item_class, $title ) {
		if ( '' !== $ref ) {
			return $ref;
		}
		if ( preg_match( '/post-(\d+)/', $item_class, $m ) ) {
			return 'post-' . $m[1];
		}
		return md5( $title );
	}

	/**
	 * Extract trimmed text content relative to a node.
	 *
	 * @param DOMXPath $xpath   XPath.
	 * @param DOMNode  $context Context node.
	 * @param string   $query   Relative XPath.
	 * @return string
	 */
	private function extract( $xpath, $context, $query ) {
		if ( '' === $query ) {
			return '';
		}
		$found = $xpath->query( $query, $context );
		if ( $found && $found->length ) {
			return trim( $found->item( 0 )->textContent );
		}
		return '';
	}

	/**
	 * Extract an image src relative to a node.
	 *
	 * @param DOMXPath $xpath   XPath.
	 * @param DOMNode  $context Context node.
	 * @param string   $query   Relative XPath (should target an img element).
	 * @return string
	 */
	private function extract_attr( $xpath, $context, $query ) {
		if ( '' === $query ) {
			return '';
		}
		$found = $xpath->query( $query, $context );
		if ( ! $found || ! $found->length ) {
			return '';
		}
		$el = $found->item( 0 );
		if ( ! $el instanceof DOMElement ) {
			return '';
		}

		// Prefer real source attributes; skip inline data: placeholders used by lazy loaders.
		foreach ( array( 'data-nectar-img-src', 'data-src', 'data-lazy-src', 'src' ) as $attr ) {
			if ( $el->hasAttribute( $attr ) ) {
				$val = trim( $el->getAttribute( $attr ) );
				if ( '' !== $val && 0 !== strpos( $val, 'data:' ) ) {
					return $val;
				}
			}
		}

		// Fall back to the first URL in srcset.
		if ( $el->hasAttribute( 'srcset' ) ) {
			$srcset = trim( $el->getAttribute( 'srcset' ) );
			if ( '' !== $srcset ) {
				$first = explode( ',', $srcset );
				$url   = trim( explode( ' ', trim( $first[0] ) )[0] );
				if ( '' !== $url ) {
					return $url;
				}
			}
		}

		return '';
	}

	/**
	 * Parse a price string into a float.
	 *
	 * @param string $raw Raw price text.
	 * @return float
	 */
	private function parse_price( $raw ) {
		$clean = preg_replace( '/[^0-9,.]/', '', $raw );
		$clean = str_replace( ',', '.', $clean );
		if ( '' === $clean ) {
			return 0.0;
		}
		// Keep only the last decimal point if multiple remain.
		$parts = explode( '.', $clean );
		if ( count( $parts ) > 2 ) {
			$dec   = array_pop( $parts );
			$clean = implode( '', $parts ) . '.' . $dec;
		}
		return (float) $clean;
	}
}
