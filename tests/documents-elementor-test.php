<?php
/** Standalone regressions for the native Elementor document field bridge. */

namespace Elementor {
	final class Controls_Manager {
		const SELECT = 'select';
		const TEXT = 'text';
	}
}

namespace Elementor\Core\DynamicTags {
	abstract class Base_Tag {
		private $settings;
		private $controls = array();
		public function __construct( $data = array() ) {
			$this->settings = $data['settings'] ?? array();
			$this->register_controls();
		}
		protected function register_controls() {}
		protected function add_control( $name, $definition ) {
			$this->controls[ $name ] = $definition;
		}
		public function get_settings( $name ) {
			return $this->settings[ $name ] ?? ( $this->controls[ $name ]['default'] ?? null );
		}
		public function get_controls() { return $this->controls; }
		public function get_editor_config() {
			return array(
				'name' => $this->get_name(),
				'group' => $this->get_group(),
				'categories' => $this->get_categories(),
				'display_conditions' => array( 'native_preserved' => array( 'label' => 'Preserved' ) ),
			);
		}
	}
	abstract class Tag extends Base_Tag {}
	abstract class Data_Tag extends Base_Tag {}
}

namespace {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
	$GLOBALS['documents_elementor_test'] = array(
		'post_id' => 17,
		'hooks' => array(),
		'lookups' => array(),
		'data' => array(
			17 => array(
				'title' => 'Conseil & mairie',
				'summary' => '<script>alert(1)</script>Summary',
				'reference' => 'PV-17',
				'version' => 'Version 2',
				'effective_date' => '2026-11-09',
				'updated_at' => '2026-11-10',
				'url' => 'https://example.org/document.pdf',
				'reader_url' => 'https://example.org/reader',
				'secondary_url' => 'https://example.org/reader',
				'download_label' => 'Telecharger le document',
				'download_aria_label' => 'Conseil & mairie: Telecharger le document',
				'reader_label' => 'Consulter',
				'reader_aria_label' => 'Conseil & mairie: Consulter',
				'page_count' => 12,
				'format_label' => '12 pages - PDF',
				'mime_type' => 'application/pdf',
				'file_size' => '2.00 KB',
				'thumbnail_id' => 42,
				'internal_note' => 'Never expose this field',
			),
			18 => array(),
			19 => array(),
		),
	);
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['documents_elementor_test']['hooks'][ $hook ] = array( $callback, $priority, $accepted_args );
	}
	function __( $value, $domain = '' ) { return $value; }
	function get_the_ID() { return $GLOBALS['documents_elementor_test']['post_id']; }
	function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
	function esc_url_raw( $value, $protocols = null ) {
		return filter_var( $value, FILTER_VALIDATE_URL ) && in_array( parse_url( $value, PHP_URL_SCHEME ), $protocols ?? array( 'http', 'https' ), true ) ? $value : '';
	}
	function wp_timezone() { return new DateTimeZone( 'Europe/Paris' ); }
	function get_option( $name ) { return 'date_format' === $name ? 'd/m/Y' : false; }
	function wp_date( $format, $timestamp, $timezone ) {
		return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone )->format( $format );
	}

	final class TheCore_Collectivity_Documents_Repository {
		public function get_public_document_data( $post_id ) {
			$GLOBALS['documents_elementor_test']['lookups'][] = $post_id;
			return $GLOBALS['documents_elementor_test']['data'][ $post_id ] ?? array();
		}
	}
	final class Documents_Test_Dynamic_Manager {
		public $groups = array();
		public $tags = array();
		public function register_group( $name, $definition ) { $this->groups[ $name ] = $definition; }
		public function register( $tag ) { $this->tags[ $tag->get_name() ] = $tag; }
	}
	final class Documents_Test_Condition_Stack {
		public $control;
		public $updates = array();
		public function __construct( $control ) { $this->control = $control; }
		public function get_controls( $name ) { return 'dynamic_tag' === $name ? $this->control : false; }
		public function update_control( $name, $definition ) {
			$this->updates[] = array( $name, $definition );
			$this->control = array_replace( $this->control, $definition );
		}
	}
	function documents_elementor_equal( $expected, $actual, $message ) {
		if ( $expected !== $actual ) {
			throw new RuntimeException( $message . ' Expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
		}
	}
	function documents_elementor_check( $condition, $message ) {
		if ( ! $condition ) throw new RuntimeException( $message );
	}
	function documents_elementor_render( $settings = array() ) {
		$tag = new TheCore_Collectivity_Document_Field_Tag( array( 'settings' => $settings ) );
		ob_start();
		$tag->render();
		return ob_get_clean();
	}

	require ABSPATH . 'includes/features/documents/class-thecore-collectivity-documents-elementor.php';
	$integration = new TheCore_Collectivity_Documents_Elementor();
	$integration->register_hooks();
	documents_elementor_check( isset( $GLOBALS['documents_elementor_test']['hooks']['elementor/dynamic_tags/register'] ), 'Register on the native dynamic tag hook.' );
	documents_elementor_equal( 1, count( $GLOBALS['documents_elementor_test']['hooks'] ), 'No custom loop, frontend asset or query registration is needed.' );
	$manager = new Documents_Test_Dynamic_Manager();
	$integration->register_tags( $manager );
	documents_elementor_check( ! isset( $GLOBALS['documents_elementor_test']['hooks']['elementor/element/dynamic_tags/__settings/before_section_end'] ), 'Core-only installations do not register a Pro condition UI hook.' );
}

