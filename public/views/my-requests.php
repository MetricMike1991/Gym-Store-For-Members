<?php
/**
 * Front-end: compact "my requests" summary for [gym_my_requests].
 *
 * @package GymStoreForMembers
 * @var object[] $requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="gsfm-myreq">
	<h3 class="gsfm-myreq-title"><?php esc_html_e( 'YOUR list — items you\'ve asked us to bring in', 'gym-store-for-members' ); ?></h3>
	<p class="gsfm-myreq-sub"><?php esc_html_e( 'These are your personal requests for the next order. Remove anything you no longer want.', 'gym-store-for-members' ); ?></p>

	<ul class="gsfm-myreq-list"<?php echo empty( $requests ) ? ' style="display:none;"' : ''; ?>>
		<?php foreach ( $requests as $r ) : ?>
			<?php $mode = ( isset( $r->request_mode ) && 'vending' === $r->request_mode ) ? 'vending' : 'order'; ?>
			<li class="gsfm-myreq-item" data-product="<?php echo esc_attr( $r->product_id ); ?>">
				<span class="gsfm-myreq-thumb">
					<?php if ( $r->image_url ) : ?>
						<img src="<?php echo esc_url( $r->image_url ); ?>" alt="" />
					<?php endif; ?>
				</span>
				<span class="gsfm-myreq-name"><?php echo esc_html( $r->title ); ?></span>
				<span class="gsfm-myreq-type gsfm-myreq-type-<?php echo esc_attr( $mode ); ?>">
					<?php echo 'vending' === $mode
						? esc_html__( 'I want you to get this into the vending machine', 'gym-store-for-members' )
						: esc_html__( 'Please get this product in for me in the next order and I will buy it', 'gym-store-for-members' ); ?>
				</span>
				<span class="gsfm-status gsfm-status-<?php echo esc_attr( $r->status ); ?>"><?php echo esc_html( ucfirst( $r->status ) ); ?></span>
				<button type="button" class="gsfm-myreq-remove" data-product="<?php echo esc_attr( $r->product_id ); ?>" aria-label="<?php esc_attr_e( 'Remove from my list', 'gym-store-for-members' ); ?>" title="<?php esc_attr_e( 'Remove', 'gym-store-for-members' ); ?>">&times;</button>
			</li>
		<?php endforeach; ?>
	</ul>

	<p class="gsfm-myreq-empty"<?php echo empty( $requests ) ? '' : ' style="display:none;"'; ?>>
		<?php esc_html_e( 'You haven\'t added anything yet. Browse the shop above and tap a product to add it here.', 'gym-store-for-members' ); ?>
	</p>
</div>
