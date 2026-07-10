<?php
/**
 * Transport schedules module.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-thecore-collectivity-transports-schedule-schema.php';
require_once __DIR__ . '/class-thecore-collectivity-transports-schedule-repository.php';
require_once __DIR__ . '/class-thecore-collectivity-transports-realtime-repository.php';
require_once __DIR__ . '/class-thecore-collectivity-transports-realtime-adapter-interface.php';
require_once __DIR__ . '/class-thecore-collectivity-transports-realtime-adapter-factory.php';
require_once __DIR__ . '/class-thecore-collectivity-transports-gtfs-source-resolver.php';
require_once __DIR__ . '/class-thecore-collectivity-transports-gtfs-importer.php';
require_once __DIR__ . '/class-thecore-collectivity-transports-gtfs-discovery.php';
require_once __DIR__ . '/class-thecore-collectivity-transports-gtfs-realtime-adapter.php';
require_once __DIR__ . '/class-thecore-collectivity-transports-siri-realtime-adapter.php';
require_once __DIR__ . '/class-thecore-collectivity-transports-siri-lite-realtime-adapter.php';
require_once __DIR__ . '/class-thecore-collectivity-transports-realtime-importer.php';
require_once __DIR__ . '/class-thecore-collectivity-transports-schedule-admin.php';

final class TheCore_Collectivity_Transports_Schedules {
	/**
	 * Cron hook.
	 */
	const CRON_HOOK = 'bellevue_transport_gtfs_sync';

	/**
	 * Realtime cron hook.
	 */
	const REALTIME_CRON_HOOK = 'bellevue_transport_gtfs_realtime_sync';

	/**
	 * Schema manager.
	 *
	 * @var TheCore_Collectivity_Transports_Schedule_Schema
	 */
	private $schema;

	/**
	 * Repository.
	 *
	 * @var TheCore_Collectivity_Transports_Schedule_Repository
	 */
	private $repository;

	/**
	 * Importer.
	 *
	 * @var TheCore_Collectivity_Transports_GTFS_Importer
	 */
	private $importer;

	/**
	 * GTFS source resolver.
	 *
	 * @var TheCore_Collectivity_Transports_GTFS_Source_Resolver
	 */
	private $source_resolver;

	/**
	 * Realtime repository.
	 *
	 * @var TheCore_Collectivity_Transports_Realtime_Repository
	 */
	private $realtime_repository;

	/**
	 * Realtime importer.
	 *
	 * @var TheCore_Collectivity_Transports_Realtime_Importer
	 */
	private $realtime_importer;

	/**
	 * Locality discovery helper.
	 *
	 * @var TheCore_Collectivity_Transports_GTFS_Discovery
	 */
	private $discovery;

	/**
	 * Admin page.
	 *
	 * @var TheCore_Collectivity_Transports_Schedule_Admin
	 */
	private $admin;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->schema     = new TheCore_Collectivity_Transports_Schedule_Schema();
		$this->repository = new TheCore_Collectivity_Transports_Schedule_Repository();
		$this->source_resolver = new TheCore_Collectivity_Transports_GTFS_Source_Resolver();
		$this->realtime_repository = new TheCore_Collectivity_Transports_Realtime_Repository( $this->repository );
		$this->importer   = new TheCore_Collectivity_Transports_GTFS_Importer( $this->repository, $this->source_resolver );
		$this->discovery  = new TheCore_Collectivity_Transports_GTFS_Discovery( $this->repository, $this->source_resolver );
		$this->realtime_importer = new TheCore_Collectivity_Transports_Realtime_Importer(
			$this->repository,
			$this->realtime_repository,
			new TheCore_Collectivity_Transports_Realtime_Adapter_Factory(
				array(
					new TheCore_Collectivity_Transports_GTFS_Realtime_Adapter(),
					new TheCore_Collectivity_Transports_SIRI_Realtime_Adapter(),
					new TheCore_Collectivity_Transports_SIRI_Lite_Realtime_Adapter(),
				)
			)
		);
		$this->admin      = new TheCore_Collectivity_Transports_Schedule_Admin( $this->repository, $this->realtime_repository, $this->importer, $this->realtime_importer, $this->discovery );
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		$this->admin->register_hooks();
		add_filter( 'cron_schedules', array( $this, 'register_cron_schedules' ) );
		add_action( 'init', array( $this, 'schedule_cron' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_scheduled_import' ) );
		add_action( self::REALTIME_CRON_HOOK, array( $this, 'run_scheduled_realtime_import' ) );
	}

	/**
	 * Get schema manager.
	 *
	 * @return TheCore_Collectivity_Transports_Schedule_Schema
	 */
	public function get_schema() {
		return $this->schema;
	}

	/**
	 * Register custom cron intervals.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function register_cron_schedules( $schedules ) {
		if ( empty( $schedules['bellevue_five_minutes'] ) ) {
			$schedules['bellevue_five_minutes'] = array(
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => __( 'Toutes les 5 minutes', 'bellevue' ),
			);
		}

		return $schedules;
	}

	/**
	 * Ensure recurring GTFS sync exists.
	 */
	public function schedule_cron() {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			// Static cron already exists.
		} else {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', self::CRON_HOOK );
		}

		if ( ! wp_next_scheduled( self::REALTIME_CRON_HOOK ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'bellevue_five_minutes', self::REALTIME_CRON_HOOK );
		}
	}

	/**
	 * Execute scheduled import.
	 */
	public function run_scheduled_import() {
		$this->importer->import_enabled_sources();
	}

	/**
	 * Execute scheduled realtime import.
	 *
	 * @return void
	 */
	public function run_scheduled_realtime_import() {
		$this->realtime_importer->import_enabled_sources();
	}

	/**
	 * Get repository.
	 *
	 * @return TheCore_Collectivity_Transports_Schedule_Repository
	 */
	public function get_repository() {
		return $this->repository;
	}

	/**
	 * Get realtime repository.
	 *
	 * @return TheCore_Collectivity_Transports_Realtime_Repository
	 */
	public function get_realtime_repository() {
		return $this->realtime_repository;
	}

	/**
	 * Get realtime importer.
	 *
	 * @return TheCore_Collectivity_Transports_Realtime_Importer
	 */
	public function get_realtime_importer() {
		return $this->realtime_importer;
	}
}
