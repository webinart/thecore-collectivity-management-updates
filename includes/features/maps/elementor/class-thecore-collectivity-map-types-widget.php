<?php
/**
 * Standalone map geometry type filter widget.
 */

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TheCore_Collectivity_Map_Types_Widget extends TheCore_Collectivity_Map_Linked_Widget_Base {
	/**
	 * Get widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'tccm-map-types';
	}

	/**
	 * Get widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Types de résultats', 'thecore-collectivity-management' );
	}

	/**
	 * Get widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-post-list';
	}

	/**
	 * Get keywords.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return array( 'map', 'carte', 'lieux', 'circuits', 'transport', 'types' );
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => esc_html__( 'Contenu', 'thecore-collectivity-management' ),
			)
		);

		$this->register_group_id_control();

		$this->add_control(
			'include_reset',
			array(
				'label'        => esc_html__( 'Afficher le bouton réinitialiser', 'thecore-collectivity-management' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Oui', 'thecore-collectivity-management' ),
				'label_off'    => esc_html__( 'Non', 'thecore-collectivity-management' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			array(
				'label' => esc_html__( 'Style', 'thecore-collectivity-management' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'selector' => '{{WRAPPER}} .tccm-map__toggle, {{WRAPPER}} .tccm-map__reset',
			)
		);

		$this->add_control(
			'button_background_color',
			array(
				'label'     => esc_html__( 'Fond', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-toggle-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_border_color',
			array(
				'label'     => esc_html__( 'Bordure', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-toggle-border: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_text_color',
			array(
				'label'     => esc_html__( 'Texte', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-toggle-text: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_hover_heading',
			array(
				'label'     => esc_html__( 'Survol / focus', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'button_hover_background_color',
			array(
				'label'     => esc_html__( 'Fond', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-toggle-hover-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_hover_border_color',
			array(
				'label'     => esc_html__( 'Bordure', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-toggle-hover-border: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_hover_text_color',
			array(
				'label'     => esc_html__( 'Texte', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-toggle-hover-text: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_active_heading',
			array(
				'label'     => esc_html__( 'Actif', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'button_active_background_color',
			array(
				'label'     => esc_html__( 'Fond', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-toggle-active-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_active_border_color',
			array(
				'label'     => esc_html__( 'Bordure', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-toggle-active-border: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_active_text_color',
			array(
				'label'     => esc_html__( 'Texte', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-toggle-active-text: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget.
	 *
	 * @return void
	 */
	protected function render() {
		$settings      = $this->get_settings_for_display();
		$group_context = $this->get_group_context( $settings );
		$widget_id     = 'tccm-map-types-' . $this->get_id();
		$style_vars    = $this->get_style_variables( $settings );

		$this->add_render_attribute(
			'wrapper',
			array(
				'class'          => 'tccm-map-filters tccm-map-filters--types',
				'id'             => $widget_id,
				'data-map-group' => $group_context['normalized'],
				'style'          => ! empty( $style_vars ) ? implode( ';', $style_vars ) . ';' : '',
			)
		);
		?>
		<div <?php echo $this->get_render_attribute_string( 'wrapper' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php $this->render_group_notice( $group_context['raw'] ); ?>
			<?php
			TheCore_Collectivity_Map_Widget::render_controls_markup(
				$widget_id,
				array(
					'group_id'        => $group_context['normalized'],
					'show_search'     => false,
					'show_types'      => true,
					'facet_keys'      => array(),
					'show_accessible' => false,
					'show_reset'      => isset( $settings['include_reset'] ) && 'yes' === $settings['include_reset'],
					'show_summary'    => false,
				)
			);
			?>
		</div>
		<?php
	}

	/**
	 * Build inline CSS variables for the types widget controls.
	 *
	 * @param array $settings Widget settings.
	 * @return array
	 */
	private function get_style_variables( array $settings ) {
		$variables = array(
			'--tccm-map-toggle-bg'            => $settings['button_background_color'] ?? '',
			'--tccm-map-toggle-border'        => $settings['button_border_color'] ?? '',
			'--tccm-map-toggle-text'          => $settings['button_text_color'] ?? '',
			'--tccm-map-toggle-hover-bg'      => $settings['button_hover_background_color'] ?? '',
			'--tccm-map-toggle-hover-border'  => $settings['button_hover_border_color'] ?? '',
			'--tccm-map-toggle-hover-text'    => $settings['button_hover_text_color'] ?? '',
			'--tccm-map-toggle-active-bg'     => $settings['button_active_background_color'] ?? '',
			'--tccm-map-toggle-active-border' => $settings['button_active_border_color'] ?? '',
			'--tccm-map-toggle-active-text'   => $settings['button_active_text_color'] ?? '',
		);

		$declarations = array();
		foreach ( $variables as $name => $value ) {
			$sanitized = $this->sanitize_css_color_value( $value );
			if ( '' === $sanitized ) {
				continue;
			}

			$declarations[] = $name . ':' . $sanitized;
		}

		return $declarations;
	}

	/**
	 * Sanitize CSS color values for inline custom properties.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function sanitize_css_color_value( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		$hex = sanitize_hex_color( $value );
		if ( $hex ) {
			return $hex;
		}

		if ( preg_match( '/^(rgba?|hsla?)\\([\d\s.,%+-]+\)$/i', $value ) ) {
			return $value;
		}

		if ( preg_match( '/^var\\(\\s*--[a-z0-9_-]+(?:\\s*,\\s*[^)]+)?\\s*\\)$/i', $value ) ) {
			return $value;
		}

		return '';
	}
}
