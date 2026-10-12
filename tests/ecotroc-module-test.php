<?php
/** Module integration with the existing in-memory WordPress smoke harness. */
ini_set( 'log_errors', '0' );
require __DIR__ . '/plugin-load-smoke.php';

function get_current_blog_id() { return 1; }
function wp_safe_remote_request( $url, $args ) {
	throw new RuntimeException( 'Unexpected HTTP during module lifecycle.' );
}

$registry = TheCore_Collectivity_Management::instance()->get_module_registry();
tccm_smoke_assert( ! $registry->is_active( 'ecotroc' ), 'EcoTroc remains disabled by default.' );
tccm_smoke_assert( ! class_exists( 'TheCore_Collectivity_EcoTroc_Module', false ), 'A disabled EcoTroc implementation stays unloaded.' );

$definition = $registry->get_definition( 'ecotroc' );
tccm_smoke_assert( is_file( $definition['file'] ), 'The reserved module now has an implementation.' );
$GLOBALS['tccm_smoke_options']['tccm_module_states']['ecotroc'] = true;
$enabled = new TheCore_Collectivity_Module_Registry( $registry->get_definitions() );
tccm_smoke_assert( $enabled->is_active( 'ecotroc' ), 'The existing registry can resolve EcoTroc when explicitly enabled in memory.' );
tccm_smoke_assert( ! class_exists( $definition['class'], false ), 'Resolution does not load the module.' );

$options = $GLOBALS['tccm_smoke_options'];
$actions = $GLOBALS['tccm_smoke_actions'];
$filters = $GLOBALS['tccm_smoke_filters'];
require $definition['file'];
$module = new TheCore_Collectivity_EcoTroc_Module();
tccm_smoke_assert( 'ecotroc' === $module->get_id(), 'The implementation uses the reserved identifier.' );
foreach ( array( 'register_hooks', 'install', 'activate', 'maybe_upgrade', 'deactivate' ) as $method ) {
	$module->{$method}();
}
tccm_smoke_assert( $options === $GLOBALS['tccm_smoke_options'], 'Module lifecycle persists no settings or data.' );
tccm_smoke_assert( $actions === $GLOBALS['tccm_smoke_actions'] && $filters === $GLOBALS['tccm_smoke_filters'], 'Module adds no routes, widgets or cron hooks.' );
$client = $module->create_client();
$result = $client->get_version();
tccm_smoke_assert( ! $result['ok'] && 'configuration_error' === $result['error']['code'] && 'not_sent' === $result['error']['outcome'], 'Unconfigured connector sends nothing.' );
fwrite( STDOUT, "EcoTroc module integration tests passed (in-memory activation only).\n" );
