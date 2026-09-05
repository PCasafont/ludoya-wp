<?php
/**
 * One event, as a card.
 *
 * Laid out the way the Ludoya app lays one out: image, then when it is, then what it is, then where.
 * The whole card is a single anchor — three separate links inside one card is three tab stops and
 * three underlines for one destination.
 *
 * Copy this file to `ludoya/event-card.php` in your theme to change it.
 *
 * @package Ludoya
 *
 * @var array  $event      The event.
 * @var string $event_page URL of a page carrying [ludoya_event], or empty to link to the Ludoya app.
 * @var bool   $past       Whether this is a past event.
 */

defined( 'ABSPATH' ) || exit;

$ludoya_id   = isset( $event['id'] ) ? $event['id'] : '';
$ludoya_link = $event_page
	? add_query_arg( 'ludoya_event', rawurlencode( $ludoya_id ), $event_page )
	: ludoya_event_url( $event );

$ludoya_title = isset( $event['title'] ) ? $event['title'] : '';
$ludoya_when  = ludoya_event_when(
	ludoya_get( $event, 'startsAt' ),
	ludoya_get( $event, 'endsAt' ),
	ludoya_get( $event, 'timeZone' )
);

// The event's own image, else the game it is about — a planned play rarely has art of its own but
// almost always has a box. Tested with empty(), not against '': the API sends an absent image as a
// null, which is not the empty string, and a strict test here silently skipped every fallback.
$ludoya_image = ludoya_get( $event, 'imageUrl', '' );
if ( empty( $ludoya_image ) ) {
	$ludoya_image = ludoya_get( $event, 'game.imageUrl', '' );
}

$ludoya_seats_left = null;
if ( ! empty( $event['capacity'] ) ) {
	$ludoya_seats_left = max( 0, (int) $event['capacity'] - (int) $event['participantCount'] );
}

$ludoya_classes = array( 'ludoya-card' );
if ( $past ) {
	$ludoya_classes[] = 'ludoya-card--past';
}
if ( ! empty( $event['canceled'] ) ) {
	$ludoya_classes[] = 'ludoya-card--canceled';
}
?>
<a
	class="<?php echo esc_attr( implode( ' ', $ludoya_classes ) ); ?>"
	href="<?php echo esc_url( $ludoya_link ); ?>"
>
	<span class="ludoya-card__media">
		<?php if ( $ludoya_image ) : ?>
			<img src="<?php echo esc_url( $ludoya_image ); ?>" alt="" loading="lazy" />
		<?php else : ?>
			<span
				class="ludoya-card__tile"
				style="--ludoya-tint: <?php echo (int) ludoya_tint( $ludoya_id ); ?>"
				aria-hidden="true"
			><?php echo esc_html( mb_strtoupper( mb_substr( $ludoya_title, 0, 1 ) ) ); ?></span>
		<?php endif; ?>
	</span>

	<span class="ludoya-card__body">
		<span class="ludoya-card__when ludoya-card__when--<?php echo esc_attr( $ludoya_when['state'] ? $ludoya_when['state'] : 'none' ); ?>">
			<time datetime="<?php echo esc_attr( ludoya_get( $event, 'startsAt', '' ) ); ?>"><?php echo esc_html( $ludoya_when['text'] ); ?></time>
		</span>

		<span class="ludoya-card__title"><?php echo esc_html( $ludoya_title ); ?></span>

		<?php if ( ! empty( $event['location']['name'] ) ) : ?>
			<span class="ludoya-card__where"><?php echo esc_html( $event['location']['name'] ); ?></span>
		<?php endif; ?>

		<?php
		// A planned play takes its title from the game, so naming the game again just repeats the
		// heading back at the reader.
		$ludoya_game_name = ludoya_get( $event, 'game.name', '' );
		?>
		<?php if ( $ludoya_game_name && $ludoya_game_name !== $ludoya_title ) : ?>
			<span class="ludoya-card__game"><?php echo esc_html( $ludoya_game_name ); ?></span>
		<?php endif; ?>

		<span class="ludoya-card__tags">
			<span class="ludoya-tag"><?php echo esc_html( ludoya_event_type_label( isset( $event['type'] ) ? $event['type'] : '' ) ); ?></span>

			<?php if ( ! empty( $event['canceled'] ) ) : ?>
				<span class="ludoya-tag ludoya-tag--negative"><?php esc_html_e( 'Cancelled', 'ludoya' ); ?></span>
			<?php elseif ( null === $ludoya_seats_left ) : ?>
				<span class="ludoya-tag ludoya-tag--quiet">
					<?php
					printf(
						/* translators: %d: number of people signed up. */
						esc_html( _n( '%d going', '%d going', (int) $event['participantCount'], 'ludoya' ) ),
						(int) $event['participantCount']
					);
					?>
				</span>
			<?php elseif ( 0 === $ludoya_seats_left ) : ?>
				<span class="ludoya-tag ludoya-tag--negative"><?php esc_html_e( 'Full', 'ludoya' ); ?></span>
			<?php else : ?>
				<span class="ludoya-tag ludoya-tag--positive">
					<?php
					printf(
						/* translators: %d: number of free seats. */
						esc_html( _n( '%d seat left', '%d seats left', $ludoya_seats_left, 'ludoya' ) ),
						(int) $ludoya_seats_left
					);
					?>
				</span>
			<?php endif; ?>
		</span>
	</span>
</a>
