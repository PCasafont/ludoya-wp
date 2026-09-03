<?php
/**
 * The organisation's game collection.
 *
 * Copy this file to `ludoya/collection.php` in your theme to change it.
 *
 * @package Ludoya
 *
 * @var array  $games            Games, as returned by the API.
 * @var int    $total_games      Total base games in the collection.
 * @var int    $total_expansions Total expansions in the collection.
 * @var string $layout           grid or list.
 * @var string $heading          Optional heading.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ludoya ludoya-collection ludoya-collection--<?php echo esc_attr( $layout ); ?>">
	<?php if ( $heading ) : ?>
		<h2 class="ludoya-heading"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>

	<p class="ludoya-collection__count">
		<?php
		printf(
			/* translators: 1: number of games, 2: number of expansions. */
			esc_html__( '%1$d games and %2$d expansions', 'ludoya' ),
			(int) $total_games,
			(int) $total_expansions
		);
		?>
	</p>

	<?php if ( empty( $games ) ) : ?>
		<p class="ludoya-empty"><?php esc_html_e( 'Nothing in the collection yet.', 'ludoya' ); ?></p>
	<?php else : ?>
		<ul class="ludoya-games">
			<?php foreach ( $games as $ludoya_game ) : ?>
				<li class="ludoya-game">
					<a href="<?php echo esc_url( ludoya_game_url( $ludoya_game ) ); ?>">
						<?php if ( ! empty( $ludoya_game['imageUrl'] ) ) : ?>
							<img src="<?php echo esc_url( $ludoya_game['imageUrl'] ); ?>" alt="" loading="lazy" />
						<?php endif; ?>
						<span class="ludoya-game__name"><?php echo esc_html( $ludoya_game['name'] ); ?></span>
					</a>
					<span class="ludoya-game__meta">
						<?php
						$ludoya_bits = array();
						if ( ! empty( $ludoya_game['yearPublished'] ) ) {
							$ludoya_bits[] = (string) (int) $ludoya_game['yearPublished'];
						}
						if ( isset( $ludoya_game['minPlayerCount'], $ludoya_game['maxPlayerCount'] ) ) {
							$ludoya_bits[] = $ludoya_game['minPlayerCount'] === $ludoya_game['maxPlayerCount']
								? sprintf(
									/* translators: %d: number of players. */
									esc_html__( '%d players', 'ludoya' ),
									(int) $ludoya_game['minPlayerCount']
								)
								: sprintf(
									/* translators: 1: minimum players, 2: maximum players. */
									esc_html__( '%1$d–%2$d players', 'ludoya' ),
									(int) $ludoya_game['minPlayerCount'],
									(int) $ludoya_game['maxPlayerCount']
								);
						}
						echo esc_html( implode( ' · ', $ludoya_bits ) );
						?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
