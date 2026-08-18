<?php
/**
 * Admin: member requests table with status control and CSV export.
 *
 * @package GymStoreForMembers
 * @var object[] $requests
 * @var string   $status
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$export_url = wp_nonce_url(
	add_query_arg(
		array(
			'page'        => 'gsfm-requests',
			'gsfm_export' => 1,
			'status'      => $status,
		),
		admin_url( 'admin.php' )
	),
	'gsfm_export'
);
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Member Requests', 'gym-store-for-members' ); ?></h1>

	<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Request updated.', 'gym-store-for-members' ); ?></p></div>
	<?php endif; ?>

	<form method="get" style="margin:12px 0;">
		<input type="hidden" name="page" value="gsfm-requests" />
		<label>
			<?php esc_html_e( 'Filter status:', 'gym-store-for-members' ); ?>
			<select name="status" onchange="this.form.submit()">
				<option value="" <?php selected( $status, '' ); ?>><?php esc_html_e( 'All', 'gym-store-for-members' ); ?></option>
				<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'gym-store-for-members' ); ?></option>
				<option value="arrived" <?php selected( $status, 'arrived' ); ?>><?php esc_html_e( 'Arrived', 'gym-store-for-members' ); ?></option>
				<option value="collected" <?php selected( $status, 'collected' ); ?>><?php esc_html_e( 'Collected', 'gym-store-for-members' ); ?></option>
			</select>
		</label>
		<a href="<?php echo esc_url( $export_url ); ?>" class="button button-primary"><?php esc_html_e( 'Export CSV', 'gym-store-for-members' ); ?></a>
	</form>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Member', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'Email', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'Product', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'Type', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'Requested', 'gym-store-for-members' ); ?></th>
				<th><?php esc_html_e( 'Status', 'gym-store-for-members' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $requests ) ) : ?>
			<tr><td colspan="7"><?php esc_html_e( 'No requests yet.', 'gym-store-for-members' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $requests as $r ) : ?>
				<tr>
					<form method="post">
						<?php wp_nonce_field( 'gsfm_request' ); ?>
						<input type="hidden" name="request_id" value="<?php echo esc_attr( $r->id ); ?>" />
						<td><?php echo esc_html( $r->display_name ); ?></td>
						<td><?php echo esc_html( $r->user_email ); ?></td>
						<td><?php echo esc_html( $r->title ); ?></td>
						<td><?php echo 'vending' === $r->request_mode ? esc_html__( 'Vending', 'gym-store-for-members' ) : esc_html__( 'Order', 'gym-store-for-members' ); ?></td>
						<td><?php echo esc_html( $r->requested_at ); ?></td>
						<td>
							<select name="status">
								<option value="pending" <?php selected( $r->status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'gym-store-for-members' ); ?></option>
								<option value="arrived" <?php selected( $r->status, 'arrived' ); ?>><?php esc_html_e( 'Arrived', 'gym-store-for-members' ); ?></option>
								<option value="collected" <?php selected( $r->status, 'collected' ); ?>><?php esc_html_e( 'Collected', 'gym-store-for-members' ); ?></option>
							</select>
						</td>
						<td><button class="button" name="gsfm_save_request" value="1"><?php esc_html_e( 'Update', 'gym-store-for-members' ); ?></button></td>
					</form>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
</div>
