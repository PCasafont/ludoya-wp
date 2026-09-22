<?php
/**
 * Small helpers shared by the admin screens, the shortcodes and the templates.
 *
 * @package Ludoya
 */

defined( 'ABSPATH' ) || exit;

/**
 * The capability required to administer Ludoya from wp-admin.
 *
 * @return string
 */
function ludoya_capability() {
	return apply_filters( 'ludoya_capability', 'manage_options' );
}

/**
 * Public URL of an event on ludoya.com.
 *
 * Takes the whole event where there is one, so the link can use the readable slug; the id still
 * resolves, which is what the edit screen has to fall back on before an event is loaded.
 *
 * @param array|string $event Event as returned by the API, or a bare event id.
 * @return string
 */
function ludoya_event_url( $event ) {
	if ( is_array( $event ) ) {
		$ref = ludoya_get( $event, 'slug', '' );
		if ( '' === $ref ) {
			$ref = ludoya_get( $event, 'id', '' );
		}
	} else {
		$ref = (string) $event;
	}
	return trailingslashit( Ludoya_Settings::site_base() ) . 'events/' . rawurlencode( $ref );
}

/**
 * Public URL of a board game in the Ludoya app.
 *
 * The app's route is `g/{slug}` — taken from its router, not guessed: a made-up path would fall
 * through to the profile catch-all and render "user not found".
 *
 * @param array $game Game as returned by the API.
 * @return string
 */
function ludoya_game_url( $game ) {
	$slug = isset( $game['slug'] ) ? $game['slug'] : ( isset( $game['id'] ) ? $game['id'] : '' );
	return trailingslashit( Ludoya_Settings::site_base() ) . 'g/' . rawurlencode( $slug );
}

/**
 * Render an ISO-8601 instant in the site's locale and timezone.
 *
 * The API answers UTC instants plus the event's own time zone; the event's zone is the one that
 * tells the reader when to turn up, so it wins over the WordPress setting when present.
 *
 * @param string|null $instant  ISO-8601 instant, e.g. 2026-12-05T17:00:00Z.
 * @param string|null $timezone Olson id from the event, optional.
 * @param string|null $format   PHP date format; defaults to the site's date + time format.
 * @return string
 */
function ludoya_format_date( $instant, $timezone = null, $format = null ) {
	if ( empty( $instant ) ) {
		return '';
	}
	try {
		$date = new DateTimeImmutable( $instant );
	} catch ( Exception $e ) {
		return '';
	}
	$zone = null;
	if ( ! empty( $timezone ) ) {
		try {
			$zone = new DateTimeZone( $timezone );
		} catch ( Exception $e ) {
			$zone = null;
		}
	}
	if ( null === $zone ) {
		$zone = wp_timezone();
	}
	if ( null === $format ) {
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
	}
	return wp_date( $format, $date->getTimestamp(), $zone );
}

/**
 * When an event happens, said the way the Ludoya app says it.
 *
 * "Today 18:00 - 20:00", "Tomorrow 17:00", "Sat 21 Sep 18:00 - 20:00". The year is dropped for the
 * current one, and a second day is only named when the event actually spans one. The returned state
 * — today, soon, now, past — is what colours the line, matching the app's --time-*-color tokens.
 *
 * @param string|null $starts_at ISO-8601 instant.
 * @param string|null $ends_at   ISO-8601 instant, optional.
 * @param string|null $timezone  Olson id from the event.
 * @return array text and state.
 */
