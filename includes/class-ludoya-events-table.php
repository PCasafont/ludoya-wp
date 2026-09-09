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
	 * Soonest first or latest first.
	 *
	 * @var bool
	 */
	protected $ascending;

	/**
	 * Constructor.
	 *
	 * @param array $events    Events from the API.
	 * @param bool  $ascending Soonest first — what an organiser scanning the upcoming list wants.
	 *                         Past views read latest first instead.
	 */
	public function __construct( $events, $ascending = false ) {
		parent::__construct(
			array(
				'singular' => 'ludoya_event',
				'plural'   => 'ludoya_events',
				'ajax'     => false,
			)
		);
		$this->events    = $events;
		$this->ascending = $ascending;
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

		// Sub-events sit under their parent, whichever way the list runs.
		$this->events = ludoya_group_by_parent( $this->events, $this->ascending );

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
	 * A sub-event's row is marked so the stylesheet can indent it under its parent.
	 *
	 * @param array $item Event.
	 */
	public function single_row( $item ) {
		$depth = isset( $item['_depth'] ) ? (int) $item['_depth'] : 0;
		if ( $depth > 0 ) {
			printf( '<tr class="ludoya-row--child" style="--ludoya-depth: %d">', (int) $depth );
		} else {
			echo '<tr>';
		}
		$this->single_row_columns( $item );
		echo '</tr>';
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

		if ( ludoya_can_host_sub_events( $item ) ) {
			$actions['sub_event'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					add_query_arg(
						array(
							'page'   => 'ludoya-event-edit',
							'parent' => rawurlencode( $item['id'] ),
						),
						admin_url( 'admin.php' )
					)
				),
				esc_html__( 'Add sub-event', 'ludoya' )
			);
		}

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
		// Deleting a parent takes its sub-events with it, so that confirmation says so.
		$child_count       = isset( $item['_childCount'] ) ? (int) $item['_childCount'] : 0;
		$actions['delete'] = $this->action_link(
			$item['id'],
			'delete',
			__( 'Delete', 'ludoya' ),
			'ludoya-delete submitdelete' . ( $child_count > 0 ? ' ludoya-delete--parent' : '' )
		);

		$badge = '';
		if ( $child_count > 0 ) {
			$badge = sprintf(
				' <span class="ludoya-status ludoya-status--count">%s</span>',
				esc_html(
					sprintf(
						/* translators: %d: number of sub-events. */
						_n( '%d sub-event', '%d sub-events', $child_count, 'ludoya' ),
						$child_count
					)
				)
			);
		}

		return sprintf(
			'<strong><a class="row-title" href="%s">%s</a></strong>%s%s',
			esc_url( $edit_url ),
			esc_html( $item['title'] ),
			$badge,
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
		// The same "Ds 21 set 18:00" the cards and the app speak, not a second date dialect.
		$when = ludoya_event_when(
			isset( $item['startsAt'] ) ? $item['startsAt'] : null,
			isset( $item['endsAt'] ) ? $item['endsAt'] : null,
			isset( $item['timeZone'] ) ? $item['timeZone'] : null
		);
		return esc_html( $when['text'] );
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
