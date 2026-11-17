<?php
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
define( 'THECORE_COLLECTIVITY_MANAGEMENT_URL', 'https://municipality.test/plugin/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
$temporary = sys_get_temp_dir() . '/tccm-pdf-test-' . bin2hex( random_bytes( 8 ) );
mkdir( $temporary, 0700 );
mkdir( $temporary . '/storage', 0700 );
define( 'THECORE_COLLECTIVITY_PDF_CONFIG', $temporary . '/client.json' );
file_put_contents( $temporary . '/certificate.pem', 'test certificate' );
$GLOBALS['pdf_calls'] = array();
$GLOBALS['pdf_responses'] = array();
$GLOBALS['pdf_transients'] = array();
$GLOBALS['pdf_actions'] = array();
$GLOBALS['pdf_events'] = array();
$GLOBALS['pdf_markers'] = array();
$GLOBALS['pdf_terms_error'] = false;
$GLOBALS['pdf_posts'] = array( 10 => (object) array( 'ID' => 10, 'post_type' => 'tccm_school_menu', 'post_status' => 'publish', 'post_password' => '', 'post_title' => 'Semaine du 16 au 20 novembre 2026' ) );
$GLOBALS['pdf_week'] = array( 'version' => 1, 'start' => '2026-11-16', 'notice' => '', 'days' => array() );

class WP_Error {
	public function __construct( public $code, public $message, public $data = null ) {}
	public function get_error_data() { return $this->data; }
	public function get_error_message() { return $this->message; }
}
class TheCore_Collectivity_School_Menus_Data {
	const POST_TYPE = 'tccm_school_menu';
	const TAXONOMY = 'tccm_food_marker';
	public static function get( $post_id ) { return $GLOBALS['pdf_week']; }
	public static function marker( $term_id ) { return $GLOBALS['pdf_markers'][ $term_id ]; }
}
class TheCore_Collectivity_School_Menus_Renderer {
	public static function render( $post_id, $limit ) { return '<div class="tccm-school-menus">' . htmlspecialchars( $GLOBALS['pdf_week']['notice'], ENT_QUOTES, 'UTF-8' ) . '</div>'; }
}
function __( $text, $domain = '' ) { return $text; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function home_url( $path = '' ) { return 'https://municipality.test' . $path; }
function admin_url( $path ) { return home_url( '/wp-admin/' . $path ); }
function wp_parse_url( $value ) { return parse_url( $value ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function esc_url( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body'] ?? ''; }
function wp_remote_retrieve_header( $response, $header ) { return $response['headers'][ $header ] ?? ''; }
function wp_remote_request( $url, $arguments ) {
	$GLOBALS['pdf_calls'][] = array( 'url' => $url, 'arguments' => $arguments );
	if ( ! $GLOBALS['pdf_responses'] ) throw new RuntimeException( 'Unexpected HTTP request.' );
	$response = array_shift( $GLOBALS['pdf_responses'] );
	if ( isset( $arguments['filename'] ) && isset( $response['body'] ) ) file_put_contents( $arguments['filename'], $response['body'] );
	return $response;
}
function add_query_arg( $key, $value, $url = null ) {
	if ( is_array( $key ) ) { $url = $value; $values = $key; } else { $values = array( $key => $value ); }
	return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . http_build_query( $values );
}
function get_post( $post_id ) { return $GLOBALS['pdf_posts'][ $post_id ] ?? null; }
function get_terms( $arguments ) { return $GLOBALS['pdf_terms_error'] ? new WP_Error( 'terms', 'Unavailable' ) : array_map( static function ( $term_id ) { return (object) array( 'term_id' => $term_id ); }, array_keys( $GLOBALS['pdf_markers'] ) ); }
function get_bloginfo( $name ) { return 'name' === $name ? 'Saint-Vincent-sur-Jard' : 'fr-FR'; }
function get_theme_mod( $name ) { return 1; }
function get_option( $name ) { return 2; }
function get_transient( $key ) { return $GLOBALS['pdf_transients'][ $key ] ?? false; }
function set_transient( $key, $value, $duration ) { $GLOBALS['pdf_transients'][ $key ] = $value; }
function delete_transient( $key ) { unset( $GLOBALS['pdf_transients'][ $key ] ); }
function add_action( ...$arguments ) { $GLOBALS['pdf_actions'][] = $arguments; }
function wp_next_scheduled( $hook, $arguments = array() ) { return false; }
function wp_schedule_single_event( ...$arguments ) { $GLOBALS['pdf_events'][] = $arguments; }
function wp_schedule_event( ...$arguments ) { $GLOBALS['pdf_events'][] = $arguments; }

require ABSPATH . 'includes/features/school-menus/class-thecore-collectivity-school-menus-downloads.php';
$checks = 0;
function pdf_check( $condition, $message ) {
	$GLOBALS['checks']++;
	if ( ! $condition ) throw new RuntimeException( $message );
}
function pdf_config( $changes = array() ) {
	$config = array_merge( array( 'service_url' => 'https://pdf.technical.test', 'api_key' => str_repeat( 'a', 48 ), 'certificate' => $GLOBALS['temporary'] . '/certificate.pem', 'storage' => $GLOBALS['temporary'] . '/storage' ), $changes );
	file_put_contents( THECORE_COLLECTIVITY_PDF_CONFIG, json_encode( $config ) );
	chmod( THECORE_COLLECTIVITY_PDF_CONFIG, 0600 );
	clearstatcache();
	$property = new ReflectionProperty( TheCore_Collectivity_PDF_Client::class, 'configuration' );
	$property->setValue( null, null );
}
function pdf_queue( $changes = array(), $download_changes = array() ) {
	$content = "%PDF-1.7\ntest\n%%EOF\n";
	$result = array_merge( array( 'status' => 'ready', 'id' => str_repeat( 'a', 48 ), 'sha256' => hash( 'sha256', $content ), 'bytes' => strlen( $content ), 'pdfUrl' => 'https://pdf.technical.test/v1/files/' . str_repeat( 'a', 48 ) . '.pdf' ), $changes );
	$GLOBALS['pdf_responses'] = array(
		array( 'response' => array( 'code' => 201 ), 'body' => json_encode( $result ) ),
		array_merge( array( 'response' => array( 'code' => 200 ), 'headers' => array( 'content-type' => 'application/pdf' ), 'body' => $content ), $download_changes ),
	);
}

try {
	pdf_check( ! TheCore_Collectivity_PDF_Client::enabled(), 'Missing configuration keeps browser printing available.' );
	pdf_config();
	pdf_check( TheCore_Collectivity_PDF_Client::enabled(), 'Private configuration enables the PDF client.' );
	chmod( THECORE_COLLECTIVITY_PDF_CONFIG, 0644 );
	clearstatcache();
	( new ReflectionProperty( TheCore_Collectivity_PDF_Client::class, 'configuration' ) )->setValue( null, null );
	pdf_check( ! TheCore_Collectivity_PDF_Client::enabled(), 'World-readable secrets are rejected.' );
	foreach ( array( 'http://pdf.technical.test', 'https://user:secret@pdf.technical.test', 'https://127.0.0.1', 'https://pdf.technical.test:444', 'https://pdf.technical.test/path', 'https://pdf.technical.test?query=1', 'https://pdf.technical.test#fragment' ) as $url ) {
		pdf_config( array( 'service_url' => $url ) );
		pdf_check( ! TheCore_Collectivity_PDF_Client::enabled(), 'Invalid service origin is rejected: ' . $url );
	}
	pdf_config( array( 'storage' => ABSPATH ) );
	pdf_check( ! TheCore_Collectivity_PDF_Client::enabled(), 'Web-accessible storage is rejected.' );
	pdf_config( array( 'certificate' => '/nonexistent' ) );
	pdf_check( ! TheCore_Collectivity_PDF_Client::enabled(), 'Missing trust anchor fails closed.' );
	pdf_config();
	$document = TheCore_Collectivity_School_Menus_Downloads::print_document( 10 );
	pdf_check( strpos( $document, 'id="svj-school-menu-document"' ) !== false && strpos( $document, 'assets/illustrated/proposition-menu-automne/' ) !== false, 'Printing uses the approved illustrated model.' );
	preg_match( '/<script type="application\/json" id="svj-school-menu-document">(.*?)<\/script>/s', $document, $matches );
	$context = json_decode( $matches[1], true );
	pdf_check( 'autumn-hedgehog-composition' === $context['theme']['id'] && 10 === $context['week']['id'], 'The current model and requested live menu are passed to the renderer.' );
	$GLOBALS['pdf_week']['notice'] = '</script><script>alert("test")</script>';
	$updated_document = TheCore_Collectivity_School_Menus_Downloads::print_document( 10 );
	pdf_check( $document !== $updated_document && strpos( $updated_document, '</script><script>alert' ) === false, 'Live edits are reflected without allowing a JSON script breakout.' );
	$GLOBALS['pdf_week']['notice'] = '';
	$_GET['pdf_revision'] = 'backend-generation';
	$backend_document = TheCore_Collectivity_School_Menus_Downloads::print_document( 10 );
	preg_match( '/<script type="application\/json" id="svj-school-menu-document">(.*?)<\/script>/s', $backend_document, $matches );
	pdf_check( false === json_decode( $matches[1], true )['autoPrint'], 'Backend source does not request a browser print dialog.' );
	unset( $_GET['pdf_revision'] );
	$manifest = json_decode( file_get_contents( ABSPATH . 'includes/features/school-menus/assets/illustrated/manifest.json' ), true );
	pdf_check( false === $manifest['includesMenuSnapshots'] && ! is_file( ABSPATH . 'includes/features/school-menus/assets/illustrated/proposition-menu-automne/menus.json' ), 'The deployed graphic package contains no frozen menu datasets.' );
	$path = $temporary . '/storage/received.pdf';
	pdf_queue();
	$result = TheCore_Collectivity_PDF_Client::render_to_file( 'https://municipality.test/print', 'revision1', $path, array( 'landscape' => true, 'headers' => array( 'forbidden' => 'value' ) ) );
	pdf_check( ! is_wp_error( $result ) && is_file( $path ), 'Verified PDF is copied locally.' );
	pdf_check( 0600 === ( fileperms( $path ) & 0777 ), 'Downloaded file remains private.' );
	foreach ( $GLOBALS['pdf_calls'] as $call ) {
		pdf_check( true === $call['arguments']['sslverify'] && 0 === $call['arguments']['redirection'] && true === $call['arguments']['reject_unsafe_urls'] && $temporary . '/certificate.pem' === $call['arguments']['sslcertificates'], 'Certificate, hostname and redirects stay protected.' );
	}
	$body = json_decode( $GLOBALS['pdf_calls'][0]['arguments']['body'], true );
	pdf_check( ! isset( $body['headers'] ) && true === $body['landscape'], 'Only supported PDF options are sent.' );
	pdf_check( ! isset( $GLOBALS['pdf_calls'][1]['arguments']['headers']['Authorization'] ), 'Download does not forward the API key.' );
	unlink( $path );
	foreach ( array( array( 'pdfUrl' => 'https://evil.test/file.pdf' ), array( 'pdfUrl' => 'https://pdf.technical.test/v1/files/../config.json' ), array( 'bytes' => 20971521 ), array( 'bytes' => '20' ), array( 'sha256' => str_repeat( '0', 64 ) ), array( 'status' => 'pending' ), array( 'id' => '../private' ) ) as $changes ) {
		pdf_queue( $changes );
		pdf_check( is_wp_error( TheCore_Collectivity_PDF_Client::render_to_file( 'https://municipality.test/print', 'revision', $path ) ) && ! is_file( $path ), 'Unsafe response or integrity mismatch is rejected.' );
	}
	foreach ( array( array( 'headers' => array( 'content-type' => 'text/html' ) ), array( 'body' => 'not a PDF' ), array( 'response' => array( 'code' => 302 ) ) ) as $changes ) {
		pdf_queue( array(), $changes );
		pdf_check( is_wp_error( TheCore_Collectivity_PDF_Client::render_to_file( 'https://municipality.test/print', 'revision', $path ) ), 'Invalid download is rejected.' );
	}
	pdf_check( ! glob( $temporary . '/storage/.pdf-*' ), 'Failed requests leave no temporary files.' );
	$GLOBALS['pdf_responses'] = array( array( 'response' => array( 'code' => 429 ), 'headers' => array( 'retry-after' => '120' ) ) );
	$result = TheCore_Collectivity_PDF_Client::render_to_file( 'https://municipality.test/print', 'revision', $path );
	pdf_check( is_wp_error( $result ) && 120 === $result->get_error_data()['retry_after'], 'Quota failure has bounded retry advice.' );
	$GLOBALS['pdf_responses'] = array( new WP_Error( 'tls', 'Untrusted certificate' ) );
	pdf_check( is_wp_error( TheCore_Collectivity_PDF_Client::render_to_file( 'https://municipality.test/print', 'revision', $path ) ), 'TLS errors fail closed.' );
	$before = count( $GLOBALS['pdf_calls'] );
	pdf_check( is_wp_error( TheCore_Collectivity_School_Menus_Downloads::pdf_file( 10, false ) ) && $before === count( $GLOBALS['pdf_calls'] ), 'Uncached HEAD never launches Chromium.' );
	foreach ( array( null, 0, -1, '10extra', '1e1', array( 10 ), 999 ) as $invalid ) {
		pdf_check( is_wp_error( TheCore_Collectivity_School_Menus_Downloads::pdf_file( $invalid ) ), 'Invalid menu IDs cannot trigger rendering.' );
	}
	foreach ( array( 'draft', 'private', 'trash' ) as $status ) {
		$GLOBALS['pdf_posts'][10]->post_status = $status;
		pdf_check( is_wp_error( TheCore_Collectivity_School_Menus_Downloads::pdf_file( 10 ) ), 'Unpublished menus remain inaccessible.' );
	}
	$GLOBALS['pdf_posts'][10]->post_status = 'publish';
	$GLOBALS['pdf_posts'][10]->post_password = 'password';
	pdf_check( is_wp_error( TheCore_Collectivity_School_Menus_Downloads::pdf_file( 10 ) ), 'Password-protected menus remain inaccessible.' );
	$GLOBALS['pdf_posts'][10]->post_password = '';
	$lock = fopen( $temporary . '/storage/render.lock', 'c' );
	flock( $lock, LOCK_EX );
	pdf_check( is_wp_error( TheCore_Collectivity_School_Menus_Downloads::pdf_file( 10 ) ), 'Concurrent rendering is bounded by a non-blocking lock.' );
	flock( $lock, LOCK_UN );
	fclose( $lock );
	pdf_queue();
	$first = TheCore_Collectivity_School_Menus_Downloads::pdf_file( 10 );
	pdf_check( ! is_wp_error( $first ) && 'menu-scolaire-2026-11-16.pdf' === $first['filename'], 'Published week generates its own named PDF.' );
	$before = count( $GLOBALS['pdf_calls'] );
	pdf_check( TheCore_Collectivity_School_Menus_Downloads::pdf_file( 10 ) === $first && count( $GLOBALS['pdf_calls'] ) === $before, 'Repeated downloads reuse the local PDF without service calls.' );
	$GLOBALS['pdf_week']['notice'] = 'Updated menu';
	pdf_queue();
	$second = TheCore_Collectivity_School_Menus_Downloads::pdf_file( 10 );
	pdf_check( $first['revision'] !== $second['revision'], 'Menu edits invalidate the local cache.' );
	$GLOBALS['pdf_markers'][5] = array( 'name' => 'New origin' );
	pdf_queue();
	$third = TheCore_Collectivity_School_Menus_Downloads::pdf_file( 10 );
	pdf_check( $second['revision'] !== $third['revision'], 'Marker edits invalidate the local cache.' );
	$GLOBALS['pdf_terms_error'] = true;
	pdf_check( is_wp_error( TheCore_Collectivity_School_Menus_Downloads::pdf_file( 10 ) ), 'Unavailable taxonomy returns a controlled error.' );
	$GLOBALS['pdf_terms_error'] = false;
	touch( $first['path'], time() - 91 * DAY_IN_SECONDS );
	TheCore_Collectivity_School_Menus_PDF::cleanup();
	pdf_check( ! is_file( $first['path'] ) && is_file( $third['path'] ), 'Cleanup removes expired files but preserves recent menus.' );
	TheCore_Collectivity_School_Menus_Downloads::register_hooks();
	pdf_check( in_array( 'admin_post_nopriv_tccm_school_menu_pdf', array_column( $GLOBALS['pdf_actions'], 0 ), true ), 'Public PDF route is registered.' );
	pdf_check( in_array( 'admin_post_nopriv_tccm_school_menu_print', array_column( $GLOBALS['pdf_actions'], 0 ), true ), 'Existing HTML printing remains available.' );
	pdf_check( in_array( 'admin_post_nopriv_tccm_school_menu_calendar', array_column( $GLOBALS['pdf_actions'], 0 ), true ), 'Existing ICS downloads remain available.' );
	TheCore_Collectivity_School_Menus_PDF::schedule( 10 );
	pdf_check( in_array( 'thecore_collectivity_school_menu_pdf_prepare', array_column( $GLOBALS['pdf_events'], 1 ), true ), 'Published menu edits schedule background generation.' );
	echo $checks . " PDF checks passed.\n";
} finally {
	foreach ( glob( $temporary . '/storage/*' ) as $file ) unlink( $file );
	foreach ( glob( $temporary . '/storage/.pdf-*' ) as $file ) unlink( $file );
	rmdir( $temporary . '/storage' );
	foreach ( glob( $temporary . '/*' ) as $file ) unlink( $file );
	rmdir( $temporary );
}
