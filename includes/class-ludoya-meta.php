<?php
/**
 * Social meta tags for pages that show one specific event.
 *
 * A club shares its event links in WhatsApp and Telegram, and what the chat shows is decided by
 * Open Graph tags in the head — which a shortcode rendering in the body is too late to add. So the
 * event is resolved from the request before the head is printed, through the same cache the
 * shortcode will hit a moment later, and the page gets the event's title, description and image
 * as its card.
 *
 * @package Ludoya
 */

defined( 'ABSPATH' ) || exit;

/**
 * Emits Open Graph and Twitter tags for the event a page is about to render.
 */
class Ludoya_Meta {

	/**
	 * Hook into the head.
	 */
	public static function register() {
		add_action( 'wp_head', array( __CLASS__, 'render' ), 5 );
	}

	/**
	 * Print the tags, when this request is for one specific event.
	 */
	public static function render() {
		$event_id = self::requested_event_id();
		if ( '' === $event_id ) {
			return;
		}

		$event = Ludoya_Client::get( 'events/' . rawurlencode( $event_id ) );
		if ( is_wp_error( $event ) || empty( $event['title'] ) ) {
			return;
		}

		$image = ludoya_get( $event, 'imageUrl' );
		if ( empty( $image ) ) {
			$image = ludoya_get( $event, 'game.imageUrl' );
		}

		$when = ludoya_event_when(
			ludoya_get( $event, 'startsAt' ),
			ludoya_get( $event, 'endsAt' ),
			ludoya_get( $event, 'timeZone' )
		);

		$description = trim( (string) ludoya_get( $event, 'description', '' ) );
		$description = $description ? wp_strip_all_tags( $description ) : '';
		// The date is the one thing every share needs; lead with it.
		$description = trim( $when['text'] . ( $description ? ' — ' . $description : '' ) );
		if ( function_exists( 'mb_substr' ) && mb_strlen( $description ) > 200 ) {
			$description = mb_substr( $description, 0, 199 ) . '…';
		}

		$tags = array(
			'og:title'       => $event['title'],
			'og:description' => $description,
			'og:type'        => 'website',
		);
		if ( ! empty( $image ) ) {
			$tags['og:image']     = $image;
			$tags['twitter:card'] = 'summary_large_image';
		}

		/**
		 * The social tags about to be printed for an event page.
		 *
		 * Return an empty array to let an SEO plugin own the head instead.
		 *
		 * @param array $tags  Property to content.
		 * @param array $event The event, as returned by the API.
		 */
		$tags = apply_filters( 'ludoya_event_meta', $tags, $event );

		foreach ( $tags as $ludoya_property => $ludoya_content ) {
			if ( '' === (string) $ludoya_content ) {
				continue;
			}
			printf(
				0 === strpos( $ludoya_property, 'twitter:' )
					? '<meta name="%s" content="%s" />' . "\n"
					: '<meta property="%s" content="%s" />' . "\n",
				esc_attr( $ludoya_property ),
				esc_attr( $ludoya_content )
			);
		}
	}

	/**
	 * Which event this request is about, or an empty string.
	 *
	 * Two ways a page ends up about one event: the shared-page pattern carries it in the link, and
	 * a dedicated page names it in its own content — as a shortcode id or as the block's attribute.
	 *
	 * @return string
	 */
	protected static function requested_event_id() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
		if ( isset( $_GET['ludoya_event'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return sanitize_text_field( wp_unslash( $_GET['ludoya_event'] ) );
		}

		if ( ! is_singular() ) {
			return '';
		}
		$post = get_post();
		if ( ! $post ) {
			return '';
		}

		if ( preg_match( '/\[ludoya_event\s[^\]]*id="([^"]+)"/', $post->post_content, $m ) ) {
			return sanitize_text_field( $m[1] );
		}
		if ( preg_match( '/<!--\s*wp:ludoya\/event\s.*?"id"\s*:\s*"([^"]+)"/', $post->post_content, $m ) ) {
			return sanitize_text_field( $m[1] );
		}
		return '';
	}
}
