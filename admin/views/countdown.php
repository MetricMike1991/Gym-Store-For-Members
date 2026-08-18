<?php
/**
 * Admin: drop countdown banner settings.
 *
 * @package GymStoreForMembers
 * @var array $c
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Drop Countdown', 'gym-store-for-members' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Run your shop like a timed group order. Set a deadline and message, then add the [gym_countdown] shortcode to your shop page (or anywhere on your site).', 'gym-store-for-members' ); ?></p>

	<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Countdown saved.', 'gym-store-for-members' ); ?></p></div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'gsfm_countdown' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Enable banner', 'gym-store-for-members' ); ?></th>
				<td><label><input type="checkbox" name="enabled" value="1" <?php checked( (int) $c['enabled'], 1 ); ?> /> <?php esc_html_e( 'Show the countdown banner', 'gym-store-for-members' ); ?></label></td>
			</tr>
			<tr>
				<th><label for="deadline"><?php esc_html_e( 'Order closes at', 'gym-store-for-members' ); ?></label></th>
				<td>
					<input type="datetime-local" name="deadline" id="deadline" value="<?php echo esc_attr( $c['deadline'] ); ?>" />
					<p class="description"><?php esc_html_e( 'When the timer hits zero the banner switches to the closed message.', 'gym-store-for-members' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="headline"><?php esc_html_e( 'Headline', 'gym-store-for-members' ); ?></label></th>
				<td><input type="text" name="headline" id="headline" class="large-text" value="<?php echo esc_attr( $c['headline'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="subtext"><?php esc_html_e( 'Message', 'gym-store-for-members' ); ?></label></th>
				<td><textarea name="subtext" id="subtext" rows="3" class="large-text"><?php echo esc_textarea( $c['subtext'] ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="closed_text"><?php esc_html_e( 'Closed message', 'gym-store-for-members' ); ?></label></th>
				<td><textarea name="closed_text" id="closed_text" rows="2" class="large-text"><?php echo esc_textarea( $c['closed_text'] ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="bg_color"><?php esc_html_e( 'Banner colour', 'gym-store-for-members' ); ?></label></th>
				<td><input type="color" name="bg_color" id="bg_color" value="<?php echo esc_attr( $c['bg_color'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="text_color"><?php esc_html_e( 'Text colour', 'gym-store-for-members' ); ?></label></th>
				<td><input type="color" name="text_color" id="text_color" value="<?php echo esc_attr( $c['text_color'] ); ?>" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Shortcode', 'gym-store-for-members' ); ?></th>
				<td><code>[gym_countdown]</code></td>
			</tr>
		</table>
		<p><button class="button button-primary" name="gsfm_save_countdown" value="1"><?php esc_html_e( 'Save Countdown', 'gym-store-for-members' ); ?></button></p>
	</form>
</div>
