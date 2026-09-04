<?php
/**
 * One event, in full, with its sign-up form when the site allows sign-ups.
 *
 * Same visual language as the card, one size up: art, when, what, where, then the detail.
 *
 * Copy this file to `ludoya/event-single.php` in your theme to change it.
 *
 * @package Ludoya
 *
 * @var array  $event    The event.
 * @var string $signup   Rendered sign-up form, or an empty string.
 * @var string $back_url Optional URL back to the events list.
 */

defined( 'ABSPATH' ) || exit;

$ludoya_title = isset( $event['title'] ) ? $event['title'] : '';
$ludoya_when  = ludoya_event_when(
	ludoya_get( $event, 'startsAt' ),
	ludoya_get( $event, 'endsAt' ),
	ludoya_get( $event, 'timeZone' )
);

// The event's own art, else the game's box. See the note in event-card.php on why this is empty()
// and not a comparison against '': an absent image arrives as a null.
$ludoya_image = ludoya_get( $event, 'imageUrl', '' );
if ( empty( $ludoya_image ) ) {
	$ludoya_image = ludoya_get( $event, 'game.imageUrl', '' );
}

$ludoya_game_name = ludoya_get( $event, 'game.name', '' );

$ludoya_seats_left = null;
if ( ! empty( $event['capacity'] ) ) {
	$ludoya_seats_left = max( 0, (int) $event['capacity'] - (int) $event['participantCount'] );
}
?>
<div class="ludoya ludoya-event<?php echo ! empty( $event['canceled'] ) ? ' ludoya-event--canceled' : ''; ?>">
	<?php if ( $back_url ) : ?>
		<p class="ludoya-event__back"><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'All events', 'ludoya' ); ?></a></p>
	<?php endif; ?>

	<div class="ludoya-event__head">
		<div class="ludoya-event__art">
			<?php if ( $ludoya_image ) : ?>
				<img src="<?php echo esc_url( $ludoya_image ); ?>" alt="" />
			<?php else : ?>
				<span
					class="ludoya-card__tile"
					style="--ludoya-tint: <?php echo (int) ludoya_tint( ludoya_get( $event, 'id', '' ) ); ?>"
					aria-hidden="true"
				><?php echo esc_html( mb_strtoupper( mb_substr( $ludoya_title, 0, 1 ) ) ); ?></span>
			<?php endif; ?>
		</div>

		<div class="ludoya-event__headings">
			<p class="ludoya-card__when ludoya-card__when--<?php echo esc_attr( $ludoya_when['state'] ? $ludoya_when['state'] : 'none' ); ?>">
				<?php echo esc_html( $ludoya_when['text'] ); ?>
			</p>

			<h2 class="ludoya-event__title"><?php echo esc_html( $ludoya_title ); ?></h2>

			<?php if ( ! empty( $event['location']['name'] ) ) : ?>
				<p class="ludoya-event__where">
					<?php echo esc_html( $event['location']['name'] ); ?>
					<?php if ( ! empty( $event['location']['address'] ) ) : ?>
						<span class="ludoya-event__address"><?php echo esc_html( $event['location']['address'] ); ?></span>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<?php if ( $ludoya_game_name && $ludoya_game_name !== $ludoya_title ) : ?>
				<p class="ludoya-event__game">
					<?php
					$ludoya_game_year = (int) ludoya_get( $event, 'game.yearPublished', 0 );
					echo esc_html( $ludoya_game_year ? sprintf( '%s (%d)', $ludoya_game_name, $ludoya_game_year ) : $ludoya_game_name );
					?>
				</p>
			<?php endif; ?>

			<p class="ludoya-card__tags">
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
							/* translators: 1: free seats, 2: total seats. */
							esc_html__( '%1$d of %2$d seats left', 'ludoya' ),
							(int) $ludoya_seats_left,
							(int) $event['capacity']
						);
						?>
					</span>
				<?php endif; ?>

				<?php foreach ( ludoya_get( $event, 'languages', array() ) as $ludoya_language ) : ?>
					<span class="ludoya-tag ludoya-tag--quiet"><?php echo esc_html( strtoupper( $ludoya_language ) ); ?></span>
				<?php endforeach; ?>
			</p>
		</div>
	</div>

	<?php if ( ! empty( $event['teacher']['name'] ) || ! empty( $event['master']['name'] ) ) : ?>
		<p class="ludoya-event__people">
			<?php if ( ! empty( $event['teacher']['name'] ) ) : ?>
				<span><?php esc_html_e( 'Teaching', 'ludoya' ); ?>: <strong><?php echo esc_html( $event['teacher']['name'] ); ?></strong></span>
			<?php endif; ?>
			<?php if ( ! empty( $event['master']['name'] ) ) : ?>
				<span><?php esc_html_e( 'Game master', 'ludoya' ); ?>: <strong><?php echo esc_html( $event['master']['name'] ); ?></strong></span>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<?php if ( ! empty( $event['description'] ) ) : ?>
		<div class="ludoya-event__description"><?php echo wp_kses_post( wpautop( $event['description'] ) ); ?></div>
	<?php endif; ?>

	<?php if ( $signup ) : ?>
		<?php echo $signup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the form template escapes its own output. ?>
	<?php elseif ( empty( $event['canceled'] ) ) : ?>
		<p>
			<a class="ludoya-button" href="<?php echo esc_url( ludoya_event_url( $event ) ); ?>">
				<?php esc_html_e( 'Sign up on Ludoya', 'ludoya' ); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
