<?php
/**
 * Every game at an event, each with where it is played.
 *
 * The question a visitor brings to a fair's site is "can I play X there, and where?". So each game
 * lists its places: the tournament, the scheduled game, the demo area, each with its day and hour
 * and a link to it.
 *
 * Copy this file to `ludoya/event-games.php` in your theme to change it.
 *
 * @package Ludoya
 *
 * @var array  $rows       See ludoya_event_games_build().
 * @var array  $root       The event.
 * @var string $event_page URL of the shared event page, or empty to link to the Ludoya app.
 * @var string $heading    Optional heading.
 */

defined( 'ABSPATH' ) || exit;

$ludoya_root_id = (string) ludoya_get( $root, 'id', '' );
?>
<div class="ludoya ludoya-event-games" data-ludoya-games>
	<?php if ( $heading ) : ?>
		<h2 class="ludoya-heading"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>

	<?php if ( empty( $rows ) ) : ?>
		<p class="ludoya-empty"><?php esc_html_e( 'The games are not announced yet.', 'ludoya' ); ?></p>
	<?php else : ?>
		<?php if ( count( $rows ) > 8 ) : ?>
			<div class="ludoya-programme__filters" data-filters hidden>
				<label class="ludoya-programme__search">
					<span class="screen-reader-text"><?php esc_html_e( 'Search the games', 'ludoya' ); ?></span>
					<svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M10 2a8 8 0 0 1 6.32 12.9l5.39 5.4-1.41 1.4-5.4-5.38A8 8 0 1 1 10 2Zm0 2a6 6 0 1 0 0 12 6 6 0 0 0 0-12Z"/></svg>
					<input type="search" data-filter-search placeholder="<?php esc_attr_e( 'Search a game', 'ludoya' ); ?>" autocomplete="off" />
				</label>
				<p class="ludoya-programme__count" aria-live="polite" data-count
					data-one="<?php esc_attr_e( '1 game', 'ludoya' ); ?>"
					<?php /* translators: %d: number of games shown. */ ?>
					data-many="<?php esc_attr_e( '%d games', 'ludoya' ); ?>"
				></p>
			</div>
		<?php endif; ?>

		<ul class="ludoya-event-games__grid">
			<?php foreach ( $rows as $ludoya_row ) : ?>
				<?php $ludoya_game = $ludoya_row['game']; ?>
				<li class="ludoya-event-game ludoya-programme__item" data-search="<?php echo esc_attr( ludoya_fold( ludoya_get( $ludoya_game, 'name', '' ) ) ); ?>">
					<a class="ludoya-event-game__cover" href="<?php echo esc_url( ludoya_game_url( $ludoya_game ) ); ?>" target="_blank" rel="noopener" tabindex="-1" aria-hidden="true">
						<?php if ( ! empty( $ludoya_game['imageUrl'] ) ) : ?>
							<img src="<?php echo esc_url( $ludoya_game['imageUrl'] ); ?>" alt="" loading="lazy" />
						<?php else : ?>
							<span class="ludoya-card__tile" style="--ludoya-tint: <?php echo (int) ludoya_tint( ludoya_get( $ludoya_game, 'id', '' ) ); ?>"><?php echo esc_html( mb_strtoupper( mb_substr( (string) ludoya_get( $ludoya_game, 'name', '' ), 0, 1 ) ) ); ?></span>
						<?php endif; ?>
					</a>
					<div class="ludoya-event-game__body">
						<a class="ludoya-event-game__name" href="<?php echo esc_url( ludoya_game_url( $ludoya_game ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) ludoya_get( $ludoya_game, 'name', '' ) ); ?></a>
						<?php
						// A game played at a dozen sessions would be a column of a dozen links; three
						// say where it is, the rest fold away behind a count.
						$ludoya_shown = array_slice( $ludoya_row['places'], 0, 3 );
						$ludoya_more  = array_slice( $ludoya_row['places'], 3 );
						?>
						<?php foreach ( array( $ludoya_shown, $ludoya_more ) as $ludoya_part => $ludoya_places ) : ?>
							<?php if ( empty( $ludoya_places ) ) { continue; } ?>
							<?php if ( 1 === $ludoya_part ) : ?>
								<details class="ludoya-event-game__more">
									<summary>
										<?php
										/* translators: %d: how many more sessions or tables. */
										printf( esc_html( _n( '%d more', '%d more', count( $ludoya_places ), 'ludoya' ) ), count( $ludoya_places ) );
										?>
									</summary>
							<?php endif; ?>
						<ul class="ludoya-event-game__places">
							<?php foreach ( $ludoya_places as $ludoya_place ) : ?>
								<?php
								$ludoya_place_event = $ludoya_place['event'];
								$ludoya_is_root     = ! empty( $ludoya_place['container'] ) || (string) ludoya_get( $ludoya_place_event, 'id', '' ) === $ludoya_root_id;
								$ludoya_entry       = $ludoya_place['entry'];
								$ludoya_when        = '';
								if ( is_array( $ludoya_entry ) && '' !== $ludoya_entry['time'] ) {
									$ludoya_when = ucfirst( $ludoya_entry['day_short'] ) . ' ' . $ludoya_entry['time'];
								}
								$ludoya_href = $event_page
									? add_query_arg( 'ludoya_event', rawurlencode( (string) $ludoya_place_event['id'] ), $event_page )
									: ludoya_event_url( $ludoya_place_event );
								?>
								<li>
									<?php if ( $ludoya_is_root ) : ?>
										<span><?php esc_html_e( 'At the event', 'ludoya' ); ?></span>
									<?php else : ?>
										<a href="<?php echo esc_url( $ludoya_href ); ?>"><?php echo esc_html( (string) ludoya_get( $ludoya_place_event, 'title', '' ) ); ?></a>
										<?php if ( '' !== $ludoya_when ) : ?>
											<span class="ludoya-event-game__when"><?php echo esc_html( $ludoya_when ); ?></span>
										<?php endif; ?>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
							<?php if ( 1 === $ludoya_part ) : ?>
								</details>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
		<div class="ludoya-programme__none" data-none hidden>
			<p><?php esc_html_e( 'No game by that name at this event.', 'ludoya' ); ?></p>
			<button type="button" class="ludoya-button ludoya-button--quiet" data-reset><?php esc_html_e( 'Show every game', 'ludoya' ); ?></button>
		</div>
	<?php endif; ?>
</div>
