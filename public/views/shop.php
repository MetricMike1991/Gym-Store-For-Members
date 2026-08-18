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
	<p class="gsfm-back">
		<a href="<?php echo esc_url( get_permalink() ); ?>" class="gsfm-back-link">&#8592; <?php esc_html_e( 'All categories', 'gym-store-for-members' ); ?></a>
		<span class="gsfm-back-cat"><?php echo esc_html( $cat_name ); ?></span>
	</p>
	<?php if ( empty( $products ) ) : ?>
		<p class="gsfm-empty"><?php esc_html_e( 'No products are in stock right now. Check back soon.', 'gym-store-for-members' ); ?></p>
	<?php else : ?>
		<div class="gsfm-grid">
			<?php foreach ( $products as $p ) : ?>
				<?php
				$requested = in_array( (int) $p->id, $my_products, true );
				$pr        = GSFM_Products::pricing( $p );
				?>
				<div class="gsfm-card">
					<div class="gsfm-thumb">
						<?php if ( $pr['on_sale'] ) : ?>
							<span class="gsfm-sale-badge"><?php esc_html_e( 'SALE', 'gym-store-for-members' ); ?></span>
						<?php endif; ?>
						<?php if ( $p->image_url ) : ?>
							<img src="<?php echo esc_url( $p->image_url ); ?>" alt="<?php echo esc_attr( $p->title ); ?>" loading="lazy" />
						<?php endif; ?>
					</div>
					<h3 class="gsfm-title"><?php echo esc_html( $p->title ); ?></h3>
					<?php if ( $pr['on_sale'] ) : ?>
						<p class="gsfm-price">
							<del class="gsfm-price-was">&euro;<?php echo esc_html( number_format( $pr['regular'], 2 ) ); ?></del>
							<ins class="gsfm-price-now">&euro;<?php echo esc_html( number_format( $pr['effective'], 2 ) ); ?></ins>
						</p>
					<?php else : ?>
						<p class="gsfm-price">&euro;<?php echo esc_html( number_format( $pr['effective'], 2 ) ); ?></p>
					<?php endif; ?>

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
