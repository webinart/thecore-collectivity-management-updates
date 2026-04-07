<?php
/**
 * GTFS Realtime adapter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Transports_GTFS_Realtime_Adapter implements TheCore_Collectivity_Transports_Realtime_Adapter_Interface {
	/**
	 * Get supported format key.
	 *
	 * @return string
	 */
	public function get_format_key() {
		return 'gtfs_rt';
	}

	/**
	 * Import one GTFS-RT source into canonical realtime arrays.
	 *
	 * @param array $source_config Source configuration.
	 * @return array|WP_Error
	 */
	public function import( array $source_config ) {
		$trip_updates_url      = ! empty( $source_config['trip_updates_url'] ) ? esc_url_raw( (string) $source_config['trip_updates_url'] ) : '';
		$service_alerts_url    = ! empty( $source_config['service_alerts_url'] ) ? esc_url_raw( (string) $source_config['service_alerts_url'] ) : '';
		$vehicle_positions_url = ! empty( $source_config['vehicle_positions_url'] ) ? esc_url_raw( (string) $source_config['vehicle_positions_url'] ) : '';

		if ( '' === $trip_updates_url && '' === $service_alerts_url && '' === $vehicle_positions_url ) {
			return new WP_Error( 'bellevue_transport_realtime_url_missing', __( 'Aucune URL temps réel n est configurée pour cette source.', 'bellevue' ) );
		}

		$result = array(
			'format'         => 'gtfs_rt',
			'feedTimestamps' => array(
				'tripUpdates'      => 0,
				'serviceAlerts'    => 0,
				'vehiclePositions' => 0,
			),
			'counts'         => array(
				'tripUpdateEntities' => 0,
				'tripUpdates'        => 0,
				'predictions'        => 0,
				'alertEntities'      => 0,
				'alerts'             => 0,
				'vehicleEntities'    => 0,
				'vehicles'           => 0,
			),
			'predictions'    => array(),
			'alerts'         => array(),
			'vehicles'       => array(),
		);

		if ( '' !== $trip_updates_url ) {
			$trip_updates = $this->parse_trip_updates( $this->fetch_binary_feed( $trip_updates_url ) );
			if ( is_wp_error( $trip_updates ) ) {
				return $trip_updates;
			}

			$result['feedTimestamps']['tripUpdates'] = intval( $trip_updates['feed_timestamp'] ?? 0 );
			$result['counts']['tripUpdateEntities']  = intval( $trip_updates['entity_count'] ?? 0 );
			$result['counts']['tripUpdates']         = intval( $trip_updates['trip_update_count'] ?? 0 );
			$result['counts']['predictions']         = intval( $trip_updates['prediction_count'] ?? 0 );
			$result['predictions']                   = (array) $trip_updates['predictions'];
		}

		if ( '' !== $service_alerts_url ) {
			$service_alerts = $this->parse_service_alerts( $this->fetch_binary_feed( $service_alerts_url ) );
			if ( is_wp_error( $service_alerts ) ) {
				return $service_alerts;
			}

			$result['feedTimestamps']['serviceAlerts'] = intval( $service_alerts['feed_timestamp'] ?? 0 );
			$result['counts']['alertEntities']         = intval( $service_alerts['entity_count'] ?? 0 );
			$result['counts']['alerts']                = intval( $service_alerts['alert_count'] ?? 0 );
			$result['alerts']                          = (array) $service_alerts['alerts'];
		}

		if ( '' !== $vehicle_positions_url ) {
			$vehicle_positions = $this->parse_vehicle_positions( $this->fetch_binary_feed( $vehicle_positions_url ) );
			if ( is_wp_error( $vehicle_positions ) ) {
				return $vehicle_positions;
			}

			$result['feedTimestamps']['vehiclePositions'] = intval( $vehicle_positions['feed_timestamp'] ?? 0 );
			$result['counts']['vehicleEntities']          = intval( $vehicle_positions['entity_count'] ?? 0 );
			$result['counts']['vehicles']                 = intval( $vehicle_positions['vehicle_count'] ?? 0 );
			$result['vehicles']                           = (array) $vehicle_positions['vehicles'];
		}

		return $result;
	}

	/**
	 * Parse GTFS-RT TripUpdates feed.
	 *
	 * @param string|WP_Error $binary Binary GTFS-RT payload.
	 * @return array|WP_Error
	 */
	public function parse_trip_updates( $binary ) {
		if ( is_wp_error( $binary ) ) {
			return $binary;
		}

		$feed = $this->parse_feed_message( $binary );
		if ( is_wp_error( $feed ) ) {
			return $feed;
		}

		$feed_timestamp    = $this->extract_feed_timestamp( $feed );
		$predictions       = array();
		$entity_count      = 0;
		$trip_update_count = 0;

		foreach ( (array) $feed->getEntityList() as $entity ) {
			++$entity_count;
			if ( ! $entity->hasTripUpdate() ) {
				continue;
			}

			++$trip_update_count;
			$trip_update = $entity->getTripUpdate();
			$trip        = $trip_update->hasTrip() ? $trip_update->getTrip() : null;
			if ( ! $trip ) {
				continue;
			}

			$trip_id                = $trip->hasTripId() ? sanitize_text_field( $trip->getTripId() ) : '';
			$route_id               = $trip->hasRouteId() ? sanitize_text_field( $trip->getRouteId() ) : '';
			$service_date           = $trip->hasStartDate() ? preg_replace( '/[^0-9]/', '', (string) $trip->getStartDate() ) : '';
			$trip_schedule_relation = $trip->hasScheduleRelationship() ? intval( $trip->getScheduleRelationship() ) : 0;
			$trip_delay             = $trip_update->hasDelay() ? intval( $trip_update->getDelay() ) : null;

			foreach ( (array) $trip_update->getStopTimeUpdateList() as $stop_update ) {
				$stop_id           = $stop_update->hasStopId() ? sanitize_text_field( $stop_update->getStopId() ) : '';
				$stop_sequence     = $stop_update->hasStopSequence() ? intval( $stop_update->getStopSequence() ) : 0;
				$schedule_relation = $stop_update->hasScheduleRelationship() ? intval( $stop_update->getScheduleRelationship() ) : 0;
				$arrival           = $stop_update->hasArrival() ? $stop_update->getArrival() : null;
				$departure         = $stop_update->hasDeparture() ? $stop_update->getDeparture() : null;

				if ( '' === $trip_id || '' === $stop_id ) {
					continue;
				}

				$predictions[] = array(
					'entity_id'             => $entity->hasId() ? sanitize_text_field( $entity->getId() ) : '',
					'trip_id'               => $trip_id,
					'route_id'              => $route_id,
					'stop_id'               => $stop_id,
					'stop_sequence'         => $stop_sequence,
					'service_date'          => $service_date,
					'headsign'              => '',
					'schedule_relationship' => $this->map_prediction_relationship( $schedule_relation, $trip_schedule_relation ),
					'arrival_timestamp'     => $this->extract_event_timestamp( $arrival ),
					'departure_timestamp'   => $this->extract_event_timestamp( $departure ),
					'arrival_delay'         => $this->extract_event_delay( $arrival ),
					'departure_delay'       => $this->extract_event_delay( $departure ),
					'trip_delay'            => $trip_delay,
					'feed_timestamp'        => $feed_timestamp,
				);
			}
		}

		return array(
			'feed_timestamp'    => $feed_timestamp,
			'entity_count'      => $entity_count,
			'trip_update_count' => $trip_update_count,
			'prediction_count'  => count( $predictions ),
			'predictions'       => $predictions,
		);
	}

	/**
	 * Parse GTFS-RT ServiceAlerts feed.
	 *
	 * @param string|WP_Error $binary Binary GTFS-RT payload.
	 * @return array|WP_Error
	 */
	public function parse_service_alerts( $binary ) {
		if ( is_wp_error( $binary ) ) {
			return $binary;
		}

		$feed = $this->parse_feed_message( $binary );
		if ( is_wp_error( $feed ) ) {
			return $feed;
		}

		$feed_timestamp = $this->extract_feed_timestamp( $feed );
		$alerts         = array();
		$entity_count   = 0;
		$alert_count    = 0;

		foreach ( (array) $feed->getEntityList() as $entity ) {
			++$entity_count;
			if ( ! $entity->hasAlert() ) {
				continue;
			}

			++$alert_count;
			$alert          = $entity->getAlert();
			$scope          = $this->extract_alert_scope( $alert );
			$active_periods = $this->extract_alert_active_periods( $alert );
			$window         = $this->summarize_active_periods( $active_periods );
			$effect_value   = $alert->hasEffect() ? intval( $alert->getEffect() ) : 0;

			$alerts[] = array(
				'alert_id'         => $entity->hasId() ? sanitize_text_field( $entity->getId() ) : md5( wp_json_encode( $scope ) . '|' . $feed_timestamp ),
				'header_text'      => $this->extract_translated_string( $alert->hasHeaderText() ? $alert->getHeaderText() : null ),
				'description_text' => $this->extract_translated_string( $alert->hasDescriptionText() ? $alert->getDescriptionText() : null ),
				'url'              => $this->extract_translated_string( $alert->hasUrl() ? $alert->getUrl() : null ),
				'cause'            => $alert->hasCause() ? $this->map_alert_cause( intval( $alert->getCause() ) ) : '',
				'effect'           => $alert->hasEffect() ? $this->map_alert_effect( $effect_value ) : '',
				'severity'         => $this->map_alert_severity( $effect_value ),
				'route_ids'        => $scope['route_ids'],
				'stop_ids'         => $scope['stop_ids'],
				'trip_ids'         => $scope['trip_ids'],
				'agency_ids'       => $scope['agency_ids'],
				'route_types'      => $scope['route_types'],
				'start_timestamp'  => $window['start'],
				'end_timestamp'    => $window['end'],
				'feed_timestamp'   => $feed_timestamp,
			);
		}

		return array(
			'feed_timestamp' => $feed_timestamp,
			'entity_count'   => $entity_count,
			'alert_count'    => $alert_count,
			'alerts'         => $alerts,
		);
	}

	/**
	 * Parse GTFS-RT VehiclePositions feed.
	 *
	 * @param string|WP_Error $binary Binary GTFS-RT payload.
	 * @return array|WP_Error
	 */
	public function parse_vehicle_positions( $binary ) {
		if ( is_wp_error( $binary ) ) {
			return $binary;
		}

		$feed = $this->parse_feed_message( $binary );
		if ( is_wp_error( $feed ) ) {
			return $feed;
		}

		$feed_timestamp = $this->extract_feed_timestamp( $feed );
		$vehicles       = array();
		$entity_count   = 0;
		$vehicle_count  = 0;

		foreach ( (array) $feed->getEntityList() as $entity ) {
			++$entity_count;
			if ( ! $entity->hasVehicle() ) {
				continue;
			}

			++$vehicle_count;
			$vehicle = $entity->getVehicle();
			$trip    = $vehicle->hasTrip() ? $vehicle->getTrip() : null;
			$desc    = $vehicle->hasVehicle() ? $vehicle->getVehicle() : null;
			$pos     = $vehicle->hasPosition() ? $vehicle->getPosition() : null;

			$vehicles[] = array(
				'entity_id'             => $entity->hasId() ? sanitize_text_field( $entity->getId() ) : '',
				'vehicle_id'            => $desc && $desc->hasId() ? sanitize_text_field( $desc->getId() ) : '',
				'vehicle_label'         => $desc && $desc->hasLabel() ? sanitize_text_field( $desc->getLabel() ) : '',
				'license_plate'         => $desc && method_exists( $desc, 'hasLicensePlate' ) && $desc->hasLicensePlate() ? sanitize_text_field( $desc->getLicensePlate() ) : '',
				'trip_id'               => $trip && $trip->hasTripId() ? sanitize_text_field( $trip->getTripId() ) : '',
				'route_id'              => $trip && $trip->hasRouteId() ? sanitize_text_field( $trip->getRouteId() ) : '',
				'stop_id'               => $vehicle->hasStopId() ? sanitize_text_field( $vehicle->getStopId() ) : '',
				'current_stop_sequence' => $vehicle->hasCurrentStopSequence() ? intval( $vehicle->getCurrentStopSequence() ) : 0,
				'current_status'        => $vehicle->hasCurrentStatus() ? $this->map_vehicle_status( intval( $vehicle->getCurrentStatus() ) ) : '',
				'latitude'              => $pos && $pos->hasLatitude() ? (float) $pos->getLatitude() : null,
				'longitude'             => $pos && $pos->hasLongitude() ? (float) $pos->getLongitude() : null,
				'bearing'               => $pos && $pos->hasBearing() ? (float) $pos->getBearing() : null,
				'speed'                 => $pos && $pos->hasSpeed() ? (float) $pos->getSpeed() : null,
				'congestion_level'      => $vehicle->hasCongestionLevel() ? $this->map_vehicle_congestion( intval( $vehicle->getCongestionLevel() ) ) : '',
				'occupancy_status'      => $vehicle->hasOccupancyStatus() ? $this->map_vehicle_occupancy( intval( $vehicle->getOccupancyStatus() ) ) : '',
				'vehicle_timestamp'     => $vehicle->hasTimestamp() ? intval( $vehicle->getTimestamp() ) : null,
				'feed_timestamp'        => $feed_timestamp,
			);
		}

		return array(
			'feed_timestamp' => $feed_timestamp,
			'entity_count'   => $entity_count,
			'vehicle_count'  => $vehicle_count,
			'vehicles'       => $vehicles,
		);
	}

	/**
	 * Fetch one GTFS-RT feed over HTTP.
	 *
	 * @param string $url Feed URL.
	 * @return string|WP_Error
	 */
	private function fetch_binary_feed( $url ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url ) {
			return new WP_Error( 'bellevue_transport_realtime_url_missing', __( 'L URL du flux temps réel est vide.', 'bellevue' ) );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 30,
				'redirection' => 5,
				'user-agent'  => 'TheCore-Collectivity-Management/' . THECORE_COLLECTIVITY_MANAGEMENT_VERSION . '; ' . home_url( '/' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = intval( wp_remote_retrieve_response_code( $response ) );
		$body = (string) wp_remote_retrieve_body( $response );
		if ( $code < 200 || $code >= 300 || '' === $body ) {
			return new WP_Error(
				'bellevue_transport_realtime_http_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Le flux temps réel a repondu avec le code HTTP %d.', 'bellevue' ),
					$code
				)
			);
		}

		return $body;
	}

	/**
	 * Parse one binary feed message while silencing known protobuf deprecations.
	 *
	 * @param string $binary Binary payload.
	 * @return transit_realtime\FeedMessage|WP_Error
	 */
	private function parse_feed_message( $binary ) {
		if ( ! class_exists( 'transit_realtime\\FeedMessage' ) ) {
			return new WP_Error( 'bellevue_transport_gtfs_rt_missing_runtime', __( 'Le runtime GTFS-RT n est pas disponible. Verifiez l autoload du plugin.', 'bellevue' ) );
		}

		$binary = (string) $binary;
		if ( '' === $binary ) {
			return new WP_Error( 'bellevue_transport_gtfs_rt_empty', __( 'Le flux GTFS-RT est vide.', 'bellevue' ) );
		}

		$previous_reporting = error_reporting();
		error_reporting( $previous_reporting & ~E_DEPRECATED & ~E_USER_DEPRECATED );

		try {
			$feed = new transit_realtime\FeedMessage();
			$feed->parse( $binary );
		} catch ( Exception $exception ) {
			error_reporting( $previous_reporting );
			return new WP_Error( 'bellevue_transport_gtfs_rt_parse_error', $exception->getMessage() );
		} catch ( Error $error ) {
			error_reporting( $previous_reporting );
			return new WP_Error( 'bellevue_transport_gtfs_rt_parse_error', $error->getMessage() );
		}

		error_reporting( $previous_reporting );

		return $feed;
	}

	/**
	 * Extract one feed timestamp.
	 *
	 * @param transit_realtime\FeedMessage $feed Feed message.
	 * @return int
	 */
	private function extract_feed_timestamp( $feed ) {
		if ( $feed->hasHeader() ) {
			$header = $feed->getHeader();
			if ( $header && $header->hasTimestamp() ) {
				return intval( $header->getTimestamp() );
			}
		}

		return 0;
	}

	/**
	 * Extract translated string.
	 *
	 * @param mixed $translated TranslatedString message.
	 * @return string
	 */
	private function extract_translated_string( $translated ) {
		if ( ! $translated || ! method_exists( $translated, 'getTranslationList' ) ) {
			return '';
		}

		foreach ( (array) $translated->getTranslationList() as $translation ) {
			if ( $translation && method_exists( $translation, 'hasText' ) && $translation->hasText() ) {
				return sanitize_text_field( (string) $translation->getText() );
			}
		}

		return '';
	}

	/**
	 * Extract GTFS-RT stop-time event timestamp.
	 *
	 * @param mixed $event StopTimeEvent message.
	 * @return int|null
	 */
	private function extract_event_timestamp( $event ) {
		if ( ! $event || ! method_exists( $event, 'hasTime' ) || ! $event->hasTime() ) {
			return null;
		}

		return intval( $event->getTime() );
	}

	/**
	 * Extract GTFS-RT stop-time event delay.
	 *
	 * @param mixed $event StopTimeEvent message.
	 * @return int|null
	 */
	private function extract_event_delay( $event ) {
		if ( ! $event || ! method_exists( $event, 'hasDelay' ) || ! $event->hasDelay() ) {
			return null;
		}

		return intval( $event->getDelay() );
	}

	/**
	 * Extract alert scope arrays.
	 *
	 * @param mixed $alert Alert message.
	 * @return array
	 */
	private function extract_alert_scope( $alert ) {
		$scope = array(
			'route_ids'   => array(),
			'stop_ids'    => array(),
			'trip_ids'    => array(),
			'agency_ids'  => array(),
			'route_types' => array(),
		);

		if ( ! $alert || ! $alert->hasInformedEntity() ) {
			return $scope;
		}

		foreach ( (array) $alert->getInformedEntityList() as $entity ) {
			if ( ! $entity ) {
				continue;
			}

			if ( $entity->hasAgencyId() ) {
				$scope['agency_ids'][] = sanitize_text_field( $entity->getAgencyId() );
			}
			if ( $entity->hasRouteId() ) {
				$scope['route_ids'][] = sanitize_text_field( $entity->getRouteId() );
			}
			if ( $entity->hasRouteType() ) {
				$scope['route_types'][] = intval( $entity->getRouteType() );
			}
			if ( $entity->hasStopId() ) {
				$scope['stop_ids'][] = sanitize_text_field( $entity->getStopId() );
			}
			if ( $entity->hasTrip() ) {
				$trip = $entity->getTrip();
				if ( $trip && $trip->hasTripId() ) {
					$scope['trip_ids'][] = sanitize_text_field( $trip->getTripId() );
				}
				if ( $trip && $trip->hasRouteId() ) {
					$scope['route_ids'][] = sanitize_text_field( $trip->getRouteId() );
				}
			}
		}

		$scope['route_ids']   = array_values( array_unique( array_filter( $scope['route_ids'], 'strlen' ) ) );
		$scope['stop_ids']    = array_values( array_unique( array_filter( $scope['stop_ids'], 'strlen' ) ) );
		$scope['trip_ids']    = array_values( array_unique( array_filter( $scope['trip_ids'], 'strlen' ) ) );
		$scope['agency_ids']  = array_values( array_unique( array_filter( $scope['agency_ids'], 'strlen' ) ) );
		$scope['route_types'] = array_values( array_unique( array_filter( array_map( 'intval', $scope['route_types'] ) ) ) );

		return $scope;
	}

	/**
	 * Extract alert active periods.
	 *
	 * @param mixed $alert Alert message.
	 * @return array<int,array{start:int|null,end:int|null}>
	 */
	private function extract_alert_active_periods( $alert ) {
		if ( ! $alert || ! $alert->hasActivePeriod() ) {
			return array();
		}

		$periods = array();
		foreach ( (array) $alert->getActivePeriodList() as $period ) {
			if ( ! $period ) {
				continue;
			}

			$periods[] = array(
				'start' => method_exists( $period, 'hasStart' ) && $period->hasStart() ? intval( $period->getStart() ) : null,
				'end'   => method_exists( $period, 'hasEnd' ) && $period->hasEnd() ? intval( $period->getEnd() ) : null,
			);
		}

		return $periods;
	}

	/**
	 * Summarize alert active periods into one time window.
	 *
	 * @param array $periods Parsed periods.
	 * @return array{start:int|null,end:int|null}
	 */
	private function summarize_active_periods( array $periods ) {
		$start = null;
		$end   = null;

		foreach ( $periods as $period ) {
			if ( ! empty( $period['start'] ) ) {
				$start = null === $start ? intval( $period['start'] ) : min( $start, intval( $period['start'] ) );
			}

			if ( ! empty( $period['end'] ) ) {
				$end = null === $end ? intval( $period['end'] ) : max( $end, intval( $period['end'] ) );
			}
		}

		return array(
			'start' => $start,
			'end'   => $end,
		);
	}

	/**
	 * Map prediction relationship to internal slug.
	 *
	 * @param int $stop_relationship Stop relationship.
	 * @param int $trip_relationship Trip relationship.
	 * @return string
	 */
	private function map_prediction_relationship( $stop_relationship, $trip_relationship ) {
		if ( 3 === intval( $trip_relationship ) ) {
			return 'cancelled';
		}

		switch ( intval( $stop_relationship ) ) {
			case 1:
				return 'skipped';
			case 2:
				return 'no_data';
			default:
				return 'scheduled';
		}
	}

	/**
	 * Map alert cause enum.
	 *
	 * @param int $value Cause enum.
	 * @return string
	 */
	private function map_alert_cause( $value ) {
		switch ( intval( $value ) ) {
			case transit_realtime\Alert\Cause::OTHER_CAUSE:
				return 'other';
			case transit_realtime\Alert\Cause::TECHNICAL_PROBLEM:
				return 'technical_problem';
			case transit_realtime\Alert\Cause::STRIKE:
				return 'strike';
			case transit_realtime\Alert\Cause::DEMONSTRATION:
				return 'demonstration';
			case transit_realtime\Alert\Cause::ACCIDENT:
				return 'accident';
			case transit_realtime\Alert\Cause::HOLIDAY:
				return 'holiday';
			case transit_realtime\Alert\Cause::WEATHER:
				return 'weather';
			case transit_realtime\Alert\Cause::MAINTENANCE:
				return 'maintenance';
			case transit_realtime\Alert\Cause::CONSTRUCTION:
				return 'construction';
			case transit_realtime\Alert\Cause::POLICE_ACTIVITY:
				return 'police_activity';
			case transit_realtime\Alert\Cause::MEDICAL_EMERGENCY:
				return 'medical_emergency';
			default:
				return 'unknown';
		}
	}

	/**
	 * Map alert effect enum.
	 *
	 * @param int $value Effect enum.
	 * @return string
	 */
	private function map_alert_effect( $value ) {
		switch ( intval( $value ) ) {
			case transit_realtime\Alert\Effect::NO_SERVICE:
				return 'no_service';
			case transit_realtime\Alert\Effect::REDUCED_SERVICE:
				return 'reduced_service';
			case transit_realtime\Alert\Effect::SIGNIFICANT_DELAYS:
				return 'significant_delays';
			case transit_realtime\Alert\Effect::DETOUR:
				return 'detour';
			case transit_realtime\Alert\Effect::ADDITIONAL_SERVICE:
				return 'additional_service';
			case transit_realtime\Alert\Effect::MODIFIED_SERVICE:
				return 'modified_service';
			case transit_realtime\Alert\Effect::OTHER_EFFECT:
				return 'other_effect';
			case transit_realtime\Alert\Effect::STOP_MOVED:
				return 'stop_moved';
			default:
				return 'unknown';
		}
	}

	/**
	 * Map alert effect to a coarse severity.
	 *
	 * @param int $value Effect enum.
	 * @return string
	 */
	private function map_alert_severity( $value ) {
		switch ( intval( $value ) ) {
			case transit_realtime\Alert\Effect::NO_SERVICE:
				return 'critical';
			case transit_realtime\Alert\Effect::SIGNIFICANT_DELAYS:
			case transit_realtime\Alert\Effect::DETOUR:
			case transit_realtime\Alert\Effect::STOP_MOVED:
				return 'warning';
			case transit_realtime\Alert\Effect::REDUCED_SERVICE:
			case transit_realtime\Alert\Effect::MODIFIED_SERVICE:
				return 'warning';
			default:
				return 'info';
		}
	}

	/**
	 * Map vehicle status enum.
	 *
	 * @param int $value Vehicle status enum.
	 * @return string
	 */
	private function map_vehicle_status( $value ) {
		switch ( intval( $value ) ) {
			case transit_realtime\VehiclePosition\VehicleStopStatus::INCOMING_AT:
				return 'incoming_at';
			case transit_realtime\VehiclePosition\VehicleStopStatus::STOPPED_AT:
				return 'stopped_at';
			case transit_realtime\VehiclePosition\VehicleStopStatus::IN_TRANSIT_TO:
			default:
				return 'in_transit_to';
		}
	}

	/**
	 * Map vehicle congestion enum.
	 *
	 * @param int $value Congestion enum.
	 * @return string
	 */
	private function map_vehicle_congestion( $value ) {
		switch ( intval( $value ) ) {
			case transit_realtime\VehiclePosition\CongestionLevel::RUNNING_SMOOTHLY:
				return 'running_smoothly';
			case transit_realtime\VehiclePosition\CongestionLevel::STOP_AND_GO:
				return 'stop_and_go';
			case transit_realtime\VehiclePosition\CongestionLevel::CONGESTION:
				return 'congestion';
			case transit_realtime\VehiclePosition\CongestionLevel::SEVERE_CONGESTION:
				return 'severe_congestion';
			default:
				return '';
		}
	}

	/**
	 * Map vehicle occupancy enum.
	 *
	 * @param int $value Occupancy enum.
	 * @return string
	 */
	private function map_vehicle_occupancy( $value ) {
		switch ( intval( $value ) ) {
			case transit_realtime\VehiclePosition\OccupancyStatus::EMPTY0:
				return 'empty';
			case transit_realtime\VehiclePosition\OccupancyStatus::MANY_SEATS_AVAILABLE:
				return 'many_seats_available';
			case transit_realtime\VehiclePosition\OccupancyStatus::FEW_SEATS_AVAILABLE:
				return 'few_seats_available';
			case transit_realtime\VehiclePosition\OccupancyStatus::STANDING_ROOM_ONLY:
				return 'standing_room_only';
			case transit_realtime\VehiclePosition\OccupancyStatus::CRUSHED_STANDING_ROOM_ONLY:
				return 'crushed_standing_room_only';
			case transit_realtime\VehiclePosition\OccupancyStatus::FULL:
				return 'full';
			case transit_realtime\VehiclePosition\OccupancyStatus::NOT_ACCEPTING_PASSENGERS:
				return 'not_accepting_passengers';
			default:
				return '';
		}
	}
}
