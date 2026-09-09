<?php
/**
 * Front-end shortcodes.
 *
 * Each one is a thin adapter: read attributes, call the API, hand the decoded arrays to a template
 * the theme is allowed to override.
 *
 * @package Ludoya
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the plugin's shortcodes.
 */
class Ludoya_Shortcodes {

	/**
	 * Hook the shortcodes up.
	 */
	public static function register() {
		add_shortcode( 'ludoya_events', array( __CLASS__, 'events' ) );
		add_shortcode( 'ludoya_event', array( __CLASS__, 'event' ) );
		add_shortcode( 'ludoya_signup', array( __CLASS__, 'signup' ) );
		add_shortcode( 'ludoya_collection', array( __CLASS__, 'collection' ) );
		add_shortcode( 'ludoya_stats', array( __CLASS__, 'stats' ) );
		add_shortcode( 'ludoya_locations', array( __CLASS__, 'locations' ) );
	}

	/**
	 * [ludoya_events] — the organisation's events.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function events( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'        => 6,
				'past'         => 0,
				'type'         => '',
				'include_sub'  => 0,
				'spot'         => '',
				'layout'       => 'cards',
				'event_page'   => '',
				'heading'      => '',
				'empty'        => __( 'No events scheduled right now.', 'ludoya' ),
			),
			$atts,
			'ludoya_events'
		);

		$past_limit = max( 0, (int) $atts['past'] );
		// A table's events are almost always sub-events of something bigger, so a spot filter reads
		// through them whatever the sub-events setting says. The API does the same.
		$response = Ludoya_Client::get(
			'events',
			array(
				'pastLimit'        => (string) $past_limit,
				'includeSubEvents' => empty( $atts['include_sub'] ) && '' === $atts['spot'] ? 'false' : 'true',
				'spotId'           => sanitize_text_field( $atts['spot'] ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return ludoya_render_error( $response );
		}

		$future = self::filter_events( ludoya_get( $response, 'futureEvents.elements', array() ), $atts );
		$past   = self::filter_events( ludoya_get( $response, 'pastEvents.elements', array() ), $atts );

		// The API ranks events by relevance; a programme on a page reads in the order things happen.
		// Soonest first for what is coming, most recent first for what has been.
		$future = self::sort_by_start( $future, true );
		$past   = self::sort_by_start( $past, false );

		$limit = max( 1, (int) $atts['limit'] );

		return ludoya_render(
			'events-list',
			array(
				'future_events' => array_slice( $future, 0, $limit ),
				'past_events'   => $past_limit > 0 ? array_slice( $past, 0, $past_limit ) : array(),
				'layout'        => 'list' === $atts['layout'] ? 'list' : 'cards',
				'event_page'    => self::event_page_url( $atts['event_page'] ),
				'heading'       => $atts['heading'],
				'empty'         => $atts['empty'],
			)
		);
	}

	/**
	 * [ludoya_event] — one event, with its sign-up form when the page allows it.
	 *
	 * With no id attribute the event is read from the query string, which is what makes a single
	 * "Event" page work as the target of every card in an [ludoya_events] list.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function event( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'          => '',
				'show_signup' => 1,
				'back_url'    => '',
			),
			$atts,
			'ludoya_event'
		);

		$event_id = $atts['id'];
		if ( '' === $event_id && isset( $_GET['ludoya_event'] ) ) {
			$event_id = sanitize_text_field( wp_unslash( $_GET['ludoya_event'] ) );
		}
		if ( '' === $event_id ) {
			return '';
		}

		$event = Ludoya_Client::get( 'events/' . rawurlencode( $event_id ) );
		if ( is_wp_error( $event ) ) {
			return ludoya_render_error( $event );
		}

		$signup = '';
		if ( ! empty( $atts['show_signup'] ) && Ludoya_Settings::signup_open() && empty( $event['canceled'] ) ) {
			$signup = Ludoya_Signup::render_form( $event );
		}

		// A bigger event shows its programme. The sub-events link back to this same page when it is
		// the shared "Event" page (the one that reads its event from the link); a page pinned to one
		// event by id cannot show another, so there they link to the Ludoya app instead.
		$children   = array();
		$event_page = '' === $atts['id'] ? (string) get_permalink() : '';
		if ( ludoya_can_host_sub_events( $event ) ) {
			$children = Ludoya_Client::get( 'events/' . rawurlencode( $event_id ) . '/children' );
			$children = is_wp_error( $children ) ? array() : ludoya_group_by_parent( ludoya_get( $children, 'children', array() ) );
		}

		// A sub-event names the event it is part of, so a visitor who landed on one table of a
		// convention can find the convention.
		$parent = array();
		if ( ! empty( $event['parentId'] ) ) {
			$parent = Ludoya_Client::get( 'events/' . rawurlencode( $event['parentId'] ) );
			$parent = is_wp_error( $parent ) ? array() : $parent;
		}

		return ludoya_render(
			'event-single',
			array(
				'event'      => $event,
				'signup'     => $signup,
				'back_url'   => $atts['back_url'] ? esc_url_raw( $atts['back_url'] ) : '',
				'children'   => $children,
				'parent'     => $parent,
				'event_page' => $event_page,
			)
		);
	}

	/**
	 * [ludoya_signup event="..."] — the sign-up form on its own.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function signup( $atts ) {
		$atts = shortcode_atts( array( 'event' => '' ), $atts, 'ludoya_signup' );

		$event_id = $atts['event'];
		if ( '' === $event_id && isset( $_GET['ludoya_event'] ) ) {
			$event_id = sanitize_text_field( wp_unslash( $_GET['ludoya_event'] ) );
		}
		if ( '' === $event_id || ! Ludoya_Settings::signup_open() ) {
			return '';
		}

		$event = Ludoya_Client::get( 'events/' . rawurlencode( $event_id ) );
		if ( is_wp_error( $event ) ) {
			return ludoya_render_error( $event );
		}
		return Ludoya_Signup::render_form( $event );
	}

	/**
	 * [ludoya_collection] — the games the organisation owns.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function collection( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'   => 24,
				'filter'  => 'ownership=OWNED',
				// Sort properties are the API's enum names, so upper case: NAME, YEAR_PUBLISHED,
				// PLAY_COUNT, RATING and the rest.
				'sort'    => 'NAME,ASC',
				'search'  => '',
				'layout'  => 'grid',
				'heading' => '',
			),
			$atts,
			'ludoya_collection'
		);

		$filter = $atts['filter'];
		if ( '' !== $atts['search'] ) {
			$filter = ( '' === $filter ? '' : $filter . ';' ) . 'nameFilter=' . $atts['search'];
		}

		$response = Ludoya_Client::get(
			'collection',
			array(
				'filter'     => $filter,
				'sort'       => $atts['sort'],
				'pagination' => max( 1, (int) $atts['limit'] ) . ',0',
			)
		);
		if ( is_wp_error( $response ) ) {
			return ludoya_render_error( $response );
		}

		return ludoya_render(
			'collection',
			array(
				'games'            => isset( $response['games'] ) ? $response['games'] : array(),
				'total_games'      => (int) ludoya_get( $response, 'totalGames', 0 ),
				'total_expansions' => (int) ludoya_get( $response, 'totalExpansions', 0 ),
				'layout'           => 'list' === $atts['layout'] ? 'list' : 'grid',
				'heading'          => $atts['heading'],
			)
		);
	}

	/**
	 * [ludoya_stats] — play statistics for the organisation.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function stats( $atts ) {
		$atts = shortcode_atts(
			array(
				'period'    => 'ONE_YEAR',
				'top_games' => 5,
				'heading'   => '',
			),
			$atts,
			'ludoya_stats'
		);

		$period = $atts['period'];
		// The API wants PERIOD,date,index; a bare period is the common case, so complete it here
		// rather than making every site owner learn the format.
		if ( false === strpos( $period, ',' ) ) {
			$period = $period . ',' . gmdate( 'Y-m-d' ) . ',0';
		}

		$response = Ludoya_Client::get( 'stats', array( 'period' => $period ) );
		if ( is_wp_error( $response ) ) {
			return ludoya_render_error( $response );
		}

		// Two shapes in the wild. The current API sends a lean public one; an older API sent its
		// internal model, whose fields sit deeper. Normalise both so the template knows only one.
		if ( isset( $response['playCount'] ) ) {
			$tiles   = array(
				'plays'          => (int) $response['playCount'],
				'unique_games'   => (int) ludoya_get( $response, 'uniqueGames', 0 ),
				'unique_players' => (int) ludoya_get( $response, 'uniquePlayers', 0 ),
				'play_time'      => ( '' !== (string) ludoya_get( $response, 'playTime', '' ) ) ? (string) $response['playTime'] : '0h',
			);
			$by_game = array();
			foreach ( ludoya_get( $response, 'mostPlayed', array() ) as $ludoya_entry ) {
				$by_game[] = array(
					'game'  => ludoya_get( $ludoya_entry, 'game', array() ),
					'plays' => (int) ludoya_get( $ludoya_entry, 'playCount', 0 ),
				);
			}
		} else {
			$ludoya_old = ludoya_get( $response, 'playStats.stats', array() );
			$tiles      = array(
				'plays'          => (int) ludoya_get( $ludoya_old, 'totalPlayCount', 0 ),
				'unique_games'   => (int) ludoya_get( $ludoya_old, 'uniqueGames', 0 ),
				'unique_players' => (int) ludoya_get( $ludoya_old, 'uniquePlayers', 0 ),
				'play_time'      => (string) ludoya_get( $ludoya_old, 'totalPlayTime', '' ),
			);
			$by_game    = array();
			foreach ( ludoya_get( $response, 'playStatsByGame', array() ) as $ludoya_entry ) {
				$by_game[] = array(
					'game'  => ludoya_get( $ludoya_entry, 'game', array() ),
					'plays' => (int) ludoya_get( $ludoya_entry, 'stats.totalPlayCount', 0 ),
				);
			}
		}

		return ludoya_render(
			'stats',
			array(
				'tiles'   => $tiles,
				'by_game' => array_slice( $by_game, 0, max( 0, (int) $atts['top_games'] ) ),
				'heading' => $atts['heading'],
			)
		);
	}

	/**
	 * [ludoya_locations] — where the organisation plays.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function locations( $atts ) {
		$atts = shortcode_atts( array( 'heading' => '' ), $atts, 'ludoya_locations' );

		$response = Ludoya_Client::get( 'locations' );
		if ( is_wp_error( $response ) ) {
			return ludoya_render_error( $response );
		}

		return ludoya_render(
			'locations',
			array(
				'locations' => isset( $response['locations'] ) ? $response['locations'] : array(),
				'heading'   => $atts['heading'],
			)
		);
	}

	/**
	 * Apply the attribute filters the API does not do for us.
	 *
	 * @param array $events Events from the API.
	 * @param array $atts   Shortcode attributes.
	 * @return array
	 */
	protected static function filter_events( $events, $atts ) {
		if ( '' === $atts['type'] ) {
			return $events;
		}
		$wanted = array_map( 'trim', explode( ',', strtoupper( $atts['type'] ) ) );
		$kept   = array();
		foreach ( $events as $event ) {
			if ( in_array( isset( $event['type'] ) ? $event['type'] : '', $wanted, true ) ) {
				$kept[] = $event;
			}
		}
		return $kept;
	}

	/**
	 * Order events by start date.
	 *
	 * @param array $events    Events as returned by the API.
	 * @param bool  $ascending Soonest first; false for latest first.
	 * @return array
	 */
	protected static function sort_by_start( $events, $ascending ) {
		usort(
			$events,
			static function ( $a, $b ) use ( $ascending ) {
				$left  = (string) ludoya_get( $a, 'startsAt', '' );
				$right = (string) ludoya_get( $b, 'startsAt', '' );
				return $ascending ? strcmp( $left, $right ) : strcmp( $right, $left );
			}
		);
		return $events;
	}

	/**
	 * Resolve the `event_page` attribute, which may be a page id, a slug or a URL.
	 *
	 * An empty result means the cards link to ludoya.com instead of to a page on this site.
	 *
	 * @param string $value Attribute value.
	 * @return string
	 */
	protected static function event_page_url( $value ) {
		if ( '' === $value ) {
			return '';
		}
		if ( is_numeric( $value ) ) {
			return (string) get_permalink( (int) $value );
		}
		if ( 0 === strpos( $value, 'http' ) ) {
			return esc_url_raw( $value );
		}
		$page = get_page_by_path( sanitize_title( $value ) );
		return $page ? (string) get_permalink( $page ) : '';
	}
}
