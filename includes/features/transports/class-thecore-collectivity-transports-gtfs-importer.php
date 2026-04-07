<?php
/**
 * GTFS importer for Bellevue transports.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Transports_GTFS_Importer {
	/**
	 * Schedule repository.
	 *
	 * @var TheCore_Collectivity_Transports_Schedule_Repository
	 */
	private $schedule_repository;

	/**
	 * GTFS source resolver.
	 *
	 * @var TheCore_Collectivity_Transports_GTFS_Source_Resolver
	 */
	private $source_resolver;

	/**
	 * Constructor.
	 *
	 * @param TheCore_Collectivity_Transports_Schedule_Repository   $schedule_repository Schedule repository.
	 * @param TheCore_Collectivity_Transports_GTFS_Source_Resolver $source_resolver     Source resolver.
	 */
	public function __construct( TheCore_Collectivity_Transports_Schedule_Repository $schedule_repository, TheCore_Collectivity_Transports_GTFS_Source_Resolver $source_resolver ) {
		$this->schedule_repository = $schedule_repository;
		$this->source_resolver     = $source_resolver;
	}

	/**
	 * Run the import.
	 *
	 * @return array|WP_Error
	 */
	public function import( $provider_key = '' ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'bellevue_transport_zip_missing', __( 'ZipArchive n est pas disponible sur ce serveur.', 'bellevue' ) );
		}

		$source_config = $this->schedule_repository->get_gtfs_source_config( $provider_key );
		$provider_key  = ! empty( $source_config['provider_key'] ) ? sanitize_key( (string) $source_config['provider_key'] ) : $this->schedule_repository->get_default_provider_key();
		$gtfs_url      = ! empty( $source_config['gtfs_url'] ) ? (string) $source_config['gtfs_url'] : TheCore_Collectivity_Transports_Schedule_Repository::DEFAULT_GTFS_URL;

		if ( '' === $gtfs_url ) {
			return new WP_Error( 'bellevue_transport_gtfs_url_missing', __( 'Aucune URL GTFS n est configuree.', 'bellevue' ) );
		}

		$config = $this->schedule_repository->get_mapping_overview( $provider_key );
		if ( empty( $config['lines'] ) ) {
			return new WP_Error( 'bellevue_transport_no_lines', __( 'Aucune ligne n est configuree en source GTFS.', 'bellevue' ) );
		}

		if ( empty( $config['stopIds'] ) ) {
			return new WP_Error( 'bellevue_transport_no_stops', __( 'Aucun stop_id GTFS n est configure sur les lignes ou lieux.', 'bellevue' ) );
		}

		if ( empty( $config['routeIds'] ) && empty( $config['shortNames'] ) ) {
			return new WP_Error( 'bellevue_transport_no_routes', __( 'Aucun route_id ou route_short_name GTFS n est configure sur les lignes.', 'bellevue' ) );
		}

		$this->schedule_repository->update_import_status(
			array(
				'status'      => 'running',
				'started_at'  => current_time( 'mysql' ),
				'source_url'  => $gtfs_url,
				'provider'    => ! empty( $source_config['provider_label'] ) ? $source_config['provider_label'] : '',
				'providerKey' => $provider_key,
				'mapping'     => array(
					'lineCount'  => $config['lineCount'],
					'routeCount' => $config['routeCount'],
					'shortCount' => $config['shortCount'],
					'stopCount'  => $config['stopCount'],
				),
			),
			$provider_key
		);

		$download = $this->source_resolver->download_to_temp_file( $gtfs_url, 180 );
		if ( is_wp_error( $download ) ) {
			$this->schedule_repository->update_import_status(
				array(
					'status'     => 'error',
					'completed_at'=> current_time( 'mysql' ),
					'message'    => $download->get_error_message(),
					'source_url' => $gtfs_url,
					'provider'   => ! empty( $source_config['provider_label'] ) ? $source_config['provider_label'] : '',
					'providerKey'=> $provider_key,
				),
				$provider_key
			);
			return $download;
		}

		$temp_file = $download['temp_file'];
		$result    = $this->import_archive( $temp_file, $config, $provider_key );
		@unlink( $temp_file );

		if ( is_wp_error( $result ) ) {
			$this->schedule_repository->update_import_status(
				array(
					'status'      => 'error',
					'completed_at'=> current_time( 'mysql' ),
					'message'     => $result->get_error_message(),
					'source_url'  => $gtfs_url,
					'provider'    => ! empty( $source_config['provider_label'] ) ? $source_config['provider_label'] : '',
					'providerKey' => $provider_key,
				),
				$provider_key
			);
			return $result;
		}

		$status = array(
			'status'       => 'success',
			'completed_at' => current_time( 'mysql' ),
			'source_url'   => $gtfs_url,
			'download_url' => $download['download_url'] ?? $gtfs_url,
			'provider'     => ! empty( $source_config['provider_label'] ) ? $source_config['provider_label'] : '',
			'providerKey'  => $provider_key,
			'counts'       => $result,
			'mapping'      => array(
				'lineCount'  => $config['lineCount'],
				'routeCount' => $config['routeCount'],
				'shortCount' => $config['shortCount'],
				'stopCount'  => $config['stopCount'],
			),
		);
		$this->schedule_repository->update_import_status( $status, $provider_key );

		return $status;
	}

	/**
	 * Import every enabled GTFS source.
	 *
	 * @return array
	 */
	public function import_enabled_sources() {
		$results = array();
		foreach ( $this->schedule_repository->get_gtfs_sources() as $source ) {
			if ( empty( $source['is_enabled'] ) || empty( $source['provider_key'] ) ) {
				continue;
			}

			$results[ $source['provider_key'] ] = $this->import( $source['provider_key'] );
		}

		return $results;
	}

	/**
	 * Import a downloaded GTFS archive.
	 *
	 * @param string $file   Local ZIP file.
	 * @param array  $config Mapping config.
	 * @return array|WP_Error
	 */
	private function import_archive( $file, array $config, $provider_key ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $file ) ) {
			return new WP_Error( 'bellevue_transport_gtfs_open_failed', __( 'Impossible d ouvrir l archive GTFS telechargee.', 'bellevue' ) );
		}
		try {
			$routes          = array();
			$route_ids       = array();
			$mapped_stop_ids = array_values( array_unique( $config['stopIds'] ) );
			$tracked_stops   = array_fill_keys( $mapped_stop_ids, true );

			$route_id_map   = array_fill_keys( $config['routeIds'], true );
			$short_name_map = array_fill_keys( $config['shortNames'], true );

			$this->iterate_csv_rows(
				$zip,
				'routes.txt',
				function ( array $row ) use ( &$routes, &$route_ids, $route_id_map, $short_name_map ) {
				$route_id   = isset( $row['route_id'] ) ? sanitize_text_field( $row['route_id'] ) : '';
				$short_name = isset( $row['route_short_name'] ) ? sanitize_text_field( $row['route_short_name'] ) : '';
				if ( '' === $route_id ) {
					return;
				}

				if ( ! isset( $route_id_map[ $route_id ] ) && ! isset( $short_name_map[ $short_name ] ) ) {
					return;
				}

					$route_ids[ $route_id ] = true;
					$routes[ $route_id ] = array(
						'route_id'         => $route_id,
						'route_short_name' => $short_name,
						'route_long_name'  => isset( $row['route_long_name'] ) ? sanitize_text_field( $row['route_long_name'] ) : '',
						'route_type'       => isset( $row['route_type'] ) ? absint( $row['route_type'] ) : 0,
						'agency_id'        => isset( $row['agency_id'] ) ? sanitize_text_field( $row['agency_id'] ) : '',
						'route_color'      => $this->sanitize_route_color( isset( $row['route_color'] ) ? $row['route_color'] : '' ),
						'route_text_color' => $this->sanitize_route_color( isset( $row['route_text_color'] ) ? $row['route_text_color'] : '' ),
					);
					}
				);

			if ( empty( $routes ) ) {
				$zip->close();
				return new WP_Error( 'bellevue_transport_gtfs_no_routes', __( 'Aucune route GTFS n a ete trouvee avec le mapping configure.', 'bellevue' ) );
			}

			$trips       = array();
			$trip_ids    = array();
			$service_ids = array();
			$shape_ids   = array();
			$this->iterate_csv_rows(
				$zip,
				'trips.txt',
				function ( array $row ) use ( &$trips, &$trip_ids, &$service_ids, &$shape_ids, $route_ids ) {
				$route_id = isset( $row['route_id'] ) ? sanitize_text_field( $row['route_id'] ) : '';
				$trip_id  = isset( $row['trip_id'] ) ? sanitize_text_field( $row['trip_id'] ) : '';
				if ( '' === $trip_id || empty( $route_ids[ $route_id ] ) ) {
					return;
				}

				$service_id = isset( $row['service_id'] ) ? sanitize_text_field( $row['service_id'] ) : '';
				$shape_id   = isset( $row['shape_id'] ) ? sanitize_text_field( $row['shape_id'] ) : '';
				$trip_ids[ $trip_id ]    = true;
				$service_ids[ $service_id ] = true;
				if ( '' !== $shape_id ) {
					$shape_ids[ $shape_id ] = true;
				}
				$trips[ $trip_id ] = array(
					'trip_id'        => $trip_id,
					'route_id'       => $route_id,
					'service_id'     => $service_id,
					'trip_headsign'  => isset( $row['trip_headsign'] ) ? sanitize_text_field( $row['trip_headsign'] ) : '',
					'direction_id'   => isset( $row['direction_id'] ) ? sanitize_text_field( $row['direction_id'] ) : '',
					'shape_id'       => $shape_id,
				);
				}
			);

			if ( empty( $trips ) ) {
				$zip->close();
				return new WP_Error( 'bellevue_transport_gtfs_no_trips', __( 'Aucun trip GTFS n a ete trouve pour les routes configurees.', 'bellevue' ) );
			}

			$services = array();
			$this->iterate_csv_rows(
				$zip,
				'calendar.txt',
				function ( array $row ) use ( &$services, $service_ids ) {
				$service_id = isset( $row['service_id'] ) ? sanitize_text_field( $row['service_id'] ) : '';
				if ( '' === $service_id || empty( $service_ids[ $service_id ] ) ) {
					return;
				}

				$services[ $service_id ] = array(
					'service_id'   => $service_id,
					'monday'       => isset( $row['monday'] ) ? absint( $row['monday'] ) : 0,
					'tuesday'      => isset( $row['tuesday'] ) ? absint( $row['tuesday'] ) : 0,
					'wednesday'    => isset( $row['wednesday'] ) ? absint( $row['wednesday'] ) : 0,
					'thursday'     => isset( $row['thursday'] ) ? absint( $row['thursday'] ) : 0,
					'friday'       => isset( $row['friday'] ) ? absint( $row['friday'] ) : 0,
					'saturday'     => isset( $row['saturday'] ) ? absint( $row['saturday'] ) : 0,
					'sunday'       => isset( $row['sunday'] ) ? absint( $row['sunday'] ) : 0,
					'start_date'   => isset( $row['start_date'] ) ? sanitize_text_field( $row['start_date'] ) : '',
					'end_date'     => isset( $row['end_date'] ) ? sanitize_text_field( $row['end_date'] ) : '',
					'added_dates'  => array(),
					'removed_dates'=> array(),
				);
				}
			);

			$this->iterate_csv_rows(
				$zip,
				'calendar_dates.txt',
				function ( array $row ) use ( &$services, $service_ids ) {
				$service_id     = isset( $row['service_id'] ) ? sanitize_text_field( $row['service_id'] ) : '';
				$exception_date = isset( $row['date'] ) ? sanitize_text_field( $row['date'] ) : '';
				$exception_type = isset( $row['exception_type'] ) ? absint( $row['exception_type'] ) : 0;
				if ( '' === $service_id || empty( $service_ids[ $service_id ] ) || '' === $exception_date ) {
					return;
				}

				if ( empty( $services[ $service_id ] ) ) {
					$services[ $service_id ] = array(
						'service_id'    => $service_id,
						'monday'        => 0,
						'tuesday'       => 0,
						'wednesday'     => 0,
						'thursday'      => 0,
						'friday'        => 0,
						'saturday'      => 0,
						'sunday'        => 0,
						'start_date'    => '',
						'end_date'      => '',
						'added_dates'   => array(),
						'removed_dates' => array(),
					);
				}

				if ( 1 === $exception_type ) {
					$services[ $service_id ]['added_dates'][] = $exception_date;
				} elseif ( 2 === $exception_type ) {
					$services[ $service_id ]['removed_dates'][] = $exception_date;
				}
				}
			);

			$stops = array();
			$this->iterate_csv_rows(
				$zip,
				'stops.txt',
				function ( array $row ) use ( &$stops, $tracked_stops ) {
				$stop_id = isset( $row['stop_id'] ) ? sanitize_text_field( $row['stop_id'] ) : '';
				if ( '' === $stop_id || empty( $tracked_stops[ $stop_id ] ) ) {
					return;
				}

				$stops[ $stop_id ] = array(
					'stop_id'        => $stop_id,
					'stop_name'      => isset( $row['stop_name'] ) ? sanitize_text_field( $row['stop_name'] ) : '',
					'parent_station' => isset( $row['parent_station'] ) ? sanitize_text_field( $row['parent_station'] ) : '',
					'stop_lat'       => isset( $row['stop_lat'] ) && '' !== $row['stop_lat'] ? (float) $row['stop_lat'] : null,
					'stop_lon'       => isset( $row['stop_lon'] ) && '' !== $row['stop_lon'] ? (float) $row['stop_lon'] : null,
				);
				}
			);

			$stop_times = array();
			$this->iterate_csv_rows(
				$zip,
				'stop_times.txt',
				function ( array $row ) use ( &$stop_times, $trip_ids, $tracked_stops ) {
				$trip_id = isset( $row['trip_id'] ) ? sanitize_text_field( $row['trip_id'] ) : '';
				$stop_id = isset( $row['stop_id'] ) ? sanitize_text_field( $row['stop_id'] ) : '';
				if ( '' === $trip_id || '' === $stop_id || empty( $trip_ids[ $trip_id ] ) || empty( $tracked_stops[ $stop_id ] ) ) {
					return;
				}

				$arrival_secs   = $this->time_to_seconds( isset( $row['arrival_time'] ) ? $row['arrival_time'] : '' );
				$departure_secs = $this->time_to_seconds( isset( $row['departure_time'] ) ? $row['departure_time'] : '' );
				if ( null === $arrival_secs && null === $departure_secs ) {
					return;
				}

				if ( null === $arrival_secs ) {
					$arrival_secs = $departure_secs;
				}

				if ( null === $departure_secs ) {
					$departure_secs = $arrival_secs;
				}

				$stop_times[] = array(
					'trip_id'        => $trip_id,
					'stop_id'        => $stop_id,
					'stop_sequence'  => isset( $row['stop_sequence'] ) ? absint( $row['stop_sequence'] ) : 0,
					'arrival_secs'   => $arrival_secs,
					'departure_secs' => $departure_secs,
				);
				}
			);

			$shapes = array();
			if ( false !== $zip->locateName( 'shapes.txt' ) && ! empty( $shape_ids ) ) {
				$this->iterate_csv_rows(
					$zip,
					'shapes.txt',
					function ( array $row ) use ( &$shapes, $shape_ids ) {
					$shape_id = isset( $row['shape_id'] ) ? sanitize_text_field( $row['shape_id'] ) : '';
					if ( '' === $shape_id || empty( $shape_ids[ $shape_id ] ) ) {
						return;
					}

					if ( ! isset( $row['shape_pt_sequence'], $row['shape_pt_lat'], $row['shape_pt_lon'] ) ) {
						return;
					}

					$sequence = absint( $row['shape_pt_sequence'] );
					$lat      = '' !== $row['shape_pt_lat'] ? (float) $row['shape_pt_lat'] : null;
					$lon      = '' !== $row['shape_pt_lon'] ? (float) $row['shape_pt_lon'] : null;
					if ( null === $lat || null === $lon ) {
						return;
					}

					$shapes[] = array(
						'shape_id'          => $shape_id,
						'shape_pt_sequence' => $sequence,
						'shape_pt_lat'      => $lat,
						'shape_pt_lon'      => $lon,
					);
					}
				);
			}

			$zip->close();

			if ( empty( $stop_times ) ) {
				return new WP_Error( 'bellevue_transport_gtfs_no_stop_times', __( 'Aucun horaire n a ete trouve pour les stops GTFS configures.', 'bellevue' ) );
			}

			$this->replace_imported_data( $provider_key, $routes, $trips, $shapes, $stop_times, $services, $stops );

			return array(
				'routes'    => count( $routes ),
				'trips'     => count( $trips ),
				'shapes'    => count( $shapes ),
				'services'  => count( $services ),
				'stops'     => count( $stops ),
				'stopTimes' => count( $stop_times ),
			);
		} catch ( RuntimeException $exception ) {
			$zip->close();
			return new WP_Error( 'bellevue_transport_gtfs_runtime', $exception->getMessage() );
		}
	}

	/**
	 * Replace previously imported data.
	 *
	 * @param array $routes     Route rows.
	 * @param array $trips      Trip rows.
	 * @param array $shapes     Shape rows.
	 * @param array $stop_times Stop time rows.
	 * @param array $services   Service rows.
	 * @param array $stops      Stop rows.
	 */
	private function replace_imported_data( $provider_key, array $routes, array $trips, array $shapes, array $stop_times, array $services, array $stops ) {
		global $wpdb;

		$provider_key = sanitize_key( (string) $provider_key );

		$stops_table     = TheCore_Collectivity_Transports_Schedule_Schema::get_table_name( TheCore_Collectivity_Transports_Schedule_Schema::TABLE_STOPS );
		$routes_table    = TheCore_Collectivity_Transports_Schedule_Schema::get_table_name( TheCore_Collectivity_Transports_Schedule_Schema::TABLE_ROUTES );
		$trips_table     = TheCore_Collectivity_Transports_Schedule_Schema::get_table_name( TheCore_Collectivity_Transports_Schedule_Schema::TABLE_TRIPS );
		$shapes_table    = TheCore_Collectivity_Transports_Schedule_Schema::get_table_name( TheCore_Collectivity_Transports_Schedule_Schema::TABLE_SHAPES );
		$stop_times_table= TheCore_Collectivity_Transports_Schedule_Schema::get_table_name( TheCore_Collectivity_Transports_Schedule_Schema::TABLE_STOP_TIMES );
		$services_table  = TheCore_Collectivity_Transports_Schedule_Schema::get_table_name( TheCore_Collectivity_Transports_Schedule_Schema::TABLE_SERVICES );

		$wpdb->query( 'START TRANSACTION' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$stop_times_table} WHERE provider_key = %s", $provider_key ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$trips_table} WHERE provider_key = %s", $provider_key ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$shapes_table} WHERE provider_key = %s", $provider_key ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$services_table} WHERE provider_key = %s", $provider_key ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$routes_table} WHERE provider_key = %s", $provider_key ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$stops_table} WHERE provider_key = %s", $provider_key ) );

		$service_rows = array_map(
			function ( $row ) use ( $provider_key ) {
				return array(
					'provider_key'  => $provider_key,
					'service_id'    => $row['service_id'],
					'monday'        => $row['monday'],
					'tuesday'       => $row['tuesday'],
					'wednesday'     => $row['wednesday'],
					'thursday'      => $row['thursday'],
					'friday'        => $row['friday'],
					'saturday'      => $row['saturday'],
					'sunday'        => $row['sunday'],
					'start_date'    => $row['start_date'],
					'end_date'      => $row['end_date'],
					'added_dates'   => wp_json_encode( array_values( array_unique( $row['added_dates'] ) ) ),
					'removed_dates' => wp_json_encode( array_values( array_unique( $row['removed_dates'] ) ) ),
				);
			},
			$services
		);

		try {
			$this->insert_rows(
				$stops_table,
				array( 'provider_key', 'stop_id', 'stop_name', 'parent_station', 'stop_lat', 'stop_lon' ),
				$this->prepend_provider_key( array_values( $stops ), $provider_key ),
				array( '%s', '%s', '%s', '%s', '%f', '%f' )
			);
				$this->insert_rows(
					$routes_table,
					array( 'provider_key', 'route_id', 'route_short_name', 'route_long_name', 'route_type', 'agency_id', 'route_color', 'route_text_color' ),
					$this->prepend_provider_key( array_values( $routes ), $provider_key ),
					array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
				);
			$this->insert_rows(
				$services_table,
				array( 'provider_key', 'service_id', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday', 'start_date', 'end_date', 'added_dates', 'removed_dates' ),
				array_values( $service_rows ),
				array( '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' ),
				100
			);
			$this->insert_rows(
				$trips_table,
				array( 'provider_key', 'trip_id', 'route_id', 'service_id', 'trip_headsign', 'direction_id', 'shape_id' ),
				$this->prepend_provider_key( array_values( $trips ), $provider_key ),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
				250
			);
			$this->insert_rows(
				$shapes_table,
				array( 'provider_key', 'shape_id', 'shape_pt_sequence', 'shape_pt_lat', 'shape_pt_lon' ),
				$this->prepend_provider_key( array_values( $shapes ), $provider_key ),
				array( '%s', '%s', '%d', '%f', '%f' ),
				500
			);
			$this->insert_rows(
				$stop_times_table,
				array( 'provider_key', 'trip_id', 'stop_id', 'stop_sequence', 'arrival_secs', 'departure_secs' ),
				$this->prepend_provider_key( array_values( $stop_times ), $provider_key ),
				array( '%s', '%s', '%s', '%d', '%d', '%d' ),
				500
			);
		} catch ( RuntimeException $exception ) {
			$wpdb->query( 'ROLLBACK' );
			throw $exception;
		}

		$wpdb->query( 'COMMIT' );
	}

	/**
	 * Inject one provider key into imported rows.
	 *
	 * @param array  $rows         Imported rows.
	 * @param string $provider_key Provider key.
	 * @return array
	 */
	private function prepend_provider_key( array $rows, $provider_key ) {
		$provider_key = sanitize_key( (string) $provider_key );

		return array_map(
			static function ( array $row ) use ( $provider_key ) {
				$row['provider_key'] = $provider_key;
				return $row;
			},
			$rows
		);
	}

	/**
	 * Insert rows in batches for faster GTFS imports.
	 *
	 * @param string $table      Target table.
	 * @param array  $columns    Ordered column list.
	 * @param array  $rows       Rows to insert.
	 * @param array  $formats    Value formats for one row.
	 * @param int    $chunk_size Rows per statement.
	 * @return void
	 */
	private function insert_rows( $table, array $columns, array $rows, array $formats, $chunk_size = 250 ) {
		global $wpdb;

		if ( empty( $rows ) ) {
			return;
		}

		$chunk_size     = max( 1, absint( $chunk_size ) );
		$escaped_columns = '`' . implode( '`, `', array_map( 'sanitize_key', $columns ) ) . '`';

		foreach ( array_chunk( $rows, $chunk_size ) as $chunk ) {
			$placeholders = array();
			$values       = array();

			foreach ( $chunk as $row ) {
				$row_placeholders = array();
				foreach ( $columns as $index => $column ) {
					$row_placeholders[] = $formats[ $index ];
					$values[] = isset( $row[ $column ] ) ? $row[ $column ] : null;
				}
				$placeholders[] = '(' . implode( ', ', $row_placeholders ) . ')';
			}

			$sql = "INSERT INTO {$table} ({$escaped_columns}) VALUES " . implode( ', ', $placeholders );
			$query = $wpdb->prepare( $sql, $values );
			$result = $wpdb->query( $query );

			if ( false === $result ) {
				throw new RuntimeException( sprintf( 'GTFS insert failed for table %s: %s', $table, $wpdb->last_error ) );
			}
		}
	}

	/**
	 * Iterate a CSV file from the GTFS archive.
	 *
	 * @param ZipArchive $zip      Open archive.
	 * @param string     $filename Filename inside archive.
	 * @param callable   $callback Row callback.
	 * @return void
	 */
	private function iterate_csv_rows( ZipArchive $zip, $filename, callable $callback ) {
		$stream = $zip->getStream( $filename );
		if ( ! $stream ) {
			throw new RuntimeException( sprintf( 'Missing GTFS file: %s', $filename ) );
		}

		$header = null;
		while ( false !== ( $row = fgetcsv( $stream ) ) ) {
			if ( null === $header ) {
				$header = $this->normalize_csv_header( $row );
				continue;
			}

			if ( empty( $header ) ) {
				continue;
			}

			$row = array_pad( $row, count( $header ), '' );
			$callback( array_combine( $header, $row ) );
		}

		fclose( $stream );
	}

	/**
	 * Normalize CSV header row.
	 *
	 * @param array $header Raw header row.
	 * @return array
	 */
	private function normalize_csv_header( array $header ) {
		$header = array_map( 'strval', $header );
		if ( ! empty( $header[0] ) ) {
			$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] );
		}
		return array_map( 'trim', $header );
	}

	/**
	 * Convert a GTFS time string to seconds.
	 *
	 * @param string $value Time string.
	 * @return int|null
	 */
	private function time_to_seconds( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}

		$parts = array_map( 'intval', explode( ':', $value ) );
		if ( count( $parts ) < 2 ) {
			return null;
		}

		$hours   = $parts[0];
		$minutes = $parts[1];
		$seconds = isset( $parts[2] ) ? $parts[2] : 0;
		return ( $hours * HOUR_IN_SECONDS ) + ( $minutes * MINUTE_IN_SECONDS ) + $seconds;
	}

	/**
	 * Sanitize GTFS hexadecimal route colors.
	 *
	 * @param string $value Raw color.
	 * @return string
	 */
	private function sanitize_route_color( $value ) {
		$value = strtoupper( trim( (string) $value ) );
		$value = ltrim( $value, '#' );

		if ( ! preg_match( '/^[0-9A-F]{6}$/', $value ) ) {
			return '';
		}

		return $value;
	}
}
