<?php
/** Document scalar values in native Elementor text elements. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Document_Field_Tag extends \Elementor\Core\DynamicTags\Tag {
	public function get_name() {
		return TheCore_Collectivity_Documents_Elementor::TAG_FIELD;
	}

	public function get_title() {
		return __( 'Document - Champ', 'thecore-collectivity-management' );
	}

	public function get_group() {
		return TheCore_Collectivity_Documents_Elementor::GROUP;
	}

	public function get_categories() {
		return array( 'text' );
	}

	protected function register_controls() {
		$this->add_control(
			'field',
			array(
				'label'   => __( 'Champ', 'thecore-collectivity-management' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => TheCore_Collectivity_Documents_Elementor::text_fields(),
				'default' => 'title',
			)
		);
		$this->add_control(
			'date_format',
			array(
				'label'       => __( 'Format de date', 'thecore-collectivity-management' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'description' => __( 'Vide : format defini dans les reglages WordPress.', 'thecore-collectivity-management' ),
				'condition'   => array( 'field' => array( 'effective_date', 'updated_at' ) ),
			)
		);
	}

	public function render() {
		echo esc_html( TheCore_Collectivity_Documents_Elementor::text_value( $this->get_settings( 'field' ), $this->get_settings( 'date_format' ) ) );
	}
}
