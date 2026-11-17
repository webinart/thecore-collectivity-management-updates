<?php
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'THECORE_COLLECTIVITY_MANAGEMENT_DIR', ABSPATH );
$GLOBALS['school_test_meta'] = array();
$GLOBALS['school_test_allowed'] = true;
$GLOBALS['school_test_conflict'] = 0;
$GLOBALS['school_test_actions'] = array();
$GLOBALS['school_test_notices'] = array();
$GLOBALS['school_test_posts'] = array( 17 => (object) array( 'ID' => 17, 'post_type' => 'tccm_school_menu', 'post_status' => 'publish', 'post_password' => '' ) );

class WP_Error {
	public function __construct( public $code, public $message, public $data = null ) {}
}
class School_Menu_Test_DB {
	public $posts = 'posts';
	public $postmeta = 'postmeta';
	public function prepare( $query, ...$arguments ) { return $query; }
	public function get_var( $query ) { return $GLOBALS['school_test_conflict']; }
}
class TheCore_Collectivity_School_Menus_Admin {
	public static function save_error( ...$arguments ) { $GLOBALS['school_test_notices'][] = $arguments; }
}
$GLOBALS['wpdb'] = new School_Menu_Test_DB();
function __( $value, $domain = '' ) { return $value; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_timezone() { return new DateTimeZone( 'Europe/Paris' ); }
function wp_parse_url( $value, $component = -1 ) { return parse_url( $value, $component ); }
function sanitize_text_field( $value ) { return trim( strip_tags( preg_replace( '/\s+/u', ' ', $value ) ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( $value ) ); }
function current_user_can( ...$arguments ) { return $GLOBALS['school_test_allowed']; }
function get_term( $marker_id, $taxonomy ) { return in_array( $marker_id, array( 1, 2 ), true ) ? (object) array( 'term_id' => $marker_id, 'name' => 1 === $marker_id ? 'Bio' : 'Ferme locale' ) : null; }
function get_term_meta( $marker_id, $key, $single ) { return '_tccm_marker_family' === $key ? ( 2 === $marker_id ? 'origin' : 'preparation' ) : ''; }
function get_post( $post_id ) { return $GLOBALS['school_test_posts'][ $post_id ] ?? null; }
function get_post_type( $post_id ) { return get_post( $post_id )->post_type ?? null; }
function get_post_status( $post_id ) { return get_post( $post_id )->post_status ?? null; }
function get_post_meta( $post_id, $key, $single ) { return $GLOBALS['school_test_meta'][ $post_id ][ $key ] ?? ''; }
function school_test_slashes( $value, $add ) {
	if ( is_array( $value ) ) return array_map( static function ( $item ) use ( $add ) { return school_test_slashes( $item, $add ); }, $value );
	return is_string( $value ) ? ( $add ? addslashes( $value ) : stripslashes( $value ) ) : $value;
}
function wp_slash( $value ) { return school_test_slashes( $value, true ); }
function wp_unslash( $value ) { return school_test_slashes( $value, false ); }
function update_post_meta( $post_id, $key, $value ) { $GLOBALS['school_test_meta'][ $post_id ][ $key ] = wp_unslash( $value ); }
function do_action( ...$arguments ) { $GLOBALS['school_test_actions'][] = $arguments; }
function absint( $value ) { return abs( (int) $value ); }
function wp_verify_nonce( ...$arguments ) { return false; }
function home_url( $path = '' ) { return 'https://municipality.test' . $path; }
function get_post_modified_time( ...$arguments ) { return '20261117T090000Z'; }

require ABSPATH . 'includes/features/school-menus/class-thecore-collectivity-school-menus-data.php';
require ABSPATH . 'includes/features/school-menus/class-thecore-collectivity-school-menus-downloads.php';

$checks = 0;
function school_check( $condition, $message ) {
	$GLOBALS['checks']++;
	if ( ! $condition ) throw new RuntimeException( $message );
}
function school_reject( $change, $message ) {
	$week = $GLOBALS['school_test_week'];
	$change( $week );
	school_check( is_wp_error( TheCore_Collectivity_School_Menus_Data::normalize( $week ) ), $message );
}

$definitions = require ABSPATH . 'includes/module-definitions.php';
school_check( false === $definitions['school-menus']['default_active'], 'School menus remain opt-in.' );
school_check( ! class_exists( 'TheCore_Collectivity_School_Menus_Module', false ), 'Reading definitions must not load an inactive module.' );
school_check( false === TheCore_Collectivity_School_Menus_Data::date( '2026-02-30' ), 'Impossible dates are rejected.' );
school_check( false === TheCore_Collectivity_School_Menus_Data::date( '2026-11-16 10:00' ), 'Dates cannot contain a time.' );
$week = TheCore_Collectivity_School_Menus_Data::empty_week( '2026-11-16' );
school_check( '2026-11-20' === $week['days'][4]['date'], 'One week contains Monday through Friday.' );
school_check( $week === TheCore_Collectivity_School_Menus_Data::normalize( $week ), 'A valid unspecified week round-trips.' );
$week['days'][0]['status'] = 'menu';
$week['days'][0]['courses']['main'] = array( array( 'parts' => array( array( 'text' => "Œuf à la sauce de l'école", 'markers' => array( 1 ) ), array( 'text' => 'Épinards', 'markers' => array( 2 ) ) ) ) );
$week['source_markers'] = array( 2 );
$week['notice'] = 'Sous réserve de livraison.';
$GLOBALS['school_test_week'] = $week;
school_check( $week === TheCore_Collectivity_School_Menus_Data::normalize( $week ), 'Markers remain attached to their ingredient fragments.' );
school_reject( static function ( &$value ) { $value['version'] = 2; }, 'Content and theme contract versions are distinct.' );
school_reject( static function ( &$value ) { $value['start'] = '2026-11-17'; }, 'A week must start on Monday.' );
school_reject( static function ( &$value ) { array_pop( $value['days'] ); }, 'Five days are required.' );
school_reject( static function ( &$value ) { $value['days'][1]['date'] = '2026-11-19'; }, 'Day dates must follow the week start.' );
school_reject( static function ( &$value ) { $value['days'][0]['vegetarian'] = 'true'; }, 'Vegetarian flags are boolean.' );
school_reject( static function ( &$value ) { $value['days'][0]['courses']['main'] = array(); }, 'A serving day needs food.' );
school_reject( static function ( &$value ) { $value['days'][0]['status'] = 'closed'; }, 'Closed days cannot hide food.' );
school_reject( static function ( &$value ) { $value['days'][1]['vegetarian'] = true; }, 'Unspecified days cannot be vegetarian menus.' );
school_reject( static function ( &$value ) { $value['source_url'] = 'javascript:alert(1)'; }, 'Source links reject non-HTTP URLs.' );
school_reject( static function ( &$value ) { $value['source_markers'] = array( 1 ); }, 'Poster origins cannot contain preparation markers.' );
school_reject( static function ( &$value ) { $value['days'][0]['courses']['main'][0]['parts'][0]['markers'] = array( 99 ); }, 'Unknown markers are rejected on save.' );
school_reject( static function ( &$value ) { $value['days'][0]['courses']['main'][0]['parts'][0]['text'] = str_repeat( 'a', 501 ); }, 'Ingredient text is bounded.' );
school_reject( static function ( &$value ) { $value['notice'] = str_repeat( 'a', 3001 ); }, 'Additional information is bounded.' );
$duplicates = $week;
$duplicates['days'][0]['courses']['main'][0]['parts'][0]['markers'] = array( 1, 1 );
school_check( $week === TheCore_Collectivity_School_Menus_Data::normalize( $duplicates ), 'Duplicate ingredient markers are deduplicated.' );
$GLOBALS['school_test_allowed'] = false;
school_check( is_wp_error( TheCore_Collectivity_School_Menus_Data::update( 17, $week ) ), 'Saving requires edit permission.' );
school_check( array() === $GLOBALS['school_test_meta'], 'Refused saves write no metadata.' );
$GLOBALS['school_test_allowed'] = true;
school_check( $week === TheCore_Collectivity_School_Menus_Data::update( 17, $week ), 'Authorized valid saves persist the week.' );
school_check( $week === TheCore_Collectivity_School_Menus_Data::get( 17 ), 'Apostrophes survive WordPress slash handling.' );
school_check( '2026-11-16' === get_post_meta( 17, '_tccm_school_menu_start', true ) && '2026-11-20' === get_post_meta( 17, '_tccm_school_menu_end', true ), 'Indexed dates match the saved week.' );
school_check( 'thecore_collectivity/school_menus/updated' === $GLOBALS['school_test_actions'][0][0], 'Saving signals PDF preparation and integrations.' );
school_check( is_wp_error( TheCore_Collectivity_School_Menus_Data::update( 17, array() ) ), 'Invalid updates are rejected.' );
school_check( $week === TheCore_Collectivity_School_Menus_Data::get( 17 ), 'Invalid updates preserve previous data.' );
$GLOBALS['school_test_conflict'] = 18;
school_check( is_wp_error( TheCore_Collectivity_School_Menus_Data::update( 17, $week ) ), 'Duplicate published weeks are refused.' );
$GLOBALS['school_test_conflict'] = 0;
$GLOBALS['school_test_posts'][18] = (object) array( 'ID' => 18, 'post_type' => 'tccm_school_menu', 'post_status' => 'draft', 'post_password' => '' );
$publication = TheCore_Collectivity_School_Menus_Data::guard_publication( array( 'post_type' => 'tccm_school_menu', 'post_status' => 'publish' ), array( 'ID' => 18 ) );
school_check( 'draft' === $publication['post_status'], 'Publishing a menu without valid data leaves a draft.' );
$calendar = TheCore_Collectivity_School_Menus_Downloads::file( 17 );
school_check( 'menu-scolaire-2026-11-16.ics' === $calendar['filename'], 'Calendar filenames identify the week.' );
school_check( 1 === substr_count( $calendar['content'], 'BEGIN:VEVENT' ), 'Calendar exports omit closed and unspecified days.' );
school_check( str_contains( $calendar['content'], 'DTSTART;VALUE=DATE:20261116' ) && str_contains( $calendar['content'], 'DTEND;VALUE=DATE:20261117' ), 'Calendar entries are all-day with exclusive end dates.' );
school_check( str_contains( $calendar['content'], 'TRANSP:TRANSPARENT' ), 'School meals do not block calendar availability.' );
$unfolded = str_replace( "\r\n ", '', $calendar['content'] );
school_check( str_contains( $unfolded, 'Épinards (Ferme locale)' ) && str_contains( $unfolded, '(Bio)' ), 'Calendar descriptions include ingredient-level origins and quality.' );
foreach ( explode( "\r\n", $calendar['content'] ) as $line ) {
	if ( strlen( $line ) > 75 || ! mb_check_encoding( $line, 'UTF-8' ) ) throw new RuntimeException( 'ICS folding must preserve UTF-8 and the 75-octet limit.' );
}
school_check( is_wp_error( TheCore_Collectivity_School_Menus_Downloads::file( 18 ) ), 'Draft menu exports are refused.' );
$GLOBALS['school_test_posts'][17]->post_password = 'protected';
school_check( is_wp_error( TheCore_Collectivity_School_Menus_Downloads::file( 17 ) ), 'Password-protected exports are refused.' );
echo $checks . " school menu data/calendar checks passed.\n";
