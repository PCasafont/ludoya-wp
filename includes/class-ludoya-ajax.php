<?php
/**
 * Admin-only search endpoints, so the event form can pick a game or a person by name.
 *
 * @package Ludoya
 */

defined( 'ABSPATH' ) || exit;

/**
 * Proxies the API's search endpoints for the admin screens.
 */
class Ludoya_Ajax {

	const NONCE = 'ludoya_admin_search';

	/**
	 * Hook the endpoints up. Logged-in only: there is no public use for these.
	 */
	public static function register() {
		add_action( 'wp_ajax_ludoya_search_games', array( __CLASS__, 'search_games' ) );
		add_action( 'wp_ajax_ludoya_search_users', array( __CLASS__, 'search_users' ) );
	}

	/**
	 * Search the board game catalogue.
	 */
	public static function search_games() {
		$query = self::guard();

		$response = Ludoya_Client::get(
			'search/boardgames',
			array(
				'query'      => $query,
				'pagination' => '10,0',
			),
			MINUTE_IN_SECONDS
		);
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$results = array();
		foreach ( ludoya_get( $response, 'games.elements', array() ) as $game ) {
			$results[] = array(
				'id'    => $game['id'],
				'label' => $game['name'] . ( empty( $game['yearPublished'] ) ? '' : ' (' . (int) $game['yearPublished'] . ')' ),
			);
		}
		wp_send_json_success( $results );
	}

	/**
	 * Search users, narrowed to the ones worth adding to an event.
	 */
	public static function search_users() {
		$query = self::guard();

		// PLAY is the intent for putting somebody at a table, which is what every picker here does.
		// It searches on the "can be tagged in a play" visibility rather than on plain profile
		// visibility, so people who opted out of that do not show up.
		$response = Ludoya_Client::get(
			'search/users',
			array(
				'query'      => $query,
				'intent'     => 'PLAY',
				'pagination' => '10,0',
			),
			MINUTE_IN_SECONDS
		);
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$results = array();
		foreach ( ludoya_get( $response, 'users.elements', array() ) as $user ) {
			$results[] = array(
				'id'    => $user['id'],
				'label' => $user['name'] . ' (@' . $user['username'] . ')',
			);
		}
		wp_send_json_success( $results );
	}

	/**
	 * Check the nonce and capability, and return the search text.
	 *
	 * @return string
	 */
	protected static function guard() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( ludoya_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'ludoya' ) ), 403 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked immediately above.
		$query = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		if ( strlen( $query ) < 2 ) {
			wp_send_json_success( array() );
		}
		return $query;
	}
}
