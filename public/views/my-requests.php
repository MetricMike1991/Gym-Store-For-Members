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
	<h3 class="gsfm-myreq-title">&#128203; <?php esc_html_e( 'YOUR list — items you\'ve asked us to bring in', 'gym-store-for-members' ); ?></h3>
	<p class="gsfm-myreq-sub"><?php esc_html_e( 'These are your personal requests for the next order. Manage them from the shop above.', 'gym-store-for-members' ); ?></p>
	<ul class="gsfm-myreq-list">
		<?php foreach ( $requests as $r ) : ?>
			<?php $mode = ( isset( $r->request_mode ) && 'vending' === $r->request_mode ) ? 'vending' : 'order'; ?>
			<li class="gsfm-myreq-item">
				<span class="gsfm-myreq-thumb">
					<?php if ( $r->image_url ) : ?>
						<img src="<?php echo esc_url( $r->image_url ); ?>" alt="" />
					<?php endif; ?>
				</span>
				<span class="gsfm-myreq-name"><?php echo esc_html( $r->title ); ?></span>
				<span class="gsfm-myreq-type gsfm-myreq-type-<?php echo esc_attr( $mode ); ?>">
					<?php echo 'vending' === $mode ? esc_html__( 'Vending', 'gym-store-for-members' ) : esc_html__( 'Order', 'gym-store-for-members' ); ?>
				</span>
				<span class="gsfm-status gsfm-status-<?php echo esc_attr( $r->status ); ?>"><?php echo esc_html( ucfirst( $r->status ) ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
