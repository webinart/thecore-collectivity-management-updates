<?php
/**
 * Transports feature module.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-thecore-collectivity-transports-post-types.php';
require_once __DIR__ . '/class-thecore-collectivity-transports-meta.php';
require_once __DIR__ . '/class-thecore-collectivity-transports-repository.php';
require_once __DIR__ . '/class-thecore-collectivity-transports-schedules.php';
require_once __DIR__ . '/class-thecore-collectivity-transports-normalizer.php';

final class TheCore_Collectivity_Transports_Module extends TheCore_Collectivity_Abstract_Module {
	/**
	 * Post type manager.
	 *
	 * @var TheCore_Collectivity_Transports_Post_Types
	 */
	private $post_types;

	/**
	 * Meta manager.
	 *
	 * @var TheCore_Collectivity_Transports_Meta
	 */
	private $meta;

	/**
	 * Repository.
	 *
	 * @var TheCore_Collectivity_Transports_Repository
	 */
	private $repository;

	/**
	 * Schedules module.
	 *
	 * @var TheCore_Collectivity_Transports_Schedules
	 */
	private $schedules;

	/**
	 * Normalizer.
	 *
	 * @var TheCore_Collectivity_Transports_Normalizer
	 */
	private $normalizer;

	/**
	 * Stable module id.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'transports';
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->post_types = new TheCore_Collectivity_Transports_Post_Types();
		$this->meta       = new TheCore_Collectivity_Transports_Meta();
		$this->repository = new TheCore_Collectivity_Transports_Repository();
		$this->schedules  = new TheCore_Collectivity_Transports_Schedules();
		$this->normalizer = new TheCore_Collectivity_Transports_Normalizer( $this->repository, $this->schedules->get_repository() );
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		$this->post_types->register_hooks();
		$this->meta->register_hooks();
		$this->schedules->register_hooks();
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'elementor/frontend/after_register_styles', array( $this, 'register_elementor_styles' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( $this, 'register_elementor_scripts' ) );
		add_action( 'elementor/editor/before_enqueue_styles', array( $this, 'register_elementor_styles' ) );
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'register_elementor_scripts' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widgets' ) );
	}

	/**
	 * Install the transport schedule schema.
	 *
	 * @return void
	 */
	public function install() {
		$this->schedules->get_schema()->install();
	}

	/**
	 * Upgrade the transport schedule schema when its version changes.
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		$this->schedules->get_schema()->maybe_upgrade();
	}

	/**
	 * Ensure transport synchronization schedules exist.
	 *
	 * @return void
	 */
	public function activate() {
		$this->schedules->schedule_cron();
	}

	/**
	 * Clear transport schedules without deleting imported data.
	 *
	 * @return void
	 */
	public function deactivate() {
		wp_clear_scheduled_hook( TheCore_Collectivity_Transports_Schedules::CRON_HOOK );
		wp_clear_scheduled_hook( TheCore_Collectivity_Transports_Schedules::REALTIME_CRON_HOOK );
	}

	/**
	 * Register transport widget styles.
	 *
	 * @return void
	 */
	public function register_elementor_styles() {
		TheCore_Collectivity_Elementor::register_leaflet_style();
		if ( wp_style_is( TheCore_Collectivity_Elementor::HANDLE_TRANSPORT_STYLE, 'registered' ) ) {
			return;
		}

		$dependencies = array( TheCore_Collectivity_Elementor::HANDLE_LEAFLET_STYLE );
		if ( TheCore_Collectivity_Management::instance()->is_module_active( 'alerts' ) ) {
			$dependencies[] = TheCore_Collectivity_Elementor::HANDLE_ALERTS_STYLE;
		}

		wp_register_style(
			TheCore_Collectivity_Elementor::HANDLE_TRANSPORT_STYLE,
			THECORE_COLLECTIVITY_MANAGEMENT_URL . 'includes/features/transports/assets/transport-explorer.css',
			$dependencies,
			TheCore_Collectivity_Elementor::get_asset_version( 'includes/features/transports/assets/transport-explorer.css' )
		);
	}

	/**
	 * Register transport widget scripts.
	 *
	 * @return void
	 */
	public function register_elementor_scripts() {
		TheCore_Collectivity_Elementor::register_leaflet_script();
		if ( wp_script_is( TheCore_Collectivity_Elementor::HANDLE_TRANSPORT_SCRIPT, 'registered' ) ) {
			return;
		}

		wp_register_script(
			TheCore_Collectivity_Elementor::HANDLE_TRANSPORT_SCRIPT,
			THECORE_COLLECTIVITY_MANAGEMENT_URL . 'includes/features/transports/assets/transport-explorer.js',
			array( TheCore_Collectivity_Elementor::HANDLE_LEAFLET_SCRIPT ),
			TheCore_Collectivity_Elementor::get_asset_version( 'includes/features/transports/assets/transport-explorer.js' ),
			true
		);
	}

	/**
	 * Register transport widgets and historical aliases.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public function register_elementor_widgets( $widgets_manager ) {
		$base = THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/transports/elementor/';
		require_once $base . 'class-thecore-collectivity-transport-explorer-widget.php';
		require_once $base . 'class-thecore-collectivity-transport-schedule-widget.php';

		if ( ! class_exists( 'Bellevue_Transport_Explorer_Widget', false ) ) {
			class_alias( 'TheCore_Collectivity_Transport_Explorer_Widget', 'Bellevue_Transport_Explorer_Widget' );
		}

		if ( ! class_exists( 'Bellevue_Transport_Schedule_Widget', false ) ) {
			class_alias( 'TheCore_Collectivity_Transport_Schedule_Widget', 'Bellevue_Transport_Schedule_Widget' );
		}

		$widgets_manager->register( new TheCore_Collectivity_Transport_Explorer_Widget() );
		$widgets_manager->register( new TheCore_Collectivity_Transport_Schedule_Widget() );
	}

	/**
	 * Register public REST routes used by the transport explorer widget.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			'thecore-collectivity/v1',
			'/transports/widget-payload',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_widget_payload_response' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'thecore-collectivity/v1',
			'/transports/widget-realtime',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_widget_realtime_response' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Return the latest transport widget payload.
	 *
	 * @return WP_REST_Response
	 */
	public function get_widget_payload_response() {
		$response = rest_ensure_response(
			array(
				'payload'    => $this->normalizer->get_widget_payload(),
				'generatedAt' => current_time( 'mysql' ),
			)
		);
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );

		return $response;
	}

	/**
	 * Return the latest realtime-only widget payload.
	 *
	 * @return WP_REST_Response
	 */
	public function get_widget_realtime_response() {
		$response = rest_ensure_response(
			array(
				'payload'     => $this->normalizer->get_widget_realtime_payload(),
				'generatedAt' => current_time( 'mysql' ),
			)
		);
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );

		return $response;
	}

	/**
	 * Get repository.
	 *
	 * @return TheCore_Collectivity_Transports_Repository
	 */
	public function get_repository() {
		return $this->repository;
	}

	/**
	 * Get normalizer.
	 *
	 * @return TheCore_Collectivity_Transports_Normalizer
	 */
	public function get_normalizer() {
		return $this->normalizer;
	}

	/**
	 * Get schedules module.
	 *
	 * @return TheCore_Collectivity_Transports_Schedules
	 */
	public function get_schedules() {
		return $this->schedules;
	}
}
