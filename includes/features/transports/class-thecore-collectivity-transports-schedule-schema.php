<?php
/**
 * Transport schedule database schema.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Transports_Schedule_Schema {
	/**
	 * Schema version option.
	 */
	const OPTION_DB_VERSION = 'bellevue_transport_schedule_db_version';

	/**
	 * Current schema version.
	 */
	const DB_VERSION = '2.5.0';

	/**
	 * Legacy single-source option.
	 */
	const LEGACY_OPTION_GTFS_SOURCE_CONFIG = 'bellevue_transport_gtfs_source_config';

	/**
	 * Multi-source option.
	 */
	const OPTION_GTFS_SOURCES = 'bellevue_transport_gtfs_sources';

	/**
	 * Table suffixes.
	 */
	const TABLE_STOPS      = 'bellevue_tr_stops';
	const TABLE_ROUTES     = 'bellevue_tr_routes';
	const TABLE_TRIPS      = 'bellevue_tr_trips';
	const TABLE_SHAPES     = 'bellevue_tr_shapes';
	const TABLE_STOP_TIMES = 'bellevue_tr_stop_times';
	const TABLE_SERVICES   = 'bellevue_tr_services';
	const TABLE_REALTIME_PREDICTIONS = 'bellevue_tr_rt_predictions';
	const TABLE_REALTIME_ALERTS = 'bellevue_tr_rt_alerts';
	const TABLE_REALTIME_VEHICLES = 'bellevue_tr_rt_vehicles';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'maybe_upgrade' ), 5 );
	}

	/**
	 * Upgrade schema if needed.
	 */
	public function maybe_upgrade() {
		if ( self::DB_VERSION === get_option( self::OPTION_DB_VERSION ) ) {
			return;
		}

		$this->install();
	}

	/**
	 * Install or upgrade schema.
	 */
	public function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$stops_table     = self::get_table_name( self::TABLE_STOPS );
		$routes_table    = self::get_table_name( self::TABLE_ROUTES );
		$trips_table     = self::get_table_name( self::TABLE_TRIPS );
		$shapes_table    = self::get_table_name( self::TABLE_SHAPES );
		$stop_times      = self::get_table_name( self::TABLE_STOP_TIMES );
		$services_table  = self::get_table_name( self::TABLE_SERVICES );
		$realtime_predictions_table = self::get_table_name( self::TABLE_REALTIME_PREDICTIONS );
		$realtime_alerts_table      = self::get_table_name( self::TABLE_REALTIME_ALERTS );
		$realtime_vehicles_table    = self::get_table_name( self::TABLE_REALTIME_VEHICLES );

		$sql = array(
			"CREATE TABLE {$stops_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				provider_key varchar(64) NOT NULL DEFAULT '',
				stop_id varchar(64) NOT NULL,
				stop_name varchar(191) NOT NULL,
				parent_station varchar(64) DEFAULT '',
				stop_lat decimal(10,6) DEFAULT NULL,
				stop_lon decimal(10,6) DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY provider_stop (provider_key, stop_id),
				KEY provider_parent (provider_key, parent_station),
				KEY provider_name (provider_key, stop_name)
			) {$charset_collate};",
			"CREATE TABLE {$routes_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				provider_key varchar(64) NOT NULL DEFAULT '',
				route_id varchar(64) NOT NULL,
				route_short_name varchar(64) DEFAULT '',
				route_long_name varchar(191) DEFAULT '',
				route_type smallint(5) unsigned DEFAULT 0,
				agency_id varchar(64) DEFAULT '',
				route_color varchar(16) DEFAULT '',
				route_text_color varchar(16) DEFAULT '',
				PRIMARY KEY  (id),
				UNIQUE KEY provider_route (provider_key, route_id),
				KEY provider_short_name (provider_key, route_short_name)
			) {$charset_collate};",
			"CREATE TABLE {$trips_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				provider_key varchar(64) NOT NULL DEFAULT '',
				trip_id varchar(64) NOT NULL,
				route_id varchar(64) NOT NULL,
				service_id varchar(64) NOT NULL,
				trip_headsign varchar(191) DEFAULT '',
				direction_id varchar(16) DEFAULT '',
				shape_id varchar(64) DEFAULT '',
				terminal_stop_id varchar(64) DEFAULT '',
				terminal_stop_name varchar(191) DEFAULT '',
				terminal_stop_locality varchar(191) DEFAULT '',
				terminal_stop_sequence int(10) unsigned DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY provider_trip (provider_key, trip_id),
				KEY provider_route (provider_key, route_id),
				KEY provider_service (provider_key, service_id),
				KEY provider_shape (provider_key, shape_id)
			) {$charset_collate};",
			"CREATE TABLE {$shapes_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				provider_key varchar(64) NOT NULL DEFAULT '',
				shape_id varchar(64) NOT NULL,
				shape_pt_sequence int(10) unsigned DEFAULT 0,
				shape_pt_lat decimal(10,6) DEFAULT NULL,
				shape_pt_lon decimal(10,6) DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY provider_shape_point (provider_key, shape_id, shape_pt_sequence),
				KEY provider_shape (provider_key, shape_id)
			) {$charset_collate};",
			"CREATE TABLE {$stop_times} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				provider_key varchar(64) NOT NULL DEFAULT '',
				trip_id varchar(64) NOT NULL,
				stop_id varchar(64) NOT NULL,
				stop_sequence int(10) unsigned DEFAULT 0,
				arrival_secs int(10) unsigned DEFAULT 0,
				departure_secs int(10) unsigned DEFAULT 0,
				PRIMARY KEY  (id),
				KEY provider_trip (provider_key, trip_id),
				KEY provider_stop (provider_key, stop_id),
				KEY provider_trip_stop (provider_key, trip_id, stop_id),
				KEY provider_stop_departure (provider_key, stop_id, departure_secs)
			) {$charset_collate};",
			"CREATE TABLE {$services_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				provider_key varchar(64) NOT NULL DEFAULT '',
				service_id varchar(64) NOT NULL,
				monday tinyint(1) unsigned DEFAULT 0,
				tuesday tinyint(1) unsigned DEFAULT 0,
				wednesday tinyint(1) unsigned DEFAULT 0,
				thursday tinyint(1) unsigned DEFAULT 0,
				friday tinyint(1) unsigned DEFAULT 0,
				saturday tinyint(1) unsigned DEFAULT 0,
				sunday tinyint(1) unsigned DEFAULT 0,
				start_date char(8) DEFAULT '',
				end_date char(8) DEFAULT '',
				added_dates longtext NULL,
				removed_dates longtext NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY provider_service (provider_key, service_id)
			) {$charset_collate};",
			"CREATE TABLE {$realtime_predictions_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				provider_key varchar(64) NOT NULL DEFAULT '',
				entity_id varchar(191) DEFAULT '',
				trip_id varchar(128) NOT NULL DEFAULT '',
				route_id varchar(128) DEFAULT '',
				stop_id varchar(128) NOT NULL DEFAULT '',
				stop_sequence int(10) unsigned DEFAULT 0,
				service_date char(8) DEFAULT '',
				headsign varchar(191) DEFAULT '',
				schedule_relationship varchar(32) DEFAULT 'scheduled',
				arrival_timestamp bigint(20) DEFAULT NULL,
				departure_timestamp bigint(20) DEFAULT NULL,
				arrival_delay int(11) DEFAULT NULL,
				departure_delay int(11) DEFAULT NULL,
				trip_delay int(11) DEFAULT NULL,
				feed_timestamp bigint(20) DEFAULT NULL,
				fetched_at_gmt datetime DEFAULT NULL,
				expires_at_gmt datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY provider_prediction (provider_key, trip_id, stop_id, stop_sequence, service_date),
				KEY provider_stop (provider_key, stop_id),
				KEY provider_trip (provider_key, trip_id),
				KEY provider_route (provider_key, route_id),
				KEY provider_expiry (provider_key, expires_at_gmt)
			) {$charset_collate};",
			"CREATE TABLE {$realtime_alerts_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				provider_key varchar(64) NOT NULL DEFAULT '',
				alert_id varchar(191) NOT NULL DEFAULT '',
				header_text text NULL,
				description_text longtext NULL,
				url text NULL,
				cause varchar(64) DEFAULT '',
				effect varchar(64) DEFAULT '',
				severity varchar(64) DEFAULT '',
				route_ids longtext NULL,
				stop_ids longtext NULL,
				trip_ids longtext NULL,
				agency_ids longtext NULL,
				route_types longtext NULL,
				start_timestamp bigint(20) DEFAULT NULL,
				end_timestamp bigint(20) DEFAULT NULL,
				feed_timestamp bigint(20) DEFAULT NULL,
				fetched_at_gmt datetime DEFAULT NULL,
				expires_at_gmt datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY provider_alert (provider_key, alert_id),
				KEY provider_expiry (provider_key, expires_at_gmt),
				KEY provider_window (provider_key, start_timestamp, end_timestamp)
			) {$charset_collate};",
			"CREATE TABLE {$realtime_vehicles_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				provider_key varchar(64) NOT NULL DEFAULT '',
				entity_id varchar(191) NOT NULL DEFAULT '',
				vehicle_id varchar(128) DEFAULT '',
				vehicle_label varchar(191) DEFAULT '',
				license_plate varchar(128) DEFAULT '',
				trip_id varchar(128) DEFAULT '',
				route_id varchar(128) DEFAULT '',
				stop_id varchar(128) DEFAULT '',
				current_stop_sequence int(10) unsigned DEFAULT 0,
				current_status varchar(32) DEFAULT '',
				latitude decimal(10,6) DEFAULT NULL,
				longitude decimal(10,6) DEFAULT NULL,
				bearing decimal(8,3) DEFAULT NULL,
				speed decimal(10,3) DEFAULT NULL,
				congestion_level varchar(64) DEFAULT '',
				occupancy_status varchar(64) DEFAULT '',
				timestamp bigint(20) DEFAULT NULL,
				feed_timestamp bigint(20) DEFAULT NULL,
				fetched_at_gmt datetime DEFAULT NULL,
				expires_at_gmt datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY provider_vehicle_entity (provider_key, entity_id),
				KEY provider_route (provider_key, route_id),
				KEY provider_trip (provider_key, trip_id),
				KEY provider_stop (provider_key, stop_id),
				KEY provider_expiry (provider_key, expires_at_gmt)
			) {$charset_collate};",
		);

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		$this->migrate_multi_provider_schema();
		update_option( self::OPTION_DB_VERSION, self::DB_VERSION, false );
	}

	/**
	 * Get full table name.
	 *
	 * @param string $table_suffix Table suffix.
	 * @return string
	 */
	public static function get_table_name( $table_suffix ) {
		global $wpdb;

		return $wpdb->prefix . $table_suffix;
	}

	/**
	 * Apply non-destructive provider-key migration steps.
	 *
	 * @return void
	 */
	private function migrate_multi_provider_schema() {
		global $wpdb;

		$default_provider = $this->get_default_provider_key();
		$tables           = array(
			self::TABLE_STOPS      => array(
				'legacy_unique' => array( 'stop_id' ),
				'ensure_unique' => array( 'provider_stop' => 'ALTER TABLE %s ADD UNIQUE KEY provider_stop (provider_key, stop_id)' ),
			),
			self::TABLE_ROUTES     => array(
				'legacy_unique' => array( 'route_id' ),
				'ensure_unique' => array( 'provider_route' => 'ALTER TABLE %s ADD UNIQUE KEY provider_route (provider_key, route_id)' ),
			),
			self::TABLE_TRIPS      => array(
				'legacy_unique' => array( 'trip_id' ),
				'ensure_unique' => array( 'provider_trip' => 'ALTER TABLE %s ADD UNIQUE KEY provider_trip (provider_key, trip_id)' ),
			),
			self::TABLE_SERVICES   => array(
				'legacy_unique' => array( 'service_id' ),
				'ensure_unique' => array( 'provider_service' => 'ALTER TABLE %s ADD UNIQUE KEY provider_service (provider_key, service_id)' ),
			),
			self::TABLE_STOP_TIMES => array(
				'legacy_unique' => array(),
				'ensure_unique' => array(),
			),
		);

		foreach ( $tables as $table_suffix => $table_config ) {
			$table = self::get_table_name( $table_suffix );

			if ( ! $this->table_has_column( $table, 'provider_key' ) ) {
				$wpdb->query( "ALTER TABLE {$table} ADD COLUMN provider_key varchar(64) NOT NULL DEFAULT '' AFTER id" );
			}

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET provider_key = %s WHERE provider_key = '' OR provider_key IS NULL",
					$default_provider
				)
			);

			foreach ( $table_config['legacy_unique'] as $index_name ) {
				$this->drop_index_if_exists( $table, $index_name );
			}

			foreach ( $table_config['ensure_unique'] as $index_name => $statement ) {
				if ( ! $this->table_has_index( $table, $index_name ) ) {
					$wpdb->query( sprintf( $statement, $table ) );
				}
			}
		}
	}

	/**
	 * Check whether one table has a given column.
	 *
	 * @param string $table  Table name.
	 * @param string $column Column name.
	 * @return bool
	 */
	private function table_has_column( $table, $column ) {
		global $wpdb;

		$result = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) );
		return ! empty( $result );
	}

	/**
	 * Check whether one table has a given index.
	 *
	 * @param string $table Table name.
	 * @param string $index Index name.
	 * @return bool
	 */
	private function table_has_index( $table, $index ) {
		global $wpdb;

		$result = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM {$table} WHERE Key_name = %s", $index ) );
		return ! empty( $result );
	}

	/**
	 * Drop one index if it exists.
	 *
	 * @param string $table Table name.
	 * @param string $index Index name.
	 * @return void
	 */
	private function drop_index_if_exists( $table, $index ) {
		global $wpdb;

		if ( ! $this->table_has_index( $table, $index ) ) {
			return;
		}

		$wpdb->query( "ALTER TABLE {$table} DROP INDEX {$index}" );
	}

	/**
	 * Resolve a provider key for existing mono-source imported rows.
	 *
	 * @return string
	 */
	private function get_default_provider_key() {
		$sources = get_option( self::OPTION_GTFS_SOURCES, array() );
		if ( is_array( $sources ) ) {
			foreach ( $sources as $source ) {
				$provider_key = sanitize_key( (string) ( $source['provider_key'] ?? '' ) );
				if ( '' !== $provider_key ) {
					return $provider_key;
				}
			}
		}

		$legacy = get_option( self::LEGACY_OPTION_GTFS_SOURCE_CONFIG, array() );
		if ( is_array( $legacy ) ) {
			$provider_key = sanitize_key( (string) ( $legacy['provider_key'] ?? '' ) );
			if ( '' !== $provider_key ) {
				return $provider_key;
			}
		}

		return 'default';
	}
}
