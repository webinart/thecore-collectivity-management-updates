<?php
/**
 * Transport explorer Elementor widget.
 */

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TheCore_Collectivity_Transport_Explorer_Widget extends Widget_Base {
	/**
	 * Get widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'bellevue-transport-explorer';
	}

	/**
	 * Get widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Transport Explorer', 'bellevue' );
	}

	/**
	 * Get widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-site-search';
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
		return array( 'transport', 'mobilite', 'map', 'carte', 'bellevue' );
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
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => esc_html__( 'Contenu', 'bellevue' ),
			)
		);

		$this->add_control(
			'title',
			array(
				'label'       => esc_html__( 'Titre', 'bellevue' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Transports & Mobilites', 'bellevue' ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'description',
			array(
				'label'       => esc_html__( 'Description', 'bellevue' ),
				'type'        => Controls_Manager::TEXTAREA,
				'default'     => esc_html__( 'Trouvez toutes les informations sur les transports en commun, le stationnement et les mobilites douces a Bellevue.', 'bellevue' ),
				'rows'        => 3,
			)
		);

		$this->add_control(
			'show_alerts',
			array(
				'label'        => esc_html__( 'Afficher les alertes transport', 'bellevue' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Oui', 'bellevue' ),
				'label_off'    => esc_html__( 'Non', 'bellevue' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_all_route_vehicles_on_map',
			array(
				'label'        => esc_html__( 'Afficher tous les véhicules de la ligne sur la carte', 'bellevue' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Oui', 'bellevue' ),
				'label_off'    => esc_html__( 'Non', 'bellevue' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => esc_html__( 'Si non, la carte n affiche que les véhicules dont le trip dessert au moins un arrêt suivi localement pour la ligne.', 'bellevue' ),
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
				'label'       => esc_html__( 'Intervalle de rafraîchissement (secondes)', 'bellevue' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 15,
				'max'         => 300,
				'step'        => 5,
				'default'     => 30,
				'condition'   => array(
					'realtime_auto_refresh' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'map_height',
			array(
				'label'      => esc_html__( 'Hauteur carte', 'bellevue' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 280,
						'max' => 720,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 420,
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_realtime_vehicle_style',
			array(
				'label' => esc_html__( 'Véhicules temps réel', 'bellevue' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'realtime_vehicle_pulse',
			array(
				'label'        => esc_html__( 'Animation du véhicule', 'bellevue' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Oui', 'bellevue' ),
				'label_off'    => esc_html__( 'Non', 'bellevue' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'realtime_vehicle_background',
			array(
				'label' => esc_html__( 'Fond', 'bellevue' ),
				'type'  => Controls_Manager::COLOR,
			)
		);

		$this->add_control(
			'realtime_vehicle_border',
			array(
				'label' => esc_html__( 'Contour', 'bellevue' ),
				'type'  => Controls_Manager::COLOR,
			)
		);

		$this->add_control(
			'realtime_vehicle_icon_color',
			array(
				'label' => esc_html__( 'Couleur de l’icône', 'bellevue' ),
				'type'  => Controls_Manager::COLOR,
			)
		);

		$this->add_control(
			'realtime_vehicle_pulse_color',
			array(
				'label' => esc_html__( 'Couleur de l’animation', 'bellevue' ),
				'type'  => Controls_Manager::COLOR,
				'condition' => array(
					'realtime_vehicle_pulse' => 'yes',
				),
			)
		);

		$this->add_control(
			'realtime_vehicle_size',
			array(
				'label'      => esc_html__( 'Taille du marqueur', 'bellevue' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 18,
						'max' => 48,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 30,
				),
			)
		);

		$this->add_control(
			'realtime_vehicle_icon_size',
			array(
				'label'      => esc_html__( 'Taille de l’icône', 'bellevue' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 10,
						'max' => 24,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 14,
				),
			)
		);

		$this->add_control(
			'realtime_vehicle_pulse_duration',
			array(
				'label'      => esc_html__( 'Durée de l’animation', 'bellevue' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's' ),
				'range'      => array(
					's' => array(
						'min'  => 0.8,
						'max'  => 3,
						'step' => 0.1,
					),
				),
				'default'    => array(
					'unit' => 's',
					'size' => 1.4,
				),
				'condition' => array(
					'realtime_vehicle_pulse' => 'yes',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget.
	 */
	protected function render() {
		$settings         = $this->get_settings_for_display();
		$title            = ! empty( $settings['title'] ) ? (string) $settings['title'] : __( 'Transports & Mobilites', 'bellevue' );
		$description      = ! empty( $settings['description'] ) ? (string) $settings['description'] : '';
		$show_alerts      = isset( $settings['show_alerts'] ) && 'yes' === $settings['show_alerts'];
		$show_all_route_vehicles_on_map = isset( $settings['show_all_route_vehicles_on_map'] ) && 'yes' === $settings['show_all_route_vehicles_on_map'];
		$realtime_auto_refresh          = isset( $settings['realtime_auto_refresh'] ) && 'yes' === $settings['realtime_auto_refresh'];
		$realtime_refresh_interval      = isset( $settings['realtime_refresh_interval'] ) ? max( 15, intval( $settings['realtime_refresh_interval'] ) ) : 30;
		$map_height       = isset( $settings['map_height']['size'] ) ? absint( $settings['map_height']['size'] ) : 420;
		$vehicle_pulse    = isset( $settings['realtime_vehicle_pulse'] ) && 'yes' === $settings['realtime_vehicle_pulse'];
		$widget_id        = 'bte-' . $this->get_id();
		$transports       = TheCore_Collectivity_Management::instance()->get_transports_module();
		$alerts_module    = TheCore_Collectivity_Management::instance()->get_alerts_module();
		$payload          = $transports->get_normalizer()->get_widget_payload();
		$transport_alerts = $show_alerts ? $alerts_module->get_repository()->get_active_alerts( TheCore_Collectivity_Alerts_Post_Type::TOPIC_TRANSPORT ) : array();
		$alerts_markup    = $show_alerts ? $alerts_module->get_renderer()->render_banner_list( $transport_alerts, array( 'wrapper_class' => 'bte__alerts' ) ) : '';

		$wrapper_styles = array(
			'--bte-map-height:' . max( 280, $map_height ) . 'px',
		);

		if ( ! empty( $settings['realtime_vehicle_background'] ) ) {
			$wrapper_styles[] = '--bte-vehicle-marker-bg:' . (string) $settings['realtime_vehicle_background'];
		}

		if ( ! empty( $settings['realtime_vehicle_border'] ) ) {
			$wrapper_styles[] = '--bte-vehicle-marker-border:' . (string) $settings['realtime_vehicle_border'];
		}

		if ( ! empty( $settings['realtime_vehicle_icon_color'] ) ) {
			$wrapper_styles[] = '--bte-vehicle-marker-icon-color:' . (string) $settings['realtime_vehicle_icon_color'];
		}

		if ( ! empty( $settings['realtime_vehicle_pulse_color'] ) ) {
			$wrapper_styles[] = '--bte-vehicle-pulse-color:' . (string) $settings['realtime_vehicle_pulse_color'];
		}

		if ( ! empty( $settings['realtime_vehicle_size']['size'] ) ) {
			$wrapper_styles[] = '--bte-vehicle-marker-size:' . floatval( $settings['realtime_vehicle_size']['size'] ) . 'px';
		}

		if ( ! empty( $settings['realtime_vehicle_icon_size']['size'] ) ) {
			$wrapper_styles[] = '--bte-vehicle-icon-size:' . floatval( $settings['realtime_vehicle_icon_size']['size'] ) . 'px';
		}

		if ( ! empty( $settings['realtime_vehicle_pulse_duration']['size'] ) ) {
			$wrapper_styles[] = '--bte-vehicle-pulse-duration:' . floatval( $settings['realtime_vehicle_pulse_duration']['size'] ) . 's';
		}

		$this->add_render_attribute(
			'wrapper',
			array(
				'class' => array_filter(
					array(
						'bte',
						$vehicle_pulse ? 'bte--vehicle-pulse' : '',
					)
				),
				'id'    => $widget_id,
				'data-show-all-route-vehicles-on-map' => $show_all_route_vehicles_on_map ? '1' : '0',
				'data-realtime-refresh-enabled'       => $realtime_auto_refresh ? '1' : '0',
				'data-realtime-refresh-interval'      => (string) $realtime_refresh_interval,
				'data-realtime-refresh-endpoint'      => esc_url_raw( rest_url( 'thecore-collectivity/v1/transports/widget-realtime' ) ),
				'style' => implode( ';', $wrapper_styles ) . ';',
			)
		);
		?>
		<div <?php echo $this->get_render_attribute_string( 'wrapper' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<script type="application/json" class="bte__data"><?php echo wp_json_encode( $payload ); ?></script>

			<?php if ( '' !== $alerts_markup ) : ?>
				<?php echo $alerts_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>

			<?php if ( $show_alerts ) : ?>
				<div class="bte__realtime-alerts" data-realtime-alerts hidden></div>
			<?php endif; ?>

			<section class="bte__hero">
				<div class="bte__hero-copy">
					<h2 class="bte__title"><?php echo esc_html( $title ); ?></h2>
					<?php if ( '' !== $description ) : ?>
						<p class="bte__description"><?php echo esc_html( $description ); ?></p>
					<?php endif; ?>
				</div>
				<div class="bte__search-row">
					<label class="screen-reader-text" for="<?php echo esc_attr( $widget_id ); ?>-search"><?php esc_html_e( 'Rechercher dans les transports', 'bellevue' ); ?></label>
					<div class="bte__search-control">
						<span class="bte__search-icon" aria-hidden="true"><?php echo $this->get_icon_markup( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<input id="<?php echo esc_attr( $widget_id ); ?>-search" class="bte__search-input" type="search" placeholder="<?php echo esc_attr__( 'Rechercher une ligne, un arret, un parking...', 'bellevue' ); ?>" />
					</div>
					<button type="button" class="bte__search-button"><?php echo $this->get_icon_markup( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span><?php esc_html_e( 'Rechercher', 'bellevue' ); ?></span></button>
				</div>
			</section>

				<section class="bte__map-card">
					<div class="bte__card-head">
						<div>
							<p class="bte__card-kicker"><?php esc_html_e( 'Carte', 'bellevue' ); ?></p>
						<h3 class="bte__card-title"><?php echo $this->get_icon_markup( 'map' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span><?php esc_html_e( 'Carte interactive des transports', 'bellevue' ); ?></span></h3>
						<p class="bte__card-description"><?php esc_html_e( 'Visualisez les arrets de bus, parkings relais, parkings velos et gares.', 'bellevue' ); ?></p>
					</div>
				</div>
				<div class="bte__map" data-map></div>
					<div class="bte__map-empty" data-map-empty hidden><?php esc_html_e( 'Aucune donnee cartographique a afficher avec ces filtres.', 'bellevue' ); ?></div>
					<div class="bte__legend" data-legend></div>
				</section>

				<section class="bte__results" aria-label="<?php echo esc_attr__( 'Resultats transports', 'bellevue' ); ?>">
					<div class="bte__filters" aria-label="<?php echo esc_attr__( 'Filtres transports', 'bellevue' ); ?>">
						<div class="bte__filter-group bte__filter-group--types">
							<span class="bte__filter-label"><?php esc_html_e( 'Afficher :', 'bellevue' ); ?></span>
							<button type="button" class="bte__type-filter" data-filter-type="bus"><?php echo $this->get_icon_markup( 'bus' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'Bus', 'bellevue' ); ?></span></button>
							<button type="button" class="bte__type-filter" data-filter-type="parking"><?php echo $this->get_icon_markup( 'parking' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'Parkings', 'bellevue' ); ?></span></button>
							<button type="button" class="bte__type-filter" data-filter-type="velo"><?php echo $this->get_icon_markup( 'bike' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'Parking velos', 'bellevue' ); ?></span></button>
							<button type="button" class="bte__type-filter" data-filter-type="train"><?php echo $this->get_icon_markup( 'train' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'Train', 'bellevue' ); ?></span></button>
						</div>
						<div class="bte__filter-group bte__filter-group--advanced">
							<label class="bte__toggle"><input type="checkbox" class="bte__toggle-input" data-filter="accessible" /><span><?php echo $this->get_icon_markup( 'accessible' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Accessibilite PMR', 'bellevue' ); ?></span></label>
							<label class="bte__toggle"><input type="checkbox" class="bte__toggle-input" data-filter="free" /><span><?php echo $this->get_icon_markup( 'leaf' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Gratuit uniquement', 'bellevue' ); ?></span></label>
							<label class="bte__select-wrap">
								<span class="screen-reader-text"><?php esc_html_e( 'Jour', 'bellevue' ); ?></span>
								<select class="bte__select" data-filter="day">
									<option value="tous"><?php esc_html_e( 'Tous les jours', 'bellevue' ); ?></option>
									<option value="semaine"><?php esc_html_e( 'Lundi - Vendredi', 'bellevue' ); ?></option>
									<option value="samedi"><?php esc_html_e( 'Samedi', 'bellevue' ); ?></option>
									<option value="dimanche"><?php esc_html_e( 'Dimanche', 'bellevue' ); ?></option>
								</select>
							</label>
							<button type="button" class="bte__reset" data-action="reset" hidden><?php esc_html_e( 'Reinitialiser les filtres', 'bellevue' ); ?></button>
						</div>
					</div>

					<div class="bte__panel" data-panel="bus" hidden>
						<div class="bte__panel-toolbar">
							<div>
								<h3 class="bte__panel-title"><?php esc_html_e( 'Bus', 'bellevue' ); ?></h3>
								<p class="bte__panel-count"><span data-count-label="bus">0</span> <?php esc_html_e( 'ligne(s) trouvee(s)', 'bellevue' ); ?></p>
							</div>
							<select class="bte__select" data-sort="bus">
								<option value="name"><?php esc_html_e( 'Nom de ligne', 'bellevue' ); ?></option>
								<option value="frequency"><?php esc_html_e( 'Frequence', 'bellevue' ); ?></option>
							</select>
						</div>
						<div class="bte__cards bte__cards--line-grid" data-list="bus"></div>
						<div class="bte__line-detail" data-line-detail="bus" hidden></div>
						<div class="bte__empty" data-empty="bus" hidden><?php esc_html_e( 'Aucune ligne ne correspond aux filtres actuels.', 'bellevue' ); ?></div>
					</div>

					<div class="bte__panel" data-panel="parking" hidden>
						<div class="bte__panel-toolbar">
							<div>
								<h3 class="bte__panel-title"><?php esc_html_e( 'Parkings', 'bellevue' ); ?></h3>
								<p class="bte__panel-count"><span data-count-label="parking">0</span> <?php esc_html_e( 'parking(s) trouve(s)', 'bellevue' ); ?></p>
							</div>
							<select class="bte__select" data-sort="parking">
								<option value="disponibilite"><?php esc_html_e( 'Disponibilite', 'bellevue' ); ?></option>
								<option value="name"><?php esc_html_e( 'Nom', 'bellevue' ); ?></option>
								<option value="capacite"><?php esc_html_e( 'Capacite', 'bellevue' ); ?></option>
							</select>
						</div>
						<div class="bte__cards bte__cards--grid" data-list="parking"></div>
						<div class="bte__empty" data-empty="parking" hidden><?php esc_html_e( 'Aucun parking ne correspond aux filtres actuels.', 'bellevue' ); ?></div>
					</div>

					<div class="bte__panel" data-panel="velo" hidden>
						<div class="bte__panel-toolbar">
							<div>
								<h3 class="bte__panel-title"><?php esc_html_e( 'Parking velos', 'bellevue' ); ?></h3>
								<p class="bte__panel-count"><span data-count-label="velo">0</span> <?php esc_html_e( 'parking(s) velos', 'bellevue' ); ?></p>
							</div>
						</div>
						<div class="bte__cards bte__cards--grid" data-list="velo"></div>
						<div class="bte__empty" data-empty="velo" hidden><?php esc_html_e( 'Aucun parking velos n est disponible.', 'bellevue' ); ?></div>
					</div>

					<div class="bte__panel" data-panel="train" hidden>
						<div class="bte__panel-toolbar">
							<div>
								<h3 class="bte__panel-title"><?php esc_html_e( 'Train', 'bellevue' ); ?></h3>
								<p class="bte__panel-count"><span data-count-label="train">0</span> <?php esc_html_e( 'gare(s) / point(s) train', 'bellevue' ); ?></p>
							</div>
						</div>
						<div class="bte__cards" data-list="train"></div>
						<div class="bte__empty" data-empty="train" hidden><?php esc_html_e( 'Aucune information train n est disponible.', 'bellevue' ); ?></div>
					</div>

					<div class="bte__results-empty" data-empty="results" hidden><?php esc_html_e( 'Aucun resultat ne correspond aux filtres actuels.', 'bellevue' ); ?></div>
				</section>
			</div>
		<?php
	}

	/**
	 * Get SVG icon markup.
	 *
	 * @param string $icon Icon key.
	 * @return string
	 */
	private function get_icon_markup( $icon ) {
		switch ( $icon ) {
			case 'bus':
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6v6"></path><path d="M15 6v6"></path><path d="M2 12h19.6"></path><path d="M18 18h3s.5-1.7.8-2.8c.1-.4.2-.8.2-1.2 0-.4-.1-.8-.2-1.2l-1.4-5C20.1 6.8 19.1 6 18 6H4a2 2 0 0 0-2 2v10h3"></path><circle cx="7" cy="18" r="2"></circle><path d="M9 18h5"></path><circle cx="16" cy="18" r="2"></circle></svg>';
			case 'parking':
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9 17V7h4a3 3 0 0 1 0 6H9"></path></svg>';
			case 'bike':
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18.5" cy="17.5" r="3.5"></circle><circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="15" cy="5" r="1"></circle><path d="M12 17.5V14l-3-3 4-3 2 3h2"></path></svg>';
			case 'train':
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="16" rx="2"></rect><path d="M4 11h16"></path><path d="M8 15h.01"></path><path d="M16 15h.01"></path><path d="M8 19 6 21"></path><path d="M18 21 16 19"></path></svg>';
			case 'map':
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"></polygon><line x1="9" y1="3" x2="9" y2="18"></line><line x1="15" y1="6" x2="15" y2="21"></line></svg>';
			case 'accessible':
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="16" cy="4" r="1"></circle><path d="m18 19 1-7-6 1"></path><path d="m5 8 3-3 5.5 3-2.36 3.5"></path><path d="M4 20h4"></path><path d="m16 20 3-4"></path></svg>';
			case 'leaf':
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 0 1 4 13C4 7 11 4 20 4c0 9-3 16-9 16Z"></path><path d="M11 20C7 16 9 12 15 8"></path></svg>';
			case 'search':
			default:
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>';
		}
	}
}
