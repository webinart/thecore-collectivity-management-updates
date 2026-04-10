<?php
/**
 * Transport schedule repository.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Transports_Schedule_Repository {
	/**
	 * Import status option.
	 */
	const OPTION_IMPORT_STATUS = 'bellevue_transport_schedule_import_status';

	/**
	 * Discovery status option.
	 */
	const OPTION_DISCOVERY_STATUS = 'bellevue_transport_schedule_discovery_status';

	/**
	 * Legacy single GTFS source option.
	 */
	const OPTION_GTFS_SOURCE_CONFIG = 'bellevue_transport_gtfs_source_config';

	/**
	 * Multi-source GTFS option.
	 */
	const OPTION_GTFS_SOURCES = 'bellevue_transport_gtfs_sources';

	/**
	 * Default GTFS provider label.
	 */
	const DEFAULT_GTFS_PROVIDER_LABEL = 'IDFM';

	/**
	 * Default GTFS provider key.
	 */
	const DEFAULT_GTFS_PROVIDER_KEY = 'idfm';

	/**
	 * Default GTFS ZIP URL.
	 */
	const DEFAULT_GTFS_URL = 'https://eu.ftp.opendatasoft.com/stif/GTFS/IDFM-gtfs.zip';

	/**
	 * Stop direction strategy: compute destination from GTFS terminal stop.
	 */
	const STOP_DIRECTION_STRATEGY_GTFS = 'gtfs_terminal';

	/**
	 * Stop direction strategy: parse destination from trip headsign.
	 */
	const STOP_DIRECTION_STRATEGY_PARSE = 'parse_headsign';

	/**
	 * In-request cache for stop destination labels.
	 *
	 * @var array
	 */
	private $stop_destination_cache = array();

	/**
	 * Get last import status.
	 *
	 * @return array
	 */
	public function get_import_status( $provider_key = '' ) {
		$statuses = $this->get_import_statuses();
		$key      = $this->resolve_provider_key( $provider_key );

		return isset( $statuses[ $key ] ) && is_array( $statuses[ $key ] ) ? $statuses[ $key ] : array();
	}

	/**
	 * Get all import statuses, keyed by provider key.
	 *
	 * @return array
	 */
	public function get_import_statuses() {
		return $this->normalize_status_map( get_option( self::OPTION_IMPORT_STATUS, array() ) );
	}

	/**
	 * Persist import status for one provider.
	 *
	 * @param array  $status       Status payload.
	 * @param string $provider_key Provider key.
	 */
	public function update_import_status( array $status, $provider_key = '' ) {
		$statuses                 = $this->get_import_statuses();
		$provider_key             = $this->resolve_provider_key( $provider_key );
		$statuses[ $provider_key ] = $this->sanitize_status_payload( $status, $provider_key );

		update_option( self::OPTION_IMPORT_STATUS, $statuses, false );
	}

	/**
	 * Get last locality discovery status.
	 *
	 * @return array
	 */
	public function get_discovery_status( $provider_key = '' ) {
		$statuses = $this->get_discovery_statuses();
		$key      = $this->resolve_provider_key( $provider_key );

		return isset( $statuses[ $key ] ) && is_array( $statuses[ $key ] ) ? $statuses[ $key ] : array();
	}

	/**
	 * Get all discovery statuses, keyed by provider key.
	 *
	 * @return array
	 */
	public function get_discovery_statuses() {
		return $this->normalize_status_map( get_option( self::OPTION_DISCOVERY_STATUS, array() ) );
	}

	/**
	 * Persist locality discovery status for one provider.
	 *
	 * @param array  $status       Status payload.
	 * @param string $provider_key Provider key.
	 */
	public function update_discovery_status( array $status, $provider_key = '' ) {
		$statuses                 = $this->get_discovery_statuses();
		$provider_key             = $this->resolve_provider_key( $provider_key );
		$statuses[ $provider_key ] = $this->sanitize_status_payload( $status, $provider_key );

		update_option( self::OPTION_DISCOVERY_STATUS, $statuses, false );
	}

	/**
	 * Get configured GTFS sources.
	 *
	 * @return array
	 */
	public function get_gtfs_sources() {
		$sources = get_option( self::OPTION_GTFS_SOURCES, array() );
		$sources = is_array( $sources ) ? $sources : array();

		$normalized = array();
		if ( ! empty( $sources ) ) {
			foreach ( $sources as $index => $source ) {
				if ( ! is_array( $source ) ) {
					continue;
				}

				$normalized[] = $this->normalize_source_config( $source, 'source-' . ( $index + 1 ) );
			}
		}

		if ( empty( $normalized ) ) {
			$legacy = get_option( self::OPTION_GTFS_SOURCE_CONFIG, array() );
			if ( is_array( $legacy ) && ! empty( $legacy ) ) {
				$normalized[] = $this->normalize_source_config( $legacy, self::DEFAULT_GTFS_PROVIDER_KEY );
			}
		}

		if ( empty( $normalized ) ) {
			$normalized[] = $this->normalize_source_config( array(), self::DEFAULT_GTFS_PROVIDER_KEY );
		}

		return $normalized;
	}

	/**
	 * Persist GTFS sources.
	 *
	 * @param array $sources Raw sources.
	 * @return array
	 */
	public function update_gtfs_sources( array $sources ) {
		$sanitized   = array();
		$seen_keys   = array();

		foreach ( $sources as $index => $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}

			if ( ! $this->source_has_content( $source ) ) {
				continue;
			}

			$normalized = $this->normalize_source_config( $source, 'source-' . ( $index + 1 ) );
			if ( '' === $normalized['provider_key'] ) {
				continue;
			}

			$base_key = $normalized['provider_key'];
			$suffix   = 2;
			while ( isset( $seen_keys[ $normalized['provider_key'] ] ) ) {
				$normalized['provider_key'] = sanitize_key( $base_key . '-' . $suffix );
				++$suffix;
			}

			$seen_keys[ $normalized['provider_key'] ] = true;
			$sanitized[]                              = $normalized;
		}

		if ( empty( $sanitized ) ) {
			$sanitized[] = $this->normalize_source_config( array(), self::DEFAULT_GTFS_PROVIDER_KEY );
		}

		update_option( self::OPTION_GTFS_SOURCES, $sanitized, false );
		$this->sync_legacy_gtfs_source_option( $sanitized );

		return $sanitized;
	}

	/**
	 * Get configured GTFS source metadata.
	 *
	 * @param string $provider_key Optional provider key.
	 * @return array
	 */
	public function get_gtfs_source_config( $provider_key = '' ) {
		$sources = $this->get_gtfs_sources();
		$key     = sanitize_key( (string) $provider_key );

		if ( '' !== $key ) {
			foreach ( $sources as $source ) {
				if ( $key === $source['provider_key'] ) {
					return $source;
				}
			}
		}

		foreach ( $sources as $source ) {
			if ( ! empty( $source['is_enabled'] ) ) {
				return $source;
			}
		}

		return reset( $sources ) ?: $this->normalize_source_config( array(), self::DEFAULT_GTFS_PROVIDER_KEY );
	}

	/**
	 * Persist one GTFS source metadata payload for backward compatibility.
	 *
	 * @param array $config Raw config.
	 * @return array
	 */
	public function update_gtfs_source_config( array $config ) {
		$sources      = $this->get_gtfs_sources();
		$provider_key = sanitize_key( (string) ( $config['provider_key'] ?? '' ) );
		$updated      = false;

		foreach ( $sources as $index => $source ) {
			if ( '' !== $provider_key && $provider_key === $source['provider_key'] ) {
				$sources[ $index ] = $this->normalize_source_config( array_merge( $source, $config ), $provider_key );
				$updated           = true;
				break;
			}
		}

		if ( ! $updated ) {
			$current      = $this->get_gtfs_source_config();
			$merged       = $this->normalize_source_config( array_merge( $current, $config ), $provider_key ?: $current['provider_key'] );
			$sources[0]   = $merged;
			$provider_key = $merged['provider_key'];
		}

		$sources = $this->update_gtfs_sources( $sources );

		return $this->get_gtfs_source_config( $provider_key );
	}

	/**
	 * Get mapping overview used by import and admin.
	 *
	 * @return array
	 */
	public function get_mapping_overview( $provider_key = '' ) {
		$source_config       = $this->get_gtfs_source_config( $provider_key );
		$active_provider_key = ! empty( $source_config['provider_key'] ) ? (string) $source_config['provider_key'] : self::DEFAULT_GTFS_PROVIDER_KEY;
		$lines  = get_posts(
			array(
				'post_type'      => TheCore_Collectivity_Transports_Post_Types::POST_TYPE_LINE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$places = get_posts(
			array(
				'post_type'      => TheCore_Collectivity_Transports_Post_Types::POST_TYPE_PLACE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$route_ids    = array();
		$short_names  = array();
		$stop_ids     = array();
		$mapped_lines = array();
		$mapped_stops = array();

		foreach ( $lines as $line ) {
			$source = (string) get_post_meta( $line->ID, TheCore_Collectivity_Transports_Meta::META_DATA_SOURCE, true );
			if ( ! TheCore_Collectivity_Transports_Meta::is_gtfs_source( $source ) ) {
				continue;
			}

			if ( ! $this->is_gtfs_provider_match( $line->ID, $active_provider_key ) ) {
				continue;
			}

			$line_route_ids   = $this->parse_meta_list( get_post_meta( $line->ID, TheCore_Collectivity_Transports_Meta::META_GTFS_ROUTE_IDS, true ) );
			$line_short_names = $this->parse_meta_list( get_post_meta( $line->ID, TheCore_Collectivity_Transports_Meta::META_GTFS_SHORT_NAMES, true ) );
			$line_stop_ids    = $this->get_line_stop_ids( $line->ID, $active_provider_key );

			$route_ids        = array_merge( $route_ids, $line_route_ids );
			$short_names      = array_merge( $short_names, $line_short_names );
			$stop_ids         = array_merge( $stop_ids, $line_stop_ids );

			$mapped_lines[] = array(
				'id'         => (int) $line->ID,
				'title'      => get_the_title( $line ),
				'source'     => $source,
				'routeIds'   => $line_route_ids,
				'shortNames' => $line_short_names,
				'stopIds'    => $line_stop_ids,
			);
		}

		foreach ( $places as $place ) {
			if ( ! $this->is_gtfs_provider_match( $place->ID, $active_provider_key ) ) {
				continue;
			}

			$place_stop_ids = $this->parse_meta_list( get_post_meta( $place->ID, TheCore_Collectivity_Transports_Meta::META_GTFS_STOP_IDS, true ) );
			if ( empty( $place_stop_ids ) ) {
				continue;
			}

			$stop_ids = array_merge( $stop_ids, $place_stop_ids );
			$mapped_stops[] = array(
				'id'      => (int) $place->ID,
				'title'   => get_the_title( $place ),
				'stopIds' => $place_stop_ids,
			);
		}

		$route_ids   = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $route_ids ) ) ) );
		$short_names = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $short_names ) ) ) );
		$stop_ids    = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $stop_ids ) ) ) );

		return array(
			'routeIds'    => $route_ids,
			'shortNames'  => $short_names,
			'stopIds'     => $stop_ids,
			'lines'       => $mapped_lines,
			'places'      => $mapped_stops,
			'lineCount'   => count( $mapped_lines ),
			'stopCount'   => count( $stop_ids ),
			'routeCount'  => count( $route_ids ),
			'shortCount'  => count( $short_names ),
			'providerKey' => $active_provider_key,
			'providerLabel' => ! empty( $source_config['provider_label'] ) ? (string) $source_config['provider_label'] : '',
		);
	}

	/**
	 * Get configured reference stops for a line.
	 *
	 * @param int $line_id Line post id.
	 * @return array
	 */
	public function get_line_reference_stops( $line_id ) {
		$line_id = (int) $line_id;
		if ( $line_id <= 0 ) {
			return array();
		}

		$value   = get_post_meta( $line_id, TheCore_Collectivity_Transports_Meta::META_REFERENCE_STOPS, true );
		$decoded = json_decode( (string) $value, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$reference_stops = array();
		foreach ( $decoded as $direction_key => $stop_id ) {
			$direction_key = trim( (string) $direction_key );
			$stop_id       = sanitize_text_field( (string) $stop_id );
			if ( '' === $direction_key || '' === $stop_id ) {
				continue;
			}

			$reference_stops[ $direction_key ] = $stop_id;
		}

		return $reference_stops;
	}

	/**
	 * Get reference stop choices for a line, grouped by direction.
	 *
	 * @param int $line_id Line post id.
	 * @return array
	 */
	public function get_line_reference_stop_options( $line_id ) {
		$schedule = $this->get_line_schedule( $line_id );
		if ( empty( $schedule['stops'] ) || ! is_array( $schedule['stops'] ) ) {
			return array(
				'directions' => array(),
			);
		}

		$stop_name_counts = array();
		foreach ( $schedule['stops'] as $stop_payload ) {
			$stop_name = ! empty( $stop_payload['stopName'] ) ? (string) $stop_payload['stopName'] : '';
			if ( '' === $stop_name ) {
				continue;
			}

			if ( empty( $stop_name_counts[ $stop_name ] ) ) {
				$stop_name_counts[ $stop_name ] = 0;
			}

			++$stop_name_counts[ $stop_name ];
		}

		$directions = array();
		foreach ( $schedule['stops'] as $stop_payload ) {
			$stop_id   = ! empty( $stop_payload['stopId'] ) ? (string) $stop_payload['stopId'] : '';
			$stop_name = ! empty( $stop_payload['stopName'] ) ? (string) $stop_payload['stopName'] : $stop_id;
			if ( '' === $stop_id ) {
				continue;
			}

			foreach ( $stop_payload['directions'] as $direction_payload ) {
				$direction_key = $this->get_direction_key( $direction_payload );
				if ( '' === $direction_key ) {
					continue;
				}

				if ( empty( $directions[ $direction_key ] ) ) {
					$directions[ $direction_key ] = array(
						'key'         => $direction_key,
						'label'       => $this->get_direction_label( $direction_payload ),
						'directionId' => isset( $direction_payload['directionId'] ) ? (string) $direction_payload['directionId'] : '',
						'stopOptions' => array(),
					);
				}

				if ( isset( $directions[ $direction_key ]['stopOptions'][ $stop_id ] ) ) {
					continue;
				}

				$directions[ $direction_key ]['stopOptions'][ $stop_id ] = array(
					'stopId'   => $stop_id,
					'stopName' => $stop_name,
					'label'    => $this->get_stop_option_label( $stop_id, $stop_name, ! empty( $stop_name_counts[ $stop_name ] ) && $stop_name_counts[ $stop_name ] > 1 ),
				);
			}
		}

		foreach ( $directions as $direction_key => $direction_payload ) {
			$stop_options = array_values( $direction_payload['stopOptions'] );
			usort(
				$stop_options,
				static function ( $left, $right ) {
					return strcasecmp( $left['label'], $right['label'] );
				}
			);
			$directions[ $direction_key ]['stopOptions'] = $stop_options;
		}

		$directions = array_values( $directions );
		usort(
			$directions,
			static function ( $left, $right ) {
				return strcasecmp( $left['label'], $right['label'] );
			}
		);

		return array(
			'directions' => $directions,
		);
	}

	/**
	 * Get schedule payload for a line.
	 *
	 * @param int $line_id Line post id.
	 * @return array
	 */
	public function get_line_schedule( $line_id ) {
		$line_id = (int) $line_id;
		if ( $line_id <= 0 ) {
			return $this->get_empty_schedule();
		}

		$source = (string) get_post_meta( $line_id, TheCore_Collectivity_Transports_Meta::META_DATA_SOURCE, true );
		if ( ! TheCore_Collectivity_Transports_Meta::is_gtfs_source( $source ) ) {
			return $this->get_empty_schedule( $source ?: TheCore_Collectivity_Transports_Meta::DATA_SOURCE_MANUAL );
		}

		$route_ids    = $this->parse_meta_list( get_post_meta( $line_id, TheCore_Collectivity_Transports_Meta::META_GTFS_ROUTE_IDS, true ) );
		$short_names  = $this->parse_meta_list( get_post_meta( $line_id, TheCore_Collectivity_Transports_Meta::META_GTFS_SHORT_NAMES, true ) );
		$provider_key = sanitize_key( (string) get_post_meta( $line_id, TheCore_Collectivity_Transports_Meta::META_GTFS_PROVIDER_KEY, true ) );
		if ( '' === $provider_key ) {
			$provider_key = $this->get_default_provider_key();
		}
		$source_config  = $this->get_gtfs_source_config( $provider_key );
		$primary_stops = $this->get_line_stop_ids( $line_id, $provider_key );

		if ( empty( $primary_stops ) ) {
			return $this->get_empty_schedule( $source );
		}

			$route_rows = $this->get_routes_for_mapping( $provider_key, $route_ids, $short_names );
			if ( empty( $route_rows ) ) {
				return $this->get_empty_schedule( $source );
			}
			$route_meta = $this->build_route_meta( $route_rows, $short_names );

		$route_ids     = array_values( array_unique( wp_list_pluck( $route_rows, 'route_id' ) ) );
		$trip_rows     = $this->get_trips_for_routes( $provider_key, $route_ids );
		$route_geojson = $this->build_line_geojson( $provider_key, $trip_rows, $primary_stops );
		if ( empty( $trip_rows ) ) {
			return $this->get_empty_schedule( $source );
		}

		$trip_index    = array();
		$service_ids   = array();
		$trip_ids      = array();
		foreach ( $trip_rows as $trip ) {
			$trip_index[ $trip['trip_id'] ] = $trip;
			$service_ids[]                  = $trip['service_id'];
			$trip_ids[]                     = $trip['trip_id'];
		}

		$service_index = $this->get_service_index( $provider_key, array_values( array_unique( $service_ids ) ) );
		$stop_rows     = $this->get_stops_by_ids( $provider_key, $primary_stops );
		$stop_index    = array();
		foreach ( $stop_rows as $stop_row ) {
			$stop_index[ $stop_row['stop_id'] ] = $stop_row;
		}

		$stop_times = $this->get_stop_times_for_trips_and_stops( $provider_key, $trip_ids, $primary_stops );
		if ( empty( $stop_times ) ) {
			return $this->get_empty_schedule( $source );
		}

		$day_refs          = $this->get_reference_dates();
		$today_ref         = $this->get_today_reference();
		$stops_payload     = array();
		$summary           = array();
		$available_days    = array();
		$has_live_departures = false;
		$realtime_repository = class_exists( 'TheCore_Collectivity_Transports_Realtime_Repository' )
			? new TheCore_Collectivity_Transports_Realtime_Repository( $this )
			: null;
		$realtime_status     = $realtime_repository ? $realtime_repository->get_realtime_status( $provider_key ) : array();
		$realtime_index      = array();
		$realtime_alerts     = array();
		$realtime_vehicles   = array();
		$tracked_trip_ids    = ! empty( $primary_stops ) ? $this->get_trip_ids_serving_stops( $provider_key, $trip_rows, $primary_stops ) : array();

		if ( ! empty( $source_config['realtime_is_enabled'] ) && $realtime_repository ) {
			$realtime_index = $realtime_repository->get_prediction_index(
				$provider_key,
				$trip_ids,
				$primary_stops,
				$today_ref['serviceDate']
			);
			$realtime_alerts = $realtime_repository->get_alerts_for_context(
				$provider_key,
				$route_ids,
				$primary_stops,
				$trip_ids,
				isset( $route_meta['routeType'] ) ? intval( $route_meta['routeType'] ) : null,
				! empty( $route_meta['agencyId'] ) ? (string) $route_meta['agencyId'] : ''
			);
			$realtime_vehicles = $realtime_repository->get_vehicle_positions_for_context(
				$provider_key,
				$route_ids,
				$trip_ids
			);

			if ( ! empty( $realtime_vehicles ) ) {
				foreach ( $realtime_vehicles as $index => $vehicle ) {
					$trip_id = ! empty( $vehicle['tripId'] ) ? (string) $vehicle['tripId'] : '';
					$realtime_vehicles[ $index ]['servesTrackedStops'] = empty( $primary_stops ) || '' === $trip_id || isset( $tracked_trip_ids[ $trip_id ] );
				}
				unset( $vehicle, $index );
			}
		}

		foreach ( $primary_stops as $stop_id ) {
			$stop_payload = $this->build_stop_schedule_payload( $stop_id, $stop_index, $stop_times, $trip_index, $service_index, $day_refs, $today_ref, $realtime_index );
			if ( empty( $stop_payload['directions'] ) ) {
				continue;
			}

			foreach ( $stop_payload['directions'] as $direction_payload ) {
				if ( ! empty( $direction_payload['liveDepartures']['items'] ) ) {
					$has_live_departures = true;
					break;
				}
			}

			$stops_payload[] = $stop_payload;
		}

		foreach ( $day_refs as $day_key => $day_ref ) {
			$day_times = array();
			foreach ( $stops_payload as $stop_payload ) {
				foreach ( $stop_payload['directions'] as $direction_payload ) {
					if ( empty( $direction_payload['dayTypes'][ $day_key ]['departures'] ) ) {
						continue;
					}
					$day_times = array_merge( $day_times, $direction_payload['dayTypes'][ $day_key ]['departures'] );
				}
			}

			$day_times = array_values( array_unique( $day_times ) );
			sort( $day_times );
			if ( empty( $day_times ) ) {
				continue;
			}

			$available_days[] = $day_key;
			$summary[] = array(
				'key'           => $day_key,
				'label'         => $day_ref['label'],
				'referenceDate' => $day_ref['date'],
				'first'         => reset( $day_times ),
				'last'          => end( $day_times ),
				'range'         => reset( $day_times ) . ' - ' . end( $day_times ),
			);
		}

		if ( empty( $stops_payload ) ) {
			return $this->get_empty_schedule( $source );
		}

		$status               = $this->get_import_status( $provider_key );
		$has_realtime_content = $has_live_departures || ! empty( $realtime_alerts ) || ! empty( $realtime_vehicles );

			return array(
				'available'      => ! empty( $summary ),
				'source'         => $source,
				'providerKey'    => $provider_key,
				'updatedAt'      => ! empty( $status['completed_at'] ) ? (string) $status['completed_at'] : '',
				'realtime'       => array(
					'enabled'           => ! empty( $source_config['realtime_is_enabled'] ),
					'format'            => ! empty( $source_config['realtime_format'] ) ? (string) $source_config['realtime_format'] : 'none',
					'updatedAt'         => ! empty( $realtime_status['completed_at'] ) ? (string) $realtime_status['completed_at'] : '',
					'available'         => $has_realtime_content,
					'alertsAvailable'   => ! empty( $realtime_alerts ),
					'vehiclesAvailable' => ! empty( $realtime_vehicles ),
					'status'            => ! empty( $realtime_status['status'] ) ? (string) $realtime_status['status'] : '',
					'providerKey'       => $provider_key,
					'alerts'            => $realtime_alerts,
					'vehicles'          => $realtime_vehicles,
				),
				'summary'        => $summary,
				'availableDays'  => $available_days,
				'stops'          => $stops_payload,
				'summaryText'    => implode( ' | ', array_map( array( $this, 'format_summary_label' ), $summary ) ),
				'routeMeta'      => $route_meta,
				'routeGeoJson'   => $route_geojson,
			);
		}

	/**
	 * Resolve destination labels for one stop or stop hub.
	 *
	 * @param array  $stop_ids     Exact GTFS stop ids.
	 * @param array  $line_ids     Related transport line post ids.
	 * @param string $provider_key Optional provider key.
	 * @param string $strategy     Optional strategy override.
	 * @return array
	 */
	public function get_stop_destination_labels( array $stop_ids, array $line_ids = array(), $provider_key = '', $strategy = '' ) {
		$provider_key = $this->resolve_provider_key( $provider_key );
		$stop_ids     = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $stop_ids ) ) ) );
		$line_ids     = array_values( array_unique( array_filter( array_map( 'intval', $line_ids ) ) ) );

		if ( empty( $stop_ids ) || empty( $line_ids ) ) {
			return array();
		}

		$source_config = $this->get_gtfs_source_config( $provider_key );
		$strategy      = $this->normalize_stop_direction_strategy( $strategy ?: ( $source_config['stop_direction_strategy'] ?? self::STOP_DIRECTION_STRATEGY_GTFS ) );
		$cache_key     = md5( wp_json_encode( array( $provider_key, $strategy, $stop_ids, $line_ids ) ) );

		if ( isset( $this->stop_destination_cache[ $cache_key ] ) ) {
			return $this->stop_destination_cache[ $cache_key ];
		}

		$route_ids = $this->get_route_ids_for_line_posts( $line_ids, $provider_key );
		if ( empty( $route_ids ) ) {
			$this->stop_destination_cache[ $cache_key ] = array();
			return array();
		}

		$labels = array();
		if ( self::STOP_DIRECTION_STRATEGY_GTFS === $strategy ) {
			$labels = $this->build_stop_destination_labels_from_gtfs( $provider_key, $route_ids, $stop_ids );
			if ( empty( $labels ) ) {
				$labels = $this->build_stop_destination_labels_from_headsigns( $provider_key, $route_ids, $stop_ids );
			}
		} else {
			$labels = $this->build_stop_destination_labels_from_headsigns( $provider_key, $route_ids, $stop_ids );
		}

		natcasesort( $labels );
		$labels = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $labels ) ) ) );
		$this->stop_destination_cache[ $cache_key ] = $labels;

		return $labels;
	}

	/**
	 * Build stop payload for all directions and day types.
	 *
	 * @param string $stop_id       Stop id.
	 * @param array  $stop_index    Indexed stop rows.
	 * @param array  $stop_times    Stop time rows.
	 * @param array  $trip_index    Indexed trip rows.
	 * @param array  $service_index Indexed service rows.
	 * @param array  $day_refs      Reference dates.
	 * @return array
	 */
	private function build_stop_schedule_payload( $stop_id, $stop_index, $stop_times, $trip_index, $service_index, $day_refs, array $today_ref, array $realtime_index ) {
		$directions = array();
		$now_ts     = ! empty( $today_ref['nowTimestamp'] ) ? intval( $today_ref['nowTimestamp'] ) : time();

		foreach ( $stop_times as $row ) {
			if ( $row['stop_id'] !== $stop_id ) {
				continue;
			}

			if ( empty( $trip_index[ $row['trip_id'] ] ) ) {
				continue;
			}

			$trip          = $trip_index[ $row['trip_id'] ];
			$direction_key = $trip['direction_id'] . '|' . $trip['trip_headsign'];
			if ( empty( $directions[ $direction_key ] ) ) {
				$directions[ $direction_key ] = array(
					'directionId' => $trip['direction_id'],
					'headsign'    => $trip['trip_headsign'],
					'dayTypes'    => array(),
					'todayTrips'  => array(),
					'liveDepartures' => array(
						'label'         => __( 'Prochains départs', 'bellevue' ),
						'referenceDate' => $today_ref['date'],
						'items'         => array(),
					),
				);
			}

			foreach ( $day_refs as $day_key => $day_ref ) {
				if ( empty( $service_index[ $trip['service_id'] ] ) ) {
					continue;
				}

				$service = $service_index[ $trip['service_id'] ];
				if ( ! $this->is_service_active_on_date( $service, $day_ref['date'] ) ) {
					continue;
				}

				if ( empty( $directions[ $direction_key ]['dayTypes'][ $day_key ] ) ) {
					$directions[ $direction_key ]['dayTypes'][ $day_key ] = array(
						'key'           => $day_key,
						'label'         => $day_ref['label'],
						'referenceDate' => $day_ref['date'],
						'departures'    => array(),
						'count'         => 0,
						'truncated'     => false,
					);
				}

				$time = $this->format_seconds_to_time( intval( $row['departure_secs'] ) );
				$directions[ $direction_key ]['dayTypes'][ $day_key ]['departures'][] = $time;
			}

			if ( ! empty( $service_index[ $trip['service_id'] ] ) && $this->is_service_active_on_date( $service_index[ $trip['service_id'] ], $today_ref['date'] ) ) {
				$prediction = $this->get_realtime_prediction_for_stop_time_row( $row, $realtime_index );
				$today_item = $this->build_stop_passage_payload( $row, $prediction, $today_ref );
				if ( ! empty( $today_item ) ) {
					$directions[ $direction_key ]['todayTrips'][] = $today_item;
				}
				$live_item  = $this->build_live_departure_payload( $today_item, $now_ts );
				if ( ! empty( $live_item ) ) {
					$directions[ $direction_key ]['liveDepartures']['items'][] = $live_item;
				}
			}
		}

		foreach ( $directions as $direction_key => $direction_payload ) {
			foreach ( $direction_payload['dayTypes'] as $day_key => $day_payload ) {
				$times = array_values( array_unique( $day_payload['departures'] ) );
				sort( $times );
				$directions[ $direction_key ]['dayTypes'][ $day_key ]['count'] = count( $times );
				$directions[ $direction_key ]['dayTypes'][ $day_key ]['departures'] = $times;
				$directions[ $direction_key ]['dayTypes'][ $day_key ]['truncated']  = false;
			}

			if ( ! empty( $direction_payload['liveDepartures']['items'] ) ) {
				$items = $direction_payload['liveDepartures']['items'];
				usort(
					$items,
					static function ( $left, $right ) {
						return intval( $left['displayTimestamp'] ?? 0 ) <=> intval( $right['displayTimestamp'] ?? 0 );
					}
				);
				$directions[ $direction_key ]['liveDepartures']['items'] = array_slice( $items, 0, 6 );
			}

			if ( ! empty( $direction_payload['todayTrips'] ) ) {
				$items = $direction_payload['todayTrips'];
				usort(
					$items,
					static function ( $left, $right ) {
						return intval( $left['displayTimestamp'] ?? 0 ) <=> intval( $right['displayTimestamp'] ?? 0 );
					}
				);
				$directions[ $direction_key ]['todayTrips'] = $items;
			}

			ksort( $directions[ $direction_key ]['dayTypes'] );
		}

		return array(
			'stopId'     => $stop_id,
			'stopName'   => ! empty( $stop_index[ $stop_id ]['stop_name'] ) ? $stop_index[ $stop_id ]['stop_name'] : $stop_id,
			'directions' => array_values( $directions ),
		);
	}

	/**
	 * Resolve GTFS route ids for related transport lines.
	 *
	 * @param array  $line_ids     Transport line post ids.
	 * @param string $provider_key Provider key.
	 * @return array
	 */
	private function get_route_ids_for_line_posts( array $line_ids, $provider_key ) {
		$route_ids   = array();
		$short_names = array();

		foreach ( $line_ids as $line_id ) {
			$line_id = absint( $line_id );
			if ( $line_id <= 0 || ! $this->is_gtfs_provider_match( $line_id, $provider_key ) ) {
				continue;
			}

			$route_ids   = array_merge( $route_ids, $this->parse_meta_list( get_post_meta( $line_id, TheCore_Collectivity_Transports_Meta::META_GTFS_ROUTE_IDS, true ) ) );
			$short_names = array_merge( $short_names, $this->parse_meta_list( get_post_meta( $line_id, TheCore_Collectivity_Transports_Meta::META_GTFS_SHORT_NAMES, true ) ) );
		}

		$route_ids   = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $route_ids ) ) ) );
		$short_names = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $short_names ) ) ) );

		if ( ! empty( $short_names ) ) {
			$route_rows = $this->get_routes_for_mapping( $provider_key, $route_ids, $short_names );
			$route_ids  = array_values( array_unique( array_merge( $route_ids, wp_list_pluck( $route_rows, 'route_id' ) ) ) );
		}

		return $route_ids;
	}

	/**
	 * Build stop destination labels from GTFS terminal stops.
	 *
	 * @param string $provider_key Provider key.
	 * @param array  $route_ids    GTFS route ids.
	 * @param array  $stop_ids     Exact stop ids.
	 * @return array
	 */
	private function build_stop_destination_labels_from_gtfs( $provider_key, array $route_ids, array $stop_ids ) {
		$trip_rows = $this->get_trips_for_routes( $provider_key, $route_ids );
		if ( empty( $trip_rows ) ) {
			return array();
		}

		$matched_trip_ids = $this->get_trip_ids_serving_stops( $provider_key, $trip_rows, $stop_ids );
		if ( empty( $matched_trip_ids ) ) {
			return array();
		}

		$trip_ids            = array_keys( $matched_trip_ids );
		$stop_sequence_index = $this->get_trip_stop_sequence_index( $provider_key, $trip_ids, $stop_ids );
		$labels              = array();

		foreach ( $trip_rows as $trip_row ) {
			$trip_id = ! empty( $trip_row['trip_id'] ) ? (string) $trip_row['trip_id'] : '';
			if ( '' === $trip_id || ! isset( $matched_trip_ids[ $trip_id ] ) || empty( $stop_sequence_index[ $trip_id ] ) ) {
				continue;
			}

			$current_sequence  = intval( $stop_sequence_index[ $trip_id ]['stop_sequence'] ?? 0 );
			$terminal_sequence = intval( $trip_row['terminal_stop_sequence'] ?? 0 );
			if ( $terminal_sequence <= $current_sequence ) {
				continue;
			}

			$label = $this->normalize_stop_destination_label( $trip_row['terminal_stop_locality'] ?? '' );
			if ( '' === $label ) {
				$label = $this->normalize_stop_destination_label( $trip_row['terminal_stop_name'] ?? '' );
			}
			if ( '' !== $label ) {
				$labels[ $label ] = $label;
			}
		}

		return array_values( $labels );
	}

	/**
	 * Build stop destination labels by parsing GTFS headsigns.
	 *
	 * @param string $provider_key Provider key.
	 * @param array  $route_ids    GTFS route ids.
	 * @param array  $stop_ids     Exact stop ids.
	 * @return array
	 */
	private function build_stop_destination_labels_from_headsigns( $provider_key, array $route_ids, array $stop_ids ) {
		$trip_rows = $this->get_trips_for_routes( $provider_key, $route_ids );
		if ( empty( $trip_rows ) ) {
			return array();
		}

		$matched_trip_ids = $this->get_trip_ids_serving_stops( $provider_key, $trip_rows, $stop_ids );
		if ( empty( $matched_trip_ids ) ) {
			return array();
		}

		$trip_ids            = array_keys( $matched_trip_ids );
		$stop_sequence_index = $this->get_trip_stop_sequence_index( $provider_key, $trip_ids, $stop_ids );
		$labels              = array();

		foreach ( $trip_rows as $trip_row ) {
			$trip_id = ! empty( $trip_row['trip_id'] ) ? (string) $trip_row['trip_id'] : '';
			if ( '' === $trip_id || ! isset( $matched_trip_ids[ $trip_id ] ) || empty( $stop_sequence_index[ $trip_id ] ) ) {
				continue;
			}

			$current_sequence  = intval( $stop_sequence_index[ $trip_id ]['stop_sequence'] ?? 0 );
			$terminal_sequence = intval( $trip_row['terminal_stop_sequence'] ?? 0 );
			if ( $terminal_sequence > 0 && $terminal_sequence <= $current_sequence ) {
				continue;
			}

			$label = $this->parse_stop_destination_label_from_headsign( $trip_row['trip_headsign'] ?? '' );
			if ( '' !== $label ) {
				$labels[ $label ] = $label;
			}
		}

		return array_values( $labels );
	}

	/**
	 * Resolve route rows for a line mapping.
	 *
	 * @param array $route_ids   Exact route ids.
	 * @param array $short_names Public short names.
	 * @return array
	 */
	private function get_routes_for_mapping( $provider_key, array $route_ids, array $short_names ) {
		global $wpdb;

		$where  = array();
		$params = array();
		$table  = TheCore_Collectivity_Transports_Schedule_Schema::get_table_name( TheCore_Collectivity_Transports_Schedule_Schema::TABLE_ROUTES );

		if ( ! empty( $route_ids ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $route_ids ), '%s' ) );
			$where[]      = "route_id IN ({$placeholders})";
			$params       = array_merge( $params, $route_ids );
		}

		if ( ! empty( $short_names ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $short_names ), '%s' ) );
			$where[]      = "route_short_name IN ({$placeholders})";
			$params       = array_merge( $params, $short_names );
		}

		if ( empty( $where ) ) {
			return array();
		}

		$sql   = "SELECT route_id, route_short_name, route_long_name, route_type, agency_id, route_color, route_text_color FROM {$table} WHERE provider_key = %s AND (" . implode( ' OR ', $where ) . ') ORDER BY route_short_name ASC';
		$query = $wpdb->prepare( $sql, array_merge( array( sanitize_key( (string) $provider_key ) ), $params ) );
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Pick primary route metadata for a line.
	 *
	 * @param array $route_rows   Matching GTFS routes.
	 * @param array $short_names  Preferred short names.
	 * @return array
	 */
	private function build_route_meta( array $route_rows, array $short_names ) {
		if ( empty( $route_rows ) ) {
			return array();
		}

		$preferred = null;
		if ( ! empty( $short_names ) ) {
			foreach ( $short_names as $short_name ) {
				foreach ( $route_rows as $route_row ) {
					if ( ! empty( $route_row['route_short_name'] ) && 0 === strcasecmp( (string) $route_row['route_short_name'], (string) $short_name ) ) {
						$preferred = $route_row;
						break 2;
					}
				}
			}
		}

		if ( null === $preferred ) {
			$preferred = reset( $route_rows );
		}

		if ( ! is_array( $preferred ) ) {
			return array();
		}

		return array(
			'routeId'       => ! empty( $preferred['route_id'] ) ? (string) $preferred['route_id'] : '',
			'routeShortName'=> ! empty( $preferred['route_short_name'] ) ? (string) $preferred['route_short_name'] : '',
			'routeLongName' => ! empty( $preferred['route_long_name'] ) ? (string) $preferred['route_long_name'] : '',
			'routeType'     => isset( $preferred['route_type'] ) ? intval( $preferred['route_type'] ) : 0,
			'agencyId'      => ! empty( $preferred['agency_id'] ) ? (string) $preferred['agency_id'] : '',
			'routeColor'    => ! empty( $preferred['route_color'] ) ? (string) $preferred['route_color'] : '',
			'routeTextColor'=> ! empty( $preferred['route_text_color'] ) ? (string) $preferred['route_text_color'] : '',
		);
	}

	/**
	 * Get imported trips for routes.
	 *
	 * @param array $route_ids Route ids.
	 * @return array
	 */
	private function get_trips_for_routes( $provider_key, array $route_ids ) {
		global $wpdb;

		if ( empty( $route_ids ) ) {
			return array();
		}

		$table        = TheCore_Collectivity_Transports_Schedule_Schema::get_table_name( TheCore_Collectivity_Transports_Schedule_Schema::TABLE_TRIPS );
		$placeholders = implode( ', ', array_fill( 0, count( $route_ids ), '%s' ) );
		$sql          = "SELECT trip_id, route_id, service_id, trip_headsign, direction_id, shape_id, terminal_stop_id, terminal_stop_name, terminal_stop_locality, terminal_stop_sequence FROM {$table} WHERE provider_key = %s AND route_id IN ({$placeholders})";
		$query        = $wpdb->prepare( $sql, array_merge( array( sanitize_key( (string) $provider_key ) ), $route_ids ) );
		$rows         = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Build one representative GeoJSON payload for a transport line from GTFS shapes.
	 *
	 * @param string $provider_key       Provider key.
	 * @param array  $trip_rows          Imported GTFS trip rows.
	 * @param array  $preferred_stop_ids Stop ids preferred for shape selection.
	 * @return array|null
	 */
	private function build_line_geojson( $provider_key, array $trip_rows, array $preferred_stop_ids = array() ) {
		$trip_rows_for_shapes = $trip_rows;
		if ( ! empty( $preferred_stop_ids ) ) {
			$preferred_trip_ids = $this->get_trip_ids_serving_stops( $provider_key, $trip_rows, $preferred_stop_ids );
			if ( ! empty( $preferred_trip_ids ) ) {
				$trip_rows_for_shapes = array_values(
					array_filter(
						$trip_rows,
						static function ( $trip_row ) use ( $preferred_trip_ids ) {
							$trip_id = ! empty( $trip_row['trip_id'] ) ? (string) $trip_row['trip_id'] : '';

							return '' !== $trip_id && isset( $preferred_trip_ids[ $trip_id ] );
						}
					)
				);
			}
		}

		$shape_usage = array();
		$headsigns   = array();

		foreach ( $trip_rows_for_shapes as $trip_row ) {
			$shape_id = ! empty( $trip_row['shape_id'] ) ? sanitize_text_field( (string) $trip_row['shape_id'] ) : '';
			if ( '' === $shape_id ) {
				continue;
			}

			$direction_id = isset( $trip_row['direction_id'] ) ? sanitize_text_field( (string) $trip_row['direction_id'] ) : '';
			if ( empty( $shape_usage[ $direction_id ] ) ) {
				$shape_usage[ $direction_id ] = array();
			}

			if ( empty( $shape_usage[ $direction_id ][ $shape_id ] ) ) {
				$shape_usage[ $direction_id ][ $shape_id ] = 0;
			}

			++$shape_usage[ $direction_id ][ $shape_id ];

			if ( ! empty( $trip_row['trip_headsign'] ) && empty( $headsigns[ $direction_id ] ) ) {
				$headsigns[ $direction_id ] = sanitize_text_field( (string) $trip_row['trip_headsign'] );
			}
		}

		if ( empty( $shape_usage ) ) {
			return null;
		}

		$selected_shape_ids = array();
		foreach ( $shape_usage as $direction_id => $shapes ) {
			arsort( $shapes );
			$shape_id = (string) key( $shapes );
			if ( '' === $shape_id ) {
				continue;
			}

			$selected_shape_ids[ $direction_id ] = $shape_id;
		}

		if ( empty( $selected_shape_ids ) ) {
			return null;
		}

		$shape_points = $this->get_shape_points( $provider_key, array_values( array_unique( $selected_shape_ids ) ) );
		if ( empty( $shape_points ) ) {
			return null;
		}

		$features = array();
		foreach ( $selected_shape_ids as $direction_id => $shape_id ) {
			if ( empty( $shape_points[ $shape_id ] ) ) {
				continue;
			}

			$coordinates = array();
			foreach ( $shape_points[ $shape_id ] as $point ) {
				if ( ! isset( $point['shape_pt_lon'], $point['shape_pt_lat'] ) ) {
					continue;
				}

				$coordinates[] = array(
					(float) $point['shape_pt_lon'],
					(float) $point['shape_pt_lat'],
				);
			}

			if ( count( $coordinates ) < 2 ) {
				continue;
			}

			$features[] = array(
				'type'       => 'Feature',
				'properties' => array(
					'directionId' => (string) $direction_id,
					'shapeId'     => (string) $shape_id,
					'headsign'    => ! empty( $headsigns[ $direction_id ] ) ? (string) $headsigns[ $direction_id ] : '',
				),
				'geometry'   => array(
					'type'        => 'LineString',
					'coordinates' => $coordinates,
				),
			);
		}

		if ( empty( $features ) ) {
			return null;
		}

		return array(
			'type'     => 'FeatureCollection',
			'features' => $features,
		);
	}

	/**
	 * Get trip ids that actually serve a given set of stops.
	 *
	 * @param string $provider_key Provider key.
	 * @param array  $trip_rows    Imported GTFS trip rows.
	 * @param array  $stop_ids     Preferred stop ids.
	 * @return array
	 */
	private function get_trip_ids_serving_stops( $provider_key, array $trip_rows, array $stop_ids ) {
		if ( empty( $trip_rows ) || empty( $stop_ids ) ) {
			return array();
		}

		$trip_ids = array_values(
			array_filter(
				array_map(
					static function ( $trip_row ) {
						return ! empty( $trip_row['trip_id'] ) ? sanitize_text_field( (string) $trip_row['trip_id'] ) : '';
					},
					$trip_rows
				)
			)
		);

		if ( empty( $trip_ids ) ) {
			return array();
		}

		$stop_times = $this->get_stop_times_for_trips_and_stops( $provider_key, $trip_ids, $stop_ids );
		if ( empty( $stop_times ) ) {
			return array();
		}

		$matched_trip_ids = array();
		foreach ( $stop_times as $stop_time ) {
			if ( empty( $stop_time['trip_id'] ) ) {
				continue;
			}

			$matched_trip_ids[ (string) $stop_time['trip_id'] ] = true;
		}

		return $matched_trip_ids;
	}

	/**
	 * Get the selected stop sequence for each trip serving one stop set.
	 *
	 * @param string $provider_key Provider key.
	 * @param array  $trip_ids     Trip ids.
	 * @param array  $stop_ids     Selected stop ids.
	 * @return array
	 */
	private function get_trip_stop_sequence_index( $provider_key, array $trip_ids, array $stop_ids ) {
		$stop_times = $this->get_stop_times_for_trips_and_stops( $provider_key, $trip_ids, $stop_ids );
		if ( empty( $stop_times ) ) {
			return array();
		}

		$index = array();
		foreach ( $stop_times as $stop_time ) {
			$trip_id = ! empty( $stop_time['trip_id'] ) ? (string) $stop_time['trip_id'] : '';
			if ( '' === $trip_id ) {
				continue;
			}

			$sequence = intval( $stop_time['stop_sequence'] ?? 0 );
			if ( empty( $index[ $trip_id ] ) || $sequence < intval( $index[ $trip_id ]['stop_sequence'] ?? PHP_INT_MAX ) ) {
				$index[ $trip_id ] = $stop_time;
			}
		}

		return $index;
	}

	/**
	 * Get shape point rows indexed by shape id.
	 *
	 * @param string $provider_key Provider key.
	 * @param array  $shape_ids    Shape ids.
	 * @return array
	 */
	private function get_shape_points( $provider_key, array $shape_ids ) {
		global $wpdb;

		if ( empty( $shape_ids ) ) {
			return array();
		}

		$table        = TheCore_Collectivity_Transports_Schedule_Schema::get_table_name( TheCore_Collectivity_Transports_Schedule_Schema::TABLE_SHAPES );
		$placeholders = implode( ', ', array_fill( 0, count( $shape_ids ), '%s' ) );
		$sql          = "SELECT shape_id, shape_pt_sequence, shape_pt_lat, shape_pt_lon FROM {$table} WHERE provider_key = %s AND shape_id IN ({$placeholders}) ORDER BY shape_id ASC, shape_pt_sequence ASC";
		$query        = $wpdb->prepare( $sql, array_merge( array( sanitize_key( (string) $provider_key ) ), $shape_ids ) );
		$rows         = $wpdb->get_results( $query, ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$indexed = array();
		foreach ( $rows as $row ) {
			$shape_id = ! empty( $row['shape_id'] ) ? (string) $row['shape_id'] : '';
			if ( '' === $shape_id ) {
				continue;
			}

			if ( empty( $indexed[ $shape_id ] ) ) {
				$indexed[ $shape_id ] = array();
			}

			$indexed[ $shape_id ][] = $row;
		}

		return $indexed;
	}

	/**
	 * Get stop times for selected trips and stops.
	 *
	 * @param array $trip_ids  Trip ids.
	 * @param array $stop_ids  Stop ids.
	 * @return array
	 */
	private function get_stop_times_for_trips_and_stops( $provider_key, array $trip_ids, array $stop_ids ) {
		global $wpdb;

		if ( empty( $trip_ids ) || empty( $stop_ids ) ) {
			return array();
		}

		$table             = TheCore_Collectivity_Transports_Schedule_Schema::get_table_name( TheCore_Collectivity_Transports_Schedule_Schema::TABLE_STOP_TIMES );
		$trip_placeholders = implode( ', ', array_fill( 0, count( $trip_ids ), '%s' ) );
		$stop_placeholders = implode( ', ', array_fill( 0, count( $stop_ids ), '%s' ) );
		$params            = array_merge( array( sanitize_key( (string) $provider_key ) ), $trip_ids, $stop_ids );
		$sql               = "SELECT trip_id, stop_id, stop_sequence, arrival_secs, departure_secs FROM {$table} WHERE provider_key = %s AND trip_id IN ({$trip_placeholders}) AND stop_id IN ({$stop_placeholders}) ORDER BY stop_id ASC, departure_secs ASC";
		$query             = $wpdb->prepare( $sql, $params );
		$rows              = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get stops by GTFS stop ids.
	 *
	 * @param array $stop_ids Stop ids.
	 * @return array
	 */
	private function get_stops_by_ids( $provider_key, array $stop_ids ) {
		global $wpdb;

		if ( empty( $stop_ids ) ) {
			return array();
		}

		$table        = TheCore_Collectivity_Transports_Schedule_Schema::get_table_name( TheCore_Collectivity_Transports_Schedule_Schema::TABLE_STOPS );
		$placeholders = implode( ', ', array_fill( 0, count( $stop_ids ), '%s' ) );
		$sql          = "SELECT stop_id, stop_name, parent_station FROM {$table} WHERE provider_key = %s AND stop_id IN ({$placeholders})";
		$query        = $wpdb->prepare( $sql, array_merge( array( sanitize_key( (string) $provider_key ) ), $stop_ids ) );
		$rows         = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Build service index.
	 *
	 * @param array $service_ids Service ids.
	 * @return array
	 */
	private function get_service_index( $provider_key, array $service_ids ) {
		global $wpdb;

		if ( empty( $service_ids ) ) {
			return array();
		}

		$table        = TheCore_Collectivity_Transports_Schedule_Schema::get_table_name( TheCore_Collectivity_Transports_Schedule_Schema::TABLE_SERVICES );
		$placeholders = implode( ', ', array_fill( 0, count( $service_ids ), '%s' ) );
		$sql          = "SELECT service_id, monday, tuesday, wednesday, thursday, friday, saturday, sunday, start_date, end_date, added_dates, removed_dates FROM {$table} WHERE provider_key = %s AND service_id IN ({$placeholders})";
		$query        = $wpdb->prepare( $sql, array_merge( array( sanitize_key( (string) $provider_key ) ), $service_ids ) );
		$rows         = $wpdb->get_results( $query, ARRAY_A );
		$index        = array();

		foreach ( (array) $rows as $row ) {
			$row['added_dates']   = $this->decode_json_array( $row['added_dates'] );
			$row['removed_dates'] = $this->decode_json_array( $row['removed_dates'] );
			$index[ $row['service_id'] ] = $row;
		}

		return $index;
	}

	/**
	 * Determine if a service is active on a given date.
	 *
	 * @param array  $service Service row.
	 * @param string $date    Y-m-d date.
	 * @return bool
	 */
	private function is_service_active_on_date( array $service, $date ) {
		$ymd = str_replace( '-', '', $date );
		if ( in_array( $ymd, $service['removed_dates'], true ) ) {
			return false;
		}

		if ( in_array( $ymd, $service['added_dates'], true ) ) {
			return true;
		}

		if ( ! empty( $service['start_date'] ) && $ymd < $service['start_date'] ) {
			return false;
		}

		if ( ! empty( $service['end_date'] ) && $ymd > $service['end_date'] ) {
			return false;
		}

		$weekday = strtolower( ( new DateTimeImmutable( $date, wp_timezone() ) )->format( 'l' ) );
		return ! empty( $service[ $weekday ] );
	}

	/**
	 * Get reference dates for UI day types.
	 *
	 * @return array
	 */
	private function get_reference_dates() {
		$timezone = wp_timezone();
		$today    = new DateTimeImmutable( 'now', $timezone );

		return array(
			'semaine'  => array(
				'label' => __( 'Lun-Ven', 'bellevue' ),
				'date'  => $this->get_next_weekday_date( $today )->format( 'Y-m-d' ),
			),
			'samedi'   => array(
				'label' => __( 'Samedi', 'bellevue' ),
				'date'  => $this->get_next_named_day( $today, 6 )->format( 'Y-m-d' ),
			),
			'dimanche' => array(
				'label' => __( 'Dimanche', 'bellevue' ),
				'date'  => $this->get_next_named_day( $today, 7 )->format( 'Y-m-d' ),
			),
		);
	}

	/**
	 * Get current-day realtime reference payload.
	 *
	 * @return array
	 */
	private function get_today_reference() {
		$timezone = wp_timezone();
		$today    = new DateTimeImmutable( 'now', $timezone );
		$day      = intval( $today->format( 'N' ) );
		$day_key  = 'semaine';

		if ( 6 === $day ) {
			$day_key = 'samedi';
		} elseif ( 7 === $day ) {
			$day_key = 'dimanche';
		}

		return array(
			'dayKey'       => $day_key,
			'date'         => $today->format( 'Y-m-d' ),
			'serviceDate'  => $today->format( 'Ymd' ),
			'nowTimestamp' => $today->getTimestamp(),
		);
	}

	/**
	 * Get next weekday reference date.
	 *
	 * @param DateTimeImmutable $date Current date.
	 * @return DateTimeImmutable
	 */
	private function get_next_weekday_date( DateTimeImmutable $date ) {
		$current = $date;
		for ( $i = 0; $i < 7; $i++ ) {
			$day = intval( $current->format( 'N' ) );
			if ( $day >= 1 && $day <= 5 ) {
				return $current;
			}
			$current = $current->modify( '+1 day' );
		}

		return $date;
	}

	/**
	 * Get next occurrence of a named day number.
	 *
	 * @param DateTimeImmutable $date Current date.
	 * @param int               $day  Day number, 1=Mon ... 7=Sun.
	 * @return DateTimeImmutable
	 */
	private function get_next_named_day( DateTimeImmutable $date, $day ) {
		$current = $date;
		for ( $i = 0; $i < 7; $i++ ) {
			if ( intval( $current->format( 'N' ) ) === intval( $day ) ) {
				return $current;
			}
			$current = $current->modify( '+1 day' );
		}

		return $date;
	}

	/**
	 * Resolve GTFS stop ids for a line.
	 *
	 * @param int $line_id Line post id.
	 * @return array
	 */
	private function get_line_stop_ids( $line_id, $provider_key = '' ) {
		$stop_ids = $this->parse_meta_list( get_post_meta( $line_id, TheCore_Collectivity_Transports_Meta::META_PRIMARY_STOP_IDS, true ) );
		if ( ! empty( $stop_ids ) ) {
			return $stop_ids;
		}

		$places = get_posts(
			array(
				'post_type'      => TheCore_Collectivity_Transports_Post_Types::POST_TYPE_PLACE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
			)
		);

		foreach ( $places as $place ) {
			if ( ! $this->is_gtfs_provider_match( $place->ID, $provider_key ) ) {
				continue;
			}

			$related_lines = get_post_meta( $place->ID, TheCore_Collectivity_Transports_Meta::META_RELATED_LINES, true );
			$related_lines = is_array( $related_lines ) ? array_map( 'intval', $related_lines ) : array();
			if ( ! in_array( intval( $line_id ), $related_lines, true ) ) {
				continue;
			}

			$stop_ids = array_merge( $stop_ids, $this->parse_meta_list( get_post_meta( $place->ID, TheCore_Collectivity_Transports_Meta::META_GTFS_STOP_IDS, true ) ) );
		}

		return array_values( array_unique( array_filter( $stop_ids ) ) );
	}

	/**
	 * Parse a free-form list meta value.
	 *
	 * @param mixed $value Raw meta value.
	 * @return array
	 */
	public function parse_meta_list( $value ) {
		if ( is_array( $value ) ) {
			$items = array_map(
				static function ( $item ) {
					return sanitize_text_field( trim( (string) $item ) );
				},
				$value
			);

			return array_values( array_unique( array_filter( $items, 'strlen' ) ) );
		}

		$value = str_replace( array( "\r\n", "\r", ';', '|' ), array( "\n", "\n", ',', ',' ), (string) $value );
		$items = preg_split( '/[\n,]+/', $value );
		$items = is_array( $items ) ? $items : array();
		$items = array_map( 'trim', $items );
		$items = array_filter( $items, 'strlen' );
		return array_values( array_unique( array_map( 'sanitize_text_field', $items ) ) );
	}

	/**
	 * Decode JSON array safely.
	 *
	 * @param mixed $value JSON value.
	 * @return array
	 */
	private function decode_json_array( $value ) {
		$decoded = json_decode( (string) $value, true );
		return is_array( $decoded ) ? array_values( array_filter( array_map( 'sanitize_text_field', $decoded ) ) ) : array();
	}

	/**
	 * Format summary row label.
	 *
	 * @param array $summary Summary row.
	 * @return string
	 */
	private function format_summary_label( array $summary ) {
		return sprintf( '%s %s', $summary['label'], $summary['range'] );
	}

	/**
	 * Build a stable direction key.
	 *
	 * @param array $direction_payload Direction payload.
	 * @return string
	 */
	private function get_direction_key( array $direction_payload ) {
		$direction_id = isset( $direction_payload['directionId'] ) ? (string) $direction_payload['directionId'] : '';
		$headsign     = isset( $direction_payload['headsign'] ) ? (string) $direction_payload['headsign'] : '';
		return sanitize_text_field( $direction_id . '|' . $headsign );
	}

	/**
	 * Get a human-readable direction label.
	 *
	 * @param array $direction_payload Direction payload.
	 * @return string
	 */
	private function get_direction_label( array $direction_payload ) {
		$headsign     = isset( $direction_payload['headsign'] ) ? trim( (string) $direction_payload['headsign'] ) : '';
		$direction_id = isset( $direction_payload['directionId'] ) ? trim( (string) $direction_payload['directionId'] ) : '';

		if ( '' !== $headsign ) {
			return $headsign;
		}

		if ( '' !== $direction_id ) {
			return sprintf( 'Direction %s', $direction_id );
		}

		return __( 'Direction non renseignee', 'bellevue' );
	}

	/**
	 * Parse one destination label from a headsign string.
	 *
	 * @param string $headsign Raw headsign.
	 * @return string
	 */
	private function parse_stop_destination_label_from_headsign( $headsign ) {
		$headsign = trim( sanitize_text_field( (string) $headsign ) );
		if ( '' === $headsign ) {
			return '';
		}

		$segments = preg_split( '/\s*(?:→|->)\s*/u', $headsign );
		if ( is_array( $segments ) && count( $segments ) > 1 ) {
			$headsign = (string) end( $segments );
		}

		return $this->normalize_stop_destination_label( $headsign );
	}

	/**
	 * Normalize one stop destination label.
	 *
	 * @param string $label Raw label.
	 * @return string
	 */
	private function normalize_stop_destination_label( $label ) {
		return trim( sanitize_text_field( (string) $label ) );
	}

	/**
	 * Build an exact stop option label.
	 *
	 * @param string $stop_id      Exact stop id.
	 * @param string $stop_name    Human-readable stop name.
	 * @param bool   $is_duplicate Whether this stop name is ambiguous.
	 * @return string
	 */
	private function get_stop_option_label( $stop_id, $stop_name, $is_duplicate ) {
		$stop_id   = (string) $stop_id;
		$stop_name = '' !== (string) $stop_name ? (string) $stop_name : $stop_id;
		if ( ! $is_duplicate ) {
			return $stop_name;
		}

		$stop_suffix = preg_replace( '/^.*:/', '', $stop_id );
		return sprintf( '%s (%s)', $stop_name, $stop_suffix );
	}

	/**
	 * Convert seconds to HH:mm.
	 *
	 * @param int $seconds GTFS seconds.
	 * @return string
	 */
	private function format_seconds_to_time( $seconds ) {
		$hours   = floor( $seconds / 3600 );
		$minutes = floor( ( $seconds % 3600 ) / 60 );
		return sprintf( '%02d:%02d', $hours, $minutes );
	}

	/**
	 * Convert GTFS seconds for one service date into a Unix timestamp.
	 *
	 * @param string $date    Service date Y-m-d.
	 * @param int    $seconds GTFS seconds since service-day midnight.
	 * @return int
	 */
	private function build_gtfs_timestamp( $date, $seconds ) {
		$base = new DateTimeImmutable( $date . ' 00:00:00', wp_timezone() );
		return $base->getTimestamp() + intval( $seconds );
	}

	/**
	 * Format a Unix timestamp as HH:mm in site timezone.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	private function format_timestamp_to_time( $timestamp ) {
		if ( empty( $timestamp ) ) {
			return '';
		}

		return wp_date( 'H:i', intval( $timestamp ) );
	}

	/**
	 * Resolve one realtime prediction for a stop-time row.
	 *
	 * @param array $row            Static stop-time row.
	 * @param array $realtime_index Indexed realtime predictions.
	 * @return array|null
	 */
	private function get_realtime_prediction_for_stop_time_row( array $row, array $realtime_index ) {
		if ( empty( $row['trip_id'] ) || empty( $row['stop_id'] ) || empty( $realtime_index ) ) {
			return null;
		}

		$sequence_key = class_exists( 'TheCore_Collectivity_Transports_Realtime_Repository' )
			? ( new TheCore_Collectivity_Transports_Realtime_Repository( $this ) )->build_prediction_key( $row['trip_id'], $row['stop_id'], intval( $row['stop_sequence'] ?? 0 ) )
			: '';
		if ( $sequence_key && ! empty( $realtime_index[ $sequence_key ] ) ) {
			return $realtime_index[ $sequence_key ];
		}

		$fallback_key = class_exists( 'TheCore_Collectivity_Transports_Realtime_Repository' )
			? ( new TheCore_Collectivity_Transports_Realtime_Repository( $this ) )->build_prediction_key( $row['trip_id'], $row['stop_id'], '' )
			: '';
		if ( $fallback_key && ! empty( $realtime_index[ $fallback_key ] ) ) {
			return $realtime_index[ $fallback_key ];
		}

		return null;
	}

	/**
	 * Build one live departure payload for the current day.
	 *
	 * @param array      $row        Static stop-time row.
	 * @param array|null $prediction Realtime prediction.
	 * @param array      $today_ref  Today reference.
	 * @param int        $now_ts     Current timestamp.
	 * @return array|null
	 */
	private function build_stop_passage_payload( array $row, $prediction, array $today_ref ) {
		$scheduled_secs = isset( $row['departure_secs'] ) ? intval( $row['departure_secs'] ) : 0;
		$scheduled_ts   = $this->build_gtfs_timestamp( $today_ref['date'], $scheduled_secs );
		$status         = 'scheduled';
		$realtime_ts    = null;
		$delay_seconds  = null;

		if ( is_array( $prediction ) ) {
			$status        = ! empty( $prediction['schedule_relationship'] ) ? (string) $prediction['schedule_relationship'] : 'scheduled';
			$delay_seconds = null !== $prediction['departure_delay']
				? intval( $prediction['departure_delay'] )
				: ( null !== $prediction['arrival_delay']
					? intval( $prediction['arrival_delay'] )
					: ( null !== $prediction['trip_delay'] ? intval( $prediction['trip_delay'] ) : null ) );
			$realtime_ts   = ! empty( $prediction['departure_timestamp'] )
				? intval( $prediction['departure_timestamp'] )
				: ( ! empty( $prediction['arrival_timestamp'] ) ? intval( $prediction['arrival_timestamp'] ) : null );

			if ( null === $realtime_ts && null !== $delay_seconds && 'scheduled' === $status ) {
				$realtime_ts = $scheduled_ts + $delay_seconds;
			}

			if ( in_array( $status, array( 'cancelled', 'skipped' ), true ) ) {
				$realtime_ts = null;
			} elseif ( 'scheduled' === $status && ( null !== $realtime_ts || null !== $delay_seconds ) ) {
				$status = 'realtime';
			}
		}

		$display_ts = null !== $realtime_ts ? $realtime_ts : $scheduled_ts;

		return array(
			'tripId'           => ! empty( $row['trip_id'] ) ? (string) $row['trip_id'] : '',
			'stopId'           => ! empty( $row['stop_id'] ) ? (string) $row['stop_id'] : '',
			'stopSequence'     => isset( $row['stop_sequence'] ) ? intval( $row['stop_sequence'] ) : 0,
			'status'           => $status,
			'scheduled'        => $this->format_seconds_to_time( $scheduled_secs ),
			'scheduledTimestamp' => $scheduled_ts,
			'realtime'         => null !== $realtime_ts ? $this->format_timestamp_to_time( $realtime_ts ) : '',
			'realtimeTimestamp'=> $realtime_ts,
			'displayTime'      => $this->format_timestamp_to_time( $display_ts ),
			'displayTimestamp' => $display_ts,
			'delaySeconds'     => $delay_seconds,
			'delayMinutes'     => null !== $delay_seconds ? intval( round( $delay_seconds / 60 ) ) : null,
			'isRealtime'       => 'realtime' === $status,
		);
	}

	/**
	 * Keep only upcoming or very recent live departures for the realtime block.
	 *
	 * @param array|null $departure_item Prepared passage payload.
	 * @param int        $now_ts         Current timestamp.
	 * @return array|null
	 */
	private function build_live_departure_payload( $departure_item, $now_ts ) {
		if ( ! is_array( $departure_item ) || empty( $departure_item['displayTimestamp'] ) ) {
			return null;
		}

		if ( intval( $departure_item['displayTimestamp'] ) < ( $now_ts - 5 * MINUTE_IN_SECONDS ) ) {
			return null;
		}

		return $departure_item;
	}

	/**
	 * Empty schedule payload.
	 *
	 * @param string $source Source label.
	 * @return array
	 */
	private function get_empty_schedule( $source = TheCore_Collectivity_Transports_Meta::DATA_SOURCE_MANUAL ) {
		return array(
			'available'     => false,
			'source'        => $source,
			'updatedAt'     => '',
			'realtime'      => array(
				'enabled'           => false,
				'format'            => 'none',
				'updatedAt'         => '',
				'available'         => false,
				'alertsAvailable'   => false,
				'vehiclesAvailable' => false,
				'status'            => '',
				'alerts'            => array(),
				'vehicles'          => array(),
			),
			'summary'       => array(),
			'availableDays' => array(),
			'stops'         => array(),
			'summaryText'   => '',
			'routeMeta'     => array(),
			'routeGeoJson'  => null,
		);
	}

	/**
	 * Determine whether a transport post matches the active GTFS provider.
	 *
	 * @param int    $post_id             Post id.
	 * @param string $active_provider_key Active provider key.
	 * @return bool
	 */
	private function is_gtfs_provider_match( $post_id, $active_provider_key ) {
		$active_provider_key = sanitize_key( (string) $active_provider_key );
		if ( '' === $active_provider_key ) {
			$active_provider_key = $this->get_default_provider_key();
		}

		$post_provider_key = sanitize_key( (string) get_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_GTFS_PROVIDER_KEY, true ) );
		if ( '' === $post_provider_key ) {
			return false;
		}

		return $post_provider_key === $active_provider_key;
	}

	/**
	 * Normalize status option storage to a provider-keyed map.
	 *
	 * @param mixed $raw_status Raw option value.
	 * @return array
	 */
	private function normalize_status_map( $raw_status ) {
		if ( ! is_array( $raw_status ) || empty( $raw_status ) ) {
			return array();
		}

		if ( isset( $raw_status['status'] ) || isset( $raw_status['completed_at'] ) || isset( $raw_status['started_at'] ) ) {
			$key = $this->get_default_provider_key();
			return array(
				$key => $this->sanitize_status_payload( $raw_status, $key ),
			);
		}

		$normalized = array();
		foreach ( $raw_status as $provider_key => $status ) {
			if ( ! is_array( $status ) ) {
				continue;
			}

			$key                = sanitize_key( (string) $provider_key );
			$key                = '' !== $key ? $key : $this->get_default_provider_key();
			$normalized[ $key ] = $this->sanitize_status_payload( $status, $key );
		}

		return $normalized;
	}

	/**
	 * Sanitize one status payload.
	 *
	 * @param array  $status       Raw payload.
	 * @param string $provider_key Provider key.
	 * @return array
	 */
	private function sanitize_status_payload( array $status, $provider_key ) {
		$status['providerKey'] = sanitize_key( (string) $provider_key );
		return $status;
	}

	/**
	 * Normalize one GTFS source config.
	 *
	 * @param array  $config       Raw config.
	 * @param string $fallback_key Fallback provider key.
	 * @return array
	 */
	private function normalize_source_config( array $config, $fallback_key = '' ) {
		$provider_label = sanitize_text_field( (string) ( $config['provider_label'] ?? '' ) );
		$provider_key   = sanitize_key( (string) ( $config['provider_key'] ?? '' ) );

		if ( '' === $provider_label ) {
			$provider_label = self::DEFAULT_GTFS_PROVIDER_LABEL;
		}

		if ( '' === $provider_key ) {
			$provider_key = sanitize_key( remove_accents( $provider_label ) );
		}

		if ( '' === $provider_key ) {
			$provider_key = sanitize_key( (string) $fallback_key );
		}

		if ( '' === $provider_key ) {
			$provider_key = self::DEFAULT_GTFS_PROVIDER_KEY;
		}

		$gtfs_url = esc_url_raw( (string) ( $config['gtfs_url'] ?? '' ) );
		if ( '' === $gtfs_url ) {
			$gtfs_url = self::DEFAULT_GTFS_URL;
		}

		return array(
			'provider_label'   => $provider_label,
			'provider_key'     => $provider_key,
			'gtfs_url'         => $gtfs_url,
			'stop_direction_strategy' => $this->normalize_stop_direction_strategy( $config['stop_direction_strategy'] ?? self::STOP_DIRECTION_STRATEGY_GTFS ),
			'realtime_format'  => $this->normalize_realtime_format( $config['realtime_format'] ?? 'none' ),
			'trip_updates_url' => esc_url_raw( (string) ( $config['trip_updates_url'] ?? '' ) ),
			'service_alerts_url' => esc_url_raw( (string) ( $config['service_alerts_url'] ?? '' ) ),
			'vehicle_positions_url' => esc_url_raw( (string) ( $config['vehicle_positions_url'] ?? '' ) ),
			'locality_name'    => sanitize_text_field( (string) ( $config['locality_name'] ?? '' ) ),
			'locality_insee'   => sanitize_text_field( (string) ( $config['locality_insee'] ?? '' ) ),
			'extra_localities' => $this->parse_meta_list( $config['extra_localities'] ?? array() ),
			'extra_route_ids'  => $this->parse_meta_list( $config['extra_route_ids'] ?? array() ),
			'extra_stop_ids'   => $this->parse_meta_list( $config['extra_stop_ids'] ?? array() ),
			'is_enabled'       => ! isset( $config['is_enabled'] ) || ! empty( $config['is_enabled'] ),
			'realtime_is_enabled' => ! empty( $config['realtime_is_enabled'] ),
		);
	}

	/**
	 * Keep the legacy single-source option aligned with the first configured source.
	 *
	 * @param array $sources Sanitized sources.
	 * @return void
	 */
	private function sync_legacy_gtfs_source_option( array $sources ) {
		$first = reset( $sources );
		if ( ! is_array( $first ) ) {
			delete_option( self::OPTION_GTFS_SOURCE_CONFIG );
			return;
		}

		update_option(
			self::OPTION_GTFS_SOURCE_CONFIG,
			array(
				'provider_label'   => $first['provider_label'],
				'provider_key'     => $first['provider_key'],
				'gtfs_url'         => $first['gtfs_url'],
				'stop_direction_strategy' => $first['stop_direction_strategy'],
				'realtime_format'  => $first['realtime_format'],
				'trip_updates_url' => $first['trip_updates_url'],
				'service_alerts_url' => $first['service_alerts_url'],
				'vehicle_positions_url' => $first['vehicle_positions_url'],
				'locality_name'    => $first['locality_name'],
				'locality_insee'   => $first['locality_insee'],
				'extra_localities' => $first['extra_localities'],
				'extra_route_ids'  => $first['extra_route_ids'],
				'extra_stop_ids'   => $first['extra_stop_ids'],
				'realtime_is_enabled' => $first['realtime_is_enabled'],
			),
			false
		);
	}

	/**
	 * Resolve one provider key.
	 *
	 * @param string $provider_key Raw provider key.
	 * @return string
	 */
	private function resolve_provider_key( $provider_key ) {
		$key = sanitize_key( (string) $provider_key );
		return '' !== $key ? $key : $this->get_default_provider_key();
	}

	/**
	 * Determine whether one source row contains actual content.
	 *
	 * @param array $source Raw source row.
	 * @return bool
	 */
	private function source_has_content( array $source ) {
		$fields = array(
			(string) ( $source['provider_label'] ?? '' ),
			(string) ( $source['provider_key'] ?? '' ),
			(string) ( $source['gtfs_url'] ?? '' ),
			(string) ( $source['realtime_format'] ?? '' ),
			(string) ( $source['trip_updates_url'] ?? '' ),
			(string) ( $source['service_alerts_url'] ?? '' ),
			(string) ( $source['vehicle_positions_url'] ?? '' ),
			(string) ( $source['locality_name'] ?? '' ),
			(string) ( $source['locality_insee'] ?? '' ),
			is_array( $source['extra_localities'] ?? null ) ? implode( "\n", $source['extra_localities'] ) : (string) ( $source['extra_localities'] ?? '' ),
			is_array( $source['extra_route_ids'] ?? null ) ? implode( "\n", $source['extra_route_ids'] ) : (string) ( $source['extra_route_ids'] ?? '' ),
			is_array( $source['extra_stop_ids'] ?? null ) ? implode( "\n", $source['extra_stop_ids'] ) : (string) ( $source['extra_stop_ids'] ?? '' ),
		);

		foreach ( $fields as $field ) {
			if ( '' !== trim( $field ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize realtime format.
	 *
	 * @param string $format Raw format.
	 * @return string
	 */
	private function normalize_realtime_format( $format ) {
		$format = sanitize_key( (string) $format );
		if ( in_array( $format, array( 'gtfs_rt', 'siri', 'siri_lite' ), true ) ) {
			return $format;
		}

		return 'none';
	}

	/**
	 * Normalize stop direction strategy.
	 *
	 * @param string $strategy Raw strategy.
	 * @return string
	 */
	private function normalize_stop_direction_strategy( $strategy ) {
		$strategy = sanitize_key( (string) $strategy );
		if ( in_array( $strategy, array( self::STOP_DIRECTION_STRATEGY_GTFS, self::STOP_DIRECTION_STRATEGY_PARSE ), true ) ) {
			return $strategy;
		}

		return self::STOP_DIRECTION_STRATEGY_GTFS;
	}

	/**
	 * Get default provider key from configured sources.
	 *
	 * @return string
	 */
	public function get_default_provider_key() {
		$sources = $this->get_gtfs_sources();
		foreach ( $sources as $source ) {
			if ( ! empty( $source['is_enabled'] ) && ! empty( $source['provider_key'] ) ) {
				return sanitize_key( (string) $source['provider_key'] );
			}
		}

		$first = reset( $sources );
		if ( is_array( $first ) && ! empty( $first['provider_key'] ) ) {
			return sanitize_key( (string) $first['provider_key'] );
		}

		return self::DEFAULT_GTFS_PROVIDER_KEY;
	}
}
