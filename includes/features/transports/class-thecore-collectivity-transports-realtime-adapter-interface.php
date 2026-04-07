<?php
/**
 * Realtime transport adapter contract.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface TheCore_Collectivity_Transports_Realtime_Adapter_Interface {
	/**
	 * Get supported format key.
	 *
	 * @return string
	 */
	public function get_format_key();

	/**
	 * Parse one realtime payload into canonical predictions.
	 *
	 * @param string $payload Raw payload.
	 * @return array|WP_Error
	 */
	public function parse_trip_updates( $payload );

	/**
	 * Parse one realtime payload into canonical service alerts.
	 *
	 * @param string $payload Raw payload.
	 * @return array|WP_Error
	 */
	public function parse_service_alerts( $payload );

	/**
	 * Parse one realtime payload into canonical vehicle positions.
	 *
	 * @param string $payload Raw payload.
	 * @return array|WP_Error
	 */
	public function parse_vehicle_positions( $payload );
}
