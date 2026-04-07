<?php
/**
 * Standalone map filters Elementor widget.
 */

use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TheCore_Collectivity_Map_Filters_Widget extends TheCore_Collectivity_Map_Linked_Widget_Base {
	/**
	 * Get widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'tccm-map-filters';
	}

	/**
	 * Get widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Filtres de carte', 'thecore-collectivity-management' );
	}

	/**
	 * Get widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-filter';
	}

	/**
	 * Get keywords.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return array( 'map', 'carte', 'filtres', 'filter', 'leaflet' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => esc_html__( 'Contenu', 'thecore-collectivity-management' ),
			)
		);

		$this->register_group_id_control();

		$this->add_control(
			'show_search',
			array(
				'label'        => esc_html__( 'Afficher la recherche', 'thecore-collectivity-management' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Oui', 'thecore-collectivity-management' ),
				'label_off'    => esc_html__( 'Non', 'thecore-collectivity-management' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_filters',
			array(
				'label'        => esc_html__( 'Afficher les filtres', 'thecore-collectivity-management' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Oui', 'thecore-collectivity-management' ),
				'label_off'    => esc_html__( 'Non', 'thecore-collectivity-management' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_summary',
			array(
				'label'        => esc_html__( 'Afficher le compteur', 'thecore-collectivity-management' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Oui', 'thecore-collectivity-management' ),
				'label_off'    => esc_html__( 'Non', 'thecore-collectivity-management' ),
				'return_value' => 'yes',
				'default'      => 'yes',
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
		$group_id      = $group_context['normalized'];
		$widget_id     = 'tccm-map-filters-' . $this->get_id();

		$this->add_render_attribute(
			'wrapper',
			array(
				'class'          => 'tccm-map-filters',
				'id'             => $widget_id,
				'data-map-group' => $group_id,
			)
		);
		?>
		<div <?php echo $this->get_render_attribute_string( 'wrapper' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php $this->render_group_notice( $group_context['raw'] ); ?>

			<?php
			TheCore_Collectivity_Map_Widget::render_controls_markup(
				$widget_id,
				array(
					'group_id'     => $group_id,
					'show_search'  => isset( $settings['show_search'] ) && 'yes' === $settings['show_search'],
					'show_filters' => isset( $settings['show_filters'] ) && 'yes' === $settings['show_filters'],
					'show_summary' => isset( $settings['show_summary'] ) && 'yes' === $settings['show_summary'],
				)
			);
			?>
		</div>
		<?php
	}
}
