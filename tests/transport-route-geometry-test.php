<?php
/** Route coverage regressions, using the real repository and in-memory GTFS rows. */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function wp_json_encode( $value ) { return json_encode( $value ); }

require ABSPATH . 'includes/features/transports/class-thecore-collectivity-transports-schedule-schema.php';
require ABSPATH . 'includes/features/transports/class-thecore-collectivity-transports-schedule-repository.php';

class Route_Geometry_Test_DB {
	public $prefix = 'test_';
	public $shapes = array();
	public $stop_times = array();
	public function prepare( $sql, $params ) { return array( $sql, $params ); }
	public function get_results( $query, $mode ) {
		list( $sql, $params ) = $query;
		$provider = array_shift( $params );
		if ( false !== strpos( $sql, 'bellevue_tr_shapes' ) ) {
			$rows = array_values( array_filter( $this->shapes, static function ( $row ) use ( $provider, $params ) {
				return $row['provider_key'] === $provider && in_array( (string) $row['shape_id'], array_map( 'strval', $params ), true );
			} ) );
			usort( $rows, static function ( $a, $b ) { return strcmp( $a['shape_id'], $b['shape_id'] ) ?: $a['shape_pt_sequence'] <=> $b['shape_pt_sequence']; } );
			return $rows;
		}
		if ( false !== strpos( $sql, 'bellevue_tr_stop_times' ) ) {
			preg_match( '/trip_id IN \(([^)]+)\)/', $sql, $matches );
			$trip_count = substr_count( $matches[1], '%s' );
			$trips = array_slice( $params, 0, $trip_count );
			$stops = array_slice( $params, $trip_count );
			return array_values( array_filter( $this->stop_times, static function ( $row ) use ( $provider, $trips, $stops ) {
				return $row['provider_key'] === $provider && in_array( $row['trip_id'], $trips, true ) && in_array( $row['stop_id'], $stops, true );
			} ) );
		}
		throw new RuntimeException( 'Unexpected query: ' . $sql );
	}
	public function add_shape( $id, $coordinates, $provider = 'operator-a' ) {
		foreach ( $coordinates as $i => $point ) {
			$this->shapes[] = array( 'provider_key' => $provider, 'shape_id' => $id, 'shape_pt_sequence' => $i, 'shape_pt_lon' => $point[0], 'shape_pt_lat' => $point[1] );
		}
	}
}

