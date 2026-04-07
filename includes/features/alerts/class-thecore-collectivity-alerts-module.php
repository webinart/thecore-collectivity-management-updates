<?php
/**
 * Alerts feature module.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-thecore-collectivity-alerts-post-type.php';
require_once __DIR__ . '/class-thecore-collectivity-alerts-meta.php';
require_once __DIR__ . '/class-thecore-collectivity-alerts-repository.php';
require_once __DIR__ . '/class-thecore-collectivity-alerts-renderer.php';

final class TheCore_Collectivity_Alerts_Module {
	/**
	 * Post type manager.
	 *
	 * @var TheCore_Collectivity_Alerts_Post_Type
	 */
	private $post_type;

	/**
	 * Meta manager.
	 *
	 * @var TheCore_Collectivity_Alerts_Meta
	 */
	private $meta;

	/**
	 * Repository.
	 *
	 * @var TheCore_Collectivity_Alerts_Repository
	 */
	private $repository;

	/**
	 * Renderer.
	 *
	 * @var TheCore_Collectivity_Alerts_Renderer
	 */
	private $renderer;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->post_type  = new TheCore_Collectivity_Alerts_Post_Type();
		$this->meta       = new TheCore_Collectivity_Alerts_Meta();
		$this->repository = new TheCore_Collectivity_Alerts_Repository();
		$this->renderer   = new TheCore_Collectivity_Alerts_Renderer();
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		$this->post_type->register_hooks();
		$this->meta->register_hooks();
	}

	/**
	 * Get repository instance.
	 *
	 * @return TheCore_Collectivity_Alerts_Repository
	 */
	public function get_repository() {
		return $this->repository;
	}

	/**
	 * Get renderer instance.
	 *
	 * @return TheCore_Collectivity_Alerts_Renderer
	 */
	public function get_renderer() {
		return $this->renderer;
	}
}
