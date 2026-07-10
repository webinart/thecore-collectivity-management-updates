<?php
/**
 * Declarative module metadata. Reading this file never loads feature classes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'menu-subtext'      => array(
		'class'                 => 'TheCore_Collectivity_Menu_Subtext',
		'file'                  => THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/class-thecore-collectivity-menu-subtext.php',
		'default_active'        => true,
		'dependencies'          => array(),
		'optional_dependencies' => array(),
	),
	'event-agenda'      => array(
		'class'                 => 'TheCore_Collectivity_Event_Agenda',
		'file'                  => THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/class-thecore-collectivity-event-agenda.php',
		'default_active'        => true,
		'dependencies'          => array(),
		'optional_dependencies' => array(),
	),
	'shared-taxonomies' => array(
		'class'                 => 'TheCore_Collectivity_Shared_Taxonomies_Module',
		'file'                  => THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/shared-taxonomies/class-thecore-collectivity-shared-taxonomies-module.php',
		'default_active'        => true,
		'dependencies'          => array(),
		'optional_dependencies' => array( 'maps', 'procedures', 'documents' ),
	),
	'maps'              => array(
		'class'                 => 'TheCore_Collectivity_Maps_Module',
		'file'                  => THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/maps/class-thecore-collectivity-maps-module.php',
		'default_active'        => true,
		'dependencies'          => array(),
		'optional_dependencies' => array( 'shared-taxonomies', 'transports' ),
	),
	'alerts'            => array(
		'class'                 => 'TheCore_Collectivity_Alerts_Module',
		'file'                  => THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/alerts/class-thecore-collectivity-alerts-module.php',
		'default_active'        => true,
		'dependencies'          => array(),
		'optional_dependencies' => array(),
	),
	'procedures'        => array(
		'class'                 => 'TheCore_Collectivity_Procedures_Module',
		'file'                  => THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/procedures/class-thecore-collectivity-procedures-module.php',
		'default_active'        => true,
		'dependencies'          => array(),
		'optional_dependencies' => array( 'documents', 'shared-taxonomies' ),
	),
	'documents'         => array(
		'class'                 => 'TheCore_Collectivity_Documents_Module',
		'file'                  => THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/documents/class-thecore-collectivity-documents-module.php',
		'default_active'        => true,
		'dependencies'          => array(),
		'optional_dependencies' => array( 'procedures', 'shared-taxonomies' ),
	),
	'transports'        => array(
		'class'                 => 'TheCore_Collectivity_Transports_Module',
		'file'                  => THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/transports/class-thecore-collectivity-transports-module.php',
		'default_active'        => true,
		'dependencies'          => array(),
		'optional_dependencies' => array( 'alerts' ),
	),
	'service-public'    => array(
		'class'                 => 'TheCore_Collectivity_Service_Public_Module',
		'file'                  => THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/service-public/class-thecore-collectivity-service-public-module.php',
		'default_active'        => true,
		'dependencies'          => array(),
		'optional_dependencies' => array(),
	),
	'ecotroc'           => array(
		'class'                 => 'TheCore_Collectivity_EcoTroc_Module',
		'file'                  => THECORE_COLLECTIVITY_MANAGEMENT_DIR . 'includes/features/ecotroc/class-thecore-collectivity-ecotroc-module.php',
		'default_active'        => false,
		'dependencies'          => array(),
		'optional_dependencies' => array(),
	),
);
