<?php
/**
 * Atomic Elementor widget for event date badges.
 */

use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control;
use Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base;
use Elementor\Modules\AtomicWidgets\PropTypes\Attributes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Classes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;
use Elementor\Modules\Components\PropTypes\Overridable_Prop_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Event_Date_Widget extends Atomic_Widget_Base {
	/**
	 * Element type.
	 *
	 * @return string
	 */
	public static function get_element_type(): string {
		return 'tccm-event-date';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return esc_html__( 'Event Date', 'thecore-collectivity-management' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-calendar';
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
		return array( 'event', 'date', 'agenda', 'atomic' );
	}

	/**
	 * Define props schema.
	 *
	 * @return array
	 */
	protected static function define_props_schema(): array {
		return array(
			'classes'    => Classes_Prop_Type::make()->default( array() ),
			'format'     => String_Prop_Type::make()
				->default( 'parts' )
				->enum( array( 'default', 'parts' ) ),
			'post_id'    => String_Prop_Type::make()->default( '' ),
			'attributes' => Attributes_Prop_Type::make()->meta( Overridable_Prop_Type::ignore() ),
		);
	}

	/**
	 * Define controls.
	 *
	 * @return array
	 */
	protected function define_atomic_controls(): array {
		return array(
			Section::make()
				->set_label( __( 'Content', 'thecore-collectivity-management' ) )
				->set_items(
					array(
						Select_Control::bind_to( 'format' )
							->set_label( __( 'Format', 'thecore-collectivity-management' ) )
							->set_options(
								array(
									array(
										'value' => 'parts',
										'label' => __( 'Parts', 'thecore-collectivity-management' ),
									),
									array(
										'value' => 'default',
										'label' => __( 'Default', 'thecore-collectivity-management' ),
									),
								)
							),
						Text_Control::bind_to( 'post_id' )
							->set_label( __( 'Post ID', 'thecore-collectivity-management' ) )
							->set_placeholder( __( 'Leave empty for current event', 'thecore-collectivity-management' ) ),
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

		$format = isset( $settings['format'] ) && 'default' === $settings['format'] ? 'default' : 'parts';
		$html   = do_shortcode(
			sprintf(
				'[bellevue_event_date post_id="%1$d" format="%2$s"]',
				$post_id,
				$format
			)
		);

		if ( '' === trim( $html ) ) {
			return;
		}

		$classes = array( 'tccm-evt-date' );
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
