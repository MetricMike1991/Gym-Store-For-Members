<?php
/**
 * Admin: products table with Scrape Now and per-product editing.
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

	<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Product saved.', 'gym-store-for-members' ); ?></p></div>
	<?php endif; ?>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Image', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'Title', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'Supplier €', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'Display € (your price)', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'Stock', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'Visible', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'Last Scraped', 'gym-store-for-members' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $products ) ) : ?>
			<tr><td colspan="8"><?php esc_html_e( 'No products yet. Configure Settings, then click Scrape Now.', 'gym-store-for-members' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $products as $p ) : ?>
				<tr>
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
						<td><input type="number" step="0.01" min="0" name="display_price" value="<?php echo esc_attr( $p->display_price ); ?>" style="width:90px;" /></td>
						<td>
							<?php if ( $p->in_stock ) : ?>
								<span style="color:#1a7f37;">&#10003; <?php esc_html_e( 'In stock', 'gym-store-for-members' ); ?></span>
							<?php else : ?>
								<span style="color:#b32d2e;"><?php esc_html_e( 'Out', 'gym-store-for-members' ); ?></span>
							<?php endif; ?>
						</td>
						<td><input type="checkbox" name="visible" value="1" <?php checked( (int) $p->visible, 1 ); ?> /></td>
						<td><?php echo esc_html( $p->last_scraped ); ?></td>
						<td><button class="button" name="gsfm_save_product" value="1"><?php esc_html_e( 'Save', 'gym-store-for-members' ); ?></button></td>
					</form>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
</div>
