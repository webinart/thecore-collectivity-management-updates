<?php
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

final class TheCore_Collectivity_Event_Programme_Widget extends Atomic_Widget_Base {
	public static function get_element_type(): string { return 'tccm-event-programme'; }
	public function get_title(): string { return esc_html__( 'Programme de l’événement', 'thecore-collectivity-management' ); }
	public function get_icon(): string { return 'eicon-post-list'; }
	public function get_categories(): array { return array( 'v4-elements', TheCore_Collectivity_Elementor::CATEGORY ); }
	public function get_keywords() { return array( 'programme', 'agenda', 'event', 'horaires', 'collectivite' ); }
	public function get_style_depends() { return array( TheCore_Collectivity_Event_Programme::STYLE ); }

	protected static function define_props_schema(): array {
		return array(
			'classes' => Classes_Prop_Type::make()->default( array() ),
			'post_id' => String_Prop_Type::make()->default( '' ),
			'title' => String_Prop_Type::make()->default( __( 'Programme', 'thecore-collectivity-management' ) ),
			'heading' => String_Prop_Type::make()->default( 'h2' )->enum( array( 'h2', 'h3', 'h4' ) ),
			'attributes' => Attributes_Prop_Type::make()->meta( Overridable_Prop_Type::ignore() ),
		);
	}

	protected function define_atomic_controls(): array {
		return array(
			Section::make()->set_label( __( 'Programme', 'thecore-collectivity-management' ) )->set_items( array(
				Text_Control::bind_to( 'title' )->set_label( __( 'Titre', 'thecore-collectivity-management' ) ),
				Text_Control::bind_to( 'post_id' )->set_label( __( 'ID de l’événement', 'thecore-collectivity-management' ) )->set_placeholder( __( 'Vide : événement courant', 'thecore-collectivity-management' ) ),
				Select_Control::bind_to( 'heading' )->set_label( __( 'Niveau du titre', 'thecore-collectivity-management' ) )->set_options( array(
					array( 'value' => 'h2', 'label' => 'H2' ), array( 'value' => 'h3', 'label' => 'H3' ), array( 'value' => 'h4', 'label' => 'H4' ),
				) ),
			) ),
			Section::make()->set_label( __( 'Réglages', 'thecore-collectivity-management' ) )->set_id( 'settings' )->set_items( array(
				Text_Control::bind_to( '_cssid' )->set_label( __( 'ID', 'thecore-collectivity-management' ) )->set_meta( $this->get_css_id_control_meta() ),
			) ),
		);
	}

	protected function render() {
		$settings = $this->get_atomic_settings();
		$post_id = absint( $settings['post_id'] ?? 0 );
		if ( ! $post_id ) {
			foreach ( array( get_the_ID(), get_queried_object_id() ) as $candidate ) {
				if ( 'post' === get_post_type( $candidate ) ) {
					$post_id = (int) $candidate;
					break;
				}
			}
		}
		if ( ! $post_id && class_exists( '\\Elementor\\Plugin' ) ) {
			$document = \Elementor\Plugin::$instance->documents->get_current();
			if ( $document ) {
				$post_id = absint( $document->get_settings( 'preview_id' ) );
			}
		}
		echo TheCore_Collectivity_Event_Programme_Renderer::render( $post_id, $settings['title'] ?? __( 'Programme', 'thecore-collectivity-management' ),
			$settings['heading'] ?? 'h2', $settings['classes'] ?? array(), $settings['_cssid'] ?? '' );
	}
}
