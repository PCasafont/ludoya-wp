<?php
/**
 * Event administration: list, create, edit, publish, cancel, delete, and sign people up.
 *
 * Edits go out as PATCH with If-Match, because the same events are edited in the Ludoya app at the
 * same time: PATCH leaves fields this API does not model alone, and If-Match turns a concurrent
 * edit into a message instead of a silent overwrite.
 *
 * @package Ludoya
 */

defined( 'ABSPATH' ) || exit;

/**
 * The event screens.
 */
class Ludoya_Events_Admin {

	const SAVE_ACTION        = 'ludoya_save_event';
	const ROW_ACTION         = 'ludoya_event_action';
	const PARTICIPANT_ACTION = 'ludoya_participant_action';
	const FORM_ACTION        = 'ludoya_form_action';

	/**
	 * Hook the write handlers up.
	 */
	public static function register() {
		add_action( 'admin_post_' . self::SAVE_ACTION, array( __CLASS__, 'save' ) );
		add_action( 'admin_post_' . self::ROW_ACTION, array( __CLASS__, 'row_action' ) );
		add_action( 'admin_post_' . self::PARTICIPANT_ACTION, array( __CLASS__, 'participant_action' ) );
		add_action( 'admin_post_' . self::FORM_ACTION, array( __CLASS__, 'form_action' ) );
	}

	/**
	 * The events list screen.
	 */
	public static function render_list() {
		self::guard();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a view filter, not a write.
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'upcoming';

		$response = Ludoya_Client::get(
			'events',
			array(
				'pastLimit'        => 'past' === $view || 'all' === $view ? '100' : '0',
				'includeSubEvents' => 'true',
			),
			0
		);

		$error  = is_wp_error( $response ) ? $response : null;
		$events = array();
		if ( ! $error ) {
			if ( 'past' !== $view ) {
				$events = array_merge( $events, ludoya_get( $response, 'futureEvents.elements', array() ) );
			}
			if ( 'upcoming' !== $view ) {
				$events = array_merge( $events, ludoya_get( $response, 'pastEvents.elements', array() ) );
			}
		}

		$table = new Ludoya_Events_Table( $events, 'upcoming' === $view );
		$table->prepare_items();

		include LUDOYA_DIR . 'templates/admin/events-list.php';
	}