function ludoya_event_when( $starts_at, $ends_at = null, $timezone = null ) {
	if ( empty( $starts_at ) ) {
		return array(
			'text'  => __( 'Date not decided', 'ludoya' ),
			'state' => '',
		);
	}

	$zone = null;
	if ( ! empty( $timezone ) ) {
		try {
			$zone = new DateTimeZone( $timezone );
		} catch ( Exception $e ) {
			$zone = null;
		}
	}
	if ( null === $zone ) {
		$zone = wp_timezone();
	}

	try {
		$start = ( new DateTimeImmutable( $starts_at ) )->setTimezone( $zone );
		$end   = empty( $ends_at ) ? null : ( new DateTimeImmutable( $ends_at ) )->setTimezone( $zone );
	} catch ( Exception $e ) {
		return array(
			'text'  => '',
			'state' => '',
		);
	}

	$time_format = get_option( 'time_format', 'H:i' );
	$now         = new DateTimeImmutable( 'now', $zone );
	$today       = $now->setTime( 0, 0 );
	$start_day   = $start->setTime( 0, 0 );
	$days        = (int) $today->diff( $start_day )->format( '%r%a' );

	if ( 0 === $days ) {
		$day   = __( 'Today', 'ludoya' );
		$state = 'today';
	} elseif ( 1 === $days ) {
		$day   = __( 'Tomorrow', 'ludoya' );
		$state = 'soon';
	} else {
		$same_year = ( $start->format( 'Y' ) === $now->format( 'Y' ) );
		$day       = wp_date( $same_year ? 'D j M' : 'D j M Y', $start->getTimestamp(), $zone );
		$state     = '';
	}

	$text = $day . ' ' . wp_date( $time_format, $start->getTimestamp(), $zone );
	if ( $end ) {
		$text .= ' – ';
		if ( $start->format( 'Y-m-d' ) !== $end->format( 'Y-m-d' ) ) {
			$same_year = ( $end->format( 'Y' ) === $now->format( 'Y' ) );
			$text     .= wp_date( $same_year ? 'D j M' : 'D j M Y', $end->getTimestamp(), $zone ) . ' ';
		}
		$text .= wp_date( $time_format, $end->getTimestamp(), $zone );
	}

	// Running and finished beat the day label: "Today" on something that ended this morning reads
	// as an invitation.
	$finish = $end ? $end : $start->setTime( 23, 59 );
	if ( $now > $finish ) {
		$state = 'past';
	} elseif ( $now >= $start ) {
		$state = 'now';
	}

	return array(
		'text'  => $text,
		'state' => $state,
	);
}

/**
 * A stable tint for an event with no image of its own, so a list of them looks composed rather than
 * random. Hues are the app's banner palette; the id picks one and always picks the same one.
 *
 * @param string $seed Event id.
 * @return int Hue in degrees.
 */
function ludoya_tint( $seed ) {
	$hues = array( 222, 174, 142, 32, 78, 330, 45, 262 );
	return $hues[ hexdec( substr( md5( (string) $seed ), 0, 4 ) ) % count( $hues ) ];
}

/**
 * Human label for an event type.
 *
 * @param string $type EventType value.
 * @return string
 */
function ludoya_event_type_label( $type ) {
	$labels = array(
		// The enum names are the API's; the labels are the ones the Ludoya app shows, which is what
		// a reader recognises. MEETUP is simply "Event" there, and PLANNED_PLAY a "Scheduled game".
		'MEETUP'       => __( 'Event', 'ludoya' ),
		'PLANNED_PLAY' => __( 'Scheduled game', 'ludoya' ),
		'TOURNAMENT'   => __( 'Tournament', 'ludoya' ),
		'PLAY_BOOTH'   => __( 'Play booth', 'ludoya' ),
	);
	return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
}

/**
 * Free text the way the Ludoya app shows it: paragraphs, links you can click, and the markdown
 * people actually type — **bold**, *italic*, headings and lists.
 *
 * The app renders descriptions as markdown and links bare URLs; the plugin printed the same text
 * verbatim, so a URL an organizer pasted arrived as dead text and "**important**" arrived with its
 * asterisks (LT-21). The text is escaped first and this only ever emits a fixed set of tags, so
 * nothing an author types becomes markup the escaping removed. Deliberately not a full parser:
 * anything it does not recognise stays as typed.
 *
 * @param string $text Raw text from the API.
 * @return string HTML: p, br, strong, em, s, code, a, ul, ol, li, h4–h6.
 */
function ludoya_rich_text( $text ) {
	$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
	if ( '' === trim( $text ) ) {
		return '';
	}

	$blocks    = array();
	$paragraph = array();
	$list      = null;

	$close_paragraph = static function () use ( &$blocks, &$paragraph ) {
		if ( ! empty( $paragraph ) ) {
			$blocks[]  = '<p>' . implode( '<br>', $paragraph ) . '</p>';
			$paragraph = array();
		}
	};
	$close_list = static function () use ( &$blocks, &$list ) {
		if ( null !== $list ) {
			$blocks[] = '</' . $list . '>';
			$list     = null;
		}
	};

	foreach ( explode( "\n", $text ) as $line ) {
		if ( '' === trim( $line ) ) {
			$close_paragraph();
			$close_list();
			continue;
		}
		if ( preg_match( '/^\s*([-*]|\d+[.)])\s+(.*)$/', $line, $m ) ) {
			$tag = ctype_digit( $m[1][0] ) ? 'ol' : 'ul';
			$close_paragraph();
			if ( $list !== $tag ) {
				$close_list();
				$blocks[] = '<' . $tag . '>';
				$list     = $tag;
			}
			$blocks[] = '<li>' . ludoya_rich_inline( $m[2] ) . '</li>';
			continue;
		}
		$close_list();
		if ( preg_match( '/^\s*(#{1,3})\s+(.*)$/', $line, $m ) ) {
			$close_paragraph();
			// The same `#` has to come out the same size here as it does in Ludoya itself, where
			// these descriptions are written: `#` is an h3 there, so it is an h3 here. They still
			// sit under the page's own h1/h2, and h6 is the floor.
			$level    = min( strlen( $m[1] ) + 2, 6 );
			$blocks[] = '<h' . $level . '>' . ludoya_rich_inline( $m[2] ) . '</h' . $level . '>';
			continue;
		}
		$paragraph[] = ludoya_rich_inline( $line );
	}
	$close_paragraph();
	$close_list();

	return implode( "\n", $blocks );
}

