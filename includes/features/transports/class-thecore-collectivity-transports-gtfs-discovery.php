<?php
/**
 * GTFS locality discovery and sync for transports.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Transports_GTFS_Discovery {
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
	 * Discover and sync transport content for the configured locality.
	 *
	 * @return array|WP_Error
	 */
	public function discover_locality( $provider_key = '' ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'bellevue_transport_zip_missing', __( 'ZipArchive n est pas disponible sur ce serveur.', 'bellevue' ) );
		}

		$config           = $this->schedule_repository->get_gtfs_source_config( $provider_key );
		$provider_key     = ! empty( $config['provider_key'] ) ? sanitize_key( (string) $config['provider_key'] ) : $this->schedule_repository->get_default_provider_key();
		$locality_name = isset( $config['locality_name'] ) ? trim( (string) $config['locality_name'] ) : '';
		$extra_localities = isset( $config['extra_localities'] ) && is_array( $config['extra_localities'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', $config['extra_localities'] ) ) ) : array();
		$extra_route_ids  = isset( $config['extra_route_ids'] ) && is_array( $config['extra_route_ids'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', $config['extra_route_ids'] ) ) ) : array();
		$extra_stop_ids   = isset( $config['extra_stop_ids'] ) && is_array( $config['extra_stop_ids'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', $config['extra_stop_ids'] ) ) ) : array();
		$gtfs_url      = isset( $config['gtfs_url'] ) ? trim( (string) $config['gtfs_url'] ) : '';

		if ( '' === $locality_name && empty( $extra_localities ) && empty( $extra_route_ids ) && empty( $extra_stop_ids ) ) {
			return new WP_Error( 'bellevue_transport_locality_missing', __( 'Aucune commune GTFS, route supplementaire ou stop supplementaire n est configuree pour la decouverte automatique.', 'bellevue' ) );
		}

		if ( '' === $gtfs_url ) {
			return new WP_Error( 'bellevue_transport_gtfs_url_missing', __( 'Aucune URL GTFS n est configuree.', 'bellevue' ) );
		}

		$this->schedule_repository->update_discovery_status(
			array(
				'status'       => 'running',
				'started_at'   => current_time( 'mysql' ),
				'source_url'   => $gtfs_url,
				'provider'     => isset( $config['provider_label'] ) ? (string) $config['provider_label'] : '',
				'providerKey'  => $provider_key,
				'localityName' => $locality_name,
				'localityInsee'=> isset( $config['locality_insee'] ) ? (string) $config['locality_insee'] : '',
				'extraLocalities' => $extra_localities,
				'extraRouteIds'   => $extra_route_ids,
				'extraStopIds'    => $extra_stop_ids,
			),
			$provider_key
		);

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$temp_file = download_url( $gtfs_url, 180 );
		if ( is_wp_error( $temp_file ) ) {
			$this->schedule_repository->update_discovery_status(
				array(
					'status'       => 'error',
					'completed_at' => current_time( 'mysql' ),
					'message'      => $temp_file->get_error_message(),
					'source_url'   => $gtfs_url,
					'provider'     => isset( $config['provider_label'] ) ? (string) $config['provider_label'] : '',
					'providerKey'  => $provider_key,
					'localityName' => $locality_name,
					'localityInsee'=> isset( $config['locality_insee'] ) ? (string) $config['locality_insee'] : '',
					'extraLocalities' => $extra_localities,
					'extraRouteIds'   => $extra_route_ids,
					'extraStopIds'    => $extra_stop_ids,
				),
				$provider_key
			);
			return $temp_file;
		}

		$result = $this->discover_archive( $temp_file, $config );
		@unlink( $temp_file );

		if ( is_wp_error( $result ) ) {
			$this->schedule_repository->update_discovery_status(
				array(
					'status'       => 'error',
					'completed_at' => current_time( 'mysql' ),
					'message'      => $result->get_error_message(),
					'source_url'   => $gtfs_url,
					'provider'     => isset( $config['provider_label'] ) ? (string) $config['provider_label'] : '',
					'providerKey'  => $provider_key,
					'localityName' => $locality_name,
					'localityInsee'=> isset( $config['locality_insee'] ) ? (string) $config['locality_insee'] : '',
					'extraLocalities' => $extra_localities,
					'extraRouteIds'   => $extra_route_ids,
					'extraStopIds'    => $extra_stop_ids,
				),
				$provider_key
			);
			return $result;
		}

		$status = array(
			'status'       => 'success',
			'completed_at' => current_time( 'mysql' ),
			'source_url'   => $gtfs_url,
			'provider'     => isset( $config['provider_label'] ) ? (string) $config['provider_label'] : '',
			'providerKey'  => $provider_key,
			'localityName' => $locality_name,
			'localityInsee'=> isset( $config['locality_insee'] ) ? (string) $config['locality_insee'] : '',
			'extraLocalities' => $extra_localities,
			'extraRouteIds'   => $extra_route_ids,
			'extraStopIds'    => $extra_stop_ids,
			'counts'       => $result,
		);

		$this->schedule_repository->update_discovery_status( $status, $provider_key );

		return $status;
	}

	/**
	 * Parse a GTFS archive and sync the locality content.
	 *
	 * @param string $file   Local ZIP path.
	 * @param array  $config Source config.
	 * @return array|WP_Error
	 */
	private function discover_archive( $file, array $config ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $file ) ) {
			return new WP_Error( 'bellevue_transport_gtfs_open_failed', __( 'Impossible d ouvrir l archive GTFS telechargee.', 'bellevue' ) );
		}

		$locality_name      = isset( $config['locality_name'] ) ? (string) $config['locality_name'] : '';
		$provider           = isset( $config['provider_label'] ) ? (string) $config['provider_label'] : '';
		$provider_key       = ! empty( $config['provider_key'] ) ? sanitize_key( (string) $config['provider_key'] ) : TheCore_Collectivity_Transports_Schedule_Repository::DEFAULT_GTFS_PROVIDER_KEY;
		$extra_localities   = isset( $config['extra_localities'] ) && is_array( $config['extra_localities'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', $config['extra_localities'] ) ) ) : array();
		$explicit_route_ids = isset( $config['extra_route_ids'] ) && is_array( $config['extra_route_ids'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', $config['extra_route_ids'] ) ) ) : array();
		$explicit_stop_ids  = isset( $config['extra_stop_ids'] ) && is_array( $config['extra_stop_ids'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', $config['extra_stop_ids'] ) ) ) : array();

		try {
			$locality_names     = array_values( array_filter( array_unique( array_merge( array( $locality_name ), $extra_localities ) ) ) );
			$locality_keys      = array_values( array_filter( array_unique( array_map( array( $this, 'normalize_text_key' ), $locality_names ) ) ) );
			$explicit_route_map = array_fill_keys( $explicit_route_ids, true );
			$explicit_stop_map  = array_fill_keys( $explicit_stop_ids, true );
			$all_stops          = array();
			$stops              = array();
			$duplicate_names    = array();
			$seed_stop_ids      = array();
			$stop_trip_ids      = array();
			$trip_to_route      = array();
			$route_trip_ids     = array();
			$route_stop_ids     = array();
			$stop_route_ids     = array();
			$routes             = array();

			$this->iterate_csv_rows(
				$zip,
				'stops.txt',
				function ( array $row ) use ( &$all_stops, &$stops, &$seed_stop_ids, $locality_keys, $explicit_stop_map ) {
					$stop_id = isset( $row['stop_id'] ) ? sanitize_text_field( $row['stop_id'] ) : '';
					if ( '' === $stop_id ) {
						return;
					}

					$city_name = isset( $row['city_name'] ) ? trim( (string) $row['city_name'] ) : '';
					$stop_name = isset( $row['stop_name'] ) ? sanitize_text_field( $row['stop_name'] ) : $stop_id;
					$stop      = array(
						'stop_id'             => $stop_id,
						'stop_name'           => $stop_name,
						'stop_lat'            => isset( $row['stop_lat'] ) ? sanitize_text_field( $row['stop_lat'] ) : '',
						'stop_lon'            => isset( $row['stop_lon'] ) ? sanitize_text_field( $row['stop_lon'] ) : '',
						'stop_url'            => isset( $row['stop_url'] ) ? esc_url_raw( $row['stop_url'] ) : '',
						'wheelchair_boarding' => isset( $row['wheelchair_boarding'] ) ? sanitize_text_field( $row['wheelchair_boarding'] ) : '',
						'area_name'           => isset( $row['area_name'] ) ? sanitize_text_field( $row['area_name'] ) : '',
						'city_name'           => $city_name,
						'location_type'       => isset( $row['location_type'] ) ? sanitize_text_field( $row['location_type'] ) : '',
						'parent_station'      => isset( $row['parent_station'] ) ? sanitize_text_field( $row['parent_station'] ) : '',
					);
					$all_stops[ $stop_id ] = $stop;

					$city_key         = '' !== $city_name ? $this->normalize_text_key( $city_name ) : '';
					$matches_locality = '' !== $city_key && ! empty( $locality_keys ) && in_array( $city_key, $locality_keys, true );
					$is_explicit_stop = ! empty( $explicit_stop_map[ $stop_id ] );

					if ( ! $matches_locality && ! $is_explicit_stop ) {
						return;
					}

					$stops[ $stop_id ]         = $stop;
					$seed_stop_ids[ $stop_id ] = true;
				}
			);

			if ( empty( $stops ) && empty( $explicit_route_map ) ) {
				$zip->close();
				return new WP_Error( 'bellevue_transport_gtfs_no_local_stops', __( 'Aucun arret GTFS n a ete trouve pour les communes ou stop IDs configures.', 'bellevue' ) );
			}

			$this->iterate_csv_rows(
				$zip,
				'stop_times.txt',
				function ( array $row ) use ( &$stop_trip_ids, $seed_stop_ids ) {
					$stop_id = isset( $row['stop_id'] ) ? sanitize_text_field( $row['stop_id'] ) : '';
					$trip_id = isset( $row['trip_id'] ) ? sanitize_text_field( $row['trip_id'] ) : '';
					if ( '' === $stop_id || '' === $trip_id || empty( $seed_stop_ids[ $stop_id ] ) ) {
						return;
					}

					if ( empty( $stop_trip_ids[ $stop_id ] ) ) {
						$stop_trip_ids[ $stop_id ] = array();
					}

					$stop_trip_ids[ $stop_id ][ $trip_id ] = true;
				}
			);

			if ( empty( $stop_trip_ids ) && empty( $explicit_route_map ) ) {
				$zip->close();
				return new WP_Error( 'bellevue_transport_gtfs_no_local_trips', __( 'Aucun trip GTFS n a ete trouve pour les arrets ou routes configures.', 'bellevue' ) );
			}

			$local_trip_ids = array();
			foreach ( $stop_trip_ids as $trip_ids ) {
				foreach ( array_keys( $trip_ids ) as $trip_id ) {
					$local_trip_ids[ $trip_id ] = true;
				}
			}

			$this->iterate_csv_rows(
				$zip,
				'trips.txt',
				function ( array $row ) use ( &$trip_to_route, &$route_trip_ids, $local_trip_ids, $explicit_route_map ) {
					$trip_id  = isset( $row['trip_id'] ) ? sanitize_text_field( $row['trip_id'] ) : '';
					$route_id = isset( $row['route_id'] ) ? sanitize_text_field( $row['route_id'] ) : '';
					if ( '' === $trip_id || '' === $route_id ) {
						return;
					}

					$is_seed_trip      = ! empty( $local_trip_ids[ $trip_id ] );
					$is_explicit_route = ! empty( $explicit_route_map[ $route_id ] );
					if ( ! $is_seed_trip && ! $is_explicit_route ) {
						return;
					}

					$trip_to_route[ $trip_id ] = array(
						'route_id'      => $route_id,
						'direction_id'  => isset( $row['direction_id'] ) ? sanitize_text_field( $row['direction_id'] ) : '',
						'trip_headsign' => isset( $row['trip_headsign'] ) ? sanitize_text_field( $row['trip_headsign'] ) : '',
					);

					if ( empty( $route_trip_ids[ $route_id ] ) ) {
						$route_trip_ids[ $route_id ] = array();
					}

					$route_trip_ids[ $route_id ][ $trip_id ] = true;
				}
			);

			if ( empty( $route_trip_ids ) ) {
				$zip->close();
				return new WP_Error( 'bellevue_transport_gtfs_no_local_routes', __( 'Aucune ligne GTFS n a ete trouvee pour les communes, routes ou stops configures.', 'bellevue' ) );
			}

			foreach ( $stop_trip_ids as $stop_id => $trip_ids ) {
				foreach ( array_keys( $trip_ids ) as $trip_id ) {
					if ( empty( $trip_to_route[ $trip_id ]['route_id'] ) ) {
						continue;
					}

					$route_id = $trip_to_route[ $trip_id ]['route_id'];
					if ( empty( $route_stop_ids[ $route_id ] ) ) {
						$route_stop_ids[ $route_id ] = array();
					}
					if ( empty( $stop_route_ids[ $stop_id ] ) ) {
						$stop_route_ids[ $stop_id ] = array();
					}

					$route_stop_ids[ $route_id ][ $stop_id ] = true;
					$stop_route_ids[ $stop_id ][ $route_id ] = true;
				}
			}

			foreach ( $stops as $stop ) {
				$stop_name = isset( $stop['stop_name'] ) ? (string) $stop['stop_name'] : '';
				if ( '' === $stop_name ) {
					continue;
				}

				if ( empty( $duplicate_names[ $stop_name ] ) ) {
					$duplicate_names[ $stop_name ] = 0;
				}

				++$duplicate_names[ $stop_name ];
			}

			$route_ids = array_fill_keys( array_keys( $route_trip_ids ), true );
			$this->iterate_csv_rows(
				$zip,
				'routes.txt',
				function ( array $row ) use ( &$routes, $route_ids ) {
					$route_id = isset( $row['route_id'] ) ? sanitize_text_field( $row['route_id'] ) : '';
					if ( '' === $route_id || empty( $route_ids[ $route_id ] ) ) {
						return;
					}

					$routes[ $route_id ] = array(
						'route_id'         => $route_id,
						'route_short_name' => isset( $row['route_short_name'] ) ? sanitize_text_field( $row['route_short_name'] ) : '',
						'route_long_name'  => isset( $row['route_long_name'] ) ? sanitize_text_field( $row['route_long_name'] ) : '',
						'route_type'       => isset( $row['route_type'] ) ? absint( $row['route_type'] ) : 0,
						'route_color'      => isset( $row['route_color'] ) ? sanitize_text_field( $row['route_color'] ) : '',
						'route_text_color' => isset( $row['route_text_color'] ) ? sanitize_text_field( $row['route_text_color'] ) : '',
					);
				}
			);

			if ( empty( $routes ) ) {
				$zip->close();
				return new WP_Error( 'bellevue_transport_gtfs_no_route_rows', __( 'Les lignes GTFS n ont pas pu etre resolues dans routes.txt.', 'bellevue' ) );
			}

			$line_sync  = $this->sync_lines( $routes, $route_stop_ids, $provider, $provider_key );
			$place_sync = $this->sync_places( $stops, $duplicate_names, $stop_route_ids, $routes, $line_sync['routePostMap'], $provider, $provider_key );

			$zip->close();

			return array(
				'lines_created'    => $line_sync['created'],
				'lines_updated'    => $line_sync['updated'],
				'places_created'   => $place_sync['created'],
				'places_updated'   => $place_sync['updated'],
				'routes'           => count( $routes ),
				'stops'            => count( $stops ),
				'provider'         => $provider,
				'locality'         => $locality_name,
				'extra_localities' => $extra_localities,
				'extra_route_ids'  => $explicit_route_ids,
				'extra_stop_ids'   => $explicit_stop_ids,
			);
		} catch ( Exception $exception ) {
			$zip->close();
			return new WP_Error( 'bellevue_transport_gtfs_discovery_runtime', $exception->getMessage() );
		}
	}

	/**
	 * Create or update line posts from GTFS routes.
	 *
	 * @param array  $routes         Indexed GTFS routes.
	 * @param array  $route_stop_ids Local stop ids per route.
	 * @param string $provider       Provider label.
	 * @return array
	 */
	private function sync_lines( array $routes, array $route_stop_ids, $provider, $provider_key ) {
		$created       = 0;
		$updated       = 0;
		$route_post_map = array();

		uasort(
			$routes,
			static function ( $left, $right ) {
				$left_label  = ! empty( $left['route_short_name'] ) ? $left['route_short_name'] : $left['route_id'];
				$right_label = ! empty( $right['route_short_name'] ) ? $right['route_short_name'] : $right['route_id'];
				return strcasecmp( $left_label, $right_label );
			}
		);

		foreach ( $routes as $route_id => $route ) {
			$post_id = $this->find_line_post_id( $route_id, $provider_key );
			$title   = $this->build_line_title( $route, $provider );
			$postarr = array(
				'post_type'   => TheCore_Collectivity_Transports_Post_Types::POST_TYPE_LINE,
				'post_status' => 'publish',
				'post_title'  => $title,
			);

			if ( $post_id > 0 ) {
				$postarr['ID'] = $post_id;
				$post_id       = wp_update_post( $postarr, true );
				if ( ! is_wp_error( $post_id ) ) {
					++$updated;
				}
			} else {
				$post_id = wp_insert_post( $postarr, true );
				if ( ! is_wp_error( $post_id ) ) {
					++$created;
				}
			}

			if ( is_wp_error( $post_id ) || $post_id <= 0 ) {
				continue;
			}

			$route_short_name = ! empty( $route['route_short_name'] ) ? (string) $route['route_short_name'] : '';
			$route_long_name  = ! empty( $route['route_long_name'] ) ? (string) $route['route_long_name'] : '';
			$stop_ids         = ! empty( $route_stop_ids[ $route_id ] ) ? array_keys( $route_stop_ids[ $route_id ] ) : array();
			sort( $stop_ids );

			update_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_LINE_CODE, $route_short_name ?: $route_id );
			update_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_ROUTE_LABEL, $route_long_name );
			update_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_DATA_SOURCE, TheCore_Collectivity_Transports_Meta::DATA_SOURCE_GTFS );
			update_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_GTFS_PROVIDER_KEY, sanitize_key( (string) $provider_key ) );
			update_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_GTFS_ROUTE_IDS, $route_id );
			update_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_GTFS_SHORT_NAMES, $route_short_name );
			update_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_PRIMARY_STOP_IDS, implode( "\n", $stop_ids ) );
			update_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_GTFS_SYNC_ROUTE_KEY, $route_id );
			update_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_SORT_ORDER, $this->infer_sort_order( $route_short_name ?: $route_id ) );

			wp_set_post_terms(
				$post_id,
				array( $this->resolve_mode_slug_from_route_type( isset( $route['route_type'] ) ? intval( $route['route_type'] ) : 0 ) ),
				TheCore_Collectivity_Transports_Post_Types::TAXONOMY_MODE,
				false
			);

			$route_post_map[ $route_id ] = (int) $post_id;
		}

		return array(
			'created'      => $created,
			'updated'      => $updated,
			'routePostMap' => $route_post_map,
		);
	}

	/**
	 * Create or update place posts from GTFS stops.
	 *
	 * @param array  $stops            Indexed GTFS stops.
	 * @param array  $duplicate_names  Duplicate stop-name counts.
	 * @param array  $stop_route_ids   Route ids by stop id.
	 * @param array  $routes           Route rows.
	 * @param array  $route_post_map   Post ids by route id.
	 * @param string $provider         Provider label.
	 * @return array
	 */
	private function sync_places( array $stops, array $duplicate_names, array $stop_route_ids, array $routes, array $route_post_map, $provider, $provider_key ) {
		$created = 0;
		$updated = 0;

		uasort(
			$stops,
			static function ( $left, $right ) {
				return strcasecmp( $left['stop_name'], $right['stop_name'] );
			}
		);

		foreach ( $stops as $stop_id => $stop ) {
			$post_id = $this->find_place_post_id( $stop_id, $provider_key );
			$title   = $this->build_stop_title( $stop, ! empty( $duplicate_names[ $stop['stop_name'] ] ) && $duplicate_names[ $stop['stop_name'] ] > 1 );
			$postarr = array(
				'post_type'   => TheCore_Collectivity_Transports_Post_Types::POST_TYPE_PLACE,
				'post_status' => 'publish',
				'post_title'  => $title,
			);

			if ( $post_id > 0 ) {
				$postarr['ID'] = $post_id;
				$post_id       = wp_update_post( $postarr, true );
				if ( ! is_wp_error( $post_id ) ) {
					++$updated;
				}
			} else {
				$post_id = wp_insert_post( $postarr, true );
				if ( ! is_wp_error( $post_id ) ) {
					++$created;
				}
			}

			if ( is_wp_error( $post_id ) || $post_id <= 0 ) {
				continue;
			}

			$route_ids      = ! empty( $stop_route_ids[ $stop_id ] ) ? array_keys( $stop_route_ids[ $stop_id ] ) : array();
			$related_lines  = array();
			$mode_slugs     = array();

			foreach ( $route_ids as $route_id ) {
				if ( ! empty( $route_post_map[ $route_id ] ) ) {
					$related_lines[] = intval( $route_post_map[ $route_id ] );
				}
				if ( ! empty( $routes[ $route_id ] ) ) {
					$mode_slugs[] = $this->resolve_mode_slug_from_route_type( intval( $routes[ $route_id ]['route_type'] ) );
				}
			}

			$related_lines = array_values( array_unique( array_filter( $related_lines ) ) );
			$mode_slugs    = array_values( array_unique( array_filter( $mode_slugs ) ) );
			if ( empty( $mode_slugs ) ) {
				$mode_slugs[] = TheCore_Collectivity_Transports_Post_Types::MODE_BUS;
			}

			update_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_PLACE_SUBTITLE, $provider ? sprintf( __( 'Arret %s', 'bellevue' ), $provider ) : __( 'Arret de transport', 'bellevue' ) );
			update_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_ADDRESS, $this->build_stop_address( $stop ) );
			update_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_LATITUDE, $stop['stop_lat'] );
			update_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_LONGITUDE, $stop['stop_lon'] );
			update_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_GTFS_STOP_IDS, $stop_id );
			update_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_GTFS_PROVIDER_KEY, sanitize_key( (string) $provider_key ) );
			update_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_RELATED_LINES, $related_lines );
			update_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_IS_ACCESSIBLE, '1' === (string) $stop['wheelchair_boarding'] ? '1' : '0' );
			update_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_EXTERNAL_URL, $stop['stop_url'] );
			update_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_SEARCH_KEYWORDS, implode( ' ', array_filter( array( $stop['city_name'], $stop['area_name'] ) ) ) );
			update_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_SORT_ORDER, 0 );
			update_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_GTFS_SYNC_STOP_KEY, $stop_id );

			wp_set_post_terms(
				$post_id,
				$mode_slugs,
				TheCore_Collectivity_Transports_Post_Types::TAXONOMY_MODE,
				false
			);
		}

		return array(
			'created' => $created,
			'updated' => $updated,
		);
	}

	/**
	 * Find an existing line post for a GTFS route id.
	 *
	 * @param string $route_id GTFS route id.
	 * @return int
	 */
	private function find_line_post_id( $route_id, $provider_key ) {
		$route_id = sanitize_text_field( (string) $route_id );
		$provider_key = sanitize_key( (string) $provider_key );
		if ( '' === $route_id ) {
			return 0;
		}

		$posts = get_posts(
			array(
				'post_type'      => TheCore_Collectivity_Transports_Post_Types::POST_TYPE_LINE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'meta_key'       => TheCore_Collectivity_Transports_Meta::META_GTFS_SYNC_ROUTE_KEY,
				'meta_value'     => $route_id,
				'fields'         => 'ids',
			)
		);

		foreach ( $posts as $post_id ) {
			if ( $this->is_provider_match( $post_id, $provider_key ) ) {
				return intval( $post_id );
			}
		}

		$lines = get_posts(
			array(
				'post_type'      => TheCore_Collectivity_Transports_Post_Types::POST_TYPE_LINE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
			)
		);

		foreach ( $lines as $line ) {
			$source = (string) get_post_meta( $line->ID, TheCore_Collectivity_Transports_Meta::META_DATA_SOURCE, true );
			if ( ! TheCore_Collectivity_Transports_Meta::is_gtfs_source( $source ) ) {
				continue;
			}

			if ( ! $this->is_provider_match( $line->ID, $provider_key ) ) {
				continue;
			}

			$route_ids = $this->schedule_repository->parse_meta_list( get_post_meta( $line->ID, TheCore_Collectivity_Transports_Meta::META_GTFS_ROUTE_IDS, true ) );
			if ( in_array( $route_id, $route_ids, true ) ) {
				return intval( $line->ID );
			}
		}

		return 0;
	}

	/**
	 * Find an existing place post for a GTFS stop id.
	 *
	 * @param string $stop_id GTFS stop id.
	 * @return int
	 */
	private function find_place_post_id( $stop_id, $provider_key ) {
		$stop_id = sanitize_text_field( (string) $stop_id );
		$provider_key = sanitize_key( (string) $provider_key );
		if ( '' === $stop_id ) {
			return 0;
		}

		$posts = get_posts(
			array(
				'post_type'      => TheCore_Collectivity_Transports_Post_Types::POST_TYPE_PLACE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'meta_key'       => TheCore_Collectivity_Transports_Meta::META_GTFS_SYNC_STOP_KEY,
				'meta_value'     => $stop_id,
				'fields'         => 'ids',
			)
		);

		foreach ( $posts as $post_id ) {
			if ( $this->is_provider_match( $post_id, $provider_key ) ) {
				return intval( $post_id );
			}
		}

		$places = get_posts(
			array(
				'post_type'      => TheCore_Collectivity_Transports_Post_Types::POST_TYPE_PLACE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
			)
		);

		foreach ( $places as $place ) {
			if ( ! $this->is_provider_match( $place->ID, $provider_key ) ) {
				continue;
			}

			$stop_ids = $this->schedule_repository->parse_meta_list( get_post_meta( $place->ID, TheCore_Collectivity_Transports_Meta::META_GTFS_STOP_IDS, true ) );
			if ( in_array( $stop_id, $stop_ids, true ) ) {
				return intval( $place->ID );
			}
		}

		return 0;
	}

	/**
	 * Check whether one transport post belongs to the given provider.
	 *
	 * @param int    $post_id       Post id.
	 * @param string $provider_key  Provider key.
	 * @return bool
	 */
	private function is_provider_match( $post_id, $provider_key ) {
		return sanitize_key( (string) get_post_meta( $post_id, TheCore_Collectivity_Transports_Meta::META_GTFS_PROVIDER_KEY, true ) ) === sanitize_key( (string) $provider_key );
	}

	/**
	 * Build a line title for admin usage.
	 *
	 * @param array  $route    GTFS route row.
	 * @param string $provider Provider label.
	 * @return string
	 */
	private function build_line_title( array $route, $provider ) {
		$short_name = ! empty( $route['route_short_name'] ) ? trim( (string) $route['route_short_name'] ) : '';
		$long_name  = ! empty( $route['route_long_name'] ) ? trim( (string) $route['route_long_name'] ) : '';

		if ( '' !== $short_name ) {
			return sprintf( __( 'Ligne %s', 'bellevue' ), $short_name );
		}

		if ( '' !== $long_name ) {
			return $long_name;
		}

		return $provider ? sprintf( __( 'Ligne %1$s %2$s', 'bellevue' ), $provider, $route['route_id'] ) : sprintf( __( 'Ligne %s', 'bellevue' ), $route['route_id'] );
	}

	/**
	 * Build a stop title and disambiguate duplicates.
	 *
	 * @param array $stop         Stop row.
	 * @param bool  $is_duplicate Whether the stop name is duplicated locally.
	 * @return string
	 */
	private function build_stop_title( array $stop, $is_duplicate ) {
		$stop_name = ! empty( $stop['stop_name'] ) ? (string) $stop['stop_name'] : (string) $stop['stop_id'];
		if ( ! $is_duplicate ) {
			return $stop_name;
		}

		$area_name = ! empty( $stop['area_name'] ) ? trim( (string) $stop['area_name'] ) : '';
		if ( '' !== $area_name ) {
			return sprintf( '%1$s - %2$s', $stop_name, $area_name );
		}

		$stop_suffix = preg_replace( '/^.*:/', '', (string) $stop['stop_id'] );
		return sprintf( '%1$s (%2$s)', $stop_name, $stop_suffix );
	}

	/**
	 * Build a concise address string from a stop row.
	 *
	 * @param array $stop Stop row.
	 * @return string
	 */
	private function build_stop_address( array $stop ) {
		$parts = array();
		if ( ! empty( $stop['area_name'] ) ) {
			$parts[] = trim( (string) $stop['area_name'] );
		}
		if ( ! empty( $stop['city_name'] ) ) {
			$parts[] = trim( (string) $stop['city_name'] );
		}

		return implode( ', ', array_filter( $parts ) );
	}

	/**
	 * Resolve the default transport mode from a GTFS route type.
	 *
	 * @param int $route_type GTFS route type.
	 * @return string
	 */
	private function resolve_mode_slug_from_route_type( $route_type ) {
		$route_type = intval( $route_type );
		if ( in_array( $route_type, array( 0, 1, 2 ), true ) ) {
			return TheCore_Collectivity_Transports_Post_Types::MODE_TRAIN;
		}

		return TheCore_Collectivity_Transports_Post_Types::MODE_BUS;
	}

	/**
	 * Infer a stable sort order from a public line code.
	 *
	 * @param string $label Public label.
	 * @return int
	 */
	private function infer_sort_order( $label ) {
		$label = trim( (string) $label );
		if ( preg_match( '/(\d+)/', $label, $matches ) ) {
			return intval( $matches[1] );
		}

		return 0;
	}

	/**
	 * Normalize a text key for locality matching.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function normalize_text_key( $value ) {
		$value = remove_accents( trim( (string) $value ) );
		$value = strtolower( $value );
		$value = preg_replace( '/\s+/', ' ', $value );
		return trim( (string) $value );
	}

	/**
	 * Iterate through a CSV file inside a GTFS ZIP archive.
	 *
	 * @param ZipArchive $zip      Open ZIP archive.
	 * @param string     $filename CSV filename.
	 * @param callable   $callback Callback for each row.
	 * @throws RuntimeException When the CSV cannot be opened.
	 */
	private function iterate_csv_rows( ZipArchive $zip, $filename, callable $callback ) {
		$stream = $zip->getStream( $filename );
		if ( ! is_resource( $stream ) ) {
			throw new RuntimeException( sprintf( 'Le fichier %s est introuvable dans l archive GTFS.', $filename ) );
		}

		$headers = null;
		while ( false !== ( $row = fgetcsv( $stream ) ) ) {
			if ( null === $headers ) {
				$headers = array_map(
					static function ( $header ) {
						return sanitize_key( trim( (string) $header ) );
					},
					$row
				);
				continue;
			}

			if ( empty( $row ) ) {
				continue;
			}

			$record = array();
			foreach ( $headers as $index => $header ) {
				if ( '' === $header ) {
					continue;
				}
				$record[ $header ] = isset( $row[ $index ] ) ? trim( (string) $row[ $index ] ) : '';
			}

			$callback( $record );
		}

		fclose( $stream );
	}
}
