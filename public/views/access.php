<?php
/**
 * Front-end: branded login / register panel for [gym_access].
 *
 * @package GymStoreForMembers
 * @var bool    $logged_in
 * @var WP_User $current
 * @var string  $redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="gsfm-access">
	<?php if ( $logged_in ) : ?>
		<div class="gsfm-access-card">
			<div class="gsfm-modal-check">&#10003;</div>
			<h3><?php printf( /* translators: %s: member name */ esc_html__( 'You\'re logged in, %s', 'gym-store-for-members' ), esc_html( $current->display_name ) ); ?></h3>
			<p><?php esc_html_e( 'You can now tell us what you\'d like us to order in.', 'gym-store-for-members' ); ?></p>
			<a class="gsfm-btn" href="<?php echo esc_url( $redirect ); ?>"><?php esc_html_e( 'Go to the shop', 'gym-store-for-members' ); ?></a>
		</div>
	<?php else : ?>
		<div class="gsfm-access-card">
			<h3 class="gsfm-access-heading"><?php esc_html_e( 'First, we need to know you\'re a member', 'gym-store-for-members' ); ?></h3>
			<p class="gsfm-access-intro"><?php esc_html_e( 'Confirm your membership with the email your gym membership is under. This lets us match your requests before we place the order. It only takes a few seconds.', 'gym-store-for-members' ); ?></p>

			<div class="gsfm-access-tabs">
				<button type="button" class="gsfm-tab is-active" data-tab="register"><?php esc_html_e( 'Confirm my membership', 'gym-store-for-members' ); ?></button>
				<button type="button" class="gsfm-tab" data-tab="login"><?php esc_html_e( 'I\'ve done this before', 'gym-store-for-members' ); ?></button>
			</div>

			<div class="gsfm-access-msg" role="alert"></div>

			<form class="gsfm-access-form" data-form="register">
				<input type="hidden" name="redirect" value="<?php echo esc_url( $redirect ); ?>" />
				<label><?php esc_html_e( 'Your name', 'gym-store-for-members' ); ?>
					<input type="text" name="name" autocomplete="name" required />
				</label>
				<label><?php esc_html_e( 'Membership email', 'gym-store-for-members' ); ?>
					<input type="email" name="email" autocomplete="email" required />
				</label>
				<label><?php esc_html_e( 'Choose a password', 'gym-store-for-members' ); ?>
					<input type="password" name="password" autocomplete="new-password" minlength="8" required />
				</label>
				<input type="text" name="website" class="gsfm-hp" tabindex="-1" autocomplete="off" aria-hidden="true" />
				<button type="submit" class="gsfm-btn"><?php esc_html_e( 'Confirm & continue', 'gym-store-for-members' ); ?></button>
			</form>

			<form class="gsfm-access-form" data-form="login" style="display:none;">
				<input type="hidden" name="redirect" value="<?php echo esc_url( $redirect ); ?>" />
				<label><?php esc_html_e( 'Membership email', 'gym-store-for-members' ); ?>
					<input type="email" name="email" autocomplete="email" required />
				</label>
				<label><?php esc_html_e( 'Password', 'gym-store-for-members' ); ?>
					<input type="password" name="password" autocomplete="current-password" required />
				</label>
				<button type="submit" class="gsfm-btn"><?php esc_html_e( 'Continue', 'gym-store-for-members' ); ?></button>
				<a class="gsfm-forgot" href="<?php echo esc_url( wp_lostpassword_url( $redirect ) ); ?>"><?php esc_html_e( 'Forgot your password?', 'gym-store-for-members' ); ?></a>
			</form>
		</div>
	<?php endif; ?>
</div>
