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
 * Public URL of a board game on ludoya.com.
 *
 * @param array $game Game as returned by the API.
 * @return string
 */
function ludoya_game_url( $game ) {
	$slug = isset( $game['slug'] ) ? $game['slug'] : ( isset( $game['id'] ) ? $game['id'] : '' );
	return trailingslashit( Ludoya_Settings::site_base() ) . 'boardgames/' . rawurlencode( $slug );
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
 * Every event type, for the admin select.
 *
 * @return array
 */
function ludoya_event_types() {
	return array( 'MEETUP', 'PLANNED_PLAY', 'TOURNAMENT', 'PLAY_BOOTH' );
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
	// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template scope, keys are ours.
	extract( $vars, EXTR_SKIP );
	ob_start();
	include $file;
	return ob_get_clean();
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
