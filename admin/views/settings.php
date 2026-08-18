<?php
/**
 * Admin: scraper settings.
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

		<h2><?php esc_html_e( '1. Session (manual login)', 'gym-store-for-members' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Log into the supplier in your browser, then paste your session cookie so the scraper acts as you. Open DevTools (F12) → Application/Storage → Cookies → copy the cookie value(s), or copy the full Cookie request header from the Network tab.', 'gym-store-for-members' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="session_cookie"><?php esc_html_e( 'Session cookie', 'gym-store-for-members' ); ?></label></th>
				<td>
					<textarea name="session_cookie" id="session_cookie" rows="3" class="large-text code" placeholder="name1=value1; name2=value2"><?php echo esc_textarea( $s['session_cookie'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Format: name=value; name2=value2 — exactly as sent in the Cookie header. Refresh this if scrapes start failing (cookies expire).', 'gym-store-for-members' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( '2. Category URLs to crawl', 'gym-store-for-members' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="category_urls"><?php esc_html_e( 'Category URLs', 'gym-store-for-members' ); ?></label></th>
				<td>
					<textarea name="category_urls" id="category_urls" rows="8" class="large-text code" placeholder="Amino Acids | https://protaminonutrition.com/product-category/amino-acids/&#10;Creatine | https://protaminonutrition.com/product-category/creatine/&#10;Energy Drinks | https://protaminonutrition.com/product-category/energy-drinks/"><?php echo esc_textarea( $s['category_urls'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One category per line. Format: Category Name | URL — or just the URL (name is inferred from it). The shop will show a category grid using these names.', 'gym-store-for-members' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="product_link_pattern"><?php esc_html_e( 'Product link pattern', 'gym-store-for-members' ); ?></label></th>
				<td><input name="product_link_pattern" id="product_link_pattern" type="text" class="regular-text code" value="<?php echo esc_attr( $s['product_link_pattern'] ); ?>" /> <span class="description"><?php esc_html_e( 'A link is treated as a product if its URL contains this text (default /product/).', 'gym-store-for-members' ); ?></span></td>
			</tr>
			<tr>
				<th><label for="max_pages"><?php esc_html_e( 'Max pages per category', 'gym-store-for-members' ); ?></label></th>
				<td><input name="max_pages" id="max_pages" type="number" min="1" value="<?php echo esc_attr( $s['max_pages'] ); ?>" style="width:80px;" /></td>
			</tr>
		</table>

		<h2><?php esc_html_e( '2b. Category Images (optional)', 'gym-store-for-members' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Set a custom thumbnail for each category. Leave blank to use a product image from that category automatically.', 'gym-store-for-members' ); ?></p>

		<?php
		$parsed_cats = GSFM_Scraper::parse_category_lines( $s['category_urls'] );
		$cat_images  = get_option( 'gsfm_cat_images', array() );
		if ( ! empty( $parsed_cats ) ) :
		?>
		<table class="form-table" role="presentation">
			<?php foreach ( $parsed_cats as $cat ) : ?>
				<?php $current_img = isset( $cat_images[ $cat['slug'] ] ) ? $cat_images[ $cat['slug'] ] : ''; ?>
				<tr>
					<th><?php echo esc_html( $cat['name'] ); ?></th>
					<td>
						<div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
							<div class="gsfm-cat-preview" style="width:80px;height:80px;border:1px solid #ddd;border-radius:6px;overflow:hidden;background:#f5f5f5;display:flex;align-items:center;justify-content:center;">
								<?php if ( $current_img ) : ?>
									<img src="<?php echo esc_url( $current_img ); ?>" style="width:100%;height:80px;object-fit:cover;display:block;" />
								<?php else : ?>
									<span style="color:#aaa;font-size:11px;"><?php esc_html_e( 'None', 'gym-store-for-members' ); ?></span>
								<?php endif; ?>
							</div>
							<div>
								<input type="hidden" name="cat_image[<?php echo esc_attr( $cat['slug'] ); ?>]"
									   class="gsfm-cat-img-url"
									   value="<?php echo esc_attr( $current_img ); ?>" />
								<button type="button" class="button gsfm-cat-img-pick"><?php esc_html_e( 'Choose Image', 'gym-store-for-members' ); ?></button>
								<?php if ( $current_img ) : ?>
									<button type="button" class="button gsfm-cat-img-remove" style="margin-left:6px;"><?php esc_html_e( 'Remove', 'gym-store-for-members' ); ?></button>
								<?php endif; ?>
							</div>
						</div>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php else : ?>
		<p class="description"><?php esc_html_e( 'Save your category URLs above first, then image pickers will appear here.', 'gym-store-for-members' ); ?></p>
		<?php endif; ?>

		<script>
		(function() {
			document.querySelectorAll('.gsfm-cat-img-pick').forEach(function(btn) {
				btn.addEventListener('click', function() {
					var row    = btn.closest('td');
					var input  = row.querySelector('.gsfm-cat-img-url');
					var preview = row.querySelector('.gsfm-cat-preview');
					var frame  = wp.media({ title: 'Choose Category Image', button: { text: 'Use this image' }, multiple: false });
					frame.on('select', function() {
						var att = frame.state().get('selection').first().toJSON();
						input.value = att.url;
						preview.innerHTML = '<img src="' + att.url + '" style="width:100%;height:80px;object-fit:cover;display:block;" />';
					});
					frame.open();
				});
			});
			document.querySelectorAll('.gsfm-cat-img-remove').forEach(function(btn) {
				btn.addEventListener('click', function() {
					var row    = btn.closest('td');
					var input  = row.querySelector('.gsfm-cat-img-url');
					var preview = row.querySelector('.gsfm-cat-preview');
					input.value = '';
					preview.innerHTML = '<span style="color:#aaa;font-size:11px;">None</span>';
					btn.style.display = 'none';
				});
			});
		})();
		</script>

		<h2><?php esc_html_e( '3. AI fallback (optional)', 'gym-store-for-members' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Structured data (JSON-LD / OpenGraph) is used automatically and needs no AI. Enable this only for suppliers whose product pages have no structured data.', 'gym-store-for-members' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Enable AI fallback', 'gym-store-for-members' ); ?></th>
				<td><label><input type="checkbox" name="use_ai" value="1" <?php checked( (int) $s['use_ai'], 1 ); ?> /> <?php esc_html_e( 'Use OpenAI when structured data is missing', 'gym-store-for-members' ); ?></label></td>
			</tr>
			<tr>
				<th><label for="openai_key"><?php esc_html_e( 'OpenAI API key', 'gym-store-for-members' ); ?></label></th>
				<td>
					<input name="openai_key" id="openai_key" type="password" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo $s['openai_key_enc'] ? esc_attr__( '•••••• (saved — leave blank to keep)', 'gym-store-for-members' ) : 'sk-...'; ?>" />
					<p class="description"><?php esc_html_e( 'Stored AES-256 encrypted. Leave blank to keep the current key.', 'gym-store-for-members' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="openai_model"><?php esc_html_e( 'Model', 'gym-store-for-members' ); ?></label></th>
				<td><input name="openai_model" id="openai_model" type="text" class="regular-text" value="<?php echo esc_attr( $s['openai_model'] ); ?>" /></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Legacy: login + listing scrape (fallback)', 'gym-store-for-members' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Only used when no category URLs are set above. Programmatic login and listing-page XPath selectors.', 'gym-store-for-members' ); ?></p>
		<details>
			<summary style="cursor:pointer;margin:8px 0;"><?php esc_html_e( 'Show legacy settings', 'gym-store-for-members' ); ?></summary>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="login_url"><?php esc_html_e( 'Login URL', 'gym-store-for-members' ); ?></label></th>
					<td><input name="login_url" id="login_url" type="url" class="regular-text" value="<?php echo esc_attr( $s['login_url'] ); ?>" /></td>
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
					<td><input name="password" id="password" type="password" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo $s['password_enc'] ? esc_attr__( '•••••• (saved)', 'gym-store-for-members' ) : ''; ?>" /></td>
				</tr>
				<tr>
					<th><label for="listing_url"><?php esc_html_e( 'Listing URL', 'gym-store-for-members' ); ?></label></th>
					<td><input name="listing_url" id="listing_url" type="url" class="regular-text" value="<?php echo esc_attr( $s['listing_url'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="pages"><?php esc_html_e( 'Pages to scrape', 'gym-store-for-members' ); ?></label></th>
					<td><input name="pages" id="pages" type="number" min="1" value="<?php echo esc_attr( $s['pages'] ); ?>" style="width:80px;" /></td>
				</tr>
				<tr>
					<th><label for="page_param"><?php esc_html_e( 'Page query parameter', 'gym-store-for-members' ); ?></label></th>
					<td><input name="page_param" id="page_param" type="text" value="<?php echo esc_attr( $s['page_param'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="xpath_item"><?php esc_html_e( 'Product item XPath', 'gym-store-for-members' ); ?></label></th>
					<td><input name="xpath_item" id="xpath_item" type="text" class="large-text code" value="<?php echo esc_attr( $s['xpath_item'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="xpath_title"><?php esc_html_e( 'Title XPath', 'gym-store-for-members' ); ?></label></th>
					<td><input name="xpath_title" id="xpath_title" type="text" class="large-text code" value="<?php echo esc_attr( $s['xpath_title'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="xpath_image"><?php esc_html_e( 'Image XPath', 'gym-store-for-members' ); ?></label></th>
					<td><input name="xpath_image" id="xpath_image" type="text" class="large-text code" value="<?php echo esc_attr( $s['xpath_image'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="xpath_price"><?php esc_html_e( 'Price XPath', 'gym-store-for-members' ); ?></label></th>
					<td><input name="xpath_price" id="xpath_price" type="text" class="large-text code" value="<?php echo esc_attr( $s['xpath_price'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="xpath_stock"><?php esc_html_e( 'Stock XPath', 'gym-store-for-members' ); ?></label></th>
					<td><input name="xpath_stock" id="xpath_stock" type="text" class="large-text code" value="<?php echo esc_attr( $s['xpath_stock'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="xpath_ref"><?php esc_html_e( 'SKU/ref XPath', 'gym-store-for-members' ); ?></label></th>
					<td><input name="xpath_ref" id="xpath_ref" type="text" class="large-text code" value="<?php echo esc_attr( $s['xpath_ref'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="in_stock_text"><?php esc_html_e( '"In stock" text match', 'gym-store-for-members' ); ?></label></th>
					<td><input name="in_stock_text" id="in_stock_text" type="text" class="regular-text" value="<?php echo esc_attr( $s['in_stock_text'] ); ?>" /></td>
				</tr>
			</table>
		</details>

		<p>
			<button class="button button-primary" name="gsfm_save_settings" value="1"><?php esc_html_e( 'Save Settings', 'gym-store-for-members' ); ?></button>
			<button type="button" id="gsfm-test-btn" class="button" style="margin-left:8px;"
				data-ajax="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>"
				data-nonce="<?php echo esc_attr( wp_create_nonce( 'gsfm_admin' ) ); ?>">
				<?php esc_html_e( 'Test Connection', 'gym-store-for-members' ); ?>
			</button>
		</p>
	</form>

	<div id="gsfm-test-result" style="display:none;margin-top:16px;padding:14px 18px;border-radius:6px;max-width:680px;font-family:monospace;font-size:13px;line-height:1.7;background:#f0f0f1;border:1px solid #c3c4c7;"></div>

	<script>
	(function() {
		var btn = document.getElementById('gsfm-test-btn');
		var out = document.getElementById('gsfm-test-result');
		if (!btn || !out) { return; }
		btn.addEventListener('click', function() {
			out.style.display = 'block';
			out.style.color   = '#555';
			out.innerHTML     = 'Fetching&hellip;';
			var fd = new FormData();
			fd.append('action', 'gsfm_test_connection');
			fd.append('nonce',  btn.dataset.nonce);
			fetch(btn.dataset.ajax, { method: 'POST', credentials: 'same-origin', body: fd })
				.then(function(r) { return r.json(); })
				.then(function(res) {
					if (!res || !res.success) {
						out.style.color = '#b32d2e';
						out.textContent = (res && res.data && res.data.message) || 'Test failed.';
						return;
					}
					var d = res.data;
					var lines = [
						'URL:           ' + d.url,
						'HTTP status:   ' + d.http_status,
						'Page title:    ' + d.page_title,
						'Cookie set:    ' + (d.cookie_set ? 'Yes' : 'No — paste session cookie first'),
						'Login wall:    ' + (d.login_wall ? '⚠ YES — cookie not accepted or expired' : 'No ✓'),
						'Links found:   ' + d.total_links,
						'Product links: ' + d.product_links + (d.product_links === 0 ? '  ← check product link pattern' : ' ✓'),
					];
					if (d.sample_products && d.sample_products.length) {
						lines.push('');
						lines.push('Sample product URLs:');
						d.sample_products.forEach(function(u) { lines.push('  ' + u); });
					}
					out.style.color = (!d.login_wall && d.product_links > 0) ? '#1a7f37' : '#7a5c00';
					out.innerHTML   = lines.join('<br>');
				})
				.catch(function(e) {
					out.style.color = '#b32d2e';
					out.textContent = 'Network error: ' + e;
				});
		});
	})();
	</script>
</div>
