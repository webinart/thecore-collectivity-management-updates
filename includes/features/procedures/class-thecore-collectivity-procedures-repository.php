<?php
/**
 * Procedures repository.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Procedures_Repository {
	/**
	 * Get procedures.
	 *
	 * @param array $args Additional query args.
	 * @return WP_Post[]
	 */
	public function get_procedures( $args = array() ) {
		$defaults = array(
			'post_type'      => TheCore_Collectivity_Procedures_Post_Type::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_key'       => TheCore_Collectivity_Procedures_Meta::META_SORT_ORDER,
			'orderby'        => array(
				'meta_value_num' => 'ASC',
				'title'          => 'ASC',
			),
			'order'          => 'ASC',
		);

		return get_posts( wp_parse_args( $args, $defaults ) );
	}

	/**
	 * Get documents related to a procedure.
	 *
	 * @param int $procedure_id Procedure id.
	 * @return WP_Post[]
	 */
	public function get_related_documents( $procedure_id ) {
		$procedure_id = absint( $procedure_id );
		if ( $procedure_id <= 0 ) {
			return array();
		}

		$direct_ids = get_post_meta( $procedure_id, TheCore_Collectivity_Procedures_Meta::META_RELATED_DOCUMENT_IDS, true );
		$direct_ids = is_array( $direct_ids ) ? array_map( 'intval', $direct_ids ) : array();

		$reverse_ids = get_posts(
			array(
				'post_type'      => TheCore_Collectivity_Documents_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => TheCore_Collectivity_Documents_Meta::META_RELATED_PROCEDURE_IDS,
						'value'   => '"' . $procedure_id . '"',
						'compare' => 'LIKE',
					),
				),
			)
		);

		$document_ids = array_values( array_unique( array_filter( array_merge( $direct_ids, array_map( 'intval', $reverse_ids ) ) ) ) );
		if ( empty( $document_ids ) ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'      => TheCore_Collectivity_Documents_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'post__in'       => $document_ids,
				'orderby'        => 'post__in',
			)
		);
	}
}
