<?php
/**
 * Main plugin class for The Core - Collectivity Management.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/features/class-thecore-collectivity-menu-subtext.php';
require_once __DIR__ . '/features/class-thecore-collectivity-event-agenda.php';
require_once __DIR__ . '/features/shared-taxonomies/class-thecore-collectivity-shared-taxonomies-module.php';
require_once __DIR__ . '/features/maps/class-thecore-collectivity-maps-module.php';
require_once __DIR__ . '/features/alerts/class-thecore-collectivity-alerts-module.php';
require_once __DIR__ . '/features/procedures/class-thecore-collectivity-procedures-module.php';
require_once __DIR__ . '/features/documents/class-thecore-collectivity-documents-module.php';
require_once __DIR__ . '/features/transports/class-thecore-collectivity-transports-module.php';
require_once __DIR__ . '/features/service-public/class-thecore-collectivity-service-public-module.php';

final class TheCore_Collectivity_Management {
	/**
	 * Singleton instance.
	 *
	 * @var TheCore_Collectivity_Management|null
	 */
	private static $instance = null;

	/**
	 * Menu subtext feature instance.
	 *
	 * @var TheCore_Collectivity_Menu_Subtext
	 */
	private $menu_subtext_feature;

	/**
	 * Event agenda feature instance.
	 *
	 * @var TheCore_Collectivity_Event_Agenda
	 */
	private $event_agenda_feature;

	/**
	 * Shared taxonomies module instance.
	 *
	 * @var TheCore_Collectivity_Shared_Taxonomies_Module
	 */
	private $shared_taxonomies_module;

	/**
	 * Maps module instance.
	 *
	 * @var TheCore_Collectivity_Maps_Module
	 */
	private $maps_module;

	/**
	 * Alerts module instance.
	 *
	 * @var TheCore_Collectivity_Alerts_Module
	 */
	private $alerts_module;

	/**
	 * Procedures module instance.
	 *
	 * @var TheCore_Collectivity_Procedures_Module
	 */
	private $procedures_module;

	/**
	 * Documents module instance.
	 *
	 * @var TheCore_Collectivity_Documents_Module
	 */
	private $documents_module;

	/**
	 * Transports module instance.
	 *
	 * @var TheCore_Collectivity_Transports_Module
	 */
	private $transports_module;

	/**
	 * Service-public module instance.
	 *
	 * @var TheCore_Collectivity_Service_Public_Module
	 */
	private $service_public_module;

	/**
	 * Get singleton instance.
	 *
	 * @return TheCore_Collectivity_Management
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Plugin activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		$instance = self::instance();

		if ( $instance->transports_module && method_exists( $instance->transports_module, 'get_schedules' ) ) {
			$schema = new TheCore_Collectivity_Transports_Schedule_Schema();
			$schema->install();

			if ( ! wp_next_scheduled( TheCore_Collectivity_Transports_Schedules::CRON_HOOK ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', TheCore_Collectivity_Transports_Schedules::CRON_HOOK );
			}

			if ( ! wp_next_scheduled( TheCore_Collectivity_Transports_Schedules::REALTIME_CRON_HOOK ) ) {
				add_filter(
					'cron_schedules',
					static function ( $schedules ) {
						if ( empty( $schedules['bellevue_five_minutes'] ) ) {
							$schedules['bellevue_five_minutes'] = array(
								'interval' => 5 * MINUTE_IN_SECONDS,
								'display'  => __( 'Toutes les 5 minutes', 'bellevue' ),
							);
						}

						return $schedules;
					}
				);
				wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'bellevue_five_minutes', TheCore_Collectivity_Transports_Schedules::REALTIME_CRON_HOOK );
			}
		}

		if ( $instance->service_public_module && method_exists( $instance->service_public_module, 'get_schema' ) ) {
			$instance->service_public_module->get_schema()->install();
			$instance->service_public_module->register_public_routes();
			flush_rewrite_rules( false );
			update_option( TheCore_Collectivity_Service_Public_Module::OPTION_REWRITE_VERSION, TheCore_Collectivity_Service_Public_Module::REWRITE_VERSION, false );

			if ( ! wp_next_scheduled( TheCore_Collectivity_Service_Public_Module::CRON_HOOK ) ) {
				wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'daily', TheCore_Collectivity_Service_Public_Module::CRON_HOOK );
			}
		}
	}

	/**
	 * Plugin deactivation callback.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( TheCore_Collectivity_Transports_Schedules::CRON_HOOK );
		wp_clear_scheduled_hook( TheCore_Collectivity_Transports_Schedules::REALTIME_CRON_HOOK );
		wp_clear_scheduled_hook( TheCore_Collectivity_Service_Public_Module::CRON_HOOK );
		flush_rewrite_rules( false );
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		TheCore_Collectivity_Elementor::register_hooks();

		$this->menu_subtext_feature = new TheCore_Collectivity_Menu_Subtext();
		$this->menu_subtext_feature->register_hooks();

		$this->event_agenda_feature = new TheCore_Collectivity_Event_Agenda();
		$this->event_agenda_feature->register_hooks();

		$this->shared_taxonomies_module = new TheCore_Collectivity_Shared_Taxonomies_Module();
		$this->shared_taxonomies_module->register_hooks();

		$this->maps_module = new TheCore_Collectivity_Maps_Module();
		$this->maps_module->register_hooks();

		$this->alerts_module = new TheCore_Collectivity_Alerts_Module();
		$this->alerts_module->register_hooks();

		$this->procedures_module = new TheCore_Collectivity_Procedures_Module();
		$this->procedures_module->register_hooks();

		$this->documents_module = new TheCore_Collectivity_Documents_Module();
		$this->documents_module->register_hooks();

		$this->transports_module = new TheCore_Collectivity_Transports_Module();
		$this->transports_module->register_hooks();

		$this->service_public_module = new TheCore_Collectivity_Service_Public_Module();
		$this->service_public_module->register_hooks();
	}

	/**
	 * Get the event agenda feature instance.
	 *
	 * @return TheCore_Collectivity_Event_Agenda
	 */
	public function get_event_agenda_feature() {
		return $this->event_agenda_feature;
	}

	/**
	 * Get shared taxonomies module instance.
	 *
	 * @return TheCore_Collectivity_Shared_Taxonomies_Module
	 */
	public function get_shared_taxonomies_module() {
		return $this->shared_taxonomies_module;
	}

	/**
	 * Get maps module instance.
	 *
	 * @return TheCore_Collectivity_Maps_Module
	 */
	public function get_maps_module() {
		return $this->maps_module;
	}

	/**
	 * Get alerts module instance.
	 *
	 * @return TheCore_Collectivity_Alerts_Module
	 */
	public function get_alerts_module() {
		return $this->alerts_module;
	}

	/**
	 * Get procedures module instance.
	 *
	 * @return TheCore_Collectivity_Procedures_Module
	 */
	public function get_procedures_module() {
		return $this->procedures_module;
	}

	/**
	 * Get documents module instance.
	 *
	 * @return TheCore_Collectivity_Documents_Module
	 */
	public function get_documents_module() {
		return $this->documents_module;
	}

	/**
	 * Get transports module instance.
	 *
	 * @return TheCore_Collectivity_Transports_Module
	 */
	public function get_transports_module() {
		return $this->transports_module;
	}

	/**
	 * Get Service-public module instance.
	 *
	 * @return TheCore_Collectivity_Service_Public_Module
	 */
	public function get_service_public_module() {
		return $this->service_public_module;
	}
}

if ( ! class_exists( 'Bellevue_Theme', false ) ) {
	class_alias( 'TheCore_Collectivity_Management', 'Bellevue_Theme' );
}
