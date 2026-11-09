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
 * @var array  $event      The event.
 * @var string $signup     Rendered sign-up form, or an empty string.
 * @var string $back_url   Optional URL back to the events list.
 * @var array  $programme  The event's programme (see ludoya_programme_build()), when it has one.
 * @var array  $parent     The event this one is part of, when it is a sub-event.
 * @var array  $area       The festival area it is in (id and title), when its parent is one.
 * @var string $event_page URL of the page to link sub-events to, or empty to link to the Ludoya app.
 */

defined( 'ABSPATH' ) || exit;

$programme  = isset( $programme ) ? $programme : array();
$parent     = isset( $parent ) ? $parent : array();
$event_page = isset( $event_page ) ? $event_page : '';

$ludoya_parent_link = '';
if ( ! empty( $parent['id'] ) ) {
	$ludoya_parent_link = $event_page
		? add_query_arg( 'ludoya_event', rawurlencode( $parent['id'] ), $event_page )
		: ludoya_event_url( $parent );
}

$ludoya_title = isset( $event['title'] ) ? $event['title'] : '';
$ludoya_when  = ludoya_event_when(
	ludoya_get( $event, 'startsAt' ),
	ludoya_get( $event, 'endsAt' ),
	ludoya_get( $event, 'timeZone' )
);
// Something that runs all through its parent has no times of its own on purpose: "Date not decided"
// would tell a visitor it is not confirmed.
if ( ! empty( $event['alwaysOpen'] ) && empty( $event['startsAt'] ) ) {
	$ludoya_when['text'] = __( 'All through the event', 'ludoya' );
}

// An event that runs in separate sittings says so here, one line each: its own startsAt and endsAt
// only span them, and printing that span alone tells a visitor the table is open through hours it
// is not. One sitting is the ordinary case and reads exactly as it did before.
$ludoya_sittings = ludoya_split_sittings( array( $event ) );
if ( count( $ludoya_sittings ) < 2 ) {
	$ludoya_sittings = array();
}

// The event's own art, else the game's box. See the note in event-card.php on why this is empty()
// and not a comparison against '': an absent image arrives as a null.
$ludoya_image = ludoya_get( $event, 'imageUrl', '' );
if ( empty( $ludoya_image ) ) {
	$ludoya_image = ludoya_get( $event, 'game.imageUrl', '' );
}

$ludoya_game_name = ludoya_get( $event, 'game.name', '' );

// Seats only mean something when Ludoya takes the sign-ups (see ludoya_takes_signups()).
$ludoya_seats_left = null;
if ( ! empty( $event['capacity'] ) && ludoya_takes_signups( $event ) ) {
	$ludoya_seats_left = max( 0, (int) $event['capacity'] - (int) $event['participantCount'] );
}

