<?php
/**
 * A big event's whole programme: what runs all along, then each day by hour, with filters.
 *
 * Everything is rendered on the server and readable without JavaScript. The filters (day, area,
 * kind, a search box) are hidden until the script wakes them, and only hide entries — so a visitor
 * without JavaScript, a crawler and a printout all get the full programme.
 *
 * Copy this file to `ludoya/programme.php` in your theme to change it.
 *
 * @package Ludoya
 *
 * @var array  $root         The event whose programme this is.
 * @var array  $programme    See ludoya_programme_build().
 * @var string $event_page   URL of the shared event page, or empty to link to the Ludoya app.
 * @var bool   $show_filters Whether to offer the filters.
 * @var string $heading      Optional heading.
 * @var string $area         Area id to open the programme on, or empty for every area.
 * @var array  $only_kinds   Optional. Event types to keep (TOURNAMENT, PLANNED_PLAY…); empty keeps all.
 * @var array  $area_order   Optional. Area names in the order the filter lists them.
 * @var int    $limit        Optional. Show at most this many timed entries, soonest first; 0 for all.
 */

defined( 'ABSPATH' ) || exit;

$ludoya_areas   = array();
foreach ( $programme['areas'] as $ludoya_area_row ) {
	$ludoya_areas[ $ludoya_area_row['id'] ] = $ludoya_area_row;
}
$ludoya_has_any = ! empty( $programme['entries'] ) || ! empty( $programme['all_along'] ) || ! empty( $programme['undated'] );

// One area named on the block means a page about that area: no chips, the rest never shown.
$ludoya_fixed_area = ( '' !== $area && isset( $ludoya_areas[ $area ] ) ) ? $area : '';
$only_kinds        = isset( $only_kinds ) ? array_filter( (array) $only_kinds ) : array();
$limit             = isset( $limit ) ? max( 0, (int) $limit ) : 0;
$area_order        = isset( $area_order ) ? (array) $area_order : array();
if ( ! empty( $area_order ) ) {
	// The organiser's order (the one their own menu uses), then anything unnamed in its usual order.
	$ludoya_rank = array_flip( array_map( 'ludoya_fold', $area_order ) );
	uasort(
		$ludoya_areas,
		static function ( $a, $b ) use ( $ludoya_rank ) {
			$left  = isset( $ludoya_rank[ ludoya_fold( $a['title'] ) ] ) ? $ludoya_rank[ ludoya_fold( $a['title'] ) ] : PHP_INT_MAX;
			$right = isset( $ludoya_rank[ ludoya_fold( $b['title'] ) ] ) ? $ludoya_rank[ ludoya_fold( $b['title'] ) ] : PHP_INT_MAX;
			return $left === $right ? strcmp( $a['title'], $b['title'] ) : ( $left < $right ? -1 : 1 );
		}
	);
}
$ludoya_venue      = (string) ludoya_get( $root, 'location.id', '' );

/**
 * One entry, as a filterable card.
 *
 * @param array  $entry      Programme entry.
 * @param string $when_text  What the card's date line says.
 */
$ludoya_item = static function ( $entry, $when_text ) use ( $ludoya_areas, $event_page, $ludoya_fixed_area, $ludoya_venue ) {
	$area_row = ( '' !== $entry['area'] && isset( $ludoya_areas[ $entry['area'] ] ) && '' === $ludoya_fixed_area )
		? array(
			'id'    => $entry['area'],
			'title' => $ludoya_areas[ $entry['area'] ]['title'],
		)
		: array();
	printf(
		'<div class="ludoya-programme__item" data-area="%s" data-area-name="%s" data-kind="%s" data-search="%s" data-start="%s" data-end="%s">',
		esc_attr( $entry['area'] ),
		esc_attr( '' !== $entry['area'] && isset( $ludoya_areas[ $entry['area'] ] ) ? sanitize_title( $ludoya_areas[ $entry['area'] ]['title'] ) : '' ),
		esc_attr( $entry['kind'] ),
		esc_attr( $entry['search'] ),
		esc_attr( empty( $entry['continues'] ) ? (string) ludoya_get( $entry['event'], 'startsAt', '' ) : $entry['sort'] ),
		esc_attr( '' !== $entry['ends'] ? $entry['ends'] : (string) ludoya_get( $entry['event'], 'startsAt', '' ) )
	);
	echo ludoya_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the template escapes its own output.
		'event-card',
		array(
			'event'      => $entry['event'],
			'event_page' => $event_page,
			'past'       => false,
			'when_text'    => $when_text,
			'in_programme' => true,
			'area'         => $area_row,
			'same_venue' => '' !== $ludoya_venue && (string) ludoya_get( $entry['event'], 'location.id', '' ) === $ludoya_venue,
		)
	);
	echo '</div>';
};

