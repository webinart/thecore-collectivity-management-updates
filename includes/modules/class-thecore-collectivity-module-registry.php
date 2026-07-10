<?php
/**
 * Lightweight module registry and activation resolver.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Module_Registry {
	/**
	 * Per-site explicit module state overrides.
	 */
	const OPTION_MODULE_STATES = 'tccm_module_states';

	/**
	 * Last resolved active state, used for module lifecycle transitions.
	 */
	const OPTION_ACTIVE_SNAPSHOT = 'tccm_active_modules_snapshot';

	/**
	 * Normalized definitions keyed by stable module id.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private $definitions = array();

	/**
	 * Resolution diagnostics keyed by module id.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private $statuses = array();

	/**
	 * Ordered active module ids.
	 *
	 * @var array<int,string>|null
	 */
	private $active_ids = null;

	/**
	 * Constructor.
	 *
	 * @param array<string,array<string,mixed>> $definitions Module definitions.
	 */
	public function __construct( array $definitions ) {
		if ( function_exists( 'apply_filters' ) ) {
			$definitions = (array) apply_filters( 'thecore_collectivity/module_definitions', $definitions );
		}

		foreach ( $definitions as $id => $definition ) {
			$this->register( $id, $definition );
		}
	}

	/**
	 * Register one declarative module definition.
	 *
	 * @param string              $id         Stable module identifier.
	 * @param array<string,mixed> $definition Definition values.
	 * @return bool
	 */
	public function register( $id, array $definition ) {
		$id = $this->normalize_id( $id );
		if ( '' === $id ) {
			return false;
		}

		$class = isset( $definition['class'] ) ? trim( (string) $definition['class'] ) : '';
		$file  = isset( $definition['file'] ) ? (string) $definition['file'] : '';
		if ( '' === $class || '' === $file ) {
			return false;
		}

		$this->definitions[ $id ] = array(
			'id'                    => $id,
			'class'                 => $class,
			'file'                  => $file,
			'default_active'        => ! empty( $definition['default_active'] ),
			'dependencies'          => $this->normalize_ids( $definition['dependencies'] ?? array() ),
			'optional_dependencies' => $this->normalize_ids( $definition['optional_dependencies'] ?? array() ),
		);

		$this->active_ids = null;
		$this->statuses   = array();

		return true;
	}

	/**
	 * Return all module definitions.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_definitions() {
		return $this->definitions;
	}

	/**
	 * Return one definition.
	 *
	 * @param string $id Module id.
	 * @return array<string,mixed>|null
	 */
	public function get_definition( $id ) {
		$id = $this->normalize_id( $id );
		return isset( $this->definitions[ $id ] ) ? $this->definitions[ $id ] : null;
	}

	/**
	 * Resolve and return active module ids in required-dependency order.
	 *
	 * @return array<int,string>
	 */
	public function get_active_ids() {
		if ( null !== $this->active_ids ) {
			return $this->active_ids;
		}

		$this->active_ids = array();
		$this->statuses   = array();

		foreach ( array_keys( $this->definitions ) as $id ) {
			$this->resolve_module( $id, array() );
		}

		$this->decorate_optional_dependency_statuses();

		return $this->active_ids;
	}

	/**
	 * Whether a module resolved as active.
	 *
	 * @param string $id Module id.
	 * @return bool
	 */
	public function is_active( $id ) {
		$id = $this->normalize_id( $id );
		return in_array( $id, $this->get_active_ids(), true );
	}

	/**
	 * Return resolution statuses.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_statuses() {
		$this->get_active_ids();
		return $this->statuses;
	}

	/**
	 * Return one module resolution status.
	 *
	 * @param string $id Module id.
	 * @return array<string,mixed>|null
	 */
	public function get_status( $id ) {
		$id       = $this->normalize_id( $id );
		$statuses = $this->get_statuses();
		return isset( $statuses[ $id ] ) ? $statuses[ $id ] : null;
	}

	/**
	 * Record a runtime loading failure and remove the module from active ids.
	 *
	 * @param string $id     Module id.
	 * @param string $reason Stable reason code.
	 * @return void
	 */
	public function mark_unavailable( $id, $reason ) {
		$id = $this->normalize_id( $id );
		$this->get_active_ids();

		if ( ! isset( $this->definitions[ $id ] ) ) {
			return;
		}

		$this->statuses[ $id ] = array(
			'active'                              => false,
			'requested'                           => true,
			'reason'                              => $this->normalize_id( $reason ),
			'dependency'                          => '',
			'unavailable_optional_dependencies'  => array(),
		);
		$this->active_ids      = array_values( array_diff( $this->active_ids, array( $id ) ) );
	}

	/**
	 * Resolve one module recursively through required dependencies.
	 *
	 * @param string            $id    Module id.
	 * @param array<int,string> $stack Current dependency stack.
	 * @return bool
	 */
	private function resolve_module( $id, array $stack ) {
		if ( isset( $this->statuses[ $id ] ) ) {
			return ! empty( $this->statuses[ $id ]['active'] );
		}

		if ( in_array( $id, $stack, true ) ) {
			$this->statuses[ $id ] = array(
				'active'     => false,
				'requested'  => true,
				'reason'     => 'circular_dependency',
				'dependency' => $id,
			);
			return false;
		}

		if ( ! isset( $this->definitions[ $id ] ) ) {
			return false;
		}

		$definition = $this->definitions[ $id ];
		$requested  = $this->resolve_requested_state( $id, $definition );
		if ( ! $requested ) {
			$this->statuses[ $id ] = array(
				'active'     => false,
				'requested'  => false,
				'reason'     => 'disabled',
				'dependency' => '',
			);
			return false;
		}

		if ( ! is_file( $definition['file'] ) ) {
			$this->statuses[ $id ] = array(
				'active'     => false,
				'requested'  => true,
				'reason'     => 'missing_file',
				'dependency' => '',
			);
			return false;
		}

		$stack[] = $id;
		foreach ( $definition['dependencies'] as $dependency_id ) {
			if ( ! isset( $this->definitions[ $dependency_id ] ) ) {
				$this->statuses[ $id ] = array(
					'active'     => false,
					'requested'  => true,
					'reason'     => 'missing_dependency',
					'dependency' => $dependency_id,
				);
				return false;
			}

			if ( ! $this->resolve_module( $dependency_id, $stack ) ) {
				$this->statuses[ $id ] = array(
					'active'     => false,
					'requested'  => true,
					'reason'     => 'inactive_dependency',
					'dependency' => $dependency_id,
				);
				return false;
			}
		}

		$this->statuses[ $id ] = array(
			'active'     => true,
			'requested'  => true,
			'reason'     => 'active',
			'dependency' => '',
		);

		if ( ! in_array( $id, $this->active_ids, true ) ) {
			$this->active_ids[] = $id;
		}

		return true;
	}

	/**
	 * Apply default, per-site override and final PHP decision filter.
	 *
	 * @param string              $id         Module id.
	 * @param array<string,mixed> $definition Module definition.
	 * @return bool
	 */
	private function resolve_requested_state( $id, array $definition ) {
		$enabled   = ! empty( $definition['default_active'] );
		$overrides = function_exists( 'get_option' ) ? get_option( self::OPTION_MODULE_STATES, array() ) : array();
		$overrides = is_array( $overrides ) ? $overrides : array();

		if ( array_key_exists( $id, $overrides ) ) {
			$enabled = $this->normalize_boolean( $overrides[ $id ] );
		}

		if ( function_exists( 'apply_filters' ) ) {
			$enabled = (bool) apply_filters( 'thecore_collectivity/module_enabled', $enabled, $id, $definition );
		}

		return $enabled;
	}

	/**
	 * Expose unavailable optional integrations without disabling their owners.
	 *
	 * @return void
	 */
	private function decorate_optional_dependency_statuses() {
		foreach ( $this->statuses as $id => $status ) {
			$unavailable = array();
			if ( ! empty( $status['active'] ) ) {
				foreach ( $this->definitions[ $id ]['optional_dependencies'] as $dependency_id ) {
					if ( ! isset( $this->statuses[ $dependency_id ] ) || empty( $this->statuses[ $dependency_id ]['active'] ) ) {
						$unavailable[] = $dependency_id;
					}
				}
			}

			$this->statuses[ $id ]['unavailable_optional_dependencies'] = $unavailable;
		}
	}

	/**
	 * Normalize an id without requiring WordPress in standalone tests.
	 *
	 * @param mixed $id Raw id.
	 * @return string
	 */
	private function normalize_id( $id ) {
		$id = (string) $id;
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $id );
		}

		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $id ) );
	}

	/**
	 * Normalize a list of ids.
	 *
	 * @param mixed $ids Raw ids.
	 * @return array<int,string>
	 */
	private function normalize_ids( $ids ) {
		$normalized = array();
		foreach ( (array) $ids as $id ) {
			$id = $this->normalize_id( $id );
			if ( '' !== $id ) {
				$normalized[] = $id;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Normalize values accepted in the state override option.
	 *
	 * @param mixed $value Raw option value.
	 * @return bool
	 */
	private function normalize_boolean( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return 1 === (int) $value;
		}

		return in_array( strtolower( trim( (string) $value ) ), array( '1', 'true', 'yes', 'on', 'enabled' ), true );
	}
}
