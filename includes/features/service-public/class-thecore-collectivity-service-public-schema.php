<?php
/**
 * Service-public database schema.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Service_Public_Schema {
	const OPTION_DB_VERSION = 'tccm_service_public_db_version';
	const DB_VERSION        = '0.1.0';

	const TABLE_SOURCES      = 'tccm_sp_sources';
	const TABLE_IMPORTS      = 'tccm_sp_imports';
	const TABLE_ITEMS        = 'tccm_sp_items';
	const TABLE_RENDER_CACHE = 'tccm_sp_render_cache';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'maybe_upgrade' ), 5 );
	}

	/**
	 * Install schema when the stored version is outdated.
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		if ( self::DB_VERSION === get_option( self::OPTION_DB_VERSION ) ) {
			return;
		}

		$this->install();
	}

	/**
	 * Create or update tables.
	 *
	 * @return void
	 */
	public function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$sources_table   = self::get_table_name( self::TABLE_SOURCES );
		$imports_table   = self::get_table_name( self::TABLE_IMPORTS );
		$items_table     = self::get_table_name( self::TABLE_ITEMS );
		$cache_table     = self::get_table_name( self::TABLE_RENDER_CACHE );

		$sql = array(
			"CREATE TABLE {$sources_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				audience varchar(32) NOT NULL DEFAULT '',
				label varchar(191) NOT NULL DEFAULT '',
				dataset_slug varchar(191) NOT NULL DEFAULT '',
				dataset_api_url text NULL,
				resource_url text NULL,
				resource_title varchar(191) NOT NULL DEFAULT '',
				resource_file_name varchar(191) NOT NULL DEFAULT '',
				resource_date varchar(64) NOT NULL DEFAULT '',
				attribution_label varchar(191) NOT NULL DEFAULT '',
				enabled tinyint(1) unsigned NOT NULL DEFAULT 1,
				created_at_gmt datetime DEFAULT NULL,
				updated_at_gmt datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY audience (audience),
				KEY enabled (enabled)
			) {$charset_collate};",
			"CREATE TABLE {$imports_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				source_id bigint(20) unsigned NOT NULL DEFAULT 0,
				audience varchar(32) NOT NULL DEFAULT '',
				status varchar(32) NOT NULL DEFAULT 'pending',
				started_at_gmt datetime DEFAULT NULL,
				finished_at_gmt datetime DEFAULT NULL,
				resource_url text NULL,
				resource_file_name varchar(191) NOT NULL DEFAULT '',
				resource_date varchar(64) NOT NULL DEFAULT '',
				items_imported int(10) unsigned NOT NULL DEFAULT 0,
				items_deleted int(10) unsigned NOT NULL DEFAULT 0,
				message longtext NULL,
				PRIMARY KEY  (id),
				KEY source_id (source_id),
				KEY audience_status (audience, status),
				KEY started_at_gmt (started_at_gmt)
			) {$charset_collate};",
			"CREATE TABLE {$items_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				audience varchar(32) NOT NULL DEFAULT '',
				item_id varchar(64) NOT NULL DEFAULT '',
				item_type varchar(64) NOT NULL DEFAULT '',
				title text NULL,
				slug varchar(191) NOT NULL DEFAULT '',
				summary text NULL,
				breadcrumb_json longtext NULL,
				content_json longtext NULL,
				raw_xml longtext NULL,
				official_url text NULL,
				source_url text NULL,
				source_file_name varchar(191) NOT NULL DEFAULT '',
				source_file_date varchar(64) NOT NULL DEFAULT '',
				source_updated_at_gmt datetime DEFAULT NULL,
				imported_at_gmt datetime DEFAULT NULL,
				search_text longtext NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY audience_item (audience, item_id),
				KEY audience_type (audience, item_type),
				KEY item_id (item_id),
				KEY slug (slug)
			) {$charset_collate};",
			"CREATE TABLE {$cache_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				audience varchar(32) NOT NULL DEFAULT '',
				item_id varchar(64) NOT NULL DEFAULT '',
				cache_key varchar(191) NOT NULL DEFAULT '',
				html longtext NULL,
				generated_at_gmt datetime DEFAULT NULL,
				expires_at_gmt datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY cache_key (cache_key),
				KEY audience_item (audience, item_id),
				KEY expires_at_gmt (expires_at_gmt)
			) {$charset_collate};",
		);

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		$this->maybe_insert_default_sources();
		update_option( self::OPTION_DB_VERSION, self::DB_VERSION, false );
	}

	/**
	 * Insert official default sources once.
	 *
	 * @return void
	 */
	private function maybe_insert_default_sources() {
		global $wpdb;

		$table = self::get_table_name( self::TABLE_SOURCES );
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $count > 0 ) {
			return;
		}

		foreach ( self::get_default_sources() as $source ) {
			$wpdb->insert(
				$table,
				array_merge(
					$source,
					array(
						'enabled'        => 1,
						'created_at_gmt' => current_time( 'mysql', true ),
						'updated_at_gmt' => current_time( 'mysql', true ),
					)
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Return default official data.gouv sources.
	 *
	 * @return array[]
	 */
	public static function get_default_sources() {
		return array(
			array(
				'audience'           => 'particuliers',
				'label'              => 'Fiches pratiques particuliers',
				'dataset_slug'       => 'fiches-pratiques-et-ressources-de-service-public-gouv-fr-particuliers',
				'dataset_api_url'    => 'https://www.data.gouv.fr/api/1/datasets/fiches-pratiques-et-ressources-de-service-public-gouv-fr-particuliers/',
				'resource_url'       => 'https://lecomarquage.service-public.gouv.fr/vdd/3.5/part/zip/vosdroits-latest.zip',
				'resource_title'     => 'Fiches pratiques et ressources pour les particuliers',
				'resource_file_name' => 'vosdroits-latest.zip',
				'resource_date'      => '',
				'attribution_label'  => 'Service-Public.gouv.fr / DILA',
			),
			array(
				'audience'           => 'professionnels',
				'label'              => 'Fiches pratiques professionnels',
				'dataset_slug'       => 'fiches-pratiques-et-ressources-entreprendre-service-public-gouv-fr',
				'dataset_api_url'    => 'https://www.data.gouv.fr/api/1/datasets/fiches-pratiques-et-ressources-entreprendre-service-public-gouv-fr/',
				'resource_url'       => 'https://lecomarquage.service-public.gouv.fr/vdd/3.5/pro/zip/vosdroits-latest.zip',
				'resource_title'     => 'Fiches pratiques et ressources pour les entreprises',
				'resource_file_name' => 'vosdroits-latest.zip',
				'resource_date'      => '',
				'attribution_label'  => 'Entreprendre.Service-Public.gouv.fr / DILA',
			),
		);
	}

	/**
	 * Resolve full table name.
	 *
	 * @param string $suffix Table suffix.
	 * @return string
	 */
	public static function get_table_name( $suffix ) {
		global $wpdb;

		return $wpdb->prefix . $suffix;
	}
}
