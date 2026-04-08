<?php
/**
 * Plugin Name: The Core - Collectivity Management
 * Plugin URI:  https://the-core.local/
 * Description: Common collectivity management module for The Core based sites.
 * Update URI:  https://github.com/webinart/thecore-collectivity-management
 * Version:     1.0.1-beta.12
 * Author:      The Core
 * Text Domain: thecore-collectivity-management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'THECORE_COLLECTIVITY_MANAGEMENT_FILE', __FILE__ );
define( 'THECORE_COLLECTIVITY_MANAGEMENT_BASENAME', plugin_basename( __FILE__ ) );
define( 'THECORE_COLLECTIVITY_MANAGEMENT_DIR', plugin_dir_path( __FILE__ ) );
define( 'THECORE_COLLECTIVITY_MANAGEMENT_URL', plugin_dir_url( __FILE__ ) );
define( 'THECORE_COLLECTIVITY_MANAGEMENT_VERSION', '1.0.1-beta.12' );

$thecore_collectivity_management_autoload = THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'vendor/autoload.php';
if ( file_exists( $thecore_collectivity_management_autoload ) ) {
	require_once $thecore_collectivity_management_autoload;
}

require_once THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/class-thecore-collectivity-elementor.php';
require_once THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/class-thecore-collectivity-management.php';
require_once THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/class-thecore-collectivity-legacy-aliases.php';
require_once THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/class-thecore-collectivity-plugin-updater.php';

register_activation_hook( THECORE_COLLECTIVITY_MANAGEMENT_FILE, array( 'TheCore_Collectivity_Management', 'activate' ) );
register_deactivation_hook( THECORE_COLLECTIVITY_MANAGEMENT_FILE, array( 'TheCore_Collectivity_Management', 'deactivate' ) );

TheCore_Collectivity_Plugin_Updater::register_hooks();
TheCore_Collectivity_Management::instance();
