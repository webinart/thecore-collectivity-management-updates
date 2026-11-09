<?php
/** Standalone regressions for typed document fields and guarded admin writes. */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
$GLOBALS['document_meta_test'] = array(
	'meta' => array(),
	'registered' => array(),
	'hooks' => array(),
	'can_edit' => true,
	'revision' => false,
	'autosave' => false,
	'types' => array( 17 => 'tccm_document', 18 => 'post', 40 => 'attachment', 41 => 'post', 42 => 'attachment' ),
	'urls' => array( 40 => 'https://example.org/uploads/guide.pdf', 42 => 'file:///private/guide.pdf' ),
	'orders' => array( 17 => 0, 18 => 4 ),
	'post_writes' => array(),
);

class WP_Post {
	public $ID;
	public $post_type;
	public $post_title = '';
	public $menu_order = 0;
	public function __construct( $id, $type ) {
		$this->ID = $id;
		$this->post_type = $type;
	}
}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['document_meta_test']['hooks'][ $hook ] = array( $callback, $priority, $accepted_args );
}
function register_post_meta( $type, $key, $definition ) {
	$GLOBALS['document_meta_test']['registered'][ $key ] = array( 'type' => $type, 'definition' => $definition );
}
function current_user_can( $capability, $id = 0 ) {
	return 'edit_post' === $capability && 17 === (int) $id && $GLOBALS['document_meta_test']['can_edit'];
}
function get_post_type( $id ) { return $GLOBALS['document_meta_test']['types'][ $id ] ?? false; }
function wp_get_attachment_url( $id ) { return $GLOBALS['document_meta_test']['urls'][ $id ] ?? false; }
function wp_is_post_revision( $id ) { return $GLOBALS['document_meta_test']['revision']; }
function wp_is_post_autosave( $id ) { return $GLOBALS['document_meta_test']['autosave']; }
function wp_verify_nonce( $value, $action ) { return 'verified' === $value && 'tccm_document_meta_action' === $action; }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value ); }
function sanitize_text_field( $value ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( $value ) ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( $value ) ); }
function wp_parse_url( $value ) { return parse_url( $value ); }
function esc_url_raw( $value, $protocols = null ) {
	$scheme = parse_url( $value, PHP_URL_SCHEME );
	return filter_var( $value, FILTER_VALIDATE_URL ) && in_array( strtolower( $scheme ?? '' ), $protocols ?? array( 'http', 'https' ), true ) ? $value : '';
}
function update_post_meta( $id, $key, $value ) {
	$exists = array_key_exists( $key, $GLOBALS['document_meta_test']['meta'][ $id ] ?? array() );
	if ( $exists && $GLOBALS['document_meta_test']['meta'][ $id ][ $key ] === $value ) return false;
	$GLOBALS['document_meta_test']['meta'][ $id ][ $key ] = $value;
	$hook = $exists ? 'updated_post_meta' : 'added_post_meta';
	if ( isset( $GLOBALS['document_meta_test']['hooks'][ $hook ] ) ) {
		call_user_func( $GLOBALS['document_meta_test']['hooks'][ $hook ][0], 1, $id, $key, $value );
	}
	return true;
}
function delete_post_meta( $id, $key ) { unset( $GLOBALS['document_meta_test']['meta'][ $id ][ $key ] ); }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['document_meta_test']['meta'][ $id ][ $key ] ?? ''; }
function wp_enqueue_media() {}
function wp_nonce_field( $action, $name ) { echo '<input name="' . $name . '" value="verified" />'; }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_textarea( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html_e( $value, $domain = '' ) { echo esc_html( $value ); }
function checked( $expected, $actual, $echo = true ) {
	$result = $expected === $actual ? 'checked="checked"' : '';
	if ( $echo ) echo $result;
	return $result;
}
function selected( $expected, $actual, $echo = true ) { return checked( $expected, $actual, $echo ); }
function get_post( $id ) {
	$type = get_post_type( $id );
	if ( ! $type ) return null;
	$post = new WP_Post( $id, $type );
	$post->menu_order = $GLOBALS['document_meta_test']['orders'][ $id ] ?? 0;
	return $post;
}
function wp_update_post( $data ) {
	$GLOBALS['document_meta_test']['post_writes'][] = $data;
	$GLOBALS['document_meta_test']['orders'][ $data['ID'] ] = $data['menu_order'];
	if ( isset( $GLOBALS['document_meta_test']['during_post_update'] ) ) {
		call_user_func( $GLOBALS['document_meta_test']['during_post_update'], $data );
	}
	return $data['ID'];
}

require ABSPATH . 'includes/features/documents/class-thecore-collectivity-documents-post-type.php';
require ABSPATH . 'includes/features/documents/class-thecore-collectivity-documents-meta.php';

function document_meta_check( $condition, $message ) {
	if ( ! $condition ) throw new RuntimeException( $message );
}
function document_meta_equal( $expected, $actual, $message ) {
	document_meta_check( $expected === $actual, $message . ' Expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
}
function document_meta_request( $values = array() ) {
	$_POST = array_merge( array( TheCore_Collectivity_Documents_Meta::NONCE_NAME => 'verified' ), $values );
}

$fields = new TheCore_Collectivity_Documents_Meta();
$post = new WP_Post( 17, TheCore_Collectivity_Documents_Post_Type::POST_TYPE );
$fields->register_hooks();
document_meta_equal( 20, $GLOBALS['document_meta_test']['hooks']['init'][1], 'Register typed fields after the post type.' );
document_meta_equal( 4, $GLOBALS['document_meta_test']['hooks']['added_post_meta'][2], 'Observe creation of the global sort metadata.' );
document_meta_equal( 4, $GLOBALS['document_meta_test']['hooks']['updated_post_meta'][2], 'Observe REST and admin updates of global sort metadata.' );
$fields->register_meta();
$registered = $GLOBALS['document_meta_test']['registered'];
document_meta_equal( 13, count( $registered ), 'Only the explicit scalar field allowlist is registered.' );
document_meta_check( ! isset( $registered[ TheCore_Collectivity_Documents_Meta::META_RELATED_PROCEDURE_IDS ] ), 'Do not expose private relation lists in REST.' );
foreach ( $registered as $key => $record ) {
	document_meta_equal( 'tccm_document', $record['type'], 'Metadata is scoped to documents.' );
	document_meta_equal( true, $record['definition']['single'], 'Document scalar fields have one value.' );
	document_meta_equal( array( 'edit' ), $record['definition']['show_in_rest']['schema']['context'], 'Download metadata stays out of anonymous REST views, including password-protected posts.' );
	document_meta_check( is_callable( $record['definition']['sanitize_callback'] ), 'Every registered field has a sanitizer.' );
	document_meta_check( call_user_func( $record['definition']['auth_callback'], false, $key, 17 ), 'Document editors can update registered fields.' );
	document_meta_check( ! call_user_func( $record['definition']['auth_callback'], true, $key, 18 ), 'Other post types cannot use document field write permissions.' );
}
$GLOBALS['document_meta_test']['can_edit'] = false;
document_meta_check( ! $fields->authorize_meta_update( true, TheCore_Collectivity_Documents_Meta::META_SUMMARY, 17 ), 'Existing allowed flags do not bypass edit_post.' );
$GLOBALS['document_meta_test']['can_edit'] = true;

foreach ( array( '2026-01-31', '2028-02-29', ' 2026-11-09 ' ) as $valid ) {
	document_meta_equal( trim( $valid ), $fields->sanitize_date_input( $valid ), 'Accept real calendar dates.' );
}
foreach ( array( '', '2026-02-30', '2026-02-29', '2026-13-01', '0000-01-01', '2026-1-01', '2026-11-09 extra', array( '2026-11-09' ) ) as $invalid ) {
	document_meta_equal( '', $fields->sanitize_date_input( $invalid ), 'Reject impossible or malformed dates.' );
}
foreach ( array( 'https://example.org/guide.pdf', 'http://example.org/guide.pdf?download=1' ) as $url ) {
	document_meta_equal( $url, $fields->sanitize_url_input( $url ), 'Preserve absolute HTTP(S) download links.' );
}
foreach ( array( '', '/guide.pdf', '//example.org/guide.pdf', 'file:///private/guide.pdf', 'ftp://example.org/guide.pdf', 'javascript:alert(1)', 'https://user:password@example.org/guide.pdf', array( 'https://example.org' ) ) as $invalid ) {
	document_meta_equal( '', $fields->sanitize_url_input( $invalid ), 'Reject non-web, relative, credential-bearing or structured URLs.' );
}
document_meta_equal( 40, $fields->sanitize_attachment_input( '40' ), 'Accept an actual attachment with a web URL.' );
foreach ( array( 0, -40, 41, 42, 999, array( 40 ) ) as $invalid ) {
	document_meta_equal( 0, $fields->sanitize_attachment_input( $invalid ), 'Reject wrong object types, unknown ids and private-path attachment URLs.' );
}
document_meta_equal( 0, $fields->sanitize_order_input( -4 ), 'Display order cannot be negative.' );
document_meta_equal( 7, $fields->sanitize_order_input( '7' ), 'Keep numeric display order.' );
document_meta_equal( '1', $fields->sanitize_featured_input( '1' ), 'Preserve the existing featured string contract.' );
document_meta_equal( '0', $fields->sanitize_featured_input( '0' ), 'Explicitly clear the featured flag.' );

$fields->sync_native_sort_order( 1, 17, TheCore_Collectivity_Documents_Meta::META_SUMMARY, 'Ignored' );
$fields->sync_native_sort_order( 1, 18, TheCore_Collectivity_Documents_Meta::META_SORT_ORDER, 7 );
$fields->sync_native_sort_order( 1, 999, TheCore_Collectivity_Documents_Meta::META_SORT_ORDER, 7 );
document_meta_equal( array(), $GLOBALS['document_meta_test']['post_writes'], 'Never modify other fields, post types or missing posts.' );
update_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_SORT_ORDER, 7 );
document_meta_equal( 7, $GLOBALS['document_meta_test']['orders'][17], 'Creating the sort metadata updates native menu_order.' );
update_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_SORT_ORDER, 9 );
document_meta_equal( 9, $GLOBALS['document_meta_test']['orders'][17], 'Updating the sort metadata updates native menu_order.' );
$write_count = count( $GLOBALS['document_meta_test']['post_writes'] );
$fields->sync_native_sort_order( 1, 17, TheCore_Collectivity_Documents_Meta::META_SORT_ORDER, 9 );
document_meta_equal( $write_count, count( $GLOBALS['document_meta_test']['post_writes'] ), 'Unchanged native ordering does not write to the post.' );
$GLOBALS['document_meta_test']['during_post_update'] = static function( $data ) use ( $fields ) {
	$fields->sync_native_sort_order( 1, $data['ID'], TheCore_Collectivity_Documents_Meta::META_SORT_ORDER, 200 );
	document_meta_request( array( 'tccm_doc_sort_order' => '200' ) );
	$fields->save_metabox( $data['ID'], get_post( $data['ID'] ) );
};
$fields->sync_native_sort_order( 1, 17, TheCore_Collectivity_Documents_Meta::META_SORT_ORDER, -8 );
unset( $GLOBALS['document_meta_test']['during_post_update'] );
document_meta_equal( 0, $GLOBALS['document_meta_test']['orders'][17], 'Clamp the native ordering and guard against recursive post-save updates.' );
document_meta_equal( $write_count + 1, count( $GLOBALS['document_meta_test']['post_writes'] ), 'Recursive save callbacks cannot trigger additional writes.' );
$fields->sync_native_sort_order( 1, 17, TheCore_Collectivity_Documents_Meta::META_SORT_ORDER, 2 );
document_meta_equal( 2, $GLOBALS['document_meta_test']['orders'][17], 'The re-entry guard resets after an update.' );

