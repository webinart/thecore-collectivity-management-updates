<?php
/**
 * Shared base for map-linked Elementor widgets.
 */

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class TheCore_Collectivity_Map_Linked_Widget_Base extends Widget_Base {
	/**
	 * Get widget category.
	 *
	 * @return array
	 */
	public function get_categories() {
		return array( TheCore_Collectivity_Elementor::CATEGORY );
	}

	/**
	 * Get shared style dependencies.
	 *
	 * @return array
	 */
	public function get_style_depends() {
		return array(
			TheCore_Collectivity_Elementor::HANDLE_MAP_STYLE,
			'elementor-icons',
			'elementor-icons-fa-solid',
			'elementor-icons-fa-regular',
			'elementor-icons-fa-brands',
		);
	}

	/**
	 * Get shared script dependencies.
	 *
	 * @return array
	 */
	public function get_script_depends() {
		return array( TheCore_Collectivity_Elementor::HANDLE_MAP_SCRIPT );
	}

	/**
	 * Register common group id control.
	 *
	 * @return void
	 */
	protected function register_group_id_control() {
		$this->add_control(
			'map_group_id',
			array(
				'label'       => esc_html__( 'Identifiant de groupe', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'description' => esc_html__( 'Utilisez exactement le même identifiant que sur le widget Carte à piloter.', 'thecore-collectivity-management' ),
			)
		);
	}

	/**
	 * Normalize the configured map group id.
	 *
	 * @param array $settings Widget settings.
	 * @return array
	 */
	protected function get_group_context( array $settings ) {
		$raw_group_id = isset( $settings['map_group_id'] ) ? (string) $settings['map_group_id'] : '';

		return array(
			'raw'        => $raw_group_id,
			'normalized' => TheCore_Collectivity_Map_Widget::normalize_map_group_id( $raw_group_id, $this->get_id() ),
		);
	}

	/**
	 * Render a notice in Elementor when no explicit group id is set.
	 *
	 * @param string $raw_group_id Raw configured group id.
	 * @return void
	 */
	protected function render_group_notice( $raw_group_id ) {
		if ( '' !== sanitize_key( (string) $raw_group_id ) || ! $this->is_edit_mode() ) {
			return;
		}
		?>
		<p class="tccm-map-filters__notice"><?php esc_html_e( 'Renseignez le même identifiant de groupe que sur le widget Carte pour relier les filtres.', 'thecore-collectivity-management' ); ?></p>
		<?php
	}

	/**
	 * Determine if widget is rendered in Elementor editor or preview.
	 *
	 * @return bool
	 */
	protected function is_edit_mode() {
		if ( wp_doing_ajax() && ! empty( $_POST['action'] ) ) {
			$action = sanitize_key( wp_unslash( $_POST['action'] ) );
			if ( 'elementor_ajax' === $action || 'elementor_render_widget' === $action ) {
				return true;
			}
		}

		if ( is_admin() && ! empty( $_GET['action'] ) ) {
			$action = sanitize_key( wp_unslash( $_GET['action'] ) );
			if ( 'elementor' === $action ) {
				return true;
			}
		}

		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}

		$plugin = \Elementor\Plugin::$instance;

		return $plugin->editor->is_edit_mode() || $plugin->preview->is_preview_mode();
	}
}
