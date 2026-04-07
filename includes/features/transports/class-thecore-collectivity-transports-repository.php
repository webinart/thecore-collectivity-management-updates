<?php
/**
 * Transports repository.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Transports_Repository {
	/**
	 * Get transport lines.
	 *
	 * @return WP_Post[]
	 */
	public function get_lines() {
		$query = new WP_Query(
			array(
				'post_type'           => TheCore_Collectivity_Transports_Post_Types::POST_TYPE_LINE,
				'post_status'         => 'publish',
				'posts_per_page'      => -1,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'meta_key'            => TheCore_Collectivity_Transports_Meta::META_SORT_ORDER,
				'orderby'             => array(
					'meta_value_num' => 'ASC',
					'title'          => 'ASC',
				),
				'order'               => 'ASC',
			)
		);

		return $query->posts;
	}

	/**
	 * Get transport places.
	 *
	 * @return WP_Post[]
	 */
	public function get_places() {
		$query = new WP_Query(
			array(
				'post_type'           => TheCore_Collectivity_Transports_Post_Types::POST_TYPE_PLACE,
				'post_status'         => 'publish',
				'posts_per_page'      => -1,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'meta_key'            => TheCore_Collectivity_Transports_Meta::META_SORT_ORDER,
				'orderby'             => array(
					'meta_value_num' => 'ASC',
					'title'          => 'ASC',
				),
				'order'               => 'ASC',
			)
		);

		return $query->posts;
	}
}
