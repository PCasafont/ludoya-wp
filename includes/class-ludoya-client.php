<?php
/**
 * HTTP client for the Ludoya public API.
 *
 * Everything goes through here: one place that knows about the key header, the cache, the rate
 * limit headers and the shape of an error body.
 *
 * @package Ludoya
 */

defined( 'ABSPATH' ) || exit;

/**
 * Client for the public/v1 API.
 */
class Ludoya_Client {

	const RATE_TRANSIENT = 'ludoya_rate_limit';

	/**
	 * ETag of the most recent GET, so an edit screen can send If-Match on the way back.
	 *
	 * @var string
	 */
	protected static $last_etag = '';

	/**
	 * GET, cached.
	 *
	 * @param string   $path  Path under public/v1, for example events.
	 * @param array    $query Query arguments.
	 * @param int|null $ttl   Cache lifetime in seconds; null uses the configured one, 0 skips the cache.
	 * @return array|WP_Error
	 */
	public static function get( $path, $query = array(), $ttl = null ) {
		$ttl = ( null === $ttl ) ? Ludoya_Settings::cache_ttl() : (int) $ttl;
		$key = self::cache_key( $path, $query );

		if ( $ttl > 0 ) {
			$cached = get_transient( $key );
			if ( is_array( $cached ) ) {
				self::$last_etag = isset( $cached['etag'] ) ? $cached['etag'] : '';
				return $cached['data'];
			}
		}

		$result = self::request( 'GET', $path, $query );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( $ttl > 0 ) {
			set_transient(
				$key,
				array(
					'data' => $result,
					'etag' => self::$last_etag,
				),
				$ttl
			);
		}
		return $result;
	}

	/**
	 * POST. Writes are never cached and always invalidate the cache.
	 *
	 * @param string $path Path under public/v1.
	 * @param array  $body JSON body.
	 * @return array|WP_Error
	 */
	public static function post( $path, $body = array() ) {
		return self::write( 'POST', $path, $body );
	}

	/**
	 * PATCH, the safe update: it only touches the keys it is given, so fields this API does not
	 * expose keep whatever staff set in the Ludoya app.
	 *
	 * @param string $path Path under public/v1.
	 * @param array  $body JSON body.
	 * @param string $etag ETag from the matching GET, sent as If-Match.
	 * @return array|WP_Error
	 */
	public static function patch( $path, $body = array(), $etag = '' ) {
		$headers = $etag ? array( 'If-Match' => $etag ) : array();
		return self::write( 'PATCH', $path, $body, $headers );
	}

	/**
	 * PUT.
	 *
	 * @param string $path Path under public/v1.
	 * @param array  $body JSON body.
	 * @return array|WP_Error
	 */
	public static function put( $path, $body = array() ) {
		return self::write( 'PUT', $path, $body );
	}

	/**
	 * DELETE.
	 *
	 * @param string $path  Path under public/v1.
	 * @param array  $query Query arguments.
	 * @return array|WP_Error
	 */
	public static function delete( $path, $query = array() ) {
		$result = self::request( 'DELETE', $path, $query );
		if ( ! is_wp_error( $result ) ) {
			ludoya_flush_cache();
		}
		return $result;
	}

	/**
	 * The ETag of the last GET.
	 *
	 * @return string
	 */
	public static function last_etag() {
		return self::$last_etag;
	}

	/**
	 * What the last authenticated call reported about the rate limit.
	 *
	 * @return array|false
	 */
	public static function rate_limit() {
		return get_transient( self::RATE_TRANSIENT );
	}

	/**
	 * Whether a key has been configured at all.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return '' !== Ludoya_Settings::api_key();
	}

	/**
	 * A cheap authenticated call, for the settings page's connection test.
	 *
	 * @return array|WP_Error
	 */
	public static function ping() {
		return self::get( 'locations', array(), 0 );
	}

	/**
	 * Perform a write and drop the cache on success.
	 *
	 * @param string $method  HTTP method.
	 * @param string $path    Path under public/v1.
	 * @param array  $body    JSON body.
	 * @param array  $headers Extra headers.
	 * @return array|WP_Error
	 */
	protected static function write( $method, $path, $body, $headers = array() ) {
		$result = self::request( $method, $path, array(), $body, $headers );
		if ( ! is_wp_error( $result ) ) {
			ludoya_flush_cache();
		}
		return $result;
	}

