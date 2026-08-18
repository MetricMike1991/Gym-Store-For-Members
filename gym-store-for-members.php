<?php
/**
 * Plugin Name:       Gym Store For Members
 * Description:       Scrape wholesale supplement products from a supplier and let gym members request items for the next order. Admin exports requests as CSV.
 * Version:           1.4.0
 * Author:            MetricMike1991
 * License:           GPL-2.0-or-later
 * Text Domain:       gym-store-for-members
 * Requires PHP:      7.4
 * Requires at least: 5.8
 *
 * @package GymStoreForMembers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GSFM_VERSION', '1.4.0' );
define( 'GSFM_FILE', __FILE__ );
define( 'GSFM_DIR', plugin_dir_path( __FILE__ ) );
define( 'GSFM_URL', plugin_dir_url( __FILE__ ) );
define( 'GSFM_TABLE_PRODUCTS', 'gss_products' );
define( 'GSFM_TABLE_WISHLIST', 'gss_wishlist' );

require_once GSFM_DIR . 'includes/class-database.php';
require_once GSFM_DIR . 'includes/class-products.php';
require_once GSFM_DIR . 'includes/class-wishlist.php';
require_once GSFM_DIR . 'includes/class-scraper.php';
require_once GSFM_DIR . 'includes/class-export.php';
require_once GSFM_DIR . 'admin/class-admin.php';
require_once GSFM_DIR . 'public/class-public.php';

register_activation_hook( __FILE__, array( 'GSFM_Database', 'install' ) );

/**
 * Boot the plugin once all plugins are loaded.
 */
function gsfm_bootstrap() {
	GSFM_Database::maybe_upgrade();
	if ( is_admin() ) {
		( new GSFM_Admin() )->init();
	}
	( new GSFM_Public() )->init();
}
add_action( 'plugins_loaded', 'gsfm_bootstrap' );
