<?php
/**
 * Elementor integration for The Core - Collectivity Management.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Elementor {
	/**
	 * Elementor category slug.
	 */
	const CATEGORY = 'thecore-collectivity';

	/**
	 * Shared widget asset handles.
	 */
	const HANDLE_ALERTS_STYLE     = 'thecore-collectivity-alerts-widget';
	const HANDLE_MAP_STYLE        = 'thecore-collectivity-map-widget';
	const HANDLE_MAP_SCRIPT       = 'thecore-collectivity-map-widget';
	const HANDLE_PROCEDURE_STYLE  = 'thecore-collectivity-procedure-content-widget';
	const HANDLE_TRANSPORT_STYLE  = 'thecore-collectivity-transport-explorer-widget';
	const HANDLE_TRANSPORT_SCRIPT = 'thecore-collectivity-transport-explorer-widget';
	const HANDLE_LEAFLET_STYLE    = 'thecore-collectivity-leaflet';
	const HANDLE_LEAFLET_SCRIPT   = 'thecore-collectivity-leaflet';

	/**
	 * Register hooks.
	 */
	public static function register_hooks() {
		add_action( 'elementor/init', array( __CLASS__, 'register_assets_loader_assets' ) );
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'register_category' ) );
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register_styles' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'register_scripts' ) );
		add_action( 'elementor/editor/before_enqueue_styles', array( __CLASS__, 'register_styles' ) );
		add_action( 'elementor/editor/before_enqueue_scripts', array( __CLASS__, 'register_scripts' ) );
		add_action( 'elementor/preview/enqueue_styles', array( __CLASS__, 'enqueue_preview_styles' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( __CLASS__, 'enqueue_preview_scripts' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
	}

	/**
	 * Register Elementor category.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 */
	public static function register_category( $elements_manager ) {
		$categories = $elements_manager->get_categories();
		if ( isset( $categories[ self::CATEGORY ] ) ) {
			return;
		}

		$elements_manager->add_category(
			self::CATEGORY,
			array(
				'title' => esc_html__( 'The Core - Collectivity', 'thecore-collectivity-management' ),
				'icon'  => 'fa fa-building',
			)
		);
	}

	/**
	 * Register widget styles.
	 */
	public static function register_styles() {
		if ( wp_style_is( self::HANDLE_MAP_STYLE, 'registered' ) && wp_style_is( self::HANDLE_LEAFLET_STYLE, 'registered' ) ) {
			return;
		}

		$base_url = THECORE_COLLECTIVITY_MANAGEMENT_URL . 'includes/features/';

		wp_register_style(
			self::HANDLE_LEAFLET_STYLE,
			'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css',
			array(),
			'1.9.4'
		);

		wp_register_style(
			self::HANDLE_ALERTS_STYLE,
			$base_url . 'alerts/assets/alerts-widget.css',
			array(),
			self::get_asset_version( 'includes/features/alerts/assets/alerts-widget.css' )
		);

		wp_register_style(
			self::HANDLE_MAP_STYLE,
			$base_url . 'maps/assets/map-widget.css',
			array( self::HANDLE_LEAFLET_STYLE ),
			self::get_asset_version( 'includes/features/maps/assets/map-widget.css' )
		);

		wp_register_style(
			self::HANDLE_PROCEDURE_STYLE,
			$base_url . 'procedures/assets/procedure-content-widget.css',
			array(),
			self::get_asset_version( 'includes/features/procedures/assets/procedure-content-widget.css' )
		);

		wp_register_style(
			self::HANDLE_TRANSPORT_STYLE,
			$base_url . 'transports/assets/transport-explorer.css',
			array( self::HANDLE_LEAFLET_STYLE, self::HANDLE_ALERTS_STYLE ),
			self::get_asset_version( 'includes/features/transports/assets/transport-explorer.css' )
		);
	}

	/**
	 * Register widget scripts.
	 */
	public static function register_scripts() {
		if ( wp_script_is( self::HANDLE_MAP_SCRIPT, 'registered' ) && wp_script_is( self::HANDLE_LEAFLET_SCRIPT, 'registered' ) ) {
			return;
		}

		$base_url = THECORE_COLLECTIVITY_MANAGEMENT_URL . 'includes/features/';

		wp_register_script(
			self::HANDLE_LEAFLET_SCRIPT,
			'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js',
			array(),
			'1.9.4',
			true
		);

		wp_register_script(
			self::HANDLE_MAP_SCRIPT,
			$base_url . 'maps/assets/map-widget.js',
			array( self::HANDLE_LEAFLET_SCRIPT ),
			self::get_asset_version( 'includes/features/maps/assets/map-widget.js' ),
			true
		);

		wp_register_script(
			self::HANDLE_TRANSPORT_SCRIPT,
			$base_url . 'transports/assets/transport-explorer.js',
			array( self::HANDLE_LEAFLET_SCRIPT ),
			self::get_asset_version( 'includes/features/transports/assets/transport-explorer.js' ),
			true
		);
	}

	/**
	 * Register plugin assets in Elementor assets loader for preview/editor contexts.
	 *
	 * @return void
	 */
	public static function register_assets_loader_assets() {
		if ( ! class_exists( '\Elementor\Plugin' ) || empty( \Elementor\Plugin::$instance->assets_loader ) ) {
			return;
		}

		$base_url = THECORE_COLLECTIVITY_MANAGEMENT_URL . 'includes/features/';

		\Elementor\Plugin::$instance->assets_loader->add_assets(
			array(
				'styles'  => array(
					self::HANDLE_LEAFLET_STYLE => array(
						'src'          => 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css',
						'version'      => '1.9.4',
						'dependencies' => array(),
					),
					self::HANDLE_MAP_STYLE     => array(
						'src'          => $base_url . 'maps/assets/map-widget.css',
						'version'      => self::get_asset_version( 'includes/features/maps/assets/map-widget.css' ),
						'dependencies' => array( self::HANDLE_LEAFLET_STYLE ),
					),
				),
				'scripts' => array(
					self::HANDLE_LEAFLET_SCRIPT => array(
						'src'          => 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js',
						'version'      => '1.9.4',
						'dependencies' => array(),
					),
					self::HANDLE_MAP_SCRIPT     => array(
						'src'          => $base_url . 'maps/assets/map-widget.js',
						'version'      => self::get_asset_version( 'includes/features/maps/assets/map-widget.js' ),
						'dependencies' => array( self::HANDLE_LEAFLET_SCRIPT ),
					),
				),
			)
		);
	}

	/**
	 * Resolve an asset version from file modification time.
	 *
	 * @param string $relative_path Plugin-relative path.
	 * @return string
	 */
	private static function get_asset_version( $relative_path ) {
		$path = THECORE_COLLECTIVITY_MANAGEMENT_DIR . ltrim( $relative_path, '/' );
		if ( file_exists( $path ) ) {
			return (string) filemtime( $path );
		}

		return THECORE_COLLECTIVITY_MANAGEMENT_VERSION;
	}

	/**
	 * Ensure map styles are available in Elementor preview.
	 *
	 * @return void
	 */
	public static function enqueue_preview_styles() {
		self::register_styles();

		wp_enqueue_style( self::HANDLE_LEAFLET_STYLE );
		wp_enqueue_style( self::HANDLE_MAP_STYLE );
	}

	/**
	 * Ensure map scripts are available in Elementor preview.
	 *
	 * @return void
	 */
	public static function enqueue_preview_scripts() {
		self::register_scripts();

		wp_enqueue_script( self::HANDLE_LEAFLET_SCRIPT );
		wp_enqueue_script( self::HANDLE_MAP_SCRIPT );
	}

	/**
	 * Register plugin widgets.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public static function register_widgets( $widgets_manager ) {
			require_once THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/alerts/elementor/class-thecore-collectivity-alerts-widget.php';
			require_once THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/event-agenda/elementor/class-thecore-collectivity-event-date-widget.php';
			require_once THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/maps/elementor/class-thecore-collectivity-map-linked-widget-base.php';
			require_once THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/maps/elementor/class-thecore-collectivity-map-widget.php';
			require_once THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/maps/elementor/class-thecore-collectivity-map-search-widget.php';
			require_once THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/maps/elementor/class-thecore-collectivity-map-types-widget.php';
			require_once THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/maps/elementor/class-thecore-collectivity-map-facets-widget.php';
			require_once THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/maps/elementor/class-thecore-collectivity-map-reset-widget.php';
			require_once THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/maps/elementor/class-thecore-collectivity-map-filters-widget.php';
			require_once THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/procedures/elementor/class-thecore-collectivity-procedure-content-widget.php';
			require_once THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/transports/elementor/class-thecore-collectivity-transport-explorer-widget.php';
			require_once THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/transports/elementor/class-thecore-collectivity-transport-schedule-widget.php';

		if ( ! class_exists( 'Bellevue_Alerts_Widget', false ) ) {
			class_alias( 'TheCore_Collectivity_Alerts_Widget', 'Bellevue_Alerts_Widget' );
		}

		if ( ! class_exists( 'Bellevue_Transport_Explorer_Widget', false ) ) {
			class_alias( 'TheCore_Collectivity_Transport_Explorer_Widget', 'Bellevue_Transport_Explorer_Widget' );
		}

		if ( ! class_exists( 'Bellevue_Transport_Schedule_Widget', false ) ) {
			class_alias( 'TheCore_Collectivity_Transport_Schedule_Widget', 'Bellevue_Transport_Schedule_Widget' );
		}

			$widgets_manager->register( new TheCore_Collectivity_Alerts_Widget() );
			$widgets_manager->register( new TheCore_Collectivity_Event_Date_Widget() );
			$widgets_manager->register( new TheCore_Collectivity_Map_Widget() );
			$widgets_manager->register( new TheCore_Collectivity_Map_Search_Widget() );
			$widgets_manager->register( new TheCore_Collectivity_Map_Types_Widget() );
			$widgets_manager->register( new TheCore_Collectivity_Map_Facets_Widget() );
			$widgets_manager->register( new TheCore_Collectivity_Map_Reset_Widget() );
			$widgets_manager->register( new TheCore_Collectivity_Map_Filters_Widget() );
			$widgets_manager->register( new TheCore_Collectivity_Procedure_Content_Widget() );
			$widgets_manager->register( new TheCore_Collectivity_Transport_Explorer_Widget() );
			$widgets_manager->register( new TheCore_Collectivity_Transport_Schedule_Widget() );
		}
	}

if ( ! class_exists( 'Bellevue_Elementor', false ) ) {
	class_alias( 'TheCore_Collectivity_Elementor', 'Bellevue_Elementor' );
}
