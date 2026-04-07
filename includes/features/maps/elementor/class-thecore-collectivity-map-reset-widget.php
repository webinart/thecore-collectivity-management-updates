<?php
/**
 * Standalone map reset Elementor widget.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TheCore_Collectivity_Map_Reset_Widget extends TheCore_Collectivity_Map_Linked_Widget_Base {
	/**
	 * Get widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'tccm-map-reset';
	}

	/**
	 * Get widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Réinitialiser la carte', 'thecore-collectivity-management' );
	}

	/**
	 * Get widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-reset';
	}

	/**
	 * Get keywords.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return array( 'map', 'carte', 'reset', 'réinitialiser' );
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
		$widget_id     = 'tccm-map-reset-' . $this->get_id();

		$this->add_render_attribute(
			'wrapper',
			array(
				'class'          => 'tccm-map-filters tccm-map-filters--reset',
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
					'facet_keys'      => array(),
					'show_accessible' => false,
					'show_reset'      => true,
					'show_summary'    => false,
				)
			);
			?>
		</div>
		<?php
	}
}
