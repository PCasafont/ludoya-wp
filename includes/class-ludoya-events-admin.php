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

		$event = array();
		$etag  = '';
		$error = null;
		if ( '' !== $event_id ) {
			$event = Ludoya_Client::get( 'events/' . rawurlencode( $event_id ), array(), 0 );
			if ( is_wp_error( $event ) ) {
				$error = $event;
				$event = array();
			} else {
				$etag = Ludoya_Client::last_etag();
			}
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

		$timezone = isset( $post['time_zone'] ) ? sanitize_text_field( $post['time_zone'] ) : '';
		$body     = self::body_from_post( $post, $timezone, $is_new );

		if ( '' === $body['title'] ) {
			Ludoya_Admin::add_notice( 'error', __( 'An event needs a title.', 'ludoya' ) );
			self::back_to_edit( $event_id );
		}

		if ( $is_new ) {
			$result = Ludoya_Client::post( 'events', $body );
			if ( is_wp_error( $result ) ) {
				Ludoya_Admin::add_notice( 'error', $result->get_error_message() );
				self::back_to_edit( '' );
			}
			$event_id = isset( $result['id'] ) ? $result['id'] : '';
			Ludoya_Admin::add_notice( 'success', __( 'Event created.', 'ludoya' ) );
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

		$image_url = isset( $post['image_url'] ) ? esc_url_raw( $post['image_url'] ) : '';
		if ( '' !== $image_url ) {
			$body['image'] = array( 'url' => $image_url );
		}

		// Draft is one-way: publishing happens through its own endpoint, and sending draft=false on
		// a patch would neither publish the event nor say why nothing happened.
		if ( $is_new ) {
			$body['draft'] = ! empty( $post['draft'] );
		}

		return $body;
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
	 * Send the admin back to the edit screen (or the list, for a create that failed).
	 *
	 * @param string $event_id Event id, or an empty string.
	 */
	protected static function back_to_edit( $event_id ) {
		$url = admin_url( 'admin.php?page=ludoya-event-edit' );
		if ( '' !== $event_id ) {
			$url = add_query_arg( 'event', rawurlencode( $event_id ), $url );
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
