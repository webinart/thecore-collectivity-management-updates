<?php
/**
 * Shared Elementor infrastructure for collectivity modules.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Elementor {
	/**
	 * Shared Elementor category.
	 */
	const CATEGORY = 'thecore-collectivity';

	/**
	 * Historical public asset handles kept for widget compatibility.
	 */
	const HANDLE_ALERTS_STYLE         = 'thecore-collectivity-alerts-widget';
	const HANDLE_MAP_STYLE            = 'thecore-collectivity-map-widget';
	const HANDLE_MAP_SCRIPT           = 'thecore-collectivity-map-widget';
	const HANDLE_PROCEDURE_STYLE      = 'thecore-collectivity-procedure-content-widget';
	const HANDLE_TRANSPORT_STYLE      = 'thecore-collectivity-transport-explorer-widget';
	const HANDLE_TRANSPORT_SCRIPT     = 'thecore-collectivity-transport-explorer-widget';
	const HANDLE_SERVICE_PUBLIC_STYLE = 'thecore-collectivity-service-public-widget';
	const HANDLE_LEAFLET_STYLE        = 'thecore-collectivity-leaflet';
	const HANDLE_LEAFLET_SCRIPT       = 'thecore-collectivity-leaflet';

	/**
	 * Register only the integration shared by every active module.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'register_category' ) );
	}

	/**
	 * Register the shared widget category.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 * @return void
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
	 * Register the shared Leaflet stylesheet on demand.
	 *
	 * @return void
	 */
	public static function register_leaflet_style() {
		if ( wp_style_is( self::HANDLE_LEAFLET_STYLE, 'registered' ) ) {
			return;
		}

		wp_register_style(
			self::HANDLE_LEAFLET_STYLE,
			'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css',
			array(),
			'1.9.4'
		);
	}

	/**
	 * Register the shared Leaflet script on demand.
	 *
	 * @return void
	 */
	public static function register_leaflet_script() {
		if ( wp_script_is( self::HANDLE_LEAFLET_SCRIPT, 'registered' ) ) {
			return;
		}

		wp_register_script(
			self::HANDLE_LEAFLET_SCRIPT,
			'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js',
			array(),
			'1.9.4',
			true
		);
	}

	/**
	 * Add module-owned assets to Elementor's editor/preview asset loader.
	 *
	 * @param array<string,mixed> $assets Elementor asset-loader definition.
	 * @return void
	 */
	public static function add_loader_assets( array $assets ) {
		if ( ! class_exists( '\Elementor\Plugin' ) || empty( \Elementor\Plugin::$instance->assets_loader ) ) {
			return;
		}

		\Elementor\Plugin::$instance->assets_loader->add_assets( $assets );
	}

	/**
	 * Resolve an asset version from file modification time.
	 *
	 * @param string $relative_path Plugin-relative path.
	 * @return string
	 */
	public static function get_asset_version( $relative_path ) {
		$path = THECORE_COLLECTIVITY_MANAGEMENT_DIR . ltrim( $relative_path, '/' );
		if ( file_exists( $path ) ) {
			return (string) filemtime( $path );
		}

		return THECORE_COLLECTIVITY_MANAGEMENT_VERSION;
	}
}

if ( ! class_exists( 'Bellevue_Elementor', false ) ) {
	class_alias( 'TheCore_Collectivity_Elementor', 'Bellevue_Elementor' );
}
