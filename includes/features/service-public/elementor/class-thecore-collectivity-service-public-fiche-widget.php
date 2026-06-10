<?php
/**
 * Service-public fiche Elementor widget.
 */

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TheCore_Collectivity_Service_Public_Fiche_Widget extends Widget_Base {
	public function get_name() {
		return 'tccm-service-public-fiche';
	}

	public function get_title() {
		return esc_html__( 'Service-public - Fiche', 'thecore-collectivity-management' );
	}

	public function get_icon() {
		return 'eicon-document-file';
	}

	public function get_categories() {
		return array( TheCore_Collectivity_Elementor::CATEGORY );
	}

	public function get_keywords() {
		return array( 'service-public', 'demarche', 'fiche', 'dila' );
	}

	public function get_style_depends() {
		return array( TheCore_Collectivity_Elementor::HANDLE_SERVICE_PUBLIC_STYLE );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => esc_html__( 'Contenu', 'thecore-collectivity-management' ),
			)
		);

		$this->add_control(
			'audience',
			array(
				'label'   => esc_html__( 'Audience', 'thecore-collectivity-management' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'particuliers',
				'options' => array(
					'particuliers'    => esc_html__( 'Particuliers', 'thecore-collectivity-management' ),
					'professionnels'  => esc_html__( 'Professionnels', 'thecore-collectivity-management' ),
				),
			)
		);

		$this->add_control(
			'item_id',
			array(
				'label'       => esc_html__( 'Identifiant de fiche', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'placeholder' => 'F10036',
			)
		);

		$this->add_control(
			'show_title',
			array(
				'label'        => esc_html__( 'Afficher le titre', 'thecore-collectivity-management' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_source',
			array(
				'label'        => esc_html__( 'Afficher la source', 'thecore-collectivity-management' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$item_id  = sanitize_text_field( $settings['item_id'] ?? '' );

		if ( '' === $item_id ) {
			echo '<div class="tccm-service-public tccm-service-public--empty"><p>' . esc_html__( 'Renseignez un identifiant de fiche Service-public.', 'thecore-collectivity-management' ) . '</p></div>';
			return;
		}

		$renderer = new TheCore_Collectivity_Service_Public_Renderer( new TheCore_Collectivity_Service_Public_Repository() );
		echo $renderer->render_item(
			sanitize_key( $settings['audience'] ?? 'particuliers' ),
			$item_id,
			array(
				'show_title'  => 'yes' === ( $settings['show_title'] ?? 'yes' ),
				'show_source' => 'yes' === ( $settings['show_source'] ?? 'yes' ),
			)
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
