<?php
/**
 * Play statistics for the organisation.
 *
 * Copy this file to `ludoya/stats.php` in your theme to change it.
 *
 * @package Ludoya
 *
 * @var array  $stats   The aggregated play stats.
 * @var array  $by_game Per-game stats, most played first.
 * @var string $heading Optional heading.
 */

defined( 'ABSPATH' ) || exit;

$ludoya_tiles = array(
	array( __( 'Plays', 'ludoya' ), isset( $stats['totalPlayCount'] ) ? (int) $stats['totalPlayCount'] : 0 ),
	array( __( 'Different games', 'ludoya' ), isset( $stats['uniqueGames'] ) ? (int) $stats['uniqueGames'] : 0 ),
	array( __( 'Players', 'ludoya' ), isset( $stats['uniquePlayers'] ) ? (int) $stats['uniquePlayers'] : 0 ),
	// The API sends durations already formatted for reading, e.g. "12h 30m".
	array( __( 'Time played', 'ludoya' ), isset( $stats['totalPlayTime'] ) ? $stats['totalPlayTime'] : '' ),
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
				<li>
					<span class="ludoya-top-games__rank" aria-hidden="true"><?php echo (int) ( $ludoya_rank + 1 ); ?></span>
					<a href="<?php echo esc_url( ludoya_game_url( isset( $ludoya_entry['game'] ) ? $ludoya_entry['game'] : array() ) ); ?>">
						<?php echo esc_html( ludoya_get( $ludoya_entry, 'game.name', '' ) ); ?>
					</a>
					<span class="ludoya-top-games__count">
						<?php
						$ludoya_plays = (int) ludoya_get( $ludoya_entry, 'stats.totalPlayCount', 0 );
						printf(
							/* translators: %d: number of plays. */
							esc_html( _n( '%d play', '%d plays', $ludoya_plays, 'ludoya' ) ),
							(int) $ludoya_plays
						);
						?>
					</span>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>
</div>
