<?php
/**
 * Standalone coverage for the menu guide's task access and site-specific workflow.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'THECORE_COLLECTIVITY_PDF_CONFIG', __FILE__ . '/unused' );
require_once dirname( __DIR__ ) . '/includes/modules/interface-thecore-collectivity-module.php';
require_once dirname( __DIR__ ) . '/includes/modules/abstract-thecore-collectivity-module.php';
require_once dirname( __DIR__ ) . '/includes/features/school-menus/class-thecore-collectivity-school-menus-module.php';

class WP_Error {}
function is_wp_error( $result ) { return $result instanceof WP_Error; }
function add_action( $name, $callback ) {}
function add_filter( $name, $callback ) { $GLOBALS['callbacks'][ $name ][] = $callback; }
function get_post_type_object( $type ) {
	return 'tccm_school_menu' === $type ? (object) array( 'cap' => (object) array(
		'edit_posts' => 'edit_test_menus', 'publish_posts' => 'publish_test_menus',
	) ) : null;
}
function get_taxonomy( $type ) {
	return 'tccm_food_marker' === $type ? (object) array( 'cap' => (object) array( 'assign_terms' => 'assign_test_markers' ) ) : null;
}
function current_user_can( $cap ) { return in_array( $cap, $GLOBALS['caps'], true ); }
function __( $text, $domain ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html_e( $text, $domain ) { echo esc_html( $text ); }
function apply_filters( $name, $value ) {
	foreach ( $GLOBALS['callbacks'][ $name ] ?? array() as $callback ) {
		$value = $callback( $value );
	}
	return $GLOBALS['filters'][ $name ] ?? $value;
}
function check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }

$previous = array( 'other-guide' => array( 'screen' => 'other' ) );
$hook = 'thecore_administration_workspace_guides';
check( apply_filters( $hook, $previous ) === $previous, 'A disabled module contributes no guide.' );
( new TheCore_Collectivity_School_Menus_Module() )->register_hooks();
foreach ( array( array(), array( 'edit_test_menus' ), array( 'assign_test_markers' ) ) as $caps ) {
	check( apply_filters( $hook, $previous ) === $previous, 'Both editing and markers permissions are required.' );
}
$caps = array( 'edit_test_menus', 'assign_test_markers' );
$guides = apply_filters( $hook, $previous );
$guide = $guides['school-menu-week'];
check( $previous['other-guide'] === $guides['other-guide'] && 'school-menus' === $guide['screen'], 'Guide must augment the registry and bind to the editor.' );
check( 'Renseigner le menu de la semaine' === $guide['title'], 'Guide title mismatch.' );

function read_guide( $action ) {
	$GLOBALS['filters']['thecore_collectivity_school_menu_guide_action'] = $action;
	ob_start();
	call_user_func( $GLOBALS['guide']['render'] );
	return ob_get_clean();
}
$actions = array( 'publish' => 'Publier le menu', 'submit' => 'Envoyer pour validation', 'complete' => 'Terminer la saisie' );
foreach ( array( '', 'draft', 'submit', 'complete', 'unknown', 'publish' ) as $action ) {
	$body = read_guide( $action );
	foreach ( $actions as $key => $label ) {
		$offered = $action === $key && 'publish' !== $key;
		check( $offered === ( false !== strpos( $body, $label ) ), 'Unexpected action ' . $key . ' for ' . $action );
	}
	if ( '' === $action || 'unknown' === $action ) {
		check( false === strpos( $body, 'Aperçu du menu' ), 'Unregistered editors must get generic save instructions.' );
	}
}
$caps[] = 'publish_test_menus';
check( false !== strpos( read_guide( 'publish' ), 'Publier le menu' ), 'Publication must appear for the configured publisher.' );
$filters['thecore_collectivity_school_menu_guide_markers_instruction'] = '<b>Marqueur local</b>';
check( false !== strpos( read_guide( 'draft' ), '&lt;b&gt;Marqueur local&lt;/b&gt;' ), 'Site instructions must remain escaped text.' );
$filters['thecore_collectivity_school_menu_guide_markers_instruction'] = array();
check( false !== strpos( read_guide( 'draft' ), 'plusieurs repères' ), 'Fall back to the common procedure for an invalid custom instruction.' );
echo "School menu workspace guide tests passed.\n";