namespace ElementorPro\Modules\DisplayConditions\Conditions {
	if ( ! class_exists( __NAMESPACE__ . '\\Dynamic_Tags_Condition', false ) ) {
		final class Dynamic_Tags_Condition {}
	}
}

namespace {
	$integration->register_tags( $manager );
	documents_elementor_check( isset( $GLOBALS['documents_elementor_test']['hooks']['elementor/element/dynamic_tags/__settings/before_section_end'] ), 'Add the UI hook only when the existing native Pro condition is available.' );
	documents_elementor_equal( array( 'tccm-document-field', 'tccm-document-url' ), array_keys( $manager->tags ), 'Expose only document text and URL tags.' );
	documents_elementor_check( isset( $manager->groups['tccm-documents'] ), 'Keep document fields in a named native editor group.' );
	documents_elementor_equal( array( 'text' ), $manager->tags['tccm-document-field']->get_categories(), 'Text remains compatible with native atomic text props.' );
	documents_elementor_equal( array( 'url' ), $manager->tags['tccm-document-url']->get_categories(), 'Document links remain compatible with native atomic link props.' );
	foreach ( $manager->tags as $tag ) {
		foreach ( $tag->get_controls() as $control ) {
			documents_elementor_check( in_array( $control['type'], array( 'select', 'text' ), true ), 'Use control types supported by the native V4 converter.' );
			documents_elementor_check( array_key_exists( 'default', $control ), 'Every converted control has an explicit default.' );
		}
	}
	$existing_options = array( 'native_option' => 'Native label', 'native_group' => array( 'label' => 'Native group', 'type' => 'group' ) );
	$stack = new Documents_Test_Condition_Stack( array( 'options' => $existing_options, 'default' => 'native_option', 'disabled_options' => array( 'native_option' ) ) );
	$integration->add_display_condition_option( $stack );
	documents_elementor_equal( 'native_option', $stack->control['default'], 'Do not change the native condition default.' );
	documents_elementor_equal( array( 'native_option' ), $stack->control['disabled_options'], 'Keep the native disabled-option restrictions.' );
	documents_elementor_equal( 'Native label', $stack->control['options']['native_option'], 'Merge options without replacing existing native labels.' );
	documents_elementor_equal( array( 'label' => 'Native group', 'type' => 'group' ), $stack->control['options']['native_group'], 'Preserve native group markers.' );
	documents_elementor_equal( 'group', $stack->control['options'][ TheCore_Collectivity_Documents_Elementor::GROUP ]['type'], 'Give documents their own native select group.' );
	documents_elementor_equal( 'Document - Lien secondaire', $stack->control['options'][ TheCore_Collectivity_Documents_Elementor::CONDITION_SECONDARY_URL ], 'Expose the secondary URL with a clear condition label.' );
	$integration->add_display_condition_option( $stack );
	documents_elementor_equal( 4, count( $stack->control['options'] ), 'Registering the same condition again does not duplicate options.' );
	$existing_custom = new Documents_Test_Condition_Stack( array( 'options' => array( TheCore_Collectivity_Documents_Elementor::CONDITION_SECONDARY_URL => 'Existing integration label' ) ) );
	$integration->add_display_condition_option( $existing_custom );
	documents_elementor_equal( 'Existing integration label', $existing_custom->control['options'][ TheCore_Collectivity_Documents_Elementor::CONDITION_SECONDARY_URL ], 'Do not overwrite an option already registered under the same key.' );
	foreach ( array( false, array(), array( 'options' => 'invalid' ) ) as $missing_control ) {
		$absent_stack = new Documents_Test_Condition_Stack( $missing_control );
		$integration->add_display_condition_option( $absent_stack );
		documents_elementor_equal( array(), $absent_stack->updates, 'A missing or unsupported native control is untouched.' );
	}
	$integration->add_display_condition_option( null );
	$integration->add_display_condition_option( new stdClass() );
	$secondary_config = $manager->tags[ TheCore_Collectivity_Documents_Elementor::TAG_URL ]->get_editor_config();
	documents_elementor_check( isset( $secondary_config['display_conditions']['native_preserved'] ), 'Keep the inherited tag configuration when adding the condition.' );
	$condition_definition = $secondary_config['display_conditions'][ TheCore_Collectivity_Documents_Elementor::CONDITION_SECONDARY_URL ];
	documents_elementor_equal( array( 'field' => 'secondary_url' ), $condition_definition['settings'], 'Give the native data provider plain tag settings, not atomic prop wrappers.' );
	documents_elementor_equal( TheCore_Collectivity_Documents_Elementor::GROUP, $condition_definition['group'], 'Use the document group consistently in tag and UI configuration.' );
	$native_resolved_definition = $condition_definition + array( 'dynamic_tag_name' => $secondary_config['name'] );
	documents_elementor_equal( TheCore_Collectivity_Documents_Elementor::TAG_URL, $native_resolved_definition['dynamic_tag_name'], 'The native provider can resolve this condition to the registered URL tag.' );
	$native_condition_tag = new TheCore_Collectivity_Document_Url_Tag( array( 'settings' => $native_resolved_definition['settings'] ) );
	documents_elementor_equal( 'https://example.org/reader', $native_condition_tag->get_value(), 'Plain native condition settings resolve the actual secondary URL server-side.' );
	documents_elementor_equal( 'Conseil &amp; mairie', documents_elementor_render(), 'Render the default title with text escaping.' );
	documents_elementor_equal( '&lt;script&gt;alert(1)&lt;/script&gt;Summary', documents_elementor_render( array( 'field' => 'summary' ) ), 'Document text cannot inject markup.' );
	documents_elementor_equal( '', documents_elementor_render( array( 'field' => 'internal_note' ) ), 'Only the public text field allowlist can be selected.' );
	documents_elementor_equal( '', documents_elementor_render( array( 'field' => array( 'title' ) ) ), 'Malformed field settings are rejected.' );
	documents_elementor_equal( '', documents_elementor_render( array( 'field' => 'url' ) ), 'URLs are not exposed as generic text fields.' );
	documents_elementor_equal( '09/11/2026', documents_elementor_render( array( 'field' => 'effective_date' ) ), 'Use the WordPress date format by default.' );
	documents_elementor_equal( '2026', documents_elementor_render( array( 'field' => 'effective_date', 'date_format' => 'Y' ) ), 'Allow native PHP date formats explicitly.' );
	documents_elementor_equal( '2.00 KB', documents_elementor_render( array( 'field' => 'file_size' ) ), 'Preserve the file size already formatted by the repository.' );
	documents_elementor_equal( 'Telecharger le document', documents_elementor_render( array( 'field' => 'download_label' ) ), 'Preserve a custom download label.' );
	documents_elementor_equal( 'Conseil &amp; mairie: Telecharger le document', documents_elementor_render( array( 'field' => 'download_aria_label' ) ), 'Escape the computed accessible download label exactly once.' );
	documents_elementor_equal( 'Conseil &amp; mairie: Consulter', documents_elementor_render( array( 'field' => 'reader_aria_label' ) ), 'Escape the computed accessible consultation label exactly once.' );
	documents_elementor_equal( '12', documents_elementor_render( array( 'field' => 'page_count' ) ), 'Expose document page counts as native text.' );
	documents_elementor_equal( '12 pages - PDF', documents_elementor_render( array( 'field' => 'format_label' ) ), 'Expose the centralized display label without recomputing repository metadata.' );
	$GLOBALS['documents_elementor_test']['data'][17]['effective_date'] = '2026-02-30';
	documents_elementor_equal( '', documents_elementor_render( array( 'field' => 'effective_date' ) ), 'Impossible dates do not silently roll over.' );
	$GLOBALS['documents_elementor_test']['data'][17]['effective_date'] = "2026-11-09\0";
	documents_elementor_equal( '', documents_elementor_render( array( 'field' => 'effective_date' ) ), 'Malformed dates are rejected before reaching PHP date parsing.' );
	$GLOBALS['documents_elementor_test']['data'][17]['reference'] = array( 'not', 'scalar' );
	documents_elementor_equal( '', documents_elementor_render( array( 'field' => 'reference' ) ), 'Structured metadata cannot leak through text tags.' );
	$url_tag = new TheCore_Collectivity_Document_Url_Tag();
	documents_elementor_equal( 'https://example.org/document.pdf', $url_tag->get_value(), 'Use the resolved file URL, not the CPT permalink.' );
	$reader_tag = new TheCore_Collectivity_Document_Url_Tag( array( 'settings' => array( 'field' => 'reader_url' ) ) );
	documents_elementor_equal( 'https://example.org/reader', $reader_tag->get_value(), 'Support a separate consultation link.' );
	$secondary_tag = new TheCore_Collectivity_Document_Url_Tag( array( 'settings' => array( 'field' => 'secondary_url' ) ) );
	documents_elementor_equal( 'https://example.org/reader', $secondary_tag->get_value(), 'Expose a secondary link separately from the consultation fallback.' );
	$GLOBALS['documents_elementor_test']['data'][17]['secondary_url'] = '';
	$GLOBALS['documents_elementor_test']['data'][17]['reader_url'] = 'https://example.org/document.pdf';
	documents_elementor_equal( '', $secondary_tag->get_value(), 'An absent secondary link stays empty.' );
	documents_elementor_equal( '', $native_condition_tag->get_value(), 'The native is_not_empty condition receives an empty value when no secondary URL exists.' );
	documents_elementor_equal( 'https://example.org/document.pdf', $reader_tag->get_value(), 'The consultation fallback remains a repository responsibility.' );
	$invalid_tag = new TheCore_Collectivity_Document_Url_Tag( array( 'settings' => array( 'field' => 'internal_note' ) ) );
	documents_elementor_equal( '', $invalid_tag->get_value(), 'No arbitrary metadata URL is readable.' );
	$GLOBALS['documents_elementor_test']['data'][17]['reader_url'] = 'javascript:alert(1)';
	documents_elementor_equal( '', $reader_tag->get_value(), 'Executable URL schemes cannot reach native links.' );
	foreach ( array( 18, 19, 999 ) as $hidden_post_id ) {
		$GLOBALS['documents_elementor_test']['post_id'] = $hidden_post_id;
		documents_elementor_equal( '', documents_elementor_render(), 'Empty repository results stay empty for protected, unpublished or non-document objects.' );
		documents_elementor_equal( '', $url_tag->get_value(), 'Hidden documents never yield a link.' );
	}
	$GLOBALS['documents_elementor_test']['post_id'] = 0;
	$count_before = count( $GLOBALS['documents_elementor_test']['lookups'] );
	documents_elementor_equal( '', documents_elementor_render(), 'Do not guess a page or query object outside a valid post context.' );
	documents_elementor_equal( $count_before, count( $GLOBALS['documents_elementor_test']['lookups'] ), 'Invalid current post IDs never query the repository.' );
	$GLOBALS['documents_elementor_test']['post_id'] = 17;
	documents_elementor_equal( 'Conseil &amp; mairie', documents_elementor_render(), 'A later loop iteration resolves the new current post without stale tag caching.' );

	fwrite( STDOUT, "Document Elementor integration tests passed.\n" );
}
