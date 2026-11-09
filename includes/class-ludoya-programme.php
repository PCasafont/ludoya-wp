<?php
/**
 * Fetches what a big event's programme and games views need.
 *
 * The shaping is in includes/programme.php; this class only decides which requests to make and
 * keeps their number small. A Club key gets 30 requests a minute, and a fair's page is the one
 * every visitor opens in the same week.
 *
 * @package Ludoya
 */

defined( 'ABSPATH' ) || exit;

/**
 * Programme and games-at-the-event data.
 */
class Ludoya_Programme {

	/**
	 * Most single-event requests a games view may make on a cold cache. A programme with more
	 * game-less activities than this lists the games it could read rather than stalling the page.
	 */
	const MAX_GAME_LOOKUPS = 40;

	/**
	 * Most children requests the fallback walk may make.
	 */
	const MAX_CHILD_LOOKUPS = 25;

	/**
	 * Every event under a root, at any depth, in one request where possible.
	 *
	 * The organisation's list with sub-events holds the whole tree of anything still to come, so a
	 * fair of forty activities costs one cached request instead of one per area. A finished event is
	 * not in that list (it only carries a few past events, without their sub-events), so the tree is
	 * then walked one children request per container instead, within a budget.
	 *
	 * @param array $root The root event, as GET events/{id} returns it.
	 * @return array Events, flat, each with its parentId.
	 */
	public static function descendants( $root ) {
		$root_id = (string) ludoya_get( $root, 'id', '' );
		if ( '' === $root_id ) {
			return array();
		}

		$list = Ludoya_Client::get( 'events', array( 'includeSubEvents' => 'true' ) );
		if ( ! is_wp_error( $list ) ) {
			$events = ludoya_get( $list, 'futureEvents.elements', array() );
			foreach ( $events as $event ) {
				if ( $root_id === (string) ludoya_get( $event, 'parentId', '' ) ) {
					return $events;
				}
			}
		}

		// Not in the list: walk it.
		$events  = array();
		$pending = array( $root_id );
		$calls   = 0;
		while ( ! empty( $pending ) && $calls < self::MAX_CHILD_LOOKUPS ) {
			$parent   = array_shift( $pending );
			$children = Ludoya_Client::get( 'events/' . rawurlencode( $parent ) . '/children' );
			++$calls;
			if ( is_wp_error( $children ) ) {
				continue;
			}
			foreach ( ludoya_get( $children, 'children', array() ) as $child ) {
				$events[] = $child;
				if ( ludoya_can_host_sub_events( $child ) ) {
					$pending[] = (string) $child['id'];
				}
			}
		}
		return $events;
	}

	/**
	 * The programme of an event.
	 *
	 * @param array $root The root event.
	 * @return array See ludoya_programme_build().
	 */
	public static function programme( $root ) {
		return ludoya_programme_build( (string) ludoya_get( $root, 'id', '' ), self::descendants( $root ) );
	}

	/**
	 * Every game at an event, with where each is played.
	 *
	 * An activity's own game comes with the list. A games list (a demo area's line-up) only comes
	 * with the single event, so those are read one by one — but only for events that can carry one:
	 * the root, its areas, and activities that are not about a single game.
	 *
	 * @param array  $root      The root event, as GET events/{id} returns it (with its games).
	 * @param array  $programme Its programme, from self::programme().
	 * @param string $area      Only the games of this area (its id or its name); empty for all.
	 * @return array See ludoya_event_games_build().
	 */
	public static function games( $root, $programme, $area = '' ) {
		// An area page lists that area's games: the festival's own list belongs to no area.
		$area_id = '';
		foreach ( $programme['areas'] as $row ) {
			if ( '' !== $area && ( $row['id'] === $area || ludoya_fold( $row['title'] ) === ludoya_fold( $area ) ) ) {
				$area_id = $row['id'];
			}
		}
		$places = array();
		if ( '' === $area_id ) {
			$places[] = array(
				'event'     => $root,
				'games'     => ludoya_get( $root, 'games', array() ),
				'entry'     => null,
				'container' => true,
			);
		}

		$lookups = 0;
		$read    = static function ( $event ) use ( &$lookups ) {
			if ( $lookups >= self::MAX_GAME_LOOKUPS ) {
				return array();
			}
			++$lookups;
			$full = Ludoya_Client::get( 'events/' . rawurlencode( (string) $event['id'] ) );
			return is_wp_error( $full ) ? array() : ludoya_get( $full, 'games', array() );
		};

		foreach ( $programme['areas'] as $row ) {
			if ( '' !== $area_id && $row['id'] !== $area_id ) {
				continue;
			}
			$places[] = array(
				'event'     => $row['event'],
				'games'     => $read( $row['event'] ),
				'entry'     => null,
				'container' => true,
			);
		}

		$done = array();
		foreach ( array_merge( $programme['all_along'], $programme['entries'], $programme['undated'] ) as $entry ) {
			if ( ! empty( $entry['continues'] ) || ( '' !== $area_id && $entry['area'] !== $area_id ) ) {
				continue;
			}
			$event = $entry['event'];
			$id    = (string) $event['id'];
			// A game list belongs to the event, not the sitting: read it once.
			$games = array();
			if ( ! isset( $done[ $id ] ) && empty( $event['game'] ) && in_array( (string) ludoya_get( $event, 'type', '' ), array( 'MEETUP', 'PLAY_BOOTH' ), true ) ) {
				$games = $read( $event );
			}
			$done[ $id ] = true;
			$places[]    = array(
				'event' => $event,
				'games' => $games,
				'entry' => $entry,
			);
		}

		return ludoya_event_games_build( $places );
	}

	/**
	 * Resolve the event a view is about: an attribute, else the page's link, else the site's main
	 * event (Ludoya › Settings).
	 *
	 * @param string $attribute Id or ext:<id> from the shortcode, may be empty.
	 * @return array|WP_Error|null The event, an API error, or null when nothing names one.
	 */
	public static function resolve_event( $attribute ) {
		$event_id = (string) $attribute;
		if ( '' === $event_id && isset( $_GET['ludoya_event'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public read.
			$event_id = sanitize_text_field( wp_unslash( $_GET['ludoya_event'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( '' === $event_id ) {
			$event_id = Ludoya_Settings::main_event();
		}
		if ( '' === $event_id ) {
			return null;
		}
		return Ludoya_Client::get( 'events/' . rawurlencode( $event_id ) );
	}
}