	/**
	 * The create/edit screen.
	 */
	public static function render_edit() {
		self::guard();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which event to show.
		$event_id = isset( $_GET['event'] ) ? sanitize_text_field( wp_unslash( $_GET['event'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which event a new one hangs under.
		$parent_id = isset( $_GET['parent'] ) ? sanitize_text_field( wp_unslash( $_GET['parent'] ) ) : '';

		$event = array();
		$etag  = '';
		$error = null;
		if ( '' !== $event_id ) {
			$event = Ludoya_Client::get( 'events/' . rawurlencode( $event_id ), array(), 0 );
			if ( is_wp_error( $event ) ) {
				$error = $event;
				$event = array();
			} else {
				$etag      = Ludoya_Client::last_etag();
				$parent_id = (string) ludoya_get( $event, 'parentId', '' );
			}
		}

		// The parent, for a sub-event being made or edited: its title heads the screen, and its dates
		// bound the date inputs, because the API refuses a sub-event that runs outside its parent.
		$parent = array();
		if ( '' !== $parent_id ) {
			$parent = Ludoya_Client::get( 'events/' . rawurlencode( $parent_id ), array(), 0 );
			if ( is_wp_error( $parent ) ) {
				if ( ! $error ) {
					$error = $parent;
				}
				$parent = array();
			}
		}

		// What already hangs under this event. Read only where the API would accept more, so a
		// scheduled game's screen does not spend a call on a list that is always empty.
		$children = array();
		if ( ! empty( $event ) && ludoya_can_host_sub_events( $event ) ) {
			$children = Ludoya_Client::get( 'events/' . rawurlencode( $event_id ) . '/children', array(), 0 );
			$children = is_wp_error( $children ) ? array() : ludoya_group_by_parent( ludoya_get( $children, 'children', array() ) );
		}

		$locations = Ludoya_Client::get( 'locations' );
		$locations = is_wp_error( $locations ) ? array() : ludoya_get( $locations, 'locations', array() );

		$templates = Ludoya_Client::get( 'form-templates' );
		$templates = is_wp_error( $templates ) ? array() : ludoya_get( $templates, 'templates', array() );

		$event_form = array();
		if ( '' !== $event_id && ! $error ) {
			$event_form = Ludoya_Client::get( 'events/' . rawurlencode( $event_id ) . '/form', array(), 0 );
			$event_form = is_wp_error( $event_form ) ? array() : $event_form;
		}

		include LUDOYA_DIR . 'templates/admin/event-form.php';
	}

	/**
	 * The form templates screen: what exists, and what each one asks.
	 */
	public static function render_forms() {
		self::guard();

		$templates = Ludoya_Client::get( 'form-templates', array(), 0 );
		$error     = is_wp_error( $templates ) ? $templates : null;
		$templates = $error ? array() : ludoya_get( $templates, 'templates', array() );

		include LUDOYA_DIR . 'templates/admin/form-templates.php';
	}

	/**
	 * Create or update an event.
	 */
	public static function save() {
		self::guard();
		check_admin_referer( self::SAVE_ACTION );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above; every field is sanitised below.
		$post     = wp_unslash( $_POST );
		$event_id = isset( $post['event_id'] ) ? sanitize_text_field( $post['event_id'] ) : '';
		$is_new   = ( '' === $event_id );

		$timezone  = isset( $post['time_zone'] ) ? sanitize_text_field( $post['time_zone'] ) : '';
		$parent_id = isset( $post['parent_event_id'] ) ? sanitize_text_field( $post['parent_event_id'] ) : '';
		$body      = self::body_from_post( $post, $timezone, $is_new );

		if ( '' === $body['title'] ) {
			Ludoya_Admin::add_notice( 'error', __( 'An event needs a title.', 'ludoya' ) );
			self::back_to_edit( $event_id, $parent_id );
		}

		if ( $is_new ) {
			$result = Ludoya_Client::post( 'events', $body );
			if ( is_wp_error( $result ) ) {
				Ludoya_Admin::add_notice( 'error', $result->get_error_message() );
				self::back_to_edit( '', $parent_id );
			}
			$event_id = isset( $result['id'] ) ? $result['id'] : '';
			Ludoya_Admin::add_notice( 'success', '' === $parent_id ? __( 'Event created.', 'ludoya' ) : __( 'Sub-event created.', 'ludoya' ) );
		} else {
			$etag   = isset( $post['etag'] ) ? sanitize_text_field( $post['etag'] ) : '';
			$result = Ludoya_Client::patch( 'events/' . rawurlencode( $event_id ), $body, $etag );
			if ( is_wp_error( $result ) ) {
				Ludoya_Admin::add_notice( 'error', $result->get_error_message() );
				self::back_to_edit( $event_id );
			}
			Ludoya_Admin::add_notice( 'success', __( 'Event updated.', 'ludoya' ) );
		}

		$template_choice = isset( $post['form_template_id'] ) ? sanitize_text_field( $post['form_template_id'] ) : 'keep';
		if ( $is_new && '' === $template_choice ) {
			// A brand new event has no form to clear.
			$template_choice = 'keep';
		}
		if ( '' !== $event_id && 'keep' !== $template_choice ) {
			self::save_form_choice( $event_id, $template_choice );
		}

		self::back_to_edit( $event_id );
	}

	/**
	 * Publish, cancel or delete one event.
	 */
	public static function row_action() {
		self::guard();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked against the per-row nonce below.
		$request  = wp_unslash( $_REQUEST );
		$event_id = isset( $request['event'] ) ? sanitize_text_field( $request['event'] ) : '';
		$action   = isset( $request['do'] ) ? sanitize_key( $request['do'] ) : '';
		check_admin_referer( self::ROW_ACTION . '_' . $action . '_' . $event_id );

		$path = 'events/' . rawurlencode( $event_id );
		switch ( $action ) {
			case 'publish':
				$result  = Ludoya_Client::post( $path . '/publish' );
				$success = __( 'Event published.', 'ludoya' );
				break;
			case 'cancel':
				$result  = Ludoya_Client::post( $path . '/cancel' );
				$success = __( 'Event cancelled. Attendees have been notified.', 'ludoya' );
				break;
			case 'delete':
				$result  = Ludoya_Client::delete( $path );
				$success = __( 'Event deleted.', 'ludoya' );
				break;
			default:
				$result  = new WP_Error( 'ludoya_unknown_action', __( 'Unknown action.', 'ludoya' ) );
				$success = '';
		}

		if ( is_wp_error( $result ) ) {
			Ludoya_Admin::add_notice( 'error', $result->get_error_message() );
		} else {
			Ludoya_Admin::add_notice( 'success', $success );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=ludoya-events' ) );
		exit;
	}

	/**
	 * Add or remove a participant.
	 */
	public static function participant_action() {
		self::guard();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified immediately below.
		$post     = wp_unslash( $_POST );
		$event_id = isset( $post['event_id'] ) ? sanitize_text_field( $post['event_id'] ) : '';
		check_admin_referer( self::PARTICIPANT_ACTION . '_' . $event_id );

		$action = isset( $post['do'] ) ? sanitize_key( $post['do'] ) : 'add';
		$path   = 'events/' . rawurlencode( $event_id ) . '/participants';

		if ( 'remove' === $action ) {
			$user_id = isset( $post['user_id'] ) ? sanitize_text_field( $post['user_id'] ) : '';
			$result  = Ludoya_Client::delete( $path . '/' . rawurlencode( $user_id ) );
			$success = __( 'Participant removed.', 'ludoya' );
		} else {
			$user_id = isset( $post['user_id'] ) ? sanitize_text_field( $post['user_id'] ) : '';
			if ( '' !== $user_id ) {
				$body = array( 'userId' => $user_id );
			} else {
				$body = array(
					'name'  => isset( $post['name'] ) ? sanitize_text_field( $post['name'] ) : '',
					'email' => isset( $post['email'] ) ? sanitize_email( $post['email'] ) : '',
				);
				if ( '' === $body['name'] || ! is_email( $body['email'] ) ) {
					Ludoya_Admin::add_notice( 'error', __( 'Pick a Ludoya user, or give a name and a valid email address.', 'ludoya' ) );
					self::back_to_edit( $event_id );
				}
			}
			$result  = Ludoya_Client::post( $path, $body );
			$success = __( 'Participant added.', 'ludoya' );
		}

		if ( is_wp_error( $result ) ) {
			Ludoya_Admin::add_notice( 'error', $result->get_error_message() );
		} else {
			Ludoya_Admin::add_notice( 'success', $success );
		}
		self::back_to_edit( $event_id );
	}

	/**
	 * Delete a form template.
	 */
	public static function form_action() {
		self::guard();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked against the per-row nonce below.
		$request     = wp_unslash( $_REQUEST );
		$template_id = isset( $request['template'] ) ? sanitize_text_field( $request['template'] ) : '';
		check_admin_referer( self::FORM_ACTION . '_' . $template_id );

		// Always detach: refusing to delete a template still in use would strand the site owner here,
		// because this screen cannot tell them which events are using it.
		$result = Ludoya_Client::delete(
			'form-templates/' . rawurlencode( $template_id ),
			array( 'detach' => 'true' )
		);

		if ( is_wp_error( $result ) ) {
			Ludoya_Admin::add_notice( 'error', $result->get_error_message() );
		} else {
			Ludoya_Admin::add_notice( 'success', __( 'Form template deleted.', 'ludoya' ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=ludoya-forms' ) );
		exit;
	}

	/**
	 * Build the request body from the submitted form.
	 *
	 * Every field here is one the API reports back, so the form shows its current value and is the
	 * whole truth about it: blanking one means "clear it", and it is sent, null included.
	 *
	 * Three fields cannot express "make it empty" — see the note on $optional below. Those go out
	 * only when filled in, because sending null would quietly wipe whatever staff set in the Ludoya
	 * app, which is what PATCH exists to avoid.
	 *
	 * @param array  $post     Sanitised POST data.
	 * @param string $timezone Olson id the datetime inputs were entered in.
	 * @param bool   $is_new   Whether this is a create.
	 * @return array
	 */
	protected static function body_from_post( $post, $timezone, $is_new ) {
		$body = array(
			'type'                   => isset( $post['type'] ) ? sanitize_text_field( $post['type'] ) : 'MEETUP',
			'title'                  => isset( $post['title'] ) ? sanitize_text_field( $post['title'] ) : '',
			'description'            => isset( $post['description'] ) ? sanitize_textarea_field( $post['description'] ) : '',
			'startsAt'               => self::to_instant( isset( $post['starts_at'] ) ? $post['starts_at'] : '', $timezone ),
			'endsAt'                 => self::to_instant( isset( $post['ends_at'] ) ? $post['ends_at'] : '', $timezone ),
			'capacity'               => self::to_int_or_null( isset( $post['capacity'] ) ? $post['capacity'] : '' ),
			'teacherUserId'          => self::to_string_or_null( isset( $post['teacher_user_id'] ) ? $post['teacher_user_id'] : '' ),
			'masterUserId'           => self::to_string_or_null( isset( $post['master_user_id'] ) ? $post['master_user_id'] : '' ),
			// Blank means "any table", which is a real answer, so it is sent as one.
			'spotId'                 => self::to_string_or_null( isset( $post['spot_id'] ) ? $post['spot_id'] : '' ),
			// Always sent: the form shows the event's own languages (not inherited ones), so a blank
			// input means "no languages of its own", which is how an event inherits its parent's.
			'languages'              => self::to_language_codes( isset( $post['languages'] ) ? $post['languages'] : '' ),
			'visibility'             => isset( $post['visibility'] ) ? sanitize_text_field( $post['visibility'] ) : 'PUBLIC',
			'restrictedAttendance'   => ! empty( $post['restricted_attendance'] ),
			'minParticipants'        => self::to_int_or_null( isset( $post['min_participants'] ) ? $post['min_participants'] : '' ),
			'maxReservationsPerUser' => self::to_int_or_null( isset( $post['max_reservations'] ) ? $post['max_reservations'] : '' ),
		);

		// The three that cannot say "make it empty". An event always sits somewhere, so the blank
		// location option means "use the organisation default", not "clear it". The game picker can
		// only replace a game, never take one away. And your own id is still not reported back, so a
		// blank input there is indistinguishable from "I did not touch this".
		$optional = array(
			'locationId' => self::to_string_or_null( isset( $post['location_id'] ) ? $post['location_id'] : '' ),
			'gameId'     => self::to_string_or_null( isset( $post['game_id'] ) ? $post['game_id'] : '' ),
			'externalId' => self::to_string_or_null( isset( $post['external_id'] ) ? $post['external_id'] : '' ),
		);
		foreach ( $optional as $key => $value ) {
			if ( null !== $value ) {
				$body[ $key ] = $value;
			}
		}

		// An API too old to report these back also left the form showing defaults for them, so what
		// the form has to say about them is worthless. Drop them rather than save a guess.
		if ( empty( $post['api_reports_settings'] ) ) {
			foreach ( array( 'visibility', 'restrictedAttendance', 'minParticipants', 'maxReservationsPerUser', 'spotId' ) as $unreported ) {
				unset( $body[ $unreported ] );
			}
		}

		// The setup a booth or a tournament cannot exist without. Sent only for the type it belongs
		// to: the API drops an off-branch config anyway, and sending one would claim the event is
		// something it is not.
		if ( 'PLAY_BOOTH' === $body['type'] ) {
			$body['playBoothConfig'] = self::booth_config_from_post( $post );
		}
		if ( 'TOURNAMENT' === $body['type'] ) {
			// On an edit of a tournament that already has a setup, the form defaults to leaving it
			// alone: configuring replaces every phase, and the one shown here is only the first, so
			// a save that was moving the capacity must not flatten a final table set up in the app.
			$setup = isset( $post['tournament_setup'] ) ? sanitize_key( $post['tournament_setup'] ) : 'set';
			if ( 'keep' !== $setup ) {
				$body['tournament'] = array( 'phases' => array( self::tournament_phase_from_post( $post ) ) );
			}
		}

		$image_url = isset( $post['image_url'] ) ? esc_url_raw( $post['image_url'] ) : '';
		if ( '' !== $image_url ) {
			$body['image'] = array( 'url' => $image_url );
		}

		// Draft is one-way: publishing happens through its own endpoint, and sending draft=false on
		// a patch would neither publish the event nor say why nothing happened.
		if ( $is_new ) {
			$body['draft'] = ! empty( $post['draft'] );
		}

		// Which event this one hangs under is decided at creation and never sent again: a patch
		// that named it would be re-parenting, which nothing on the edit screen offers.
		$parent_id = self::to_string_or_null( isset( $post['parent_event_id'] ) ? $post['parent_event_id'] : '' );
		if ( $is_new && null !== $parent_id ) {
			$body['parentEventId'] = $parent_id;
		}

		return $body;
	}

	/**
	 * How a play booth runs, from the booth fields of the form.
	 *
	 * The two required numbers fall back to the form's own defaults rather than to null: the API
	 * models them as plain integers, so a blank input would come back as a deserialisation error
	 * instead of a sentence the organiser can act on.
	 *
	 * @param array $post Sanitised POST data.
	 * @return array
	 */
	protected static function booth_config_from_post( $post ) {
		$duration = self::to_int_or_null( isset( $post['booth_session_minutes'] ) ? $post['booth_session_minutes'] : '' );
		$players  = self::to_int_or_null( isset( $post['booth_max_players'] ) ? $post['booth_max_players'] : '' );

		return array(
			'planningMode'           => isset( $post['booth_planning_mode'] ) ? sanitize_text_field( $post['booth_planning_mode'] ) : 'GRID',
			'sessionDurationMinutes' => ( null === $duration || $duration < 1 ) ? 60 : $duration,
			'maxPlayersPerSession'   => ( null === $players || $players < 1 ) ? 4 : $players,
			// Empty means the booth seats people at the venue's named tables instead of counted lanes.
			'tableCount'             => self::to_int_or_null( isset( $post['booth_table_count'] ) ? $post['booth_table_count'] : '' ),
			'arrangeMode'            => isset( $post['booth_arrange_mode'] ) ? sanitize_text_field( $post['booth_arrange_mode'] ) : 'AUTO',
		);
	}

	/**
	 * One tournament phase — format, scoring and tiebreakers — from the tournament fields.
	 *
	 * One phase is the whole tournament for almost every club event. A cut into a final table is a
	 * second phase, and those are added in the Ludoya app: offering a phase builder here would be a
	 * second implementation of the same screen, with its own ideas about which combinations are legal.
	 *
	 * @param array $post Sanitised POST data.
	 * @return array
	 */
	protected static function tournament_phase_from_post( $post ) {
		$points = array();
		foreach ( preg_split( '/[\s,;]+/', trim( (string) ( isset( $post['tournament_points'] ) ? $post['tournament_points'] : '' ) ) ) as $value ) {
			if ( '' !== $value && is_numeric( $value ) ) {
				$points[] = (int) $value;
			}
		}
		// 4/3/2/1 is what the Ludoya app starts every tournament on, and an empty list is not a
		// tournament: every placement would score nothing.
		if ( empty( $points ) ) {
			$points = array( 4, 3, 2, 1 );
		}

		$tiebreakers = array();
		$allowed     = array_keys( ludoya_tiebreakers() );
		foreach ( (array) ( isset( $post['tournament_tiebreakers'] ) ? $post['tournament_tiebreakers'] : array() ) as $value ) {
			$value = sanitize_text_field( $value );
			if ( in_array( $value, $allowed, true ) ) {
				$tiebreakers[] = $value;
			}
		}

		$table_size = self::to_int_or_null( isset( $post['tournament_table_size'] ) ? $post['tournament_table_size'] : '' );

		return array(
			'format'                 => isset( $post['tournament_format'] ) ? sanitize_text_field( $post['tournament_format'] ) : 'SWISS',
			'targetTableSize'        => ( null === $table_size || $table_size < 2 ) ? 4 : $table_size,
			'pointsPerPlacement'     => $points,
			'tiebreakers'            => $tiebreakers,
			// Empty means open-ended: the organiser keeps starting rounds until they stop.
			'totalRounds'            => self::to_int_or_null( isset( $post['tournament_rounds'] ) ? $post['tournament_rounds'] : '' ),
			'sharedRankPointsPolicy' => isset( $post['tournament_shared_rank'] ) ? sanitize_text_field( $post['tournament_shared_rank'] ) : 'HIGHER',
			'byePoints'              => (int) self::to_int_or_null( isset( $post['tournament_bye_points'] ) ? $post['tournament_bye_points'] : '0' ),
		);
	}

	/**
	 * Point the event at a form template, or clear it.
	 *
	 * @param string $event_id    Event id.
	 * @param string $template_id Template id, or an empty string to clear the event's form.
	 */
	protected static function save_form_choice( $event_id, $template_id ) {
		$body   = ( '' === $template_id ) ? array() : array( 'templateId' => $template_id );
		$result = Ludoya_Client::put( 'events/' . rawurlencode( $event_id ) . '/form', $body );
		if ( is_wp_error( $result ) ) {
			Ludoya_Admin::add_notice( 'error', $result->get_error_message() );
		}
	}

	/**
	 * Turn a datetime-local value into the UTC instant the API expects.
	 *
	 * @param string $value    Value from a datetime-local input.
	 * @param string $timezone Olson id the value was entered in.
	 * @return string|null
	 */
	protected static function to_instant( $value, $timezone ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}
		try {
			$zone = $timezone ? new DateTimeZone( $timezone ) : wp_timezone();
			$date = new DateTimeImmutable( $value, $zone );
		} catch ( Exception $e ) {
			return null;
		}
		return $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
	}

	/**
	 * Render a UTC instant into a datetime-local input value.
	 *
	 * @param string|null $instant  ISO-8601 instant.
	 * @param string      $timezone Olson id to show it in.
	 * @return string
	 */
	public static function to_input( $instant, $timezone ) {
		if ( empty( $instant ) ) {
			return '';
		}
		try {
			$zone = $timezone ? new DateTimeZone( $timezone ) : wp_timezone();
			$date = new DateTimeImmutable( $instant );
		} catch ( Exception $e ) {
			return '';
		}
		return $date->setTimezone( $zone )->format( 'Y-m-d\TH:i' );
	}

	/**
	 * Blank numeric input means "no limit", which the API spells as null.
	 *
	 * @param string $value Raw value.
	 * @return int|null
	 */
	protected static function to_int_or_null( $value ) {
		$value = trim( (string) $value );
		return ( '' === $value ) ? null : (int) $value;
	}

	/**
	 * Parse a comma- or space-separated list of two-letter language codes.
	 *
	 * Anything that is not two letters is dropped rather than rejected: the API does the same with
	 * codes it does not know, and a typo should not block saving the rest of the event.
	 *
	 * @param string $value Raw input, e.g. "es, ca en".
	 * @return array
	 */
	protected static function to_language_codes( $value ) {
		$codes = array();
		foreach ( preg_split( '/[\s,;]+/', strtolower( trim( (string) $value ) ) ) as $code ) {
			if ( preg_match( '/^[a-z]{2}$/', $code ) ) {
				$codes[] = $code;
			}
		}
		return array_values( array_unique( $codes ) );
	}

	/**
	 * Blank text input means "unset", which the API spells as null.
	 *
	 * @param string $value Raw value.
	 * @return string|null
	 */
	protected static function to_string_or_null( $value ) {
		$value = trim( (string) $value );
		return ( '' === $value ) ? null : sanitize_text_field( $value );
	}

	/**
	 * Send the admin back to the edit screen (or the create screen, for a create that failed).
	 *
	 * @param string $event_id  Event id, or an empty string.
	 * @param string $parent_id Parent event id, so a failed sub-event create returns to the same
	 *                          "Add sub-event" screen rather than a plain one.
	 */
	protected static function back_to_edit( $event_id, $parent_id = '' ) {
		$url = admin_url( 'admin.php?page=ludoya-event-edit' );
		if ( '' !== $event_id ) {
			$url = add_query_arg( 'event', rawurlencode( $event_id ), $url );
		} elseif ( '' !== $parent_id ) {
			$url = add_query_arg( 'parent', rawurlencode( $parent_id ), $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Refuse anybody without the capability.
	 */
	protected static function guard() {
		if ( ! current_user_can( ludoya_capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'ludoya' ) );
		}
	}
}
