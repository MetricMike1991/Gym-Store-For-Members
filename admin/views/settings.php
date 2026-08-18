<?php
/**
 * Admin: scraper settings (credentials + selectors).
 *
 * @package GymStoreForMembers
 * @var array $s
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Scraper Settings', 'gym-store-for-members' ); ?></h1>

	<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'gym-store-for-members' ); ?></p></div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'gsfm_settings' ); ?>

		<h2><?php esc_html_e( 'Supplier Login', 'gym-store-for-members' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="login_url"><?php esc_html_e( 'Login URL', 'gym-store-for-members' ); ?></label></th>
				<td><input name="login_url" id="login_url" type="url" class="regular-text" value="<?php echo esc_attr( $s['login_url'] ); ?>" placeholder="https://protaminonutrition.com/my-account/" /></td>
			</tr>
			<tr>
				<th><label for="username_field"><?php esc_html_e( 'Username field name', 'gym-store-for-members' ); ?></label></th>
				<td><input name="username_field" id="username_field" type="text" class="regular-text" value="<?php echo esc_attr( $s['username_field'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="password_field"><?php esc_html_e( 'Password field name', 'gym-store-for-members' ); ?></label></th>
				<td><input name="password_field" id="password_field" type="text" class="regular-text" value="<?php echo esc_attr( $s['password_field'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="username"><?php esc_html_e( 'Username', 'gym-store-for-members' ); ?></label></th>
				<td><input name="username" id="username" type="text" class="regular-text" value="<?php echo esc_attr( $s['username'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="password"><?php esc_html_e( 'Password', 'gym-store-for-members' ); ?></label></th>
				<td>
					<input name="password" id="password" type="password" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo $s['password_enc'] ? esc_attr__( '•••••• (saved — leave blank to keep)', 'gym-store-for-members' ) : ''; ?>" />
					<p class="description"><?php esc_html_e( 'Stored AES-256 encrypted. Leave blank to keep the current password.', 'gym-store-for-members' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Product Listing', 'gym-store-for-members' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="listing_url"><?php esc_html_e( 'Listing URL', 'gym-store-for-members' ); ?></label></th>
				<td><input name="listing_url" id="listing_url" type="url" class="regular-text" value="<?php echo esc_attr( $s['listing_url'] ); ?>" placeholder="https://protaminonutrition.com/shop/" /></td>
			</tr>
			<tr>
				<th><label for="pages"><?php esc_html_e( 'Pages to scrape', 'gym-store-for-members' ); ?></label></th>
				<td><input name="pages" id="pages" type="number" min="1" value="<?php echo esc_attr( $s['pages'] ); ?>" style="width:80px;" /></td>
			</tr>
			<tr>
				<th><label for="page_param"><?php esc_html_e( 'Page query parameter', 'gym-store-for-members' ); ?></label></th>
				<td><input name="page_param" id="page_param" type="text" value="<?php echo esc_attr( $s['page_param'] ); ?>" /> <span class="description"><?php esc_html_e( 'e.g. "page" for ?page=2', 'gym-store-for-members' ); ?></span></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'XPath Selectors', 'gym-store-for-members' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Inspect the supplier listing page (DevTools) to fill these. "Item" is the repeating product container; the rest are relative to it.', 'gym-store-for-members' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="xpath_item"><?php esc_html_e( 'Product item', 'gym-store-for-members' ); ?></label></th>
				<td><input name="xpath_item" id="xpath_item" type="text" class="large-text code" value="<?php echo esc_attr( $s['xpath_item'] ); ?>" placeholder="//li[contains(@class,'product')]" /></td>
			</tr>
			<tr>
				<th><label for="xpath_title"><?php esc_html_e( 'Title (relative)', 'gym-store-for-members' ); ?></label></th>
				<td><input name="xpath_title" id="xpath_title" type="text" class="large-text code" value="<?php echo esc_attr( $s['xpath_title'] ); ?>" placeholder=".//h2" /></td>
			</tr>
			<tr>
				<th><label for="xpath_image"><?php esc_html_e( 'Image (relative)', 'gym-store-for-members' ); ?></label></th>
				<td><input name="xpath_image" id="xpath_image" type="text" class="large-text code" value="<?php echo esc_attr( $s['xpath_image'] ); ?>" placeholder=".//img" /></td>
			</tr>
			<tr>
				<th><label for="xpath_price"><?php esc_html_e( 'Price (relative)', 'gym-store-for-members' ); ?></label></th>
				<td><input name="xpath_price" id="xpath_price" type="text" class="large-text code" value="<?php echo esc_attr( $s['xpath_price'] ); ?>" placeholder=".//span[contains(@class,'price')]" /></td>
			</tr>
			<tr>
				<th><label for="xpath_stock"><?php esc_html_e( 'Stock text (relative)', 'gym-store-for-members' ); ?></label></th>
				<td><input name="xpath_stock" id="xpath_stock" type="text" class="large-text code" value="<?php echo esc_attr( $s['xpath_stock'] ); ?>" placeholder=".//p[contains(@class,'stock')]" /></td>
			</tr>
			<tr>
				<th><label for="xpath_ref"><?php esc_html_e( 'SKU / ref (relative)', 'gym-store-for-members' ); ?></label></th>
				<td><input name="xpath_ref" id="xpath_ref" type="text" class="large-text code" value="<?php echo esc_attr( $s['xpath_ref'] ); ?>" placeholder=".//span[contains(@class,'sku')]" /></td>
			</tr>
			<tr>
				<th><label for="in_stock_text"><?php esc_html_e( '"In stock" text match', 'gym-store-for-members' ); ?></label></th>
				<td><input name="in_stock_text" id="in_stock_text" type="text" class="regular-text" value="<?php echo esc_attr( $s['in_stock_text'] ); ?>" /> <span class="description"><?php esc_html_e( 'Product counts as in stock if the stock text contains this. Leave blank to treat all as in stock.', 'gym-store-for-members' ); ?></span></td>
			</tr>
		</table>

		<p><button class="button button-primary" name="gsfm_save_settings" value="1"><?php esc_html_e( 'Save Settings', 'gym-store-for-members' ); ?></button></p>
	</form>
</div>
