<?php
require __DIR__ . '/school-menu-data-test.php';

define( 'THECORE_COLLECTIVITY_MANAGEMENT_URL', 'https://municipality.test/plugin/' );
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_attr( $value ); }
function esc_html__( $value, $domain = '' ) { return esc_html( $value ); }
function esc_attr__( $value, $domain = '' ) { return esc_attr( $value ); }
function wp_timezone_string() { return 'Europe/Paris'; }
function wp_date( $format, $timestamp, $timezone ) { return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone )->format( $format ); }
function wp_unique_id( $prefix ) { static $counter = 0; return $prefix . ++$counter; }
function wp_enqueue_style( $handle ) {}
function wp_enqueue_script( $handle ) {}
function admin_url( $path ) { return home_url( '/wp-admin/' . $path ); }
function add_query_arg( $arguments, $url ) { return $url . '?' . http_build_query( $arguments ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_get_inline_script_tag( $value ) { return '<script>' . $value . '</script>'; }

class WP_Query {
	public $posts;
	public function __construct( $arguments ) {
		$GLOBALS['school_display_query'] = $arguments;
		$this->posts = array_values( array_filter( $GLOBALS['school_test_posts'], static function ( $post ) use ( $arguments ) {
			return $post->post_type === $arguments['post_type'] && $post->post_status === $arguments['post_status'] && '' === $post->post_password && ( ! isset( $arguments['p'] ) || $post->ID === $arguments['p'] );
		} ) );
		usort( $this->posts, static function ( $first, $second ) { return strcmp( get_post_meta( $second->ID, '_tccm_school_menu_start', true ), get_post_meta( $first->ID, '_tccm_school_menu_start', true ) ); } );
		$this->posts = array_slice( $this->posts, 0, $arguments['posts_per_page'] );
	}
}

require ABSPATH . 'includes/features/school-menus/class-thecore-collectivity-school-menus-renderer.php';

function school_display_week( $post_id, $start, $status = 'publish', $password = '', $post_type = 'tccm_school_menu' ) {
	$GLOBALS['school_test_posts'][ $post_id ] = (object) array( 'ID' => $post_id, 'post_type' => $post_type, 'post_status' => $status, 'post_password' => $password, 'post_title' => $start );
	$week = TheCore_Collectivity_School_Menus_Data::empty_week( $start );
	$GLOBALS['school_test_meta'][ $post_id ] = array( '_tccm_school_menu' => $week, '_tccm_school_menu_start' => $start );
}
function school_display_xpath( $html ) {
	$document = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$document->loadHTML( '<?xml encoding="UTF-8">' . $html );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	return new DOMXPath( $document );
}

$GLOBALS['school_test_posts'] = array();
$GLOBALS['school_test_meta'] = array();
school_display_week( 20, '2026-11-09' );
school_display_week( 21, '2026-11-16' );
school_display_week( 22, '2026-11-23' );
school_display_week( 23, '2026-11-30', 'draft' );
school_display_week( 24, '2026-12-07', 'pending' );
school_display_week( 25, '2026-12-14', 'private' );
school_display_week( 26, '2026-12-21', 'future' );
school_display_week( 27, '2026-12-28', 'publish', 'secret' );
school_display_week( 28, '2027-01-04', 'publish', '', 'page' );
$before = serialize( array( $GLOBALS['school_test_posts'], $GLOBALS['school_test_meta'] ) );
$html = TheCore_Collectivity_School_Menus_Renderer::render( 0, 52, true );
$xpath = school_display_xpath( $html );
school_check( 2 === $xpath->query( '//*[@data-menu-group="current"]//*[@data-menu-week]' )->length, 'Current and upcoming weeks appear in the main group.' );
school_check( 1 === $xpath->query( '//*[@data-menu-group="archive"]//*[@data-menu-week]' )->length, 'Past weeks appear only in the previous-weeks group.' );
school_check( 1 === $xpath->query( '//details[@data-menu-archives and not(@open)]' )->length, 'Previous weeks use a closed native disclosure.' );
school_check( '2026-11-16' === $xpath->query( '//*[@data-menu-group="current"]//*[@data-menu-week][1]' )->item( 0 )->getAttribute( 'data-week-start' ), 'The first main week is the current one.' );
school_check( 3 === $xpath->query( '//*[@data-illustrated-url]' )->length, 'Illustrated views are offered only for public menus.' );
school_check( 3 === $xpath->query( '//*[@data-illustrated-url]/*[contains(@class,"tccms-illustrated-stage")]/*[contains(@class,"tccms-illustrated-fallback")]' )->length, 'Illustrated loading shells and readable fallbacks are rendered on the server.' );
school_check( 3 === $xpath->query( '//*[@data-illustrated-url]/*[contains(@class,"tccms-week-footer")]' )->length, 'Downloads stay outside the illustrated loading shell.' );
school_check( 3 === $xpath->query( '//*[@data-illustrated-url]/*[contains(@class,"tccms-illustrated-stage")]/*[@role="status"]' )->length, 'Each illustrated view has an accessible loading state.' );
school_check( 'Europe/Paris' === $xpath->query( '//*[@data-menu-calendar]' )->item( 0 )->getAttribute( 'data-timezone' ), 'The browser receives the WordPress timezone.' );
school_check( 'publish' === $GLOBALS['school_display_query']['post_status'] && false === $GLOBALS['school_display_query']['has_password'], 'The query excludes unpublished and password-protected posts.' );
$GLOBALS['school_test_today'] = '2026-11-22';
$xpath = school_display_xpath( TheCore_Collectivity_School_Menus_Renderer::render( 0, 52 ) );
school_check( 2 === $xpath->query( '//*[@data-menu-group="current"]//*[@data-menu-week]' )->length, 'Sunday retains the current main week.' );
$GLOBALS['school_test_today'] = '2026-11-23';
$xpath = school_display_xpath( TheCore_Collectivity_School_Menus_Renderer::render( 0, 52 ) );
school_check( 1 === $xpath->query( '//*[@data-menu-group="current"]//*[@data-menu-week]' )->length, 'Monday moves the finished week into previous weeks.' );
school_check( '2026-11-16' === $xpath->query( '//*[@data-menu-group="archive"]//*[@data-menu-week][1]' )->item( 0 )->getAttribute( 'data-week-start' ), 'Previous weeks are newest first.' );
$GLOBALS['school_test_today'] = '2026-12-07';
$html = TheCore_Collectivity_School_Menus_Renderer::render( 0, 52 );
$xpath = school_display_xpath( $html );
school_check( 0 === $xpath->query( '//*[@data-menu-group="current"]//*[@data-menu-week]' )->length && 1 === $xpath->query( '//*[@data-menu-group="current"]//*[@data-menu-empty and not(@hidden)]' )->length, 'No upcoming menu gives an honest empty state without reviving an expired week.' );
$xpath = school_display_xpath( TheCore_Collectivity_School_Menus_Renderer::render( 20, 1 ) );
school_check( 1 === $xpath->query( '//*[@data-menu-week]' )->length && 0 === $xpath->query( '//*[@data-menu-calendar]' )->length, 'An explicit archived-week ID remains accessible to exports.' );
school_check( ! str_contains( TheCore_Collectivity_School_Menus_Renderer::render( 21, 1 ), 'data-illustrated-url' ), 'Illustrated display remains opt-in.' );
$_GET['layout'] = 'mobile';
$document = TheCore_Collectivity_School_Menus_Downloads::view_document( 20 );
$xpath = school_display_xpath( $document );
$context = json_decode( $xpath->query( '//script[@id="svj-school-menu-document"]' )->item( 0 )->textContent, true );
school_check( true === $context['screen'] && true === $context['mobile'] && false === $context['autoPrint'], 'Mobile screen views never trigger printing.' );
school_check( 1 === $xpath->query( '//*[@id="menu-root"]//*[@data-menu-week]' )->length, 'Screen views include readable server-rendered HTML before JavaScript runs.' );
foreach ( array( 0, 'invalid', 23, 24, 25, 26, 27, 28, 999 ) as $unavailable ) {
	school_check( is_wp_error( TheCore_Collectivity_School_Menus_Downloads::view_document( $unavailable ) ), 'Unavailable menus cannot be opened through the screen endpoint: ' . $unavailable );
}
school_check( $before === serialize( array( $GLOBALS['school_test_posts'], $GLOBALS['school_test_meta'] ) ), 'Classification and public rendering never mutate stored menus or publication statuses.' );
echo "School menu display checks passed. Total assertions: " . $checks . ".\n";
