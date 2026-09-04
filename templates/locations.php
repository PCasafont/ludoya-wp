<?php
/**
 * Where the organisation plays.
 *
 * Copy this file to `ludoya/locations.php` in your theme to change it.
 *
 * @package Ludoya
 *
 * @var array  $locations Locations, as returned by the API.
 * @var string $heading   Optional heading.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ludoya ludoya-locations">
	<?php if ( $heading ) : ?>
		<h2 class="ludoya-heading"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>

	<?php if ( empty( $locations ) ) : ?>
		<p class="ludoya-empty"><?php esc_html_e( 'No locations yet.', 'ludoya' ); ?></p>
	<?php else : ?>
		<ul class="ludoya-location-list">
			<?php foreach ( $locations as $ludoya_location ) : ?>
				<li class="ludoya-location">
					<span class="ludoya-location__media">
						<?php if ( ! empty( $ludoya_location['imageUrl'] ) ) : ?>
							<img src="<?php echo esc_url( $ludoya_location['imageUrl'] ); ?>" alt="" loading="lazy" />
						<?php else : ?>
							<span
								class="ludoya-card__tile"
								style="--ludoya-tint: <?php echo (int) ludoya_tint( ludoya_get( $ludoya_location, 'id', $ludoya_location['name'] ) ); ?>"
								aria-hidden="true"
							><?php echo esc_html( mb_strtoupper( mb_substr( $ludoya_location['name'], 0, 1 ) ) ); ?></span>
						<?php endif; ?>
					</span>
					<span class="ludoya-location__body">
						<strong><?php echo esc_html( $ludoya_location['name'] ); ?></strong>
						<?php if ( ! empty( $ludoya_location['address'] ) ) : ?>
							<span class="ludoya-location__address"><?php echo esc_html( $ludoya_location['address'] ); ?></span>
						<?php endif; ?>
						<?php if ( ! empty( $ludoya_location['capacity'] ) ) : ?>
							<span class="ludoya-card__tags">
								<span class="ludoya-tag ludoya-tag--quiet">
									<?php
									printf(
										/* translators: %d: how many people fit. */
										esc_html__( 'Room for %d', 'ludoya' ),
										(int) $ludoya_location['capacity']
									);
									?>
								</span>
							</span>
						<?php endif; ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
