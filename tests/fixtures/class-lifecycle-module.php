<?php

final class TCCM_Test_Lifecycle_Module extends TheCore_Collectivity_Abstract_Module {
	public function get_id() {
		return 'lifecycle-fixture';
	}

	public function register_hooks() {
		$GLOBALS['tccm_lifecycle_log'][] = 'register_hooks';
	}

	public function install() {
		$GLOBALS['tccm_lifecycle_log'][] = 'install';
	}

	public function maybe_upgrade() {
		$GLOBALS['tccm_lifecycle_log'][] = 'maybe_upgrade';
	}

	public function activate() {
		$GLOBALS['tccm_lifecycle_log'][] = 'activate';
	}

	public function deactivate() {
		$GLOBALS['tccm_lifecycle_log'][] = 'deactivate';
	}
}
