<?php
/**
 * Maps payload normalizer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Maps_Normalizer {
	/**
	 * Repository.
	 *
	 * @var TheCore_Collectivity_Maps_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param TheCore_Collectivity_Maps_Repository $repository Repository instance.
	 */
	public function __construct( TheCore_Collectivity_Maps_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Build widget payload.
	 *
	 * @param array $filters Widget-side filters.
	 * @return array
	 */
	public function get_widget_payload( array $filters = array() ) {
		$posts       = $this->repository->get_items( $filters );
		$items       = array();
		$point_index = array();

		foreach ( $posts as $post ) {
			$item = $this->normalize_item( $post );
			$items[] = $item;

			if ( TheCore_Collectivity_Maps_Meta::GEOMETRY_POINT === $item['geometryType'] ) {
				$point_index[ $item['id'] ] = array(
					'id'        => $item['id'],
					'title'     => $item['title'],
					'subtitle'  => $item['subtitle'],
					'latitude'  => $item['latitude'],
					'longitude' => $item['longitude'],
				);
			}
		}

		foreach ( $items as &$item ) {
			if ( TheCore_Collectivity_Maps_Meta::GEOMETRY_ROUTE !== $item['geometryType'] || empty( $item['relatedPointIds'] ) ) {
				$item['relatedPoints'] = array();
				continue;
			}

			$related_points = array();
			foreach ( $item['relatedPointIds'] as $point_id ) {
				if ( ! empty( $point_index[ $point_id ] ) ) {
					$related_points[] = $point_index[ $point_id ];
				}
			}

			$item['relatedPoints'] = $related_points;
		}
		unset( $item );

		return array(
			'items'  => $items,
			'facets' => $this->build_facets( $items ),
			'stats'  => array(
				'total'  => count( $items ),
				'points' => count(
					array_filter(
						$items,
						static function ( $item ) {
							return TheCore_Collectivity_Maps_Meta::GEOMETRY_POINT === $item['geometryType'];
						}
					)
				),
				'routes' => count(
					array_filter(
						$items,
						static function ( $item ) {
							return TheCore_Collectivity_Maps_Meta::GEOMETRY_ROUTE === $item['geometryType'];
						}
					)
				),
			),
		);
	}

	/**
	 * Normalize one map item.
	 *
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	private function normalize_item( $post ) {
		$geometry_type = (string) get_post_meta( $post->ID, TheCore_Collectivity_Maps_Meta::META_GEOMETRY_TYPE, true );
		$geometry_type = TheCore_Collectivity_Maps_Meta::GEOMETRY_ROUTE === $geometry_type ? TheCore_Collectivity_Maps_Meta::GEOMETRY_ROUTE : TheCore_Collectivity_Maps_Meta::GEOMETRY_POINT;

		$universes   = $this->repository->get_term_payload( $post->ID, TheCore_Collectivity_Maps_Post_Type::TAXONOMY_UNIVERSE );
		$categories  = $this->repository->get_term_payload( $post->ID, TheCore_Collectivity_Maps_Post_Type::TAXONOMY_CATEGORY );
		$themes      = taxonomy_exists( 'tccm_theme' ) ? $this->repository->get_term_payload( $post->ID, 'tccm_theme' ) : array();
		$audiences   = taxonomy_exists( 'tccm_audience' ) ? $this->repository->get_term_payload( $post->ID, 'tccm_audience' ) : array();
		$territories = taxonomy_exists( 'tccm_territory' ) ? $this->repository->get_term_payload( $post->ID, 'tccm_territory' ) : array();

		$title       = get_the_title( $post );
		$subtitle    = (string) get_post_meta( $post->ID, TheCore_Collectivity_Maps_Meta::META_SUBTITLE, true );
		$summary     = (string) get_post_meta( $post->ID, TheCore_Collectivity_Maps_Meta::META_SUMMARY, true );
		$summary     = '' !== $summary ? $summary : wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 28 );
		$website_url = (string) get_post_meta( $post->ID, TheCore_Collectivity_Maps_Meta::META_WEBSITE_URL, true );
		$accent      = $this->normalize_color( (string) get_post_meta( $post->ID, TheCore_Collectivity_Maps_Meta::META_ACCENT_COLOR, true ) );
		$icon_key    = sanitize_key( (string) get_post_meta( $post->ID, TheCore_Collectivity_Maps_Meta::META_ICON_KEY, true ) );
		$cta_label   = (string) get_post_meta( $post->ID, TheCore_Collectivity_Maps_Meta::META_CTA_LABEL, true );
		$cta_url     = (string) get_post_meta( $post->ID, TheCore_Collectivity_Maps_Meta::META_CTA_URL, true );
		$thumbnail   = get_the_post_thumbnail_url( $post, 'medium_large' );
		$access_terms = $this->repository->get_term_payload( $post->ID, TheCore_Collectivity_Maps_Post_Type::TAXONOMY_ACCESSIBILITY );
		$is_accessible = in_array(
			TheCore_Collectivity_Maps_Post_Type::TERM_ACCESSIBLE,
			wp_list_pluck( $access_terms, 'slug' ),
			true
		);
		$primary_posts   = $this->get_related_posts_payload( get_post_meta( $post->ID, TheCore_Collectivity_Maps_Meta::META_PRIMARY_POSTS, true ), true );
		$secondary_posts = $this->get_related_posts_payload(
			get_post_meta( $post->ID, TheCore_Collectivity_Maps_Meta::META_SECONDARY_POSTS, true ),
			false,
			wp_list_pluck( $primary_posts, 'id' )
		);

		$item = array(
			'id'                 => (int) $post->ID,
			'title'              => $title,
			'permalink'          => get_permalink( $post ),
			'geometryType'       => $geometry_type,
			'subtitle'           => $subtitle,
			'summary'            => $summary,
			'ctaLabel'           => $cta_label,
			'ctaUrl'             => $cta_url,
			'displayOrder'       => (int) get_post_meta( $post->ID, TheCore_Collectivity_Maps_Meta::META_DISPLAY_ORDER, true ),
			'accentColor'        => $accent ? $accent : ( TheCore_Collectivity_Maps_Meta::GEOMETRY_ROUTE === $geometry_type ? '#1d4ed8' : '#2f855a' ),
			'iconKey'            => $icon_key,
			'imageUrl'           => $thumbnail ? (string) $thumbnail : '',
			'websiteUrl'         => $website_url,
			'socialLinks'        => $this->normalize_social_links( get_post_meta( $post->ID, TheCore_Collectivity_Maps_Meta::META_SOCIAL_LINKS, true ) ),
			'universes'          => $universes,
			'categories'         => $categories,
			'themes'             => $themes,
			'audiences'          => $audiences,
			'territories'        => $territories,
			'isAccessible'       => $is_accessible,
			'accessibilityNotes' => (string) get_post_meta( $post->ID, TheCore_Collectivity_Maps_Meta::META_ACCESSIBILITY_NOTES, true ),
			'primaryPosts'       => $primary_posts,
			'secondaryPosts'     => $secondary_posts,
			'searchText'         => $this->build_search_text(
				array_merge(
					array(
						$title,
						$subtitle,
						$summary,
					),
					wp_list_pluck( $universes, 'name' ),
					wp_list_pluck( $categories, 'name' ),
					wp_list_pluck( $themes, 'name' ),
					wp_list_pluck( $audiences, 'name' ),
					wp_list_pluck( $territories, 'name' )
				)
			),
		);

		if ( TheCore_Collectivity_Maps_Meta::GEOMETRY_POINT === $geometry_type ) {
			$item['latitude']  = $this->normalize_coordinate( get_post_meta( $post->ID, TheCore_Collectivity_Maps_Meta::META_LATITUDE, true ) );
			$item['longitude'] = $this->normalize_coordinate( get_post_meta( $post->ID, TheCore_Collectivity_Maps_Meta::META_LONGITUDE, true ) );
			$item['address']   = (string) get_post_meta( $post->ID, TheCore_Collectivity_Maps_Meta::META_ADDRESS, true );
			$item['relatedPointIds'] = array();
			$item['geojson'] = null;
			$item['distanceLabel'] = '';
			$item['durationLabel'] = '';
			$item['difficulty'] = '';
			$item['searchText'] .= ' ' . $item['address'];
		} else {
			$item['latitude']        = null;
			$item['longitude']       = null;
			$item['address']         = '';
			$item['geojson']         = $this->decode_geojson( (string) get_post_meta( $post->ID, TheCore_Collectivity_Maps_Meta::META_GEOJSON, true ) );
			$item['distanceLabel']   = (string) get_post_meta( $post->ID, TheCore_Collectivity_Maps_Meta::META_DISTANCE_LABEL, true );
			$item['durationLabel']   = (string) get_post_meta( $post->ID, TheCore_Collectivity_Maps_Meta::META_DURATION_LABEL, true );
			$item['difficulty']      = (string) get_post_meta( $post->ID, TheCore_Collectivity_Maps_Meta::META_DIFFICULTY, true );
			$item['relatedPointIds'] = $this->normalize_related_point_ids( get_post_meta( $post->ID, TheCore_Collectivity_Maps_Meta::META_RELATED_POINT_IDS, true ) );
			$item['searchText']     .= ' ' . $item['distanceLabel'] . ' ' . $item['durationLabel'] . ' ' . $item['difficulty'];
		}

		return $item;
	}

	/**
	 * Normalize related post rows for popup consumption.
	 *
	 * @param mixed $value       Raw meta value.
	 * @param bool  $with_label  Whether the relation exposes a custom button label.
	 * @param array $exclude_ids Post IDs to exclude.
	 * @return array
	 */
	private function get_related_posts_payload( $value, $with_label, array $exclude_ids = array() ) {
		$rows = is_array( $value ) ? $value : array();
		$prepared = array();
		$seen = array_fill_keys( array_map( 'intval', $exclude_ids ), true );

		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$post_id = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
			if ( $post_id <= 0 || isset( $seen[ $post_id ] ) ) {
				continue;
			}

			$seen[ $post_id ] = true;
			$prepared[] = array(
				'post_id' => $post_id,
				'label'   => $with_label ? sanitize_text_field( $row['label'] ?? '' ) : '',
				'order'   => isset( $row['order'] ) ? (int) $row['order'] : 0,
				'index'   => (int) $index,
			);
		}

		if ( empty( $prepared ) ) {
			return array();
		}

		usort(
			$prepared,
			static function ( $left, $right ) {
				return ( $left['order'] <=> $right['order'] ) ?: ( $left['index'] <=> $right['index'] );
			}
		);

		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'post__in'       => wp_list_pluck( $prepared, 'post_id' ),
			)
		);

		$post_index = array();
		foreach ( $posts as $related_post ) {
			$post_index[ (int) $related_post->ID ] = $related_post;
		}

		$payload = array();
		foreach ( $prepared as $row ) {
			if ( empty( $post_index[ $row['post_id'] ] ) ) {
				continue;
			}

			$related_post = $post_index[ $row['post_id'] ];
			$title = get_the_title( $related_post );

			$payload[] = array(
				'id'    => (int) $related_post->ID,
				'title' => $title,
				'url'   => get_permalink( $related_post ),
				'label' => $with_label && '' !== $row['label'] ? $row['label'] : $title,
			);
		}

		return $payload;
	}

	/**
	 * Normalize social links for frontend.
	 *
	 * @param mixed $value Raw meta value.
	 * @return array
	 */
	private function normalize_social_links( $value ) {
		$rows = is_array( $value ) ? $value : array();
		$payload = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$network = isset( $row['network'] ) ? sanitize_key( (string) $row['network'] ) : '';
			$url     = isset( $row['url'] ) ? esc_url_raw( (string) $row['url'] ) : '';

			if ( '' === $network || '' === $url || ! isset( TheCore_Collectivity_Maps_Meta::SOCIAL_NETWORKS[ $network ] ) ) {
				continue;
			}

			$payload[] = array(
				'network' => $network,
				'label'   => TheCore_Collectivity_Maps_Meta::SOCIAL_NETWORKS[ $network ],
				'url'     => $url,
			);
		}

		return $payload;
	}

	/**
	 * Build frontend facets from the loaded items.
	 *
	 * @param array $items Normalized items.
	 * @return array
	 */
	private function build_facets( array $items ) {
		$facets = array(
			'universes'   => array(),
			'categories'  => array(),
			'themes'      => array(),
			'audiences'   => array(),
			'territories' => array(),
		);

		foreach ( $items as $item ) {
			foreach ( array_keys( $facets ) as $facet_key ) {
				foreach ( $item[ $facet_key ] as $term ) {
					$facets[ $facet_key ][ $term['slug'] ] = array(
						'slug' => $term['slug'],
						'name' => $term['name'],
					);
				}
			}
		}

		foreach ( $facets as $facet_key => $values ) {
			uasort(
				$values,
				static function ( $left, $right ) {
					return strcasecmp( $left['name'], $right['name'] );
				}
			);
			$facets[ $facet_key ] = array_values( $values );
		}

		return $facets;
	}

	/**
	 * Normalize related point ids.
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	private function normalize_related_point_ids( $value ) {
		return is_array( $value ) ? array_values( array_filter( array_map( 'intval', $value ) ) ) : array();
	}

	/**
	 * Decode GeoJSON for frontend.
	 *
	 * @param string $value Raw JSON string.
	 * @return array|null
	 */
	private function decode_geojson( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}

		$decoded = json_decode( $value, true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Normalize color.
	 *
	 * @param string $value Raw color.
	 * @return string
	 */
	private function normalize_color( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		$sanitized = sanitize_hex_color( $value );

		return $sanitized ? $sanitized : '';
	}

	/**
	 * Normalize coordinates.
	 *
	 * @param mixed $value Raw coordinate.
	 * @return float|null
	 */
	private function normalize_coordinate( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}

		$value = str_replace( ',', '.', $value );

		return is_numeric( $value ) ? (float) $value : null;
	}

	/**
	 * Build search string.
	 *
	 * @param array $parts Parts to concatenate.
	 * @return string
	 */
	private function build_search_text( array $parts ) {
		$parts = array_values( array_filter( array_map( 'trim', array_map( 'strval', $parts ) ) ) );

		return implode( ' ', $parts );
	}
}
