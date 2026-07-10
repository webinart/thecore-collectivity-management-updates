<?php
/**
 * Documents feature module.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-thecore-collectivity-documents-post-type.php';
require_once __DIR__ . '/class-thecore-collectivity-documents-meta.php';
require_once __DIR__ . '/class-thecore-collectivity-documents-repository.php';
require_once __DIR__ . '/class-thecore-collectivity-documents-frontend.php';

final class TheCore_Collectivity_Documents_Module extends TheCore_Collectivity_Abstract_Module {
	/**
	 * Post type manager.
	 *
	 * @var TheCore_Collectivity_Documents_Post_Type
	 */
	private $post_type;

	/**
	 * Meta manager.
	 *
	 * @var TheCore_Collectivity_Documents_Meta
	 */
	private $meta;

	/**
	 * Repository.
	 *
	 * @var TheCore_Collectivity_Documents_Repository
	 */
	private $repository;

	/**
	 * Frontend/Elementor integration.
	 *
	 * @var TheCore_Collectivity_Documents_Frontend
	 */
	private $frontend;

	/**
	 * Stable module id.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'documents';
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->post_type  = new TheCore_Collectivity_Documents_Post_Type();
		$this->meta       = new TheCore_Collectivity_Documents_Meta();
		$this->repository = new TheCore_Collectivity_Documents_Repository();
		$this->frontend   = new TheCore_Collectivity_Documents_Frontend( $this->repository );
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
	 * @return TheCore_Collectivity_Documents_Repository
	 */
	public function get_repository() {
		return $this->repository;
	}

	/**
	 * Get frontend integration.
	 *
	 * @return TheCore_Collectivity_Documents_Frontend
	 */
	public function get_frontend() {
		return $this->frontend;
	}
}
