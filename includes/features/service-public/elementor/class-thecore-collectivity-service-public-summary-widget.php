<?php
/**
 * Service-public summary Elementor widget.
 */

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TheCore_Collectivity_Service_Public_Summary_Widget extends Widget_Base {
	public function get_name() {
		return 'tccm-service-public-summary';
	}

	public function get_title() {
		return esc_html__( 'Service-public - Sommaire', 'thecore-collectivity-management' );
	}

	public function get_icon() {
		return 'eicon-post-list';
	}

	public function get_categories() {
		return array( TheCore_Collectivity_Elementor::CATEGORY );
	}

	public function get_style_depends() {
		return array( TheCore_Collectivity_Elementor::HANDLE_SERVICE_PUBLIC_STYLE );
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_content', array( 'label' => esc_html__( 'Contenu', 'thecore-collectivity-management' ) ) );
		$this->add_control(
			'audience',
			array(
				'label'   => esc_html__( 'Audience', 'thecore-collectivity-management' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'particuliers',
				'options' => array(
					'particuliers'   => esc_html__( 'Particuliers', 'thecore-collectivity-management' ),
					'professionnels' => esc_html__( 'Professionnels', 'thecore-collectivity-management' ),
				),
			)
		);
		$this->add_control(
			'limit',
			array(
				'label'   => esc_html__( 'Nombre maximum', 'thecore-collectivity-management' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 24,
				'min'     => 1,
				'max'     => 200,
			)
		);
		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$renderer = new TheCore_Collectivity_Service_Public_Renderer( new TheCore_Collectivity_Service_Public_Repository() );
		echo $renderer->render_summary( sanitize_key( $settings['audience'] ?? 'particuliers' ), (int) ( $settings['limit'] ?? 24 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