update_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_SUMMARY, 'Existing summary' );
foreach ( array( null, array( 'verified' ), 'invalid' ) as $nonce ) {
	$_POST = array( 'tccm_doc_summary' => 'Overwrite' );
	if ( null !== $nonce ) $_POST[ TheCore_Collectivity_Documents_Meta::NONCE_NAME ] = $nonce;
	$fields->save_metabox( 17, $post );
	document_meta_equal( 'Existing summary', get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_SUMMARY ), 'Invalid or malformed nonces must not mutate fields.' );
}
foreach ( array( 'can_edit', 'revision', 'autosave' ) as $guard ) {
	document_meta_request( array( 'tccm_doc_summary' => 'Overwrite' ) );
	$GLOBALS['document_meta_test'][ $guard ] = 'can_edit' !== $guard;
	$fields->save_metabox( 17, $post );
	document_meta_equal( 'Existing summary', get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_SUMMARY ), 'Reject unauthorized, revision and autosave writes.' );
	$GLOBALS['document_meta_test'][ $guard ] = 'can_edit' === $guard;
}
document_meta_request( array( 'tccm_doc_summary' => 'Overwrite' ) );
$fields->save_metabox( 17, new WP_Post( 17, 'post' ) );
document_meta_equal( 'Existing summary', get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_SUMMARY ), 'Do not update other object types.' );

