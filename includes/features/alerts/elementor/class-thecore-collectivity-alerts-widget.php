<?php
/**
 * Alerts Elementor widget.
 */

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TheCore_Collectivity_Alerts_Widget extends Widget_Base {
	/**
	 * Get widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'bellevue-alerts';
	}

	/**
	 * Get widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Alerts', 'bellevue' );
	}

	/**
	 * Get widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-alert';
	}

	/**
	 * Get categories.
	 *
	 * @return array
	 */
	public function get_categories() {
		return array( TheCore_Collectivity_Elementor::CATEGORY );
	}

	/**
	 * Get style dependencies.
	 *
	 * @return array
	 */
	public function get_style_depends() {
		return array( TheCore_Collectivity_Elementor::HANDLE_ALERTS_STYLE );
	}

	/**
	 * Register controls.
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => esc_html__( 'Contenu', 'bellevue' ),
			)
		);

		$this->add_control(
			'topics',
			array(
				'label'       => esc_html__( 'Themes', 'bellevue' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->get_topic_options(),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget.
	 */
	protected function render() {
		$settings      = $this->get_settings_for_display();
		$topics        = isset( $settings['topics'] ) ? (array) $settings['topics'] : array();
		$alerts_module = TheCore_Collectivity_Management::instance()->get_alerts_module();
		$alerts        = $alerts_module->get_repository()->get_active_alerts( $topics );

		if ( empty( $alerts ) ) {
			if ( $this->is_edit_mode() ) {
				echo '<div class="bellevue-alerts bellevue-alerts--empty"><div class="bellevue-alerts__item bellevue-alerts__item--info"><div class="bellevue-alerts__content"><p class="bellevue-alerts__message">' . esc_html__( 'Aucune alerte active pour cette configuration.', 'bellevue' ) . '</p></div></div></div>';
			}
			return;
		}

		echo $alerts_module->get_renderer()->render_banner_list( $alerts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Get topic options.
	 *
	 * @return array
	 */
	private function get_topic_options() {
		$terms   = get_terms(
			array(
				'taxonomy'   => TheCore_Collectivity_Alerts_Post_Type::TAXONOMY_TOPIC,
				'hide_empty' => false,
			)
		);
		$options = array();

		if ( is_wp_error( $terms ) ) {
			return $options;
		}

		foreach ( $terms as $term ) {
			$options[ $term->slug ] = $term->name;
		}

		return $options;
	}

	/**
	 * Determine if widget is rendered in Elementor editor.
	 *
	 * @return bool
	 */
	private function is_edit_mode() {
		return class_exists( '\\Elementor\\Plugin' ) && \Elementor\Plugin::$instance->editor->is_edit_mode();
	}
}