/**
 * One line of {@see ludoya_rich_text()}: escapes it, then applies the character-level markers.
 *
 * Links first, and out of the way — a bare-URL linker that ran over "[read more](https://…)" would
 * link the address inside the parentheses and the markdown link would never form.
 *
 * @param string $line One line of raw text.
 * @return string HTML.
 */
function ludoya_rich_inline( $line ) {
	$line   = esc_html( trim( $line ) );
	$stash  = array();
	$keep   = static function ( $html ) use ( &$stash ) {
		$stash[] = $html;
		return "\x1A" . ( count( $stash ) - 1 ) . "\x1A";
	};
	$anchor = static function ( $href, $text ) {
		return '<a href="' . esc_url( $href ) . '" target="_blank" rel="noopener">' . $text . '</a>';
	};

	// `code` is literal: nothing inside it is a marker.
	$line = preg_replace_callback(
		'/`([^`]+)`/',
		static function ( $m ) use ( $keep ) {
			return $keep( '<code>' . $m[1] . '</code>' );
		},
		$line
	);
	$line = preg_replace_callback(
		'/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/',
		static function ( $m ) use ( $keep, $anchor ) {
			return $keep( $anchor( $m[2], $m[1] ) );
		},
		$line
	);
	// A trailing full stop or bracket belongs to the sentence, not the address.
	$line = preg_replace_callback(
		'/https?:\/\/[^\s<>"\']+/',
		static function ( $m ) use ( $keep, $anchor ) {
			$url   = rtrim( $m[0], '.,;:!?)' );
			$after = substr( $m[0], strlen( $url ) );
			return $keep( $anchor( $url, $url ) ) . $after;
		},
		$line
	);

	// Each marker needs a non-space character right inside it, the way markdown does.
	$line = preg_replace( '/\*\*(\S(?:[^*\n]*\S)?)\*\*/', '<strong>$1</strong>', $line );
	$line = preg_replace( '/~~(\S(?:[^~\n]*\S)?)~~/', '<s>$1</s>', $line );
	$line = preg_replace( '/(^|[^*\w])\*(\S(?:[^*\n]*\S)?)\*(?![*\w])/', '$1<em>$2</em>', $line );
	$line = preg_replace( '/(^|[^_\w])_(\S(?:[^_\n]*\S)?)_(?![_\w])/', '$1<em>$2</em>', $line );

	return preg_replace_callback(
		"/\x1A(\d+)\x1A/",
		static function ( $m ) use ( $stash ) {
			return $stash[ (int) $m[1] ];
		},
		$line
	);
}

/**
 * Whether Ludoya takes this event's sign-ups.
 *
 * An event run without sign-ups (a festival programme, an open table) has a participant count of
 * zero that means nothing, and printing "0 going" on it reads as "nobody is coming" (LT-18). The
 * field is absent from older API builds, which only ever served RSVP events.
 *
 * @param array $event The event, as returned by the API.
 * @return bool
 */
function ludoya_takes_signups( $event ) {
	$mode = (string) ludoya_get( $event, 'attendanceMode', 'RSVP' );
	return '' === $mode || 'RSVP' === $mode;
}

/**
 * Where a venue is on a map: a link to open, and an embed to show in place.
 *
 * Coordinates when the venue has them, else the address as a search — a geocoded pin is exact, an
 * address search is what a visitor would type anyway. Keyless Google Maps URLs, so a site needs no
 * API key; the embed is only loaded once the visitor opens it (see assets/js/ludoya.js).
 *
 * @param array $location The venue, as the API returns it.
 * @return array{link:string,embed:string} Both empty when the venue has neither.
 */
