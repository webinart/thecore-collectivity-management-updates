<?php
/**
 * Standalone plugin bootstrap smoke test with minimal WordPress hook stubs.
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WP_DEBUG', false );

$disabled = array_values( array_filter( array_map( 'trim', explode( ',', (string) getenv( 'TCCM_TEST_DISABLED' ) ) ) ) );
$filter_disabled = trim( (string) getenv( 'TCCM_TEST_FILTER_DISABLED' ) );
$states   = array();
foreach ( $disabled as $module_id ) {
	$states[ $module_id ] = false;
}

$GLOBALS['tccm_smoke_options'] = array(
	'tccm_module_states' => $states,
);
$GLOBALS['tccm_smoke_actions'] = array();
$GLOBALS['tccm_smoke_filters'] = array();
$GLOBALS['tccm_lifecycle_log'] = array();

$lifecycle_mode = (string) getenv( 'TCCM_TEST_LIFECYCLE' );
if ( 'enable' === $lifecycle_mode ) {
	$GLOBALS['tccm_smoke_options']['tccm_active_modules_snapshot'] = array();
} elseif ( 'disable' === $lifecycle_mode ) {
	$GLOBALS['tccm_smoke_options']['tccm_module_states']['lifecycle-fixture'] = false;
	$GLOBALS['tccm_smoke_options']['tccm_active_modules_snapshot']            = array( 'lifecycle-fixture' );
}

function plugin_basename( $file ) {
	return 'thecore-collectivity-management/' . basename( $file );
}

function plugin_dir_path( $file ) {
	return trailingslashit( dirname( $file ) );
}

function plugin_dir_url( $file ) {
	return 'https://example.test/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
}

function trailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' ) . '/';
}

function register_activation_hook( $file, $callback ) {
	$GLOBALS['tccm_smoke_activation'] = $callback;
}

function register_deactivation_hook( $file, $callback ) {
	$GLOBALS['tccm_smoke_deactivation'] = $callback;
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['tccm_smoke_actions'][ $hook ][] = $callback;
	return true;
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['tccm_smoke_filters'][ $hook ][] = $callback;
	return true;
}

function add_shortcode( $tag, $callback ) {
	$GLOBALS['tccm_smoke_shortcodes'][ $tag ] = $callback;
	return true;
}

function apply_filters( $hook, $value, ...$args ) {
	foreach ( $GLOBALS['tccm_smoke_filters'][ $hook ] ?? array() as $callback ) {
		$value = call_user_func( $callback, $value, ...$args );
	}
	return $value;
}

function do_action( $hook, ...$args ) {
	foreach ( $GLOBALS['tccm_smoke_actions'][ $hook ] ?? array() as $callback ) {
		call_user_func_array( $callback, $args );
	}
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['tccm_smoke_options'] ) ? $GLOBALS['tccm_smoke_options'][ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['tccm_smoke_options'][ $key ] = $value;
	return true;
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function get_file_data( $file, $headers, $context = '' ) {
	return array(
		'PluginName' => 'The Core - Collectivity Management',
		'PluginURI'  => 'https://the-core.local/',
		'Version'    => '1.0.1-beta.34',
		'AuthorName' => 'The Core',
		'UpdateURI'  => 'https://github.com/webinart/thecore-collectivity-management',
	);
}

function tccm_smoke_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

if ( getenv( 'TCCM_TEST_ELEMENTOR' ) ) {
	require __DIR__ . '/fixtures/elementor-stubs.php';
}

require dirname( __DIR__ ) . '/thecore-collectivity-management.php';

if ( $lifecycle_mode ) {
	add_filter(
		'thecore_collectivity/module_definitions',
		static function ( $definitions ) {
			$definitions['lifecycle-fixture'] = array(
				'class'          => 'TCCM_Test_Lifecycle_Module',
				'file'           => __DIR__ . '/fixtures/class-lifecycle-module.php',
				'default_active' => true,
			);

			return $definitions;
		}
	);
}

if ( '' !== $filter_disabled ) {
	add_filter(
		'thecore_collectivity/module_enabled',
		static function ( $enabled, $module_id ) use ( $filter_disabled ) {
			return $filter_disabled === $module_id ? false : $enabled;
		},
		10,
		2
	);
}

do_action( 'plugins_loaded' );
$management = TheCore_Collectivity_Management::instance();
$expected   = array(
	'menu-subtext',
	'event-agenda',
	'shared-taxonomies',
	'maps',
	'alerts',
	'procedures',
	'documents',
	'transports',
	'service-public',
);
$effective_disabled = array_values( array_unique( array_filter( array_merge( $disabled, array( $filter_disabled ) ) ) ) );
$active             = array_values( array_diff( $expected, $effective_disabled ) );

foreach ( $active as $module_id ) {
	tccm_smoke_assert( $management->is_module_active( $module_id ), "Module {$module_id} should be active." );
}

foreach ( $effective_disabled as $module_id ) {
	$definition = $management->get_module_registry()->get_definition( $module_id );
	tccm_smoke_assert( ! $management->is_module_active( $module_id ), "Module {$module_id} should be disabled." );
	if ( $definition ) {
		tccm_smoke_assert( ! class_exists( $definition['class'], false ), "Disabled module {$module_id} implementation should not be loaded." );
	}
}

$ecotroc_status = $management->get_module_registry()->get_status( 'ecotroc' );
tccm_smoke_assert( $ecotroc_status && 'disabled' === $ecotroc_status['reason'], 'The reserved EcoTroc module should remain disabled by default.' );
tccm_smoke_assert( ! class_exists( 'TheCore_Collectivity_EcoTroc_Module', false ), 'The unimplemented EcoTroc class must not be loaded.' );

tccm_smoke_assert( class_exists( 'Bellevue_Theme', false ), 'The historical Bellevue_Theme alias should remain available.' );
tccm_smoke_assert( class_exists( 'Bellevue_Elementor', false ), 'The historical Bellevue_Elementor alias should remain available.' );
tccm_smoke_assert( ! class_exists( 'TheCore_Collectivity_Map_Widget', false ), 'Elementor widget files should remain lazy before Elementor registration.' );

if ( ! in_array( 'transports', $effective_disabled, true ) ) {
	tccm_smoke_assert( null !== $management->get_transports_module(), 'The public transport getter should return its active module.' );
}

if ( in_array( 'alerts', $effective_disabled, true ) && ! in_array( 'transports', $effective_disabled, true ) ) {
	tccm_smoke_assert( class_exists( 'TheCore_Collectivity_Transports_Module', false ), 'Transport should load without its optional alert module.' );
	tccm_smoke_assert( ! class_exists( 'TheCore_Collectivity_Alerts_Module', false ), 'The optional alert implementation should remain unloaded.' );
}

if ( getenv( 'TCCM_TEST_ELEMENTOR' ) ) {
	$widgets_manager = new \Elementor\Widgets_Manager();
	do_action( 'elementor/widgets/register', $widgets_manager );

	$expected_widget_count = 0;
	$expected_widget_count += in_array( 'event-agenda', $effective_disabled, true ) ? 0 : 1;
	$expected_widget_count += in_array( 'alerts', $effective_disabled, true ) ? 0 : 1;
	$expected_widget_count += in_array( 'maps', $effective_disabled, true ) ? 0 : 6;
	$expected_widget_count += in_array( 'procedures', $effective_disabled, true ) ? 0 : 1;
	$expected_widget_count += in_array( 'transports', $effective_disabled, true ) ? 0 : 2;
	$expected_widget_count += in_array( 'service-public', $effective_disabled, true ) ? 0 : 3;

	tccm_smoke_assert( $expected_widget_count === count( $widgets_manager->widgets ), 'Only active modules should register Elementor widgets.' );
}

if ( 'enable' === $lifecycle_mode ) {
	tccm_smoke_assert( array( 'register_hooks', 'install', 'activate' ) === $GLOBALS['tccm_lifecycle_log'], 'A newly enabled module should register hooks, install and activate.' );
	do_action( 'init' );
	tccm_smoke_assert( 'maybe_upgrade' === end( $GLOBALS['tccm_lifecycle_log'] ), 'Active modules should receive their migration callback on init.' );
	TheCore_Collectivity_Management::deactivate();
	tccm_smoke_assert( 'deactivate' === end( $GLOBALS['tccm_lifecycle_log'] ), 'Plugin deactivation should delegate cleanup to active modules.' );
} elseif ( 'disable' === $lifecycle_mode ) {
	tccm_smoke_assert( array( 'deactivate' ) === $GLOBALS['tccm_lifecycle_log'], 'A newly disabled module should run its cleanup once without registering hooks.' );
}

$summary = $effective_disabled ? 'disabled=' . implode( ',', $effective_disabled ) : 'all defaults active';
fwrite( STDOUT, 'Plugin load smoke test passed: ' . $summary . ".\n" );
