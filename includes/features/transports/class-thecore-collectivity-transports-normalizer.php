<?php
/**
 * Transport payload normalizer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Transports_Normalizer {
	/**
	 * Repository.
	 *
	 * @var TheCore_Collectivity_Transports_Repository
	 */
	private $repository;

	/**
	 * Schedule repository.
	 *
	 * @var TheCore_Collectivity_Transports_Schedule_Repository
	 */
	private $schedule_repository;

	/**
	 * Constructor.
	 *
	 * @param TheCore_Collectivity_Transports_Repository          $repository          Repository instance.
	 * @param TheCore_Collectivity_Transports_Schedule_Repository $schedule_repository Schedule repository.
	 */
	public function __construct( TheCore_Collectivity_Transports_Repository $repository, TheCore_Collectivity_Transports_Schedule_Repository $schedule_repository ) {
		$this->repository          = $repository;
		$this->schedule_repository = $schedule_repository;
	}

	/**
	 * Build widget payload.
	 *
	 * @return array
	 */
	public function get_widget_payload() {
		$lines            = $this->repository->get_lines();
		$line_index       = array();
		$line_payloads    = array();
		$realtime_alerts  = array();
		$realtime_vehicles = array();

		foreach ( $lines as $line ) {
			$normalized = $this->normalize_line( $line );
			$line_index[ $normalized['id'] ] = $normalized;
			$line_payloads[]                 = $normalized;
			$this->collect_line_realtime_payloads( $normalized, $realtime_alerts, $realtime_vehicles );
		}

		$place_payloads = array();
		foreach ( $this->repository->get_places() as $place ) {
			$place_payloads[] = $this->normalize_place( $place, $line_index );
		}

		return array(
			'lines'    => $line_payloads,
			'places'   => $place_payloads,
			'realtime' => array(
				'alerts'   => array_values( $realtime_alerts ),
				'vehicles' => array_values( $realtime_vehicles ),
			),
		);
	}

	/**
	 * Build a realtime-only widget payload.
	 *
	 * This payload keeps the full realtime detail needed by the frontend:
	 * line-level realtime, per-stop live departures, and same-day trips used
	 * for "dernier passage" calculations, without returning the static cartography.
	 *
	 * @return array
	 */
	public function get_widget_realtime_payload() {
		$line_payloads     = array();
		$realtime_alerts   = array();
		$realtime_vehicles = array();

		foreach ( $this->repository->get_lines() as $line ) {
			$normalized      = $this->normalize_line( $line );
			$line_payloads[] = $this->extract_realtime_line_payload( $normalized );
			$this->collect_line_realtime_payloads( $normalized, $realtime_alerts, $realtime_vehicles );
		}

		return array(
			'lines'    => $line_payloads,
			'realtime' => array(
				'alerts'   => array_values( $realtime_alerts ),
				'vehicles' => array_values( $realtime_vehicles ),
			),
		);
	}

	/**
	 * Collect realtime items for widget-level payloads.
	 *
	 * @param array $line_payload       Normalized line payload.
	 * @param array $realtime_alerts    Aggregated realtime alerts.
	 * @param array $realtime_vehicles  Aggregated realtime vehicles.
	 * @return void
	 */
	private function collect_line_realtime_payloads( array $line_payload, array &$realtime_alerts, array &$realtime_vehicles ) {
		$line_id         = intval( $line_payload['id'] ?? 0 );
		$line_code       = ! empty( $line_payload['lineCode'] ) ? (string) $line_payload['lineCode'] : '';
		$line_title      = ! empty( $line_payload['title'] ) ? (string) $line_payload['title'] : '';
		$route_label     = ! empty( $line_payload['routeLabel'] ) ? (string) $line_payload['routeLabel'] : '';
		$provider_key    = ! empty( $line_payload['providerKey'] ) ? (string) $line_payload['providerKey'] : '';
		$provider_label  = ! empty( $line_payload['providerLabel'] ) ? (string) $line_payload['providerLabel'] : '';
		$route_meta      = ! empty( $line_payload['schedule']['routeMeta'] ) && is_array( $line_payload['schedule']['routeMeta'] ) ? $line_payload['schedule']['routeMeta'] : array();
		$route_id        = ! empty( $route_meta['routeId'] ) ? (string) $route_meta['routeId'] : '';
		$realtime        = ! empty( $line_payload['schedule']['realtime'] ) && is_array( $line_payload['schedule']['realtime'] ) ? $line_payload['schedule']['realtime'] : array();
		$line_searchtext = ! empty( $line_payload['searchText'] ) ? (string) $line_payload['searchText'] : '';

		foreach ( (array) ( $realtime['alerts'] ?? array() ) as $alert ) {
			$alert_key = ! empty( $alert['alertKey'] ) ? (string) $alert['alertKey'] : '';
			if ( '' === $alert_key ) {
				continue;
			}

			if ( empty( $realtime_alerts[ $alert_key ] ) ) {
				$alert['lineIds']     = array();
				$alert['lineCodes']   = array();
				$alert['lineTitles']  = array();
				$alert['searchText']  = trim( implode( ' ', array_filter( array( $alert['headerText'] ?? '', $alert['descriptionText'] ?? '', $alert['effectLabel'] ?? '', $alert['causeLabel'] ?? '' ) ) ) );
				$realtime_alerts[ $alert_key ] = $alert;
			}

			if ( $line_id > 0 && ! in_array( $line_id, $realtime_alerts[ $alert_key ]['lineIds'], true ) ) {
				$realtime_alerts[ $alert_key ]['lineIds'][] = $line_id;
			}
			if ( '' !== $line_code && ! in_array( $line_code, $realtime_alerts[ $alert_key ]['lineCodes'], true ) ) {
				$realtime_alerts[ $alert_key ]['lineCodes'][] = $line_code;
			}
			if ( '' !== $line_title && ! in_array( $line_title, $realtime_alerts[ $alert_key ]['lineTitles'], true ) ) {
				$realtime_alerts[ $alert_key ]['lineTitles'][] = $line_title;
			}

			$realtime_alerts[ $alert_key ]['searchText'] = trim( $realtime_alerts[ $alert_key ]['searchText'] . ' ' . $line_searchtext );
		}

		foreach ( (array) ( $realtime['vehicles'] ?? array() ) as $vehicle ) {
			$vehicle_key = ! empty( $vehicle['entityId'] ) ? (string) $vehicle['entityId'] : '';
			if ( '' === $vehicle_key ) {
				$vehicle_key = md5( serialize( array( $provider_key, $route_id, $vehicle['vehicleId'] ?? '', $vehicle['tripId'] ?? '', $vehicle['timestamp'] ?? '' ) ) );
			}

			$vehicle['lineId']        = $line_id;
			$vehicle['lineCode']      = $line_code;
			$vehicle['lineTitle']     = $line_title;
			$vehicle['routeLabel']    = $route_label;
			$vehicle['providerKey']   = $provider_key;
			$vehicle['providerLabel'] = $provider_label;
			$vehicle['routeId']       = ! empty( $vehicle['routeId'] ) ? (string) $vehicle['routeId'] : $route_id;
			$vehicle['theme']         = ! empty( $line_payload['theme'] ) && is_array( $line_payload['theme'] ) ? $line_payload['theme'] : array();
			$vehicle['searchText']    = trim( implode( ' ', array_filter( array( $vehicle['vehicleLabel'] ?? '', $vehicle['vehicleId'] ?? '', $line_code, $line_title, $route_label, $provider_label ) ) ) );

			$realtime_vehicles[ $vehicle_key ] = $vehicle;
		}
	}

	/**
	 * Extract the realtime-only subset for one line.
	 *
	 * @param array $line_payload Full normalized line payload.
	 * @return array
	 */
	private function extract_realtime_line_payload( array $line_payload ) {
		$realtime_stops = array();
		$stops          = ! empty( $line_payload['schedule']['stops'] ) && is_array( $line_payload['schedule']['stops'] ) ? $line_payload['schedule']['stops'] : array();

		foreach ( $stops as $stop ) {
			$realtime_directions = array();
			foreach ( (array) ( $stop['directions'] ?? array() ) as $direction ) {
				$realtime_directions[] = array(
					'directionId'    => (string) ( $direction['directionId'] ?? '' ),
					'headsign'       => (string) ( $direction['headsign'] ?? '' ),
					'liveDepartures' => ! empty( $direction['liveDepartures'] ) && is_array( $direction['liveDepartures'] ) ? $direction['liveDepartures'] : array(),
					'todayTrips'     => ! empty( $direction['todayTrips'] ) && is_array( $direction['todayTrips'] ) ? $direction['todayTrips'] : array(),
				);
			}

			$realtime_stops[] = array(
				'stopId'      => (string) ( $stop['stopId'] ?? '' ),
				'directions'  => $realtime_directions,
			);
		}

		return array(
			'id'       => intval( $line_payload['id'] ?? 0 ),
			'schedule' => array(
				'realtime' => ! empty( $line_payload['schedule']['realtime'] ) && is_array( $line_payload['schedule']['realtime'] ) ? $line_payload['schedule']['realtime'] : array(),
				'stops'    => $realtime_stops,
			),
		);
	}

	/**
	 * Normalize a transport line.
	 *
	 * @param WP_Post $post Line post.
	 * @return array
	 */
	private function normalize_line( $post ) {
		$service_days = get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_SERVICE_DAYS, true );
		$service_days = is_array( $service_days ) ? array_values( array_filter( array_map( 'sanitize_key', $service_days ) ) ) : array();

		$line_code       = (string) get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_LINE_CODE, true );
		$route_label     = (string) get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_ROUTE_LABEL, true );
		$frequency_label = (string) get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_FREQUENCY_LABEL, true );
		$hours_label     = (string) get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_HOURS_LABEL, true );
		$data_source     = TheCore_Collectivity_Transports_Meta::normalize_data_source( get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_DATA_SOURCE, true ) );
		$keywords        = (string) get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_SEARCH_KEYWORDS, true );
		$title           = get_the_title( $post );
		$schedule        = $this->schedule_repository->get_line_schedule( (int) $post->ID );
		$reference_stops = $this->schedule_repository->get_line_reference_stops( (int) $post->ID );
		$line_theme      = $this->build_line_theme( $line_code, $schedule );
		$summary_text    = ! empty( $schedule['summaryText'] ) ? (string) $schedule['summaryText'] : '';
		$service_days    = ! empty( $schedule['availableDays'] ) && is_array( $schedule['availableDays'] ) ? array_values( array_unique( array_merge( $service_days, $schedule['availableDays'] ) ) ) : $service_days;
		$provider_key    = sanitize_key( (string) get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_GTFS_PROVIDER_KEY, true ) );
		$provider_label  = $this->resolve_provider_label( $provider_key );
		$transport_modes = $this->get_line_transport_modes( $schedule );
		$mode_labels     = array_values( array_filter( wp_list_pluck( $transport_modes, 'name' ) ) );
		$transport_url   = (string) get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_EXTERNAL_URL, true );
		$map_url         = (string) get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_MAP_URL, true );
		$map_url         = '' !== $map_url ? $map_url : $transport_url;

		return array(
			'id'            => (int) $post->ID,
			'title'         => $title,
			'lineCode'      => $line_code,
			'routeLabel'    => $route_label,
			'frequencyLabel'=> $frequency_label,
				'hoursLabel'    => $hours_label,
			'dataSource'    => $data_source ?: TheCore_Collectivity_Transports_Meta::DATA_SOURCE_MANUAL,
			'providerKey'   => $provider_key,
			'providerLabel' => $provider_label,
			'timetableSummary' => $summary_text,
			'schedule'      => $schedule,
			'geojson'       => ! empty( $schedule['routeGeoJson'] ) && is_array( $schedule['routeGeoJson'] ) ? $schedule['routeGeoJson'] : null,
			'mode'          => ! empty( $transport_modes[0]['slug'] ) ? (string) $transport_modes[0]['slug'] : '',
			'modes'         => array_values( array_filter( wp_list_pluck( $transport_modes, 'slug' ) ) ),
			'modeLabels'    => $mode_labels,
			'referenceStops'=> $reference_stops,
			'theme'         => $line_theme,
			'isAccessible'  => '1' === (string) get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_IS_ACCESSIBLE, true ),
			'serviceDays'   => $service_days,
			'externalUrl'   => $transport_url,
			'transportUrl'  => $transport_url,
			'mapUrl'        => $map_url,
			'sortOrder'     => (int) get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_SORT_ORDER, true ),
			'searchText'    => implode( ' ', array_filter( array_merge( array( $title, $line_code, $route_label, $frequency_label, $hours_label, $summary_text, $provider_label, $keywords ), $mode_labels ) ) ),
		);
	}

	/**
	 * Normalize a transport place.
	 *
	 * @param WP_Post $post       Place post.
	 * @param array   $line_index Indexed line payloads.
	 * @return array
	 */
	private function normalize_place( $post, $line_index ) {
		$terms         = wp_get_post_terms( $post->ID, TheCore_Collectivity_Transports_Post_Types::TAXONOMY_MODE );
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}
		$modes         = array();
		$mode_labels   = array();
		$related_lines = get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_RELATED_LINES, true );
		$related_lines = is_array( $related_lines ) ? array_values( array_filter( array_map( 'intval', $related_lines ) ) ) : array();
		$line_codes    = array();
		$line_titles   = array();

		foreach ( $terms as $term ) {
			$modes[]       = $term->slug;
			$mode_labels[] = $term->name;
		}

		foreach ( $related_lines as $line_id ) {
			if ( empty( $line_index[ $line_id ] ) ) {
				continue;
			}

			$line_codes[]  = $line_index[ $line_id ]['lineCode'];
			$line_titles[] = $line_index[ $line_id ]['title'];
		}

		$title             = get_the_title( $post );
		$subtitle          = (string) get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_PLACE_SUBTITLE, true );
		$address           = (string) get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_ADDRESS, true );
		$parking_type      = (string) get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_PARKING_TYPE, true );
		$keywords          = (string) get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_SEARCH_KEYWORDS, true );
		$train_departures  = $this->split_multiline_meta( (string) get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_TRAIN_DEPARTURES, true ) );
		$train_information = $this->split_multiline_meta( (string) get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_TRAIN_INFORMATION, true ) );
		$provider_key      = sanitize_key( (string) get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_GTFS_PROVIDER_KEY, true ) );
		$provider_label    = $this->resolve_provider_label( $provider_key );
		$gtfs_stop_ids     = $this->schedule_repository->parse_meta_list( get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_GTFS_STOP_IDS, true ) );
		$destination_labels = $this->schedule_repository->get_stop_destination_labels( $gtfs_stop_ids, $related_lines, $provider_key );

		return array(
			'id'               => (int) $post->ID,
			'title'            => $title,
			'subtitle'         => $subtitle,
			'address'          => $address,
			'mode'             => ! empty( $modes ) ? $modes[0] : '',
			'modes'            => $modes,
			'modeLabels'       => $mode_labels,
			'providerKey'      => $provider_key,
			'providerLabel'    => $provider_label,
			'latitude'         => $this->normalize_coordinate( get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_LATITUDE, true ) ),
			'longitude'        => $this->normalize_coordinate( get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_LONGITUDE, true ) ),
			'gtfsStopIds'      => $gtfs_stop_ids,
			'destinationLabels'=> $destination_labels,
			'isAccessible'     => '1' === (string) get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_IS_ACCESSIBLE, true ),
			'externalUrl'      => (string) get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_EXTERNAL_URL, true ),
			'sortOrder'        => (int) get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_SORT_ORDER, true ),
			'relatedLineIds'   => $related_lines,
			'relatedLineCodes' => array_values( array_filter( $line_codes ) ),
			'relatedLineTitles'=> array_values( array_filter( $line_titles ) ),
			'parkingType'      => $parking_type,
			'totalPlaces'      => $this->normalize_integer_meta( get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_TOTAL_PLACES, true ) ),
			'availablePlaces'  => $this->normalize_integer_meta( get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_AVAILABLE_PLACES, true ) ),
			'pmrPlaces'        => $this->normalize_integer_meta( get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_PMR_PLACES, true ) ),
			'isFree'           => '1' === (string) get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_IS_FREE, true ),
			'bikesAvailable'   => $this->normalize_integer_meta( get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_BIKES_AVAILABLE, true ) ),
			'slotsTotal'       => $this->normalize_integer_meta( get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_SLOTS_TOTAL, true ) ),
			'electricBikes'    => $this->normalize_integer_meta( get_post_meta( $post->ID, TheCore_Collectivity_Transports_Meta::META_ELECTRIC_BIKES, true ) ),
			'trainDepartures'  => $train_departures,
			'trainInformation' => $train_information,
			'searchText'       => implode( ' ', array_filter( array_merge( array( $title, $subtitle, $address, $parking_type, $provider_label, $keywords ), $line_codes, $line_titles, $destination_labels, $train_departures, $train_information ) ) ),
		);
	}

	/**
	 * Normalize numeric meta to integer or null.
	 *
	 * @param mixed $value Raw value.
	 * @return int|null
	 */
	private function normalize_integer_meta( $value ) {
		if ( '' === (string) $value ) {
			return null;
		}

		return intval( $value );
	}

	/**
	 * Resolve transport mode facets for one line.
	 *
	 * @param array $schedule Schedule payload.
	 * @return array
	 */
	private function get_line_transport_modes( array $schedule ) {
		$route_type = isset( $schedule['routeMeta']['routeType'] ) ? intval( $schedule['routeMeta']['routeType'] ) : null;
		$mode_slug  = $this->get_mode_slug_from_route_type( $route_type );
		if ( '' === $mode_slug ) {
			return array();
		}

		$term = get_term_by( 'slug', $mode_slug, TheCore_Collectivity_Transports_Post_Types::TAXONOMY_MODE );
		if ( $term && ! is_wp_error( $term ) ) {
			return array(
				array(
					'slug' => $term->slug,
					'name' => $term->name,
				),
			);
		}

		return array(
			array(
				'slug' => $mode_slug,
				'name' => ucfirst( $mode_slug ),
			),
		);
	}

	/**
	 * Map GTFS route types to local mode taxonomy slugs.
	 *
	 * @param int|null $route_type GTFS route type.
	 * @return string
	 */
	private function get_mode_slug_from_route_type( $route_type ) {
		if ( null === $route_type ) {
			return '';
		}

		switch ( intval( $route_type ) ) {
			case 2:
				return TheCore_Collectivity_Transports_Post_Types::MODE_TRAIN;
			case 3:
			case 11:
				return TheCore_Collectivity_Transports_Post_Types::MODE_BUS;
			default:
				return '';
		}
	}

	/**
	 * Normalize coordinate value.
	 *
	 * @param mixed $value Raw value.
	 * @return float|null
	 */
	private function normalize_coordinate( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}

		$value = str_replace( ',', '.', $value );
		if ( ! is_numeric( $value ) ) {
			return null;
		}

		return (float) $value;
	}

	/**
	 * Split textarea content into clean lines.
	 *
	 * @param string $value Multiline value.
	 * @return array
	 */
	private function split_multiline_meta( $value ) {
		$lines = preg_split( '/\r\n|\r|\n/', $value );
		$lines = is_array( $lines ) ? $lines : array();
		$lines = array_map( 'trim', $lines );
		return array_values( array_filter( $lines ) );
	}

	/**
	 * Resolve provider label for one provider key.
	 *
	 * @param string $provider_key Provider key.
	 * @return string
	 */
	private function resolve_provider_label( $provider_key ) {
		$provider_key = sanitize_key( (string) $provider_key );
		if ( '' === $provider_key ) {
			return '';
		}

		$source = $this->schedule_repository->get_gtfs_source_config( $provider_key );
		return ! empty( $source['provider_label'] ) ? (string) $source['provider_label'] : $provider_key;
	}

	/**
	 * Build line color theme.
	 *
	 * @param string $line_code Public line code.
	 * @param array  $schedule  Schedule payload.
	 * @return array
	 */
	private function build_line_theme( $line_code, array $schedule ) {
		$route_meta       = ! empty( $schedule['routeMeta'] ) && is_array( $schedule['routeMeta'] ) ? $schedule['routeMeta'] : array();
		$route_color      = $this->sanitize_hex_color( ! empty( $route_meta['routeColor'] ) ? $route_meta['routeColor'] : '' );
		$route_text_color = $this->sanitize_hex_color( ! empty( $route_meta['routeTextColor'] ) ? $route_meta['routeTextColor'] : '' );
		$agency_id        = strtoupper( trim( (string) ( ! empty( $route_meta['agencyId'] ) ? $route_meta['agencyId'] : '' ) ) );
		$route_type       = isset( $route_meta['routeType'] ) ? intval( $route_meta['routeType'] ) : -1;

		if ( $route_color ) {
			return array(
				'family'    => 'gtfs',
				'color'     => '#' . $route_color,
				'textColor' => '#' . ( $route_text_color ? $route_text_color : $this->get_contrast_text_color( $route_color ) ),
			);
		}

		if ( false !== strpos( $agency_id, 'RATP' ) ) {
			return array(
				'family'    => 'ratp',
				'color'     => '#2F855A',
				'textColor' => '#FFFFFF',
			);
		}

		if ( false !== strpos( $agency_id, 'TRANSILIEN' ) || false !== strpos( $agency_id, 'SNCF' ) || 2 === $route_type ) {
			return array(
				'family'    => 'rail',
				'color'     => '#1D4ED8',
				'textColor' => '#FFFFFF',
			);
		}

		$line_code = strtoupper( trim( (string) $line_code ) );

		if ( preg_match( '/^N\\s*\\d+$/', $line_code ) ) {
			return array(
				'family'    => 'noctilien',
				'color'     => '#283891',
				'textColor' => '#FFFFFF',
			);
		}

		if ( preg_match( '/^EX\\s*\\d+$/', $line_code ) ) {
			return array(
				'family'    => 'express',
				'color'     => '#E66A2C',
				'textColor' => '#FFFFFF',
			);
		}

		return array(
			'family'    => 'bus',
			'color'     => '#2F855A',
			'textColor' => '#FFFFFF',
		);
	}

	/**
	 * Sanitize hexadecimal color.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function sanitize_hex_color( $value ) {
		$value = strtoupper( trim( (string) $value ) );
		$value = ltrim( $value, '#' );

		if ( ! preg_match( '/^[0-9A-F]{6}$/', $value ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Compute contrasting text color for a hex background.
	 *
	 * @param string $hex Six-char hex value without #.
	 * @return string
	 */
	private function get_contrast_text_color( $hex ) {
		$red   = hexdec( substr( $hex, 0, 2 ) );
		$green = hexdec( substr( $hex, 2, 2 ) );
		$blue  = hexdec( substr( $hex, 4, 2 ) );
		$luma  = ( ( $red * 299 ) + ( $green * 587 ) + ( $blue * 114 ) ) / 1000;

		return $luma >= 160 ? '111827' : 'FFFFFF';
	}
}
