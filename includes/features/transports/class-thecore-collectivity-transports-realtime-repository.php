<?php
/**
 * Transport realtime repository.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Transports_Realtime_Repository {
	/**
	 * Realtime status option.
	 */
	const OPTION_REALTIME_STATUS = 'bellevue_transport_realtime_status';

	/**
	 * Schedule repository.
	 *
	 * @var TheCore_Collectivity_Transports_Schedule_Repository
	 */
	private $schedule_repository;

	/**
	 * Constructor.
	 *
	 * @param TheCore_Collectivity_Transports_Schedule_Repository $schedule_repository Schedule repository.
	 */
	public function __construct( TheCore_Collectivity_Transports_Schedule_Repository $schedule_repository ) {
		$this->schedule_repository = $schedule_repository;
	}

	/**
	 * Get one realtime status payload.
	 *
	 * @param string $provider_key Provider key.
	 * @return array
	 */
	public function get_realtime_status( $provider_key = '' ) {
		$statuses = $this->get_realtime_statuses();
		$key      = $this->resolve_provider_key( $provider_key );

		return isset( $statuses[ $key ] ) && is_array( $statuses[ $key ] ) ? $statuses[ $key ] : array();
	}

	/**
	 * Get all realtime statuses.
	 *
	 * @return array
	 */
	public function get_realtime_statuses() {
		$raw_status = get_option( self::OPTION_REALTIME_STATUS, array() );
		if ( ! is_array( $raw_status ) || empty( $raw_status ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $raw_status as $provider_key => $status ) {
			if ( ! is_array( $status ) ) {
				continue;
			}

			$key                  = $this->resolve_provider_key( $provider_key );
			$status['providerKey'] = $key;
			$normalized[ $key ]   = $status;
		}

		return $normalized;
	}

	/**
	 * Persist one realtime status payload.
	 *
	 * @param array  $status       Status payload.
	 * @param string $provider_key Provider key.
	 * @return void
	 */
	public function update_realtime_status( array $status, $provider_key = '' ) {
		$statuses                  = $this->get_realtime_statuses();
		$provider_key              = $this->resolve_provider_key( $provider_key );
		$status['providerKey']     = $provider_key;
		$statuses[ $provider_key ] = $status;

		update_option( self::OPTION_REALTIME_STATUS, $statuses, false );
	}

	/**
	 * Replace realtime predictions for one provider.
	 *
	 * @param string $provider_key Provider key.
	 * @param array  $predictions  Prediction rows.
	 * @return int
	 */
	public function replace_predictions( $provider_key, array $predictions ) {
		global $wpdb;

		$provider_key = $this->resolve_provider_key( $provider_key );
		$table        = TheCore_Collectivity_Transports_Schedule_Schema::get_table_name( TheCore_Collectivity_Transports_Schedule_Schema::TABLE_REALTIME_PREDICTIONS );

		$wpdb->delete( $table, array( 'provider_key' => $provider_key ), array( '%s' ) );

		$inserted = 0;
		foreach ( $predictions as $prediction ) {
			if ( ! is_array( $prediction ) ) {
				continue;
			}

			$row = array(
				'provider_key'          => $provider_key,
				'entity_id'             => sanitize_text_field( (string) ( $prediction['entity_id'] ?? '' ) ),
				'trip_id'               => sanitize_text_field( (string) ( $prediction['trip_id'] ?? '' ) ),
				'route_id'              => sanitize_text_field( (string) ( $prediction['route_id'] ?? '' ) ),
				'stop_id'               => sanitize_text_field( (string) ( $prediction['stop_id'] ?? '' ) ),
				'stop_sequence'         => isset( $prediction['stop_sequence'] ) ? intval( $prediction['stop_sequence'] ) : 0,
				'service_date'          => sanitize_text_field( (string) ( $prediction['service_date'] ?? '' ) ),
				'headsign'              => sanitize_text_field( (string) ( $prediction['headsign'] ?? '' ) ),
				'schedule_relationship' => sanitize_key( (string) ( $prediction['schedule_relationship'] ?? 'scheduled' ) ),
				'arrival_timestamp'     => $this->nullable_int( $prediction['arrival_timestamp'] ?? null ),
				'departure_timestamp'   => $this->nullable_int( $prediction['departure_timestamp'] ?? null ),
				'arrival_delay'         => $this->nullable_int( $prediction['arrival_delay'] ?? null ),
				'departure_delay'       => $this->nullable_int( $prediction['departure_delay'] ?? null ),
				'trip_delay'            => $this->nullable_int( $prediction['trip_delay'] ?? null ),
				'feed_timestamp'        => $this->nullable_int( $prediction['feed_timestamp'] ?? null ),
				'fetched_at_gmt'        => sanitize_text_field( (string) ( $prediction['fetched_at_gmt'] ?? '' ) ),
				'expires_at_gmt'        => sanitize_text_field( (string) ( $prediction['expires_at_gmt'] ?? '' ) ),
			);

			$inserted += false !== $wpdb->insert(
				$table,
				$row,
				array(
					'%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s',
					'%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s',
				)
			) ? 1 : 0;
		}

		return $inserted;
	}

	/**
	 * Replace realtime service alerts for one provider.
	 *
	 * @param string $provider_key Provider key.
	 * @param array  $alerts       Alert rows.
	 * @return int
	 */
	public function replace_service_alerts( $provider_key, array $alerts ) {
		global $wpdb;

		$provider_key = $this->resolve_provider_key( $provider_key );
		$table        = TheCore_Collectivity_Transports_Schedule_Schema::get_table_name( TheCore_Collectivity_Transports_Schedule_Schema::TABLE_REALTIME_ALERTS );
		$normalized   = $this->normalize_alert_rows( $alerts );

		$wpdb->delete( $table, array( 'provider_key' => $provider_key ), array( '%s' ) );

		$inserted = 0;
		foreach ( $normalized as $alert ) {
			$row = array(
				'provider_key'     => $provider_key,
				'alert_id'         => sanitize_text_field( (string) ( $alert['alertKey'] ?? $alert['alert_id'] ?? '' ) ),
				'header_text'      => sanitize_textarea_field( (string) ( $alert['headerText'] ?? $alert['header_text'] ?? '' ) ),
				'description_text' => sanitize_textarea_field( (string) ( $alert['descriptionText'] ?? $alert['description_text'] ?? '' ) ),
				'url'              => esc_url_raw( (string) ( $alert['url'] ?? '' ) ),
				'cause'            => sanitize_key( (string) ( $alert['cause'] ?? '' ) ),
				'effect'           => sanitize_key( (string) ( $alert['effect'] ?? '' ) ),
				'severity'         => sanitize_key( (string) ( $alert['severity'] ?? $this->map_effect_to_severity( $alert['effect'] ?? '' ) ) ),
				'route_ids'        => wp_json_encode( $this->normalize_text_list( $alert['routeIds'] ?? $alert['route_ids'] ?? array() ) ),
				'stop_ids'         => wp_json_encode( $this->normalize_text_list( $alert['stopIds'] ?? $alert['stop_ids'] ?? array() ) ),
				'trip_ids'         => wp_json_encode( $this->normalize_text_list( $alert['tripIds'] ?? $alert['trip_ids'] ?? array() ) ),
				'agency_ids'       => wp_json_encode( $this->normalize_text_list( $alert['agencyIds'] ?? $alert['agency_ids'] ?? array() ) ),
				'route_types'      => wp_json_encode( $this->normalize_integer_list( $alert['routeTypes'] ?? $alert['route_types'] ?? array() ) ),
				'start_timestamp'  => $this->nullable_int( $alert['startTimestamp'] ?? $alert['start_timestamp'] ?? null ),
				'end_timestamp'    => $this->nullable_int( $alert['endTimestamp'] ?? $alert['end_timestamp'] ?? null ),
				'feed_timestamp'   => $this->nullable_int( $alert['feedTimestamp'] ?? $alert['feed_timestamp'] ?? null ),
				'fetched_at_gmt'   => sanitize_text_field( (string) ( $alert['fetchedAtGmt'] ?? $alert['fetched_at_gmt'] ?? '' ) ),
				'expires_at_gmt'   => sanitize_text_field( (string) ( $alert['expiresAtGmt'] ?? $alert['expires_at_gmt'] ?? '' ) ),
			);

			$inserted += false !== $wpdb->insert(
				$table,
				$row,
				array(
					'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s',
				)
			) ? 1 : 0;
		}

		return $inserted;
	}

	/**
	 * Backward-compatible alias for aggregated alert replacement.
	 *
	 * @param string $provider_key Provider key.
	 * @param array  $alerts       Alert rows.
	 * @return int
	 */
	public function replace_alerts( $provider_key, array $alerts ) {
		return $this->replace_service_alerts( $provider_key, $alerts );
	}

	/**
	 * Replace realtime vehicle positions for one provider.
	 *
	 * @param string $provider_key Provider key.
	 * @param array  $vehicles     Vehicle rows.
	 * @return int
	 */
	public function replace_vehicle_positions( $provider_key, array $vehicles ) {
		global $wpdb;

		$provider_key = $this->resolve_provider_key( $provider_key );
		$table        = TheCore_Collectivity_Transports_Schedule_Schema::get_table_name( TheCore_Collectivity_Transports_Schedule_Schema::TABLE_REALTIME_VEHICLES );

		$wpdb->delete( $table, array( 'provider_key' => $provider_key ), array( '%s' ) );

		$inserted = 0;
		foreach ( $vehicles as $vehicle ) {
			if ( ! is_array( $vehicle ) ) {
				continue;
			}

			$row = array(
				'provider_key'      => $provider_key,
				'entity_id'         => sanitize_text_field( (string) ( $vehicle['entity_id'] ?? '' ) ),
				'vehicle_id'        => sanitize_text_field( (string) ( $vehicle['vehicle_id'] ?? '' ) ),
				'vehicle_label'     => sanitize_text_field( (string) ( $vehicle['vehicle_label'] ?? '' ) ),
				'license_plate'     => sanitize_text_field( (string) ( $vehicle['license_plate'] ?? '' ) ),
				'trip_id'           => sanitize_text_field( (string) ( $vehicle['trip_id'] ?? '' ) ),
				'route_id'          => sanitize_text_field( (string) ( $vehicle['route_id'] ?? '' ) ),
				'stop_id'           => sanitize_text_field( (string) ( $vehicle['stop_id'] ?? '' ) ),
				'current_stop_sequence' => isset( $vehicle['current_stop_sequence'] ) ? intval( $vehicle['current_stop_sequence'] ) : 0,
				'current_status'    => sanitize_key( (string) ( $vehicle['current_status'] ?? '' ) ),
				'latitude'          => $this->nullable_float( $vehicle['latitude'] ?? null ),
				'longitude'         => $this->nullable_float( $vehicle['longitude'] ?? null ),
				'bearing'           => $this->nullable_float( $vehicle['bearing'] ?? null ),
				'speed'             => $this->nullable_float( $vehicle['speed'] ?? null ),
				'congestion_level'  => sanitize_key( (string) ( $vehicle['congestion_level'] ?? '' ) ),
				'occupancy_status'  => sanitize_key( (string) ( $vehicle['occupancy_status'] ?? '' ) ),
				'timestamp'         => $this->nullable_int( $vehicle['vehicle_timestamp'] ?? null ),
				'feed_timestamp'    => $this->nullable_int( $vehicle['feed_timestamp'] ?? null ),
				'fetched_at_gmt'    => sanitize_text_field( (string) ( $vehicle['fetched_at_gmt'] ?? '' ) ),
				'expires_at_gmt'    => sanitize_text_field( (string) ( $vehicle['expires_at_gmt'] ?? '' ) ),
			);

			$inserted += false !== $wpdb->insert(
				$table,
				$row,
				array(
					'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%d', '%d', '%s', '%s',
				)
			) ? 1 : 0;
		}

		return $inserted;
	}

	/**
	 * Backward-compatible alias for vehicle replacement.
	 *
	 * @param string $provider_key Provider key.
	 * @param array  $vehicles     Vehicle rows.
	 * @return int
	 */
	public function replace_vehicles( $provider_key, array $vehicles ) {
		return $this->replace_vehicle_positions( $provider_key, $vehicles );
	}

	/**
	 * Get realtime predictions indexed by trip / stop / sequence.
	 *
	 * @param string $provider_key Provider key.
	 * @param array  $trip_ids     GTFS trip ids.
	 * @param array  $stop_ids     GTFS stop ids.
	 * @param string $service_date GTFS service date Ymd.
	 * @return array
	 */
	public function get_prediction_index( $provider_key, array $trip_ids, array $stop_ids, $service_date = '' ) {
		global $wpdb;

		$provider_key = $this->resolve_provider_key( $provider_key );
		$trip_ids     = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $trip_ids ) ) ) );
		$stop_ids     = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $stop_ids ) ) ) );
		$service_date = preg_replace( '/[^0-9]/', '', (string) $service_date );

		if ( empty( $trip_ids ) || empty( $stop_ids ) ) {
			return array();
		}

		$table             = TheCore_Collectivity_Transports_Schedule_Schema::get_table_name( TheCore_Collectivity_Transports_Schedule_Schema::TABLE_REALTIME_PREDICTIONS );
		$trip_placeholders = implode( ', ', array_fill( 0, count( $trip_ids ), '%s' ) );
		$stop_placeholders = implode( ', ', array_fill( 0, count( $stop_ids ), '%s' ) );
		$params            = array_merge(
			array(
				$provider_key,
				current_time( 'mysql', true ),
			),
			$trip_ids,
			$stop_ids
		);
		$where_service     = '';

		if ( '' !== $service_date ) {
			$where_service = ' AND (service_date = %s OR service_date = \'\')';
			array_splice( $params, 2, 0, array( $service_date ) );
		}

		$sql = "SELECT trip_id, stop_id, stop_sequence, service_date, headsign, schedule_relationship, arrival_timestamp, departure_timestamp, arrival_delay, departure_delay, trip_delay, feed_timestamp, fetched_at_gmt, expires_at_gmt
			FROM {$table}
			WHERE provider_key = %s
				AND expires_at_gmt >= %s{$where_service}
				AND trip_id IN ({$trip_placeholders})
				AND stop_id IN ({$stop_placeholders})";

		$query = $wpdb->prepare( $sql, $params );
		$rows  = $wpdb->get_results( $query, ARRAY_A );
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return array();
		}

		$index = array();
		foreach ( $rows as $row ) {
			$trip_id       = ! empty( $row['trip_id'] ) ? (string) $row['trip_id'] : '';
			$stop_id       = ! empty( $row['stop_id'] ) ? (string) $row['stop_id'] : '';
			$stop_sequence = isset( $row['stop_sequence'] ) ? intval( $row['stop_sequence'] ) : 0;
			if ( '' === $trip_id || '' === $stop_id ) {
				continue;
			}

			$normalized = array(
				'trip_id'               => $trip_id,
				'stop_id'               => $stop_id,
				'stop_sequence'         => $stop_sequence,
				'service_date'          => ! empty( $row['service_date'] ) ? (string) $row['service_date'] : '',
				'headsign'              => ! empty( $row['headsign'] ) ? (string) $row['headsign'] : '',
				'schedule_relationship' => ! empty( $row['schedule_relationship'] ) ? (string) $row['schedule_relationship'] : 'scheduled',
				'arrival_timestamp'     => $this->nullable_int( $row['arrival_timestamp'] ?? null ),
				'departure_timestamp'   => $this->nullable_int( $row['departure_timestamp'] ?? null ),
				'arrival_delay'         => $this->nullable_int( $row['arrival_delay'] ?? null ),
				'departure_delay'       => $this->nullable_int( $row['departure_delay'] ?? null ),
				'trip_delay'            => $this->nullable_int( $row['trip_delay'] ?? null ),
				'feed_timestamp'        => $this->nullable_int( $row['feed_timestamp'] ?? null ),
				'fetched_at_gmt'        => ! empty( $row['fetched_at_gmt'] ) ? (string) $row['fetched_at_gmt'] : '',
				'expires_at_gmt'        => ! empty( $row['expires_at_gmt'] ) ? (string) $row['expires_at_gmt'] : '',
			);

			$index[ $this->build_prediction_key( $trip_id, $stop_id, $stop_sequence ) ] = $normalized;

			if ( 0 === $stop_sequence ) {
				$index[ $this->build_prediction_key( $trip_id, $stop_id, '' ) ] = $normalized;
			} else {
				$fallback_key = $this->build_prediction_key( $trip_id, $stop_id, '' );
				if ( empty( $index[ $fallback_key ] ) ) {
					$index[ $fallback_key ] = $normalized;
				}
			}
		}

		return $index;
	}

	/**
	 * Get active realtime alerts for one transport context.
	 *
	 * @param string   $provider_key Provider key.
	 * @param array    $route_ids    Route ids.
	 * @param array    $stop_ids     Stop ids.
	 * @param array    $trip_ids     Trip ids.
	 * @param int|null $route_type   Route type.
	 * @param string   $agency_id    Agency id.
	 * @return array
	 */
	public function get_alerts_for_context( $provider_key, array $route_ids = array(), array $stop_ids = array(), array $trip_ids = array(), $route_type = null, $agency_id = '' ) {
		global $wpdb;

		$provider_key = $this->resolve_provider_key( $provider_key );
		$table        = TheCore_Collectivity_Transports_Schedule_Schema::get_table_name( TheCore_Collectivity_Transports_Schedule_Schema::TABLE_REALTIME_ALERTS );
		$now_ts       = time();
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT alert_id, header_text, description_text, url, cause, effect, severity, route_ids, stop_ids, trip_ids, agency_ids, route_types, start_timestamp, end_timestamp, feed_timestamp, fetched_at_gmt, expires_at_gmt
				FROM {$table}
				WHERE provider_key = %s
					AND expires_at_gmt >= %s
					AND (start_timestamp IS NULL OR start_timestamp <= %d)
					AND (end_timestamp IS NULL OR end_timestamp = 0 OR end_timestamp >= %d)",
				$provider_key,
				current_time( 'mysql', true ),
				$now_ts,
				$now_ts
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return array();
		}

		$route_ids = $this->normalize_text_list( $route_ids );
		$stop_ids  = $this->normalize_text_list( $stop_ids );
		$trip_ids  = $this->normalize_text_list( $trip_ids );
		$agency_id = sanitize_text_field( (string) $agency_id );
		$route_type = null !== $route_type && '' !== (string) $route_type ? intval( $route_type ) : null;

		$alerts = array();
		foreach ( $rows as $row ) {
			$alert = array(
				'alertKey'        => ! empty( $row['alert_id'] ) ? (string) $row['alert_id'] : '',
				'headerText'      => ! empty( $row['header_text'] ) ? (string) $row['header_text'] : '',
				'descriptionText' => ! empty( $row['description_text'] ) ? (string) $row['description_text'] : '',
				'url'             => ! empty( $row['url'] ) ? (string) $row['url'] : '',
				'cause'           => ! empty( $row['cause'] ) ? (string) $row['cause'] : '',
				'effect'          => ! empty( $row['effect'] ) ? (string) $row['effect'] : '',
				'severity'        => ! empty( $row['severity'] ) ? (string) $row['severity'] : '',
				'routeIds'        => $this->decode_text_list( $row['route_ids'] ?? '' ),
				'stopIds'         => $this->decode_text_list( $row['stop_ids'] ?? '' ),
				'tripIds'         => $this->decode_text_list( $row['trip_ids'] ?? '' ),
				'agencyIds'       => $this->decode_text_list( $row['agency_ids'] ?? '' ),
				'routeTypes'      => $this->decode_integer_list( $row['route_types'] ?? '' ),
				'startTimestamp'  => $this->nullable_int( $row['start_timestamp'] ?? null ),
				'endTimestamp'    => $this->nullable_int( $row['end_timestamp'] ?? null ),
				'feedTimestamp'   => $this->nullable_int( $row['feed_timestamp'] ?? null ),
				'fetchedAtGmt'    => ! empty( $row['fetched_at_gmt'] ) ? (string) $row['fetched_at_gmt'] : '',
				'expiresAtGmt'    => ! empty( $row['expires_at_gmt'] ) ? (string) $row['expires_at_gmt'] : '',
				'isGlobal'        => false,
			);

			$alert['isGlobal'] = empty( $alert['routeIds'] ) && empty( $alert['stopIds'] ) && empty( $alert['tripIds'] ) && empty( $alert['agencyIds'] ) && empty( $alert['routeTypes'] );
			if ( ! $this->alert_matches_context( $alert, $route_ids, $stop_ids, $trip_ids, $agency_id, $route_type ) ) {
				continue;
			}

			$alerts[] = $alert;
		}

		usort(
			$alerts,
			static function ( $left, $right ) {
				$left_severity  = ( 'critical' === ( $left['severity'] ?? '' ) ) ? 30 : ( ( 'warning' === ( $left['severity'] ?? '' ) ) ? 20 : 10 );
				$right_severity = ( 'critical' === ( $right['severity'] ?? '' ) ) ? 30 : ( ( 'warning' === ( $right['severity'] ?? '' ) ) ? 20 : 10 );
				if ( $left_severity !== $right_severity ) {
					return $right_severity <=> $left_severity;
				}

				return intval( $right['startTimestamp'] ?? 0 ) <=> intval( $left['startTimestamp'] ?? 0 );
			}
		);

		return array_values( array_map( array( $this, 'decorate_alert' ), $alerts ) );
	}

	/**
	 * Get active vehicle positions for one transport context.
	 *
	 * @param string $provider_key Provider key.
	 * @param array  $route_ids    Route ids.
	 * @param array  $trip_ids     Trip ids.
	 * @return array
	 */
	public function get_vehicle_positions_for_context( $provider_key, array $route_ids = array(), array $trip_ids = array() ) {
		global $wpdb;

		$provider_key = $this->resolve_provider_key( $provider_key );
		$route_ids    = $this->normalize_text_list( $route_ids );
		$trip_ids     = $this->normalize_text_list( $trip_ids );
		$table        = TheCore_Collectivity_Transports_Schedule_Schema::get_table_name( TheCore_Collectivity_Transports_Schedule_Schema::TABLE_REALTIME_VEHICLES );
		$trips_table  = TheCore_Collectivity_Transports_Schedule_Schema::get_table_name( TheCore_Collectivity_Transports_Schedule_Schema::TABLE_TRIPS );
		$where        = array( 'v.provider_key = %s', 'v.expires_at_gmt >= %s' );
		$params       = array( $provider_key, current_time( 'mysql', true ) );

		if ( ! empty( $route_ids ) ) {
			$route_placeholders = implode( ', ', array_fill( 0, count( $route_ids ), '%s' ) );
			$where[]            = "(v.route_id IN ({$route_placeholders})";
			$params             = array_merge( $params, $route_ids );

			if ( ! empty( $trip_ids ) ) {
				$trip_placeholders = implode( ', ', array_fill( 0, count( $trip_ids ), '%s' ) );
				$where[ count( $where ) - 1 ] .= " OR v.trip_id IN ({$trip_placeholders})";
				$params = array_merge( $params, $trip_ids );
			}

			$where[ count( $where ) - 1 ] .= ')';
		} elseif ( ! empty( $trip_ids ) ) {
			$trip_placeholders = implode( ', ', array_fill( 0, count( $trip_ids ), '%s' ) );
			$where[]           = "v.trip_id IN ({$trip_placeholders})";
			$params            = array_merge( $params, $trip_ids );
		}

		$sql   = "SELECT v.entity_id, v.vehicle_id, v.vehicle_label, v.license_plate, v.trip_id, v.route_id, v.stop_id, v.current_stop_sequence, v.current_status, v.latitude, v.longitude, v.bearing, v.speed, v.congestion_level, v.occupancy_status, v.timestamp, v.feed_timestamp, v.fetched_at_gmt, v.expires_at_gmt,
				t.direction_id, t.trip_headsign, t.terminal_stop_id, t.terminal_stop_name, t.terminal_stop_locality
			FROM {$table} v
			LEFT JOIN {$trips_table} t
				ON t.provider_key = v.provider_key
				AND t.trip_id = v.trip_id
			WHERE " . implode( ' AND ', $where ) . "
			ORDER BY v.timestamp DESC";
		$query = $wpdb->prepare( $sql, $params );
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return array();
		}

		$vehicles = array();
		foreach ( $rows as $row ) {
			$vehicles[] = array(
				'entityId'          => ! empty( $row['entity_id'] ) ? (string) $row['entity_id'] : '',
				'vehicleId'         => ! empty( $row['vehicle_id'] ) ? (string) $row['vehicle_id'] : '',
				'vehicleLabel'      => ! empty( $row['vehicle_label'] ) ? (string) $row['vehicle_label'] : '',
				'licensePlate'      => ! empty( $row['license_plate'] ) ? (string) $row['license_plate'] : '',
				'tripId'            => ! empty( $row['trip_id'] ) ? (string) $row['trip_id'] : '',
				'routeId'           => ! empty( $row['route_id'] ) ? (string) $row['route_id'] : '',
				'directionId'       => ! empty( $row['direction_id'] ) ? (string) $row['direction_id'] : '',
				'headsign'          => ! empty( $row['trip_headsign'] ) ? (string) $row['trip_headsign'] : '',
				'terminalStopId'    => ! empty( $row['terminal_stop_id'] ) ? (string) $row['terminal_stop_id'] : '',
				'terminalStopName'  => ! empty( $row['terminal_stop_name'] ) ? (string) $row['terminal_stop_name'] : '',
				'terminalLocality'  => ! empty( $row['terminal_stop_locality'] ) ? (string) $row['terminal_stop_locality'] : '',
				'stopId'            => ! empty( $row['stop_id'] ) ? (string) $row['stop_id'] : '',
				'currentStopSequence' => isset( $row['current_stop_sequence'] ) ? intval( $row['current_stop_sequence'] ) : 0,
				'currentStatus'     => ! empty( $row['current_status'] ) ? (string) $row['current_status'] : '',
				'latitude'          => $this->nullable_float( $row['latitude'] ?? null ),
				'longitude'         => $this->nullable_float( $row['longitude'] ?? null ),
				'bearing'           => $this->nullable_float( $row['bearing'] ?? null ),
				'speed'             => $this->nullable_float( $row['speed'] ?? null ),
				'congestionLevel'   => ! empty( $row['congestion_level'] ) ? (string) $row['congestion_level'] : '',
				'occupancyStatus'   => ! empty( $row['occupancy_status'] ) ? (string) $row['occupancy_status'] : '',
				'timestamp'         => $this->nullable_int( $row['timestamp'] ?? null ),
				'feedTimestamp'     => $this->nullable_int( $row['feed_timestamp'] ?? null ),
				'fetchedAtGmt'      => ! empty( $row['fetched_at_gmt'] ) ? (string) $row['fetched_at_gmt'] : '',
				'expiresAtGmt'      => ! empty( $row['expires_at_gmt'] ) ? (string) $row['expires_at_gmt'] : '',
			);
		}

		return $vehicles;
	}

	/**
	 * Build a prediction lookup key.
	 *
	 * @param string     $trip_id       Trip id.
	 * @param string     $stop_id       Stop id.
	 * @param int|string $stop_sequence Stop sequence.
	 * @return string
	 */
	public function build_prediction_key( $trip_id, $stop_id, $stop_sequence = '' ) {
		return sanitize_text_field( (string) $trip_id ) . '|' . sanitize_text_field( (string) $stop_id ) . '|' . ( '' === (string) $stop_sequence ? '' : intval( $stop_sequence ) );
	}

	/**
	 * Aggregate raw alert rows by alert key.
	 *
	 * @param array $rows Raw alert rows.
	 * @return array
	 */
	private function aggregate_alert_rows( array $rows ) {
		$alerts = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$key = sanitize_text_field( (string) ( $row['alert_key'] ?? $row['entity_id'] ?? '' ) );
			if ( '' === $key ) {
				continue;
			}

			if ( empty( $alerts[ $key ] ) ) {
				$alerts[ $key ] = array(
					'alertKey'        => $key,
					'headerText'      => sanitize_textarea_field( (string) ( $row['header_text'] ?? '' ) ),
					'descriptionText' => sanitize_textarea_field( (string) ( $row['description_text'] ?? '' ) ),
					'url'             => esc_url_raw( (string) ( $row['url'] ?? '' ) ),
					'cause'           => sanitize_key( (string) ( $row['cause'] ?? '' ) ),
					'effect'          => sanitize_key( (string) ( $row['effect'] ?? '' ) ),
					'severity'        => $this->map_effect_to_severity( (string) ( $row['effect'] ?? '' ) ),
					'routeIds'        => array(),
					'stopIds'         => array(),
					'tripIds'         => array(),
					'agencyIds'       => array(),
					'routeTypes'      => array(),
					'startTimestamp'  => $this->nullable_int( $row['active_start_timestamp'] ?? null ),
					'endTimestamp'    => $this->nullable_int( $row['active_end_timestamp'] ?? null ),
					'feedTimestamp'   => $this->nullable_int( $row['feed_timestamp'] ?? null ),
					'fetchedAtGmt'    => sanitize_text_field( (string) ( $row['fetched_at_gmt'] ?? '' ) ),
					'expiresAtGmt'    => sanitize_text_field( (string) ( $row['expires_at_gmt'] ?? '' ) ),
				);
			}

			if ( ! empty( $row['route_id'] ) ) {
				$alerts[ $key ]['routeIds'][] = sanitize_text_field( (string) $row['route_id'] );
			}
			if ( ! empty( $row['stop_id'] ) ) {
				$alerts[ $key ]['stopIds'][] = sanitize_text_field( (string) $row['stop_id'] );
			}
			if ( ! empty( $row['trip_id'] ) ) {
				$alerts[ $key ]['tripIds'][] = sanitize_text_field( (string) $row['trip_id'] );
			}
			if ( ! empty( $row['agency_id'] ) ) {
				$alerts[ $key ]['agencyIds'][] = sanitize_text_field( (string) $row['agency_id'] );
			}
			if ( isset( $row['route_type'] ) && '' !== (string) $row['route_type'] && null !== $row['route_type'] ) {
				$alerts[ $key ]['routeTypes'][] = intval( $row['route_type'] );
			}

			$start_ts = $this->nullable_int( $row['active_start_timestamp'] ?? null );
			$end_ts   = $this->nullable_int( $row['active_end_timestamp'] ?? null );
			if ( null !== $start_ts && ( null === $alerts[ $key ]['startTimestamp'] || $start_ts < $alerts[ $key ]['startTimestamp'] ) ) {
				$alerts[ $key ]['startTimestamp'] = $start_ts;
			}
			if ( null !== $end_ts && ( null === $alerts[ $key ]['endTimestamp'] || $end_ts > $alerts[ $key ]['endTimestamp'] ) ) {
				$alerts[ $key ]['endTimestamp'] = $end_ts;
			}
		}

		foreach ( $alerts as $key => $alert ) {
			$alerts[ $key ]['routeIds']   = $this->normalize_text_list( $alert['routeIds'] );
			$alerts[ $key ]['stopIds']    = $this->normalize_text_list( $alert['stopIds'] );
			$alerts[ $key ]['tripIds']    = $this->normalize_text_list( $alert['tripIds'] );
			$alerts[ $key ]['agencyIds']  = $this->normalize_text_list( $alert['agencyIds'] );
			$alerts[ $key ]['routeTypes'] = $this->normalize_integer_list( $alert['routeTypes'] );
		}

		return array_values( $alerts );
	}

	/**
	 * Normalize alert rows coming either from legacy row-level adapters or aggregated adapters.
	 *
	 * @param array $alerts Raw alerts.
	 * @return array
	 */
	private function normalize_alert_rows( array $alerts ) {
		if ( empty( $alerts ) ) {
			return array();
		}

		$first = reset( $alerts );
		if ( is_array( $first ) && ( array_key_exists( 'route_id', $first ) || array_key_exists( 'stop_id', $first ) || array_key_exists( 'active_start_timestamp', $first ) ) ) {
			return $this->aggregate_alert_rows( $alerts );
		}

		return array_values(
			array_filter(
				$alerts,
				static function ( $alert ) {
					return is_array( $alert ) && '' !== (string) ( $alert['alertKey'] ?? $alert['alert_id'] ?? '' );
				}
			)
		);
	}

	/**
	 * Determine whether one alert matches current transport context.
	 *
	 * @param array    $alert      Alert payload.
	 * @param array    $route_ids  Route ids.
	 * @param array    $stop_ids   Stop ids.
	 * @param array    $trip_ids   Trip ids.
	 * @param string   $agency_id  Agency id.
	 * @param int|null $route_type Route type.
	 * @return bool
	 */
	private function alert_matches_context( array $alert, array $route_ids, array $stop_ids, array $trip_ids, $agency_id, $route_type ) {
		if ( ! empty( $alert['isGlobal'] ) ) {
			return false;
		}

		if ( ! empty( $route_ids ) && array_intersect( $route_ids, $alert['routeIds'] ) ) {
			return true;
		}
		if ( ! empty( $stop_ids ) && array_intersect( $stop_ids, $alert['stopIds'] ) ) {
			return true;
		}
		if ( ! empty( $trip_ids ) && array_intersect( $trip_ids, $alert['tripIds'] ) ) {
			return true;
		}
		if ( '' !== $agency_id && ! empty( $alert['agencyIds'] ) && in_array( $agency_id, $alert['agencyIds'], true ) ) {
			return true;
		}
		if ( null !== $route_type && ! empty( $alert['routeTypes'] ) && in_array( intval( $route_type ), $alert['routeTypes'], true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Decorate one alert for front payloads.
	 *
	 * @param array $alert Alert payload.
	 * @return array
	 */
	private function decorate_alert( array $alert ) {
		$effect_labels = array(
			'no_service'         => __( 'Interruption', 'bellevue' ),
			'reduced_service'    => __( 'Service réduit', 'bellevue' ),
			'significant_delays' => __( 'Retards importants', 'bellevue' ),
			'detour'             => __( 'Déviation', 'bellevue' ),
			'additional_service' => __( 'Service additionnel', 'bellevue' ),
			'modified_service'   => __( 'Service modifié', 'bellevue' ),
			'stop_moved'         => __( 'Arrêt déplacé', 'bellevue' ),
			'other_effect'       => __( 'Perturbation', 'bellevue' ),
			'unknown_effect'     => __( 'Perturbation', 'bellevue' ),
		);
		$cause_labels  = array(
			'technical_problem' => __( 'Problème technique', 'bellevue' ),
			'strike'            => __( 'Grève', 'bellevue' ),
			'demonstration'     => __( 'Manifestation', 'bellevue' ),
			'accident'          => __( 'Accident', 'bellevue' ),
			'holiday'           => __( 'Jour férié', 'bellevue' ),
			'weather'           => __( 'Météo', 'bellevue' ),
			'maintenance'       => __( 'Maintenance', 'bellevue' ),
			'construction'      => __( 'Travaux', 'bellevue' ),
			'police_activity'   => __( 'Opération de police', 'bellevue' ),
			'medical_emergency' => __( 'Urgence médicale', 'bellevue' ),
			'other_cause'       => __( 'Autre cause', 'bellevue' ),
			'unknown_cause'     => __( 'Cause non précisée', 'bellevue' ),
		);

		$alert['effectLabel']   = isset( $effect_labels[ $alert['effect'] ] ) ? $effect_labels[ $alert['effect'] ] : __( 'Perturbation', 'bellevue' );
		$alert['causeLabel']    = isset( $cause_labels[ $alert['cause'] ] ) ? $cause_labels[ $alert['cause'] ] : '';
		$alert['severityScore'] = $this->get_alert_severity_score( $alert['severity'] ?? '' );
		$alert['startLabel']    = ! empty( $alert['startTimestamp'] ) ? wp_date( 'Y-m-d H:i', intval( $alert['startTimestamp'] ) ) : '';
		$alert['endLabel']      = ! empty( $alert['endTimestamp'] ) ? wp_date( 'Y-m-d H:i', intval( $alert['endTimestamp'] ) ) : '';

		return $alert;
	}

	/**
	 * Resolve severity from effect.
	 *
	 * @param string $effect Alert effect.
	 * @return string
	 */
	private function map_effect_to_severity( $effect ) {
		$critical = array( 'no_service', 'significant_delays', 'stop_moved' );
		$warning  = array( 'reduced_service', 'detour', 'modified_service' );
		$effect   = sanitize_key( (string) $effect );

		if ( in_array( $effect, $critical, true ) ) {
			return 'critical';
		}
		if ( in_array( $effect, $warning, true ) ) {
			return 'warning';
		}

		return 'info';
	}

	/**
	 * Convert severity key into score.
	 *
	 * @param string $severity Severity key.
	 * @return int
	 */
	private function get_alert_severity_score( $severity ) {
		switch ( sanitize_key( (string) $severity ) ) {
			case 'critical':
				return 30;
			case 'warning':
				return 20;
			default:
				return 10;
		}
	}

	/**
	 * Resolve provider key.
	 *
	 * @param string $provider_key Provider key.
	 * @return string
	 */
	private function resolve_provider_key( $provider_key ) {
		$key = sanitize_key( (string) $provider_key );
		return '' !== $key ? $key : $this->schedule_repository->get_default_provider_key();
	}

	/**
	 * Normalize nullable integers.
	 *
	 * @param mixed $value Raw value.
	 * @return int|null
	 */
	private function nullable_int( $value ) {
		if ( null === $value || '' === (string) $value ) {
			return null;
		}

		return intval( $value );
	}

	/**
	 * Normalize nullable float values.
	 *
	 * @param mixed $value Raw value.
	 * @return float|null
	 */
	private function nullable_float( $value ) {
		if ( null === $value || '' === (string) $value ) {
			return null;
		}

		if ( ! is_numeric( $value ) ) {
			return null;
		}

		return floatval( $value );
	}

	/**
	 * Normalize one text list.
	 *
	 * @param array $values Raw values.
	 * @return array
	 */
	private function normalize_text_list( $values ) {
		$values = is_array( $values ) ? $values : array();
		$values = array_map(
			static function ( $value ) {
				return sanitize_text_field( (string) $value );
			},
			$values
		);

		return array_values( array_unique( array_filter( $values ) ) );
	}

	/**
	 * Normalize one integer list.
	 *
	 * @param array $values Raw values.
	 * @return array
	 */
	private function normalize_integer_list( $values ) {
		$values = is_array( $values ) ? $values : array();
		$values = array_map( 'intval', $values );
		$values = array_values( array_unique( array_filter( $values, static function ( $value ) {
			return null !== $value && '' !== (string) $value;
		} ) ) );

		return $values;
	}

	/**
	 * Decode a stored text list.
	 *
	 * @param string $raw Raw JSON value.
	 * @return array
	 */
	private function decode_text_list( $raw ) {
		$decoded = json_decode( (string) $raw, true );
		return $this->normalize_text_list( is_array( $decoded ) ? $decoded : array() );
	}

	/**
	 * Decode a stored integer list.
	 *
	 * @param string $raw Raw JSON value.
	 * @return array
	 */
	private function decode_integer_list( $raw ) {
		$decoded = json_decode( (string) $raw, true );
		return $this->normalize_integer_list( is_array( $decoded ) ? $decoded : array() );
	}
}
