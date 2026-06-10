<?php
/**
 * Service-public search Elementor widget.
 */

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TheCore_Collectivity_Service_Public_Search_Widget extends Widget_Base {
	public function get_name() {
		return 'tccm-service-public-search';
	}

	public function get_title() {
		return esc_html__( 'Service-public - Recherche', 'thecore-collectivity-management' );
	}

	public function get_icon() {
		return 'eicon-search';
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
				'default' => '',
				'options' => array(
					''               => esc_html__( 'Toutes', 'thecore-collectivity-management' ),
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
				'default' => 20,
				'min'     => 1,
				'max'     => 100,
			)
		);
		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$renderer = new TheCore_Collectivity_Service_Public_Renderer( new TheCore_Collectivity_Service_Public_Repository() );
		echo $renderer->render_search( sanitize_key( $settings['audience'] ?? '' ), '', (int) ( $settings['limit'] ?? 20 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
