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
