<?php
/** Standalone regressions for agenda timestamps, query boundaries and local dates. */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
$GLOBALS['tccm_test_timezone'] = new DateTimeZone( 'Europe/Paris' );

function wp_timezone() { return $GLOBALS['tccm_test_timezone']; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function esc_sql( $value ) { return $value; }
function current_time( $type ) {
	if ( 'timestamp' !== $type ) throw new RuntimeException( 'Unexpected time format.' );
	return time() + wp_timezone()->getOffset( new DateTimeImmutable() );
}
class WP_Query {
	public $query_vars = array();
	public function set( $key, $value ) { $this->query_vars[ $key ] = $value; }
	public function get( $key ) { return $this->query_vars[ $key ] ?? null; }
}
$wpdb = (object) array( 'posts' => 'test_posts', 'postmeta' => 'test_postmeta' );
require ABSPATH . 'includes/modules/interface-thecore-collectivity-module.php';
require ABSPATH . 'includes/modules/abstract-thecore-collectivity-module.php';
require ABSPATH . 'includes/features/class-thecore-collectivity-event-agenda.php';

function check_agenda( $condition, $message ) {
	if ( ! $condition ) throw new RuntimeException( $message );
}
function check_boundaries( $meta_query, $before, $after ) {
	foreach ( array( $meta_query[0][1]['value'], $meta_query[1][2]['value'] ) as $timestamp ) {
		check_agenda( $timestamp >= $before && $timestamp <= $after, 'Upcoming filters must use Unix time without adding the site offset.' );
	}
	check_agenda( '>=' === $meta_query[0][1]['compare'] && '>=' === $meta_query[1][2]['compare'], 'Events remain visible at their exact start/end boundary.' );
}

$agenda = new TheCore_Collectivity_Event_Agenda();
foreach ( array( 'Europe/Paris', 'America/New_York', '+05:30', 'UTC' ) as $timezone ) {
	$GLOBALS['tccm_test_timezone'] = new DateTimeZone( $timezone );
	$before = time();
	$args = $agenda::get_upcoming_events_query_args( array( 'posts_per_page' => 3 ) );
	check_boundaries( $args['meta_query'], $before, time() );
	check_agenda( 3 === $args['posts_per_page'], 'Preserve caller overrides.' );
	$query = new WP_Query();
	$query->set( 'posts_per_page', 6 );
	$before = time();
	$agenda->apply_elementor_diary_posts_query( $query );
	check_boundaries( $query->get( 'meta_query' ), $before, time() );
	check_agenda( 6 === $query->get( 'posts_per_page' ), 'Preserve the Elementor loop limit.' );
	$before = time();
	$clauses = $agenda->filter_diary_query_clauses( array( 'where' => 'unchanged' ), $query );
	$after = time();
	preg_match_all( '/(?:<=|>=)\s+(\d{10})/', $clauses['orderby'], $matches );
	check_agenda( 4 === count( $matches[1] ), 'Both ordering expressions compare start and end boundaries.' );
	foreach ( $matches[1] as $timestamp ) {
		check_agenda( (int) $timestamp >= $before && (int) $timestamp <= $after, 'SQL ordering must use the same Unix clock as event filtering.' );
	}
	check_agenda( 'unchanged' === $clauses['where'], 'Leave other SQL clauses untouched.' );
}
check_agenda( array( 'orderby' => 'original' ) === $agenda->filter_diary_query_clauses( array( 'orderby' => 'original' ), new WP_Query() ), 'Do not modify non-agenda queries.' );
$GLOBALS['tccm_test_timezone'] = new DateTimeZone( 'Europe/Paris' );
$normalize = new ReflectionMethod( $agenda, 'normalize_local_datetime' );
$timestamp = new ReflectionMethod( $agenda, 'local_datetime_to_timestamp' );
$normalize->setAccessible( true );
$timestamp->setAccessible( true );
foreach ( array(
	'2026-01-15 12:00' => '2026-01-15 11:00:00',
	'2026-07-15 12:00' => '2026-07-15 10:00:00',
	'2026-03-29 01:30' => '2026-03-29 00:30:00',
	'2026-03-29 03:30' => '2026-03-29 01:30:00',
	'2026-10-25 01:30' => '2026-10-24 23:30:00',
	'2026-10-25 03:30' => '2026-10-25 02:30:00',
	'2028-02-29 12:00' => '2028-02-29 11:00:00',
) as $local => $utc ) {
	check_agenda( $local === $normalize->invoke( null, str_replace( ' ', 'T', $local ) ), 'Accept valid datetime-local inputs.' );
	check_agenda( $local === $normalize->invoke( null, ' ' . $local . ' ' ), 'Trim valid local inputs.' );
	$actual = $timestamp->invoke( null, $local );
	$expected = ( new DateTimeImmutable( $utc, new DateTimeZone( 'UTC' ) ) )->getTimestamp();
	check_agenda( $expected === $actual, 'Convert local dates to exact UTC timestamps across winter, summer and DST transitions: ' . $local );
	check_agenda( 0 === $actual % 60, 'Seconds must be zero, not inherited from the current clock.' );
}
foreach ( array( '', 'not a date', '2026-02-30 12:00', '2026-02-29 12:00', '2026-13-01 12:00', '2026-01-01 24:01', '2026-03-29 02:30', '2026-01-01 12:00 extra' ) as $invalid ) {
	check_agenda( '' === $normalize->invoke( null, $invalid ), 'Reject invalid or nonexistent local dates: ' . $invalid );
	check_agenda( 0 === $timestamp->invoke( null, $invalid ), 'Do not silently normalize an invalid timestamp: ' . $invalid );
}
fwrite( STDOUT, "Event agenda datetime tests passed.\n" );
