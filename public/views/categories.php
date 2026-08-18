<?php
/**
 * Front-end: category grid for [gym_shop] (no category selected).
 *
 * @package GymStoreForMembers
 * @var object[] $categories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="gsfm-shop">
	<?php if ( empty( $categories ) ) : ?>
		<p class="gsfm-empty"><?php esc_html_e( 'No products available yet. Check back soon.', 'gym-store-for-members' ); ?></p>
	<?php else : ?>
		<div class="gsfm-cat-grid">
			<?php foreach ( $categories as $cat ) : ?>
				<a class="gsfm-cat-card"
				   href="<?php echo esc_url( add_query_arg( 'gsfm_cat', $cat->category_slug, get_permalink() ) ); ?>">
					<div class="gsfm-cat-thumb">
						<?php if ( ! empty( $cat->cover_image ) ) : ?>
							<img src="<?php echo esc_url( $cat->cover_image ); ?>" alt="" loading="lazy" />
						<?php else : ?>
							<span class="gsfm-cat-noimg">&#128230;</span>
						<?php endif; ?>
					</div>
					<div class="gsfm-cat-info">
						<span class="gsfm-cat-name"><?php echo esc_html( $cat->category_name ); ?></span>
						<span class="gsfm-cat-count"><?php echo esc_html( $cat->count ); ?> <?php esc_html_e( 'products', 'gym-store-for-members' ); ?></span>
					</div>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
