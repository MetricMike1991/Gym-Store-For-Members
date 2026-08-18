<?php
/**
 * Front-end: member's own requests for [gym_account].
 *
 * @package GymStoreForMembers
 * @var object[] $requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="gsfm-account">
	<h2><?php esc_html_e( 'My Requests', 'gym-store-for-members' ); ?></h2>

	<?php if ( empty( $requests ) ) : ?>
		<p><?php esc_html_e( 'You have not requested any products yet.', 'gym-store-for-members' ); ?></p>
	<?php else : ?>
		<table class="gsfm-account-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Product', 'gym-store-for-members' ); ?></th>
					<th><?php esc_html_e( 'Price', 'gym-store-for-members' ); ?></th>
					<th><?php esc_html_e( 'Requested', 'gym-store-for-members' ); ?></th>
					<th><?php esc_html_e( 'Status', 'gym-store-for-members' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $requests as $r ) : ?>
				<tr>
					<td class="gsfm-acc-product">
						<?php if ( $r->image_url ) : ?>
							<img src="<?php echo esc_url( $r->image_url ); ?>" alt="" />
						<?php endif; ?>
						<span><?php echo esc_html( $r->title ); ?></span>
					</td>
					<td>&euro;<?php echo esc_html( number_format( (float) $r->display_price, 2 ) ); ?></td>
					<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $r->requested_at ) ); ?></td>
					<td><span class="gsfm-status gsfm-status-<?php echo esc_attr( $r->status ); ?>"><?php echo esc_html( ucfirst( $r->status ) ); ?></span></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
