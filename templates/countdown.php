<?php
/**
 * Time left until an event starts, from its real dates in Ludoya.
 *
 * Rendered with the numbers as of the page load, so it reads right without JavaScript and in a
 * cached page that is a few minutes old; the script then keeps it ticking. Once the event starts
 * it says so, and once it is over it says that, instead of counting into negative numbers or
 * freezing at zero the way a hand-set timer does when nobody remembers to take it down.
 *
 * Copy this file to `ludoya/countdown.php` in your theme to change it.
 *
 * @package Ludoya
 *
 * @var array  $event   The event.
 * @var string $heading Optional line above the numbers.
 */

defined( 'ABSPATH' ) || exit;

$ludoya_start = (string) ludoya_get( $event, 'startsAt', '' );
$ludoya_end   = (string) ludoya_get( $event, 'endsAt', '' );
if ( '' === $ludoya_start ) {
	return;
}

$ludoya_now   = time();
$ludoya_from  = strtotime( $ludoya_start );
$ludoya_until = '' !== $ludoya_end ? strtotime( $ludoya_end ) : $ludoya_from;
$ludoya_state = $ludoya_now < $ludoya_from ? 'before' : ( $ludoya_now < $ludoya_until ? 'now' : 'after' );
$ludoya_left  = max( 0, $ludoya_from - $ludoya_now );
$ludoya_units = array(
	'days'    => array( (int) floor( $ludoya_left / 86400 ), __( 'days', 'ludoya' ) ),
	'hours'   => array( (int) floor( ( $ludoya_left % 86400 ) / 3600 ), __( 'hours', 'ludoya' ) ),
	'minutes' => array( (int) floor( ( $ludoya_left % 3600 ) / 60 ), __( 'minutes', 'ludoya' ) ),
	'seconds' => array( (int) ( $ludoya_left % 60 ), __( 'seconds', 'ludoya' ) ),
);
$ludoya_zone     = ludoya_event_zone( $event );
$ludoya_end_text = '' !== $ludoya_end
	? wp_date( 'l ' . get_option( 'time_format', 'H:i' ), $ludoya_until, $ludoya_zone )
	: '';
?>
<div
	class="ludoya ludoya-countdown ludoya-countdown--<?php echo esc_attr( $ludoya_state ); ?>"
	data-ludoya-countdown
	data-start="<?php echo esc_attr( $ludoya_start ); ?>"
	data-end="<?php echo esc_attr( '' !== $ludoya_end ? $ludoya_end : $ludoya_start ); ?>"
>
	<div class="ludoya-countdown__before" <?php echo 'before' === $ludoya_state ? '' : 'hidden'; ?>>
		<?php if ( $heading ) : ?>
			<p class="ludoya-countdown__heading"><?php echo esc_html( $heading ); ?></p>
		<?php endif; ?>
		<p class="ludoya-countdown__units" role="timer" aria-label="<?php esc_attr_e( 'Time left until it starts', 'ludoya' ); ?>">
			<?php foreach ( $ludoya_units as $ludoya_key => $ludoya_unit ) : ?>
				<span class="ludoya-countdown__unit">
					<span class="ludoya-countdown__value" data-unit="<?php echo esc_attr( $ludoya_key ); ?>"><?php echo esc_html( 'days' === $ludoya_key ? (string) $ludoya_unit[0] : sprintf( '%02d', $ludoya_unit[0] ) ); ?></span>
					<span class="ludoya-countdown__label"><?php echo esc_html( $ludoya_unit[1] ); ?></span>
				</span>
			<?php endforeach; ?>
		</p>
	</div>
	<p class="ludoya-countdown__now" <?php echo 'now' === $ludoya_state ? '' : 'hidden'; ?>>
		<strong><?php esc_html_e( 'It is on right now!', 'ludoya' ); ?></strong>
		<?php if ( '' !== $ludoya_end_text ) : ?>
			<?php
			/* translators: %s: day and time the event ends, e.g. "Sunday 20:00". */
			printf( esc_html__( 'Until %s.', 'ludoya' ), esc_html( $ludoya_end_text ) );
			?>
		<?php endif; ?>
	</p>
	<p class="ludoya-countdown__after" <?php echo 'after' === $ludoya_state ? '' : 'hidden'; ?>>
		<?php esc_html_e( 'That was it. Thank you for coming!', 'ludoya' ); ?>
	</p>
</div>