$ludoya_map = ludoya_map_urls( ludoya_get( $event, 'location', array() ) );
?>
<?php echo ludoya_event_jsonld( $event ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode output inside a script tag. ?>
<div class="ludoya ludoya-event<?php echo ! empty( $event['canceled'] ) ? ' ludoya-event--canceled' : ''; ?>">
	<?php if ( $ludoya_parent_link ) : ?>
		<p class="ludoya-event__back">
			<a href="<?php echo esc_url( $ludoya_parent_link ); ?>">
				&larr;
				<?php
				printf(
					/* translators: %s: the parent event. */
					esc_html__( 'Part of %s', 'ludoya' ),
					esc_html( ludoya_get( $parent, 'title', '' ) )
				);
				?>
			</a>
		</p>
	<?php elseif ( $back_url ) : ?>
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
			<?php if ( empty( $ludoya_sittings ) ) : ?>
				<p class="ludoya-card__when ludoya-card__when--<?php echo esc_attr( $ludoya_when['state'] ? $ludoya_when['state'] : 'none' ); ?>">
					<time datetime="<?php echo esc_attr( ludoya_get( $event, 'startsAt', '' ) ); ?>"><?php echo esc_html( $ludoya_when['text'] ); ?></time>
				</p>
			<?php else : ?>
				<?php foreach ( $ludoya_sittings as $ludoya_sitting ) : ?>
					<?php
					$ludoya_sitting_when = ludoya_event_when(
						ludoya_get( $ludoya_sitting, 'startsAt' ),
						ludoya_get( $ludoya_sitting, 'endsAt' ),
						ludoya_get( $ludoya_sitting, 'timeZone' )
					);
					?>
					<p class="ludoya-card__when ludoya-card__when--<?php echo esc_attr( $ludoya_sitting_when['state'] ? $ludoya_sitting_when['state'] : 'none' ); ?>">
						<time datetime="<?php echo esc_attr( ludoya_get( $ludoya_sitting, 'startsAt', '' ) ); ?>"><?php echo esc_html( $ludoya_sitting_when['text'] ); ?></time>
					</p>
				<?php endforeach; ?>
			<?php endif; ?>

			<h2 class="ludoya-event__title"><?php echo esc_html( $ludoya_title ); ?></h2>

			<?php $ludoya_place = ludoya_place_label( $event ); ?>
			<?php if ( '' !== $ludoya_place ) : ?>
				<p class="ludoya-event__where">
					<?php echo esc_html( $ludoya_place ); ?>
					<?php if ( ! empty( $event['location']['address'] ) ) : ?>
						<?php if ( '' !== $ludoya_map['link'] ) : ?>
							<a class="ludoya-event__address" href="<?php echo esc_url( $ludoya_map['link'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $event['location']['address'] ); ?></a>
						<?php else : ?>
							<span class="ludoya-event__address"><?php echo esc_html( $event['location']['address'] ); ?></span>
						<?php endif; ?>
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
				<?php if ( ! empty( $area['title'] ) ) : ?>
					<span class="ludoya-tag ludoya-tag--area" data-area-name="<?php echo esc_attr( sanitize_title( $area['title'] ) ); ?>" style="--ludoya-tint: <?php echo (int) ludoya_tint( $area['id'] ); ?>"><?php echo esc_html( $area['title'] ); ?></span>
				<?php endif; ?>
				<?php // Inside a programme "Event" says nothing its parent link does not. ?>
				<?php if ( empty( $event['parentId'] ) || 'MEETUP' !== ludoya_get( $event, 'type', '' ) ) : ?>
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
					<?php // The card's words, so the list and the page agree; a few left reads louder. ?>
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

				<?php foreach ( ludoya_get( $event, 'languages', array() ) as $ludoya_language ) : ?>
					<span class="ludoya-tag ludoya-tag--quiet"><?php echo esc_html( ludoya_language_label( $ludoya_language ) ); ?></span>
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
		<div class="ludoya-event__description"><?php echo wp_kses_post( ludoya_rich_text( $event['description'] ) ); ?></div>
	<?php endif; ?>

	<?php if ( ! empty( $event['games'] ) ) : ?>
		<?php // What is on the table: a booth's titles, a demo table's line-up (LT-19). ?>
		<div class="ludoya-event__games">
			<h3 class="ludoya-subheading"><?php esc_html_e( 'Games', 'ludoya' ); ?></h3>
			<ul class="ludoya-games">
				<?php foreach ( $event['games'] as $ludoya_game ) : ?>
					<li class="ludoya-game">
						<a href="<?php echo esc_url( ludoya_game_url( $ludoya_game ) ); ?>">
							<span class="ludoya-game__cover">
								<?php if ( ! empty( $ludoya_game['imageUrl'] ) ) : ?>
									<img src="<?php echo esc_url( $ludoya_game['imageUrl'] ); ?>" alt="" loading="lazy" />
								<?php else : ?>
									<span
										class="ludoya-card__tile"
										style="--ludoya-tint: <?php echo (int) ludoya_tint( ludoya_get( $ludoya_game, 'id', $ludoya_game['name'] ) ); ?>"
										aria-hidden="true"
									><?php echo esc_html( mb_strtoupper( mb_substr( $ludoya_game['name'], 0, 1 ) ) ); ?></span>
								<?php endif; ?>
							</span>
							<span class="ludoya-game__name"><?php echo esc_html( $ludoya_game['name'] ); ?></span>
							<?php if ( ! empty( $ludoya_game['yearPublished'] ) ) : ?>
								<span class="ludoya-game__meta"><?php echo esc_html( (string) (int) $ludoya_game['yearPublished'] ); ?></span>
							<?php endif; ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $ludoya_map['embed'] ) : ?>
		<?php // The map only loads once opened: no third-party request for a visitor who never asked. ?>
		<details class="ludoya-map">
			<summary><?php esc_html_e( 'Show on the map', 'ludoya' ); ?></summary>
			<div class="ludoya-map__frame">
				<iframe
					title="<?php echo esc_attr( $ludoya_place ); ?>"
					data-src="<?php echo esc_url( $ludoya_map['embed'] ); ?>"
					referrerpolicy="no-referrer-when-downgrade"
					allowfullscreen
				></iframe>
			</div>
		</details>
	<?php endif; ?>

	<?php if ( $signup ) : ?>
		<?php echo $signup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the form template escapes its own output. ?>
	<?php elseif ( ! empty( $event['canceled'] ) ) : ?>
	<?php elseif ( 'EXTERNAL' === ludoya_get( $event, 'attendanceMode' ) && ! empty( $event['externalCtaUrl'] ) ) : ?>
		<?php // Sign-ups happen on the organizer's own form; that is where the button goes. ?>
		<p>
			<a class="ludoya-button" href="<?php echo esc_url( $event['externalCtaUrl'] ); ?>" target="_blank" rel="noopener">
				<?php echo esc_html( ! empty( $event['externalCtaLabel'] ) ? $event['externalCtaLabel'] : __( 'Sign up', 'ludoya' ) ); ?>
			</a>
		</p>
	<?php elseif ( ludoya_takes_signups( $event ) ) : ?>
		<p>
			<a class="ludoya-button" href="<?php echo esc_url( ludoya_event_url( $event ) ); ?>">
				<?php esc_html_e( 'Sign up on Ludoya', 'ludoya' ); ?>
			</a>
		</p>
	<?php else : ?>
		<?php // Nothing to sign up for: the page is the information. A quiet link, not a call to leave the site. ?>
		<p class="ludoya-event__elsewhere">
			<a href="<?php echo esc_url( ludoya_event_url( $event ) ); ?>">
				<?php esc_html_e( 'See on Ludoya', 'ludoya' ); ?>
			</a>
		</p>
	<?php endif; ?>

	<?php if ( ! empty( $programme ) ) : ?>
		<?php // The whole tree, areas and all, through the same view the programme block uses. ?>
		<div class="ludoya-event__programme">
			<?php
			echo ludoya_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes its own output.
				'programme',
				array(
					'root'         => $event,
					'programme'    => $programme,
					'event_page'   => $event_page,
					'show_filters' => true,
					'heading'      => __( 'Programme', 'ludoya' ),
					'area'         => '',
				)
			);
			?>
		</div>
	<?php endif; ?>
</div>