function ludoya_map_urls( $location ) {
	$lat = ludoya_get( $location, 'latitude' );
	$lng = ludoya_get( $location, 'longitude' );
	if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
		$query = $lat . ',' . $lng;
	} else {
		$query = trim( (string) ludoya_get( $location, 'address', '' ) );
		$name  = trim( (string) ludoya_get( $location, 'name', '' ) );
		if ( '' === $query ) {
			return array( 'link' => '', 'embed' => '' );
		}
		// The venue's name narrows a street address to the building: "Centre Cívic, Carrer Major 1".
		if ( '' !== $name && false === stripos( $query, $name ) ) {
			$query = $name . ', ' . $query;
		}
	}
	return array(
		'link'  => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $query ),
		'embed' => 'https://maps.google.com/maps?q=' . rawurlencode( $query ) . '&z=16&output=embed',
	);
}

/**
 * Every event type, for the admin select.
 *
 * @return array
 */
function ludoya_event_types() {
	return array( 'MEETUP', 'PLANNED_PLAY', 'TOURNAMENT', 'PLAY_BOOTH' );
}

/**
 * How a play booth lays its sessions out. Labels and hints follow the Ludoya app's own wording.
 *
 * @return array
 */
function ludoya_booth_planning_modes() {
	return array(
		'GRID'      => __( 'Grid — fixed time slots', 'ludoya' ),
		'FREE_FORM' => __( 'Free form — games start any time', 'ludoya' ),
	);
}

/**
 * How interest at a booth becomes a seated game.
 *
 * @return array
 */
function ludoya_booth_arrange_modes() {
	return array(
		'AUTO'    => __( 'Auto — start a game as soon as enough players are free', 'ludoya' ),
		'PROPOSE' => __( 'Propose — suggest games for staff to confirm', 'ludoya' ),
		'MANUAL'  => __( 'Manual — staff seat every game', 'ludoya' ),
	);
}

/**
 * Tournament formats, as the Ludoya app names them.
 *
 * @return array
 */
function ludoya_tournament_formats() {
	return array(
		'SWISS'       => __( 'Swiss', 'ludoya' ),
		'MIXER'       => __( 'Mixer', 'ludoya' ),
		'SINGLE_ELIM' => __( 'Single elimination', 'ludoya' ),
		'DOUBLE_ELIM' => __( 'Double elimination', 'ludoya' ),
	);
}

/**
 * Tiebreakers, in the order the Ludoya app offers them — which is also the order they apply in.
 *
 * @return array
 */
function ludoya_tiebreakers() {
	return array(
		'BUCHHOLZ'      => __( 'Buchholz', 'ludoya' ),
		'HEAD_TO_HEAD'  => __( 'Head-to-head', 'ludoya' ),
		'RAW_SCORE_SUM' => __( 'Raw score sum', 'ludoya' ),
		'FIRST_PLACES'  => __( 'First places', 'ludoya' ),
	);
}

/**
 * What a shared placement pays, when two players finish level in one game.
 *
 * @return array
 */
function ludoya_shared_rank_policies() {
	return array(
		'HIGHER'  => __( 'Higher placement points', 'ludoya' ),
		'AVERAGE' => __( 'Average placement points', 'ludoya' ),
	);
}

/**
 * Whether an event may carry sub-events of its own.
 *
 * The API lets any event be a parent, but two kinds never make sense as one: a scheduled game is
 * one table for one evening, and a play booth only takes the sessions it books itself. Offering
 * "Add sub-event" on those would produce a form the API then refuses.
 *
 * @param array $event Event as returned by the API.
 * @return bool
 */
function ludoya_can_host_sub_events( $event ) {
	$type = isset( $event['type'] ) ? $event['type'] : '';
	return in_array( $type, array( 'MEETUP', 'TOURNAMENT' ), true ) && empty( $event['canceled'] );
}

/**
 * One entry per sitting, for an event that runs in more than one.
 *
 * A demo table open 12:00-14:00 and again 18:00-20:00 is a single event whose `schedule` carries
 * both sittings; its own startsAt and endsAt only span them. Drawn as one card it claims the floor
 * for the six hours in between and, worse, never appears under the hour its second sitting starts,
 * so a reader looking at 18:00 is told the table is not running. Each sitting becomes its own
 * entry instead, all of them pointing back at the same event.
 *
 * An event with no schedule, or one whose slots carry no start, is returned untouched.
 *
 * @param array $events Events as returned by the API.
 * @return array
 */