document_meta_request( array(
	'tccm_doc_summary' => "A <b>clear</b> summary\nSecond line",
	'tccm_doc_attachment_id' => '40',
	'tccm_doc_external_url' => 'https://example.org/fallback.pdf',
	'tccm_doc_download_label' => '<b>Download PDF</b>',
	'tccm_doc_reader_url' => 'https://example.org/complete.pdf',
	'tccm_doc_reader_label' => '<b>Complete version</b>',
	'tccm_doc_page_count' => '14',
	'tccm_doc_reference' => 'REF-42',
	'tccm_doc_version_label' => 'Version 2',
	'tccm_doc_effective_date' => '2028-02-29',
	'tccm_doc_updated_at' => '2026-02-30',
	'tccm_doc_sort_order' => '-8',
	'tccm_doc_featured' => '1',
	'tccm_doc_related_procedure_ids' => array( '12', '12', array( '41' ), '0' ),
) );
$fields->save_metabox( 17, $post );
document_meta_equal( "A clear summary\nSecond line", get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_SUMMARY ), 'Sanitize the summary without discarding line breaks.' );
document_meta_equal( 40, get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_ATTACHMENT_ID ), 'Save valid attachment ids.' );
document_meta_equal( 'Download PDF', get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_DOWNLOAD_LABEL ), 'Save the generic plain-text download label.' );
document_meta_equal( 'https://example.org/complete.pdf', get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_READER_URL ), 'Save a distinct generic consultation URL.' );
document_meta_equal( 'Complete version', get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_READER_LABEL ), 'Save a plain-text secondary label.' );
document_meta_equal( 14, get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_PAGE_COUNT ), 'Save an optional positive page count.' );
document_meta_equal( '2028-02-29', get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_EFFECTIVE_DATE ), 'Save valid effective dates.' );
document_meta_equal( '', get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_UPDATED_AT ), 'Do not save impossible dates.' );
document_meta_equal( 0, get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_SORT_ORDER ), 'Clamp saved display order.' );
document_meta_equal( '1', get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_FEATURED ), 'Save the featured flag.' );
document_meta_equal( array( 12 ), get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_RELATED_PROCEDURE_IDS ), 'Retain existing relation storage without nested input confusion.' );

