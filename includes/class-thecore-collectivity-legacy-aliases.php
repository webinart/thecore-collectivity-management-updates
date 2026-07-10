<?php
/**
 * Backward compatibility aliases for legacy Bellevue class names.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Legacy_Aliases {
	/**
	 * Register aliases for classes belonging to active modules.
	 *
	 * @return void
	 */
	public static function register() {
		$aliases = array(
			'Bellevue_Menu_Subtext'                   => 'TheCore_Collectivity_Menu_Subtext',
			'Bellevue_Event_Agenda'                   => 'TheCore_Collectivity_Event_Agenda',
			'Bellevue_Alerts_Module'                  => 'TheCore_Collectivity_Alerts_Module',
			'Bellevue_Alerts_Post_Type'               => 'TheCore_Collectivity_Alerts_Post_Type',
			'Bellevue_Alerts_Meta'                    => 'TheCore_Collectivity_Alerts_Meta',
			'Bellevue_Alerts_Repository'              => 'TheCore_Collectivity_Alerts_Repository',
			'Bellevue_Alerts_Renderer'                => 'TheCore_Collectivity_Alerts_Renderer',
			'Bellevue_Transports_Module'              => 'TheCore_Collectivity_Transports_Module',
			'Bellevue_Transports_Post_Types'          => 'TheCore_Collectivity_Transports_Post_Types',
			'Bellevue_Transports_Meta'                => 'TheCore_Collectivity_Transports_Meta',
			'Bellevue_Transports_Repository'          => 'TheCore_Collectivity_Transports_Repository',
			'Bellevue_Transports_Normalizer'          => 'TheCore_Collectivity_Transports_Normalizer',
			'Bellevue_Transports_GTFS_Importer'       => 'TheCore_Collectivity_Transports_GTFS_Importer',
			'Bellevue_Transports_Schedules'           => 'TheCore_Collectivity_Transports_Schedules',
			'Bellevue_Transports_Schedule_Admin'      => 'TheCore_Collectivity_Transports_Schedule_Admin',
			'Bellevue_Transports_Schedule_Repository' => 'TheCore_Collectivity_Transports_Schedule_Repository',
			'Bellevue_Transports_Schedule_Schema'     => 'TheCore_Collectivity_Transports_Schedule_Schema',
		);

		foreach ( $aliases as $legacy_class => $current_class ) {
			if ( class_exists( $current_class, false ) && ! class_exists( $legacy_class, false ) ) {
				class_alias( $current_class, $legacy_class );
			}
		}
	}
}