function ludoya_split_sittings( $events ) {
	$split = array();
	foreach ( $events as $event ) {
		$slots = ludoya_get( $event, 'schedule', array() );
		if ( ! is_array( $slots ) || empty( $slots ) ) {
			$split[] = $event;
			continue;
		}

		$sittings = array();
		foreach ( $slots as $slot ) {
			$start = (string) ludoya_get( $slot, 'start', '' );
			if ( '' === $start ) {
				continue;
			}
			$sitting             = $event;
			$sitting['startsAt'] = $start;
			$sitting['endsAt']   = (string) ludoya_get( $slot, 'end', '' );
			$sittings[]          = $sitting;
		}

		// Nothing usable in the schedule: the event still belongs on the programme, as it was.
		$split = array_merge( $split, empty( $sittings ) ? array( $event ) : $sittings );
	}
	return $split;
}

/**
 * The programme's entries, each tagged with the day and the time it starts.
 *
 * A festival's programme is forty cards long and every one of them repeats its own date, which
 * leaves a wall with nothing to navigate by. The app's schedule puts a divider at each start hour;
 * these tags are what let the template do the same — it prints a heading whenever the day changes
 * and a time whenever the hour does, so the reader scans headings instead of cards.
 *
 * An event that runs in separate sittings contributes one entry per sitting (see
 * ludoya_split_sittings), so entries are put back in start order here: the dividers only mean
 * anything on a list that runs forwards. Anything without a start time sorts last under its own
 * heading, since a card with no hour cannot join one.
 *
 * @param array $events Events as returned by the API.
 * @return array List of array{event: array, day_key: string, day: string, time: string}.
 */
function ludoya_programme_entries( $events ) {
	$dated   = array();
	$undated = array();
	$now     = null;

	foreach ( ludoya_split_sittings( $events ) as $event ) {
		$starts_at = (string) ludoya_get( $event, 'startsAt', '' );
		if ( '' === $starts_at ) {
			$undated[] = array(
				'event'   => $event,
				'day_key' => '',
				'day'     => __( 'Date not decided', 'ludoya' ),
				'time'    => '',
			);
			continue;
		}

		$zone = null;
		$tz   = (string) ludoya_get( $event, 'timeZone', '' );
		if ( '' !== $tz ) {
			try {
				$zone = new DateTimeZone( $tz );
			} catch ( Exception $e ) {
				$zone = null;
			}
		}
		if ( null === $zone ) {
			$zone = wp_timezone();
		}

		try {
			$start = ( new DateTimeImmutable( $starts_at ) )->setTimezone( $zone );
		} catch ( Exception $e ) {
			continue;
		}
		if ( null === $now ) {
			$now = new DateTimeImmutable( 'now', $zone );
		}

		// Same day wording the cards use, so a heading and the card under it agree.
		$days = (int) $now->setTime( 0, 0 )->diff( $start->setTime( 0, 0 ) )->format( '%r%a' );
		if ( 0 === $days ) {
			$day = __( 'Today', 'ludoya' );
		} elseif ( 1 === $days ) {
			$day = __( 'Tomorrow', 'ludoya' );
		} else {
			$same_year = ( $start->format( 'Y' ) === $now->format( 'Y' ) );
			$day       = wp_date( $same_year ? 'l j F' : 'l j F Y', $start->getTimestamp(), $zone );
		}

		$dated[] = array(
			'event'   => $event,
			'day_key' => $start->format( 'Y-m-d' ),
			'day'     => $day,
			'time'    => wp_date( get_option( 'time_format', 'H:i' ), $start->getTimestamp(), $zone ),
		);
	}

	// Instants arrive as UTC ISO-8601, so they compare as text.
	usort(
		$dated,
		static function ( $a, $b ) {
			return strcmp(
				(string) ludoya_get( $a['event'], 'startsAt', '' ),
				(string) ludoya_get( $b['event'], 'startsAt', '' )
			);
		}
	);

	return array_merge( $dated, $undated );
}

/**
 * Where an event is held, as one line: the venue, then the room or area inside it.
 *
 * A sub-event usually has no venue of its own — it borrows the one its programme is held at — and
 * says where it is by naming a spot instead. Printing only the venue therefore told every visitor
 * the town and nothing else: "Calders" for a game that is in the church, and the same "Calders" for
 * the one in the civic centre. The event carries spot ids and the venue carries the spots, so the
 * name is resolvable here without another request.
 *
 * Nested spots read outside-in ("Fàbrica de Creació · Tercer Piso") since that is the order someone
 * walks them. Several spots are joined with a comma. An event with no spot yields the venue alone.
 *
 * @param array $event The event, as returned by the API.
 * @return string Empty when the event names no venue at all.
 */
