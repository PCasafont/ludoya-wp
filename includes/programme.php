<?php
/**
 * A big event's programme, worked out from a flat list of events.
 *
 * Pure functions over API arrays: no requests and no WordPress state beyond the date helpers, so the
 * smoke test can exercise them. Fetching lives in Ludoya_Programme.
 *
 * @package Ludoya
 */

defined( 'ABSPATH' ) || exit;

/**
 * Labels for the kinds a visitor filters a programme by, in the order the filter shows them.
 *
 * Named for what the visitor came to do rather than the API's enum: someone at a fair is looking for
 * "tournaments" or "games to sign up for", not for an EventType.
 *
 * @return array EventType => label.
 */
function ludoya_programme_kinds() {
	return array(
		'TOURNAMENT'   => __( 'Tournaments', 'ludoya' ),
		'PLANNED_PLAY' => __( 'Scheduled games', 'ludoya' ),
		'PLAY_BOOTH'   => __( 'Play booths', 'ludoya' ),
		'MEETUP'       => __( 'Activities', 'ludoya' ),
	);
}

/**
 * Every event under a root, at any depth, as the programme a visitor reads.
 *
 * A festival on Ludoya is a tree: the festival, its areas (board games, role-playing, a hall), and
 * the activities inside those. The API's children endpoint answers one level, so a page that drew
 * only that level showed a fair as four area cards and none of what happens in them. This walks the
 * whole tree out of one flat list and flattens it back into what a visitor wants:
 *
 * - An event with events under it is a container, not an activity. It never appears as an entry;
 *   its activities do. A container directly under the root is an **area**, and every activity
 *   below it, however deep, belongs to that area — that is what the area filter offers.
 * - An activity that runs through the whole event (`alwaysOpen`, which has no times of its own) is
 *   not "Date not decided": it goes in `all_along`, shown above the timetable on every day.
 * - An activity in separate sittings is one entry per sitting (see ludoya_split_sittings()).
 * - Entries come back in start order, each tagged with its day and hour, ready for headings.
 *
 * The walk is guarded against a parent cycle and stops at a depth no real programme reaches, so bad
 * data ends the walk instead of hanging the page.
 *
 * @param string $root_id The event whose programme this is.
 * @param array  $events  Events as the API returns them; the root's descendants among them are used,
 *                        anything else is ignored. The root itself may or may not be present.
 * @return array{areas: array, kinds: array, days: array, all_along: array, entries: array, undated: array}
 */
