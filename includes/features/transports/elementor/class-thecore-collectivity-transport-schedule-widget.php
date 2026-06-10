<?php
/**
 * Standalone transport schedule Elementor widget.
 */

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TheCore_Collectivity_Transport_Schedule_Widget extends Widget_Base {
	/**
	 * Get widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'tccm-transport-schedule';
	}

	/**
	 * Get widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Horaires transport', 'bellevue' );
	}

	/**
	 * Get widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-clock-o';
	}

	/**
	 * Get widget categories.
	 *
	 * @return array
	 */
	public function get_categories() {
		return array( TheCore_Collectivity_Elementor::CATEGORY );
	}

	/**
	 * Get keywords.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return array( 'transport', 'horaire', 'bus', 'temps reel', 'bellevue' );
	}

	/**
	 * Get style dependencies.
	 *
	 * @return array
	 */
	public function get_style_depends() {
		return array( TheCore_Collectivity_Elementor::HANDLE_TRANSPORT_STYLE );
	}

	/**
	 * Get script dependencies.
	 *
	 * @return array
	 */
	public function get_script_depends() {
		return array( TheCore_Collectivity_Elementor::HANDLE_TRANSPORT_SCRIPT );
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
				'label' => esc_html__( 'Contenu', 'bellevue' ),
			)
		);

		$this->add_control(
			'map_group_id',
			array(
				'label'       => esc_html__( 'Identifiant de groupe', 'bellevue' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'description' => esc_html__( 'Renseignez le même identifiant que sur la carte MAP pour synchroniser les clics sur les transports.', 'bellevue' ),
			)
		);

		$this->add_control(
			'default_line_id',
			array(
				'label'       => esc_html__( 'Ligne par défaut', 'bellevue' ),
				'type'        => Controls_Manager::SELECT2,
				'label_block' => true,
				'options'     => $this->get_line_options(),
				'description' => esc_html__( 'Si vide, la première ligne disponible est utilisée.', 'bellevue' ),
			)
		);

		$this->add_control(
			'initial_open',
			array(
				'label'        => esc_html__( 'Afficher une ligne au chargement', 'bellevue' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Oui', 'bellevue' ),
				'label_off'    => esc_html__( 'Non', 'bellevue' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'Si désactivé, le bloc reste en attente jusqu’à un clic sur la carte ou une ouverture manuelle.', 'bellevue' ),
			)
		);

		$this->add_control(
			'show_map_focus_button',
			array(
				'label'        => esc_html__( 'Afficher le bouton Voir sur la carte', 'bellevue' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Oui', 'bellevue' ),
				'label_off'    => esc_html__( 'Non', 'bellevue' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => esc_html__( 'Disponible quand le widget est lié à une MAP via le même identifiant de groupe.', 'bellevue' ),
			)
		);

		$this->add_control(
			'schedule_display_mode',
			array(
				'label'   => esc_html__( 'Informations affichées', 'bellevue' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'both',
				'options' => array(
					'both'     => esc_html__( 'Horaires théoriques + temps réel', 'bellevue' ),
					'schedule' => esc_html__( 'Horaires théoriques uniquement', 'bellevue' ),
					'realtime' => esc_html__( 'Temps réel uniquement', 'bellevue' ),
				),
			)
		);

		$this->add_control(
			'map_direction_branch',
			array(
				'label'        => esc_html__( 'Limiter la carte à la direction sélectionnée', 'bellevue' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Oui', 'bellevue' ),
				'label_off'    => esc_html__( 'Non', 'bellevue' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'Si activé, la MAP liée peut n’afficher que la branche GTFS correspondant à la direction choisie.', 'bellevue' ),
			)
		);

		$this->add_control(
			'show_progress',
			array(
				'label'        => esc_html__( 'Afficher la progression', 'bellevue' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Oui', 'bellevue' ),
				'label_off'    => esc_html__( 'Non', 'bellevue' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'show_nearest_stop_button',
			array(
				'label'        => esc_html__( 'Proposer l’arrêt le plus proche', 'bellevue' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Oui', 'bellevue' ),
				'label_off'    => esc_html__( 'Non', 'bellevue' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'Ajoute un bouton utilisant la géolocalisation du navigateur pour choisir l’arrêt suivi le plus proche.', 'bellevue' ),
			)
		);

		$this->add_control(
			'realtime_auto_refresh',
			array(
				'label'        => esc_html__( 'Actualiser le temps réel automatiquement', 'bellevue' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Oui', 'bellevue' ),
				'label_off'    => esc_html__( 'Non', 'bellevue' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'realtime_refresh_interval',
			array(
				'label'     => esc_html__( 'Intervalle de rafraîchissement (secondes)', 'bellevue' ),
				'type'      => Controls_Manager::NUMBER,
				'min'       => 15,
				'max'       => 300,
				'step'      => 5,
				'default'   => 30,
				'condition' => array(
					'realtime_auto_refresh' => 'yes',
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
		$settings                  = $this->get_settings_for_display();
		$transports                = TheCore_Collectivity_Management::instance()->get_transports_module();
		$payload                   = $transports->get_normalizer()->get_widget_payload();
		$default_line_id           = ! empty( $settings['default_line_id'] ) ? absint( $settings['default_line_id'] ) : $this->get_first_line_id( $payload );
		$group_id                  = ! empty( $settings['map_group_id'] ) ? sanitize_key( (string) $settings['map_group_id'] ) : '';
		$initial_open              = isset( $settings['initial_open'] ) && 'yes' === $settings['initial_open'];
		$show_map_focus_button     = isset( $settings['show_map_focus_button'] ) && 'yes' === $settings['show_map_focus_button'];
		$schedule_display_mode     = ! empty( $settings['schedule_display_mode'] ) ? sanitize_key( (string) $settings['schedule_display_mode'] ) : 'both';
		$schedule_display_mode     = in_array( $schedule_display_mode, array( 'both', 'schedule', 'realtime' ), true ) ? $schedule_display_mode : 'both';
		$map_direction_branch      = isset( $settings['map_direction_branch'] ) && 'yes' === $settings['map_direction_branch'];
		$show_progress             = isset( $settings['show_progress'] ) && 'yes' === $settings['show_progress'];
		$show_nearest_stop_button  = isset( $settings['show_nearest_stop_button'] ) && 'yes' === $settings['show_nearest_stop_button'];
		$realtime_auto_refresh     = isset( $settings['realtime_auto_refresh'] ) && 'yes' === $settings['realtime_auto_refresh'];
		$realtime_refresh_interval = isset( $settings['realtime_refresh_interval'] ) ? max( 15, intval( $settings['realtime_refresh_interval'] ) ) : 30;
		$widget_id                 = 'bte-schedule-' . $this->get_id();

		$this->add_render_attribute(
			'wrapper',
			array_filter(
				array(
					'class'                            => 'bte bte--schedule-widget',
					'id'                               => $widget_id,
					'data-schedule-only'               => '1',
					'data-default-line-id'             => $default_line_id > 0 ? (string) $default_line_id : '',
					'data-initial-open'                => $initial_open ? '1' : '0',
					'data-schedule-can-close'          => '1',
					'data-show-map-focus-button'       => $show_map_focus_button ? '1' : '0',
					'data-schedule-display-mode'       => $schedule_display_mode,
					'data-map-direction-branch'        => $map_direction_branch ? '1' : '0',
					'data-show-progress'               => $show_progress ? '1' : '0',
					'data-show-nearest-stop-button'    => $show_nearest_stop_button ? '1' : '0',
					'data-map-group'                   => $group_id,
					'data-realtime-refresh-enabled'    => $realtime_auto_refresh ? '1' : '0',
					'data-realtime-refresh-interval'   => (string) $realtime_refresh_interval,
					'data-realtime-refresh-endpoint'   => esc_url_raw( rest_url( 'thecore-collectivity/v1/transports/widget-realtime' ) ),
					'data-show-all-route-vehicles-on-map' => '1',
				),
				static function ( $value ) {
					return null !== $value && '' !== $value;
				}
			)
		);
		?>
		<div <?php echo $this->get_render_attribute_string( 'wrapper' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<script type="application/json" class="bte__data"><?php echo wp_json_encode( $payload ); ?></script>
			<div class="bte__schedule-placeholder" data-schedule-placeholder hidden></div>
			<div class="bte__line-detail" data-line-detail="bus" hidden></div>
		</div>
		<?php
	}

	/**
	 * Build Elementor options for transport lines.
	 *
	 * @return array
	 */
	private function get_line_options() {
		$module = TheCore_Collectivity_Management::instance()->get_transports_module();
		if ( ! $module ) {
			return array();
		}

		$options = array();
		foreach ( $module->get_repository()->get_lines() as $line ) {
			$code  = get_post_meta( $line->ID, TheCore_Collectivity_Transports_Meta::META_LINE_CODE, true );
			$title = get_the_title( $line );
			$label = trim( implode( ' - ', array_filter( array( $code, $title ) ) ) );
			$options[ $line->ID ] = $label ?: sprintf(
				/* translators: %d: line post id */
				__( 'Ligne #%d', 'bellevue' ),
				$line->ID
			);
		}

		return $options;
	}

	/**
	 * Resolve the first available line id from a widget payload.
	 *
	 * @param array $payload Widget payload.
	 * @return int
	 */
	private function get_first_line_id( array $payload ) {
		$lines = ! empty( $payload['lines'] ) && is_array( $payload['lines'] ) ? $payload['lines'] : array();
		$first = reset( $lines );

		return ! empty( $first['id'] ) ? absint( $first['id'] ) : 0;
	}
}
