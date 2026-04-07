<?php
/**
 * Maps feature module.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-thecore-collectivity-maps-post-type.php';
require_once __DIR__ . '/class-thecore-collectivity-maps-meta.php';
require_once __DIR__ . '/class-thecore-collectivity-maps-repository.php';
require_once __DIR__ . '/class-thecore-collectivity-maps-normalizer.php';

final class TheCore_Collectivity_Maps_Module {
	/**
	 * Post type manager.
	 *
	 * @var TheCore_Collectivity_Maps_Post_Type
	 */
	private $post_type;

	/**
	 * Meta manager.
	 *
	 * @var TheCore_Collectivity_Maps_Meta
	 */
	private $meta;

	/**
	 * Repository.
	 *
	 * @var TheCore_Collectivity_Maps_Repository
	 */
	private $repository;

	/**
	 * Payload normalizer.
	 *
	 * @var TheCore_Collectivity_Maps_Normalizer
	 */
	private $normalizer;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->post_type  = new TheCore_Collectivity_Maps_Post_Type();
		$this->meta       = new TheCore_Collectivity_Maps_Meta();
		$this->repository = new TheCore_Collectivity_Maps_Repository();
		$this->normalizer = new TheCore_Collectivity_Maps_Normalizer( $this->repository );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		$this->post_type->register_hooks();
		$this->meta->register_hooks();
	}

	/**
	 * Get repository.
	 *
	 * @return TheCore_Collectivity_Maps_Repository
	 */
	public function get_repository() {
		return $this->repository;
	}

	/**
	 * Get normalizer.
	 *
	 * @return TheCore_Collectivity_Maps_Normalizer
	 */
	public function get_normalizer() {
		return $this->normalizer;
	}
}
