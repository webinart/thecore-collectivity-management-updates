<?php
/**
 * Service-public repository.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Service_Public_Repository {
	/**
	 * Get all configured sources.
	 *
	 * @return array[]
	 */
	public function get_sources() {
		global $wpdb;

		$table = TheCore_Collectivity_Service_Public_Schema::get_table_name( TheCore_Collectivity_Service_Public_Schema::TABLE_SOURCES );
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get enabled sources.
	 *
	 * @return array[]
	 */
	public function get_enabled_sources() {
		global $wpdb;

		$table = TheCore_Collectivity_Service_Public_Schema::get_table_name( TheCore_Collectivity_Service_Public_Schema::TABLE_SOURCES );
		return $wpdb->get_results( "SELECT * FROM {$table} WHERE enabled = 1 ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get source by ID.
	 *
	 * @param int $source_id Source ID.
	 * @return array|null
	 */
	public function get_source( $source_id ) {
		global $wpdb;

		$table = TheCore_Collectivity_Service_Public_Schema::get_table_name( TheCore_Collectivity_Service_Public_Schema::TABLE_SOURCES );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $source_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $row ?: null;
	}

	/**
	 * Get source by audience.
	 *
	 * @param string $audience Audience key.
	 * @return array|null
	 */
	public function get_source_by_audience( $audience ) {
		global $wpdb;

		$table = TheCore_Collectivity_Service_Public_Schema::get_table_name( TheCore_Collectivity_Service_Public_Schema::TABLE_SOURCES );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE audience = %s", $audience ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $row ?: null;
	}

	/**
	 * Update resource metadata after data.gouv discovery.
	 *
	 * @param int   $source_id Source ID.
	 * @param array $metadata Resource metadata.
	 * @return void
	 */
	public function update_source_resource_metadata( $source_id, array $metadata ) {
		global $wpdb;

		$table = TheCore_Collectivity_Service_Public_Schema::get_table_name( TheCore_Collectivity_Service_Public_Schema::TABLE_SOURCES );
		$wpdb->update(
			$table,
			array(
				'resource_url'       => $metadata['resource_url'] ?? '',
				'resource_title'     => $metadata['resource_title'] ?? '',
				'resource_file_name' => $metadata['resource_file_name'] ?? '',
				'resource_date'      => $metadata['resource_date'] ?? '',
				'updated_at_gmt'     => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $source_id ),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Create import log entry.
	 *
	 * @param array $source Source row.
	 * @return int
	 */
	public function create_import( array $source ) {
		global $wpdb;

		$table = TheCore_Collectivity_Service_Public_Schema::get_table_name( TheCore_Collectivity_Service_Public_Schema::TABLE_IMPORTS );
		$wpdb->insert(
			$table,
			array(
				'source_id'          => (int) $source['id'],
				'audience'           => $source['audience'],
				'status'             => 'running',
				'started_at_gmt'     => current_time( 'mysql', true ),
				'resource_url'       => $source['resource_url'],
				'resource_file_name' => $source['resource_file_name'],
				'resource_date'      => $source['resource_date'],
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Finish import log entry.
	 *
	 * @param int    $import_id Import ID.
	 * @param string $status Status.
	 * @param int    $items_imported Imported count.
	 * @param int    $items_deleted Deleted count.
	 * @param string $message Message.
	 * @return void
	 */
	public function finish_import( $import_id, $status, $items_imported, $items_deleted, $message = '' ) {
		global $wpdb;

		$table = TheCore_Collectivity_Service_Public_Schema::get_table_name( TheCore_Collectivity_Service_Public_Schema::TABLE_IMPORTS );
		$wpdb->update(
			$table,
			array(
				'status'          => $status,
				'finished_at_gmt' => current_time( 'mysql', true ),
				'items_imported'  => (int) $items_imported,
				'items_deleted'   => (int) $items_deleted,
				'message'         => $message,
			),
			array( 'id' => (int) $import_id ),
			array( '%s', '%s', '%d', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Replace all items for one audience.
	 *
	 * @param string $audience Audience.
	 * @param array  $items Items.
	 * @return array{inserted:int,deleted:int}
	 */
	public function replace_items_for_audience( $audience, array $items ) {
		global $wpdb;

		$table   = TheCore_Collectivity_Service_Public_Schema::get_table_name( TheCore_Collectivity_Service_Public_Schema::TABLE_ITEMS );
		$deleted = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE audience = %s", $audience ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$now     = current_time( 'mysql', true );
		$count   = 0;

		foreach ( $items as $item ) {
			$inserted = $wpdb->insert(
				$table,
				array(
					'audience'              => $audience,
					'item_id'               => $item['item_id'],
					'item_type'             => $item['item_type'],
					'title'                 => $item['title'],
					'slug'                  => $item['slug'],
					'summary'               => $item['summary'],
					'breadcrumb_json'       => wp_json_encode( $item['breadcrumb'] ),
					'content_json'          => wp_json_encode( $item['content'] ),
					'raw_xml'               => $item['raw_xml'],
					'official_url'          => $item['official_url'],
					'source_url'            => $item['source_url'],
					'source_file_name'      => $item['source_file_name'],
					'source_file_date'      => $item['source_file_date'],
					'source_updated_at_gmt' => $item['source_updated_at_gmt'],
					'imported_at_gmt'       => $now,
					'search_text'           => $item['search_text'],
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( false !== $inserted ) {
				$count++;
			}
		}

		$this->clear_render_cache( $audience );

		return array(
			'inserted' => $count,
			'deleted'  => $deleted,
		);
	}

	/**
	 * Get one item.
	 *
	 * @param string $audience Audience.
	 * @param string $item_id Item ID.
	 * @return array|null
	 */
	public function get_item( $audience, $item_id ) {
		global $wpdb;

		$table = TheCore_Collectivity_Service_Public_Schema::get_table_name( TheCore_Collectivity_Service_Public_Schema::TABLE_ITEMS );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE audience = %s AND item_id = %s", $audience, $item_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $row ? $this->normalize_item_row( $row ) : null;
	}

	/**
	 * Search items.
	 *
	 * @param string $query Search query.
	 * @param string $audience Audience.
	 * @param int    $limit Limit.
	 * @return array[]
	 */
	public function search_items( $query, $audience = '', $limit = 20 ) {
		global $wpdb;

		$table = TheCore_Collectivity_Service_Public_Schema::get_table_name( TheCore_Collectivity_Service_Public_Schema::TABLE_ITEMS );
		$like  = '%' . $wpdb->esc_like( $query ) . '%';
		$limit = max( 1, min( 100, (int) $limit ) );

		if ( $audience ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE audience = %s AND (item_id LIKE %s OR title LIKE %s OR search_text LIKE %s) ORDER BY title ASC LIMIT %d",
				$audience,
				$like,
				$like,
				$like,
				$limit
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE item_id LIKE %s OR title LIKE %s OR search_text LIKE %s ORDER BY title ASC LIMIT %d",
				$like,
				$like,
				$like,
				$limit
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return array_map( array( $this, 'normalize_item_row' ), $rows ?: array() );
	}

	/**
	 * Get summary items.
	 *
	 * @param string $audience Audience.
	 * @param int    $limit Limit.
	 * @return array[]
	 */
	public function get_summary_items( $audience, $limit = 24 ) {
		global $wpdb;

		$table = TheCore_Collectivity_Service_Public_Schema::get_table_name( TheCore_Collectivity_Service_Public_Schema::TABLE_ITEMS );
		$limit = max( 1, min( 200, (int) $limit ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE audience = %s AND item_id LIKE 'N%%' ORDER BY title ASC LIMIT %d",
				$audience,
				$limit
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array_map( array( $this, 'normalize_item_row' ), $rows ?: array() );
	}

	/**
	 * Get item counts by audience.
	 *
	 * @return array<string,int>
	 */
	public function get_item_counts() {
		global $wpdb;

		$table = TheCore_Collectivity_Service_Public_Schema::get_table_name( TheCore_Collectivity_Service_Public_Schema::TABLE_ITEMS );
		$rows  = $wpdb->get_results( "SELECT audience, COUNT(*) AS item_count FROM {$table} GROUP BY audience", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out   = array();

		foreach ( $rows ?: array() as $row ) {
			$out[ $row['audience'] ] = (int) $row['item_count'];
		}

		return $out;
	}

	/**
	 * Get recent import logs.
	 *
	 * @param int $limit Limit.
	 * @return array[]
	 */
	public function get_recent_imports( $limit = 10 ) {
		global $wpdb;

		$table = TheCore_Collectivity_Service_Public_Schema::get_table_name( TheCore_Collectivity_Service_Public_Schema::TABLE_IMPORTS );
		$limit = max( 1, min( 50, (int) $limit ) );

		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Clear render cache.
	 *
	 * @param string $audience Optional audience.
	 * @return void
	 */
	public function clear_render_cache( $audience = '' ) {
		global $wpdb;

		$table = TheCore_Collectivity_Service_Public_Schema::get_table_name( TheCore_Collectivity_Service_Public_Schema::TABLE_RENDER_CACHE );
		if ( $audience ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE audience = %s", $audience ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return;
		}

		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get cached rendered HTML.
	 *
	 * @param string $audience Audience.
	 * @param string $item_id Item ID.
	 * @param string $cache_key Cache key.
	 * @return string|null
	 */
	public function get_render_cache( $audience, $item_id, $cache_key ) {
		global $wpdb;

		$table = TheCore_Collectivity_Service_Public_Schema::get_table_name( TheCore_Collectivity_Service_Public_Schema::TABLE_RENDER_CACHE );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT html, expires_at_gmt FROM {$table} WHERE audience = %s AND item_id = %s AND cache_key = %s",
				$audience,
				$item_id,
				$cache_key
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $row ) {
			return null;
		}

		if ( ! empty( $row['expires_at_gmt'] ) && strtotime( $row['expires_at_gmt'] . ' UTC' ) < time() ) {
			$wpdb->delete(
				$table,
				array(
					'audience'  => $audience,
					'item_id'   => $item_id,
					'cache_key' => $cache_key,
				),
				array( '%s', '%s', '%s' )
			);
			return null;
		}

		return (string) $row['html'];
	}

	/**
	 * Store rendered HTML in cache.
	 *
	 * @param string $audience Audience.
	 * @param string $item_id Item ID.
	 * @param string $cache_key Cache key.
	 * @param string $html Rendered HTML.
	 * @param int    $ttl Time to live in seconds.
	 * @return void
	 */
	public function set_render_cache( $audience, $item_id, $cache_key, $html, $ttl = DAY_IN_SECONDS ) {
		global $wpdb;

		$table = TheCore_Collectivity_Service_Public_Schema::get_table_name( TheCore_Collectivity_Service_Public_Schema::TABLE_RENDER_CACHE );
		$wpdb->replace(
			$table,
			array(
				'audience'         => $audience,
				'item_id'          => $item_id,
				'cache_key'        => $cache_key,
				'html'             => $html,
				'generated_at_gmt' => current_time( 'mysql', true ),
				'expires_at_gmt'   => gmdate( 'Y-m-d H:i:s', time() + max( 60, (int) $ttl ) ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Normalize DB row JSON fields.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private function normalize_item_row( array $row ) {
		$row['breadcrumb'] = ! empty( $row['breadcrumb_json'] ) ? json_decode( $row['breadcrumb_json'], true ) : array();
		$row['content']    = ! empty( $row['content_json'] ) ? json_decode( $row['content_json'], true ) : array();

		return $row;
	}
}
