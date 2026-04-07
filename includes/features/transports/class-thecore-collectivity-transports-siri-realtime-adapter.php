<?php
/**
 * SIRI / SIRI-Lite realtime adapter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TheCore_Collectivity_Transports_SIRI_Realtime_Adapter implements TheCore_Collectivity_Transports_Realtime_Adapter_Interface {
	/**
	 * Get supported format key.
	 *
	 * @return string
	 */
	public function get_format_key() {
		return 'siri';
	}

	/**
	 * Parse one SIRI realtime payload into canonical predictions.
	 *
	 * @param string $payload Raw payload.
	 * @return array|WP_Error
	 */
	public function parse_trip_updates( $payload ) {
		$document = $this->parse_document( $payload );
		if ( is_wp_error( $document ) ) {
			return $document;
		}

		$visits         = array_merge(
			$this->find_nodes_by_name( $document, 'MonitoredStopVisit' ),
			$this->find_nodes_by_name( $document, 'VehicleActivity' )
		);
		$predictions    = array();
		$response_stamp = $this->extract_document_timestamp( $document );

		foreach ( $visits as $visit ) {
			$journey = $this->find_first_child_by_name( $visit, 'MonitoredVehicleJourney' );
			if ( ! is_array( $journey ) ) {
				$journey = $visit;
			}

			$stop_id           = $this->extract_scalar( $journey, array( 'MonitoredCall.StopPointRef', 'StopPointRef', 'MonitoringRef' ) );
			$route_id          = $this->extract_scalar( $journey, array( 'LineRef' ) );
			$trip_id           = $this->extract_scalar( $journey, array( 'FramedVehicleJourneyRef.DatedVehicleJourneyRef', 'DatedVehicleJourneyRef', 'VehicleJourneyRef' ) );
			$headsign          = $this->extract_scalar( $journey, array( 'DestinationName', 'DirectionName', 'PublishedLineName' ) );
			$sequence          = $this->extract_scalar( $journey, array( 'MonitoredCall.Order', 'Order' ) );
			$expected_departure = $this->extract_datetime( $journey, array( 'MonitoredCall.ExpectedDepartureTime', 'MonitoredCall.ExpectedArrivalTime', 'ExpectedDepartureTime', 'ExpectedArrivalTime' ) );
			$aimed_departure   = $this->extract_datetime( $journey, array( 'MonitoredCall.AimedDepartureTime', 'MonitoredCall.AimedArrivalTime', 'AimedDepartureTime', 'AimedArrivalTime' ) );

			if ( '' === $stop_id || '' === $route_id || ( null === $expected_departure && null === $aimed_departure ) ) {
				continue;
			}

			$scheduled_ts = null !== $aimed_departure ? $aimed_departure : $expected_departure;
			$realtime_ts  = null !== $expected_departure ? $expected_departure : $aimed_departure;
			$delay_secs   = ( null !== $expected_departure && null !== $aimed_departure ) ? intval( $expected_departure - $aimed_departure ) : null;
			$reference_ts = null !== $realtime_ts ? $realtime_ts : $scheduled_ts;

			$predictions[] = array(
				'entity_id'             => $this->extract_scalar( $visit, array( 'ItemIdentifier', 'RecordedAtTime' ) ),
				'trip_id'               => $trip_id,
				'route_id'              => $route_id,
				'stop_id'               => $stop_id,
				'stop_sequence'         => is_numeric( $sequence ) ? intval( $sequence ) : 0,
				'service_date'          => null !== $reference_ts ? gmdate( 'Ymd', $reference_ts ) : '',
				'headsign'              => $headsign,
				'schedule_relationship' => null !== $expected_departure ? 'realtime' : 'scheduled',
				'arrival_timestamp'     => $this->extract_datetime( $journey, array( 'MonitoredCall.ExpectedArrivalTime' ) ),
				'departure_timestamp'   => $realtime_ts,
				'arrival_delay'         => null,
				'departure_delay'       => $delay_secs,
				'trip_delay'            => $delay_secs,
				'feed_timestamp'        => $response_stamp,
			);
		}

		return array(
			'feed_timestamp'    => $response_stamp,
			'entity_count'      => count( $visits ),
			'trip_update_count' => count( $predictions ),
			'prediction_count'  => count( $predictions ),
			'predictions'       => $predictions,
		);
	}

	/**
	 * Parse one SIRI payload into canonical service alerts.
	 *
	 * @param string $payload Raw payload.
	 * @return array|WP_Error
	 */
	public function parse_service_alerts( $payload ) {
		$document = $this->parse_document( $payload );
		if ( is_wp_error( $document ) ) {
			return $document;
		}

		$nodes          = array_merge(
			$this->find_nodes_by_name( $document, 'PtSituationElement' ),
			$this->find_nodes_by_name( $document, 'InfoMessage' )
		);
		$response_stamp = $this->extract_document_timestamp( $document );
		$alerts         = array();

		foreach ( $nodes as $node ) {
			$route_ids = $this->collect_scalar_values( $node, array( 'LineRef' ) );
			$stop_ids  = $this->collect_scalar_values( $node, array( 'StopPointRef', 'MonitoringRef' ) );
			$trip_ids  = $this->collect_scalar_values( $node, array( 'DatedVehicleJourneyRef', 'VehicleJourneyRef' ) );
			$starts_at = $this->extract_datetime( $node, array( 'ValidityPeriod.StartTime', 'PublicationWindow.StartTime', 'StartTime' ) );
			$ends_at   = $this->extract_datetime( $node, array( 'ValidityPeriod.EndTime', 'PublicationWindow.EndTime', 'EndTime', 'ValidUntilTime' ) );

			$alerts[] = array(
				'alert_id'         => $this->extract_scalar( $node, array( 'SituationNumber', 'SituationRef', 'InfoMessageIdentifier', 'MessageIdentifier' ) ),
				'header_text'      => $this->extract_scalar( $node, array( 'Summary', 'InfoMessageRef', 'Title' ) ),
				'description_text' => $this->extract_scalar( $node, array( 'Description', 'Detail', 'Content', 'MessageText' ) ),
				'url'              => $this->extract_scalar( $node, array( 'InfoLink', 'WebLink' ) ),
				'cause'            => sanitize_key( $this->extract_scalar( $node, array( 'ReasonName', 'AlertCause', 'Cause' ) ) ),
				'effect'           => sanitize_key( $this->extract_scalar( $node, array( 'Severity', 'Effect', 'Progress' ) ) ),
				'severity'         => sanitize_key( $this->extract_scalar( $node, array( 'Severity', 'Priority' ) ) ),
				'route_ids'        => $route_ids,
				'stop_ids'         => $stop_ids,
				'trip_ids'         => $trip_ids,
				'agency_ids'       => $this->collect_scalar_values( $node, array( 'OperatorRef' ) ),
				'route_types'      => array(),
				'start_timestamp'  => $starts_at,
				'end_timestamp'    => $ends_at,
				'feed_timestamp'   => $response_stamp,
			);
		}

		$alerts = array_values(
			array_filter(
				$alerts,
				static function ( $alert ) {
					return '' !== (string) ( $alert['alert_id'] ?? '' ) || '' !== (string) ( $alert['header_text'] ?? '' ) || '' !== (string) ( $alert['description_text'] ?? '' );
				}
			)
		);

		return array(
			'feed_timestamp' => $response_stamp,
			'entity_count'   => count( $nodes ),
			'alert_count'    => count( $alerts ),
			'alerts'         => $alerts,
		);
	}

	/**
	 * Parse one SIRI payload into canonical vehicle positions.
	 *
	 * @param string $payload Raw payload.
	 * @return array|WP_Error
	 */
	public function parse_vehicle_positions( $payload ) {
		$document = $this->parse_document( $payload );
		if ( is_wp_error( $document ) ) {
			return $document;
		}

		$activities     = array_merge(
			$this->find_nodes_by_name( $document, 'VehicleActivity' ),
			$this->find_nodes_by_name( $document, 'MonitoredStopVisit' )
		);
		$response_stamp = $this->extract_document_timestamp( $document );
		$vehicles       = array();

		foreach ( $activities as $activity ) {
			$journey = $this->find_first_child_by_name( $activity, 'MonitoredVehicleJourney' );
			if ( ! is_array( $journey ) ) {
				$journey = $activity;
			}

			$lat = $this->extract_numeric( $journey, array( 'VehicleLocation.Latitude', 'Latitude' ) );
			$lng = $this->extract_numeric( $journey, array( 'VehicleLocation.Longitude', 'Longitude' ) );
			if ( null === $lat || null === $lng ) {
				continue;
			}

			$vehicles[] = array(
				'entity_id'             => $this->extract_scalar( $activity, array( 'ItemIdentifier', 'RecordedAtTime', 'VehicleRef' ) ),
				'vehicle_id'            => $this->extract_scalar( $journey, array( 'VehicleRef' ) ),
				'vehicle_label'         => $this->extract_scalar( $journey, array( 'PublishedLineName', 'JourneyName', 'VehicleJourneyName' ) ),
				'license_plate'         => '',
				'trip_id'               => $this->extract_scalar( $journey, array( 'FramedVehicleJourneyRef.DatedVehicleJourneyRef', 'DatedVehicleJourneyRef', 'VehicleJourneyRef' ) ),
				'route_id'              => $this->extract_scalar( $journey, array( 'LineRef' ) ),
				'stop_id'               => $this->extract_scalar( $journey, array( 'MonitoredCall.StopPointRef', 'StopPointRef', 'MonitoringRef' ) ),
				'current_stop_sequence' => intval( $this->extract_scalar( $journey, array( 'MonitoredCall.Order', 'Order' ) ) ?: 0 ),
				'current_status'        => ! empty( $this->extract_scalar( $journey, array( 'MonitoredCall.VehicleAtStop' ) ) ) ? 'stopped_at' : 'in_transit_to',
				'latitude'              => $lat,
				'longitude'             => $lng,
				'bearing'               => $this->extract_numeric( $journey, array( 'Bearing' ) ),
				'speed'                 => $this->extract_numeric( $journey, array( 'Velocity', 'ProgressRate' ) ),
				'congestion_level'      => sanitize_key( $this->extract_scalar( $journey, array( 'ProgressStatus' ) ) ),
				'occupancy_status'      => sanitize_key( $this->extract_scalar( $journey, array( 'Occupancy', 'OccupancyStatus' ) ) ),
				'vehicle_timestamp'     => $this->extract_datetime( $activity, array( 'RecordedAtTime', 'ResponseTimestamp' ) ),
				'feed_timestamp'        => $response_stamp,
			);
		}

		return array(
			'feed_timestamp' => $response_stamp,
			'entity_count'   => count( $activities ),
			'vehicle_count'  => count( $vehicles ),
			'vehicles'       => $vehicles,
		);
	}

	/**
	 * Parse one raw document.
	 *
	 * @param string $payload Raw payload.
	 * @return array|WP_Error
	 */
	private function parse_document( $payload ) {
		$payload = trim( (string) $payload );
		if ( '' === $payload ) {
			return new WP_Error( 'bellevue_transport_siri_empty', __( 'Le flux SIRI est vide.', 'bellevue' ) );
		}

		$json = json_decode( $payload, true );
		if ( is_array( $json ) ) {
			return $json;
		}

		if ( ! function_exists( 'simplexml_load_string' ) ) {
			return new WP_Error( 'bellevue_transport_siri_xml_missing', __( 'L extension XML de PHP est indisponible.', 'bellevue' ) );
		}

		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $payload, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET );
		if ( false === $xml ) {
			libxml_clear_errors();
			return new WP_Error( 'bellevue_transport_siri_parse_error', __( 'Impossible d analyser le flux SIRI.', 'bellevue' ) );
		}

		$document = json_decode( wp_json_encode( $xml ), true );
		if ( ! is_array( $document ) ) {
			return new WP_Error( 'bellevue_transport_siri_parse_error', __( 'Le flux SIRI n a pas pu être normalise.', 'bellevue' ) );
		}

		return $document;
	}

	/**
	 * Find nodes by local name in a normalized array document.
	 *
	 * @param mixed  $node Current node.
	 * @param string $name Target name.
	 * @return array
	 */
	private function find_nodes_by_name( $node, $name ) {
		$results = array();
		$this->walk_nodes( $node, $name, $results );
		return $results;
	}

	/**
	 * Walk nodes recursively and collect matches.
	 *
	 * @param mixed  $node    Current node.
	 * @param string $name    Target key.
	 * @param array  $results Result accumulator.
	 * @return void
	 */
	private function walk_nodes( $node, $name, array &$results ) {
		if ( ! is_array( $node ) ) {
			return;
		}

		foreach ( $node as $key => $value ) {
			if ( $key === $name ) {
				if ( $this->is_list( $value ) ) {
					foreach ( $value as $item ) {
						if ( is_array( $item ) ) {
							$results[] = $item;
						}
					}
				} elseif ( is_array( $value ) ) {
					$results[] = $value;
				}
			}

			if ( is_array( $value ) ) {
				$this->walk_nodes( $value, $name, $results );
			}
		}
	}

	/**
	 * Find the first direct child by name.
	 *
	 * @param array  $node Node array.
	 * @param string $name Child key.
	 * @return array|null
	 */
	private function find_first_child_by_name( array $node, $name ) {
		if ( empty( $node[ $name ] ) ) {
			return null;
		}

		$value = $node[ $name ];
		if ( $this->is_list( $value ) ) {
			foreach ( $value as $item ) {
				if ( is_array( $item ) ) {
					return $item;
				}
			}
		}

		return is_array( $value ) ? $value : null;
	}

	/**
	 * Extract one scalar value from nested paths.
	 *
	 * @param array $node  Current node.
	 * @param array $paths Candidate dotted paths.
	 * @return string
	 */
	private function extract_scalar( array $node, array $paths ) {
		foreach ( $paths as $path ) {
			$value = $this->extract_path_value( $node, $path );
			if ( is_scalar( $value ) ) {
				$value = trim( (string) $value );
				if ( '' !== $value ) {
					return sanitize_text_field( $value );
				}
			}
		}

		return '';
	}

	/**
	 * Extract many scalar values from nested paths.
	 *
	 * @param array $node  Current node.
	 * @param array $paths Candidate dotted paths.
	 * @return array
	 */
	private function collect_scalar_values( array $node, array $paths ) {
		$values = array();
		foreach ( $paths as $path ) {
			foreach ( $this->extract_path_values( $node, explode( '.', $path ) ) as $value ) {
				if ( is_scalar( $value ) ) {
					$value = trim( (string) $value );
					if ( '' !== $value ) {
						$values[] = sanitize_text_field( $value );
					}
				}
			}
		}

		return array_values( array_unique( $values ) );
	}

	/**
	 * Extract one numeric value from nested paths.
	 *
	 * @param array $node  Current node.
	 * @param array $paths Candidate dotted paths.
	 * @return float|null
	 */
	private function extract_numeric( array $node, array $paths ) {
		$value = $this->extract_scalar( $node, $paths );
		if ( '' === $value || ! is_numeric( str_replace( ',', '.', $value ) ) ) {
			return null;
		}

		return (float) str_replace( ',', '.', $value );
	}

	/**
	 * Extract one datetime from nested paths.
	 *
	 * @param array $node  Current node.
	 * @param array $paths Candidate dotted paths.
	 * @return int|null
	 */
	private function extract_datetime( array $node, array $paths ) {
		$value = $this->extract_scalar( $node, $paths );
		if ( '' === $value ) {
			return null;
		}

		$timestamp = strtotime( $value );
		return false !== $timestamp ? intval( $timestamp ) : null;
	}

	/**
	 * Extract one response timestamp from the document.
	 *
	 * @param array $document Parsed document.
	 * @return int
	 */
	private function extract_document_timestamp( array $document ) {
		$ts = $this->extract_datetime( $document, array( 'ServiceDelivery.ResponseTimestamp', 'ResponseTimestamp' ) );
		return null !== $ts ? $ts : 0;
	}

	/**
	 * Extract one dotted path value.
	 *
	 * @param array  $node Current node.
	 * @param string $path Dot path.
	 * @return mixed
	 */
	private function extract_path_value( array $node, $path ) {
		$values = $this->extract_path_values( $node, explode( '.', $path ) );
		return ! empty( $values ) ? reset( $values ) : null;
	}

	/**
	 * Extract all values matching one dotted path.
	 *
	 * @param mixed $node       Current node.
	 * @param array $segments   Remaining segments.
	 * @return array
	 */
	private function extract_path_values( $node, array $segments ) {
		if ( empty( $segments ) ) {
			return array( $node );
		}

		if ( ! is_array( $node ) ) {
			return array();
		}

		$segment = array_shift( $segments );
		if ( ! array_key_exists( $segment, $node ) ) {
			$results = array();
			foreach ( $node as $child ) {
				if ( is_array( $child ) ) {
					$results = array_merge( $results, $this->extract_path_values( $child, array_merge( array( $segment ), $segments ) ) );
				}
			}
			return $results;
		}

		$value = $node[ $segment ];
		if ( $this->is_list( $value ) ) {
			$results = array();
			foreach ( $value as $item ) {
				$results = array_merge( $results, $this->extract_path_values( $item, $segments ) );
			}
			return $results;
		}

		return $this->extract_path_values( $value, $segments );
	}

	/**
	 * Determine whether an array is a list.
	 *
	 * @param mixed $value Candidate value.
	 * @return bool
	 */
	private function is_list( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}

		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $value );
		}

		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
