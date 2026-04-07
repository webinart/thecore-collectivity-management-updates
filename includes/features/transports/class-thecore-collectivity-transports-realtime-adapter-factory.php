<?php
/**
 * Realtime adapter factory.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Transports_Realtime_Adapter_Factory {
	/**
	 * Registered adapters.
	 *
	 * @var array<string,TheCore_Collectivity_Transports_Realtime_Adapter_Interface>
	 */
	private $adapters = array();

	/**
	 * Constructor.
	 *
	 * @param array $adapters Adapter instances.
	 */
	public function __construct( array $adapters = array() ) {
		foreach ( $adapters as $adapter ) {
			if ( $adapter instanceof TheCore_Collectivity_Transports_Realtime_Adapter_Interface ) {
				$this->adapters[ $adapter->get_format_key() ] = $adapter;
			}
		}
	}

	/**
	 * Get adapter for one realtime format.
	 *
	 * @param string $format Realtime format.
	 * @return TheCore_Collectivity_Transports_Realtime_Adapter_Interface|null
	 */
	public function get_adapter( $format ) {
		$format = sanitize_key( (string) $format );
		return isset( $this->adapters[ $format ] ) ? $this->adapters[ $format ] : null;
	}
}
