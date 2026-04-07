<?php
/**
 * Procedures feature module.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-thecore-collectivity-procedures-post-type.php';
require_once __DIR__ . '/class-thecore-collectivity-procedures-meta.php';
require_once __DIR__ . '/class-thecore-collectivity-procedures-repository.php';
require_once __DIR__ . '/class-thecore-collectivity-procedures-frontend.php';

final class TheCore_Collectivity_Procedures_Module {
	/**
	 * Post type manager.
	 *
	 * @var TheCore_Collectivity_Procedures_Post_Type
	 */
	private $post_type;

	/**
	 * Meta manager.
	 *
	 * @var TheCore_Collectivity_Procedures_Meta
	 */
	private $meta;

	/**
	 * Repository.
	 *
	 * @var TheCore_Collectivity_Procedures_Repository
	 */
	private $repository;

	/**
	 * Frontend/Elementor integration.
	 *
	 * @var TheCore_Collectivity_Procedures_Frontend
	 */
	private $frontend;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->post_type  = new TheCore_Collectivity_Procedures_Post_Type();
		$this->meta       = new TheCore_Collectivity_Procedures_Meta();
		$this->repository = new TheCore_Collectivity_Procedures_Repository();
		$this->frontend   = new TheCore_Collectivity_Procedures_Frontend( $this->repository );
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		$this->post_type->register_hooks();
		$this->meta->register_hooks();
		$this->frontend->register_hooks();
	}

	/**
	 * Get repository.
	 *
	 * @return TheCore_Collectivity_Procedures_Repository
	 */
	public function get_repository() {
		return $this->repository;
	}

	/**
	 * Get frontend integration.
	 *
	 * @return TheCore_Collectivity_Procedures_Frontend
	 */
	public function get_frontend() {
		return $this->frontend;
	}
}
