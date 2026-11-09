<?php
/** Download or consultation URL in native Elementor links. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Document_Url_Tag extends \Elementor\Core\DynamicTags\Data_Tag {
	public function get_name() {
		return TheCore_Collectivity_Documents_Elementor::TAG_URL;
	}

	public function get_title() {
		return __( 'Document - Lien', 'thecore-collectivity-management' );
	}

	public function get_group() {
		return TheCore_Collectivity_Documents_Elementor::GROUP;
	}

	public function get_categories() {
		return array( 'url' );
	}

	public function get_editor_config() {
		$config = parent::get_editor_config();
		$config['display_conditions'] = ( $config['display_conditions'] ?? array() ) + array(
			TheCore_Collectivity_Documents_Elementor::CONDITION_SECONDARY_URL => array(
				'label'    => TheCore_Collectivity_Documents_Elementor::secondary_condition_label(),
				'settings' => array( 'field' => 'secondary_url' ),
				'group'    => TheCore_Collectivity_Documents_Elementor::GROUP,
			),
		);
		return $config;
	}

	protected function register_controls() {
		$this->add_control(
			'field',
			array(
				'label'   => __( 'Destination', 'thecore-collectivity-management' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'url'           => __( 'Fichier du document', 'thecore-collectivity-management' ),
					'reader_url'    => __( 'Consultation (ou fichier principal)', 'thecore-collectivity-management' ),
					'secondary_url' => __( 'Lien secondaire uniquement', 'thecore-collectivity-management' ),
				),
				'default' => 'url',
			)
		);
	}

	public function get_value( array $options = array() ) {
		return TheCore_Collectivity_Documents_Elementor::url_value( $this->get_settings( 'field' ) );
	}
}