function ludoya_programme_build( $root_id, $events ) {
	$children = array();
	foreach ( $events as $event ) {
		$id     = (string) ludoya_get( $event, 'id', '' );
		$parent = (string) ludoya_get( $event, 'parentId', '' );
		if ( '' !== $id && '' !== $parent && $parent !== $id ) {
			$children[ $parent ][] = $event;
		}
	}

	$by_start = static function ( $a, $b ) {
		$left  = (string) ludoya_get( $a, 'startsAt', '' );
		$right = (string) ludoya_get( $b, 'startsAt', '' );
		if ( $left === $right ) {
			return strcmp( (string) ludoya_get( $a, 'title', '' ), (string) ludoya_get( $b, 'title', '' ) );
		}
		// Undated sorts last rather than first.
		if ( '' === $left || '' === $right ) {
			return '' === $left ? 1 : -1;
		}
		return strcmp( $left, $right );
	};

	$areas     = array();
	$leaves    = array();
	$seen      = array( (string) $root_id => true );
	$max_depth = 6;

	// Depth first from the root. $area is the area an event sits in, decided at depth 1.
	$walk = static function ( $parent_id, $depth, $area ) use ( &$walk, &$children, &$seen, &$areas, &$leaves, $max_depth, $by_start ) {
		if ( $depth > $max_depth || empty( $children[ $parent_id ] ) ) {
			return;
		}
		$own = $children[ $parent_id ];
		usort( $own, $by_start );
		foreach ( $own as $event ) {
			$id = (string) $event['id'];
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;

			if ( ! empty( $children[ $id ] ) ) {
				$here = $area;
				if ( 1 === $depth ) {
					$here           = $id;
					$areas[ $id ] = array(
						'id'    => $id,
						'title' => (string) ludoya_get( $event, 'title', '' ),
						'event' => $event,
						'count' => 0,
					);
				}
				$walk( $id, $depth + 1, $here );
				continue;
			}
			if ( ! empty( $event['canceled'] ) && empty( $event['startsAt'] ) ) {
				continue;
			}
			$event['_area'] = $area;
			$leaves[]       = $event;
		}
	};
	$walk( (string) $root_id, 1, '' );

	$kind_labels = ludoya_programme_kinds();
	$kinds       = array();
	$all_along   = array();
	$undated     = array();
	$dated       = array();
	$days        = array();
	$time_format = get_option( 'time_format', 'H:i' );

	foreach ( $leaves as $leaf ) {
		$type = (string) ludoya_get( $leaf, 'type', '' );
		if ( isset( $kind_labels[ $type ] ) ) {
			$kinds[ $type ] = $kind_labels[ $type ];
		}
		if ( '' !== $leaf['_area'] && isset( $areas[ $leaf['_area'] ] ) ) {
			++$areas[ $leaf['_area'] ]['count'];
		}

		if ( ! empty( $leaf['alwaysOpen'] ) ) {
			$all_along[] = ludoya_programme_entry( $leaf, null, $time_format );
			continue;
		}

		foreach ( ludoya_split_sittings( array( $leaf ) ) as $sitting ) {
			if ( '' === (string) ludoya_get( $sitting, 'startsAt', '' ) ) {
				$undated[] = ludoya_programme_entry( $sitting, null, $time_format );
				continue;
			}
			$entry = ludoya_programme_entry( $sitting, ludoya_event_zone( $sitting ), $time_format );
			if ( null === $entry ) {
				continue;
			}
			// A two-day tournament is also on the second day: someone filtering to Sunday must find it,
			// heading the day as "continues" rather than at the hour it began the day before.
			foreach ( array_merge( array( $entry ), ludoya_programme_continuations( $sitting, $entry ) ) as $part ) {
				$dated[]                  = $part;
				$days[ $part['day_key'] ] = array(
					'key'   => $part['day_key'],
					'label' => $part['day'],
					'short' => $part['day_short'],
				);
			}
		}
	}

	usort(
		$dated,
		static function ( $a, $b ) use ( $by_start ) {
			$order = strcmp( $a['sort'], $b['sort'] );
			return 0 !== $order ? $order : $by_start( $a['event'], $b['event'] );
		}
	);
	ksort( $days );

	// Kinds in the fixed order of the labels, not in the order they turned up.
	$ordered_kinds = array();
	foreach ( $kind_labels as $type => $label ) {
		if ( isset( $kinds[ $type ] ) ) {
			$ordered_kinds[ $type ] = $label;
		}
	}

	return array(
		'areas'     => array_values( $areas ),
		'kinds'     => $ordered_kinds,
		'days'      => array_values( $days ),
		'all_along' => $all_along,
		'entries'   => $dated,
		'undated'   => $undated,
	);
}

/**
 * The time zone an event is read in: its own, else the site's.
 *
 * @param array $event Event as returned by the API.
 * @return DateTimeZone
 */
function ludoya_event_zone( $event ) {
	$tz = (string) ludoya_get( $event, 'timeZone', '' );
	if ( '' !== $tz ) {
		try {
			return new DateTimeZone( $tz );
		} catch ( Exception $e ) {
			return wp_timezone();
		}
	}
	return wp_timezone();
}

/**
 * One programme entry: the event plus what the timetable prints and filters on.
 *
 * @param array             $event       The event, or one sitting of it.
 * @param DateTimeZone|null $zone        Its zone; null for an entry with no time of its own.
 * @param string            $time_format The site's time format.
 * @return array|null Null for a start that does not parse.
 */
