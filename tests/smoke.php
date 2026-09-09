<?php
/**
 * Smoke test: run with `php tests/smoke.php`. No WordPress, no dependencies.
 *
 * It stubs the handful of WordPress functions the plugin touches and then exercises the parts whose
 * correctness is not obvious from reading them: which keys a PATCH body may contain (getting this
 * wrong wipes fields staff set in the Ludoya app), the local-time to UTC round trip, how sub-events
 * are grouped under their parent, the sign-up answer collector's ordering, and the cache key's
 * isolation between API keys.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'LUDOYA_VERSION', '0.1.0' );
define( 'LUDOYA_FILE', dirname( __DIR__ ) . '/ludoya.php' );
define( 'LUDOYA_DIR', dirname( __DIR__ ) . '/' );
define( 'LUDOYA_URL', 'http://example.test/wp-content/plugins/ludoya/' );

$GLOBALS['options'] = array();

function apply_filters( $tag, $value ) { return $value; }
function add_action() {}
function add_shortcode() {}
function __( $text ) { return $text; }
function esc_html__( $text ) { return $text; }
function get_option( $name, $default = false ) { return isset( $GLOBALS['options'][ $name ] ) ? $GLOBALS['options'][ $name ] : $default; }
function update_option( $name, $value ) { $GLOBALS['options'][ $name ] = $value; return true; }
function wp_timezone() { return new DateTimeZone( 'Europe/Madrid' ); }
function wp_timezone_string() { return 'Europe/Madrid'; }
function wp_date( $format, $timestamp, $zone ) { return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $zone )->format( $format ); }
function untrailingslashit( $s ) { return rtrim( $s, '/' ); }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $s ) ); }
function sanitize_email( $s ) { return trim( (string) $s ); }
function esc_url_raw( $s ) { return trim( (string) $s ); }
function esc_url( $s ) { return $s; }
function esc_attr( $s ) { return $s; }
function esc_html( $s ) { return $s; }
function is_email( $s ) { return (bool) filter_var( $s, FILTER_VALIDATE_EMAIL ); }
function current_user_can() { return true; }
function wp_json_encode( $data ) { return json_encode( $data ); }
function locate_template() { return ''; }
function get_transient() { return false; }
function set_transient() { return true; }
function delete_transient() { return true; }
function wp_enqueue_style() {}
function is_admin() { return false; }
function home_url( $p = '/' ) { return 'http://example.test' . $p; }
function admin_url( $p = '' ) { return 'http://example.test/wp-admin/' . $p; }
function wp_kses_post( $s ) { return $s; }
function wp_unslash( $v ) { return $v; }

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code, $message = '', $data = array() ) { $this->code = $code; $this->message = $message; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

require LUDOYA_DIR . 'includes/helpers.php';
require LUDOYA_DIR . 'includes/class-ludoya-settings.php';
require LUDOYA_DIR . 'includes/class-ludoya-client.php';
require LUDOYA_DIR . 'includes/class-ludoya-signup.php';
require LUDOYA_DIR . 'includes/class-ludoya-shortcodes.php';
require LUDOYA_DIR . 'includes/class-ludoya-blocks.php';

// The admin classes need WP_List_Table; load only the one under test.
class WP_List_Table { public function __construct( $args = array() ) {} }
require LUDOYA_DIR . 'includes/class-ludoya-events-admin.php';

$failures = array();

function check( $label, $actual, $expected ) {
	global $failures;
	$ok = ( $actual === $expected );
	printf( "%s %s\n", $ok ? 'ok  ' : 'FAIL', $label );
	if ( ! $ok ) {
		printf( "     expected: %s\n     actual:   %s\n", var_export( $expected, true ), var_export( $actual, true ) );
		$failures[] = $label;
	}
}

// --- The PATCH body rule -----------------------------------------------------------------------

$method = new ReflectionMethod( 'Ludoya_Events_Admin', 'body_from_post' );
$method->setAccessible( true );

// The form now shows every settings field's current value (the API reports them), so blanking a
// shown field means "clear it" and every settings field travels on every save. The one exception is
// an API too old to report them, marked by api_reports_settings=0: what its form showed for those
// fields was a default, so sending it would overwrite truth with a guess.
$body = $method->invoke(
	null,
	array(
		'api_reports_settings' => '1',
		'type'        => 'MEETUP',
		'title'       => 'Partida oberta',
		'description' => 'Vine a jugar',
		'starts_at'   => '2026-12-05T18:00',
		'ends_at'     => '',
		'capacity'    => '',
		'location_id' => '',
		'visibility'  => 'PUBLIC',
		'min_participants' => '',
		'languages'   => '',
		'teacher_user_id' => '',
	),
	'Europe/Madrid',
	false
);

check( 'title survives', $body['title'], 'Partida oberta' );
check( 'local time becomes the right UTC instant', $body['startsAt'], '2026-12-05T17:00:00Z' );
check( 'a blank date clears the field', $body['endsAt'], null );
check( 'a blank capacity clears the field', $body['capacity'], null );
check( 'a blank teacher clears the field', $body['teacherUserId'], null );
check( 'a blank location is not sent (keep the current one)', array_key_exists( 'locationId', $body ), false );
check( 'the shown visibility is sent', $body['visibility'], 'PUBLIC' );
check( 'a blank minimum clears the field', $body['minParticipants'], null );
check( 'an unticked attendance box is sent as false', $body['restrictedAttendance'], false );
check( 'blank languages clear to inherit', $body['languages'], array() );
check( 'draft is not sent on an edit', array_key_exists( 'draft', $body ), false );

$body = $method->invoke(
	null,
	array(
		'api_reports_settings'  => '1',
		'type'                  => 'TOURNAMENT',
		'title'                 => 'Lliga',
		'location_id'           => 'loc_1',
		'visibility'            => 'ONLY_GROUP',
		'min_participants'      => '4',
		'restricted_attendance' => '1',
		'languages'             => 'CA, es;es cat',
		'external_id'           => 'DAU-2026-A17',
		'draft'                 => '1',
		'image_url'             => 'https://example.test/a.jpg',
	),
	'Europe/Madrid',
	true
);
check( 'a filled location is sent', $body['locationId'], 'loc_1' );
check( 'a filled visibility is sent', $body['visibility'], 'ONLY_GROUP' );
check( 'a filled minimum is sent as an int', $body['minParticipants'], 4 );
check( 'a ticked attendance box is sent as true', $body['restrictedAttendance'], true );
check( 'languages are parsed, lowered, deduped, three-letter codes dropped', $body['languages'], array( 'ca', 'es' ) );
check( 'the external id is sent', $body['externalId'], 'DAU-2026-A17' );
check( 'draft is sent on a create', $body['draft'], true );
check( 'an image url is wrapped', $body['image'], array( 'url' => 'https://example.test/a.jpg' ) );

// Against an API that never reported these fields, the form only showed defaults for them, so the
// guard must drop every one rather than save a guess over what staff set in the app.
$body = $method->invoke(
	null,
	array(
		'type'                  => 'MEETUP',
		'title'                 => 'Vell',
		'visibility'            => 'PUBLIC',
		'min_participants'      => '4',
		'restricted_attendance' => '1',
		'spot_id'               => 's1',
	),
	'Europe/Madrid',
	false
);
check( 'old API: visibility is dropped', array_key_exists( 'visibility', $body ), false );
check( 'old API: minimum is dropped', array_key_exists( 'minParticipants', $body ), false );
check( 'old API: attendance is dropped', array_key_exists( 'restrictedAttendance', $body ), false );
check( 'old API: the table is dropped', array_key_exists( 'spotId', $body ), false );

// Which event a new one hangs under travels on the create and never again: sending it on a patch
// would be re-parenting, which nothing on the edit screen offers, and a blank one on a create must
// not send null (the API would read that as "no parent", which is what it means anyway).
$body = $method->invoke( null, array( 'title' => 'Torneig', 'parent_event_id' => 'ev_parent' ), 'Europe/Madrid', true );
check( 'a sub-event create names its parent', $body['parentEventId'], 'ev_parent' );
$body = $method->invoke( null, array( 'title' => 'Torneig', 'parent_event_id' => 'ev_parent' ), 'Europe/Madrid', false );
check( 'an edit never sends the parent', array_key_exists( 'parentEventId', $body ), false );
$body = $method->invoke( null, array( 'title' => 'Torneig', 'parent_event_id' => '' ), 'Europe/Madrid', true );
check( 'a plain create sends no parent key at all', array_key_exists( 'parentEventId', $body ), false );

// --- Grouping sub-events under their parent --------------------------------------------------

// Two parents with children, one orphan whose parent is not in the list, one standalone. Every
// child must follow its own parent whichever way the top level runs, children in the order they
// happen, and the orphan must stay visible at the top level.
$events = array(
	array( 'id' => 'b2', 'parentId' => 'b', 'startsAt' => '2026-10-04T10:00:00Z' ),
	array( 'id' => 'a', 'startsAt' => '2026-09-12T09:00:00Z' ),
	array( 'id' => 'orphan', 'parentId' => 'gone', 'startsAt' => '2026-09-20T09:00:00Z' ),
	array( 'id' => 'b', 'startsAt' => '2026-10-03T09:00:00Z' ),
	array( 'id' => 'a2', 'parentId' => 'a', 'startsAt' => '2026-09-12T16:00:00Z' ),
	array( 'id' => 'b1', 'parentId' => 'b', 'startsAt' => '2026-10-03T11:00:00Z' ),
	array( 'id' => 'solo', 'startsAt' => '2026-09-30T09:00:00Z' ),
	array( 'id' => 'a1', 'parentId' => 'a', 'startsAt' => '2026-09-12T10:00:00Z' ),
);
$ids = static function ( $grouped ) {
	return array_map( static function ( $e ) { return $e['id']; }, $grouped );
};
check(
	'soonest first: parents by date, each followed by its children in order, orphan at the top level',
	$ids( ludoya_group_by_parent( $events, true ) ),
	array( 'a', 'a1', 'a2', 'orphan', 'solo', 'b', 'b1', 'b2' )
);
check(
	'latest first: parents reversed, children still in the order they happen',
	$ids( ludoya_group_by_parent( $events, false ) ),
	array( 'b', 'b1', 'b2', 'solo', 'orphan', 'a', 'a1', 'a2' )
);
$grouped = ludoya_group_by_parent( $events, true );
check( 'a parent knows how many children follow it', $grouped[0]['_childCount'], 2 );
check( 'a child is marked as one', $grouped[1]['_depth'], 1 );
check( 'an orphan is not marked as a child', $grouped[3]['_depth'], 0 );
check( 'nothing is lost in the grouping', count( $grouped ), count( $events ) );

// A festival: zones under the festival, demo tables under a zone. The tables follow their zone, not
// the festival, and the festival counts everything under it.
$festival = array(
	array( 'id' => 'table2', 'parentId' => 'zone', 'startsAt' => '2026-11-15T14:00:00Z' ),
	array( 'id' => 'dau', 'startsAt' => '2026-11-15T09:00:00Z' ),
	array( 'id' => 'night', 'parentId' => 'dau', 'startsAt' => '2026-11-15T21:00:00Z' ),
	array( 'id' => 'zone', 'parentId' => 'dau', 'startsAt' => '2026-11-15T09:00:00Z' ),
	array( 'id' => 'table1', 'parentId' => 'zone', 'startsAt' => '2026-11-15T10:00:00Z' ),
);
$grouped = ludoya_group_by_parent( $festival, true );
check( 'grandchildren follow their own parent', $ids( $grouped ), array( 'dau', 'zone', 'table1', 'table2', 'night' ) );
check( 'a grandchild is two deep', $grouped[2]['_depth'], 2 );
check( 'the festival counts every descendant', $grouped[0]['_childCount'], 4 );
check( 'the zone counts only its tables', $grouped[1]['_childCount'], 2 );

check( 'a meetup can host sub-events', ludoya_can_host_sub_events( array( 'type' => 'MEETUP' ) ), true );
check( 'a cancelled one cannot', ludoya_can_host_sub_events( array( 'type' => 'MEETUP', 'canceled' => true ) ), false );
check( 'a scheduled game cannot', ludoya_can_host_sub_events( array( 'type' => 'PLANNED_PLAY' ) ), false );
check( 'a play booth cannot', ludoya_can_host_sub_events( array( 'type' => 'PLAY_BOOTH' ) ), false );

// --- The instant round trip --------------------------------------------------------------------

check(
	'UTC renders back into the event time zone',
	Ludoya_Events_Admin::to_input( '2026-12-05T17:00:00Z', 'Europe/Madrid' ),
	'2026-12-05T18:00'
);
check(
	'a missing instant renders as empty',
	Ludoya_Events_Admin::to_input( null, 'Europe/Madrid' ),
	''
);
check(
	'formatting uses the event time zone, not the site one',
	ludoya_format_date( '2026-12-05T17:00:00Z', 'UTC', 'Y-m-d H:i' ),
	'2026-12-05 17:00'
);

// --- Answer collection -------------------------------------------------------------------------

$_POST['ludoya_answers'] = array(
	'q_text'  => 'Hello',
	'q_multi' => array( 2 => 'Blue', 0 => 'Red' ),
	'q_grid'  => array( 1 => 'Saturday', 0 => 'Friday' ),
	'q_blank' => '   ',
);
$collect = new ReflectionMethod( 'Ludoya_Signup', 'collect_answers' );
$collect->setAccessible( true );
$answers = $collect->invoke( null );

check( 'a text answer becomes a one-item list', $answers['q_text'], array( 'Hello' ) );
check( 'grid answers keep row order', $answers['q_grid'], array( 'Friday', 'Saturday' ) );
check( 'checkbox answers are re-ordered by index', $answers['q_multi'], array( 'Red', 'Blue' ) );
check( 'a blank answer is dropped', array_key_exists( 'q_blank', $answers ), false );

// --- Cache keying ------------------------------------------------------------------------------

$key = new ReflectionMethod( 'Ludoya_Client', 'cache_key' );
$key->setAccessible( true );
$GLOBALS['options']['ludoya_settings'] = array( 'api_key' => 'ldy_one' );
$one = $key->invoke( null, 'events', array( 'pastLimit' => '0' ) );
$GLOBALS['options']['ludoya_settings'] = array( 'api_key' => 'ldy_two' );
$two = $key->invoke( null, 'events', array( 'pastLimit' => '0' ) );
check( 'two keys do not share a cache entry', $one === $two, false );

$GLOBALS['options']['ludoya_cache_generation'] = 9;
$bumped = $key->invoke( null, 'events', array( 'pastLimit' => '0' ) );
check( 'a flush changes the key', $bumped === $two, false );

echo empty( $failures ) ? "\nALL PASS\n" : "\n" . count( $failures ) . " FAILED\n";
exit( empty( $failures ) ? 0 : 1 );
