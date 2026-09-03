<?php
/**
 * Events list.
 *
 * Copy this file to `ludoya/events-list.php` in your theme to change it.
 *
 * @package Ludoya
 *
 * @var array  $future_events Upcoming events.
 * @var array  $past_events   Past events, when the shortcode asked for any.
 * @var string $layout        cards or list.
 * @var string $event_page    URL of a page carrying [ludoya_event], or empty to link to ludoya.com.
 * @var string $heading       Optional heading.
 * @var string $empty         Text shown when there is nothing to list.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ludoya ludoya-events ludoya-events--<?php echo esc_attr( $layout ); ?>">
	<?php if ( $heading ) : ?>
		<h2 class="ludoya-heading"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>

	<?php if ( empty( $future_events ) && empty( $past_events ) ) : ?>
		<p class="ludoya-empty"><?php echo esc_html( $empty ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $future_events ) ) : ?>
		<div class="ludoya-events__grid">
			<?php foreach ( $future_events as $event ) : ?>
				<?php
				echo ludoya_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes its own output.
					'event-card',
					array(
						'event'      => $event,
						'event_page' => $event_page,
						'past'       => false,
					)
				);
				?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $past_events ) ) : ?>
		<h3 class="ludoya-subheading"><?php esc_html_e( 'Past events', 'ludoya' ); ?></h3>
		<div class="ludoya-events__grid ludoya-events__grid--past">
			<?php foreach ( $past_events as $event ) : ?>
				<?php
				echo ludoya_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes its own output.
					'event-card',
					array(
						'event'      => $event,
						'event_page' => $event_page,
						'past'       => true,
					)
				);
				?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
