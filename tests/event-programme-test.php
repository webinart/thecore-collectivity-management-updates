<?php
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'THECORE_COLLECTIVITY_MANAGEMENT_URL', 'https://example.test/plugin/' );
$GLOBALS['programme_meta'] = array();
$GLOBALS['programme_can_edit'] = true;
$GLOBALS['programme_posts'] = array( 10 => (object) array( 'post_type' => 'post', 'post_status' => 'publish', 'post_password' => '' ) );
$GLOBALS['programme_actions'] = array();

class WP_Error {
	public function __construct( public $code, public $message ) {}
}
class TheCore_Collectivity_Elementor {
	public static function get_asset_version( $path ) { return 'test'; }
}
function __( $text, $domain = '' ) { return $text; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_timezone() { return new DateTimeZone( 'Europe/Paris' ); }
function wp_date( $format, $timestamp ) { return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( wp_timezone() )->format( $format ); }
function sanitize_text_field( $text ) { return trim( strip_tags( $text ) ); }
function sanitize_textarea_field( $text ) { return trim( strip_tags( $text ) ); }
function wp_kses_post( $text ) { return $text; }
function wpautop( $text ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_html__( $text, $domain ) { return esc_html( $text ); }
function sanitize_html_class( $text ) { return preg_replace( '/[^a-zA-Z0-9_-]/', '', $text ); }
function get_post( $post_id ) { return $GLOBALS['programme_posts'][ $post_id ] ?? null; }
function get_post_type( $post_id ) { return get_post( $post_id )->post_type ?? ''; }
function post_password_required( $post ) { return '' !== $post->post_password; }
function current_user_can( $capability, $post_id ) { return $GLOBALS['programme_can_edit']; }
function get_post_meta( $post_id, $key, $single ) { return $GLOBALS['programme_meta'][ $post_id ][ $key ] ?? ''; }
function metadata_exists( $type, $post_id, $key ) { return isset( $GLOBALS['programme_meta'][ $post_id ][ $key ] ); }
function wp_slash( $value ) { return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value ); }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value ); }
function update_post_meta( $post_id, $key, $value ) { $GLOBALS['programme_meta'][ $post_id ][ $key ] = wp_unslash( $value ); }
function delete_post_meta( $post_id, $key ) { unset( $GLOBALS['programme_meta'][ $post_id ][ $key ] ); }
function do_action( $hook, ...$arguments ) { $GLOBALS['programme_actions'][] = array( $hook, $arguments ); }
function wp_style_is( $handle, $state ) { return false; }
function wp_register_style( ...$arguments ) {}
function wp_enqueue_style( $handle ) {}
function wp_is_post_revision( $post_id ) { return 998 === $post_id; }
function wp_is_post_autosave( $post_id ) { return 999 === $post_id; }
function wp_verify_nonce( $nonce, $action ) { return 'valid' === $nonce; }
function set_transient( ...$arguments ) {}
function get_current_user_id() { return 1; }

require ABSPATH . 'includes/features/event-agenda/programme/class-thecore-collectivity-event-programme.php';
$checks = 0;
function programme_check( $passed, $message ) {
	$GLOBALS['checks']++;
	if ( ! $passed ) throw new RuntimeException( $message );
}
$item = array( 'title' => 'Parade', 'start' => '17:00', 'end' => '17:30', 'duration' => 0, 'description' => '', 'location' => '' );
$valid = array( 'version' => 1, 'days' => array( array( 'date' => '2026-11-27', 'items' => array( $item ) ) ), 'information' => '<h3>Restauration</h3>' );
programme_check( TheCore_Collectivity_Event_Programme_Schema::normalize( $valid ) == $valid, 'Valid programme is canonical.' );
$valid = TheCore_Collectivity_Event_Programme_Schema::normalize( $valid );
programme_check( ! is_wp_error( TheCore_Collectivity_Event_Programme::update( 10, $valid ) ), 'Authorized save succeeds.' );
programme_check( TheCore_Collectivity_Event_Programme::get( 10 ) === $valid, 'Stored programme roundtrips.' );
foreach ( array( null, 'json', array(), array( 'version' => 2, 'days' => array() ), array( 'days' => 'invalid' ) ) as $invalid ) {
	programme_check( is_wp_error( TheCore_Collectivity_Event_Programme_Schema::normalize( $invalid ) ), 'Reject malformed or unknown versions.' );
}
foreach ( array( '2026-02-30', '2026-13-01', '2026-11-27extra', '' ) as $date ) {
	$invalid = $valid;
	$invalid['days'][0]['date'] = $date;
	programme_check( is_wp_error( TheCore_Collectivity_Event_Programme_Schema::normalize( $invalid ) ), 'Reject invalid date.' );
}
$invalid = $valid;
$invalid['days'][] = $valid['days'][0];
programme_check( is_wp_error( TheCore_Collectivity_Event_Programme_Schema::normalize( $invalid ) ), 'Reject duplicate days.' );
foreach ( array( '25:00', '17:60', '17h', 17, array() ) as $time ) {
	$invalid = $valid;
	$invalid['days'][0]['items'][0]['start'] = $time;
	programme_check( is_wp_error( TheCore_Collectivity_Event_Programme_Schema::normalize( $invalid ) ), 'Reject invalid times.' );
}
foreach ( array( '', str_repeat( 'a', 201 ), array() ) as $title ) {
	$invalid = $valid;
	$invalid['days'][0]['items'][0]['title'] = $title;
	programme_check( is_wp_error( TheCore_Collectivity_Event_Programme::update( 10, $invalid ) ), 'Reject invalid title.' );
	programme_check( TheCore_Collectivity_Event_Programme::get( 10 ) === $valid, 'Invalid save preserves old data.' );
}
foreach ( array( -1, 1441, '30', 30.5 ) as $duration ) {
	$invalid = $valid;
	$invalid['days'][0]['items'][0]['duration'] = $duration;
	programme_check( is_wp_error( TheCore_Collectivity_Event_Programme_Schema::normalize( $invalid ) ), 'Reject invalid duration or duration plus final time.' );
}
$unsorted = $valid;
$unsorted['days'][0]['items'] = array( array_merge( $item, array( 'start' => '19:00', 'end' => '', 'title' => 'Soir' ) ), $item,
	array_merge( $item, array( 'start' => '14:00', 'end' => '18:00', 'title' => 'Balades' ) ), array_merge( $item, array( 'title' => 'Autre parade' ) ) );
