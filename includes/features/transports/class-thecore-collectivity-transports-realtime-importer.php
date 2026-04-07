<?php
/**
 * Transport realtime importer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Transports_Realtime_Importer {
	/**
	 * Schedule repository.
	 *
	 * @var TheCore_Collectivity_Transports_Schedule_Repository
	 */
	private $schedule_repository;

	/**
	 * Realtime repository.
	 *
	 * @var TheCore_Collectivity_Transports_Realtime_Repository
	 */
	private $realtime_repository;

	/**
	 * Adapter factory.
	 *
	 * @var TheCore_Collectivity_Transports_Realtime_Adapter_Factory
	 */
	private $adapter_factory;

	/**
	 * Constructor.
	 *
	 * @param TheCore_Collectivity_Transports_Schedule_Repository     $schedule_repository Schedule repository.
	 * @param TheCore_Collectivity_Transports_Realtime_Repository     $realtime_repository Realtime repository.
	 * @param TheCore_Collectivity_Transports_Realtime_Adapter_Factory $adapter_factory     Realtime adapter factory.
	 */
	public function __construct( TheCore_Collectivity_Transports_Schedule_Repository $schedule_repository, TheCore_Collectivity_Transports_Realtime_Repository $realtime_repository, TheCore_Collectivity_Transports_Realtime_Adapter_Factory $adapter_factory ) {
		$this->schedule_repository = $schedule_repository;
		$this->realtime_repository = $realtime_repository;
		$this->adapter_factory     = $adapter_factory;
	}

	/**
	 * Import realtime data for one provider.
	 *
	 * @param string $provider_key Provider key.
	 * @return array|WP_Error
	 */
	public function import( $provider_key = '' ) {
		$source_config = $this->schedule_repository->get_gtfs_source_config( $provider_key );
		$provider_key  = ! empty( $source_config['provider_key'] ) ? sanitize_key( (string) $source_config['provider_key'] ) : $this->schedule_repository->get_default_provider_key();
		$provider      = ! empty( $source_config['provider_label'] ) ? (string) $source_config['provider_label'] : $provider_key;
		$format        = ! empty( $source_config['realtime_format'] ) ? sanitize_key( (string) $source_config['realtime_format'] ) : 'none';

		if ( empty( $source_config['realtime_is_enabled'] ) || 'none' === $format ) {
			return new WP_Error( 'bellevue_transport_realtime_disabled', __( 'Le temps reel n est pas active pour cette source.', 'bellevue' ) );
		}

		$adapter = $this->resolve_adapter( $format );
		if ( is_wp_error( $adapter ) ) {
			$this->store_error_status( $adapter, $provider_key, $provider, $format, $source_config );
			return $adapter;
		}

		$endpoints = $this->get_source_endpoints( $source_config );
		if ( empty( array_filter( $endpoints ) ) ) {
			return new WP_Error( 'bellevue_transport_realtime_url_missing', __( 'Aucune URL temps reel n est configuree pour cette source.', 'bellevue' ) );
		}

		$this->realtime_repository->update_realtime_status(
			array(
				'status'             => 'running',
				'started_at'         => current_time( 'mysql' ),
				'provider'           => $provider,
				'providerKey'        => $provider_key,
				'format'             => $format,
				'tripUpdatesUrl'     => $endpoints['trip_updates'],
				'serviceAlertsUrl'   => $endpoints['service_alerts'],
				'vehiclePositionsUrl'=> $endpoints['vehicle_positions'],
			),
			$provider_key
		);

		$counts = array(
			'entities'         => 0,
			'tripUpdates'      => 0,
			'predictions'      => 0,
			'storedRows'       => 0,
			'serviceAlerts'    => 0,
			'storedAlerts'     => 0,
			'vehiclePositions' => 0,
			'storedVehicles'   => 0,
		);
		$errors = array();
		$feed_timestamps = array();

		$trip_result = $this->import_endpoint( $adapter, 'trip_updates', $endpoints['trip_updates'] );
		if ( is_wp_error( $trip_result ) ) {
			$errors['trip_updates'] = $trip_result->get_error_message();
		} else {
			$predictions = $this->stamp_feed_rows( (array) ( $trip_result['predictions'] ?? array() ) );
			$counts['entities']    += intval( $trip_result['entity_count'] ?? 0 );
			$counts['tripUpdates'] += intval( $trip_result['trip_update_count'] ?? 0 );
			$counts['predictions'] += count( $predictions );
			$counts['storedRows']  += $this->realtime_repository->replace_predictions( $provider_key, $predictions );
			if ( ! empty( $trip_result['feed_timestamp'] ) ) {
				$feed_timestamps[] = intval( $trip_result['feed_timestamp'] );
			}
		}

		$alerts_result = $this->import_endpoint( $adapter, 'service_alerts', $endpoints['service_alerts'] );
		if ( is_wp_error( $alerts_result ) ) {
			$errors['service_alerts'] = $alerts_result->get_error_message();
		} else {
			$alerts = $this->stamp_feed_rows( (array) ( $alerts_result['alerts'] ?? array() ) );
			$counts['entities']      += intval( $alerts_result['entity_count'] ?? 0 );
			$counts['serviceAlerts'] += intval( $alerts_result['alert_count'] ?? 0 );
			$counts['storedAlerts']  += $this->realtime_repository->replace_alerts( $provider_key, $alerts );
			if ( ! empty( $alerts_result['feed_timestamp'] ) ) {
				$feed_timestamps[] = intval( $alerts_result['feed_timestamp'] );
			}
		}

		$vehicles_result = $this->import_endpoint( $adapter, 'vehicle_positions', $endpoints['vehicle_positions'] );
		if ( is_wp_error( $vehicles_result ) ) {
			$errors['vehicle_positions'] = $vehicles_result->get_error_message();
		} else {
			$vehicles = $this->stamp_feed_rows( (array) ( $vehicles_result['vehicles'] ?? array() ) );
			$counts['entities']         += intval( $vehicles_result['entity_count'] ?? 0 );
			$counts['vehiclePositions'] += intval( $vehicles_result['vehicle_count'] ?? 0 );
			$counts['storedVehicles']   += $this->realtime_repository->replace_vehicles( $provider_key, $vehicles );
			if ( ! empty( $vehicles_result['feed_timestamp'] ) ) {
				$feed_timestamps[] = intval( $vehicles_result['feed_timestamp'] );
			}
		}

		$successful_feeds = 0;
		foreach ( array( 'trip_updates', 'service_alerts', 'vehicle_positions' ) as $feed_type ) {
			if ( '' !== $endpoints[ $feed_type ] && empty( $errors[ $feed_type ] ) ) {
				++$successful_feeds;
			}
		}

		if ( 0 === $successful_feeds ) {
			$error = new WP_Error( 'bellevue_transport_realtime_import_failed', implode( ' ', array_values( $errors ) ) ?: __( 'Aucun flux temps reel n a pu être importe.', 'bellevue' ) );
			$this->store_error_status( $error, $provider_key, $provider, $format, $source_config, $errors );
			return $error;
		}

		$status = array(
			'status'              => empty( $errors ) ? 'success' : 'partial',
			'completed_at'        => current_time( 'mysql' ),
			'provider'            => $provider,
			'providerKey'         => $provider_key,
			'format'              => $format,
			'tripUpdatesUrl'      => $endpoints['trip_updates'],
			'serviceAlertsUrl'    => $endpoints['service_alerts'],
			'vehiclePositionsUrl' => $endpoints['vehicle_positions'],
			'counts'              => $counts,
			'feedTimestamp'       => ! empty( $feed_timestamps ) ? max( $feed_timestamps ) : 0,
			'errors'              => $errors,
		);

		$this->realtime_repository->update_realtime_status( $status, $provider_key );

		return $status;
	}

	/**
	 * Import every enabled realtime source.
	 *
	 * @return array
	 */
	public function import_enabled_sources() {
		$results = array();
		foreach ( $this->schedule_repository->get_gtfs_sources() as $source ) {
			if ( empty( $source['is_enabled'] ) || empty( $source['realtime_is_enabled'] ) || empty( $source['provider_key'] ) ) {
				continue;
			}

			$results[ $source['provider_key'] ] = $this->import( $source['provider_key'] );
		}

		return $results;
	}

	/**
	 * Get realtime repository.
	 *
	 * @return TheCore_Collectivity_Transports_Realtime_Repository
	 */
	public function get_realtime_repository() {
		return $this->realtime_repository;
	}

	/**
	 * Import one endpoint if configured.
	 *
	 * @param TheCore_Collectivity_Transports_Realtime_Adapter_Interface $adapter   Adapter.
	 * @param string                                                     $feed_type Feed type.
	 * @param string                                                     $url       Endpoint URL.
	 * @return array|WP_Error
	 */
	private function import_endpoint( TheCore_Collectivity_Transports_Realtime_Adapter_Interface $adapter, $feed_type, $url ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url ) {
			if ( 'service_alerts' === $feed_type ) {
				return array( 'alerts' => array(), 'entity_count' => 0, 'alert_count' => 0, 'feed_timestamp' => 0 );
			}
			if ( 'vehicle_positions' === $feed_type ) {
				return array( 'vehicles' => array(), 'entity_count' => 0, 'vehicle_count' => 0, 'feed_timestamp' => 0 );
			}
			return array( 'predictions' => array(), 'entity_count' => 0, 'trip_update_count' => 0, 'feed_timestamp' => 0 );
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
					/* translators: 1: endpoint type, 2: HTTP status code */
					__( 'Le flux %1$s a repondu avec le code HTTP %2$d.', 'bellevue' ),
					$feed_type,
					$code
				)
			);
		}

		if ( 'service_alerts' === $feed_type ) {
			return $adapter->parse_service_alerts( $body );
		}
		if ( 'vehicle_positions' === $feed_type ) {
			return $adapter->parse_vehicle_positions( $body );
		}

		return $adapter->parse_trip_updates( $body );
	}

	/**
	 * Stamp canonical rows with fetch metadata.
	 *
	 * @param array $rows Canonical rows.
	 * @return array
	 */
	private function stamp_feed_rows( array $rows ) {
		$fetched_at_gmt = current_time( 'mysql', true );
		$expires_at_gmt = gmdate( 'Y-m-d H:i:s', time() + 10 * MINUTE_IN_SECONDS );

		return array_map(
			static function ( array $row ) use ( $fetched_at_gmt, $expires_at_gmt ) {
				$row['fetched_at_gmt'] = $fetched_at_gmt;
				$row['expires_at_gmt'] = $expires_at_gmt;
				return $row;
			},
			$rows
		);
	}

	/**
	 * Resolve one realtime adapter.
	 *
	 * @param string $format Realtime format.
	 * @return TheCore_Collectivity_Transports_Realtime_Adapter_Interface|WP_Error
	 */
	private function resolve_adapter( $format ) {
		$format = sanitize_key( (string) $format );
		$adapter = $this->adapter_factory->get_adapter( $format );
		if ( $adapter instanceof TheCore_Collectivity_Transports_Realtime_Adapter_Interface ) {
			return $adapter;
		}

		return new WP_Error(
			'bellevue_transport_realtime_format_not_supported',
			sprintf(
				/* translators: %s: realtime format */
				__( 'Le format temps reel "%s" n est pas supporte.', 'bellevue' ),
				$format
			)
		);
	}

	/**
	 * Extract endpoints from source config.
	 *
	 * @param array $source_config Source config.
	 * @return array
	 */
	private function get_source_endpoints( array $source_config ) {
		return array(
			'trip_updates'      => ! empty( $source_config['trip_updates_url'] ) ? esc_url_raw( (string) $source_config['trip_updates_url'] ) : '',
			'service_alerts'    => ! empty( $source_config['service_alerts_url'] ) ? esc_url_raw( (string) $source_config['service_alerts_url'] ) : '',
			'vehicle_positions' => ! empty( $source_config['vehicle_positions_url'] ) ? esc_url_raw( (string) $source_config['vehicle_positions_url'] ) : '',
		);
	}

	/**
	 * Persist one realtime error status.
	 *
	 * @param WP_Error $error        Error object.
	 * @param string   $provider_key Provider key.
	 * @param string   $provider     Provider label.
	 * @param string   $format       Realtime format.
	 * @param array    $source_config Source config.
	 * @param array    $errors       Endpoint errors.
	 * @return void
	 */
	private function store_error_status( WP_Error $error, $provider_key, $provider, $format, array $source_config, array $errors = array() ) {
		$endpoints = $this->get_source_endpoints( $source_config );

		$this->realtime_repository->update_realtime_status(
			array(
				'status'              => 'error',
				'completed_at'        => current_time( 'mysql' ),
				'provider'            => $provider,
				'providerKey'         => $provider_key,
				'format'              => $format,
				'tripUpdatesUrl'      => $endpoints['trip_updates'],
				'serviceAlertsUrl'    => $endpoints['service_alerts'],
				'vehiclePositionsUrl' => $endpoints['vehicle_positions'],
				'message'             => $error->get_error_message(),
				'errors'              => $errors,
			),
			$provider_key
		);
	}
}
