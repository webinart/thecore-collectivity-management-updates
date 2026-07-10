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

final class TheCore_Collectivity_Alerts_Module extends TheCore_Collectivity_Abstract_Module {
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
	 * Stable module id.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'alerts';
	}

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
		add_action( 'elementor/frontend/after_register_styles', array( $this, 'register_elementor_styles' ) );
		add_action( 'elementor/editor/before_enqueue_styles', array( $this, 'register_elementor_styles' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widgets' ) );
	}

	/**
	 * Register alert widget styles.
	 *
	 * @return void
	 */
	public function register_elementor_styles() {
		if ( wp_style_is( TheCore_Collectivity_Elementor::HANDLE_ALERTS_STYLE, 'registered' ) ) {
			return;
		}

		wp_register_style(
			TheCore_Collectivity_Elementor::HANDLE_ALERTS_STYLE,
			THECORE_COLLECTIVITY_MANAGEMENT_URL . 'includes/features/alerts/assets/alerts-widget.css',
			array(),
			TheCore_Collectivity_Elementor::get_asset_version( 'includes/features/alerts/assets/alerts-widget.css' )
		);
	}

	/**
	 * Register the alert widget.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public function register_elementor_widgets( $widgets_manager ) {
		require_once THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/alerts/elementor/class-thecore-collectivity-alerts-widget.php';

		if ( ! class_exists( 'Bellevue_Alerts_Widget', false ) ) {
			class_alias( 'TheCore_Collectivity_Alerts_Widget', 'Bellevue_Alerts_Widget' );
		}

		$widgets_manager->register( new TheCore_Collectivity_Alerts_Widget() );
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
