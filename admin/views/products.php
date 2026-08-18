<?php
/**
 * Admin: products table with Scrape Now, RRP lookup, sale price and margin.
 *
 * @package GymStoreForMembers
 * @var object[] $products
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Products', 'gym-store-for-members' ); ?></h1>

	<p>
		<button id="gsfm-scrape" class="button button-primary"><?php esc_html_e( 'Scrape Now', 'gym-store-for-members' ); ?></button>
		<span id="gsfm-scrape-status" style="margin-left:10px;"></span>
	</p>

	<div id="gsfm-progress" style="display:none;max-width:520px;margin:0 0 16px;">
		<div style="background:#e2e4e7;border-radius:6px;overflow:hidden;height:18px;">
			<div id="gsfm-bar-fill" style="background:#2271b1;height:18px;width:0;transition:width .3s;"></div>
		</div>
		<p id="gsfm-progress-text" style="margin:6px 0 0;"></p>
	</div>

	<p>
		<button id="gsfm-bulk-rrp" class="button"><?php esc_html_e( 'Look up all missing RRPs (AI)', 'gym-store-for-members' ); ?></button>
		<span id="gsfm-bulk-rrp-status" style="margin-left:10px;"></span>
	</p>

	<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Product saved.', 'gym-store-for-members' ); ?></p></div>
	<?php endif; ?>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Image', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'Title', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'Cost €', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'RRP €', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'Sale €', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'VAT %', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'Profit €', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'Margin', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'Stock', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'Visible', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'Hide €', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'Button label', 'gym-store-for-members' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $products ) ) : ?>
			<tr><td colspan="13"><?php esc_html_e( 'No products yet. Configure Settings, then click Scrape Now.', 'gym-store-for-members' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $products as $p ) : ?>
				<?php $pr = GSFM_Products::pricing( $p ); ?>
				<tr class="gsfm-prow" data-cost="<?php echo esc_attr( $p->supplier_price ); ?>">
					<form method="post">
						<?php wp_nonce_field( 'gsfm_product' ); ?>
						<input type="hidden" name="product_id" value="<?php echo esc_attr( $p->id ); ?>" />
						<td>
							<?php if ( $p->image_url ) : ?>
								<img src="<?php echo esc_url( $p->image_url ); ?>" alt="" style="width:48px;height:48px;object-fit:cover;" />
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $p->title ); ?></td>
						<td><?php echo esc_html( number_format( (float) $p->supplier_price, 2 ) ); ?></td>
						<td style="white-space:nowrap;">
							<input type="number" step="0.01" min="0" name="rrp" class="gsfm-rrp" value="<?php echo esc_attr( $p->rrp ); ?>" style="width:80px;" />
							<button type="button" class="button button-small gsfm-rrp-lookup" data-product="<?php echo esc_attr( $p->id ); ?>" title="<?php esc_attr_e( 'Look up RRP + VAT with AI', 'gym-store-for-members' ); ?>">🔎</button>
						</td>
						<td><input type="number" step="0.01" min="0" name="sale_price" class="gsfm-sale" value="<?php echo esc_attr( $p->sale_price ); ?>" style="width:80px;" /></td>
						<td><input type="number" step="0.5" min="0" max="40" name="vat_rate" class="gsfm-vat" value="<?php echo esc_attr( $p->vat_rate ); ?>" style="width:64px;" /></td>
						<td class="gsfm-profit">&euro;<?php echo esc_html( number_format( $pr['profit'], 2 ) ); ?></td>
						<td class="gsfm-margin"><?php echo esc_html( round( $pr['margin_pct'] ) ); ?>%</td>
						<td>
							<?php if ( $p->in_stock ) : ?>
								<span style="color:#1a7f37;">&#10003;</span>
							<?php else : ?>
								<span style="color:#b32d2e;"><?php esc_html_e( 'Out', 'gym-store-for-members' ); ?></span>
							<?php endif; ?>
						</td>
						<td><input type="checkbox" name="visible" value="1" <?php checked( (int) $p->visible, 1 ); ?> /></td>
						<td><input type="checkbox" name="hide_price" value="1" <?php checked( (int) $p->hide_price, 1 ); ?> /></td>
						<td><input type="text" name="button_label" value="<?php echo esc_attr( $p->button_label ); ?>" placeholder="<?php esc_attr_e( 'Order this In for Me', 'gym-store-for-members' ); ?>" style="width:170px;" /></td>
						<td><button class="button" name="gsfm_save_product" value="1"><?php esc_html_e( 'Save', 'gym-store-for-members' ); ?></button></td>
					</form>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
	<p class="description" style="margin-top:10px;">
		<?php esc_html_e( 'Prices shown to members are VAT-inclusive (Irish law). Profit = price members pay − VAT owed − your cost. Margin turns red if you would lose money. VAT rates are AI-suggested — confirm classifications with your accountant.', 'gym-store-for-members' ); ?>
		<br>
		<?php esc_html_e( 'Hide € hides the price on the shop (good for multipacks / interest-only items). Button label overrides the default "Order this In for Me" — e.g. "Stock these in the Gym" or "Get this Flavour".', 'gym-store-for-members' ); ?>
	</p>
</div>
