<?php
/**
 * Standalone smoke tests for the module activation resolver.
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['tccm_test_options'] = array();
$GLOBALS['tccm_test_filter']  = null;

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['tccm_test_options'] ) ? $GLOBALS['tccm_test_options'][ $key ] : $default;
}

function apply_filters( $hook, $value, ...$args ) {
	if ( 'thecore_collectivity/module_enabled' === $hook && is_callable( $GLOBALS['tccm_test_filter'] ) ) {
		return call_user_func( $GLOBALS['tccm_test_filter'], $value, ...$args );
	}

	return $value;
}

function tccm_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require_once dirname( __DIR__ ) . '/includes/modules/class-thecore-collectivity-module-registry.php';

$fixture = __DIR__ . '/fixtures/class-inactive-module.php';
$definitions = array(
	'base'       => array(
		'class'          => 'TCCM_Test_Base_Module',
		'file'           => __FILE__,
		'default_active' => true,
	),
	'dependent'  => array(
		'class'          => 'TCCM_Test_Dependent_Module',
		'file'           => __FILE__,
		'default_active' => true,
		'dependencies'   => array( 'base' ),
	),
	'commercial' => array(
		'class'          => 'TCCM_Test_Commercial_Module',
		'file'           => $fixture,
		'default_active' => false,
	),
	'optional'   => array(
		'class'                 => 'TCCM_Test_Optional_Module',
		'file'                  => __FILE__,
		'default_active'        => true,
		'optional_dependencies' => array( 'not-installed' ),
	),
);

$registry = new TheCore_Collectivity_Module_Registry( $definitions );
tccm_test_assert( array( 'base', 'dependent', 'optional' ) === $registry->get_active_ids(), 'Default-active modules should resolve in dependency order.' );
tccm_test_assert( ! class_exists( 'TCCM_Test_Inactive_Module', false ), 'A disabled module file must not be loaded by the registry.' );
tccm_test_assert( $registry->is_active( 'optional' ), 'A missing optional dependency must not disable its module.' );
tccm_test_assert( array( 'not-installed' ) === $registry->get_statuses()['optional']['unavailable_optional_dependencies'], 'Unavailable optional integrations should be diagnosed.' );

$GLOBALS['tccm_test_options'][ TheCore_Collectivity_Module_Registry::OPTION_MODULE_STATES ] = array( 'base' => false );
$registry = new TheCore_Collectivity_Module_Registry( $definitions );
tccm_test_assert( ! $registry->is_active( 'base' ), 'The per-site option should disable a default-active module.' );
tccm_test_assert( ! $registry->is_active( 'dependent' ), 'A disabled required dependency should disable its dependent.' );
tccm_test_assert( 'inactive_dependency' === $registry->get_statuses()['dependent']['reason'], 'Dependency failures should expose a stable diagnostic.' );

$GLOBALS['tccm_test_options'] = array();
$GLOBALS['tccm_test_filter']  = static function ( $enabled, $id ) {
	return 'commercial' === $id ? true : $enabled;
};
$registry = new TheCore_Collectivity_Module_Registry( $definitions );
tccm_test_assert( $registry->is_active( 'commercial' ), 'The PHP filter should support a future subscription decision.' );
tccm_test_assert( ! class_exists( 'TCCM_Test_Inactive_Module', false ), 'Resolving an enabled module still must not load its implementation.' );

$GLOBALS['tccm_test_filter'] = null;
$missing = new TheCore_Collectivity_Module_Registry(
	array(
		'broken' => array(
			'class'          => 'TCCM_Test_Broken_Module',
			'file'           => __FILE__,
			'default_active' => true,
			'dependencies'   => array( 'missing' ),
		),
	)
);
tccm_test_assert( ! $missing->is_active( 'broken' ), 'A missing required dependency should fail closed.' );
tccm_test_assert( 'missing_dependency' === $missing->get_statuses()['broken']['reason'], 'A missing dependency should be diagnosed.' );

fwrite( STDOUT, "Module registry tests passed.\n" );
