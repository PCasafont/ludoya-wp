<?php
/**
 * Admin menu, settings screen and the shared notice plumbing.
 *
 * @package Ludoya
 */

defined( 'ABSPATH' ) || exit;

/**
 * The wp-admin side of the plugin.
 */
class Ludoya_Admin {

	const SETTINGS_ACTION = 'ludoya_save_settings';

	/**
	 * Hook the admin up.
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_' . self::SETTINGS_ACTION, array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_notices' ) );
	}

	/**
	 * Register the menu.
	 */
	public static function menu() {
		$capability = ludoya_capability();

		add_menu_page(
			__( 'Ludoya', 'ludoya' ),
			__( 'Ludoya', 'ludoya' ),
			$capability,
			'ludoya-events',
			array( 'Ludoya_Events_Admin', 'render_list' ),
			'dashicons-games',
			26
		);
		add_submenu_page(
			'ludoya-events',
			__( 'Events', 'ludoya' ),
			__( 'Events', 'ludoya' ),
			$capability,
			'ludoya-events',
			array( 'Ludoya_Events_Admin', 'render_list' )
		);
		add_submenu_page(
			'ludoya-events',
			__( 'Add event', 'ludoya' ),
			__( 'Add event', 'ludoya' ),
			$capability,
			'ludoya-event-edit',
			array( 'Ludoya_Events_Admin', 'render_edit' )
		);
		add_submenu_page(
			'ludoya-events',
			__( 'Form templates', 'ludoya' ),
			__( 'Form templates', 'ludoya' ),
			$capability,
			'ludoya-forms',
			array( 'Ludoya_Events_Admin', 'render_forms' )
		);
		add_submenu_page(
			'ludoya-events',
			__( 'Settings', 'ludoya' ),
			__( 'Settings', 'ludoya' ),
			$capability,
			'ludoya-settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	/**
	 * Load admin CSS and JS on our screens only.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'ludoya' ) ) {
			return;
		}
		wp_enqueue_style( 'ludoya-admin', LUDOYA_URL . 'assets/css/admin.css', array(), ludoya_asset_version( 'assets/css/admin.css' ) );
		wp_enqueue_script( 'ludoya-admin', LUDOYA_URL . 'assets/js/admin.js', array(), ludoya_asset_version( 'assets/js/admin.js' ), true );
		wp_localize_script(
			'ludoya-admin',
			'ludoyaAdmin',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( Ludoya_Ajax::NONCE ),
				'searching' => __( 'Searching…', 'ludoya' ),
				'copied'    => __( 'Copied', 'ludoya' ),
				'copyManually' => __( 'Copy this:', 'ludoya' ),
				'noResults' => __( 'Nothing found.', 'ludoya' ),
				'confirmDelete' => __( 'Delete this event permanently? Cancelling instead keeps it visible to attendees.', 'ludoya' ),
				'confirmDeleteParent' => __( 'Delete this event and every sub-event under it permanently? Cancelling instead keeps them visible to attendees.', 'ludoya' ),
			)
		);
	}

	/**
	 * Queue a notice for the next admin page load.
	 *
	 * @param string $type    success, error or warning.
	 * @param string $message What to say.
	 */
	public static function add_notice( $type, $message ) {
		$notices   = get_transient( self::notice_key() );
		$notices   = is_array( $notices ) ? $notices : array();
		$notices[] = array( $type, $message );
		set_transient( self::notice_key(), $notices, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Print and clear the queued notices.
	 */
	public static function render_notices() {
		$notices = get_transient( self::notice_key() );
		if ( ! is_array( $notices ) ) {
			return;
		}
		delete_transient( self::notice_key() );
		foreach ( $notices as $notice ) {
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr( $notice[0] ),
				esc_html( $notice[1] )
			);
		}
	}

	/**
	 * The settings screen.
	 */
	public static function render_settings() {
		if ( ! current_user_can( ludoya_capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'ludoya' ) );
		}

		$settings = Ludoya_Settings::all();
		$status   = null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only branch, the test itself is nonced below.
		if ( isset( $_GET['ludoya_test'] ) && check_admin_referer( 'ludoya_test_connection' ) ) {
			$status = Ludoya_Client::ping();
		}

		include LUDOYA_DIR . 'templates/admin/settings.php';
	}

	/**
	 * Persist the settings form.
	 */
	public static function save_settings() {
		if ( ! current_user_can( ludoya_capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'ludoya' ) );
		}
		check_admin_referer( self::SETTINGS_ACTION );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above; Ludoya_Settings::save sanitises every field.
		Ludoya_Settings::save( wp_unslash( $_POST ) );
		self::add_notice( 'success', __( 'Settings saved.', 'ludoya' ) );

		wp_safe_redirect( admin_url( 'admin.php?page=ludoya-settings' ) );
		exit;
	}

	/**
	 * Per-user key for the notice queue.
	 *
	 * @return string
	 */
	protected static function notice_key() {
		return 'ludoya_notices_' . get_current_user_id();
	}
}
