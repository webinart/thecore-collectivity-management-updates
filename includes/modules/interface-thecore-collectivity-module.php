<?php
/**
 * Runtime contract implemented by every collectivity module.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface TheCore_Collectivity_Module_Interface {
	/**
	 * Return the stable module identifier.
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Register WordPress and third-party hooks owned by the module.
	 *
	 * @return void
	 */
	public function register_hooks();

	/**
	 * Install persistent structures required by the module.
	 *
	 * @return void
	 */
	public function install();

	/**
	 * Run version-gated module migrations when needed.
	 *
	 * @return void
	 */
	public function maybe_upgrade();

	/**
	 * Activate schedules or runtime state owned by the module.
	 *
	 * @return void
	 */
	public function activate();

	/**
	 * Remove schedules or transient runtime state without deleting content.
	 *
	 * @return void
	 */
	public function deactivate();
}
