<?php
/**
 * No-op lifecycle defaults for lightweight modules.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class TheCore_Collectivity_Abstract_Module implements TheCore_Collectivity_Module_Interface {
	/**
	 * Install persistent structures.
	 *
	 * @return void
	 */
	public function install() {}

	/**
	 * Run migrations.
	 *
	 * @return void
	 */
	public function maybe_upgrade() {}

	/**
	 * Activate runtime state.
	 *
	 * @return void
	 */
	public function activate() {}

	/**
	 * Deactivate runtime state.
	 *
	 * @return void
	 */
	public function deactivate() {}
}
