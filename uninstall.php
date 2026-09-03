<?php
/**
 * Remove everything the plugin stored.
 *
 * The API key lives in these options, so uninstalling has to take it with it.
 *
 * @package Ludoya
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'ludoya_settings' );
delete_option( 'ludoya_cache_generation' );
delete_transient( 'ludoya_rate_limit' );

// Cached responses are transients named by a generation that no longer exists once the option is
// gone, so they expire on their own; the ones with an explicit name are cleared here.
global $wpdb;
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_ludoya_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_ludoya_' ) . '%'
	)
);