$sorted = TheCore_Collectivity_Event_Programme_Schema::normalize( $unsorted );
programme_check( array_column( $sorted['days'][0]['items'], 'title' ) === array( 'Balades', 'Parade', 'Autre parade', 'Soir' ), 'Chronological and stable ordering allows overlaps.' );
$overnight = $valid;
$overnight['days'][0]['items'][0] = array_merge( $item, array( 'start' => '23:00', 'end' => '01:00' ) );
TheCore_Collectivity_Event_Programme::update( 10, $overnight );
$html = TheCore_Collectivity_Event_Programme_Renderer::render( 10 );
programme_check( str_contains( $html, '2026-11-28T01:00' ) && str_contains( $html, 'le lendemain' ), 'Overnight activities carry the correct final date.' );
$escaped = $valid;
$escaped['days'][0]['items'][0]['title'] = '<b>Parade</b>';
$escaped['days'][0]['items'][0]['description'] = 'Texte <img src=x onerror=alert(1)>';
$escaped['days'][0]['items'][0]['location'] = 'L\'atelier';
TheCore_Collectivity_Event_Programme::update( 10, $escaped );
$html = TheCore_Collectivity_Event_Programme_Renderer::render( 10, '<script>titre</script>' );
programme_check( ! str_contains( $html, '<script>' ) && ! str_contains( $html, '<img' ) && str_contains( $html, '&lt;script&gt;' ), 'Labels are escaped and activity markup is removed.' );
programme_check( TheCore_Collectivity_Event_Programme::get( 10 )['days'][0]['items'][0]['location'] === 'L\'atelier', 'Apostrophes survive native slash handling.' );
$GLOBALS['programme_can_edit'] = false;
programme_check( is_wp_error( TheCore_Collectivity_Event_Programme::update( 10, $valid ) ), 'Unauthorized writes are rejected.' );
$GLOBALS['programme_posts'][10]->post_status = 'private';
programme_check( '' === TheCore_Collectivity_Event_Programme_Renderer::render( 10 ), 'Public widget cannot expose private data.' );
$GLOBALS['programme_can_edit'] = true;
programme_check( '' !== TheCore_Collectivity_Event_Programme_Renderer::render( 10 ), 'Authorized editor can preview a private programme.' );
$GLOBALS['programme_posts'][10]->post_password = 'protected';
programme_check( '' === TheCore_Collectivity_Event_Programme_Renderer::render( 10 ), 'Password protection is respected.' );
$GLOBALS['programme_posts'][10]->post_password = '';
$GLOBALS['programme_posts'][10]->post_status = 'publish';
TheCore_Collectivity_Event_Programme::update( 10, $valid );
foreach ( array( array(), array( 'tccm_event_programme_nonce' => 'invalid' ), array( 'tccm_event_programme_nonce' => array() ) ) as $input ) {
	$_POST = $input;
	TheCore_Collectivity_Event_Programme_Admin::save( 10, $GLOBALS['programme_posts'][10] );
	programme_check( TheCore_Collectivity_Event_Programme::get( 10 ) === $valid, 'Missing and malformed nonce cannot clear programme data.' );
}
$_POST = array( 'tccm_event_programme_nonce' => 'valid', 'tccm_event_programme_payload' => 'not json', 'tccm_event_programme_information' => '' );
TheCore_Collectivity_Event_Programme_Admin::save( 10, $GLOBALS['programme_posts'][10] );
programme_check( TheCore_Collectivity_Event_Programme::get( 10 ) === $valid, 'Malformed form JSON preserves previous data.' );
$_POST = array();
$empty = TheCore_Collectivity_Event_Programme_Schema::empty_programme();
TheCore_Collectivity_Event_Programme::update( 10, $empty );
programme_check( ! metadata_exists( 'post', 10, TheCore_Collectivity_Event_Programme::META_KEY ), 'Explicitly empty programme removes only its meta.' );
programme_check( '' === TheCore_Collectivity_Event_Programme_Renderer::render( 10 ), 'Empty programme emits no heading or wrapper.' );
$untimed = $valid;
$untimed['days'][0]['items'][0] = array_merge( $item, array( 'start' => '', 'end' => '' ) );
TheCore_Collectivity_Event_Programme::update( 10, $untimed );
programme_check( str_contains( TheCore_Collectivity_Event_Programme_Renderer::render( 10 ), 'tccm-event-programme__item--untimed' ), 'Untimed activities do not reserve an empty time column.' );
fwrite( STDOUT, 'Event programme tests passed: ' . $checks . " checks.\n" );
