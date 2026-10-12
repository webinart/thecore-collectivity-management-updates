<?php
/**
 * Candidate connector only. No site activation, UI, cron, routes or business data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-thecore-collectivity-ecotroc-client.php';

final class TheCore_Collectivity_EcoTroc_Module extends TheCore_Collectivity_Abstract_Module {
	public function get_id() {
		return 'ecotroc';
	}

	public function register_hooks() {}

	/**
	 * Make a request-scoped client for this site. The trusted context bridge is
	 * deliberately supplied by the caller; a WP role is never an identity proof.
	 *
	 * @param callable|null $context_provider Candidate context dependency.
	 * @return TheCore_Collectivity_EcoTroc_Client
	 */
	public function create_client( $context_provider = null ) {
		return new TheCore_Collectivity_EcoTroc_Client( new TheCore_Collectivity_EcoTroc_Config(), $context_provider );
	}
}
