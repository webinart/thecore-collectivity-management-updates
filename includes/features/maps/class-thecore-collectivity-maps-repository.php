<?php
/**
 * Maps content repository.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Maps_Repository {
	/**
	 * Fetch map items for widget consumption.
	 *
	 * @param array $filters Query filters.
	 * @return WP_Post[]
	 */
	public function get_items( array $filters = array() ) {
		$args = array(
			'post_type'      => TheCore_Collectivity_Maps_Post_Type::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		);

		$tax_query = array();
		$this->append_tax_filter( $tax_query, TheCore_Collectivity_Maps_Post_Type::TAXONOMY_UNIVERSE, $filters, 'universes' );
		$this->append_tax_filter( $tax_query, TheCore_Collectivity_Maps_Post_Type::TAXONOMY_CATEGORY, $filters, 'categories' );
		$this->append_tax_filter( $tax_query, TheCore_Collectivity_Shared_Taxonomies_Module::TAXONOMY_THEME, $filters, 'themes' );
		$this->append_tax_filter( $tax_query, TheCore_Collectivity_Shared_Taxonomies_Module::TAXONOMY_AUDIENCE, $filters, 'audiences' );
		$this->append_tax_filter( $tax_query, TheCore_Collectivity_Shared_Taxonomies_Module::TAXONOMY_TERRITORY, $filters, 'territories' );

		if ( ! empty( $filters['accessible_only'] ) ) {
			$tax_query[] = array(
				'taxonomy' => TheCore_Collectivity_Maps_Post_Type::TAXONOMY_ACCESSIBILITY,
				'field'    => 'slug',
				'terms'    => array( TheCore_Collectivity_Maps_Post_Type::TERM_ACCESSIBLE ),
			);
		}

		if ( ! empty( $tax_query ) ) {
			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = 'AND';
			}
			$args['tax_query'] = $tax_query;
		}

		if ( ! empty( $filters['geometry_types'] ) ) {
			$geometry_types = array_values(
				array_intersect(
					array_map( 'sanitize_key', (array) $filters['geometry_types'] ),
					array( TheCore_Collectivity_Maps_Meta::GEOMETRY_POINT, TheCore_Collectivity_Maps_Meta::GEOMETRY_ROUTE )
				)
			);

			if ( ! empty( $geometry_types ) ) {
				$args['meta_query'] = array(
					array(
						'key'     => TheCore_Collectivity_Maps_Meta::META_GEOMETRY_TYPE,
						'value'   => $geometry_types,
						'compare' => 'IN',
					),
				);
			}
		}

		$items = get_posts( $args );

		usort(
			$items,
			static function ( $left, $right ) {
				$left_order  = (int) get_post_meta( $left->ID, TheCore_Collectivity_Maps_Meta::META_DISPLAY_ORDER, true );
				$right_order = (int) get_post_meta( $right->ID, TheCore_Collectivity_Maps_Meta::META_DISPLAY_ORDER, true );

				if ( $left_order === $right_order ) {
					return strcasecmp( get_the_title( $left ), get_the_title( $right ) );
				}

				return $left_order <=> $right_order;
			}
		);

		return $items;
	}

	/**
	 * Get a taxonomy payload for one post.
	 *
	 * @param int    $post_id   Post id.
	 * @param string $taxonomy  Taxonomy slug.
	 * @return array
	 */
	public function get_term_payload( $post_id, $taxonomy ) {
		$terms = wp_get_post_terms( $post_id, $taxonomy );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$payload = array();

		foreach ( $terms as $term ) {
			$payload[] = array(
				'id'   => (int) $term->term_id,
				'slug' => (string) $term->slug,
				'name' => (string) $term->name,
			);
		}

		return $payload;
	}

	/**
	 * Append one taxonomy filter if present.
	 *
	 * @param array  $tax_query Existing tax query array.
	 * @param string $taxonomy  Taxonomy slug.
	 * @param array  $filters   Filters payload.
	 * @param string $key       Filter key.
	 * @return void
	 */
	private function append_tax_filter( array &$tax_query, $taxonomy, array $filters, $key ) {
		if ( empty( $filters[ $key ] ) || ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$terms = array_values(
			array_filter(
				array_map( 'sanitize_key', (array) $filters[ $key ] )
			)
		);

		if ( empty( $terms ) ) {
			return;
		}

		$tax_query[] = array(
			'taxonomy' => $taxonomy,
			'field'    => 'slug',
			'terms'    => $terms,
		);
	}
}
