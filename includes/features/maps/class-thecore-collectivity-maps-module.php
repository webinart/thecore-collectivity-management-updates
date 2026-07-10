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

final class TheCore_Collectivity_Maps_Module extends TheCore_Collectivity_Abstract_Module {
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
	 * Stable module id.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'maps';
	}

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

		add_action( 'elementor/init', array( $this, 'register_elementor_loader_assets' ) );
		add_action( 'elementor/frontend/after_register_styles', array( $this, 'register_elementor_styles' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( $this, 'register_elementor_scripts' ) );
		add_action( 'elementor/editor/before_enqueue_styles', array( $this, 'register_elementor_styles' ) );
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'register_elementor_scripts' ) );
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue_elementor_preview_styles' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'enqueue_elementor_preview_scripts' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widgets' ) );
	}

	/**
	 * Register map styles and their shared Leaflet dependency.
	 *
	 * @return void
	 */
	public function register_elementor_styles() {
		TheCore_Collectivity_Elementor::register_leaflet_style();
		if ( wp_style_is( TheCore_Collectivity_Elementor::HANDLE_MAP_STYLE, 'registered' ) ) {
			return;
		}

		wp_register_style(
			TheCore_Collectivity_Elementor::HANDLE_MAP_STYLE,
			THECORE_COLLECTIVITY_MANAGEMENT_URL . 'includes/features/maps/assets/map-widget.css',
			array( TheCore_Collectivity_Elementor::HANDLE_LEAFLET_STYLE ),
			TheCore_Collectivity_Elementor::get_asset_version( 'includes/features/maps/assets/map-widget.css' )
		);
	}

	/**
	 * Register map scripts and their shared Leaflet dependency.
	 *
	 * @return void
	 */
	public function register_elementor_scripts() {
		TheCore_Collectivity_Elementor::register_leaflet_script();
		if ( wp_script_is( TheCore_Collectivity_Elementor::HANDLE_MAP_SCRIPT, 'registered' ) ) {
			return;
		}

		wp_register_script(
			TheCore_Collectivity_Elementor::HANDLE_MAP_SCRIPT,
			THECORE_COLLECTIVITY_MANAGEMENT_URL . 'includes/features/maps/assets/map-widget.js',
			array( TheCore_Collectivity_Elementor::HANDLE_LEAFLET_SCRIPT ),
			TheCore_Collectivity_Elementor::get_asset_version( 'includes/features/maps/assets/map-widget.js' ),
			true
		);
	}

	/**
	 * Register map assets with Elementor's preview loader.
	 *
	 * @return void
	 */
	public function register_elementor_loader_assets() {
		$base_url = THECORE_COLLECTIVITY_MANAGEMENT_URL . 'includes/features/maps/assets/';
		TheCore_Collectivity_Elementor::add_loader_assets(
			array(
				'styles'  => array(
					TheCore_Collectivity_Elementor::HANDLE_LEAFLET_STYLE => array(
						'src'          => 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css',
						'version'      => '1.9.4',
						'dependencies' => array(),
					),
					TheCore_Collectivity_Elementor::HANDLE_MAP_STYLE => array(
						'src'          => $base_url . 'map-widget.css',
						'version'      => TheCore_Collectivity_Elementor::get_asset_version( 'includes/features/maps/assets/map-widget.css' ),
						'dependencies' => array( TheCore_Collectivity_Elementor::HANDLE_LEAFLET_STYLE ),
					),
				),
				'scripts' => array(
					TheCore_Collectivity_Elementor::HANDLE_LEAFLET_SCRIPT => array(
						'src'          => 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js',
						'version'      => '1.9.4',
						'dependencies' => array(),
					),
					TheCore_Collectivity_Elementor::HANDLE_MAP_SCRIPT => array(
						'src'          => $base_url . 'map-widget.js',
						'version'      => TheCore_Collectivity_Elementor::get_asset_version( 'includes/features/maps/assets/map-widget.js' ),
						'dependencies' => array( TheCore_Collectivity_Elementor::HANDLE_LEAFLET_SCRIPT ),
					),
				),
			)
		);
	}

	/**
	 * Enqueue map assets inside the Elementor preview iframe.
	 *
	 * @return void
	 */
	public function enqueue_elementor_preview_styles() {
		$this->register_elementor_styles();
		wp_enqueue_style( TheCore_Collectivity_Elementor::HANDLE_LEAFLET_STYLE );
		wp_enqueue_style( TheCore_Collectivity_Elementor::HANDLE_MAP_STYLE );
	}

	/**
	 * Enqueue map scripts inside the Elementor preview iframe.
	 *
	 * @return void
	 */
	public function enqueue_elementor_preview_scripts() {
		$this->register_elementor_scripts();
		wp_enqueue_script( TheCore_Collectivity_Elementor::HANDLE_LEAFLET_SCRIPT );
		wp_enqueue_script( TheCore_Collectivity_Elementor::HANDLE_MAP_SCRIPT );
	}

	/**
	 * Register all MAP widgets.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public function register_elementor_widgets( $widgets_manager ) {
		$base = THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/maps/elementor/';
		require_once $base . 'class-thecore-collectivity-map-linked-widget-base.php';
		require_once $base . 'class-thecore-collectivity-map-widget.php';
		require_once $base . 'class-thecore-collectivity-map-search-widget.php';
		require_once $base . 'class-thecore-collectivity-map-types-widget.php';
		require_once $base . 'class-thecore-collectivity-map-facets-widget.php';
		require_once $base . 'class-thecore-collectivity-map-reset-widget.php';
		require_once $base . 'class-thecore-collectivity-map-filters-widget.php';

		$widgets_manager->register( new TheCore_Collectivity_Map_Widget() );
		$widgets_manager->register( new TheCore_Collectivity_Map_Search_Widget() );
		$widgets_manager->register( new TheCore_Collectivity_Map_Types_Widget() );
		$widgets_manager->register( new TheCore_Collectivity_Map_Facets_Widget() );
		$widgets_manager->register( new TheCore_Collectivity_Map_Reset_Widget() );
		$widgets_manager->register( new TheCore_Collectivity_Map_Filters_Widget() );
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
