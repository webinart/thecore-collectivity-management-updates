<?php
/**
 * SIRI-Lite realtime adapter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Transports_SIRI_Lite_Realtime_Adapter extends TheCore_Collectivity_Transports_SIRI_Realtime_Adapter {
	/**
	 * Get supported format key.
	 *
	 * @return string
	 */
	public function get_format_key() {
		return 'siri_lite';
	}
}
