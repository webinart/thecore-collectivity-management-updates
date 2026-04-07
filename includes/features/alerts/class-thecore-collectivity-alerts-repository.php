<?php
/**
 * Alerts data repository.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Alerts_Repository {
	/**
	 * Get active alerts.
	 *
	 * @param array|string $topics Optional topic slugs.
	 * @return array
	 */
	public function get_active_alerts( $topics = array() ) {
		$topic_slugs = $this->normalize_topic_slugs( $topics );
		$query       = new WP_Query(
			array(
				'post_type'           => TheCore_Collectivity_Alerts_Post_Type::POST_TYPE,
				'post_status'         => 'publish',
				'posts_per_page'      => -1,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'meta_key'            => TheCore_Collectivity_Alerts_Meta::META_SORT_ORDER,
				'orderby'             => array(
					'meta_value_num' => 'ASC',
					'title'          => 'ASC',
				),
				'order'               => 'ASC',
				'meta_query'          => array(
					array(
						'key'   => TheCore_Collectivity_Alerts_Meta::META_ENABLED,
						'value' => '1',
					),
				),
			)
		);

		$alerts = array();
		$now    = current_time( 'timestamp' );

		foreach ( $query->posts as $post ) {
			if ( ! $this->is_alert_active( $post->ID, $now ) ) {
				continue;
			}

			$alert_topics = wp_get_post_terms( $post->ID, TheCore_Collectivity_Alerts_Post_Type::TAXONOMY_TOPIC );
			if ( is_wp_error( $alert_topics ) ) {
				$alert_topics = array();
			}
			$alert_slugs  = array();
			$alert_names  = array();

			foreach ( $alert_topics as $topic ) {
				$alert_slugs[] = $topic->slug;
				$alert_names[] = $topic->name;
			}

			if ( ! empty( $topic_slugs ) && empty( array_intersect( $topic_slugs, $alert_slugs ) ) ) {
				continue;
			}

			$alerts[] = array(
				'id'        => (int) $post->ID,
				'title'     => get_the_title( $post ),
				'message'   => (string) get_post_meta( $post->ID, TheCore_Collectivity_Alerts_Meta::META_MESSAGE, true ),
				'severity'  => (string) get_post_meta( $post->ID, TheCore_Collectivity_Alerts_Meta::META_SEVERITY, true ),
				'linkUrl'   => (string) get_post_meta( $post->ID, TheCore_Collectivity_Alerts_Meta::META_LINK_URL, true ),
				'linkLabel' => (string) get_post_meta( $post->ID, TheCore_Collectivity_Alerts_Meta::META_LINK_LABEL, true ),
				'topics'    => $alert_names,
				'topicSlugs'=> $alert_slugs,
			);
		}

		return $alerts;
	}

	/**
	 * Determine if an alert is active right now.
	 *
	 * @param int $post_id Post id.
	 * @param int $now     Current timestamp.
	 * @return bool
	 */
	private function is_alert_active( $post_id, $now ) {
		$start_ts = (int) get_post_meta( $post_id, TheCore_Collectivity_Alerts_Meta::META_START_TS, true );
		$end_ts   = (int) get_post_meta( $post_id, TheCore_Collectivity_Alerts_Meta::META_END_TS, true );

		if ( $start_ts && $start_ts > $now ) {
			return false;
		}

		if ( $end_ts && $end_ts < $now ) {
			return false;
		}

		return true;
	}

	/**
	 * Normalize topic slugs.
	 *
	 * @param array|string $topics Topic slugs.
	 * @return array
	 */
	private function normalize_topic_slugs( $topics ) {
		$topics = is_array( $topics ) ? $topics : array( $topics );
		$topics = array_map( 'sanitize_key', $topics );
		return array_values( array_filter( array_unique( $topics ) ) );
	}
}
