<?php
/**
 * Front-end: drop countdown banner for [gym_countdown].
 *
 * @package GymStoreForMembers
 * @var array $c  Countdown settings.
 * @var int   $ts Deadline timestamp.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="gsfm-countdown"
	data-deadline="<?php echo esc_attr( $ts * 1000 ); ?>"
	style="background:<?php echo esc_attr( $c['bg_color'] ); ?>;color:<?php echo esc_attr( $c['text_color'] ); ?>;">
	<div class="gsfm-cd-inner">
		<div class="gsfm-cd-open">
			<p class="gsfm-cd-headline"><?php echo esc_html( $c['headline'] ); ?></p>
			<div class="gsfm-cd-timer" aria-live="polite">
				<span class="gsfm-cd-unit"><span class="gsfm-cd-d">0</span><small><?php esc_html_e( 'days', 'gym-store-for-members' ); ?></small></span>
				<span class="gsfm-cd-unit"><span class="gsfm-cd-h">0</span><small><?php esc_html_e( 'hrs', 'gym-store-for-members' ); ?></small></span>
				<span class="gsfm-cd-unit"><span class="gsfm-cd-m">0</span><small><?php esc_html_e( 'min', 'gym-store-for-members' ); ?></small></span>
				<span class="gsfm-cd-unit"><span class="gsfm-cd-s">0</span><small><?php esc_html_e( 'sec', 'gym-store-for-members' ); ?></small></span>
			</div>
			<p class="gsfm-cd-subtext"><?php echo esc_html( $c['subtext'] ); ?></p>
		</div>
		<div class="gsfm-cd-closed" style="display:none;">
			<p class="gsfm-cd-headline"><?php echo esc_html( $c['closed_text'] ); ?></p>
		</div>
	</div>
</div>
