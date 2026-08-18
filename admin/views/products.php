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
				<th><?php esc_html_e( 'Margin', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'Stock', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'Visible', 'gym-store-for-members' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $products ) ) : ?>
			<tr><td colspan="9"><?php esc_html_e( 'No products yet. Configure Settings, then click Scrape Now.', 'gym-store-for-members' ); ?></td></tr>
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
							<button type="button" class="button button-small gsfm-rrp-lookup" data-product="<?php echo esc_attr( $p->id ); ?>" title="<?php esc_attr_e( 'Look up RRP with AI', 'gym-store-for-members' ); ?>">🔎</button>
						</td>
						<td><input type="number" step="0.01" min="0" name="sale_price" class="gsfm-sale" value="<?php echo esc_attr( $p->sale_price ); ?>" style="width:80px;" /></td>
						<td class="gsfm-margin"><?php echo esc_html( round( $pr['margin_pct'] ) ); ?>%</td>
						<td>
							<?php if ( $p->in_stock ) : ?>
								<span style="color:#1a7f37;">&#10003;</span>
							<?php else : ?>
								<span style="color:#b32d2e;"><?php esc_html_e( 'Out', 'gym-store-for-members' ); ?></span>
							<?php endif; ?>
						</td>
						<td><input type="checkbox" name="visible" value="1" <?php checked( (int) $p->visible, 1 ); ?> /></td>
						<td><button class="button" name="gsfm_save_product" value="1"><?php esc_html_e( 'Save', 'gym-store-for-members' ); ?></button></td>
					</form>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
	<p class="description" style="margin-top:10px;">
		<?php esc_html_e( 'Margin is calculated from the effective price members pay (Sale if set, otherwise RRP) versus your cost. Set a Sale price below RRP to run a discount — a badge shows on the shop.', 'gym-store-for-members' ); ?>
	</p>
</div>