$ludoya_keep = static function ( $entry ) use ( $ludoya_fixed_area, $only_kinds ) {
	return ( '' === $ludoya_fixed_area || $entry['area'] === $ludoya_fixed_area )
		&& ( empty( $only_kinds ) || in_array( $entry['kind'], $only_kinds, true ) );
};
$ludoya_all_along = array_values( array_filter( $programme['all_along'], $ludoya_keep ) );
$ludoya_entries   = array_values( array_filter( $programme['entries'], $ludoya_keep ) );
$ludoya_undated   = array_values( array_filter( $programme['undated'], $ludoya_keep ) );
if ( $limit > 0 ) {
	// A short list ("what to sign up for") is about what is coming: what has started is dropped, and
	// what runs all along and what has no time yet are not the point of it.
	$ludoya_now     = gmdate( 'Y-m-d\TH:i:s\Z' );
	$ludoya_entries = array_slice(
		array_values(
			array_filter(
				$ludoya_entries,
				static function ( $entry ) use ( $ludoya_now ) {
					return empty( $entry['continues'] ) && strcmp( (string) ludoya_get( $entry['event'], 'startsAt', '' ), $ludoya_now ) > 0;
				}
			)
		),
		0,
		$limit
	);
	$ludoya_all_along = array();
	$ludoya_undated   = array();
}
$ludoya_days      = array();
foreach ( $ludoya_entries as $ludoya_entry ) {
	$ludoya_days[ $ludoya_entry['day_key'] ] = array(
		'key'   => $ludoya_entry['day_key'],
		'label' => $ludoya_entry['day'],
		'short' => $ludoya_entry['day_short'],
	);
}
$ludoya_kinds = array();
foreach ( array_merge( $ludoya_all_along, $ludoya_entries, $ludoya_undated ) as $ludoya_entry ) {
	if ( isset( $programme['kinds'][ $ludoya_entry['kind'] ] ) ) {
		$ludoya_kinds[ $ludoya_entry['kind'] ] = true;
	}
}
$ludoya_kind_labels = array_intersect_key( $programme['kinds'], $ludoya_kinds );
$ludoya_total       = count( $ludoya_all_along ) + count( $ludoya_entries ) + count( $ludoya_undated );
?>
<?php
// Today is worked out in the browser, in the event's time zone: a cached page (most hosts cache whole
// pages) would otherwise keep yesterday's "today" and offer "Now and next" on the wrong day.
?>
<div
	class="ludoya ludoya-programme"
	data-ludoya-programme
	data-tz="<?php echo esc_attr( ludoya_event_zone( $root )->getName() ); ?>"
	data-now-heading="<?php esc_attr_e( 'On right now', 'ludoya' ); ?>"
	<?php /* translators: %s: the time it ends, e.g. "13:00". */ ?>
	data-running="<?php esc_attr_e( 'On now · until %s', 'ludoya' ); ?>"
	data-started="<?php esc_attr_e( 'Already started · ask at the table', 'ludoya' ); ?>"
