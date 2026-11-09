<?php
/** Standalone regressions for native document collections and public loop data. */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
$GLOBALS['documents_test'] = array(
	'post_types' => array(), 'taxonomies' => array(), 'queries' => array(),
	'posts' => array(), 'meta' => array(), 'terms' => array(), 'memberships' => array(),
	'attachments' => array(), 'thumbnails' => array(),
);

class WP_Post {
	public $ID;
	public $post_type;
	public $post_status;
	public $post_password = '';
	public $post_title;
	public function __construct( $id, $type = 'tccm_document', $status = 'publish', $password = '' ) {
		$this->ID = $id;
		$this->post_type = $type;
		$this->post_status = $status;
		$this->post_password = $password;
		$this->post_title = 'Document ' . $id;
	}
}
class WP_Error {}
class WP_Query {
	public $query_vars;
	public function __construct( $args = array() ) { $this->query_vars = $args; }
	public function get( $key ) { return $this->query_vars[ $key ] ?? ''; }
	public function set( $key, $value ) { $this->query_vars[ $key ] = $value; }
}
function __( $value, $domain = '' ) { return $value; }
function _n( $single, $plural, $number, $domain = '' ) { return 1 === (int) $number ? $single : $plural; }
function number_format_i18n( $number ) { return number_format( $number ); }
function esc_html__( $value, $domain = '' ) { return esc_html( $value ); }
function register_post_type( $name, $args ) { $GLOBALS['documents_test']['post_types'][ $name ] = $args; }
function register_taxonomy( $name, $objects, $args ) { $GLOBALS['documents_test']['taxonomies'][ $name ] = array( 'objects' => $objects, 'args' => $args ); }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, $args ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_title( $value ) { return trim( preg_replace( '/[^a-z0-9-]+/', '-', strtolower( $value ) ), '-' ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $value ) ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function get_term( $id, $taxonomy ) { return $GLOBALS['documents_test']['terms'][ $id ] ?? false; }
function get_term_by( $field, $value, $taxonomy ) {
	foreach ( $GLOBALS['documents_test']['terms'] as $term ) {
		if ( $term instanceof WP_Error ) continue;
		if ( $term->taxonomy === $taxonomy && $term->$field === $value ) return $term;
	}
	return false;
}
function get_post( $id ) { return $GLOBALS['documents_test']['posts'][ $id ] ?? null; }
function get_post_type( $id ) { $post = get_post( $id ); return $post ? $post->post_type : false; }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['documents_test']['meta'][ $id ][ $key ] ?? ''; }
function get_the_title( $post ) { return str_replace( array( ' - ', "'", '&' ), array( ' &#8211; ', '&#8217;', '&amp;' ), $post->post_title ); }
function get_post_thumbnail_id( $id ) { return $GLOBALS['documents_test']['thumbnails'][ $id ] ?? 0; }
function wp_get_attachment_url( $id ) { return $GLOBALS['documents_test']['attachments'][ $id ] ?? false; }
function get_attached_file( $id ) { return false; }
function get_post_mime_type( $id ) { return 'application/pdf'; }
function esc_url_raw( $url, $protocols = null ) {
	$scheme = parse_url( $url, PHP_URL_SCHEME );
	return filter_var( $url, FILTER_VALIDATE_URL ) && in_array( strtolower( $scheme ?? '' ), $protocols ?? array( 'http', 'https' ), true ) ? $url : '';
}
function esc_url( $value ) { return esc_url_raw( $value ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function shortcode_atts( $defaults, $args, $tag = '' ) { return array_intersect_key( array_merge( $defaults, $args ), $defaults ); }
function get_posts( $args ) {
	$GLOBALS['documents_test']['queries'][] = $args;
	return array_values( array_filter( $GLOBALS['documents_test']['posts'], static function( $post ) use ( $args ) {
		if ( $post->post_type !== $args['post_type'] || $post->post_status !== $args['post_status'] ) return false;
		if ( isset( $args['has_password'] ) && false === $args['has_password'] && '' !== $post->post_password ) return false;
		if ( ! empty( $args['post__in'] ) && ! in_array( $post->ID, $args['post__in'], true ) ) return false;
		if ( ! empty( $args['meta_query'] ) && ! document_collection_match_meta( $post->ID, $args['meta_query'] ) ) return false;
		if ( ! empty( $args['tax_query'] ) && ! document_collection_match_tax( $post->ID, $args['tax_query'] ) ) return false;
		return true;
	} ) );
}

require ABSPATH . 'includes/features/documents/class-thecore-collectivity-documents-post-type.php';
require ABSPATH . 'includes/features/documents/class-thecore-collectivity-documents-meta.php';
require ABSPATH . 'includes/features/documents/class-thecore-collectivity-documents-repository.php';
require ABSPATH . 'includes/features/documents/class-thecore-collectivity-documents-frontend.php';

function document_collection_check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
function document_collection_equal( $expected, $actual, $message ) {
	document_collection_check( $expected === $actual, $message . ' Expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
}
function document_collection_match_meta( $id, $query ) {
	if ( isset( $query['key'] ) ) {
		$exists = array_key_exists( $query['key'], $GLOBALS['documents_test']['meta'][ $id ] ?? array() );
		if ( 'NOT EXISTS' === ( $query['compare'] ?? '' ) ) return ! $exists;
		if ( 'EXISTS' === ( $query['compare'] ?? '' ) ) return $exists;
		return $exists && (string) get_post_meta( $id, $query['key'] ) === (string) ( $query['value'] ?? '' );
	}
	$matches = array();
	foreach ( $query as $key => $clause ) if ( 'relation' !== $key && is_array( $clause ) ) $matches[] = document_collection_match_meta( $id, $clause );
	return 'OR' === strtoupper( $query['relation'] ?? 'AND' ) ? in_array( true, $matches, true ) : ! in_array( false, $matches, true );
}
function document_collection_query() { return end( $GLOBALS['documents_test']['queries'] ); }
function document_collection_match_tax( $id, $query ) {
	if ( isset( $query['taxonomy'] ) ) {
		$memberships = $GLOBALS['documents_test']['memberships'][ $id ][ $query['taxonomy'] ] ?? array();
		return (bool) array_intersect( $memberships, $query['terms'] );
	}
	$matches = array();
	foreach ( $query as $key => $clause ) if ( 'relation' !== $key && is_array( $clause ) ) $matches[] = document_collection_match_tax( $id, $clause );
	return 'OR' === strtoupper( $query['relation'] ?? 'AND' ) ? in_array( true, $matches, true ) : ! in_array( false, $matches, true );
}

$types = new TheCore_Collectivity_Documents_Post_Type();
$types->register_post_type();
$types->register_taxonomies();
$registered_post = $GLOBALS['documents_test']['post_types']['tccm_document'];
foreach ( array( 'thumbnail', 'custom-fields' ) as $support ) document_collection_check( in_array( $support, $registered_post['supports'], true ), 'Native loop fields require document support for ' . $support . '.' );
document_collection_equal( true, $registered_post['show_in_rest'], 'Native editing requires the WordPress REST object.' );
document_collection_equal( false, $registered_post['publicly_queryable'], 'Document records must not expose individual public content pages.' );
$collection = $GLOBALS['documents_test']['taxonomies']['tccm_doc_collection'];
document_collection_equal( array( 'tccm_document' ), $collection['objects'], 'Collections apply only to documents.' );
foreach ( array( 'hierarchical', 'show_ui', 'show_in_rest', 'show_admin_column' ) as $flag ) document_collection_equal( true, $collection['args'][ $flag ], 'Collection flag ' . $flag . ' enables native WordPress management.' );
foreach ( array( 'public', 'publicly_queryable', 'rewrite' ) as $flag ) document_collection_equal( false, $collection['args'][ $flag ], 'Collections are filters, not public archive pages.' );

$taxonomy = TheCore_Collectivity_Documents_Post_Type::TAXONOMY_COLLECTION;
$GLOBALS['documents_test']['terms'] = array(
	7 => (object) array( 'term_id' => 7, 'taxonomy' => $taxonomy, 'slug' => 'minutes' ),
	8 => (object) array( 'term_id' => 8, 'taxonomy' => 'category', 'slug' => 'unrelated' ),
	9 => new WP_Error(),
	10 => (object) array( 'term_id' => 10, 'taxonomy' => $taxonomy, 'slug' => 'empty-collection' ),
);
$repository = new TheCore_Collectivity_Documents_Repository();
$repository->get_documents();
$query = document_collection_query();
document_collection_equal( 'tccm_document', $query['post_type'], 'Queries target the existing document CPT.' );
document_collection_equal( 'publish', $query['post_status'], 'Queries default to published documents.' );
document_collection_equal( false, $query['has_password'], 'Queries exclude password-protected documents.' );
document_collection_check( empty( $query['tax_query'] ), 'Omitting a collection does not add an accidental taxonomy filter.' );
foreach ( array( 7, '7', 'minutes' ) as $selector ) {
	$repository->get_documents( array( 'collection' => $selector, 'posts_per_page' => 5 ) );
	$query = document_collection_query();
	document_collection_equal( 5, $query['posts_per_page'], 'Existing query arguments remain supported.' );
	document_collection_check( ! isset( $query['collection'] ), 'Collection selector must not leak into native query arguments.' );
	document_collection_equal( array( 7 ), $query['tax_query'][0]['terms'], 'ID and slug selectors resolve to the same collection.' );
	document_collection_equal( true, $query['tax_query'][0]['include_children'], 'Parent collections include their nested collections.' );
}
foreach ( array( '', 0, 8, 9, 999, 'missing', array( 7 ), new stdClass() ) as $invalid ) {
	$repository->get_documents( array( 'collection' => $invalid ) );
	document_collection_equal( array( 0 ), document_collection_query()['post__in'], 'Explicitly empty, invalid, wrong-taxonomy or missing collections fail closed.' );
}
$tax_constraint = array( 'relation' => 'OR', array( 'taxonomy' => 'tccm_doc_type', 'field' => 'slug', 'terms' => array( 'guide' ) ), array( 'taxonomy' => 'tccm_doc_type', 'field' => 'slug', 'terms' => array( 'plan' ) ) );
$meta_constraint = array( 'relation' => 'OR', array( 'key' => '_test_visible', 'value' => '1' ), array( 'key' => '_test_extra', 'value' => '1' ) );
$repository->get_documents( array( 'collection' => 7, 'tax_query' => $tax_constraint, 'meta_query' => $meta_constraint ) );
$query = document_collection_query();
document_collection_equal( 'AND', $query['tax_query']['relation'], 'Collection restrictions combine with an existing OR taxonomy group using AND.' );
document_collection_equal( $tax_constraint, $query['tax_query'][0], 'Preserve the complete native taxonomy group.' );
document_collection_equal( 'AND', $query['meta_query']['relation'], 'Ordering must not weaken native metadata restrictions.' );
document_collection_equal( $meta_constraint, $query['meta_query'][1], 'Preserve the complete native metadata group.' );
$sort = TheCore_Collectivity_Documents_Repository::get_sort_meta_query();
document_collection_equal( 'OR', $sort['relation'], 'Ordering includes records with and without order metadata.' );
document_collection_equal( 'EXISTS', $sort['tccm_document_order']['compare'], 'Order clause includes existing values.' );
document_collection_equal( 'NUMERIC', $sort['tccm_document_order']['type'], 'Order values sort numerically.' );
document_collection_equal( 'NOT EXISTS', $sort['tccm_document_order_missing']['compare'], 'Missing order metadata must not exclude legacy documents.' );

foreach ( array( 17, 18 ) as $id ) $GLOBALS['documents_test']['posts'][ $id ] = new WP_Post( $id );
$GLOBALS['documents_test']['meta'][17] = array( TheCore_Collectivity_Documents_Meta::META_SORT_ORDER => 8, '_test_visible' => '1', TheCore_Collectivity_Documents_Meta::META_FEATURED => '1' );
$GLOBALS['documents_test']['memberships'][17][ $taxonomy ] = array( 7 );
document_collection_equal( array( 17, 18 ), array_map( static function( $post ) { return $post->ID; }, $repository->get_documents() ), 'Both ordered and unordered documents remain visible.' );
document_collection_equal( array( 17 ), array_map( static function( $post ) { return $post->ID; }, $repository->get_documents( array( 'collection' => 7 ) ) ), 'A valid collection limits the native query to its members.' );
document_collection_equal( array(), $repository->get_documents( array( 'collection' => 'empty-collection' ) ), 'A valid empty collection stays empty instead of showing every document.' );
document_collection_equal( array( 17 ), array_map( static function( $post ) { return $post->ID; }, $repository->get_documents( array( 'meta_query' => $meta_constraint ) ) ), 'Ordering does not bypass the caller metadata filter.' );
document_collection_equal( array(), $repository->get_documents( array( 'collection' => 'missing' ) ), 'Invalid collection queries never fall back to all documents.' );

$frontend = new TheCore_Collectivity_Documents_Frontend();
$featured = new WP_Query();
$frontend->apply_featured_query( $featured );
document_collection_check( document_collection_match_meta( 17, $featured->get( 'meta_query' ) ), 'Featured queries keep featured documents.' );
document_collection_check( ! document_collection_match_meta( 18, $featured->get( 'meta_query' ) ), 'Featured queries must not expose other documents through the sort OR group.' );
$theme_query = new WP_Query( array( 'tax_query' => $tax_constraint, 'meta_query' => $meta_constraint, 'posts_per_page' => 6 ) );
$frontend->apply_urbanisme_query( $theme_query );
document_collection_equal( 'AND', $theme_query->get( 'tax_query' )['relation'], 'Theme restrictions do not weaken an existing OR taxonomy group.' );
document_collection_equal( $tax_constraint, $theme_query->get( 'tax_query' )[0], 'Theme queries preserve native taxonomy conditions as a group.' );
document_collection_equal( false, $theme_query->get( 'has_password' ), 'Frontend query IDs exclude password-protected documents.' );
document_collection_equal( 6, $theme_query->get( 'posts_per_page' ), 'Frontend query IDs retain native pagination.' );

foreach ( array( 19 => 'draft', 20 => 'private' ) as $id => $status ) $GLOBALS['documents_test']['posts'][ $id ] = new WP_Post( $id, 'tccm_document', $status );
$GLOBALS['documents_test']['posts'][21] = new WP_Post( 21, 'tccm_document', 'publish', 'secret' );
$GLOBALS['documents_test']['posts'][22] = new WP_Post( 22, 'post' );
foreach ( array( 0, 19, 20, 21, 22, 999 ) as $id ) document_collection_equal( array(), $repository->get_public_document_data( $id ), 'Public fields exclude unknown, unpublished, private, protected and unrelated content.' );
$GLOBALS['documents_test']['posts'][40] = new WP_Post( 40, 'attachment', 'inherit' );
$GLOBALS['documents_test']['attachments'][40] = 'https://example.org/uploads/minutes.pdf';
$GLOBALS['documents_test']['thumbnails'][17] = 45;
$GLOBALS['documents_test']['posts'][17]->post_title = "Minutes - l'assemblee & ses decisions";
$GLOBALS['documents_test']['meta'][17] += array(
	TheCore_Collectivity_Documents_Meta::META_ATTACHMENT_ID => 40,
	TheCore_Collectivity_Documents_Meta::META_EXTERNAL_URL => 'https://example.org/fallback.pdf',
	TheCore_Collectivity_Documents_Meta::META_READER_URL => 'https://example.org/full-version.pdf',
	TheCore_Collectivity_Documents_Meta::META_READER_LABEL => 'Full version',
	TheCore_Collectivity_Documents_Meta::META_DOWNLOAD_LABEL => 'Download minutes',
	TheCore_Collectivity_Documents_Meta::META_PAGE_COUNT => 4,
	TheCore_Collectivity_Documents_Meta::META_SUMMARY => 'A short summary',
	TheCore_Collectivity_Documents_Meta::META_RELATED_PROCEDURE_IDS => array( 11, 12 ),
	'_private_note' => 'Never render this',
);
$data = $repository->get_public_document_data( 17 );
document_collection_equal( "Minutes - l'assemblee & ses decisions", $data['title'], 'Public title data preserves raw punctuation instead of pre-escaped WordPress title filters.' );
document_collection_equal( 'https://example.org/uploads/minutes.pdf', $data['url'], 'The attachment is the primary source when configured.' );
document_collection_equal( 'https://example.org/full-version.pdf', $data['reader_url'], 'A consultation URL remains distinct from the primary document URL.' );
document_collection_equal( 'Full version', $data['reader_label'], 'The secondary action has its own label.' );
document_collection_equal( 'Download minutes', $data['download_label'], 'The primary action has its own editable label.' );
document_collection_equal( "Minutes - l'assemblee & ses decisions: Download minutes", $data['download_aria_label'], 'Primary accessible names preserve the raw title and identify the action.' );
document_collection_equal( "Minutes - l'assemblee & ses decisions: Full version", $data['reader_aria_label'], 'Secondary accessible names preserve the raw title and identify the consultation action.' );
document_collection_equal( 'https://example.org/full-version.pdf', $data['secondary_url'], 'The optional secondary action has a URL without a primary fallback.' );
document_collection_equal( 4, $data['page_count'], 'The page count is a numeric public field.' );
document_collection_equal( "4 pages \u{00B7} PDF", $data['format_label'], 'Format labels combine declared page count with the URL file format.' );
document_collection_equal( 45, $data['thumbnail_id'], 'Native featured images remain available.' );
document_collection_equal( array( 'title', 'summary', 'reference', 'version', 'effective_date', 'updated_at', 'url', 'download_label', 'reader_url', 'secondary_url', 'reader_label', 'mime_type', 'file_size', 'page_count', 'format_label', 'thumbnail_id', 'download_aria_label', 'reader_aria_label' ), array_keys( $data ), 'The public data contract uses an explicit field allowlist without internal metadata.' );
unset( $GLOBALS['documents_test']['meta'][17][ TheCore_Collectivity_Documents_Meta::META_READER_URL ] );
document_collection_equal( $data['url'], $repository->get_public_document_data( 17 )['reader_url'], 'The consultation action falls back to the primary URL.' );
document_collection_equal( '', $repository->get_public_document_data( 17 )['secondary_url'], 'The optional secondary action stays empty when not configured.' );
$GLOBALS['documents_test']['meta'][17][ TheCore_Collectivity_Documents_Meta::META_PAGE_COUNT ] = 1;
document_collection_equal( "1 page \u{00B7} PDF", $repository->get_public_document_data( 17 )['format_label'], 'A single page uses the singular label.' );
foreach ( array( 'javascript:alert(1)', 'file:///private/minutes.pdf', 'ftp://example.org/minutes.pdf', '/uploads/minutes.pdf', '//example.org/minutes.pdf', array( 'https://example.org/minutes.pdf' ), 'https://user:password@example.org/minutes.pdf' ) as $unsafe ) {
	document_collection_equal( '', TheCore_Collectivity_Documents_Repository::sanitize_public_url( $unsafe ), 'Unsafe, relative, structured or credential-bearing URLs are rejected.' );
}
$GLOBALS['documents_test']['meta'][18] = array( TheCore_Collectivity_Documents_Meta::META_EXTERNAL_URL => 'javascript:alert(1)', TheCore_Collectivity_Documents_Meta::META_READER_URL => 'file:///private/minutes.pdf' );
document_collection_equal( '', $repository->get_public_document_data( 18 )['url'], 'Unsafe persisted download URLs never enter public bindings.' );
document_collection_equal( '', $repository->get_public_document_data( 18 )['reader_url'], 'Unsafe persisted consultation URLs never enter public bindings.' );
$saved_meta = $GLOBALS['documents_test']['meta'][17];
foreach ( array( TheCore_Collectivity_Documents_Meta::META_SUMMARY, TheCore_Collectivity_Documents_Meta::META_REFERENCE, TheCore_Collectivity_Documents_Meta::META_VERSION_LABEL, TheCore_Collectivity_Documents_Meta::META_READER_URL, TheCore_Collectivity_Documents_Meta::META_READER_LABEL, TheCore_Collectivity_Documents_Meta::META_DOWNLOAD_LABEL, TheCore_Collectivity_Documents_Meta::META_PAGE_COUNT, TheCore_Collectivity_Documents_Meta::META_EXTERNAL_URL ) as $key ) $GLOBALS['documents_test']['meta'][17][ $key ] = array( 'Malformed legacy value' );
set_error_handler( static function( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
try {
	$malformed = $repository->get_public_document_data( 17 );
} finally {
	restore_error_handler();
	$GLOBALS['documents_test']['meta'][17] = $saved_meta;
}
foreach ( array( 'summary', 'reference', 'version', 'secondary_url' ) as $field ) document_collection_equal( '', $malformed[ $field ], 'Structured legacy metadata cannot leak or trigger warnings in scalar bindings.' );
document_collection_equal( 0, $malformed['page_count'], 'Malformed page counts use the empty numeric default.' );
foreach ( array( 19, 20, 21, 22 ) as $id ) {
	$GLOBALS['documents_test']['meta'][ $id ] = array( TheCore_Collectivity_Documents_Meta::META_SUMMARY => 'Private summary', TheCore_Collectivity_Documents_Meta::META_EXTERNAL_URL => 'https://example.org/private.pdf' );
	document_collection_equal( '', $frontend->shortcode_meta( array( 'post_id' => $id, 'field' => 'summary' ) ), 'Existing scalar shortcodes cannot leak unpublished data.' );
	document_collection_equal( '', $frontend->shortcode_link( array( 'post_id' => $id, 'part' => 'url' ) ), 'Existing link shortcodes cannot leak protected download URLs.' );
}
fwrite( STDOUT, "Document collection tests passed.\n" );