function ludoya_place_label( $event ) {
	$venue = (string) ludoya_get( $event, 'location.name', '' );
	$spots = ludoya_get( $event, 'location.spots', array() );
	$ids    = ludoya_get( $event, 'spotIds', array() );
	if ( ! is_array( $ids ) || empty( $ids ) ) {
		$single = ludoya_get( $event, 'spotId', '' );
		$ids    = '' === $single ? array() : array( $single );
	}
	if ( empty( $ids ) || ! is_array( $spots ) || empty( $spots ) ) {
		return $venue;
	}

	$by_id = array();
	foreach ( $spots as $spot ) {
		if ( isset( $spot['id'] ) ) {
			$by_id[ $spot['id'] ] = $spot;
		}
	}

	// An event that names both an area and a room inside it is in the room: listing the area again
	// on its own produced "Fàbrica de Creació, Fàbrica de Creació · Planta Baja".
	$ancestors = array();
	foreach ( $ids as $id ) {
		$node = isset( $by_id[ $id ] ) ? $by_id[ $id ] : null;
		$seen = array();
		while ( $node && ! isset( $seen[ $node['id'] ] ) ) {
			$seen[ $node['id'] ] = true;
			$parent              = (string) ludoya_get( $node, 'parentSpotId', '' );
			if ( '' === $parent || ! isset( $by_id[ $parent ] ) ) {
				break;
			}
			$ancestors[ $parent ] = true;
			$node                 = $by_id[ $parent ];
		}
	}

	$names = array();
	foreach ( $ids as $id ) {
		if ( ! isset( $by_id[ $id ] ) || isset( $ancestors[ $id ] ) ) {
			continue;
		}
		// Outside-in, and guarded against a parent cycle in the data rather than trusting it.
		$trail = array();
		$seen  = array();
		$node  = $by_id[ $id ];
		while ( $node && ! isset( $seen[ $node['id'] ] ) ) {
			$seen[ $node['id'] ] = true;
			array_unshift( $trail, (string) ludoya_get( $node, 'name', '' ) );
			$parent = (string) ludoya_get( $node, 'parentSpotId', '' );
			$node   = ( '' !== $parent && isset( $by_id[ $parent ] ) ) ? $by_id[ $parent ] : null;
		}
		$trail = array_filter( $trail );
		if ( ! empty( $trail ) ) {
			$names[] = implode( ' · ', $trail );
		}
	}

	if ( empty( $names ) ) {
		return $venue;
	}
	$inside = implode( ', ', array_unique( $names ) );
	return '' === $venue ? $inside : $venue . ' · ' . $inside;
}

/**
 * Sort events so every sub-event follows its parent, with the parents in date order.
 *
 * A flat list from the API lands a Sunday sub-event between two other clubs' Saturday events; an
 * organiser scanning the list wants the whole weekend under its heading. Top-level events go first
 * by start date, then each parent is followed by its own children in ascending date order — the
 * order they happen in, whichever way the outer list runs — and each child by its own children in
 * turn, because a festival's demo tables hang off the festival's zones, not off the festival. A
 * child whose parent is not in the list (the parent is past, the child still upcoming) stays at
 * the top level rather than disappearing.
 *
 * Each returned event carries two extra keys for the list table: `_depth` (0 for a top-level event,
 * 1 for its children, and so on) and `_childCount` (how many events hang under it, at any depth).
 *
 * @param array $events    Events as returned by the API.
 * @param bool  $ascending Soonest first for the top level; false for latest first.
 * @return array
 */
function ludoya_group_by_parent( $events, $ascending = true ) {
	$by_id = array();
	foreach ( $events as $event ) {
		if ( isset( $event['id'] ) ) {
			$by_id[ $event['id'] ] = $event;
		}
	}

	$by_start = static function ( $a, $b ) {
		$left  = (string) ( isset( $a['startsAt'] ) ? $a['startsAt'] : '' );
		$right = (string) ( isset( $b['startsAt'] ) ? $b['startsAt'] : '' );
		return strcmp( $left, $right );
	};

	$roots    = array();
	$children = array();
	foreach ( $events as $event ) {
		$parent_id = isset( $event['parentId'] ) ? (string) $event['parentId'] : '';
		if ( '' !== $parent_id && isset( $by_id[ $parent_id ] ) && $parent_id !== $event['id'] ) {
			$children[ $parent_id ][] = $event;
		} else {
			$roots[] = $event;
		}
	}

	usort( $roots, $by_start );
	if ( ! $ascending ) {
		$roots = array_reverse( $roots );
	}

	// Emits an event, then everything under it, depth first. Returns how many followed.
	$emit = static function ( $event, $depth, &$grouped ) use ( &$emit, &$children, $by_start ) {
		$own = isset( $event['id'] ) && isset( $children[ $event['id'] ] ) ? $children[ $event['id'] ] : array();
		usort( $own, $by_start );
		// Consumed on the way down, so a cycle in bad data ends instead of recursing forever.
		unset( $children[ $event['id'] ] );

		$event['_depth'] = $depth;
		$index           = count( $grouped );
		$grouped[]       = $event;

		$descendants = 0;
		foreach ( $own as $child ) {
			$descendants += 1 + $emit( $child, $depth + 1, $grouped );
		}
		$grouped[ $index ]['_childCount'] = $descendants;
		return $descendants;
	};

	$grouped = array();
	foreach ( $roots as $root ) {
		$emit( $root, 0, $grouped );
	}
	return $grouped;
}

