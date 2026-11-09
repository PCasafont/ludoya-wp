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
 * @var string $when_text  Optional. Replaces the date line, for a timetable that already prints the
 *                         day and hour above the card ("10:00 – 14:00", "All through the event").
 * @var array  $area       Optional. The area of a programme the event is in: id and title.
 * @var bool   $in_programme Optional. The card is one entry of a programme: the programme's own
 *                         headings say what a bare "Event" tag would, so it is left off.
 * @var bool   $same_venue Optional. The card sits in a programme held at its own venue: name only
 *                         the room or table, not the venue again.
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
if ( isset( $when_text ) && null !== $when_text ) {
	$ludoya_when['text'] = $when_text;
}
$in_programme = ! empty( $in_programme );
$ludoya_area = isset( $area ) && is_array( $area ) ? $area : array();

// The event's own image, else the game it is about — a planned play rarely has art of its own but
// almost always has a box. Tested with empty(), not against '': the API sends an absent image as a
// null, which is not the empty string, and a strict test here silently skipped every fallback.
$ludoya_image = ludoya_get( $event, 'imageUrl', '' );
if ( empty( $ludoya_image ) ) {
	$ludoya_image = ludoya_get( $event, 'game.imageUrl', '' );
}

// Seats only mean something when Ludoya takes the sign-ups (see ludoya_takes_signups()).
$ludoya_seats_left = null;
if ( ! empty( $event['capacity'] ) && ludoya_takes_signups( $event ) ) {
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
		<?php if ( '' !== $ludoya_when['text'] ) : ?>
			<span class="ludoya-card__when ludoya-card__when--<?php echo esc_attr( $ludoya_when['state'] ? $ludoya_when['state'] : 'none' ); ?>">
				<time datetime="<?php echo esc_attr( ludoya_get( $event, 'startsAt', '' ) ); ?>"><?php echo esc_html( $ludoya_when['text'] ); ?></time>
			</span>
		<?php endif; ?>

		<span class="ludoya-card__title"><?php echo esc_html( $ludoya_title ); ?></span>

		<?php
		// Inside a festival's programme every card is at the festival's venue; repeating it forty
		// times says nothing. A room or table inside it still shows, since that is what tells two
		// cards apart.
		$ludoya_place = ludoya_place_label( $event );
		if ( ! empty( $same_venue ) && $ludoya_place === (string) ludoya_get( $event, 'location.name', '' ) ) {
			$ludoya_place = '';
		} elseif ( ! empty( $same_venue ) ) {
			$ludoya_place = ltrim( substr( $ludoya_place, strlen( (string) ludoya_get( $event, 'location.name', '' ) ) ), ' ·' );
		}
		?>
		<?php if ( '' !== $ludoya_place ) : ?>
			<span class="ludoya-card__where"><?php echo esc_html( $ludoya_place ); ?></span>
		<?php endif; ?>

		<?php
		// A planned play takes its title from the game ("Root", "Blood on the Clocktower: Sects &
		// Violets"), so naming the game again just repeats the heading back at the reader.
		$ludoya_game_name = ludoya_get( $event, 'game.name', '' );
		?>
		<?php if ( $ludoya_game_name && false === mb_stripos( $ludoya_title, $ludoya_game_name ) ) : ?>
			<span class="ludoya-card__game"><?php echo esc_html( $ludoya_game_name ); ?></span>
		<?php endif; ?>

		<span class="ludoya-card__tags">
			<?php if ( ! empty( $ludoya_area['title'] ) ) : ?>
				<span class="ludoya-tag ludoya-tag--area" data-area-name="<?php echo esc_attr( sanitize_title( $ludoya_area['title'] ) ); ?>" style="--ludoya-tint: <?php echo (int) ludoya_tint( $ludoya_area['id'] ); ?>"><?php echo esc_html( $ludoya_area['title'] ); ?></span>
			<?php endif; ?>
			<?php if ( ! $in_programme || 'MEETUP' !== ludoya_get( $event, 'type', '' ) ) : ?>
				<span class="ludoya-tag"><?php echo esc_html( ludoya_event_type_label( isset( $event['type'] ) ? $event['type'] : '' ) ); ?></span>
			<?php endif; ?>

			<?php if ( ! empty( $event['canceled'] ) ) : ?>
				<span class="ludoya-tag ludoya-tag--negative"><?php esc_html_e( 'Cancelled', 'ludoya' ); ?></span>
			<?php elseif ( ! ludoya_takes_signups( $event ) ) : ?>
				<span class="ludoya-tag ludoya-tag--quiet"><?php esc_html_e( 'No sign-up', 'ludoya' ); ?></span>
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
				<?php // A few seats left is worth saying louder than many: it is the moment to sign up. ?>
				<span class="ludoya-tag <?php echo $ludoya_seats_left <= max( 2, (int) floor( (int) $event['capacity'] / 5 ) ) ? 'ludoya-tag--warning' : 'ludoya-tag--positive'; ?>">
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
