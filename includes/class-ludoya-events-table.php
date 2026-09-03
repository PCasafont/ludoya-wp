<?php
/**
 * The events list table.
 *
 * The rows come from the API rather than from a query, so paging happens here over the array the
 * client already fetched.
 *
 * @package Ludoya
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists Ludoya events in wp-admin.
 */
class Ludoya_Events_Table extends WP_List_Table {

	const PER_PAGE = 20;

	/**
	 * Every event fetched for this screen.
	 *
	 * @var array
	 */
	protected $events;

	/**
	 * Constructor.
	 *
	 * @param array $events Events from the API.
	 */
	public function __construct( $events ) {
		parent::__construct(
			array(
				'singular' => 'ludoya_event',
				'plural'   => 'ludoya_events',
				'ajax'     => false,
			)
		);
		$this->events = $events;
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'title'        => __( 'Title', 'ludoya' ),
			'type'         => __( 'Type', 'ludoya' ),
			'starts_at'    => __( 'When', 'ludoya' ),
			'participants' => __( 'Signed up', 'ludoya' ),
			'status'       => __( 'Status', 'ludoya' ),
		);
	}

	/**
	 * Sort the events and cut the current page out of them.
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array() );

		usort(
			$this->events,
			static function ( $a, $b ) {
				$left  = isset( $a['startsAt'] ) ? $a['startsAt'] : '';
				$right = isset( $b['startsAt'] ) ? $b['startsAt'] : '';
				return strcmp( (string) $right, (string) $left );
			}
		);

		$total   = count( $this->events );
		$page    = $this->get_pagenum();
		$this->items = array_slice( $this->events, ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => self::PER_PAGE,
				'total_pages' => (int) ceil( $total / self::PER_PAGE ),
			)
		);
	}

	/**
	 * Nothing to list.
	 */
	public function no_items() {
		esc_html_e( 'No events found.', 'ludoya' );
	}

	/**
	 * Title column, with the row actions.
	 *
	 * @param array $item Event.
	 * @return string
	 */
	public function column_title( $item ) {
		$edit_url = add_query_arg(
			array(
				'page'  => 'ludoya-event-edit',
				'event' => rawurlencode( $item['id'] ),
			),
			admin_url( 'admin.php' )
		);

		$actions = array(
			'edit' => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'ludoya' ) ),
			'view' => sprintf(
				'<a href="%s" target="_blank" rel="noopener">%s</a>',
				esc_url( ludoya_event_url( $item ) ),
				esc_html__( 'View on Ludoya', 'ludoya' )
			),
		);

		// The shortcode for this one event, ready to paste into a page of its own. Without this there
		// is nowhere in wp-admin that hands you an event id, and nobody is going to transcribe 32
		// hex characters out of the address bar.
		$actions['shortcode'] = sprintf(
			'<button type="button" class="button-link ludoya-copy" data-copy="%s">%s</button>',
			esc_attr( sprintf( '[ludoya_event id="%s"]', $item['id'] ) ),
			esc_html__( 'Copy shortcode', 'ludoya' )
		);

		if ( ! empty( $item['draft'] ) ) {
			$actions['publish'] = $this->action_link( $item['id'], 'publish', __( 'Publish', 'ludoya' ) );
		}
		if ( empty( $item['canceled'] ) ) {
			$actions['cancel'] = $this->action_link( $item['id'], 'cancel', __( 'Cancel', 'ludoya' ) );
		}
		$actions['delete'] = $this->action_link( $item['id'], 'delete', __( 'Delete', 'ludoya' ), 'ludoya-delete submitdelete' );

		return sprintf(
			'<strong><a class="row-title" href="%s">%s</a></strong>%s',
			esc_url( $edit_url ),
			esc_html( $item['title'] ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Type column.
	 *
	 * @param array $item Event.
	 * @return string
	 */
	public function column_type( $item ) {
		return esc_html( ludoya_event_type_label( isset( $item['type'] ) ? $item['type'] : '' ) );
	}

	/**
	 * Date column.
	 *
	 * @param array $item Event.
	 * @return string
	 */
	public function column_starts_at( $item ) {
		$zone = isset( $item['timeZone'] ) ? $item['timeZone'] : null;
		return esc_html( ludoya_format_date( isset( $item['startsAt'] ) ? $item['startsAt'] : '', $zone ) );
	}

	/**
	 * Participants column.
	 *
	 * @param array $item Event.
	 * @return string
	 */
	public function column_participants( $item ) {
		$count = isset( $item['participantCount'] ) ? (int) $item['participantCount'] : 0;
		if ( empty( $item['capacity'] ) ) {
			return esc_html( (string) $count );
		}
		return esc_html( sprintf( '%d / %d', $count, (int) $item['capacity'] ) );
	}

	/**
	 * Status column.
	 *
	 * @param array $item Event.
	 * @return string
	 */
	public function column_status( $item ) {
		if ( ! empty( $item['canceled'] ) ) {
			return '<span class="ludoya-status ludoya-status--canceled">' . esc_html__( 'Cancelled', 'ludoya' ) . '</span>';
		}
		if ( ! empty( $item['draft'] ) ) {
			return '<span class="ludoya-status">' . esc_html__( 'Draft', 'ludoya' ) . '</span>';
		}
		$starts = isset( $item['startsAt'] ) ? strtotime( $item['startsAt'] ) : 0;
		if ( $starts && $starts < time() ) {
			return '<span class="ludoya-status">' . esc_html__( 'Past', 'ludoya' ) . '</span>';
		}
		return '<span class="ludoya-status ludoya-status--live">' . esc_html__( 'Upcoming', 'ludoya' ) . '</span>';
	}

	/**
	 * Fallback for any column without its own method.
	 *
	 * @param array  $item        Event.
	 * @param string $column_name Column.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '';
	}

	/**
	 * A nonced link to one of the row actions.
	 *
	 * @param string $event_id Event id.
	 * @param string $action   publish, cancel or delete.
	 * @param string $label    Link text.
	 * @param string $class    Extra CSS classes.
	 * @return string
	 */
	protected function action_link( $event_id, $action, $label, $class = '' ) {
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => Ludoya_Events_Admin::ROW_ACTION,
					'do'     => $action,
					'event'  => rawurlencode( $event_id ),
				),
				admin_url( 'admin-post.php' )
			),
			Ludoya_Events_Admin::ROW_ACTION . '_' . $action . '_' . $event_id
		);

		return sprintf(
			'<a href="%s" class="%s">%s</a>',
			esc_url( $url ),
			esc_attr( $class ),
			esc_html( $label )
		);
	}
}
