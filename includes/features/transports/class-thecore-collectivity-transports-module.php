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

final class TheCore_Collectivity_Transports_Module {
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
