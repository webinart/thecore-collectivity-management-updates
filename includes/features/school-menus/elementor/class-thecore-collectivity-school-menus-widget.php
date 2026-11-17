<?php
use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control;
use Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base;
use Elementor\Modules\AtomicWidgets\PropTypes\Attributes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Classes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;
use Elementor\Modules\Components\PropTypes\Overridable_Prop_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_School_Menus_Widget extends Atomic_Widget_Base {
	public static function get_element_type(): string { return 'tccm-school-menus'; }
	public function get_title(): string { return esc_html__( 'Menus scolaires', 'thecore-collectivity-management' ); }
	public function get_icon(): string { return 'eicon-table'; }
	public function get_categories(): array { return array( 'v4-elements', TheCore_Collectivity_Elementor::CATEGORY ); }
	public function get_style_depends() { return array( 'tccm-school-menus' ); }
	public function get_script_depends() { return array( 'tccm-school-menus' ); }
	protected static function define_props_schema(): array {
		return array( 'classes' => Classes_Prop_Type::make()->default( array() ), 'menu_id' => String_Prop_Type::make()->default( '' ), 'limit' => String_Prop_Type::make()->default( '8' ), 'attributes' => Attributes_Prop_Type::make()->meta( Overridable_Prop_Type::ignore() ) );
	}
	protected function define_atomic_controls(): array {
		return array( Section::make()->set_label( __( 'Menus scolaires', 'thecore-collectivity-management' ) )->set_items( array(
			Text_Control::bind_to( 'menu_id' )->set_label( __( 'ID de la semaine (facultatif)', 'thecore-collectivity-management' ) )->set_placeholder( __( 'Vide : dernières semaines publiées', 'thecore-collectivity-management' ) ),
			Text_Control::bind_to( 'limit' )->set_label( __( 'Nombre de semaines (1 à 52)', 'thecore-collectivity-management' ) ),
		) ) );
	}
	protected function render() {
		$settings = $this->get_atomic_settings();
		echo TheCore_Collectivity_School_Menus_Renderer::render( absint( $settings['menu_id'] ?? 0 ), max( 1, min( 52, (int) ( $settings['limit'] ?? 8 ) ) ) );
	}
}
