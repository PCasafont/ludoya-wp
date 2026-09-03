<?php
/**
 * Plugin Name:       Ludoya
 * Plugin URI:        https://ludoya.com
 * Description:       Show your Ludoya events, collection and stats on your WordPress site, and run your event admin from wp-admin.
 * Version:           0.2.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Ludoya
 * Author URI:        https://ludoya.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ludoya
 * Domain Path:       /languages
 *
 * @package Ludoya
 */

defined( 'ABSPATH' ) || exit;

define( 'LUDOYA_VERSION', '0.2.0' );
define( 'LUDOYA_FILE', __FILE__ );
define( 'LUDOYA_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUDOYA_URL', plugin_dir_url( __FILE__ ) );

require_once LUDOYA_DIR . 'includes/helpers.php';
require_once LUDOYA_DIR . 'includes/class-ludoya-settings.php';
require_once LUDOYA_DIR . 'includes/class-ludoya-client.php';
require_once LUDOYA_DIR . 'includes/class-ludoya-shortcodes.php';
require_once LUDOYA_DIR . 'includes/class-ludoya-blocks.php';
require_once LUDOYA_DIR . 'includes/class-ludoya-signup.php';

if ( is_admin() ) {
	require_once LUDOYA_DIR . 'includes/class-ludoya-admin.php';
	require_once LUDOYA_DIR . 'includes/class-ludoya-events-table.php';
	require_once LUDOYA_DIR . 'includes/class-ludoya-events-admin.php';
	require_once LUDOYA_DIR . 'includes/class-ludoya-ajax.php';
}

/**
 * Boot the plugin once WordPress has loaded its own translations.
 */
function ludoya_bootstrap() {
	load_plugin_textdomain( 'ludoya', false, dirname( plugin_basename( LUDOYA_FILE ) ) . '/languages' );

	Ludoya_Shortcodes::register();
	Ludoya_Blocks::register();
	Ludoya_Signup::register();

	if ( is_admin() ) {
		Ludoya_Admin::register();
		Ludoya_Events_Admin::register();
		Ludoya_Ajax::register();
	}
}
add_action( 'init', 'ludoya_bootstrap' );

/**
 * Front-end stylesheet, enqueued only where something of ours is on the page.
 */
function ludoya_enqueue_assets() {
	wp_register_style( 'ludoya', LUDOYA_URL . 'assets/css/ludoya.css', array(), ludoya_asset_version( 'assets/css/ludoya.css' ) );
}
add_action( 'wp_enqueue_scripts', 'ludoya_enqueue_assets' );

register_deactivation_hook( __FILE__, 'ludoya_flush_cache' );
