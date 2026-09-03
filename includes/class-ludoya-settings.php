<?php
/**
 * Stored settings, and the defaults everything else reads through.
 *
 * @package Ludoya
 */

defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper over the single `ludoya_settings` option.
 */
class Ludoya_Settings {

	const OPTION = 'ludoya_settings';

	/**
	 * Defaults, also the shape of the option.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'api_key'      => '',
			'cache_ttl'    => 300,
			'timeout'      => 10,
			'signup_open'  => 0,
			'consent_text' => '',
		);
	}

	/**
	 * All settings, defaults filled in.
	 *
	 * @return array
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::defaults(), $stored );
	}

	/**
	 * One setting.
	 *
	 * @param string $key     Setting name.
	 * @param mixed  $default Returned when unset.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		return isset( $all[ $key ] ) && '' !== $all[ $key ] ? $all[ $key ] : $default;
	}

	/**
	 * The API key.
	 *
	 * Also readable from a constant, which is how a site that keeps secrets out of the database
	 * (wp-config.php, an env var, a secrets manager) supplies it.
	 *
	 * @return string
	 */
	public static function api_key() {
		if ( defined( 'LUDOYA_API_KEY' ) && LUDOYA_API_KEY ) {
			return (string) LUDOYA_API_KEY;
		}
		return (string) self::get( 'api_key', '' );
	}

	/**
	 * Whether the key comes from a constant, in which case the settings field is read-only.
	 *
	 * @return bool
	 */
	public static function api_key_is_constant() {
		return defined( 'LUDOYA_API_KEY' ) && LUDOYA_API_KEY;
	}

	/**
	 * Base URL of the API, no trailing slash.
	 *
	 * There is one Ludoya, so this is not a setting: a site owner has no reason to change it, and a
	 * wrong value here looks exactly like a broken API key. Development against a local server
	 * overrides it from wp-config.php, which is a deliberate step rather than a stray keystroke.
	 *
	 * @return string
	 */
	public static function api_base() {
		if ( defined( 'LUDOYA_API_BASE' ) && LUDOYA_API_BASE ) {
			return untrailingslashit( (string) LUDOYA_API_BASE );
		}
		return 'https://api.ludoya.com';
	}

	/**
	 * Base URL of the Ludoya web app, for links out to events and games.
	 *
	 * @return string
	 */
	public static function site_base() {
		if ( defined( 'LUDOYA_SITE_BASE' ) && LUDOYA_SITE_BASE ) {
			return untrailingslashit( (string) LUDOYA_SITE_BASE );
		}
		return 'https://app.ludoya.com';
	}

	/**
	 * How long a GET stays cached, in seconds.
	 *
	 * @return int
	 */
	public static function cache_ttl() {
		return max( 0, (int) self::get( 'cache_ttl', 300 ) );
	}

	/**
	 * HTTP timeout, in seconds.
	 *
	 * @return int
	 */
	public static function timeout() {
		return max( 1, (int) self::get( 'timeout', 10 ) );
	}

	/**
	 * Whether visitors may sign themselves up through the front-end form.
	 *
	 * @return bool
	 */
	public static function signup_open() {
		return (bool) self::get( 'signup_open', 0 );
	}

	/**
	 * Save settings, sanitised.
	 *
	 * @param array $input Raw input.
	 * @return array The stored settings.
	 */
	public static function save( $input ) {
		$current = self::all();
		$clean   = array(
			'cache_ttl'    => isset( $input['cache_ttl'] ) ? max( 0, (int) $input['cache_ttl'] ) : $current['cache_ttl'],
			'timeout'      => isset( $input['timeout'] ) ? max( 1, (int) $input['timeout'] ) : $current['timeout'],
			'signup_open'  => empty( $input['signup_open'] ) ? 0 : 1,
			'consent_text' => isset( $input['consent_text'] ) ? wp_kses_post( $input['consent_text'] ) : $current['consent_text'],
		);

		// An empty key field means "leave it alone", so that saving the page after the key has been
		// masked does not wipe it.
		$submitted_key   = isset( $input['api_key'] ) ? trim( $input['api_key'] ) : '';
		$clean['api_key'] = '' === $submitted_key ? $current['api_key'] : sanitize_text_field( $submitted_key );

		update_option( self::OPTION, $clean, false );
		ludoya_flush_cache();
		return $clean;
	}
}