	/**
	 * The one place a request is actually made.
	 *
	 * @param string     $method  HTTP method.
	 * @param string     $path    Path under public/v1.
	 * @param array      $query   Query arguments.
	 * @param array|null $body    JSON body, or null for no body.
	 * @param array      $headers Extra headers.
	 * @return array|WP_Error Decoded body, or an empty array for a 204.
	 */
	public static function request( $method, $path, $query = array(), $body = null, $headers = array() ) {
		$api_key = Ludoya_Settings::api_key();
		if ( '' === $api_key ) {
			return new WP_Error(
				'ludoya_no_key',
				__( 'No Ludoya API key configured. Add one under Ludoya, Settings.', 'ludoya' )
			);
		}

		$url = Ludoya_Settings::api_base() . '/public/v1/' . ltrim( $path, '/' );
		$query = array_filter(
			$query,
			static function ( $value ) {
				return '' !== $value && null !== $value;
			}
		);
		if ( ! empty( $query ) ) {
			// add_query_arg does not encode what it is given, and these values carry commas and
			// semicolons that the API's own parameter grammar uses.
			$url = add_query_arg( array_map( 'rawurlencode', $query ), $url );
		}

		$args = array(
			'method'  => $method,
			'timeout' => Ludoya_Settings::timeout(),
			'headers' => array_merge(
				array(
					'X-Api-Key' => $api_key,
					'Accept'    => 'application/json',
					'User-Agent' => 'ludoya-wp/' . LUDOYA_VERSION . '; ' . home_url( '/' ),
				),
				$headers
			),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		self::remember_rate_limit( $response );
		self::$last_etag = (string) wp_remote_retrieve_header( $response, 'etag' );

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$data   = ( '' === $raw ) ? array() : json_decode( $raw, true );

		if ( $status >= 200 && $status < 300 ) {
			return is_array( $data ) ? $data : array();
		}
		return self::error_from( $status, is_array( $data ) ? $data : array(), $response );
	}

	/**
	 * Turn a failure response into a WP_Error whose message a site owner can act on.
	 *
	 * The API's own message is human-readable but written for a partner developer; where a status
	 * has a concrete cause in this plugin, say that instead.
	 *
	 * @param int   $status   HTTP status.
	 * @param array $data     Decoded message and code body, when there was one.
	 * @param array $response Raw response, for headers.
	 * @return WP_Error
	 */
	protected static function error_from( $status, $data, $response ) {
		$message = isset( $data['message'] ) ? $data['message'] : '';
		$code    = isset( $data['code'] ) ? $data['code'] : 'ludoya_http_' . $status;

		switch ( $status ) {
			case 401:
				$message = __( 'Ludoya rejected the API key. Check it under Ludoya, Settings.', 'ludoya' );
				break;
			case 403:
				$message = $message ? $message : __( 'The Ludoya public API requires the Business plan.', 'ludoya' );
				break;
			case 412:
				$message = __( 'This event changed in Ludoya while you were editing it here. Reload the page and try again.', 'ludoya' );
				break;
			case 429:
				$retry   = wp_remote_retrieve_header( $response, 'retry-after' );
				$message = sprintf(
					/* translators: %s: number of seconds to wait. */
					__( 'Ludoya rate limit reached. Try again in %s seconds.', 'ludoya' ),
					$retry ? $retry : '60'
				);
				break;
			default:
				if ( '' === $message ) {
					/* translators: %d: HTTP status code. */
					$message = sprintf( __( 'Ludoya returned HTTP %d.', 'ludoya' ), $status );
				}
		}

		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/**
	 * Keep the last seen rate-limit budget, so the settings page can show it.
	 *
	 * @param array $response Raw response.
	 */
	protected static function remember_rate_limit( $response ) {
		$limit = wp_remote_retrieve_header( $response, 'x-ratelimit-limit' );
		if ( '' === $limit || null === $limit ) {
			return;
		}
		set_transient(
			self::RATE_TRANSIENT,
			array(
				'limit'     => (int) $limit,
				'remaining' => (int) wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' ),
				'resets_in' => (int) wp_remote_retrieve_header( $response, 'x-ratelimit-reset' ),
				'at'        => time(),
			),
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Cache key for a GET.
	 *
	 * The key's own fingerprint is part of it: two sites sharing a database but not a Ludoya
	 * organisation must not share cached answers.
	 *
	 * @param string $path  Path under public/v1.
	 * @param array  $query Query arguments.
	 * @return string
	 */
	protected static function cache_key( $path, $query ) {
		$generation  = (int) get_option( 'ludoya_cache_generation', 1 );
		$fingerprint = substr( md5( Ludoya_Settings::api_key() . Ludoya_Settings::api_base() ), 0, 8 );
		return 'ludoya_' . $generation . '_' . $fingerprint . '_' . md5( $path . wp_json_encode( $query ) );
	}
}
