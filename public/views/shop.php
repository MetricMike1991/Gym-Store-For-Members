<?php
/**
 * Front-end: product grid for [gym_shop].
 *
 * @package GymStoreForMembers
 * @var object[] $products
 * @var bool     $logged_in
 * @var int[]    $my_products
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="gsfm-shop">
	<?php if ( empty( $products ) ) : ?>
		<p class="gsfm-empty"><?php esc_html_e( 'No products are in stock right now. Check back soon.', 'gym-store-for-members' ); ?></p>
	<?php else : ?>
		<div class="gsfm-grid">
			<?php foreach ( $products as $p ) : ?>
				<?php $requested = in_array( (int) $p->id, $my_products, true ); ?>
				<div class="gsfm-card">
					<div class="gsfm-thumb">
						<?php if ( $p->image_url ) : ?>
							<img src="<?php echo esc_url( $p->image_url ); ?>" alt="<?php echo esc_attr( $p->title ); ?>" loading="lazy" />
						<?php endif; ?>
					</div>
					<h3 class="gsfm-title"><?php echo esc_html( $p->title ); ?></h3>
					<p class="gsfm-price">&euro;<?php echo esc_html( number_format( (float) $p->display_price, 2 ) ); ?></p>

					<?php if ( ! $logged_in ) : ?>
						<a class="gsfm-btn gsfm-btn-login" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">
							<?php esc_html_e( 'Log in to request', 'gym-store-for-members' ); ?>
						</a>
					<?php else : ?>
						<button
							class="gsfm-btn gsfm-toggle<?php echo $requested ? ' is-requested' : ''; ?>"
							data-product="<?php echo esc_attr( $p->id ); ?>"
							data-requested="<?php echo $requested ? '1' : '0'; ?>">
							<span class="gsfm-label-add"><?php esc_html_e( 'Request This', 'gym-store-for-members' ); ?></span>
							<span class="gsfm-label-remove"><?php esc_html_e( 'Requested', 'gym-store-for-members' ); ?> &#10003; (<?php esc_html_e( 'cancel', 'gym-store-for-members' ); ?>)</span>
						</button>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