>
	<?php if ( $heading ) : ?>
		<h2 class="ludoya-heading"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>

	<?php if ( ! $ludoya_has_any || 0 === $ludoya_total ) : ?>
		<p class="ludoya-empty"><?php esc_html_e( 'The programme is not out yet. Check back soon.', 'ludoya' ); ?></p>
	<?php else : ?>

		<?php if ( $show_filters && $ludoya_total > 6 ) : ?>
			<div class="ludoya-programme__filters" data-filters hidden>
				<label class="ludoya-programme__search">
					<span class="screen-reader-text"><?php esc_html_e( 'Search the programme', 'ludoya' ); ?></span>
					<svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M10 2a8 8 0 0 1 6.32 12.9l5.39 5.4-1.41 1.4-5.4-5.38A8 8 0 1 1 10 2Zm0 2a6 6 0 1 0 0 12 6 6 0 0 0 0-12Z"/></svg>
					<input type="search" data-filter-search placeholder="<?php esc_attr_e( 'Search a game or an activity', 'ludoya' ); ?>" autocomplete="off" />
				</label>

				<?php if ( count( $ludoya_days ) > 1 ) : ?>
					<div class="ludoya-chips" role="group" aria-label="<?php esc_attr_e( 'Day', 'ludoya' ); ?>" data-filter="day">
						<button type="button" class="ludoya-chip" data-value="" aria-pressed="true"><?php esc_html_e( 'Every day', 'ludoya' ); ?></button>
						<?php // Shown by the script on a day of the event only: the question at the door is "what is on now". ?>
						<button type="button" class="ludoya-chip ludoya-chip--now" data-value="now" aria-pressed="false" hidden><?php esc_html_e( 'Now and next', 'ludoya' ); ?></button>
						<?php foreach ( $ludoya_days as $ludoya_day ) : ?>
							<button type="button" class="ludoya-chip" data-value="<?php echo esc_attr( $ludoya_day['key'] ); ?>" aria-pressed="false"><?php echo esc_html( ucfirst( $ludoya_day['label'] ) ); ?></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php
				$ludoya_more_rows = ( '' === $ludoya_fixed_area && count( $ludoya_areas ) > 1 ) || count( $ludoya_kind_labels ) > 1;
				// On a phone the area and kind rows fold behind one line (the script closes it there), so
				// the programme is not a screen of chips away. Open, and so always visible, on wide screens.
				?>
				<?php if ( $ludoya_more_rows ) : ?>
				<details class="ludoya-programme__more-filters" data-more-filters open>
					<summary><?php esc_html_e( 'More filters', 'ludoya' ); ?></summary>
				<?php endif; ?>
				<?php if ( '' === $ludoya_fixed_area && count( $ludoya_areas ) > 1 ) : ?>
					<div class="ludoya-chips" role="group" aria-label="<?php esc_attr_e( 'Area', 'ludoya' ); ?>" data-filter="area">
						<button type="button" class="ludoya-chip" data-value="" aria-pressed="true"><?php esc_html_e( 'Every area', 'ludoya' ); ?></button>
						<?php foreach ( $ludoya_areas as $ludoya_area_row ) : ?>
							<button
								type="button"
								class="ludoya-chip ludoya-chip--area"
								data-area-name="<?php echo esc_attr( sanitize_title( $ludoya_area_row['title'] ) ); ?>"
								style="--ludoya-tint: <?php echo (int) ludoya_tint( $ludoya_area_row['id'] ); ?>"
								data-value="<?php echo esc_attr( $ludoya_area_row['id'] ); ?>"
								aria-pressed="false"
							><?php echo esc_html( $ludoya_area_row['title'] ); ?></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( count( $ludoya_kind_labels ) > 1 ) : ?>
					<div class="ludoya-chips" role="group" aria-label="<?php esc_attr_e( 'Kind', 'ludoya' ); ?>" data-filter="kind">
						<button type="button" class="ludoya-chip" data-value="" aria-pressed="true"><?php esc_html_e( 'Every kind', 'ludoya' ); ?></button>
						<?php foreach ( $ludoya_kind_labels as $ludoya_kind => $ludoya_label ) : ?>
							<button type="button" class="ludoya-chip" data-value="<?php echo esc_attr( $ludoya_kind ); ?>" aria-pressed="false"><?php echo esc_html( $ludoya_label ); ?></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<?php if ( $ludoya_more_rows ) : ?>
				</details>
				<?php endif; ?>

				<p class="ludoya-programme__count" aria-live="polite" data-count
					data-one="<?php esc_attr_e( '1 activity', 'ludoya' ); ?>"
					<?php /* translators: %d: number of activities shown. */ ?>
					data-many="<?php esc_attr_e( '%d activities', 'ludoya' ); ?>"
				></p>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $ludoya_all_along ) ) : ?>
			<section class="ludoya-programme__section ludoya-programme__section--along" data-section>
				<h3 class="ludoya-programme__day"><?php esc_html_e( 'All through the event', 'ludoya' ); ?></h3>
				<p class="ludoya-programme__hint"><?php esc_html_e( 'Drop in whenever you like.', 'ludoya' ); ?></p>
				<?php // "Now and next" keeps this band to one line; the visitor opens it if they want it. ?>
				<button type="button" class="ludoya-button ludoya-button--quiet ludoya-programme__along-open" data-along-open hidden>
					<?php
					/* translators: %d: how many activities run all through the event. */
					printf( esc_html( _n( 'Show the %d activity', 'Show the %d activities', count( $ludoya_all_along ), 'ludoya' ) ), (int) count( $ludoya_all_along ) );
					?>
				</button>
				<?php
				// A fair's drop-in band can run to a dozen cards, two phone screens before the timetable.
				// Past six the rest fold behind a button; a filter or a search shows them all again.
				$ludoya_fold_after = 6;
				$ludoya_extra      = count( $ludoya_all_along ) - $ludoya_fold_after > 2 ? count( $ludoya_all_along ) - $ludoya_fold_after : 0;
				?>
				<div class="ludoya-events__grid"<?php echo $ludoya_extra ? ' data-folded' : ''; ?>>
					<?php
					// The section's own heading already says when: a date line on each card would repeat it.
					foreach ( $ludoya_all_along as $ludoya_index => $ludoya_entry ) {
						if ( $ludoya_extra && $ludoya_index === $ludoya_fold_after ) {
							echo '<div class="ludoya-programme__extra">';
						}
						$ludoya_item( $ludoya_entry, '' );
					}
					if ( $ludoya_extra ) {
						echo '</div>';
					}
					?>
				</div>
				<?php if ( $ludoya_extra ) : ?>
					<button type="button" class="ludoya-button ludoya-button--quiet ludoya-programme__more" data-unfold>
						<?php
						/* translators: %d: how many more activities. */
						printf( esc_html( _n( 'Show %d more', 'Show %d more', $ludoya_extra, 'ludoya' ) ), (int) $ludoya_extra );
						?>
					</button>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php
		$ludoya_open_day  = null;
		$ludoya_open_slot = null;
		foreach ( $ludoya_entries as $ludoya_entry ) {
			if ( $ludoya_entry['day_key'] !== $ludoya_open_day ) {
				if ( null !== $ludoya_open_slot ) {
					echo '</div></div>';
				}
				if ( null !== $ludoya_open_day ) {
					echo '</section>';
				}
				$ludoya_open_day  = $ludoya_entry['day_key'];
				$ludoya_open_slot = null;
				printf(
					'<section class="ludoya-programme__section" data-section data-day="%s"><h3 class="ludoya-programme__day">%s</h3>',
					esc_attr( $ludoya_entry['day_key'] ),
					esc_html( ucfirst( $ludoya_entry['day'] ) )
				);
			}
			if ( $ludoya_entry['time'] !== $ludoya_open_slot ) {
				if ( null !== $ludoya_open_slot ) {
					echo '</div></div>';
				}
				$ludoya_open_slot = $ludoya_entry['time'];
				printf(
					'<div class="ludoya-programme__slot%s" data-slot><p class="ludoya-programme__time"><time datetime="%s">%s</time></p><div class="ludoya-events__grid">',
					$ludoya_entry['continues'] ? ' ludoya-programme__slot--continues' : '',
					esc_attr( $ludoya_entry['sort'] ),
					esc_html( $ludoya_entry['continues'] ? __( 'Continues', 'ludoya' ) : $ludoya_entry['time'] )
				);
			}
			if ( $ludoya_entry['continues'] ) {
				/* translators: %s: when it ends, e.g. "16:00". */
				$ludoya_when_text = sprintf( __( 'Until %s', 'ludoya' ), $ludoya_entry['until'] );
			} else {
				$ludoya_when_text = '' !== $ludoya_entry['until']
					/* translators: 1: start time, 2: end time, maybe with its day. */
					? sprintf( __( '%1$s – %2$s', 'ludoya' ), $ludoya_entry['time'], $ludoya_entry['until'] )
					: $ludoya_entry['time'];
			}
			$ludoya_item( $ludoya_entry, $ludoya_when_text );
		}
		if ( null !== $ludoya_open_slot ) {
			echo '</div></div>';
		}
		if ( null !== $ludoya_open_day ) {
			echo '</section>';
		}
		?>

		<?php if ( ! empty( $ludoya_undated ) ) : ?>
			<section class="ludoya-programme__section" data-section>
				<h3 class="ludoya-programme__day"><?php esc_html_e( 'Time to be announced', 'ludoya' ); ?></h3>
				<div class="ludoya-events__grid">
					<?php
					foreach ( $ludoya_undated as $ludoya_entry ) {
						$ludoya_item( $ludoya_entry, __( 'Time to be announced', 'ludoya' ) );
					}
					?>
				</div>
			</section>
		<?php endif; ?>

		<div class="ludoya-programme__none" data-none hidden>
			<p><?php esc_html_e( 'Nothing in the programme matches.', 'ludoya' ); ?></p>
			<button type="button" class="ludoya-button ludoya-button--quiet" data-reset><?php esc_html_e( 'Show everything', 'ludoya' ); ?></button>
		</div>
	<?php endif; ?>
</div>