/**
 * Every visibility value, for the admin select.
 *
 * @return array
 */
function ludoya_visibilities() {
	return array(
		'PUBLIC'       => __( 'Public', 'ludoya' ),
		'ONLY_GROUP'   => __( 'Group only', 'ludoya' ),
		'ONLY_FRIENDS' => __( 'Friends only', 'ludoya' ),
		'PRIVATE'      => __( 'Private', 'ludoya' ),
	);
}

/**
 * Cache-busting version for one of our CSS or JS files.
 *
 * The plugin version alone is not enough: it stays put between releases, so during development, and
 * across any hotfix that ships the same version, a browser keeps serving the JavaScript it already
 * has against freshly rendered HTML. That failure is silent — the markup is right, the handler is
 * simply not there — so it is worth the one stat() call to make it impossible.
 *
 * @param string $relative Path inside the plugin folder, e.g. assets/js/admin.js.
 * @return string
 */
function ludoya_asset_version( $relative ) {
	$file = LUDOYA_DIR . $relative;
	$time = file_exists( $file ) ? filemtime( $file ) : 0;
	return $time ? LUDOYA_VERSION . '.' . $time : LUDOYA_VERSION;
}

/**
 * The six things this plugin can put on a page, described for somebody running a club rather than
 * a website: what it shows, the block to reach for, and the shortcode as a fallback.
 *
 * None of them need settings to work — every attribute has a default, so a bare shortcode renders.
 * Two need something else: an event to talk about. They take it from the link that opened the page,
 * which is what makes one "Event" page serve every event, so those two carry a `needs` note rather
 * than pretending they work anywhere.
 *
 * `block` must match the block titles registered in assets/js/blocks.js, because that is the name
 * a site owner will be typing into the block inserter.
 *
 * @return array
 */
function ludoya_content_blocks() {
	return array(
		array(
			'shows'     => __( 'A list of your events', 'ludoya' ),
			'block'     => __( 'Ludoya events', 'ludoya' ),
			'shortcode' => '[ludoya_events]',
			'needs'     => '',
		),
		array(
			'shows'     => __( 'One event, with its sign-up form', 'ludoya' ),
			'block'     => __( 'Ludoya event', 'ludoya' ),
			'shortcode' => '[ludoya_event]',
			'needs'     => __( 'Put this on its own page and point your events list at it, as below. It shows whichever event the visitor clicked.', 'ludoya' ),
		),
		array(
			'shows'     => __( 'A sign-up form on its own', 'ludoya' ),
			'block'     => __( 'Ludoya sign-up form', 'ludoya' ),
			'shortcode' => '[ludoya_signup]',
			'needs'     => __( 'Same page rule, and only appears once you switch sign-ups on above.', 'ludoya' ),
		),
		array(
			'shows'     => __( 'The games you own', 'ludoya' ),
			'block'     => __( 'Ludoya collection', 'ludoya' ),
			'shortcode' => '[ludoya_collection]',
			'needs'     => '',
		),
		array(
			'shows'     => __( 'How much you have played, and what', 'ludoya' ),
			'block'     => __( 'Ludoya stats', 'ludoya' ),
			'shortcode' => '[ludoya_stats]',
			'needs'     => '',
		),
		array(
			'shows'     => __( 'Where you play', 'ludoya' ),
			'block'     => __( 'Ludoya locations', 'ludoya' ),
			'shortcode' => '[ludoya_locations]',
			'needs'     => '',
		),
	);
}

/**
 * Locate a template, letting the active theme override it from a `ludoya/` folder.
 *
 * @param string $name Template file name, without extension.
 * @return string Absolute path.
 */
function ludoya_locate_template( $name ) {
	$override = locate_template( array( 'ludoya/' . $name . '.php' ) );
	if ( $override ) {
		return $override;
	}
	return LUDOYA_DIR . 'templates/' . $name . '.php';
}

