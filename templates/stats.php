<?php
/**
 * Play statistics for the organisation.
 *
 * Copy this file to `ludoya/stats.php` in your theme to change it.
 *
 * @package Ludoya
 *
 * @var array  $tiles   plays, unique_games, unique_players, play_time (already formatted).
 * @var array  $by_game Most played first; each entry has game (id, slug, name, imageUrl) and plays.
 * @var string $heading Optional heading.
 */

defined( 'ABSPATH' ) || exit;

$ludoya_tiles = array(
	array( __( 'Plays', 'ludoya' ), $tiles['plays'] ),
	array( __( 'Different games', 'ludoya' ), $tiles['unique_games'] ),
	array( __( 'Players', 'ludoya' ), $tiles['unique_players'] ),
	array( __( 'Time played', 'ludoya' ), $tiles['play_time'] ),
);
?>
<div class="ludoya ludoya-stats">
	<?php if ( $heading ) : ?>
		<h2 class="ludoya-heading"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>

	<ul class="ludoya-tiles">
		<?php foreach ( $ludoya_tiles as $ludoya_tile ) : ?>
			<li class="ludoya-tile">
				<span class="ludoya-tile__value"><?php echo esc_html( (string) $ludoya_tile[1] ); ?></span>
				<span class="ludoya-tile__label"><?php echo esc_html( $ludoya_tile[0] ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php if ( ! empty( $by_game ) ) : ?>
		<h3 class="ludoya-subheading"><?php esc_html_e( 'Most played', 'ludoya' ); ?></h3>
		<ol class="ludoya-top-games">
			<?php foreach ( $by_game as $ludoya_rank => $ludoya_entry ) : ?>
				<?php $ludoya_game = isset( $ludoya_entry['game'] ) ? $ludoya_entry['game'] : array(); ?>
				<li>
					<span class="ludoya-top-games__rank" aria-hidden="true"><?php echo (int) ( $ludoya_rank + 1 ); ?></span>
					<span class="ludoya-top-games__cover">
						<?php if ( ! empty( $ludoya_game['imageUrl'] ) ) : ?>
							<img src="<?php echo esc_url( $ludoya_game['imageUrl'] ); ?>" alt="" loading="lazy" />
						<?php else : ?>
							<span
								class="ludoya-card__tile"
								style="--ludoya-tint: <?php echo (int) ludoya_tint( ludoya_get( $ludoya_game, 'id', ludoya_get( $ludoya_game, 'name', '' ) ) ); ?>"
								aria-hidden="true"
							><?php echo esc_html( mb_strtoupper( mb_substr( ludoya_get( $ludoya_game, 'name', '' ), 0, 1 ) ) ); ?></span>
						<?php endif; ?>
					</span>
					<a href="<?php echo esc_url( ludoya_game_url( $ludoya_game ) ); ?>">
						<?php echo esc_html( ludoya_get( $ludoya_game, 'name', '' ) ); ?>
					</a>
					<span class="ludoya-top-games__count">
						<?php
						printf(
							/* translators: %d: number of plays. */
							esc_html( _n( '%d play', '%d plays', (int) $ludoya_entry['plays'], 'ludoya' ) ),
							(int) $ludoya_entry['plays']
						);
						?>
					</span>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>
</div>
