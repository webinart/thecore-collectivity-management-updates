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

final class TheCore_Collectivity_Procedures_Module extends TheCore_Collectivity_Abstract_Module {
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
	 * Stable module id.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'procedures';
	}

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
		add_action( 'elementor/frontend/after_register_styles', array( $this, 'register_elementor_styles' ) );
		add_action( 'elementor/editor/before_enqueue_styles', array( $this, 'register_elementor_styles' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widgets' ) );
	}

	/**
	 * Register procedure widget styles.
	 *
	 * @return void
	 */
	public function register_elementor_styles() {
		if ( wp_style_is( TheCore_Collectivity_Elementor::HANDLE_PROCEDURE_STYLE, 'registered' ) ) {
			return;
		}

		wp_register_style(
			TheCore_Collectivity_Elementor::HANDLE_PROCEDURE_STYLE,
			THECORE_COLLECTIVITY_MANAGEMENT_URL . 'includes/features/procedures/assets/procedure-content-widget.css',
			array(),
			TheCore_Collectivity_Elementor::get_asset_version( 'includes/features/procedures/assets/procedure-content-widget.css' )
		);
	}

	/**
	 * Register the procedure content widget.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public function register_elementor_widgets( $widgets_manager ) {
		require_once THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/procedures/elementor/class-thecore-collectivity-procedure-content-widget.php';
		$widgets_manager->register( new TheCore_Collectivity_Procedure_Content_Widget() );
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
