<?php
/**
 * Supplier scraper.
 *
 * Preferred flow: paste a logged-in session cookie + a list of category URLs.
 * The crawler discovers product links, visits each product page, and extracts
 * data using structured sources first (JSON-LD, OpenGraph), then an optional
 * OpenAI fallback, then legacy XPath. This keeps it robust and supplier-agnostic.
 *
 * @package GymStoreForMembers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSFM_Scraper {

	const UA = 'Mozilla/5.0 (compatible; GymStoreBot/1.0)';

	/**
	 * Default settings shape.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			// Preferred: manual-login session + category crawl.
			'session_cookie'       => '',
			'category_urls'        => '',
			'product_link_pattern' => '/product/',
			'max_pages'            => 20,

			// Optional AI fallback.
			'use_ai'               => 0,
			'openai_key_enc'       => '',
			'openai_model'         => 'gpt-4o-mini',

			// Legacy: programmatic login + listing XPath (fallback only).
			'login_url'            => '',
			'listing_url'          => '',
			'pages'                => 1,
			'page_param'           => 'page',
			'username_field'       => 'username',
			'password_field'       => 'password',
			'username'             => '',
			'password_enc'         => '',
			'xpath_item'           => "//li[contains(concat(' ', normalize-space(@class), ' '), ' product ') and contains(@class,'type-product')]",
			'xpath_title'          => './/h2 | .//h3',
			'xpath_image'          => './/img',
			'xpath_price'          => ".//span[contains(@class,'price')]",
			'xpath_stock'          => '',
			'xpath_ref'            => '',
			'in_stock_text'        => '',
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
	 * @return string
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
	 * @param string $stored Stored value.
	 * @return string
	 */
	public static function decrypt( $stored ) {
		if ( '' === $stored ) {
			return '';
		}
		$raw    = base64_decode( $stored );
		$iv_len = openssl_cipher_iv_length( 'aes-256-cbc' );
		$iv     = substr( $raw, 0, $iv_len );
		$cipher = substr( $raw, $iv_len );
		$plain  = openssl_decrypt( $cipher, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv );
		return false === $plain ? '' : $plain;
	}

	/**
	 * Run a scrape. Uses the category crawl when category URLs are set,
	 * otherwise falls back to the legacy login + listing scrape.
	 *
	 * @return array|WP_Error
	 */
	public function run() {
		$s          = self::get_settings();
		$categories = $this->lines( $s['category_urls'] );

		if ( ! empty( $categories ) ) {
			return $this->run_crawl( $s, $categories );
		}

		return $this->run_legacy( $s );
	}

	/**
	 * Category URLs configured for crawling.
	 *
	 * @return array
	 */
	public function category_urls() {
		return $this->lines( self::get_settings()['category_urls'] );
	}

	/**
	 * Discover product URLs within one category (used by batched jobs).
	 *
	 * @param string $category_url Category URL.
	 * @return array
	 */
	public function discover( $category_url ) {
		$s = self::get_settings();
		return $this->collect_product_links( $category_url, trim( $s['session_cookie'] ), $s );
	}

	/**
	 * Fetch, extract and upsert a single product (used by batched jobs).
	 *
	 * @param string $url Product URL.
	 * @return string 'new' | 'updated' | 'skipped'
	 */
	public function handle_product( $url ) {
		$s    = self::get_settings();
		$html = $this->fetch( $url, trim( $s['session_cookie'] ) );
		if ( is_wp_error( $html ) ) {
			return 'skipped';
		}

		$data = $this->extract_product( $html, $url, $s );
		if ( empty( $data ) || '' === $data['title'] ) {
			return 'skipped';
		}

		$before = GSFM_Products::get_by_ref( $data['supplier_ref'] );
		GSFM_Products::upsert( $data );
		return $before ? 'updated' : 'new';
	}

	/**
	 * Crawl category pages, then extract each product page.
	 *
	 * @param array $s          Settings.
	 * @param array $categories Category URLs.
	 * @return array|WP_Error
	 */
	private function run_crawl( $s, $categories ) {
		$cookie       = trim( $s['session_cookie'] );
		$product_urls = array();

		foreach ( $categories as $cat ) {
			$links        = $this->collect_product_links( $cat, $cookie, $s );
			$product_urls = array_merge( $product_urls, $links );
		}

		$product_urls = array_values( array_unique( $product_urls ) );

		if ( empty( $product_urls ) ) {
			return new WP_Error( 'gsfm_no_products', __( 'No product links found. Check the category URLs, the product link pattern, and that your session cookie is valid.', 'gym-store-for-members' ) );
		}

		$new     = 0;
		$updated = 0;
		$skipped = 0;

		foreach ( $product_urls as $url ) {
			$html = $this->fetch( $url, $cookie );
			if ( is_wp_error( $html ) ) {
				$skipped++;
				continue;
			}

			$data = $this->extract_product( $html, $url, $s );
			if ( empty( $data ) || '' === $data['title'] ) {
				$skipped++;
				continue;
			}

			$before = GSFM_Products::get_by_ref( $data['supplier_ref'] );
			GSFM_Products::upsert( $data );
			if ( $before ) {
				$updated++;
			} else {
				$new++;
			}
		}

		return array(
			'seen'    => count( $product_urls ),
			'new'     => $new,
			'updated' => $updated,
			'skipped' => $skipped,
		);
	}

	/**
	 * Discover product links across a category's paginated pages.
	 *
	 * @param string $category_url Category URL.
	 * @param string $cookie       Session cookie header.
	 * @param array  $s            Settings.
	 * @return array
	 */
	private function collect_product_links( $category_url, $cookie, $s ) {
		$pattern = $s['product_link_pattern'];
		$max     = max( 1, (int) $s['max_pages'] );
		$links   = array();
		$visited = array();
		$queue   = array( $category_url );

		while ( ! empty( $queue ) && count( $visited ) < $max ) {
			$url = array_shift( $queue );
			if ( isset( $visited[ $url ] ) ) {
				continue;
			}
			$visited[ $url ] = true;

			$html = $this->fetch( $url, $cookie );
			if ( is_wp_error( $html ) ) {
				continue;
			}

			foreach ( $this->find_links( $html, $url ) as $href ) {
				if ( false !== strpos( $href, $pattern ) ) {
					$links[ $href ] = true;
				}
			}

			$next = $this->find_next_page( $html, $url );
			if ( $next && ! isset( $visited[ $next ] ) ) {
				$queue[] = $next;
			}
		}

		return array_keys( $links );
	}

	/**
	 * Extract a product using structured sources first, then fallbacks.
	 *
	 * @param string $html Product page HTML.
	 * @param string $url  Product URL (stable reference).
	 * @param array  $s    Settings.
	 * @return array|null
	 */
	private function extract_product( $html, $url, $s ) {
		$data = $this->extract_jsonld( $html );

		if ( empty( $data ) ) {
			$data = $this->extract_opengraph( $html );
		}

		if ( empty( $data ) && ! empty( $s['use_ai'] ) ) {
			$data = $this->extract_ai( $html, $s );
		}

		if ( empty( $data ) ) {
			return null;
		}

		// Prefer SKU as the stable key, else the product URL.
		$data['supplier_ref'] = ! empty( $data['sku'] ) ? $data['sku'] : $url;
		return $data;
	}

	/**
	 * Extract from schema.org JSON-LD Product data.
	 *
	 * @param string $html HTML.
	 * @return array|null
	 */
	private function extract_jsonld( $html ) {
		$doc = $this->dom( $html );
		if ( ! $doc ) {
			return null;
		}
		$xpath = new DOMXPath( $doc );
		$nodes = $xpath->query( "//script[@type='application/ld+json']" );
		if ( ! $nodes ) {
			return null;
		}

		foreach ( $nodes as $node ) {
			$json = json_decode( trim( $node->textContent ), true );
			if ( null === $json ) {
				continue;
			}
			$product = $this->find_product_node( $json );
			if ( $product ) {
				return $this->normalize_jsonld( $product );
			}
		}

		return null;
	}

	/**
	 * Recursively locate a Product node within decoded JSON-LD.
	 *
	 * @param mixed $json Decoded JSON.
	 * @return array|null
	 */
	private function find_product_node( $json ) {
		if ( ! is_array( $json ) ) {
			return null;
		}

		if ( isset( $json['@type'] ) ) {
			$type = is_array( $json['@type'] ) ? $json['@type'] : array( $json['@type'] );
			if ( in_array( 'Product', $type, true ) ) {
				return $json;
			}
		}

		if ( isset( $json['@graph'] ) && is_array( $json['@graph'] ) ) {
			foreach ( $json['@graph'] as $item ) {
				$found = $this->find_product_node( $item );
				if ( $found ) {
					return $found;
				}
			}
		}

		// A bare list of nodes.
		foreach ( $json as $item ) {
			if ( is_array( $item ) ) {
				$found = $this->find_product_node( $item );
				if ( $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * Normalize a JSON-LD Product node to our product shape.
	 *
	 * @param array $p Product node.
	 * @return array
	 */
	private function normalize_jsonld( $p ) {
		$title = isset( $p['name'] ) ? (string) $p['name'] : '';

		$image = '';
		if ( isset( $p['image'] ) ) {
			$img = $p['image'];
			if ( is_array( $img ) ) {
				$img = isset( $img['url'] ) ? $img['url'] : reset( $img );
			}
			if ( is_array( $img ) ) {
				$img = isset( $img['url'] ) ? $img['url'] : '';
			}
			$image = (string) $img;
		}

		$price     = 0.0;
		$in_stock  = true;
		if ( isset( $p['offers'] ) ) {
			$offers = $p['offers'];
			if ( isset( $offers[0] ) ) {
				$offers = $offers[0];
			}
			if ( isset( $offers['price'] ) ) {
				$price = $this->parse_price( (string) $offers['price'] );
			} elseif ( isset( $offers['lowPrice'] ) ) {
				$price = $this->parse_price( (string) $offers['lowPrice'] );
			}
			if ( isset( $offers['availability'] ) ) {
				$in_stock = false !== stripos( (string) $offers['availability'], 'InStock' );
			}
		}

		return array(
			'title'          => trim( $title ),
			'image_url'      => $image,
			'supplier_price' => $price,
			'in_stock'       => $in_stock,
			'sku'            => isset( $p['sku'] ) ? (string) $p['sku'] : '',
		);
	}

	/**
	 * Extract from OpenGraph / product meta tags.
	 *
	 * @param string $html HTML.
	 * @return array|null
	 */
	private function extract_opengraph( $html ) {
		$doc = $this->dom( $html );
		if ( ! $doc ) {
			return null;
		}
		$xpath = new DOMXPath( $doc );

		$meta = function ( $prop ) use ( $xpath ) {
			$n = $xpath->query( "//meta[@property=" . $this->xpath_literal( $prop ) . "]/@content | //meta[@name=" . $this->xpath_literal( $prop ) . "]/@content" );
			return ( $n && $n->length ) ? trim( $n->item( 0 )->nodeValue ) : '';
		};

		$title = $meta( 'og:title' );
		if ( '' === $title ) {
			return null;
		}

		$availability = $meta( 'product:availability' );
		if ( '' === $availability ) {
			$availability = $meta( 'og:availability' );
		}

		return array(
			'title'          => $title,
			'image_url'      => $meta( 'og:image' ),
			'supplier_price' => $this->parse_price( $meta( 'product:price:amount' ) ),
			'in_stock'       => '' === $availability ? true : false === stripos( $availability, 'out' ),
			'sku'            => $meta( 'product:retailer_item_id' ),
		);
	}

	/**
	 * Optional OpenAI fallback extraction.
	 *
	 * @param string $html HTML.
	 * @param array  $s    Settings.
	 * @return array|null
	 */
	private function extract_ai( $html, $s ) {
		$key = self::decrypt( $s['openai_key_enc'] );
		if ( '' === $key ) {
			return null;
		}

		$text = $this->readable_text( $html );
		if ( '' === $text ) {
			return null;
		}

		$body = array(
			'model'           => $s['openai_model'] ? $s['openai_model'] : 'gpt-4o-mini',
			'temperature'     => 0,
			'response_format' => array( 'type' => 'json_object' ),
			'messages'        => array(
				array(
					'role'    => 'system',
					'content' => 'Extract product data from the page text. Reply ONLY with JSON: {"title": string, "price": number, "currency": string, "image_url": string, "in_stock": boolean, "sku": string}. Use the price shown on the page; if none is visible, set price to 0.',
				),
				array(
					'role'    => 'user',
					'content' => $text,
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
			return null;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $decoded['choices'][0]['message']['content'] ) ) {
			return null;
		}

		$parsed = json_decode( $decoded['choices'][0]['message']['content'], true );
		if ( empty( $parsed['title'] ) ) {
			return null;
		}

		return array(
			'title'          => (string) $parsed['title'],
			'image_url'      => isset( $parsed['image_url'] ) ? (string) $parsed['image_url'] : '',
			'supplier_price' => isset( $parsed['price'] ) ? (float) $parsed['price'] : 0,
			'in_stock'       => isset( $parsed['in_stock'] ) ? (bool) $parsed['in_stock'] : true,
			'sku'            => isset( $parsed['sku'] ) ? (string) $parsed['sku'] : '',
		);
	}

	/**
	 * Legacy login + listing XPath scrape.
	 *
	 * @param array $s Settings.
	 * @return array|WP_Error
	 */
	private function run_legacy( $s ) {
		if ( empty( $s['listing_url'] ) || empty( $s['xpath_item'] ) ) {
			return new WP_Error( 'gsfm_config', __( 'Add category URLs (recommended) or configure the legacy listing URL and selectors.', 'gym-store-for-members' ) );
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
			$html = $this->fetch( $url, '', $cookies );
			if ( is_wp_error( $html ) ) {
				return $html;
			}

			foreach ( $this->parse_listing( $html, $s ) as $item ) {
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
			'skipped' => 0,
		);
	}

	/**
	 * Two-step programmatic login (legacy path).
	 *
	 * @param array $s Settings.
	 * @return array|WP_Error
	 */
	private function login( $s ) {
		$password = self::decrypt( $s['password_enc'] );

		$get = wp_remote_get(
			$s['login_url'],
			array(
				'timeout'     => 30,
				'redirection' => 5,
				'user-agent'  => self::UA,
			)
		);
		if ( is_wp_error( $get ) ) {
			return $get;
		}

		$get_cookies = wp_remote_retrieve_cookies( $get );
		$fields      = $this->harvest_login_fields( wp_remote_retrieve_body( $get ), $s['password_field'] );

		$fields[ $s['username_field'] ] = $s['username'];
		$fields[ $s['password_field'] ] = $password;

		$response = wp_remote_post(
			$s['login_url'],
			array(
				'timeout'     => 30,
				'redirection' => 5,
				'cookies'     => $get_cookies,
				'user-agent'  => self::UA,
				'body'        => $fields,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$cookies = $this->merge_cookies( $get_cookies, wp_remote_retrieve_cookies( $response ) );
		if ( empty( $cookies ) ) {
			return new WP_Error( 'gsfm_login', __( 'Login returned no session cookies. Check the login URL and field names.', 'gym-store-for-members' ) );
		}

		return $cookies;
	}

	/**
	 * Collect input/button fields from the form containing the password field.
	 *
	 * @param string $html           HTML.
	 * @param string $password_field Password field name.
	 * @return array
	 */
	private function harvest_login_fields( $html, $password_field ) {
		$fields = array();
		$doc    = $this->dom( $html );
		if ( ! $doc ) {
			return $fields;
		}

		$xpath = new DOMXPath( $doc );
		$forms = $xpath->query( '//form' );
		if ( ! $forms ) {
			return $fields;
		}

		$target = null;
		foreach ( $forms as $form ) {
			$pw = $xpath->query( ".//input[@name=" . $this->xpath_literal( $password_field ) . "]", $form );
			if ( $pw && $pw->length ) {
				$target = $form;
				break;
			}
		}
		if ( ! $target ) {
			return $fields;
		}

		$inputs = $xpath->query( './/input | .//button', $target );
		foreach ( $inputs as $input ) {
			if ( ! $input instanceof DOMElement ) {
				continue;
			}
			$name = $input->getAttribute( 'name' );
			if ( '' === $name ) {
				continue;
			}
			$type = strtolower( $input->getAttribute( 'type' ) );
			if ( 'checkbox' === $type && ! $input->hasAttribute( 'checked' ) ) {
				continue;
			}
			$fields[ $name ] = $input->getAttribute( 'value' );
		}

		return $fields;
	}

	/**
	 * Parse a WooCommerce listing page (legacy).
	 *
	 * @param string $html HTML.
	 * @param array  $s    Settings.
	 * @return array
	 */
	private function parse_listing( $html, $s ) {
		$items = array();
		$doc   = $this->dom( $html );
		if ( ! $doc ) {
			return $items;
		}

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
	 * @param DOMNode  $node       Product node.
	 * @param array    $s          Settings.
	 * @param string   $item_class Class attribute.
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
	 * Resolve a stable ref: SKU text, WooCommerce post ID, then title hash.
	 *
	 * @param string $ref        Ref text.
	 * @param string $item_class Class attribute.
	 * @param string $title      Title.
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
	 * Fetch a URL with an optional raw cookie header or WP cookie objects.
	 *
	 * @param string $url        URL.
	 * @param string $cookie     Raw Cookie header value.
	 * @param array  $wp_cookies WP cookie objects (legacy login).
	 * @return string|WP_Error
	 */
	private function fetch( $url, $cookie = '', $wp_cookies = array() ) {
		$args = array(
			'timeout'     => 30,
			'redirection' => 5,
			'user-agent'  => self::UA,
		);
		if ( '' !== $cookie ) {
			$args['headers'] = array( 'Cookie' => $cookie );
		}
		if ( ! empty( $wp_cookies ) ) {
			$args['cookies'] = $wp_cookies;
		}

		$response = wp_remote_get( $url, $args );
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
	 * All absolute link hrefs on a page.
	 *
	 * @param string $html HTML.
	 * @param string $base Base URL for resolving relatives.
	 * @return array
	 */
	private function find_links( $html, $base ) {
		$out = array();
		$doc = $this->dom( $html );
		if ( ! $doc ) {
			return $out;
		}
		$xpath = new DOMXPath( $doc );
		$nodes = $xpath->query( '//a[@href]' );
		if ( ! $nodes ) {
			return $out;
		}
		foreach ( $nodes as $node ) {
			$href = $this->absolute_url( trim( $node->getAttribute( 'href' ) ), $base );
			if ( '' !== $href ) {
				$out[] = $href;
			}
		}
		return $out;
	}

	/**
	 * Find a "next page" link on a listing page.
	 *
	 * @param string $html HTML.
	 * @param string $base Base URL.
	 * @return string
	 */
	private function find_next_page( $html, $base ) {
		$doc = $this->dom( $html );
		if ( ! $doc ) {
			return '';
		}
		$xpath = new DOMXPath( $doc );
		$q     = "//a[@rel='next']/@href | //link[@rel='next']/@href | //a[contains(@class,'next')]/@href | //li[contains(@class,'next')]/a/@href";
		$nodes = $xpath->query( $q );
		if ( $nodes && $nodes->length ) {
			return $this->absolute_url( trim( $nodes->item( 0 )->nodeValue ), $base );
		}
		return '';
	}

	/**
	 * Resolve a possibly-relative URL against a base.
	 *
	 * @param string $href Href.
	 * @param string $base Base URL.
	 * @return string
	 */
	private function absolute_url( $href, $base ) {
		if ( '' === $href || 0 === strpos( $href, '#' ) || 0 === stripos( $href, 'javascript:' ) || 0 === stripos( $href, 'mailto:' ) ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $href ) ) {
			return $href;
		}

		$parts = wp_parse_url( $base );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		$origin = $parts['scheme'] . '://' . $parts['host'];

		if ( 0 === strpos( $href, '//' ) ) {
			return $parts['scheme'] . ':' . $href;
		}
		if ( 0 === strpos( $href, '/' ) ) {
			return $origin . $href;
		}

		$path = isset( $parts['path'] ) ? preg_replace( '#/[^/]*$#', '/', $parts['path'] ) : '/';
		return $origin . $path . $href;
	}

	/**
	 * Build a paginated URL (legacy).
	 *
	 * @param string $base  Base URL.
	 * @param string $param Query parameter.
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
	 * Load HTML into a DOMDocument.
	 *
	 * @param string $html HTML.
	 * @return DOMDocument|null
	 */
	private function dom( $html ) {
		if ( '' === trim( (string) $html ) ) {
			return null;
		}
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		libxml_clear_errors();
		return $doc;
	}

	/**
	 * Trimmed readable text of the page body for AI input.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private function readable_text( $html ) {
		$html = preg_replace( '#<(script|style|noscript)[^>]*>.*?</\1>#is', ' ', $html );
		$text = wp_strip_all_tags( $html );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( (string) $text );
		return mb_substr( $text, 0, 6000 );
	}

	/**
	 * Extract trimmed text relative to a node.
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
	 * Extract an image URL relative to a node (handles lazy-load + srcset).
	 *
	 * @param DOMXPath $xpath   XPath.
	 * @param DOMNode  $context Context node.
	 * @param string   $query   Relative XPath.
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

		foreach ( array( 'data-nectar-img-src', 'data-src', 'data-lazy-src', 'src' ) as $attr ) {
			if ( $el->hasAttribute( $attr ) ) {
				$val = trim( $el->getAttribute( $attr ) );
				if ( '' !== $val && 0 !== strpos( $val, 'data:' ) ) {
					return $val;
				}
			}
		}

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
		$clean = preg_replace( '/[^0-9,.]/', '', (string) $raw );
		$clean = str_replace( ',', '.', $clean );
		if ( '' === $clean ) {
			return 0.0;
		}
		$parts = explode( '.', $clean );
		if ( count( $parts ) > 2 ) {
			$dec   = array_pop( $parts );
			$clean = implode( '', $parts ) . '.' . $dec;
		}
		return (float) $clean;
	}

	/**
	 * Split a textarea into trimmed non-empty lines.
	 *
	 * @param string $text Text.
	 * @return array
	 */
	private function lines( $text ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
		return array_values( array_filter( array_map( 'trim', $lines ) ) );
	}

	/**
	 * Merge two cookie arrays, later winning by name.
	 *
	 * @param array $a First set.
	 * @param array $b Second set.
	 * @return array
	 */
	private function merge_cookies( $a, $b ) {
		$merged = array();
		foreach ( array_merge( (array) $a, (array) $b ) as $cookie ) {
			if ( is_object( $cookie ) && isset( $cookie->name ) ) {
				$merged[ $cookie->name ] = $cookie;
			}
		}
		return array_values( $merged );
	}

	/**
	 * Escape a string for safe use as an XPath literal.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function xpath_literal( $value ) {
		if ( false === strpos( $value, "'" ) ) {
			return "'" . $value . "'";
		}
		if ( false === strpos( $value, '"' ) ) {
			return '"' . $value . '"';
		}
		return "concat('" . str_replace( "'", "',\"'\",'", $value ) . "')";
	}
}
