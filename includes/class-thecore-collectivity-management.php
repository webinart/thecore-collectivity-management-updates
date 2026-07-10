<?php
/**
 * Main plugin coordinator and active module loader.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Management {
	/**
	 * Singleton instance.
	 *
	 * @var TheCore_Collectivity_Management|null
	 */
	private static $instance = null;

	/**
	 * Module activation registry.
	 *
	 * @var TheCore_Collectivity_Module_Registry
	 */
	private $registry;

	/**
	 * Loaded active modules keyed by stable id.
	 *
	 * @var array<string,TheCore_Collectivity_Module_Interface>
	 */
	private $modules = array();

	/**
	 * Get singleton instance.
	 *
	 * @return TheCore_Collectivity_Management
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boot after all plugins have had a chance to register module filters.
	 *
	 * @return void
	 */
	public static function boot() {
		self::instance();
	}

	/**
	 * Plugin activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		$instance = self::instance();

		foreach ( $instance->modules as $module ) {
			$instance->run_lifecycle_method( $module, 'install' );
			$instance->run_lifecycle_method( $module, 'activate' );
		}

		update_option(
			TheCore_Collectivity_Module_Registry::OPTION_ACTIVE_SNAPSHOT,
			array_keys( $instance->modules ),
			false
		);
	}

	/**
	 * Plugin deactivation callback.
	 *
	 * @return void
	 */
	public static function deactivate() {
		$instance = self::instance();
		$modules  = array_reverse( $instance->modules, true );

		foreach ( $modules as $module ) {
			$instance->run_lifecycle_method( $module, 'deactivate' );
		}
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$definitions    = require THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/module-definitions.php';
		$this->registry = new TheCore_Collectivity_Module_Registry( (array) $definitions );

		TheCore_Collectivity_Elementor::register_hooks();
		$this->load_active_modules();
		$this->register_active_module_hooks();
		add_action( 'init', array( $this, 'maybe_upgrade_active_modules' ), 5 );

		TheCore_Collectivity_Legacy_Aliases::register();
		$this->synchronize_module_lifecycle();
		$this->publish_resolution_diagnostics();
	}

	/**
	 * Load only module classes that resolved as active.
	 *
	 * @return void
	 */
	private function load_active_modules() {
		foreach ( $this->registry->get_active_ids() as $id ) {
			$definition = $this->registry->get_definition( $id );
			if ( ! $definition || ! $this->runtime_dependencies_available( $definition ) ) {
				$this->registry->mark_unavailable( $id, 'runtime_dependency_unavailable' );
				continue;
			}

			$module = $this->instantiate_module( $definition );
			if ( ! $module ) {
				continue;
			}

			$this->modules[ $id ] = $module;
		}
	}

	/**
	 * Instantiate and validate one module implementation.
	 *
	 * @param array<string,mixed> $definition Module definition.
	 * @return TheCore_Collectivity_Module_Interface|null
	 */
	private function instantiate_module( array $definition ) {
		$id    = (string) $definition['id'];
		$class = (string) $definition['class'];
		$file  = (string) $definition['file'];

		if ( ! is_file( $file ) ) {
			$this->registry->mark_unavailable( $id, 'missing_file' );
			return null;
		}

		require_once $file;

		if ( ! class_exists( $class, false ) ) {
			$this->registry->mark_unavailable( $id, 'missing_class' );
			return null;
		}

		try {
			$module = new $class();
		} catch ( Throwable $error ) {
			$this->registry->mark_unavailable( $id, 'construction_failed' );
			$this->publish_module_error( $id, 'construction_failed', $error );
			return null;
		}

		if ( ! $module instanceof TheCore_Collectivity_Module_Interface ) {
			$this->registry->mark_unavailable( $id, 'invalid_contract' );
			return null;
		}

		if ( $id !== $module->get_id() ) {
			$this->registry->mark_unavailable( $id, 'id_mismatch' );
			return null;
		}

		return $module;
	}

	/**
	 * Ensure runtime instances exist for required dependencies.
	 *
	 * @param array<string,mixed> $definition Module definition.
	 * @return bool
	 */
	private function runtime_dependencies_available( array $definition ) {
		foreach ( (array) $definition['dependencies'] as $dependency_id ) {
			if ( ! isset( $this->modules[ $dependency_id ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Let active modules own and register all of their hooks.
	 *
	 * @return void
	 */
	private function register_active_module_hooks() {
		foreach ( $this->modules as $id => $module ) {
			try {
				$module->register_hooks();
			} catch ( Throwable $error ) {
				unset( $this->modules[ $id ] );
				$this->registry->mark_unavailable( $id, 'hook_registration_failed' );
				$this->publish_module_error( $id, 'hook_registration_failed', $error );
			}
		}
	}

	/**
	 * Run version-gated migrations for active modules.
	 *
	 * @return void
	 */
	public function maybe_upgrade_active_modules() {
		foreach ( $this->modules as $module ) {
			$this->run_lifecycle_method( $module, 'maybe_upgrade' );
		}
	}

	/**
	 * Apply lifecycle transitions when module activation decisions change.
	 *
	 * @return void
	 */
	private function synchronize_module_lifecycle() {
		$current  = array_keys( $this->modules );
		$previous = get_option( TheCore_Collectivity_Module_Registry::OPTION_ACTIVE_SNAPSHOT, null );

		if ( ! is_array( $previous ) ) {
			update_option( TheCore_Collectivity_Module_Registry::OPTION_ACTIVE_SNAPSHOT, $current, false );
			return;
		}

		$disabled = array_values( array_diff( $previous, $current ) );
		foreach ( array_reverse( $disabled ) as $id ) {
			$definition = $this->registry->get_definition( $id );
			if ( ! $definition ) {
				continue;
			}

			$module = $this->instantiate_module( $definition );
			if ( $module ) {
				$this->run_lifecycle_method( $module, 'deactivate' );
			}
		}

		$enabled = array_values( array_diff( $current, $previous ) );
		foreach ( $enabled as $id ) {
			if ( ! isset( $this->modules[ $id ] ) ) {
				continue;
			}

			$this->run_lifecycle_method( $this->modules[ $id ], 'install' );
			$this->run_lifecycle_method( $this->modules[ $id ], 'activate' );
		}

		update_option( TheCore_Collectivity_Module_Registry::OPTION_ACTIVE_SNAPSHOT, $current, false );
	}

	/**
	 * Invoke one module lifecycle method without taking down the plugin.
	 *
	 * @param TheCore_Collectivity_Module_Interface $module Module instance.
	 * @param string                                $method Lifecycle method.
	 * @return void
	 */
	private function run_lifecycle_method( TheCore_Collectivity_Module_Interface $module, $method ) {
		try {
			$module->{$method}();
		} catch ( Throwable $error ) {
			$this->publish_module_error( $module->get_id(), $method . '_failed', $error );
		}
	}

	/**
	 * Publish non-fatal module errors for logging or an external status UI.
	 *
	 * @param string    $id      Module id.
	 * @param string    $reason  Stable reason code.
	 * @param Throwable $error   Runtime error.
	 * @return void
	 */
	private function publish_module_error( $id, $reason, Throwable $error ) {
		do_action( 'thecore_collectivity/module_error', $id, $reason, $error );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'The Core Collectivity module %s: %s (%s)', $id, $reason, $error->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Publish resolver diagnostics for unavailable requested modules.
	 *
	 * @return void
	 */
	private function publish_resolution_diagnostics() {
		foreach ( $this->registry->get_statuses() as $id => $status ) {
			if ( ! empty( $status['requested'] ) && empty( $status['active'] ) ) {
				do_action( 'thecore_collectivity/module_unavailable', $id, $status );
			}
		}
	}

	/**
	 * Return the module registry.
	 *
	 * @return TheCore_Collectivity_Module_Registry
	 */
	public function get_module_registry() {
		return $this->registry;
	}

	/**
	 * Return one loaded active module.
	 *
	 * @param string $id Module id.
	 * @return TheCore_Collectivity_Module_Interface|null
	 */
	public function get_module( $id ) {
		$id = sanitize_key( (string) $id );
		return isset( $this->modules[ $id ] ) ? $this->modules[ $id ] : null;
	}

	/**
	 * Whether a module is loaded and active.
	 *
	 * @param string $id Module id.
	 * @return bool
	 */
	public function is_module_active( $id ) {
		return null !== $this->get_module( $id );
	}

	/**
	 * Get the event agenda feature instance.
	 *
	 * @return TheCore_Collectivity_Event_Agenda|null
	 */
	public function get_event_agenda_feature() {
		return $this->get_module( 'event-agenda' );
	}

	/**
	 * Get shared taxonomies module instance.
	 *
	 * @return TheCore_Collectivity_Shared_Taxonomies_Module|null
	 */
	public function get_shared_taxonomies_module() {
		return $this->get_module( 'shared-taxonomies' );
	}

	/**
	 * Get maps module instance.
	 *
	 * @return TheCore_Collectivity_Maps_Module|null
	 */
	public function get_maps_module() {
		return $this->get_module( 'maps' );
	}

	/**
	 * Get alerts module instance.
	 *
	 * @return TheCore_Collectivity_Alerts_Module|null
	 */
	public function get_alerts_module() {
		return $this->get_module( 'alerts' );
	}

	/**
	 * Get procedures module instance.
	 *
	 * @return TheCore_Collectivity_Procedures_Module|null
	 */
	public function get_procedures_module() {
		return $this->get_module( 'procedures' );
	}

	/**
	 * Get documents module instance.
	 *
	 * @return TheCore_Collectivity_Documents_Module|null
	 */
	public function get_documents_module() {
		return $this->get_module( 'documents' );
	}

	/**
	 * Get transports module instance.
	 *
	 * @return TheCore_Collectivity_Transports_Module|null
	 */
	public function get_transports_module() {
		return $this->get_module( 'transports' );
	}

	/**
	 * Get Service-public module instance.
	 *
	 * @return TheCore_Collectivity_Service_Public_Module|null
	 */
	public function get_service_public_module() {
		return $this->get_module( 'service-public' );
	}
}

if ( ! class_exists( 'Bellevue_Theme', false ) ) {
	class_alias( 'TheCore_Collectivity_Management', 'Bellevue_Theme' );
}