/**
 * Render a template with the given variables and return its output.
 *
 * @param string $name Template file name, without extension.
 * @param array  $vars Variables extracted into the template's scope.
 * @return string
 */
function ludoya_render( $name, $vars = array() ) {
	$file = ludoya_locate_template( $name );
	if ( ! file_exists( $file ) ) {
		return '';
	}
	wp_enqueue_style( 'ludoya' );
	wp_enqueue_script( 'ludoya' );
	// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template scope, keys are ours.
	extract( $vars, EXTR_SKIP );
	ob_start();
	include $file;
	return ob_get_clean();
}

/**
 * schema.org Event markup for one event, as a JSON-LD script tag.
 *
 * This is what turns a club's event page into a rich result in search — date, venue and
 * availability shown right in the listing. Emitted only on the single-event view: a list of six
 * cards is not "an event" and marking it up as several confuses crawlers more than it helps.
 *
 * @param array $event Event as returned by the API.
 * @return string Script tag, or an empty string for an event with no date.
 */
function ludoya_event_jsonld( $event ) {
	if ( empty( $event['startsAt'] ) ) {
		return '';
	}

	$zone = null;
	try {
		$zone = new DateTimeZone( ludoya_get( $event, 'timeZone', 'UTC' ) );
	} catch ( Exception $e ) {
		$zone = new DateTimeZone( 'UTC' );
	}
	$iso = static function ( $instant ) use ( $zone ) {
		try {
			return ( new DateTimeImmutable( $instant ) )->setTimezone( $zone )->format( 'Y-m-d\TH:i:sP' );
		} catch ( Exception $e ) {
			return null;
		}
	};

	$data = array(
		'@context'            => 'https://schema.org',
		'@type'               => 'Event',
		'name'                => ludoya_get( $event, 'title', '' ),
		'startDate'           => $iso( $event['startsAt'] ),
		'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
		'eventStatus'         => empty( $event['canceled'] )
			? 'https://schema.org/EventScheduled'
			: 'https://schema.org/EventCancelled',
	);

	if ( ! empty( $event['endsAt'] ) ) {
		$data['endDate'] = $iso( $event['endsAt'] );
	}
	if ( ! empty( $event['description'] ) ) {
		$data['description'] = wp_strip_all_tags( $event['description'] );
	}

	$image = ludoya_get( $event, 'imageUrl' );
	if ( empty( $image ) ) {
		$image = ludoya_get( $event, 'game.imageUrl' );
	}
	if ( ! empty( $image ) ) {
		$data['image'] = $image;
	}

	if ( ! empty( $event['location']['name'] ) ) {
		$data['location'] = array(
			'@type' => 'Place',
			'name'  => $event['location']['name'],
		);
		if ( ! empty( $event['location']['address'] ) ) {
			$data['location']['address'] = $event['location']['address'];
		}
		if ( is_numeric( ludoya_get( $event, 'location.latitude' ) ) && is_numeric( ludoya_get( $event, 'location.longitude' ) ) ) {
			$data['location']['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $event['location']['latitude'],
				'longitude' => (float) $event['location']['longitude'],
			);
		}
	}

	return '<script type="application/ld+json">'
		. wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		. '</script>';
}

/**
 * Render an error in a way that never leaks anything to a visitor.
 *
 * Administrators see what actually went wrong; everybody else sees nothing at all, because a
 * broken API key is our problem, not something to print on a public page.
 *
 * @param WP_Error $error Error from the client.
 * @return string
 */
function ludoya_render_error( $error ) {
	if ( ! current_user_can( ludoya_capability() ) ) {
		return '';
	}
	return '<div class="ludoya-error"><strong>' . esc_html__( 'Ludoya', 'ludoya' ) . ':</strong> '
		. esc_html( $error->get_error_message() ) . '</div>';
}

/**
 * Bump the cache generation, which is what invalidates every cached GET at once.
 *
 * Cheaper and more reliable than hunting transients by prefix, which is not something the object
 * cache can be asked to do.
 */
function ludoya_flush_cache() {
	$version = (int) get_option( 'ludoya_cache_generation', 1 );
	update_option( 'ludoya_cache_generation', $version + 1, false );
}

/**
 * Read a nested value out of an API response without a stack of isset() checks.
 *
 * @param array  $data    Response array.
 * @param string $path    Dot-separated path, e.g. futureEvents.elements.
 * @param mixed  $default Returned when the path is absent.
 * @return mixed
 */
function ludoya_get( $data, $path, $default = null ) {
	$current = $data;
	foreach ( explode( '.', $path ) as $segment ) {
		if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
			return $default;
		}
		$current = $current[ $segment ];
	}
	return $current;
}
