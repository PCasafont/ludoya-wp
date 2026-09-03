<?php
/**
 * Block editor blocks.
 *
 * Every block renders through its shortcode, so there is one implementation of each view rather
 * than a PHP one and a JavaScript one that drift apart. That also keeps the plugin build-free:
 * the editor script is plain JavaScript against the wp globals, no bundler in the loop.
 *
 * @package Ludoya
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the blocks.
 */
class Ludoya_Blocks {

	/**
	 * Blocks, and the attributes each passes down to its shortcode.
	 *
	 * @return array
	 */
	protected static function definitions() {
		return array(
			'events'     => array(
				'shortcode'  => 'ludoya_events',
				'attributes' => array(
					'limit'       => array( 'type' => 'number', 'default' => 6 ),
					'past'        => array( 'type' => 'number', 'default' => 0 ),
					'type'        => array( 'type' => 'string', 'default' => '' ),
					'include_sub' => array( 'type' => 'number', 'default' => 0 ),
					'layout'      => array( 'type' => 'string', 'default' => 'cards' ),
					'event_page'  => array( 'type' => 'string', 'default' => '' ),
					'heading'     => array( 'type' => 'string', 'default' => '' ),
				),
			),
			'event'      => array(
				'shortcode'  => 'ludoya_event',
				'attributes' => array(
					'id'          => array( 'type' => 'string', 'default' => '' ),
					'show_signup' => array( 'type' => 'number', 'default' => 1 ),
				),
			),
			'signup'     => array(
				'shortcode'  => 'ludoya_signup',
				'attributes' => array(
					'event' => array( 'type' => 'string', 'default' => '' ),
				),
			),
			'collection' => array(
				'shortcode'  => 'ludoya_collection',
				'attributes' => array(
					'limit'   => array( 'type' => 'number', 'default' => 24 ),
					'search'  => array( 'type' => 'string', 'default' => '' ),
					'layout'  => array( 'type' => 'string', 'default' => 'grid' ),
					'heading' => array( 'type' => 'string', 'default' => '' ),
				),
			),
			'stats'      => array(
				'shortcode'  => 'ludoya_stats',
				'attributes' => array(
					'period'    => array( 'type' => 'string', 'default' => 'ONE_YEAR' ),
					'top_games' => array( 'type' => 'number', 'default' => 5 ),
					'heading'   => array( 'type' => 'string', 'default' => '' ),
				),
			),
			'locations'  => array(
				'shortcode'  => 'ludoya_locations',
				'attributes' => array(
					'heading' => array( 'type' => 'string', 'default' => '' ),
				),
			),
		);
	}

	/**
	 * Register every block and the editor script behind them.
	 */
	public static function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'ludoya-blocks',
			LUDOYA_URL . 'assets/js/blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ),
			ludoya_asset_version( 'assets/js/blocks.js' ),
			true
		);

		foreach ( self::definitions() as $name => $definition ) {
			register_block_type(
				'ludoya/' . $name,
				array(
					'api_version'           => 3,
					'editor_script_handles' => array( 'ludoya-blocks' ),
					'attributes'            => $definition['attributes'],
					'render_callback'       => static function ( $attributes ) use ( $definition ) {
						return self::render( $definition['shortcode'], $attributes );
					},
				)
			);
		}
	}

	/**
	 * Render a block by handing its attributes to the shortcode.
	 *
	 * @param string $shortcode  Shortcode name.
	 * @param array  $attributes Block attributes.
	 * @return string
	 */
	protected static function render( $shortcode, $attributes ) {
		$parts = array();
		foreach ( (array) $attributes as $key => $value ) {
			if ( '' === $value || null === $value ) {
				continue;
			}
			// Shortcode attributes are parsed, not HTML-escaped: a quote or a bracket would end the
			// attribute (or the shortcode) rather than appear in it.
			$parts[] = sprintf( '%s="%s"', $key, str_replace( array( '"', ']', '[' ), '', (string) $value ) );
		}
		return do_shortcode( '[' . $shortcode . ( $parts ? ' ' . implode( ' ', $parts ) : '' ) . ']' );
	}
}
