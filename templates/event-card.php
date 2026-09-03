<?php
/**
 * One event, as a card.
 *
 * Copy this file to `ludoya/event-card.php` in your theme to change it.
 *
 * @package Ludoya
 *
 * @var array  $event      The event.
 * @var string $event_page URL of a page carrying [ludoya_event], or empty to link to ludoya.com.
 * @var bool   $past       Whether this is a past event.
 */

defined( 'ABSPATH' ) || exit;

$ludoya_id   = isset( $event['id'] ) ? $event['id'] : '';
$ludoya_zone = isset( $event['timeZone'] ) ? $event['timeZone'] : null;
$ludoya_link = $event_page
	? add_query_arg( 'ludoya_event', rawurlencode( $ludoya_id ), $event_page )
	: ludoya_event_url( $event );
$ludoya_seats_left = null;
if ( ! empty( $event['capacity'] ) ) {
	$ludoya_seats_left = max( 0, (int) $event['capacity'] - (int) $event['participantCount'] );
}
?>
<article class="ludoya-card<?php echo $past ? ' ludoya-card--past' : ''; ?><?php echo ! empty( $event['canceled'] ) ? ' ludoya-card--canceled' : ''; ?>">
	<?php if ( ! empty( $event['imageUrl'] ) ) : ?>
		<a class="ludoya-card__image" href="<?php echo esc_url( $ludoya_link ); ?>">
			<img src="<?php echo esc_url( $event['imageUrl'] ); ?>" alt="" loading="lazy" />
		</a>
	<?php endif; ?>

	<div class="ludoya-card__body">
		<p class="ludoya-card__meta">
			<span class="ludoya-badge"><?php echo esc_html( ludoya_event_type_label( isset( $event['type'] ) ? $event['type'] : '' ) ); ?></span>
			<?php if ( ! empty( $event['canceled'] ) ) : ?>
				<span class="ludoya-badge ludoya-badge--canceled"><?php esc_html_e( 'Cancelled', 'ludoya' ); ?></span>
			<?php endif; ?>
		</p>

		<h3 class="ludoya-card__title">
			<a href="<?php echo esc_url( $ludoya_link ); ?>"><?php echo esc_html( isset( $event['title'] ) ? $event['title'] : '' ); ?></a>
		</h3>

		<?php if ( ! empty( $event['startsAt'] ) ) : ?>
			<p class="ludoya-card__date">
				<time datetime="<?php echo esc_attr( $event['startsAt'] ); ?>">
					<?php echo esc_html( ludoya_format_date( $event['startsAt'], $ludoya_zone ) ); ?>
				</time>
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $event['location']['name'] ) ) : ?>
			<p class="ludoya-card__location"><?php echo esc_html( $event['location']['name'] ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $event['game']['name'] ) ) : ?>
			<p class="ludoya-card__game">
				<a href="<?php echo esc_url( ludoya_game_url( $event['game'] ) ); ?>"><?php echo esc_html( $event['game']['name'] ); ?></a>
			</p>
		<?php endif; ?>

		<p class="ludoya-card__seats">
			<?php
			if ( null === $ludoya_seats_left ) {
				printf(
					/* translators: %d: number of people signed up. */
					esc_html( _n( '%d person signed up', '%d people signed up', (int) $event['participantCount'], 'ludoya' ) ),
					(int) $event['participantCount']
				);
			} elseif ( 0 === $ludoya_seats_left ) {
				esc_html_e( 'Full', 'ludoya' );
			} else {
				printf(
					/* translators: %d: number of free seats. */
					esc_html( _n( '%d seat left', '%d seats left', $ludoya_seats_left, 'ludoya' ) ),
					(int) $ludoya_seats_left
				);
			}
			?>
		</p>

		<a class="ludoya-button" href="<?php echo esc_url( $ludoya_link ); ?>"><?php esc_html_e( 'Details', 'ludoya' ); ?></a>
	</div>
</article>