function ludoya_programme_entry( $event, $zone, $time_format ) {
	$entry = array(
		'event'     => $event,
		'area'      => (string) ludoya_get( $event, '_area', '' ),
		'kind'      => (string) ludoya_get( $event, 'type', '' ),
		'day_key'   => '',
		'day'       => '',
		'day_short' => '',
		'time'      => '',
		'until'     => '',
		'continues' => false,
		'sort'      => (string) ludoya_get( $event, 'startsAt', '' ),
		'ends'      => (string) ludoya_get( $event, 'endsAt', '' ),
		'search'    => ludoya_programme_search_text( $event ),
	);
	if ( null === $zone ) {
		return $entry;
	}

	try {
		$start = ( new DateTimeImmutable( (string) $event['startsAt'] ) )->setTimezone( $zone );
	} catch ( Exception $e ) {
		return null;
	}
	$entry['day_key']   = $start->format( 'Y-m-d' );
	$entry['day']       = wp_date( 'l j F', $start->getTimestamp(), $zone );
	$entry['day_short'] = wp_date( 'D j', $start->getTimestamp(), $zone );
	$entry['time']      = wp_date( $time_format, $start->getTimestamp(), $zone );

	$ends_at = (string) ludoya_get( $event, 'endsAt', '' );
	if ( '' !== $ends_at ) {
		try {
			$end = ( new DateTimeImmutable( $ends_at ) )->setTimezone( $zone );
			// The day is named only when the activity runs into another one: "until Sun 16:00".
			$entry['until'] = $end->format( 'Y-m-d' ) === $entry['day_key']
				? wp_date( $time_format, $end->getTimestamp(), $zone )
				: wp_date( 'D', $end->getTimestamp(), $zone ) . ' ' . wp_date( $time_format, $end->getTimestamp(), $zone );
		} catch ( Exception $e ) {
			$entry['until'] = '';
		}
	}
	return $entry;
}

/**
 * The later days an entry runs into, one "continues" entry each.
 *
 * Each sorts at midnight of its day, so it heads that day's timetable, and says until when it runs.
 *
 * @param array $event The event, or one sitting of it.
 * @param array $entry Its entry, from ludoya_programme_entry().
 * @return array Entries; empty for an entry within one day.
 */
function ludoya_programme_continuations( $event, $entry ) {
	if ( '' === $entry['ends'] || '' === $entry['day_key'] ) {
		return array();
	}
	$zone = ludoya_event_zone( $event );
	try {
		$end = ( new DateTimeImmutable( $entry['ends'] ) )->setTimezone( $zone );
		$day = ( new DateTimeImmutable( $entry['day_key'] . ' 00:00:00', $zone ) )->modify( '+1 day' );
	} catch ( Exception $e ) {
		return array();
	}
	$time_format = get_option( 'time_format', 'H:i' );
	$parts       = array();
	// Ends exactly at midnight: it does not run into that day. Seven days caps a bad end date.
	for ( $n = 0; $n < 7 && $day < $end; $n++ ) {
		$part              = $entry;
		$part['day_key']   = $day->format( 'Y-m-d' );
		$part['day']       = wp_date( 'l j F', $day->getTimestamp(), $zone );
		$part['day_short'] = wp_date( 'D j', $day->getTimestamp(), $zone );
		$part['time']      = '';
		$part['continues'] = true;
		$part['sort']      = $day->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
		$part['until']     = $end->format( 'Y-m-d' ) === $part['day_key']
			? wp_date( $time_format, $end->getTimestamp(), $zone )
			: wp_date( 'D', $end->getTimestamp(), $zone ) . ' ' . wp_date( $time_format, $end->getTimestamp(), $zone );
		$parts[]           = $part;
		$day               = $day->modify( '+1 day' );
	}
	return $parts;
}

