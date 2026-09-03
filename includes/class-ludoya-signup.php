<?php
/**
 * Front-end sign-up: render an event's form, and post the answers back to Ludoya.
 *
 * A sign-up here mints (or reuses) the Ludoya account behind the email address, which is why this
 * is off by default, gated on a consent checkbox, and throttled per visitor.
 *
 * @package Ludoya
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sign-up form rendering and submission.
 */
class Ludoya_Signup {

	const ACTION = 'ludoya_signup';

	/**
	 * Hook the submission handler up, for logged-in and anonymous visitors alike.
	 */
	public static function register() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Render the form for an event.
	 *
	 * @param array $event Event as returned by GET events/{id}, which carries the resolved form.
	 * @return string
	 */
	public static function render_form( $event ) {
		if ( ! Ludoya_Settings::signup_open() ) {
			return '';
		}
		$full     = isset( $event['capacity'], $event['participantCount'] )
			&& $event['capacity']
			&& $event['participantCount'] >= $event['capacity'];
		$questions = ludoya_get( $event, 'form.questions', array() );

		return ludoya_render(
			'signup-form',
			array(
				'event'        => $event,
				'questions'    => is_array( $questions ) ? $questions : array(),
				'form_intro'   => ludoya_get( $event, 'form.description', '' ),
				'full'         => $full,
				'consent_text' => Ludoya_Settings::get( 'consent_text', '' ),
				'notice'       => self::take_notice(),
			)
		);
	}

	/**
	 * Handle a submitted sign-up.
	 */
	public static function handle() {
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );

		if ( ! Ludoya_Settings::signup_open() ) {
			self::finish( $redirect, 'error', __( 'Sign-ups are closed.', 'ludoya' ) );
		}
		check_admin_referer( self::ACTION );

		$event_id = isset( $_POST['event_id'] ) ? sanitize_text_field( wp_unslash( $_POST['event_id'] ) ) : '';
		if ( '' === $event_id ) {
			self::finish( $redirect, 'error', __( 'Missing event.', 'ludoya' ) );
		}

		// A bot fills every field it finds; a person never sees this one.
		if ( ! empty( $_POST['ludoya_website'] ) ) {
			self::finish( $redirect, 'ok', __( 'Thanks! Your sign-up has been sent.', 'ludoya' ) );
		}
		if ( self::is_throttled() ) {
			self::finish( $redirect, 'error', __( 'Too many attempts. Please wait a minute and try again.', 'ludoya' ) );
		}
		if ( empty( $_POST['ludoya_consent'] ) ) {
			self::finish( $redirect, 'error', __( 'Please accept the terms to sign up.', 'ludoya' ) );
		}

		$name  = isset( $_POST['ludoya_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ludoya_name'] ) ) : '';
		$email = isset( $_POST['ludoya_email'] ) ? sanitize_email( wp_unslash( $_POST['ludoya_email'] ) ) : '';
		if ( '' === $name || ! is_email( $email ) ) {
			self::finish( $redirect, 'error', __( 'A name and a valid email address are required.', 'ludoya' ) );
		}

		$body = array(
			'name'  => $name,
			'email' => $email,
		);
		$answers = self::collect_answers();
		if ( ! empty( $answers ) ) {
			$body['formAnswers'] = $answers;
		}

		$result = Ludoya_Client::post( 'events/' . rawurlencode( $event_id ) . '/participants', $body );
		if ( is_wp_error( $result ) ) {
			// The API's message is the useful one here: it names the missing or invalid answer.
			self::finish( $redirect, 'error', $result->get_error_message() );
		}

		/**
		 * Fires after a visitor has been signed up for an event.
		 *
		 * @param string $event_id Ludoya event id.
		 * @param array  $body     What was sent.
		 * @param array  $result   The API response.
		 */
		do_action( 'ludoya_signed_up', $event_id, $body, $result );

		self::finish( $redirect, 'ok', __( 'You are signed up. Check your inbox to claim your Ludoya account.', 'ludoya' ) );
	}

	/**
	 * Read the answers out of the submitted form, keyed by question id.
	 *
	 * Grid answers are positional: one column value per row, in the order the rows were rendered,
	 * which is the shape the API validates against.
	 *
	 * @return array
	 */
	protected static function collect_answers() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the caller verified the nonce.
		$raw = isset( $_POST['ludoya_answers'] ) ? wp_unslash( $_POST['ludoya_answers'] ) : array();
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$answers = array();
		foreach ( $raw as $question_id => $value ) {
			$key = sanitize_text_field( $question_id );
			if ( is_array( $value ) ) {
				// Grid rows arrive keyed by row index; sort so the positions stay meaningful.
				$clean = $value;
				ksort( $clean, SORT_NUMERIC );
				$list = array();
				foreach ( $clean as $entry ) {
					$list[] = sanitize_text_field( is_array( $entry ) ? implode( ',', $entry ) : $entry );
				}
				$list = array_values( array_filter( $list, 'strlen' ) );
				if ( ! empty( $list ) ) {
					$answers[ $key ] = $list;
				}
				continue;
			}
			$single = sanitize_textarea_field( $value );
			if ( '' !== trim( $single ) ) {
				$answers[ $key ] = array( $single );
			}
		}
		return $answers;
	}

	/**
	 * Whether this visitor has submitted too often.
	 *
	 * @return bool
	 */
	protected static function is_throttled() {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'ludoya_signup_' . md5( $ip );
		$hits = (int) get_transient( $key );
		if ( $hits >= 5 ) {
			return true;
		}
		set_transient( $key, $hits + 1, MINUTE_IN_SECONDS );
		return false;
	}

	/**
	 * Store the outcome for the next page load and redirect back.
	 *
	 * @param string $redirect Where to send the visitor.
	 * @param string $status   ok or error.
	 * @param string $message  What to tell them.
	 */
	protected static function finish( $redirect, $status, $message ) {
		set_transient( self::notice_key(), array( $status, $message ), 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'ludoya_signup', $status, $redirect ) );
		exit;
	}

	/**
	 * Read and clear the stored outcome.
	 *
	 * @return array|null Status and message, or null.
	 */
	protected static function take_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		if ( ! isset( $_GET['ludoya_signup'] ) ) {
			return null;
		}
		$notice = get_transient( self::notice_key() );
		delete_transient( self::notice_key() );
		return is_array( $notice ) ? $notice : null;
	}

	/**
	 * Per-visitor key for the outcome transient.
	 *
	 * @return string
	 */
	protected static function notice_key() {
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
		return 'ludoya_notice_' . md5( $ip . $agent );
	}
}
