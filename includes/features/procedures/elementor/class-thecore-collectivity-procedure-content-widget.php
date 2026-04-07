<?php
/**
 * Atomic Elementor widget for procedure content blocks.
 */

use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Switch_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control;
use Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base;
use Elementor\Modules\AtomicWidgets\PropTypes\Attributes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Classes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Boolean_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;
use Elementor\Modules\Components\PropTypes\Overridable_Prop_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Procedure_Content_Widget extends Atomic_Widget_Base {
	/**
	 * Supported content blocks.
	 */
	const TYPE_REQUIRED_DOCUMENTS = 'required_documents';
	const TYPE_STEPS              = 'steps';
	const TYPE_CASES              = 'cases';
	const TYPE_FAQ                = 'faq';
	const TYPE_RELATED_DOCUMENTS  = 'related_documents';

	/**
	 * Repository instance.
	 *
	 * @var TheCore_Collectivity_Procedures_Repository
	 */
	private $procedures_repository;

	/**
	 * Documents repository instance.
	 *
	 * @var TheCore_Collectivity_Documents_Repository|null
	 */
	private $documents_repository;

	/**
	 * Constructor.
	 *
	 * @param array $data Widget data.
	 * @param mixed $args Widget args.
	 */
	public function __construct( $data = array(), $args = null ) {
		parent::__construct( $data, $args );

		$this->procedures_repository = new TheCore_Collectivity_Procedures_Repository();
		$this->documents_repository  = class_exists( 'TheCore_Collectivity_Documents_Repository' )
			? new TheCore_Collectivity_Documents_Repository()
			: null;
	}

	/**
	 * Element type.
	 *
	 * @return string
	 */
	public static function get_element_type(): string {
		return 'tccm-procedure-content';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return esc_html__( 'Procedure Content', 'thecore-collectivity-management' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-post-list';
	}

	/**
	 * Widget categories.
	 *
	 * @return array
	 */
	public function get_categories(): array {
		return array( 'v4-elements' );
	}

	/**
	 * Widget keywords.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return array( 'procedure', 'demarche', 'faq', 'documents', 'atomic' );
	}

	/**
	 * Style dependencies.
	 *
	 * @return array
	 */
	public function get_style_depends() {
		return array( TheCore_Collectivity_Elementor::HANDLE_PROCEDURE_STYLE );
	}

	/**
	 * Define props schema.
	 *
	 * @return array
	 */
	protected static function define_props_schema(): array {
		return array(
			'classes'      => Classes_Prop_Type::make()->default( array() ),
			'content_type' => String_Prop_Type::make()
				->default( self::TYPE_REQUIRED_DOCUMENTS )
				->enum(
					array(
						self::TYPE_REQUIRED_DOCUMENTS,
						self::TYPE_STEPS,
						self::TYPE_CASES,
						self::TYPE_FAQ,
						self::TYPE_RELATED_DOCUMENTS,
					)
				),
			'show_meta'    => Boolean_Prop_Type::make()->default( true ),
			'post_id'      => String_Prop_Type::make()->default( '' ),
			'attributes'   => Attributes_Prop_Type::make()->meta( Overridable_Prop_Type::ignore() ),
		);
	}

	/**
	 * Define atomic controls.
	 *
	 * @return array
	 */
	protected function define_atomic_controls(): array {
		return array(
			Section::make()
				->set_label( __( 'Content', 'thecore-collectivity-management' ) )
				->set_items(
					array(
						Select_Control::bind_to( 'content_type' )
							->set_label( __( 'Content type', 'thecore-collectivity-management' ) )
							->set_options(
								array(
									array(
										'value' => self::TYPE_REQUIRED_DOCUMENTS,
										'label' => __( 'Required documents', 'thecore-collectivity-management' ),
									),
									array(
										'value' => self::TYPE_STEPS,
										'label' => __( 'Steps', 'thecore-collectivity-management' ),
									),
									array(
										'value' => self::TYPE_CASES,
										'label' => __( 'Cases', 'thecore-collectivity-management' ),
									),
									array(
										'value' => self::TYPE_FAQ,
										'label' => __( 'FAQ', 'thecore-collectivity-management' ),
									),
									array(
										'value' => self::TYPE_RELATED_DOCUMENTS,
										'label' => __( 'Related documents', 'thecore-collectivity-management' ),
									),
								)
							),
						Switch_Control::bind_to( 'show_meta' )
							->set_label( __( 'Show document meta', 'thecore-collectivity-management' ) ),
						Text_Control::bind_to( 'post_id' )
							->set_label( __( 'Procedure ID', 'thecore-collectivity-management' ) )
							->set_placeholder( __( 'Leave empty for current procedure', 'thecore-collectivity-management' ) ),
					)
				),
			Section::make()
				->set_label( __( 'Settings', 'thecore-collectivity-management' ) )
				->set_id( 'settings' )
				->set_items(
					array(
						Text_Control::bind_to( '_cssid' )
							->set_label( __( 'ID', 'thecore-collectivity-management' ) )
							->set_meta( $this->get_css_id_control_meta() ),
					)
				),
		);
	}

	/**
	 * Render widget output.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_atomic_settings();
		$post_id  = $this->resolve_post_id( isset( $settings['post_id'] ) ? $settings['post_id'] : 0 );
		if ( $post_id <= 0 ) {
			return;
		}

		$content_type = isset( $settings['content_type'] ) ? (string) $settings['content_type'] : self::TYPE_REQUIRED_DOCUMENTS;
		$show_meta    = ! empty( $settings['show_meta'] );
		$html         = $this->render_content_block( $post_id, $content_type, $show_meta );

		if ( '' === $html ) {
			return;
		}

		$classes = array( 'tccm-proc-block', 'tccm-proc-block--' . sanitize_html_class( $content_type ) );
		if ( ! empty( $settings['classes'] ) && is_array( $settings['classes'] ) ) {
			$classes = array_merge( $classes, array_map( 'sanitize_html_class', $settings['classes'] ) );
		}

		$attributes = sprintf( 'class="%s"', esc_attr( implode( ' ', array_values( array_unique( array_filter( $classes ) ) ) ) ) );

		if ( ! empty( $settings['_cssid'] ) ) {
			$attributes .= ' id="' . esc_attr( $settings['_cssid'] ) . '"';
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div ' . $attributes . '>' . $html . '</div>';
	}

	/**
	 * Render one supported block.
	 *
	 * @param int    $post_id Procedure id.
	 * @param string $content_type Content block type.
	 * @param bool   $show_meta Whether related document meta should be displayed.
	 * @return string
	 */
	private function render_content_block( $post_id, $content_type, $show_meta ) {
		switch ( $content_type ) {
			case self::TYPE_REQUIRED_DOCUMENTS:
				return $this->render_list( $post_id, 'required_documents', 'ul' );

			case self::TYPE_STEPS:
				return $this->render_list( $post_id, 'steps', 'ol' );

			case self::TYPE_CASES:
				return $this->render_list( $post_id, 'cases', 'ul' );

			case self::TYPE_FAQ:
				return $this->render_faq( $post_id );

			case self::TYPE_RELATED_DOCUMENTS:
				return $this->render_related_documents( $post_id, $show_meta );
		}

		return '';
	}

	/**
	 * Render a line-based list.
	 *
	 * @param int    $post_id Procedure id.
	 * @param string $field Meta field slug.
	 * @param string $tag HTML tag.
	 * @return string
	 */
	private function render_list( $post_id, $field, $tag ) {
		$items = $this->get_list_values( $post_id, $field );
		if ( empty( $items ) ) {
			return '';
		}

		$html = '<' . $tag . ' class="tccm-procedure-list tccm-procedure-list--' . esc_attr( $field ) . '">';

		foreach ( $items as $item ) {
			$html .= '<li class="tccm-procedure-list__item">' . esc_html( $item ) . '</li>';
		}

		$html .= '</' . $tag . '>';

		return $html;
	}

	/**
	 * Render procedure FAQ.
	 *
	 * @param int $post_id Procedure id.
	 * @return string
	 */
	private function render_faq( $post_id ) {
		$rows = get_post_meta( $post_id, TheCore_Collectivity_Procedures_Meta::META_FAQ, true );
		$rows = is_array( $rows ) ? $rows : array();
		$rows = array_values(
			array_filter(
				$rows,
				static function( $row ) {
					return ! empty( $row['question'] ) || ! empty( $row['answer'] );
				}
			)
		);

		if ( empty( $rows ) ) {
			return '';
		}

		$html = '<div class="tccm-procedure-faq">';

		foreach ( $rows as $row ) {
			$question = isset( $row['question'] ) ? trim( (string) $row['question'] ) : '';
			$answer   = isset( $row['answer'] ) ? trim( (string) $row['answer'] ) : '';

			if ( '' === $question && '' === $answer ) {
				continue;
			}

			$html .= '<div class="tccm-procedure-faq__item">';
			if ( '' !== $question ) {
				$html .= '<div class="tccm-procedure-faq__question">' . esc_html( $question ) . '</div>';
			}
			if ( '' !== $answer ) {
				$html .= '<div class="tccm-procedure-faq__answer"><p>' . nl2br( esc_html( $answer ) ) . '</p></div>';
			}
			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render related documents cards.
	 *
	 * @param int  $post_id Procedure id.
	 * @param bool $show_meta Show reference and summary.
	 * @return string
	 */
	private function render_related_documents( $post_id, $show_meta ) {
		$documents = $this->procedures_repository->get_related_documents( $post_id );
		if ( empty( $documents ) ) {
			return '';
		}

		$html = '<div class="tccm-related-documents">';

		foreach ( $documents as $document ) {
			$url       = $this->documents_repository ? $this->documents_repository->get_document_url( $document->ID ) : '';
			$reference = class_exists( 'TheCore_Collectivity_Documents_Meta' ) ? (string) get_post_meta( $document->ID, TheCore_Collectivity_Documents_Meta::META_REFERENCE, true ) : '';
			$summary   = class_exists( 'TheCore_Collectivity_Documents_Meta' ) ? (string) get_post_meta( $document->ID, TheCore_Collectivity_Documents_Meta::META_SUMMARY, true ) : '';

			$html .= '<article class="tccm-related-documents__item">';
			if ( $url ) {
				$html .= '<a class="tccm-related-documents__link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( get_the_title( $document ) ) . '</a>';
			} else {
				$html .= '<span class="tccm-related-documents__title">' . esc_html( get_the_title( $document ) ) . '</span>';
			}

			if ( $show_meta && '' !== $reference ) {
				$html .= '<div class="tccm-related-documents__reference">' . esc_html( $reference ) . '</div>';
			}

			if ( $show_meta && '' !== $summary ) {
				$html .= '<div class="tccm-related-documents__summary">' . esc_html( $summary ) . '</div>';
			}

			$html .= '</article>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Get line repeater values.
	 *
	 * @param int    $post_id Procedure id.
	 * @param string $field Meta field slug.
	 * @return array
	 */
	private function get_list_values( $post_id, $field ) {
		$meta_key = '';

		switch ( $field ) {
			case 'required_documents':
				$meta_key = TheCore_Collectivity_Procedures_Meta::META_REQUIRED_DOCUMENTS;
				break;

			case 'steps':
				$meta_key = TheCore_Collectivity_Procedures_Meta::META_STEPS;
				break;

			case 'cases':
				$meta_key = TheCore_Collectivity_Procedures_Meta::META_CASES;
				break;
		}

		if ( '' === $meta_key ) {
			return array();
		}

		$values = get_post_meta( $post_id, $meta_key, true );
		$values = is_array( $values ) ? $values : array();

		return array_values(
			array_filter(
				array_map( 'trim', array_map( 'strval', $values ) )
			)
		);
	}

	/**
	 * Resolve current post id.
	 *
	 * @param int|string $post_id Optional explicit post id.
	 * @return int
	 */
	private function resolve_post_id( $post_id = 0 ) {
		$post_id = absint( $post_id );
		if ( $post_id > 0 ) {
			return $post_id;
		}

		$post_id = get_the_ID();
		if ( $post_id ) {
			return absint( $post_id );
		}

		$queried_id = get_queried_object_id();
		if ( $queried_id ) {
			return absint( $queried_id );
		}

		global $post;

		return ( $post instanceof WP_Post ) ? (int) $post->ID : 0;
	}
}