/**
 * What the programme's search box matches an entry on: its title and its game, folded to lower case
 * without accents, so "cacadors" finds "Caçadors" and "borgonya" finds "Borgonya".
 *
 * @param array $event Event as returned by the API.
 * @return string
 */
function ludoya_programme_search_text( $event ) {
	$text = ludoya_get( $event, 'title', '' ) . ' ' . ludoya_get( $event, 'game.name', '' );
	return ludoya_fold( $text );
}

/**
 * Lower case, accents removed: the form both sides of a search are compared in.
 *
 * The browser side does the same with String.prototype.normalize('NFD'), so what PHP writes into
 * data-search and what the visitor types meet in one alphabet.
 *
 * @param string $text Any text.
 * @return string
 */
function ludoya_fold( $text ) {
	$text = mb_strtolower( (string) $text, 'UTF-8' );
	if ( class_exists( 'Normalizer' ) ) {
		$text = preg_replace( '/\p{Mn}+/u', '', Normalizer::normalize( $text, Normalizer::FORM_D ) );
	} else {
		$text = strtr(
			$text,
			array(
				'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a',
				'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
				'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
				'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
				'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
				'ç' => 'c', 'ñ' => 'n', 'ŀ' => 'l',
			)
		);
	}
	return trim( preg_replace( '/\s+/u', ' ', $text ) );
}

/**
 * Every game at an event, each with where it is played.
 *
 * Two sources: an activity about one game (a tournament, a scheduled game) is a place that game is
 * played; an event's own games list (a demo area's line-up, a library) is a set of games on the
 * tables there. The same game from several places is one row, listing every place, so "where can I
 * play Root?" has one answer.
 *
 * A container's list (the festival's, an area's) also carries what its activities bring, so read
 * first it would name the area as a place for every game in it. Activities go first; a container
 * only adds games none of its activities has — the festival's own library, say.
 *
 * @param array $places List of array{event: array, games: array, entry: array|null, container?: bool}
 *                      — an activity or a games-list holder, its games list (may be empty), its
 *                      programme entry when it has one (for the day and hour), and whether it is
 *                      the festival or one of its areas.
 * @return array Rows of array{game: array, places: array}, sorted by game name.
 */
function ludoya_event_games_build( $places ) {
	$rows = array();
	$add  = static function ( $game, $place ) use ( &$rows ) {
		$id = (string) ludoya_get( $game, 'id', '' );
		if ( '' === $id ) {
			return;
		}
		if ( ! isset( $rows[ $id ] ) ) {
			$rows[ $id ] = array(
				'game'   => $game,
				'places' => array(),
			);
		}
		$place_id = (string) ludoya_get( $place, 'event.id', '' ) . '|' . (string) ludoya_get( $place, 'entry.day_key', '' ) . (string) ludoya_get( $place, 'entry.time', '' );
		$rows[ $id ]['places'][ $place_id ] = $place;
	};

	foreach ( $places as $place ) {
		if ( ! empty( $place['container'] ) ) {
			continue;
		}
		$own = ludoya_get( $place, 'event.game', null );
		if ( is_array( $own ) ) {
			$add( $own, $place );
		}
		foreach ( (array) ludoya_get( $place, 'games', array() ) as $game ) {
			$add( $game, $place );
		}
	}
	foreach ( $places as $place ) {
		if ( empty( $place['container'] ) ) {
			continue;
		}
		foreach ( (array) ludoya_get( $place, 'games', array() ) as $game ) {
			if ( ! isset( $rows[ (string) ludoya_get( $game, 'id', '' ) ] ) ) {
				$add( $game, $place );
			}
		}
	}

	foreach ( $rows as &$row ) {
		$row['places'] = array_values( $row['places'] );
	}
	unset( $row );

	usort(
		$rows,
		static function ( $a, $b ) {
			return strcmp( ludoya_fold( ludoya_get( $a, 'game.name', '' ) ), ludoya_fold( ludoya_get( $b, 'game.name', '' ) ) );
		}
	);
	return $rows;
}
