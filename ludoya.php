<?php
/**
 * Plugin Name:       Ludoya
 * Plugin URI:        https://ludoya.com
 * Description:       Show your Ludoya events, collection and stats on your WordPress site, and run your event admin from wp-admin.
 * Version:           0.3.0
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

define( 'LUDOYA_VERSION', '0.3.0' );
define( 'LUDOYA_FILE', __FILE__ );
define( 'LUDOYA_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUDOYA_URL', plugin_dir_url( __FILE__ ) );

require_once LUDOYA_DIR . 'includes/helpers.php';
require_once LUDOYA_DIR . 'includes/class-ludoya-settings.php';
require_once LUDOYA_DIR . 'includes/class-ludoya-client.php';
require_once LUDOYA_DIR . 'includes/class-ludoya-shortcodes.php';
require_once LUDOYA_DIR . 'includes/class-ludoya-blocks.php';
require_once LUDOYA_DIR . 'includes/class-ludoya-signup.php';
require_once LUDOYA_DIR . 'includes/class-ludoya-meta.php';

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
	Ludoya_Meta::register();

	if ( is_admin() ) {
		Ludoya_Admin::register();
		Ludoya_Events_Admin::register();
		Ludoya_Ajax::register();
	}
}
add_action( 'init', 'ludoya_bootstrap' );

/**
 * Register the front-end assets. Rendering enqueues them only where something of ours is on the
 * page, so a page without Ludoya content loads nothing.
 */
function ludoya_register_assets() {
	wp_register_style( 'ludoya', LUDOYA_URL . 'assets/css/ludoya.css', array(), ludoya_asset_version( 'assets/css/ludoya.css' ) );
	wp_register_script( 'ludoya', LUDOYA_URL . 'assets/js/ludoya.js', array(), ludoya_asset_version( 'assets/js/ludoya.js' ), true );
}
add_action( 'wp_enqueue_scripts', 'ludoya_register_assets' );

/**
 * The same stylesheet, inside the block editor's canvas.
 *
 * The editor previews render server-side through the very templates the visitor sees, but the
 * editor canvas is an iframe with its own head: a style enqueued during that render never reaches
 * it, and the preview collapses into unstyled text. enqueue_block_assets is the one hook whose
 * styles are copied into the canvas, so the preview looks like the page will.
 */
function ludoya_editor_assets() {
	if ( ! is_admin() ) {
		return;
	}
	ludoya_register_assets();
	wp_enqueue_style( 'ludoya' );
}
add_action( 'enqueue_block_assets', 'ludoya_editor_assets' );

register_deactivation_hook( __FILE__, 'ludoya_flush_cache' );