$wpdb = new Route_Geometry_Test_DB();
$repository = new TheCore_Collectivity_Transports_Schedule_Repository();
$builder = new ReflectionMethod( $repository, 'build_line_geojson' );
$builder->setAccessible( true );
function build_routes( $trips, $stops = array(), $provider = 'operator-a' ) {
	global $builder, $repository;
	return $builder->invoke( $repository, $provider, $trips, $stops );
}
function check_route( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function route_trip( $id, $shape, $direction, $headsign, $route = 'R' ) {
	return array( 'trip_id' => $id, 'shape_id' => $shape, 'direction_id' => $direction, 'trip_headsign' => $headsign, 'route_id' => $route, 'terminal_stop_id' => $headsign );
}

// Optional, untracked real-world archives can exercise exactly the same builder.
if ( isset( $argv[1], $argv[2] ) && '--fixture' === $argv[1] ) {
	$data = json_decode( file_get_contents( $argv[2] ), true, 512, JSON_THROW_ON_ERROR );
	$wpdb->shapes = $data['shapes'];
	$wpdb->stop_times = $data['stopTimes'];
	echo wp_json_encode( build_routes( $data['trips'], $data['stopIds'], $data['provider'] ) ), "\n";
	exit;
}

$wpdb->add_shape( 'short', array( array( 0, 0 ), array( 1, 0 ), array( 2, 0 ) ) );
$wpdb->add_shape( 'long', array( array( 0, 0 ), array( 1, 0 ), array( 2, 0 ), array( 3, 0 ) ) );
$wpdb->add_shape( 'branch', array( array( 0, 0 ), array( 1, 0 ), array( 2, 1 ) ) );
$wpdb->add_shape( 'return', array( array( 3, 0 ), array( 2, 0.1 ), array( 1, 0 ), array( 0, 0 ) ) );
$trips = array( route_trip( 's', 'short', '0', 'Near' ), route_trip( 'l', 'long', '0', 'Far' ), route_trip( 'b', 'branch', '0', 'Branch' ), route_trip( 'r', 'return', '1', 'Origin' ) );
$expected = build_routes( $trips );
check_route( 4 === count( $expected['features'] ), 'Preserve short services, extensions, branches and distinct return streets.' );
check_route( $expected === build_routes( array_merge( array_fill( 0, 40, $trips[0] ), array_reverse( $trips ) ) ), 'Route coverage and order must not depend on trip counts or SQL row order.' );
foreach ( $expected['features'] as $feature ) {
	$props = $feature['properties'];
	check_route( $props['headsign'] === $props['variants'][0]['headsign'], 'Geometry and label must come from the same trips.' );
}

$wpdb->add_shape( 'short-copy', array( array( 0, 0 ), array( 0, 0 ), array( 1, 0 ), array( 2, 0 ) ) );
$duplicate = build_routes( array( $trips[0], route_trip( 'c', 'short-copy', '', 'Other label', 'Other route' ) ) );
check_route( 1 === count( $duplicate['features'] ), 'Identical ordered coordinates are emitted once.' );
$props = $duplicate['features'][0]['properties'];
check_route( 2 === count( $props['shapeIds'] ) && 2 === count( $props['variants'] ), 'Dedupe must retain all source shapes, routes and labels.' );
check_route( '' === $props['headsign'] && '' === $props['directionId'], 'Do not assign misleading scalar metadata to multiple variants.' );

$wpdb->stop_times = array( array( 'provider_key' => 'operator-a', 'trip_id' => 'l', 'stop_id' => 'library' ), array( 'provider_key' => 'operator-b', 'trip_id' => 's', 'stop_id' => 'library' ) );
$scoped = build_routes( $trips, array( 'library' ) );
check_route( 1 === count( $scoped['features'] ) && 'long' === $scoped['features'][0]['properties']['shapeId'], 'Only include trips serving the selected stop in the selected provider.' );
check_route( null === build_routes( $trips, array( 'absent' ) ), 'Missing stop mapping must not silently display unrelated routes.' );
$wpdb->add_shape( 'short', array( array( 40, 40 ), array( 41, 41 ) ), 'operator-b' );
check_route( array( 0.0, 0.0 ) === build_routes( array( $trips[0] ) )['features'][0]['geometry']['coordinates'][0], 'Shape ids must be scoped by provider.' );

$wpdb->add_shape( 'loop', array( array( 0, 0 ), array( 1, 0 ), array( 1, 1 ), array( 0, 0 ) ) );
$loop = build_routes( array( route_trip( 'loop-trip', 'loop', '', '' ) ) );
check_route( 4 === count( $loop['features'][0]['geometry']['coordinates'] ), 'Loops remain complete even without direction or headsign.' );
$wpdb->add_shape( '0', array( array( 0, 0 ), array( 0, 1 ) ) );
$wpdb->stop_times[] = array( 'provider_key' => 'operator-a', 'trip_id' => '0', 'stop_id' => 'library' );
check_route( null !== build_routes( array( route_trip( '0', '0', '0', 'Zero' ) ), array( 'library' ) ), 'Zero is a valid GTFS identifier, not missing data.' );
$wpdb->add_shape( 'bad', array( array( 0, 0 ), array( null, 1 ), array( 2, 2 ) ) );
$wpdb->add_shape( 'range', array( array( 0, 0 ), array( 200, 100 ) ) );
$wpdb->add_shape( 'point', array( array( 0, 0 ), array( 0, 0 ) ) );
foreach ( array( 'absent', 'bad', 'range', 'point', '' ) as $shape ) {
	check_route( null === build_routes( array( route_trip( 'invalid', $shape, '', '' ) ) ), 'Incomplete/invalid geometry must be omitted without drawing a fabricated segment.' );
}
fwrite( STDOUT, "Transport route geometry PHP tests passed.\n" );
