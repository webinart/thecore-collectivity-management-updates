<?php
/**
 * Service-public feature module.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-thecore-collectivity-service-public-schema.php';
require_once __DIR__ . '/class-thecore-collectivity-service-public-repository.php';
require_once __DIR__ . '/class-thecore-collectivity-service-public-parser.php';
require_once __DIR__ . '/class-thecore-collectivity-service-public-importer.php';
require_once __DIR__ . '/class-thecore-collectivity-service-public-renderer.php';
require_once __DIR__ . '/class-thecore-collectivity-service-public-shortcodes.php';
require_once __DIR__ . '/class-thecore-collectivity-service-public-admin.php';

final class TheCore_Collectivity_Service_Public_Module {
	const CRON_HOOK              = 'tccm_service_public_sync';
	const REWRITE_VERSION        = '1';
	const OPTION_REWRITE_VERSION = 'tccm_service_public_rewrite_version';

	/**
	 * Schema.
	 *
	 * @var TheCore_Collectivity_Service_Public_Schema
	 */
	private $schema;

	/**
	 * Repository.
	 *
	 * @var TheCore_Collectivity_Service_Public_Repository
	 */
	private $repository;

	/**
	 * Importer.
	 *
	 * @var TheCore_Collectivity_Service_Public_Importer
	 */
	private $importer;

	/**
	 * Renderer.
	 *
	 * @var TheCore_Collectivity_Service_Public_Renderer
	 */
	private $renderer;

	/**
	 * Shortcodes.
	 *
	 * @var TheCore_Collectivity_Service_Public_Shortcodes
	 */
	private $shortcodes;

	/**
	 * Admin.
	 *
	 * @var TheCore_Collectivity_Service_Public_Admin
	 */
	private $admin;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->schema     = new TheCore_Collectivity_Service_Public_Schema();
		$this->repository = new TheCore_Collectivity_Service_Public_Repository();
		$parser           = new TheCore_Collectivity_Service_Public_Parser();
		$this->importer   = new TheCore_Collectivity_Service_Public_Importer( $this->repository, $parser );
		$this->renderer   = new TheCore_Collectivity_Service_Public_Renderer( $this->repository );
		$this->shortcodes = new TheCore_Collectivity_Service_Public_Shortcodes( $this->renderer );
		$this->admin      = new TheCore_Collectivity_Service_Public_Admin( $this->repository, $this->importer );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		$this->schema->register_hooks();
		$this->shortcodes->register_hooks();
		$this->admin->register_hooks();

		add_action( 'wp_enqueue_scripts', array( $this, 'register_front_assets' ) );
		add_action( 'init', array( $this, 'register_public_routes' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 20 );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_filter( 'document_title_parts', array( $this, 'filter_document_title_parts' ) );
		add_action( 'template_redirect', array( $this, 'render_public_item' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_scheduled_import' ) );
		add_action( 'init', array( $this, 'schedule_cron' ) );
	}

	/**
	 * Register frontend assets for widgets/shortcodes.
	 *
	 * @return void
	 */
	public function register_front_assets() {
		$src     = THECORE_COLLECTIVITY_MANAGEMENT_URL . 'includes/features/service-public/assets/service-public.css';
		$version = file_exists( THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/service-public/assets/service-public.css' ) ? (string) filemtime( THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/service-public/assets/service-public.css' ) : THECORE_COLLECTIVITY_MANAGEMENT_VERSION;
		$handles = array( 'tccm-service-public' );

		if ( class_exists( 'TheCore_Collectivity_Elementor', false ) ) {
			$handles[] = TheCore_Collectivity_Elementor::HANDLE_SERVICE_PUBLIC_STYLE;
		}

		foreach ( array_unique( $handles ) as $handle ) {
			if ( ! wp_style_is( $handle, 'registered' ) ) {
				wp_register_style( $handle, $src, array(), $version );
			}
		}
	}

	/**
	 * Register public rewrite rules.
	 *
	 * @return void
	 */
	public function register_public_routes() {
		add_rewrite_rule(
			'^service-public/(particuliers|professionnels)/([^/]+)/?$',
			'index.php?tccm_sp_audience=$matches[1]&tccm_sp_id=$matches[2]',
			'top'
		);
	}

	/**
	 * Flush rewrite rules once when public routes change.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrite_rules() {
		if ( self::REWRITE_VERSION === get_option( self::OPTION_REWRITE_VERSION ) ) {
			return;
		}

		$this->register_public_routes();
		flush_rewrite_rules( false );
		update_option( self::OPTION_REWRITE_VERSION, self::REWRITE_VERSION, false );
	}

	/**
	 * Register public query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'tccm_sp_audience';
		$vars[] = 'tccm_sp_id';

		return $vars;
	}

	/**
	 * Render public Service-public item pages.
	 *
	 * @return void
	 */
	public function render_public_item() {
		$audience = sanitize_key( get_query_var( 'tccm_sp_audience' ) );
		$item_id  = sanitize_text_field( rawurldecode( (string) get_query_var( 'tccm_sp_id' ) ) );

		if ( ! $audience || ! $item_id ) {
			return;
		}

		global $wp_query;

		$item = $this->repository->get_item( $audience, $item_id );
		if ( $wp_query ) {
			$wp_query->is_404 = ! $item;
		}

		status_header( $item ? 200 : 404 );
		nocache_headers();
		get_header();
		echo '<main id="primary" class="site-main tccm-service-public-page">';
		echo $this->renderer->render_item( $audience, $item_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</main>';
		get_footer();
		exit;
	}

	/**
	 * Improve document title on public item pages.
	 *
	 * @param array $parts Title parts.
	 * @return array
	 */
	public function filter_document_title_parts( $parts ) {
		$audience = sanitize_key( get_query_var( 'tccm_sp_audience' ) );
		$item_id  = sanitize_text_field( rawurldecode( (string) get_query_var( 'tccm_sp_id' ) ) );

		if ( ! $audience || ! $item_id ) {
			return $parts;
		}

		$item = $this->repository->get_item( $audience, $item_id );
		if ( $item && ! empty( $item['title'] ) ) {
			$parts['title'] = $item['title'];
		}

		return $parts;
	}

	/**
	 * Ensure daily cron exists.
	 *
	 * @return void
	 */
	public function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Run daily import.
	 *
	 * @return void
	 */
	public function run_scheduled_import() {
		$this->importer->import_enabled_sources();
	}

	/**
	 * Get schema.
	 *
	 * @return TheCore_Collectivity_Service_Public_Schema
	 */
	public function get_schema() {
		return $this->schema;
	}

	/**
	 * Get repository.
	 *
	 * @return TheCore_Collectivity_Service_Public_Repository
	 */
	public function get_repository() {
		return $this->repository;
	}

	/**
	 * Get importer.
	 *
	 * @return TheCore_Collectivity_Service_Public_Importer
	 */
	public function get_importer() {
		return $this->importer;
	}

	/**
	 * Get renderer.
	 *
	 * @return TheCore_Collectivity_Service_Public_Renderer
	 */
	public function get_renderer() {
		return $this->renderer;
	}
}