document_meta_request( array( 'tccm_doc_reader_label' => 'Full version' ) );
$fields->save_metabox( 17, $post );
document_meta_equal( 'https://example.org/complete.pdf', get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_READER_URL ), 'Partial requests must not clear unsubmitted scalar fields.' );
document_meta_equal( '1', get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_FEATURED ), 'Partial requests preserve an unsubmitted featured flag.' );
document_meta_request( array( 'tccm_doc_reader_url' => '', 'tccm_doc_attachment_id' => '41', 'tccm_doc_external_url' => 'javascript:alert(1)', 'tccm_doc_featured' => '0', 'tccm_doc_page_count' => '-3' ) );
$fields->save_metabox( 17, $post );
document_meta_equal( '', get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_READER_URL ), 'An explicitly cleared secondary link is removed.' );
document_meta_equal( '', get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_ATTACHMENT_ID ), 'Invalid attachment references are not stored.' );
document_meta_equal( '', get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_EXTERNAL_URL ), 'Executable URLs are not stored.' );
document_meta_equal( '0', get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_FEATURED ), 'The hidden admin input permits clearing the checkbox.' );
document_meta_equal( 0, get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_PAGE_COUNT ), 'Page counts cannot be negative.' );

ob_start();
$fields->render_metabox( $post );
$markup = ob_get_clean();
document_meta_check( false !== strpos( $markup, 'name="tccm_doc_reader_url"' ), 'The native admin form exposes the secondary link.' );
document_meta_check( false !== strpos( $markup, 'name="tccm_doc_reader_label"' ), 'The native admin form exposes its generic label.' );
document_meta_check( false !== strpos( $markup, 'name="tccm_doc_download_label"' ), 'The native admin form exposes the main link label.' );
document_meta_check( false !== strpos( $markup, 'name="tccm_doc_page_count"' ), 'The native admin form exposes the optional page count.' );
document_meta_check( false !== strpos( $markup, 'type="hidden" name="tccm_doc_featured" value="0"' ), 'The admin form always submits the featured state.' );
document_meta_check( false !== strpos( $markup, 'Ordre d affichage global' ), 'The admin explains the shared global ordering.' );

$summary_before_autosave = get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_SUMMARY );
define( 'DOING_AUTOSAVE', true );
document_meta_request( array( 'tccm_doc_summary' => 'Autosave overwrite' ) );
$fields->save_metabox( 17, $post );
document_meta_equal( $summary_before_autosave, get_post_meta( 17, TheCore_Collectivity_Documents_Meta::META_SUMMARY ), 'The WordPress autosave constant also prevents writes.' );

fwrite( STDOUT, "Document metadata tests passed.\n" );
