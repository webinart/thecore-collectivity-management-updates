<?php
/**
 * Standalone map facet filters widget.
 */

use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TheCore_Collectivity_Map_Facets_Widget extends TheCore_Collectivity_Map_Linked_Widget_Base {
	/**
	 * Get widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'tccm-map-facets';
	}

	/**
	 * Get widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Catégories de carte', 'thecore-collectivity-management' );
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
		return array( 'map', 'carte', 'catégories', 'filtres', 'facets' );
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

		$options                 = TheCore_Collectivity_Map_Widget::get_filter_select_labels();
		$options['accessible']   = esc_html__( 'Accessible', 'thecore-collectivity-management' );

		$this->add_control(
			'visible_filters',
			array(
				'label'       => esc_html__( 'Filtres à afficher', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $options,
				'default'     => array_keys( $options ),
			)
		);

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
	}

	/**
	 * Render widget.
	 *
	 * @return void
	 */
	protected function render() {
		$settings         = $this->get_settings_for_display();
		$group_context    = $this->get_group_context( $settings );
		$widget_id        = 'tccm-map-facets-' . $this->get_id();
		$visible_filters  = array_values( array_filter( array_map( 'sanitize_key', (array) ( $settings['visible_filters'] ?? array() ) ) ) );
		$show_accessible  = in_array( 'accessible', $visible_filters, true );
		$facet_keys       = array_values( array_diff( $visible_filters, array( 'accessible' ) ) );

		$this->add_render_attribute(
			'wrapper',
			array(
				'class'          => 'tccm-map-filters tccm-map-filters--facets',
				'id'             => $widget_id,
				'data-map-group' => $group_context['normalized'],
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
					'show_types'      => false,
					'facet_keys'      => $facet_keys,
					'show_accessible' => $show_accessible,
					'show_reset'      => isset( $settings['include_reset'] ) && 'yes' === $settings['include_reset'],
					'show_summary'    => false,
				)
			);
			?>
		</div>
		<?php
	}
}
