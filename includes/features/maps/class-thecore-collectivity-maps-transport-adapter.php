<?php
/**
 * Transport-to-map compatibility adapter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Maps_Transport_Adapter {
	/**
	 * Build transport place items compatible with the MAP widget.
	 *
	 * @param array $filters Adapter filters.
	 * @return array
	 */
	public function get_transport_place_items( array $filters = array() ) {
		$module = TheCore_Collectivity_Management::instance()->get_transports_module();
		if ( ! $module ) {
			return array();
		}

		$payload = $module->get_normalizer()->get_widget_payload();
		$places  = ! empty( $payload['places'] ) && is_array( $payload['places'] ) ? $payload['places'] : array();
		$lines   = ! empty( $payload['lines'] ) && is_array( $payload['lines'] ) ? $payload['lines'] : array();
		$modes   = $this->normalize_mode_filters( $filters['modes'] ?? array() );
		$line_index = array();

		foreach ( $lines as $line ) {
			$line_id = isset( $line['id'] ) ? (int) $line['id'] : 0;
			if ( $line_id <= 0 ) {
				continue;
			}

			$line_index[ $line_id ] = $line;
		}

		$filtered_places = array();
		foreach ( $places as $place ) {
			if ( empty( $place['latitude'] ) || empty( $place['longitude'] ) ) {
				continue;
			}

			if ( ! $this->matches_mode_filter( $place, $modes ) ) {
				continue;
			}

			$filtered_places[] = $place;
		}

		$items = array();
		foreach ( $this->group_transport_places( $filtered_places ) as $group ) {
			if ( 1 === count( $group ) ) {
				$items[] = $this->normalize_transport_place( $group[0], $line_index );
				continue;
			}

			$items[] = $this->normalize_transport_group( $group, $line_index );
		}

		usort(
			$items,
			static function ( $left, $right ) {
				$left_order  = isset( $left['displayOrder'] ) ? (int) $left['displayOrder'] : 0;
				$right_order = isset( $right['displayOrder'] ) ? (int) $right['displayOrder'] : 0;

				if ( $left_order === $right_order ) {
					return strcasecmp( (string) ( $left['title'] ?? '' ), (string) ( $right['title'] ?? '' ) );
				}

				return $left_order <=> $right_order;
			}
		);

		return $items;
	}

	/**
	 * Build transport line items compatible with the MAP widget.
	 *
	 * @param array $filters Adapter filters.
	 * @return array
	 */
	public function get_transport_line_items( array $filters = array() ) {
		$module = TheCore_Collectivity_Management::instance()->get_transports_module();
		if ( ! $module ) {
			return array();
		}

		$payload = $module->get_normalizer()->get_widget_payload();
		$lines   = ! empty( $payload['lines'] ) && is_array( $payload['lines'] ) ? $payload['lines'] : array();
		$places  = ! empty( $payload['places'] ) && is_array( $payload['places'] ) ? $payload['places'] : array();
		$modes   = $this->normalize_mode_filters( $filters['modes'] ?? array() );

		$items = array();
		foreach ( $lines as $line ) {
			if ( empty( $line['geojson'] ) || ! is_array( $line['geojson'] ) ) {
				continue;
			}

			if ( ! $this->matches_mode_filter( $line, $modes ) ) {
				continue;
			}

			$items[] = $this->normalize_transport_line( $line, $places );
		}

		usort(
			$items,
			static function ( $left, $right ) {
				$left_order  = isset( $left['displayOrder'] ) ? (int) $left['displayOrder'] : 0;
				$right_order = isset( $right['displayOrder'] ) ? (int) $right['displayOrder'] : 0;

				if ( $left_order === $right_order ) {
					return strcasecmp( (string) ( $left['title'] ?? '' ), (string) ( $right['title'] ?? '' ) );
				}

				return $left_order <=> $right_order;
			}
		);

		return $items;
	}

	/**
	 * Build transport mode facets for the MAP widget.
	 *
	 * @param array $items Adapted transport items.
	 * @return array
	 */
	public function get_transport_mode_facets( array $items ) {
		$index = array();

		foreach ( $items as $item ) {
			foreach ( (array) ( $item['transportModes'] ?? array() ) as $mode ) {
				$slug = isset( $mode['slug'] ) ? sanitize_key( (string) $mode['slug'] ) : '';
				$name = isset( $mode['name'] ) ? sanitize_text_field( (string) $mode['name'] ) : '';

				if ( '' === $slug || '' === $name ) {
					continue;
				}

				$index[ $slug ] = array(
					'slug' => $slug,
					'name' => $name,
				);
			}
		}

		uasort(
			$index,
			static function ( $left, $right ) {
				return strcasecmp( $left['name'], $right['name'] );
			}
		);

		return array_values( $index );
	}

	/**
	 * Normalize a transport place into one MAP-compatible point item.
	 *
	 * @param array $place Transport place payload.
	 * @return array
	 */
	private function normalize_transport_place( array $place, array $line_index = array() ) {
		$title             = isset( $place['title'] ) ? sanitize_text_field( (string) $place['title'] ) : '';
		$subtitle          = isset( $place['subtitle'] ) ? sanitize_text_field( (string) $place['subtitle'] ) : '';
		$address           = isset( $place['address'] ) ? sanitize_text_field( (string) $place['address'] ) : '';
		$transport_modes   = $this->normalize_transport_modes( $place );
		$primary_mode_slug = ! empty( $transport_modes[0]['slug'] ) ? $transport_modes[0]['slug'] : '';
		$cta               = $this->resolve_transport_cta(
			array( $place['externalUrl'] ?? '' ),
			(array) ( $place['relatedLineIds'] ?? array() ),
			$line_index
		);
		$direction_labels  = $this->build_transport_direction_labels( array( $place ), $line_index );
		$alert_meta        = $this->build_transport_alert_meta_for_places( array( $place ), $line_index );

		return array(
			'id'                 => 'transport-place-' . (int) ( $place['id'] ?? 0 ),
			'source'             => 'transport',
			'sourceLabel'        => __( 'Transport', 'thecore-collectivity-management' ),
			'providerKey'        => isset( $place['providerKey'] ) ? sanitize_key( (string) $place['providerKey'] ) : '',
			'providerLabel'      => isset( $place['providerLabel'] ) ? sanitize_text_field( (string) $place['providerLabel'] ) : '',
			'title'              => $title,
			'permalink'          => '',
			'geometryType'       => TheCore_Collectivity_Maps_Meta::GEOMETRY_POINT,
			'subtitle'           => $subtitle,
			'summary'            => $this->build_summary( $place ),
			'ctaLabel'           => $cta['label'],
			'ctaUrl'             => $cta['url'],
			'displayOrder'       => isset( $place['sortOrder'] ) ? (int) $place['sortOrder'] : 0,
			'accentColor'        => $this->get_mode_color( $primary_mode_slug ),
			'iconKey'            => $primary_mode_slug,
			'defaultIconType'    => $this->get_mode_icon_type( $primary_mode_slug ),
			'imageUrl'           => '',
			'websiteUrl'         => '',
			'socialLinks'        => array(),
			'universes'          => array(),
			'categories'         => array(),
			'themes'             => array(),
			'audiences'          => array(),
			'territories'        => array(),
			'isAccessible'       => ! empty( $place['isAccessible'] ),
			'accessibilityNotes' => $this->build_accessibility_notes( $place ),
			'primaryPosts'       => array(),
			'secondaryPosts'     => array(),
			'latitude'           => isset( $place['latitude'] ) ? (float) $place['latitude'] : null,
			'longitude'          => isset( $place['longitude'] ) ? (float) $place['longitude'] : null,
			'address'            => $address,
			'relatedPointIds'    => array(),
			'relatedPoints'      => array(),
			'geojson'            => null,
			'distanceLabel'      => '',
			'durationLabel'      => '',
			'difficulty'         => '',
			'transportModes'     => $transport_modes,
			'transportMode'      => $primary_mode_slug,
			'transportModeLabels'=> array_values( array_filter( wp_list_pluck( $transport_modes, 'name' ) ) ),
			'transportPlaceIds'  => array_values( array_filter( array( (int) ( $place['id'] ?? 0 ) ) ) ),
			'gtfsStopIds'        => $this->normalize_text_list( $place['gtfsStopIds'] ?? array() ),
			'relatedLineIds'     => array_values( array_filter( array_map( 'intval', (array) ( $place['relatedLineIds'] ?? array() ) ) ) ),
			'relatedLineCodes'   => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $place['relatedLineCodes'] ?? array() ) ) ) ),
			'relatedLineTitles'  => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $place['relatedLineTitles'] ?? array() ) ) ) ),
			'directionLabels'    => $direction_labels,
			'hasTransportAlerts' => ! empty( $alert_meta['hasAlerts'] ),
			'transportAlertSeverity' => $alert_meta['severity'],
			'transportAlertLabels' => $alert_meta['labels'],
			'parkingType'        => isset( $place['parkingType'] ) ? sanitize_text_field( (string) $place['parkingType'] ) : '',
			'totalPlaces'        => $this->normalize_integer( $place['totalPlaces'] ?? null ),
			'availablePlaces'    => $this->normalize_integer( $place['availablePlaces'] ?? null ),
			'pmrPlaces'          => $this->normalize_integer( $place['pmrPlaces'] ?? null ),
			'isFree'             => ! empty( $place['isFree'] ),
			'bikesAvailable'     => $this->normalize_integer( $place['bikesAvailable'] ?? null ),
			'slotsTotal'         => $this->normalize_integer( $place['slotsTotal'] ?? null ),
			'electricBikes'      => $this->normalize_integer( $place['electricBikes'] ?? null ),
			'trainDepartures'    => $this->normalize_text_list( $place['trainDepartures'] ?? array() ),
			'trainInformation'   => $this->normalize_text_list( $place['trainInformation'] ?? array() ),
			'searchText'         => $this->build_search_text( $place, $transport_modes ),
		);
	}

	/**
	 * Normalize a transport line into one MAP-compatible route item.
	 *
	 * @param array $line Transport line payload.
	 * @return array
	 */
	private function normalize_transport_line( array $line, array $places = array() ) {
		$transport_modes   = $this->normalize_transport_modes( $line );
		$primary_mode_slug = ! empty( $transport_modes[0]['slug'] ) ? $transport_modes[0]['slug'] : '';
		$theme             = ! empty( $line['theme'] ) && is_array( $line['theme'] ) ? $line['theme'] : array();
		$accent_color      = ! empty( $theme['color'] ) ? sanitize_hex_color( (string) $theme['color'] ) : '';
		$line_code         = ! empty( $line['lineCode'] ) ? sanitize_text_field( (string) $line['lineCode'] ) : '';
		$route_label       = ! empty( $line['routeLabel'] ) ? sanitize_text_field( (string) $line['routeLabel'] ) : '';
		$summary           = ! empty( $line['timetableSummary'] ) ? sanitize_text_field( (string) $line['timetableSummary'] ) : '';
		$marker            = $this->resolve_transport_line_marker_coordinates( $line, $places );
		$alert_meta        = $this->build_transport_alert_meta_for_line( $line );

		if ( '' === $summary ) {
			$summary = $route_label;
		}

		return array(
			'id'                  => 'transport-line-' . (int) ( $line['id'] ?? 0 ),
			'source'              => 'transport',
			'sourceLabel'         => __( 'Transport', 'thecore-collectivity-management' ),
			'providerKey'         => isset( $line['providerKey'] ) ? sanitize_key( (string) $line['providerKey'] ) : '',
			'providerLabel'       => isset( $line['providerLabel'] ) ? sanitize_text_field( (string) $line['providerLabel'] ) : '',
			'title'               => sanitize_text_field( (string) ( $line['title'] ?? '' ) ),
			'permalink'           => '',
			'geometryType'        => TheCore_Collectivity_Maps_Meta::GEOMETRY_ROUTE,
			'subtitle'            => $route_label,
			'summary'             => $summary,
			'ctaLabel'            => ! empty( $line['mapUrl'] ) ? __( 'Voir la fiche transport', 'thecore-collectivity-management' ) : '',
			'ctaUrl'              => ! empty( $line['mapUrl'] ) ? esc_url_raw( (string) $line['mapUrl'] ) : '',
			'displayOrder'        => isset( $line['sortOrder'] ) ? (int) $line['sortOrder'] : 0,
			'accentColor'         => $accent_color ?: $this->get_mode_color( $primary_mode_slug ),
			'iconKey'             => $primary_mode_slug ?: $line_code,
			'defaultIconType'     => $this->get_mode_icon_type( $primary_mode_slug ),
			'imageUrl'            => '',
			'websiteUrl'          => '',
			'socialLinks'         => array(),
			'universes'           => array(),
			'categories'          => array(),
			'themes'              => array(),
			'audiences'           => array(),
			'territories'         => array(),
			'isAccessible'        => ! empty( $line['isAccessible'] ),
			'accessibilityNotes'  => ! empty( $line['isAccessible'] ) ? __( 'Accessible PMR', 'thecore-collectivity-management' ) : '',
			'primaryPosts'        => array(),
			'secondaryPosts'      => array(),
			'latitude'            => null,
			'longitude'           => null,
			'markerLatitude'      => null !== $marker ? (float) $marker['latitude'] : null,
			'markerLongitude'     => null !== $marker ? (float) $marker['longitude'] : null,
			'hideRouteMarker'     => null !== $marker,
			'address'             => '',
			'relatedPointIds'     => array(),
			'relatedPoints'       => array(),
			'geojson'             => $line['geojson'],
			'distanceLabel'       => '',
			'durationLabel'       => '',
			'difficulty'          => '',
			'transportModes'      => $transport_modes,
			'transportMode'       => $primary_mode_slug,
			'transportModeLabels' => array_values( array_filter( wp_list_pluck( $transport_modes, 'name' ) ) ),
			'transportLineId'     => (int) ( $line['id'] ?? 0 ),
			'relatedLineIds'      => array(),
			'relatedLineCodes'    => array(),
			'relatedLineTitles'   => array(),
			'hasTransportAlerts'  => ! empty( $alert_meta['hasAlerts'] ),
			'transportAlertSeverity' => $alert_meta['severity'],
			'transportAlertLabels' => $alert_meta['labels'],
			'parkingType'         => '',
			'totalPlaces'         => null,
			'availablePlaces'     => null,
			'pmrPlaces'           => null,
			'isFree'              => false,
			'bikesAvailable'      => null,
			'slotsTotal'          => null,
			'electricBikes'       => null,
			'trainDepartures'     => array(),
			'trainInformation'    => array(),
				'searchText'          => sanitize_text_field( (string) ( $line['searchText'] ?? '' ) ),
			);
		}

		/**
		 * Resolve a stable marker position for a transport line from linked local places.
		 *
		 * @param array $line   Normalized transport line payload.
		 * @param array $places Normalized transport place payloads.
		 * @return array|null
		 */
		private function resolve_transport_line_marker_coordinates( array $line, array $places ) {
			$line_id      = isset( $line['id'] ) ? absint( $line['id'] ) : 0;
			$provider_key = isset( $line['providerKey'] ) ? sanitize_key( (string) $line['providerKey'] ) : '';

			if ( $line_id <= 0 || empty( $places ) ) {
				return null;
			}

			foreach ( $places as $place ) {
				$place_provider = isset( $place['providerKey'] ) ? sanitize_key( (string) $place['providerKey'] ) : '';
				if ( '' !== $provider_key && '' !== $place_provider && $provider_key !== $place_provider ) {
					continue;
				}

				$related_lines = array_values( array_filter( array_map( 'intval', (array) ( $place['relatedLineIds'] ?? array() ) ) ) );
				if ( ! in_array( $line_id, $related_lines, true ) ) {
					continue;
				}

				if ( empty( $place['latitude'] ) || empty( $place['longitude'] ) ) {
					continue;
				}

				return array(
					'latitude'  => (float) $place['latitude'],
					'longitude' => (float) $place['longitude'],
				);
			}

			return null;
		}

	/**
	 * Group overlapping transport places into map hubs.
	 *
	 * @param array $places Raw transport places.
	 * @return array
	 */
	private function group_transport_places( array $places ) {
		$groups = array();

		foreach ( $places as $place ) {
			$assigned = false;

			foreach ( $groups as &$group ) {
				if ( $this->can_group_transport_places( $group[0], $place ) ) {
					$group[]   = $place;
					$assigned  = true;
					break;
				}
			}
			unset( $group );

			if ( ! $assigned ) {
				$groups[] = array( $place );
			}
		}

		return $groups;
	}

	/**
	 * Decide whether two transport places should be displayed as one hub on the MAP.
	 *
	 * @param array $left  First place.
	 * @param array $right Second place.
	 * @return bool
	 */
	private function can_group_transport_places( array $left, array $right ) {
		$left_title  = $this->get_transport_base_title( $left['title'] ?? '' );
		$right_title = $this->get_transport_base_title( $right['title'] ?? '' );
		if ( '' === $left_title || '' === $right_title || 0 !== strcasecmp( $left_title, $right_title ) ) {
			return false;
		}

		$left_provider  = isset( $left['providerKey'] ) ? sanitize_key( (string) $left['providerKey'] ) : '';
		$right_provider = isset( $right['providerKey'] ) ? sanitize_key( (string) $right['providerKey'] ) : '';
		if ( $left_provider !== $right_provider ) {
			return false;
		}

		$left_mode  = isset( $left['mode'] ) ? sanitize_key( (string) $left['mode'] ) : '';
		$right_mode = isset( $right['mode'] ) ? sanitize_key( (string) $right['mode'] ) : '';
		if ( '' !== $left_mode && '' !== $right_mode && $left_mode !== $right_mode ) {
			return false;
		}

		$left_lat  = isset( $left['latitude'] ) ? (float) $left['latitude'] : null;
		$left_lng  = isset( $left['longitude'] ) ? (float) $left['longitude'] : null;
		$right_lat = isset( $right['latitude'] ) ? (float) $right['latitude'] : null;
		$right_lng = isset( $right['longitude'] ) ? (float) $right['longitude'] : null;
		if ( null === $left_lat || null === $left_lng || null === $right_lat || null === $right_lng ) {
			return false;
		}

		$distance = sqrt( pow( $left_lat - $right_lat, 2 ) + pow( $left_lng - $right_lng, 2 ) );
		return $distance <= 0.0002;
	}

	/**
	 * Normalize one grouped transport hub into one MAP point item.
	 *
	 * @param array $places Grouped transport places.
	 * @return array
	 */
	private function normalize_transport_group( array $places, array $line_index = array() ) {
		$reference_places   = $this->get_transport_reference_places( $places );
		$primary_place      = reset( $reference_places );
		$transport_modes    = $this->merge_transport_modes( $places );
		$primary_mode_slug  = ! empty( $transport_modes[0]['slug'] ) ? $transport_modes[0]['slug'] : '';
		$related_line_ids   = $this->merge_unique_int_lists( $reference_places, 'relatedLineIds' );
		$related_line_codes = $this->merge_unique_text_lists( $reference_places, 'relatedLineCodes' );
		$related_line_titles = $this->merge_unique_text_lists( $reference_places, 'relatedLineTitles' );
		$direction_labels   = $this->build_transport_direction_labels( $reference_places, $line_index );
		$alert_meta         = $this->build_transport_alert_meta_for_places( $reference_places, $line_index );
		$cta                = $this->resolve_transport_cta(
			$this->merge_unique_text_lists( $reference_places, 'externalUrl' ),
			$related_line_ids,
			$line_index
		);
		$coordinates        = $this->get_group_coordinates( $reference_places );
		$base_title         = $this->get_transport_base_title( $primary_place['title'] ?? '' );
		$child_count        = count( $reference_places );
		$summary_parts      = array();

		if ( $child_count > 1 ) {
			$summary_parts[] = sprintf(
				/* translators: %d: number of transport bays/stops grouped on the map */
				_n( '%d quai', '%d quais', $child_count, 'thecore-collectivity-management' ),
				$child_count
			);
		}

		if ( ! empty( $related_line_codes ) ) {
			$summary_parts[] = sprintf(
				/* translators: %s: list of line codes */
				__( 'Ligne %s', 'thecore-collectivity-management' ),
				implode( ', ', $related_line_codes )
			);
		}

		if ( empty( $summary_parts ) && ! empty( $primary_place['subtitle'] ) ) {
			$summary_parts[] = sanitize_text_field( (string) $primary_place['subtitle'] );
		}

		$search_parts = array(
			$base_title,
			$primary_place['address'] ?? '',
			implode( ' ', wp_list_pluck( $transport_modes, 'name' ) ),
			implode( ' ', $related_line_codes ),
			implode( ' ', $related_line_titles ),
		);

		return array(
			'id'                  => 'transport-hub-' . md5( implode( '|', wp_list_pluck( $places, 'id' ) ) ),
			'source'              => 'transport',
			'sourceLabel'         => __( 'Transport', 'thecore-collectivity-management' ),
			'providerKey'         => isset( $primary_place['providerKey'] ) ? sanitize_key( (string) $primary_place['providerKey'] ) : '',
			'providerLabel'       => isset( $primary_place['providerLabel'] ) ? sanitize_text_field( (string) $primary_place['providerLabel'] ) : '',
			'title'               => $base_title ?: sanitize_text_field( (string) ( $primary_place['title'] ?? '' ) ),
			'permalink'           => '',
			'geometryType'        => TheCore_Collectivity_Maps_Meta::GEOMETRY_POINT,
			'subtitle'            => sanitize_text_field( (string) ( $primary_place['subtitle'] ?? '' ) ),
			'summary'             => implode( ' · ', array_filter( $summary_parts ) ),
			'ctaLabel'            => $cta['label'],
			'ctaUrl'              => $cta['url'],
			'displayOrder'        => isset( $primary_place['sortOrder'] ) ? (int) $primary_place['sortOrder'] : 0,
			'accentColor'         => $this->get_mode_color( $primary_mode_slug ),
			'iconKey'             => $primary_mode_slug,
			'defaultIconType'     => $this->get_mode_icon_type( $primary_mode_slug ),
			'imageUrl'            => '',
			'websiteUrl'          => '',
			'socialLinks'         => array(),
			'universes'           => array(),
			'categories'          => array(),
			'themes'              => array(),
			'audiences'           => array(),
			'territories'         => array(),
			'isAccessible'        => ! empty( array_filter( wp_list_pluck( $places, 'isAccessible' ) ) ),
			'accessibilityNotes'  => $this->build_group_accessibility_notes( $places ),
			'primaryPosts'        => array(),
			'secondaryPosts'      => array(),
			'latitude'            => $coordinates['latitude'],
			'longitude'           => $coordinates['longitude'],
			'address'             => sanitize_text_field( (string) ( $primary_place['address'] ?? '' ) ),
			'relatedPointIds'     => array(),
			'relatedPoints'       => array(),
			'geojson'             => null,
			'distanceLabel'       => '',
			'durationLabel'       => '',
			'difficulty'          => '',
			'transportModes'      => $transport_modes,
			'transportMode'       => $primary_mode_slug,
			'transportModeLabels' => array_values( array_filter( wp_list_pluck( $transport_modes, 'name' ) ) ),
			'transportPlaceIds'   => array_values( array_filter( array_map( 'intval', wp_list_pluck( $reference_places, 'id' ) ) ) ),
			'gtfsStopIds'         => $this->merge_unique_text_lists( $reference_places, 'gtfsStopIds' ),
			'relatedLineIds'      => $related_line_ids,
			'relatedLineCodes'    => $related_line_codes,
			'relatedLineTitles'   => $related_line_titles,
			'directionLabels'     => $direction_labels,
			'hasTransportAlerts'  => ! empty( $alert_meta['hasAlerts'] ),
			'transportAlertSeverity' => $alert_meta['severity'],
			'transportAlertLabels' => $alert_meta['labels'],
			'parkingType'         => '',
			'totalPlaces'         => null,
			'availablePlaces'     => null,
			'pmrPlaces'           => null,
			'isFree'              => false,
			'bikesAvailable'      => null,
			'slotsTotal'          => null,
			'electricBikes'       => null,
			'trainDepartures'     => $this->merge_unique_text_lists( $places, 'trainDepartures' ),
			'trainInformation'    => $this->merge_unique_text_lists( $places, 'trainInformation' ),
			'searchText'          => implode( ' ', array_filter( $search_parts ) ),
		);
	}

	/**
	 * Get the most useful transport children inside one grouped hub.
	 *
	 * @param array $places Grouped places.
	 * @return array
	 */
	private function get_transport_reference_places( array $places ) {
		$with_lines = array_values(
			array_filter(
				$places,
				static function ( $place ) {
					return ! empty( $place['relatedLineIds'] ) || ! empty( $place['relatedLineCodes'] );
				}
			)
		);

		return ! empty( $with_lines ) ? $with_lines : $places;
	}

	/**
	 * Resolve the best CTA for one transport stop or grouped hub.
	 *
	 * @param array $external_urls    Candidate external urls.
	 * @param array $related_line_ids Related line ids.
	 * @param array $line_index       Indexed transport line payloads.
	 * @return array
	 */
	private function resolve_transport_cta( array $external_urls, array $related_line_ids, array $line_index ) {
		foreach ( $external_urls as $external_url ) {
			$external_url = esc_url_raw( (string) $external_url );
			if ( '' === $external_url ) {
				continue;
			}

			return array(
				'label' => __( 'Voir la fiche transport', 'thecore-collectivity-management' ),
				'url'   => $external_url,
			);
		}

		$related_line_ids = array_values( array_unique( array_filter( array_map( 'intval', $related_line_ids ) ) ) );
		if ( 1 !== count( $related_line_ids ) ) {
			return array(
				'label' => '',
				'url'   => '',
			);
		}

		$line_id = (int) $related_line_ids[0];
		if ( empty( $line_index[ $line_id ]['mapUrl'] ) ) {
			return array(
				'label' => '',
				'url'   => '',
			);
		}

		$line_url = esc_url_raw( (string) $line_index[ $line_id ]['mapUrl'] );
		if ( '' === $line_url ) {
			return array(
				'label' => '',
				'url'   => '',
			);
		}

		return array(
			'label' => __( 'Voir la fiche transport', 'thecore-collectivity-management' ),
			'url'   => $line_url,
		);
	}

	/**
	 * Build direction/terminus labels for one stop or grouped hub.
	 *
	 * @param array $places      Grouped transport places.
	 * @param array $line_index  Indexed transport line payloads.
	 * @return array
	 */
	private function build_transport_direction_labels( array $places, array $line_index ) {
		$destination_labels = $this->merge_unique_text_lists( $places, 'destinationLabels' );
		if ( ! empty( $destination_labels ) ) {
			natcasesort( $destination_labels );
			return array_values( $destination_labels );
		}

		$stop_ids          = array();
		$related_line_ids  = array();
		$direction_labels  = array();

		foreach ( $places as $place ) {
			$stop_ids         = array_merge( $stop_ids, array_map( 'sanitize_text_field', (array) ( $place['gtfsStopIds'] ?? array() ) ) );
			$related_line_ids = array_merge( $related_line_ids, array_map( 'intval', (array) ( $place['relatedLineIds'] ?? array() ) ) );
		}

		$stop_ids         = array_values( array_unique( array_filter( $stop_ids ) ) );
		$related_line_ids = array_values( array_unique( array_filter( $related_line_ids ) ) );
		if ( empty( $stop_ids ) || empty( $related_line_ids ) ) {
			return array();
		}

		foreach ( $related_line_ids as $line_id ) {
			$stops_payload = $line_index[ $line_id ]['schedule']['stops'] ?? array();
			if ( ! is_array( $stops_payload ) ) {
				continue;
			}

			foreach ( $stops_payload as $stop_payload ) {
				$stop_id = isset( $stop_payload['stopId'] ) ? sanitize_text_field( (string) $stop_payload['stopId'] ) : '';
				if ( '' === $stop_id || ! in_array( $stop_id, $stop_ids, true ) ) {
					continue;
				}

				foreach ( (array) ( $stop_payload['directions'] ?? array() ) as $direction_payload ) {
					$label = $this->get_transport_direction_destination_label( $direction_payload );
					if ( '' === $label ) {
						continue;
					}

					$direction_labels[ $label ] = $label;
				}
			}
		}

		natcasesort( $direction_labels );
		return array_values( $direction_labels );
	}

	/**
	 * Extract the effective destination/terminus label from one direction payload.
	 *
	 * @param array $direction_payload Direction payload.
	 * @return string
	 */
	private function get_transport_direction_destination_label( array $direction_payload ) {
		$headsign = isset( $direction_payload['headsign'] ) ? trim( (string) $direction_payload['headsign'] ) : '';
		if ( '' === $headsign ) {
			return '';
		}

		$segments = preg_split( '/\s*(?:→|->)\s*/u', $headsign );
		if ( is_array( $segments ) && count( $segments ) > 1 ) {
			$destination = trim( (string) end( $segments ) );
			if ( '' !== $destination ) {
				return sanitize_text_field( $destination );
			}
		}

		return sanitize_text_field( $headsign );
	}

	/**
	 * Build a compact alert meta payload for one normalized transport line.
	 *
	 * @param array $line Normalized transport line payload.
	 * @return array
	 */
	private function build_transport_alert_meta_for_line( array $line ) {
		$alerts = $line['schedule']['realtime']['alerts'] ?? array();
		if ( ! is_array( $alerts ) || empty( $alerts ) ) {
			return $this->get_empty_transport_alert_meta();
		}

		return $this->build_transport_alert_meta_from_alerts( $alerts );
	}

	/**
	 * Build alert meta for a set of transport places from related line payloads.
	 *
	 * @param array $places     Transport place payloads.
	 * @param array $line_index Indexed transport line payloads.
	 * @return array
	 */
	private function build_transport_alert_meta_for_places( array $places, array $line_index ) {
		$related_line_ids = $this->merge_unique_int_lists( $places, 'relatedLineIds' );
		$stop_ids         = $this->merge_unique_text_lists( $places, 'gtfsStopIds' );
		$alerts           = array();

		foreach ( $related_line_ids as $line_id ) {
			$line_alerts = $line_index[ $line_id ]['schedule']['realtime']['alerts'] ?? array();
			if ( ! is_array( $line_alerts ) || empty( $line_alerts ) ) {
				continue;
			}

			foreach ( $line_alerts as $alert ) {
				if ( ! is_array( $alert ) ) {
					continue;
				}

				$alert_stop_ids = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $alert['stopIds'] ?? array() ) ) ) );
				if ( ! empty( $alert_stop_ids ) && ! array_intersect( $alert_stop_ids, $stop_ids ) ) {
					continue;
				}

				$alerts[] = $alert;
			}
		}

		return $this->build_transport_alert_meta_from_alerts( $alerts );
	}

	/**
	 * Build alert meta from raw normalized alerts.
	 *
	 * @param array $alerts Alert payloads.
	 * @return array
	 */
	private function build_transport_alert_meta_from_alerts( array $alerts ) {
		if ( empty( $alerts ) ) {
			return $this->get_empty_transport_alert_meta();
		}

		$severity_rank = array(
			'critical' => 4,
			'warning'  => 3,
			'info'     => 2,
		);
		$severity      = 'info';
		$labels        = array();

		foreach ( $alerts as $alert ) {
			if ( ! is_array( $alert ) ) {
				continue;
			}

			$alert_severity = ! empty( $alert['severity'] ) ? sanitize_key( (string) $alert['severity'] ) : 'info';
			if ( ( $severity_rank[ $alert_severity ] ?? 1 ) > ( $severity_rank[ $severity ] ?? 1 ) ) {
				$severity = $alert_severity;
			}

			$label = ! empty( $alert['effectLabel'] ) ? (string) $alert['effectLabel'] : (string) ( $alert['headerText'] ?? '' );
			$label = sanitize_text_field( $label );
			if ( '' !== $label ) {
				$labels[ $label ] = $label;
			}
		}

		return array(
			'hasAlerts' => true,
			'severity'  => $severity,
			'labels'    => array_slice( array_values( $labels ), 0, 3 ),
		);
	}

	/**
	 * Empty alert meta payload.
	 *
	 * @return array
	 */
	private function get_empty_transport_alert_meta() {
		return array(
			'hasAlerts' => false,
			'severity'  => '',
			'labels'    => array(),
		);
	}

	/**
	 * Merge transport modes from multiple grouped places.
	 *
	 * @param array $places Grouped places.
	 * @return array
	 */
	private function merge_transport_modes( array $places ) {
		$index = array();

		foreach ( $places as $place ) {
			foreach ( $this->normalize_transport_modes( $place ) as $mode ) {
				if ( empty( $mode['slug'] ) ) {
					continue;
				}

				$index[ $mode['slug'] ] = $mode;
			}
		}

		return array_values( $index );
	}

	/**
	 * Merge unique integer lists from grouped places.
	 *
	 * @param array  $places Grouped places.
	 * @param string $key    Array key.
	 * @return array
	 */
	private function merge_unique_int_lists( array $places, $key ) {
		$values = array();

		foreach ( $places as $place ) {
			$values = array_merge( $values, array_map( 'intval', (array) ( $place[ $key ] ?? array() ) ) );
		}

		return array_values( array_unique( array_filter( $values ) ) );
	}

	/**
	 * Merge unique text lists from grouped places.
	 *
	 * @param array  $places Grouped places.
	 * @param string $key    Array key.
	 * @return array
	 */
	private function merge_unique_text_lists( array $places, $key ) {
		$values = array();

		foreach ( $places as $place ) {
			$raw = $place[ $key ] ?? array();
			if ( ! is_array( $raw ) ) {
				$raw = array( $raw );
			}

			$values = array_merge( $values, array_map( 'sanitize_text_field', $raw ) );
		}

		return array_values( array_filter( array_unique( $values ) ) );
	}

	/**
	 * Compute average coordinates for one grouped hub.
	 *
	 * @param array $places Grouped places.
	 * @return array
	 */
	private function get_group_coordinates( array $places ) {
		$latitudes  = array();
		$longitudes = array();

		foreach ( $places as $place ) {
			if ( isset( $place['latitude'] ) && isset( $place['longitude'] ) ) {
				$latitudes[]  = (float) $place['latitude'];
				$longitudes[] = (float) $place['longitude'];
			}
		}

		if ( empty( $latitudes ) || empty( $longitudes ) ) {
			return array(
				'latitude'  => null,
				'longitude' => null,
			);
		}

		return array(
			'latitude'  => array_sum( $latitudes ) / count( $latitudes ),
			'longitude' => array_sum( $longitudes ) / count( $longitudes ),
		);
	}

	/**
	 * Build one transport-hub accessibility note.
	 *
	 * @param array $places Grouped places.
	 * @return string
	 */
	private function build_group_accessibility_notes( array $places ) {
		$notes = array();

		foreach ( $places as $place ) {
			$note = $this->build_accessibility_notes( $place );
			if ( '' !== $note ) {
				$notes[] = $note;
			}
		}

		return implode( ' · ', array_values( array_unique( $notes ) ) );
	}

	/**
	 * Remove duplicate suffixes from one transport stop title.
	 *
	 * @param string $title Raw title.
	 * @return string
	 */
	private function get_transport_base_title( $title ) {
		$title = sanitize_text_field( (string) $title );
		return trim( preg_replace( '/\s*\([^)]*\)\s*$/', '', $title ) );
	}

	/**
	 * Normalize transport modes into term-like payloads.
	 *
	 * @param array $place Transport place payload.
	 * @return array
	 */
	private function normalize_transport_modes( array $place ) {
		$slugs  = isset( $place['modes'] ) && is_array( $place['modes'] ) ? array_values( array_filter( array_map( 'sanitize_key', $place['modes'] ) ) ) : array();
		$labels = isset( $place['modeLabels'] ) && is_array( $place['modeLabels'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', $place['modeLabels'] ) ) ) : array();
		$modes  = array();

		foreach ( $slugs as $index => $slug ) {
			$modes[] = array(
				'slug' => $slug,
				'name' => $labels[ $index ] ?? ucfirst( $slug ),
			);
		}

		if ( empty( $modes ) && ! empty( $place['mode'] ) ) {
			$slug    = sanitize_key( (string) $place['mode'] );
			$label   = ! empty( $place['modeLabels'][0] ) ? sanitize_text_field( (string) $place['modeLabels'][0] ) : ucfirst( $slug );
			$modes[] = array(
				'slug' => $slug,
				'name' => $label,
			);
		}

		return $modes;
	}

	/**
	 * Normalize transport mode filters.
	 *
	 * @param mixed $modes Raw mode values.
	 * @return array
	 */
	private function normalize_mode_filters( $modes ) {
		$values = array_values(
			array_filter(
				array_map( 'sanitize_key', (array) $modes )
			)
		);

		if ( empty( $values ) || in_array( '__all__', $values, true ) ) {
			return array();
		}

		return array_values( array_unique( $values ) );
	}

	/**
	 * Check whether one transport place matches the requested mode filters.
	 *
	 * @param array $place Transport place payload.
	 * @param array $modes Filtered modes.
	 * @return bool
	 */
	private function matches_mode_filter( array $place, array $modes ) {
		if ( empty( $modes ) ) {
			return true;
		}

		$place_modes = isset( $place['modes'] ) && is_array( $place['modes'] ) ? array_map( 'sanitize_key', $place['modes'] ) : array();

		return ! empty( array_intersect( $modes, $place_modes ) );
	}

	/**
	 * Build a compact transport summary.
	 *
	 * @param array $place Transport place payload.
	 * @return string
	 */
	private function build_summary( array $place ) {
		$mode = isset( $place['mode'] ) ? sanitize_key( (string) $place['mode'] ) : '';

		switch ( $mode ) {
			case TheCore_Collectivity_Transports_Post_Types::MODE_PARKING:
				if ( ! empty( $place['parkingType'] ) ) {
					return sanitize_text_field( (string) $place['parkingType'] );
				}

				if ( null !== $this->normalize_integer( $place['totalPlaces'] ?? null ) ) {
					return sprintf(
						/* translators: %d: number of parking spaces */
						__( '%d places', 'thecore-collectivity-management' ),
						(int) $place['totalPlaces']
					);
				}
				break;

			case TheCore_Collectivity_Transports_Post_Types::MODE_BIKE:
				if ( null !== $this->normalize_integer( $place['slotsTotal'] ?? null ) ) {
					return sprintf(
						/* translators: %d: total bike slots */
						__( '%d emplacements vélos', 'thecore-collectivity-management' ),
						(int) $place['slotsTotal']
					);
				}
				break;

			case TheCore_Collectivity_Transports_Post_Types::MODE_TRAIN:
				if ( ! empty( $place['trainInformation'][0] ) ) {
					return sanitize_text_field( (string) $place['trainInformation'][0] );
				}
				break;
		}

		if ( ! empty( $place['subtitle'] ) ) {
			return sanitize_text_field( (string) $place['subtitle'] );
		}

		return '';
	}

	/**
	 * Build accessibility notes from transport payload.
	 *
	 * @param array $place Transport place payload.
	 * @return string
	 */
	private function build_accessibility_notes( array $place ) {
		$notes = array();

		if ( ! empty( $place['isAccessible'] ) ) {
			$notes[] = __( 'Accessible PMR', 'thecore-collectivity-management' );
		}

		$pmr_places = $this->normalize_integer( $place['pmrPlaces'] ?? null );
		if ( null !== $pmr_places && $pmr_places > 0 ) {
			$notes[] = sprintf(
				/* translators: %d: number of PMR places */
				__( '%d place(s) PMR', 'thecore-collectivity-management' ),
				$pmr_places
			);
		}

		return implode( ' · ', $notes );
	}

	/**
	 * Build search text for one adapted transport place.
	 *
	 * @param array $place Transport payload.
	 * @param array $transport_modes Normalized transport modes.
	 * @return string
	 */
	private function build_search_text( array $place, array $transport_modes ) {
		$parts = array(
			$place['title'] ?? '',
			$place['subtitle'] ?? '',
			$place['address'] ?? '',
			$place['parkingType'] ?? '',
		);

		$parts = array_merge(
			$parts,
			wp_list_pluck( $transport_modes, 'name' ),
			(array) ( $place['relatedLineCodes'] ?? array() ),
			(array) ( $place['relatedLineTitles'] ?? array() ),
			(array) ( $place['trainDepartures'] ?? array() ),
			(array) ( $place['trainInformation'] ?? array() )
		);

		return implode( ' ', array_filter( array_map( 'sanitize_text_field', $parts ) ) );
	}

	/**
	 * Normalize integer-like values.
	 *
	 * @param mixed $value Raw value.
	 * @return int|null
	 */
	private function normalize_integer( $value ) {
		if ( '' === (string) $value || null === $value ) {
			return null;
		}

		return (int) $value;
	}

	/**
	 * Normalize line-based text arrays.
	 *
	 * @param mixed $value Raw list.
	 * @return array
	 */
	private function normalize_text_list( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'sanitize_text_field', $value ) ) );
	}

	/**
	 * Get the default display color for one transport mode.
	 *
	 * @param string $mode_slug Transport mode slug.
	 * @return string
	 */
	private function get_mode_color( $mode_slug ) {
		switch ( $mode_slug ) {
			case TheCore_Collectivity_Transports_Post_Types::MODE_BUS:
				return '#1d4ed8';
			case TheCore_Collectivity_Transports_Post_Types::MODE_PARKING:
				return '#334155';
			case TheCore_Collectivity_Transports_Post_Types::MODE_BIKE:
				return '#059669';
			case TheCore_Collectivity_Transports_Post_Types::MODE_TRAIN:
				return '#7c3aed';
			default:
				return '#0f766e';
		}
	}

	/**
	 * Get the default icon type for one transport mode.
	 *
	 * @param string $mode_slug Transport mode slug.
	 * @return string
	 */
	private function get_mode_icon_type( $mode_slug ) {
		switch ( $mode_slug ) {
			case TheCore_Collectivity_Transports_Post_Types::MODE_BUS:
				return 'bus';
			case TheCore_Collectivity_Transports_Post_Types::MODE_PARKING:
				return 'parking';
			case TheCore_Collectivity_Transports_Post_Types::MODE_BIKE:
				return 'bike';
			case TheCore_Collectivity_Transports_Post_Types::MODE_TRAIN:
				return 'train';
			default:
				return 'info';
		}
	}
}
