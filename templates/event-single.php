<?php
/**
 * One event, in full, with its sign-up form when the site allows sign-ups.
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

$ludoya_zone = isset( $event['timeZone'] ) ? $event['timeZone'] : null;
?>
<div class="ludoya ludoya-event">
	<?php if ( $back_url ) : ?>
		<p class="ludoya-event__back"><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'All events', 'ludoya' ); ?></a></p>
	<?php endif; ?>

	<?php if ( ! empty( $event['imageUrl'] ) ) : ?>
		<img class="ludoya-event__image" src="<?php echo esc_url( $event['imageUrl'] ); ?>" alt="" />
	<?php endif; ?>

	<p class="ludoya-card__meta">
		<span class="ludoya-badge"><?php echo esc_html( ludoya_event_type_label( isset( $event['type'] ) ? $event['type'] : '' ) ); ?></span>
		<?php if ( ! empty( $event['canceled'] ) ) : ?>
			<span class="ludoya-badge ludoya-badge--canceled"><?php esc_html_e( 'Cancelled', 'ludoya' ); ?></span>
		<?php endif; ?>
	</p>

	<h2 class="ludoya-event__title"><?php echo esc_html( isset( $event['title'] ) ? $event['title'] : '' ); ?></h2>

	<ul class="ludoya-event__facts">
		<?php if ( ! empty( $event['startsAt'] ) ) : ?>
			<li>
				<strong><?php esc_html_e( 'Starts', 'ludoya' ); ?>:</strong>
				<time datetime="<?php echo esc_attr( $event['startsAt'] ); ?>"><?php echo esc_html( ludoya_format_date( $event['startsAt'], $ludoya_zone ) ); ?></time>
			</li>
		<?php endif; ?>
		<?php if ( ! empty( $event['endsAt'] ) ) : ?>
			<li>
				<strong><?php esc_html_e( 'Ends', 'ludoya' ); ?>:</strong>
				<time datetime="<?php echo esc_attr( $event['endsAt'] ); ?>"><?php echo esc_html( ludoya_format_date( $event['endsAt'], $ludoya_zone ) ); ?></time>
			</li>
		<?php endif; ?>
		<li>
			<strong><?php esc_html_e( 'Signed up', 'ludoya' ); ?>:</strong>
			<?php
			echo esc_html(
				empty( $event['capacity'] )
					? (string) (int) $event['participantCount']
					: sprintf( '%d / %d', (int) $event['participantCount'], (int) $event['capacity'] )
			);
			?>
		</li>
		<?php if ( ! empty( $event['location']['name'] ) ) : ?>
			<li>
				<strong><?php esc_html_e( 'Where', 'ludoya' ); ?>:</strong>
				<?php echo esc_html( $event['location']['name'] ); ?>
				<?php if ( ! empty( $event['location']['address'] ) ) : ?>
					<span class="ludoya-event__address"><?php echo esc_html( $event['location']['address'] ); ?></span>
				<?php endif; ?>
			</li>
		<?php endif; ?>
		<?php if ( ! empty( $event['game']['name'] ) ) : ?>
			<li>
				<strong><?php esc_html_e( 'Game', 'ludoya' ); ?>:</strong>
				<a href="<?php echo esc_url( ludoya_game_url( $event['game'] ) ); ?>"><?php echo esc_html( $event['game']['name'] ); ?></a>
			</li>
		<?php endif; ?>
		<?php if ( ! empty( $event['teacher']['name'] ) ) : ?>
			<li><strong><?php esc_html_e( 'Teaching', 'ludoya' ); ?>:</strong> <?php echo esc_html( $event['teacher']['name'] ); ?></li>
		<?php endif; ?>
		<?php if ( ! empty( $event['master']['name'] ) ) : ?>
			<li><strong><?php esc_html_e( 'Game master', 'ludoya' ); ?>:</strong> <?php echo esc_html( $event['master']['name'] ); ?></li>
		<?php endif; ?>
	</ul>

	<?php if ( ! empty( $event['description'] ) ) : ?>
		<div class="ludoya-event__description"><?php echo wp_kses_post( wpautop( $event['description'] ) ); ?></div>
	<?php endif; ?>

	<?php if ( $signup ) : ?>
		<?php echo $signup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the form template escapes its own output. ?>
	<?php else : ?>
		<p>
			<a class="ludoya-button" href="<?php echo esc_url( ludoya_event_url( $event ) ); ?>">
				<?php esc_html_e( 'Sign up on Ludoya', 'ludoya' ); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
