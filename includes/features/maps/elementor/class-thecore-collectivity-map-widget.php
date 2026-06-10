<?php
/**
 * Generic map Elementor widget.
 */

require_once dirname( __DIR__ ) . '/class-thecore-collectivity-maps-transport-adapter.php';

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Repeater;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TheCore_Collectivity_Map_Widget extends Widget_Base {
	/**
	 * Get widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'tccm-map';
	}

	/**
	 * Get widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Carte', 'thecore-collectivity-management' );
	}

	/**
	 * Get widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-google-maps';
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
	 * Get keywords.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return array( 'map', 'carte', 'leaflet', 'point', 'parcours' );
	}

	/**
	 * Get style dependencies.
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
	 * Get script dependencies.
	 *
	 * @return array
	 */
	public function get_script_depends() {
		return array( TheCore_Collectivity_Elementor::HANDLE_MAP_SCRIPT );
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

		$this->add_control(
			'map_group_id',
			array(
				'label'       => esc_html__( 'Identifiant de groupe', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'description' => esc_html__( 'Renseignez le même identifiant sur le widget Carte et sur le widget Filtres pour qu’ils partagent le même état.', 'thecore-collectivity-management' ),
			)
		);

		$this->add_control(
			'universes',
			array(
				'label'       => esc_html__( 'Univers', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->get_term_options( TheCore_Collectivity_Maps_Post_Type::TAXONOMY_UNIVERSE, true ),
				'default'     => array( '__all__' ),
				'description' => esc_html__( 'Choisissez "Tous" ou laissez vide pour ne pas filtrer.', 'thecore-collectivity-management' ),
			)
		);

		$this->add_control(
			'categories',
			array(
				'label'       => esc_html__( 'Catégories', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->get_term_options( TheCore_Collectivity_Maps_Post_Type::TAXONOMY_CATEGORY, true ),
				'default'     => array( '__all__' ),
				'description' => esc_html__( 'Choisissez "Tous" ou laissez vide pour ne pas filtrer.', 'thecore-collectivity-management' ),
			)
		);

		$this->add_control(
			'themes',
			array(
				'label'       => esc_html__( 'Thèmes', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->get_term_options( TheCore_Collectivity_Shared_Taxonomies_Module::TAXONOMY_THEME, true ),
				'default'     => array( '__all__' ),
				'description' => esc_html__( 'Choisissez "Tous" ou laissez vide pour ne pas filtrer.', 'thecore-collectivity-management' ),
			)
		);

		$this->add_control(
			'audiences',
			array(
				'label'       => esc_html__( 'Publics', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->get_term_options( TheCore_Collectivity_Shared_Taxonomies_Module::TAXONOMY_AUDIENCE, true ),
				'default'     => array( '__all__' ),
				'description' => esc_html__( 'Choisissez "Tous" ou laissez vide pour ne pas filtrer.', 'thecore-collectivity-management' ),
			)
		);

		$this->add_control(
			'territories',
			array(
				'label'       => esc_html__( 'Territoires', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->get_term_options( TheCore_Collectivity_Shared_Taxonomies_Module::TAXONOMY_TERRITORY, true ),
				'default'     => array( '__all__' ),
				'description' => esc_html__( 'Choisissez "Tous" ou laissez vide pour ne pas filtrer.', 'thecore-collectivity-management' ),
			)
		);

		$this->add_control(
			'accessible_only',
			array(
				'label'        => esc_html__( 'Accessible uniquement', 'thecore-collectivity-management' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Oui', 'thecore-collectivity-management' ),
				'label_off'    => esc_html__( 'Non', 'thecore-collectivity-management' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'include_transport_places',
			array(
				'label'        => esc_html__( 'Inclure les transports', 'thecore-collectivity-management' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Oui', 'thecore-collectivity-management' ),
				'label_off'    => esc_html__( 'Non', 'thecore-collectivity-management' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'transport_modes',
			array(
				'label'       => esc_html__( 'Modes de transport', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->get_term_options( TheCore_Collectivity_Transports_Post_Types::TAXONOMY_MODE, true ),
				'default'     => array( '__all__' ),
				'description' => esc_html__( 'Choisissez "Tous" ou laissez vide pour ne pas filtrer les transports inclus.', 'thecore-collectivity-management' ),
				'condition'   => array(
					'include_transport_places' => 'yes',
				),
			)
		);

		$this->add_control(
			'transport_alert_styles',
			array(
				'label'        => esc_html__( 'Styliser les alertes transport sur la carte', 'thecore-collectivity-management' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Oui', 'thecore-collectivity-management' ),
				'label_off'    => esc_html__( 'Non', 'thecore-collectivity-management' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'Optionnel: applique un style visuel aux lignes ou arrêts de transport concernés par une alerte temps réel.', 'thecore-collectivity-management' ),
				'condition'    => array(
					'include_transport_places' => 'yes',
				),
			)
		);

		$this->add_control(
			'show_points',
			array(
				'label'        => esc_html__( 'Afficher les lieux', 'thecore-collectivity-management' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Oui', 'thecore-collectivity-management' ),
				'label_off'    => esc_html__( 'Non', 'thecore-collectivity-management' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_routes',
			array(
				'label'        => esc_html__( 'Afficher les parcours', 'thecore-collectivity-management' ),
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

		$this->add_control(
			'show_editor_debug',
			array(
				'label'        => esc_html__( 'Afficher le debug d’édition', 'thecore-collectivity-management' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Oui', 'thecore-collectivity-management' ),
				'label_off'    => esc_html__( 'Non', 'thecore-collectivity-management' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'Affiche les aides de debug dans l’éditeur Elementor uniquement.', 'thecore-collectivity-management' ),
			)
		);

		$this->add_responsive_control(
			'map_height',
			array(
				'label'      => esc_html__( 'Hauteur de la carte', 'thecore-collectivity-management' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 280,
						'max' => 900,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 520,
				),
			)
		);

		$this->add_control(
			'initial_view_mode',
			array(
				'label'   => esc_html__( 'Cadrage initial', 'thecore-collectivity-management' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'fit_bounds',
				'options' => array(
					'fit_bounds' => esc_html__( 'Auto: ajuster à tous les résultats', 'thecore-collectivity-management' ),
					'manual'     => esc_html__( 'Manuel: centre et zoom imposés', 'thecore-collectivity-management' ),
				),
			)
		);

		$this->add_control(
			'initial_center_lat',
			array(
				'label'       => esc_html__( 'Latitude initiale', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::NUMBER,
				'step'        => 0.000001,
				'condition'   => array(
					'initial_view_mode' => 'manual',
				),
			)
		);

		$this->add_control(
			'initial_center_lng',
			array(
				'label'       => esc_html__( 'Longitude initiale', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::NUMBER,
				'step'        => 0.000001,
				'condition'   => array(
					'initial_view_mode' => 'manual',
				),
			)
		);

		$this->add_control(
			'initial_zoom',
			array(
				'label'     => esc_html__( 'Zoom initial', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::NUMBER,
				'min'       => 1,
				'max'       => 20,
				'step'      => 0.25,
				'default'   => 13,
				'condition' => array(
					'initial_view_mode' => 'manual',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_category_styles',
			array(
				'label' => esc_html__( 'Styles par catégorie', 'thecore-collectivity-management' ),
			)
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'category_slug',
			array(
				'label'       => esc_html__( 'Catégorie', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::SELECT,
				'label_block' => true,
				'options'     => $this->get_term_options( TheCore_Collectivity_Maps_Post_Type::TAXONOMY_CATEGORY, false ),
			)
		);

		$repeater->add_control(
			'color',
			array(
				'label' => esc_html__( 'Couleur', 'thecore-collectivity-management' ),
				'type'  => Controls_Manager::COLOR,
			)
		);

		$repeater->add_control(
			'icon_type',
			array(
				'label'   => esc_html__( 'Type d’icône', 'thecore-collectivity-management' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'default',
				'options' => $this->get_marker_icon_options(),
			)
		);

		$repeater->add_control(
			'elementor_icon',
			array(
				'label'     => esc_html__( 'Icône Elementor', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::ICONS,
				'skin'      => 'inline',
				'condition' => array(
					'icon_type' => 'elementor_icon',
				),
			)
		);

		$repeater->add_control(
			'icon_label',
			array(
				'label'       => esc_html__( 'Libellé de l’icône', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => false,
				'placeholder' => 'PL',
				'condition'   => array(
					'icon_type' => 'label',
				),
			)
		);

		$repeater->add_control(
			'custom_svg',
			array(
				'label'       => esc_html__( 'SVG personnalisé', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 6,
				'label_block' => true,
				'placeholder' => '<svg viewBox="0 0 24 24" ...>...</svg>',
				'description' => esc_html__( 'Collez un petit SVG monochrome. Il sera injecté tel quel dans l’icône du marqueur après nettoyage.', 'thecore-collectivity-management' ),
				'condition'   => array(
					'icon_type' => 'custom_svg',
				),
			)
		);

		$repeater->add_control(
			'route_heading',
			array(
				'label'     => esc_html__( 'Réglages parcours', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$repeater->add_control(
			'route_stroke_width',
			array(
				'label'      => esc_html__( 'Épaisseur du tracé', 'thecore-collectivity-management' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 2,
						'max' => 16,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 4,
				),
			)
		);

		$repeater->add_control(
			'route_stroke_style',
			array(
				'label'   => esc_html__( 'Type de trait', 'thecore-collectivity-management' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'solid',
				'options' => array(
					'solid'   => esc_html__( 'Continu', 'thecore-collectivity-management' ),
					'dashed'  => esc_html__( 'Tirets', 'thecore-collectivity-management' ),
					'dotted'  => esc_html__( 'Pointillé', 'thecore-collectivity-management' ),
					'dashdot' => esc_html__( 'Tirets et points', 'thecore-collectivity-management' ),
				),
			)
		);

		$repeater->add_control(
			'route_icon_position',
			array(
				'label'      => esc_html__( 'Position de l’icône sur le parcours', 'thecore-collectivity-management' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( '%' ),
				'range'      => array(
					'%' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'default'    => array(
					'unit' => '%',
					'size' => 50,
				),
			)
		);

		$repeater->add_control(
			'route_trim_start',
			array(
				'label'       => esc_html__( 'Décalage au début du tracé', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => array( '%' ),
				'range'       => array(
					'%' => array(
						'min' => 0,
						'max' => 95,
					),
				),
				'default'     => array(
					'unit' => '%',
					'size' => 0,
				),
				'description' => esc_html__( '0 = aucun rognage. 100 = jusqu’au milieu du parcours.', 'thecore-collectivity-management' ),
			)
		);

		$repeater->add_control(
			'route_trim_end',
			array(
				'label'       => esc_html__( 'Décalage à la fin du tracé', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => array( '%' ),
				'range'       => array(
					'%' => array(
						'min' => 0,
						'max' => 95,
					),
				),
				'default'     => array(
					'unit' => '%',
					'size' => 0,
				),
				'description' => esc_html__( '0 = aucun rognage. 100 = jusqu’au milieu du parcours.', 'thecore-collectivity-management' ),
			)
		);

		$this->add_control(
			'category_styles',
			array(
				'label'       => esc_html__( 'Styles des catégories', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ category_slug || "style" }}}',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_map_styles',
			array(
				'label' => esc_html__( 'Styles de la carte', 'thecore-collectivity-management' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'loader_spinner_color',
			array(
				'label'     => esc_html__( 'Couleur du spinner', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-loading-spinner-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'loader_text_color',
			array(
				'label'     => esc_html__( 'Couleur du texte de chargement', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .tccm-map__loading-label' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'loader_text_typography',
				'selector' => '{{WRAPPER}} .tccm-map__loading-label',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_popup_styles',
			array(
				'label' => esc_html__( 'Styles des vignettes', 'thecore-collectivity-management' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'popup_card_heading',
			array(
				'label' => esc_html__( 'Vignette', 'thecore-collectivity-management' ),
				'type'  => Controls_Manager::HEADING,
			)
		);

		$this->add_control(
			'popup_background_color',
			array(
				'label'     => esc_html__( 'Fond', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-popup-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'popup_border_color',
			array(
				'label'     => esc_html__( 'Bordure', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-popup-border: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'popup_radius_heading',
			array(
				'label' => esc_html__( 'Rayon', 'thecore-collectivity-management' ),
				'type'  => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'popup_border_radius',
			array(
				'label'      => esc_html__( 'Border radius', 'thecore-collectivity-management' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 40,
					),
				),
				'default'    => array(
					'size' => 16,
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--tccm-map-popup-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'popup_card_box_shadow',
				'selector' => '{{WRAPPER}} .tccm-map__popup .leaflet-popup-content-wrapper',
			)
		);

		$this->add_control(
			'popup_title_heading',
			array(
				'label'     => esc_html__( 'Titre', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'popup_title_color',
			array(
				'label'     => esc_html__( 'Couleur', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-popup-title: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'popup_title_typography',
				'selector' => '{{WRAPPER}} .tccm-map__popup-title',
			)
		);

		$this->add_control(
			'popup_text_heading',
			array(
				'label'     => esc_html__( 'Texte', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'popup_text_color',
			array(
				'label'     => esc_html__( 'Texte principal', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-popup-text: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'popup_muted_color',
			array(
				'label'     => esc_html__( 'Texte secondaire', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-popup-muted: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'popup_link_color',
			array(
				'label'     => esc_html__( 'Liens texte', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-popup-link: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'popup_section_title_color',
			array(
				'label'     => esc_html__( 'Titres de sections', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-popup-section-title: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'popup_body_typography',
				'selector' => '{{WRAPPER}} .tccm-map__popup-subtitle, {{WRAPPER}} .tccm-map__popup-summary, {{WRAPPER}} .tccm-map__popup-meta, {{WRAPPER}} .tccm-map__popup-accessibility, {{WRAPPER}} .tccm-map__popup-link-list, {{WRAPPER}} .tccm-map__popup-link-list a, {{WRAPPER}} .tccm-map__popup-list',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'popup_section_title_typography',
				'selector' => '{{WRAPPER}} .tccm-map__popup-section-title',
			)
		);

		$this->add_control(
			'popup_primary_button_heading',
			array(
				'label'     => esc_html__( 'Bouton principal', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'popup_primary_button_background_color',
			array(
				'label'     => esc_html__( 'Fond', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-popup-primary-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'popup_primary_button_text_color',
			array(
				'label'     => esc_html__( 'Texte', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-popup-primary-text: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'popup_primary_button_border_color',
			array(
				'label'     => esc_html__( 'Bordure', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-popup-primary-border: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'popup_primary_button_typography',
				'selector' => '{{WRAPPER}} .tccm-map__popup-cta--primary',
			)
		);

		$this->add_control(
			'popup_secondary_button_heading',
			array(
				'label'     => esc_html__( 'Bouton secondaire', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'popup_secondary_button_background_color',
			array(
				'label'     => esc_html__( 'Fond', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-popup-secondary-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'popup_secondary_button_text_color',
			array(
				'label'     => esc_html__( 'Texte', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-popup-secondary-text: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'popup_secondary_button_border_color',
			array(
				'label'     => esc_html__( 'Bordure', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-popup-secondary-border: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'popup_secondary_button_typography',
				'selector' => '{{WRAPPER}} .tccm-map__popup-cta--related',
			)
		);

		$this->add_control(
			'popup_tags_heading',
			array(
				'label'     => esc_html__( 'Tags et pastilles', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'popup_tag_background_color',
			array(
				'label'     => esc_html__( 'Fond des tags', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-popup-tag-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'popup_tag_text_color',
			array(
				'label'     => esc_html__( 'Texte des tags', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-popup-tag-text: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'popup_tag_typography',
				'selector' => '{{WRAPPER}} .tccm-map__popup-tags span',
			)
		);

		$this->add_control(
			'popup_chip_background_color',
			array(
				'label'     => esc_html__( 'Fond des pastilles de liens', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-popup-chip-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'popup_chip_text_color',
			array(
				'label'     => esc_html__( 'Texte des pastilles de liens', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--tccm-map-popup-chip-text: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'popup_chip_typography',
				'selector' => '{{WRAPPER}} .tccm-map__popup-chip',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_route_styles',
			array(
				'label' => esc_html__( 'Styles des parcours', 'thecore-collectivity-management' ),
			)
		);

		$this->add_control(
			'route_styles_help',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Définissez quelques presets réutilisables, puis affectez-les explicitement aux parcours. Cela évite que les couleurs changent quand l’ordre ou les filtres évoluent.', 'thecore-collectivity-management' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->register_route_preset_controls( 'route_preset_a', esc_html__( 'Preset A', 'thecore-collectivity-management' ) );
		$this->register_route_preset_controls( 'route_preset_b', esc_html__( 'Preset B', 'thecore-collectivity-management' ) );
		$this->register_route_preset_controls( 'route_preset_c', esc_html__( 'Preset C', 'thecore-collectivity-management' ) );
		$this->register_route_preset_controls( 'route_preset_d', esc_html__( 'Preset D', 'thecore-collectivity-management' ) );

		$route_assignments = new Repeater();

		$route_assignments->add_control(
			'route_item_id',
			array(
				'label'       => esc_html__( 'Parcours', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::SELECT2,
				'label_block' => true,
				'options'     => $this->get_route_item_options(),
			)
		);

		$route_assignments->add_control(
			'preset_key',
			array(
				'label'   => esc_html__( 'Preset', 'thecore-collectivity-management' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'route_preset_a',
				'options' => $this->get_route_preset_options(),
			)
		);

		$this->add_control(
			'route_style_assignments',
			array(
				'label'       => esc_html__( 'Affectation des parcours', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $route_assignments->get_controls(),
				'title_field' => '{{{ preset_key || "preset" }}}',
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
		$this->maybe_sync_document_assets();
		$show_points      = isset( $settings['show_points'] ) && 'yes' === $settings['show_points'];
		$show_routes      = isset( $settings['show_routes'] ) && 'yes' === $settings['show_routes'];
		$geometry_types   = array();
		$map_height       = isset( $settings['map_height']['size'] ) ? absint( $settings['map_height']['size'] ) : 520;
		$group_id         = self::normalize_map_group_id( $settings['map_group_id'] ?? '', $this->get_id() );

		if ( $show_points ) {
			$geometry_types[] = TheCore_Collectivity_Maps_Meta::GEOMETRY_POINT;
		}

		if ( $show_routes ) {
			$geometry_types[] = TheCore_Collectivity_Maps_Meta::GEOMETRY_ROUTE;
		}

		$filters = array(
			'universes'      => $this->normalize_term_filter_values( $this->get_universe_filter_values( $settings ), TheCore_Collectivity_Maps_Post_Type::TAXONOMY_UNIVERSE ),
			'categories'     => $this->normalize_term_filter_values( $settings['categories'] ?? array(), TheCore_Collectivity_Maps_Post_Type::TAXONOMY_CATEGORY ),
			'themes'         => $this->normalize_term_filter_values( $settings['themes'] ?? array(), TheCore_Collectivity_Shared_Taxonomies_Module::TAXONOMY_THEME ),
			'audiences'      => $this->normalize_term_filter_values( $settings['audiences'] ?? array(), TheCore_Collectivity_Shared_Taxonomies_Module::TAXONOMY_AUDIENCE ),
			'territories'    => $this->normalize_term_filter_values( $settings['territories'] ?? array(), TheCore_Collectivity_Shared_Taxonomies_Module::TAXONOMY_TERRITORY ),
			'accessible_only'=> isset( $settings['accessible_only'] ) && 'yes' === $settings['accessible_only'],
			'geometry_types' => $geometry_types,
		);

		$maps_module = TheCore_Collectivity_Management::instance()->get_maps_module();
		$payload     = $maps_module->get_normalizer()->get_widget_payload( $filters );
		$payload['categoryStyles'] = $this->get_category_styles_payload( $settings['category_styles'] ?? array() );
		$payload['routeStyles']    = $this->get_route_styles_payload( $settings );
		$payload['view']           = $this->get_view_payload( $settings );
		$payload['transportAlertStylesEnabled'] = isset( $settings['transport_alert_styles'] ) && 'yes' === $settings['transport_alert_styles'];
		if ( isset( $settings['include_transport_places'] ) && 'yes' === $settings['include_transport_places'] ) {
			$payload = $this->merge_transport_payload(
				$payload,
				array(
					'modes'          => $this->normalize_term_filter_values( $settings['transport_modes'] ?? array(), TheCore_Collectivity_Transports_Post_Types::TAXONOMY_MODE ),
					'include_points' => $show_points,
					'include_routes' => $show_routes,
				)
			);
		}
		$widget_id   = 'tccm-map-' . $this->get_id();
		$wrapper_styles = array_merge(
			array(
				'--tccm-map-height:' . max( 280, $map_height ) . 'px',
			),
			$this->get_popup_style_variables( $settings )
		);

		$this->add_render_attribute(
			'wrapper',
			array(
				'class'          => 'tccm-map',
				'id'             => $widget_id,
				'style'          => implode( ';', $wrapper_styles ) . ';',
				'data-map-group' => $group_id,
			)
		);
		?>
		<div <?php echo $this->get_render_attribute_string( 'wrapper' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="tccm-map__data" hidden><?php echo wp_json_encode( $payload ); ?></div>

			<?php
			self::render_controls_markup(
				$widget_id,
				array(
					'group_id'     => $group_id,
					'show_search'  => isset( $settings['show_search'] ) && 'yes' === $settings['show_search'],
					'show_filters' => isset( $settings['show_filters'] ) && 'yes' === $settings['show_filters'],
					'show_summary' => isset( $settings['show_summary'] ) && 'yes' === $settings['show_summary'],
				)
			);
			?>

			<?php if ( $this->is_edit_mode() && isset( $settings['show_editor_debug'] ) && 'yes' === $settings['show_editor_debug'] ) : ?>
				<p class="tccm-map__editor-debug-server">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: total items, 2: total points, 3: total routes */
							__( 'Debug serveur: payload=%1$d | lieux=%2$d | parcours=%3$d', 'thecore-collectivity-management' ),
							(int) ( $payload['stats']['total'] ?? 0 ),
							(int) ( $payload['stats']['points'] ?? 0 ),
							(int) ( $payload['stats']['routes'] ?? 0 )
						)
					);
					?>
				</p>

				<div class="tccm-map__editor-preview" data-editor-style-preview hidden>
					<p class="tccm-map__editor-preview-title"><?php esc_html_e( 'Aperçu des styles configurés', 'thecore-collectivity-management' ); ?></p>
					<p class="tccm-map__editor-debug" data-editor-debug hidden></p>
					<div class="tccm-map__editor-preview-items" data-editor-style-preview-items></div>
				</div>
			<?php endif; ?>

			<div class="tccm-map__canvas-wrap">
				<div class="tccm-map__loading" data-map-loading>
					<div class="tccm-map__loading-inner">
						<span class="tccm-map__loading-spinner" aria-hidden="true"></span>
						<span class="tccm-map__loading-label"><?php esc_html_e( 'Chargement de la carte…', 'thecore-collectivity-management' ); ?></span>
					</div>
				</div>
				<div class="tccm-map__canvas" data-map></div>
				<div class="tccm-map__empty" data-map-empty hidden><?php esc_html_e( 'Aucun élément ne correspond aux filtres actuels.', 'thecore-collectivity-management' ); ?></div>
			</div>
		</div>
		<?php
	}

		/**
		 * Normalize a shared group identifier for map/filter widgets.
		 *
		 * @param mixed  $value           Raw configured group identifier.
		 * @param string $fallback_suffix Unique fallback suffix.
		 * @return string
		 */
		public static function normalize_map_group_id( $value, $fallback_suffix ) {
			$group_id = sanitize_key( (string) $value );
			if ( '' !== $group_id ) {
				return $group_id;
			}

			$fallback_suffix = sanitize_key( (string) $fallback_suffix );
			if ( '' === $fallback_suffix ) {
				$fallback_suffix = wp_generate_uuid4();
			}

			return 'tccm-map-group-' . $fallback_suffix;
		}

		/**
		 * Render the shared filter/search UI used by both widgets.
		 *
		 * @param string $widget_id Unique widget identifier.
		 * @param array  $args      Control display options.
		 * @return void
		 */
		public static function render_controls_markup( $widget_id, array $args = array() ) {
			$show_search     = ! empty( $args['show_search'] );
			$show_filters    = ! empty( $args['show_filters'] );
			$show_summary    = ! empty( $args['show_summary'] );
			$show_reset      = array_key_exists( 'show_reset', $args ) ? ! empty( $args['show_reset'] ) : ( $show_search || $show_filters );
			$show_types      = array_key_exists( 'show_types', $args ) ? ! empty( $args['show_types'] ) : $show_filters;
			$show_accessible = array_key_exists( 'show_accessible', $args ) ? ! empty( $args['show_accessible'] ) : $show_filters;
			$facet_labels    = self::get_filter_select_labels();
			$facet_keys      = isset( $args['facet_keys'] ) && is_array( $args['facet_keys'] )
				? array_values( array_intersect( $args['facet_keys'], array_keys( $facet_labels ) ) )
				: ( $show_filters ? array_keys( $facet_labels ) : array() );
			$group_id        = isset( $args['group_id'] ) ? sanitize_key( (string) $args['group_id'] ) : '';
			$show_toolbar    = $show_search || $show_types || ! empty( $facet_keys ) || $show_accessible || $show_reset;

			if ( ! $show_toolbar && ! $show_summary ) {
				return;
			}
			?>
			<div
				class="tccm-map__controls"
				data-map-controls
				data-map-group="<?php echo esc_attr( $group_id ); ?>"
				data-show-search="<?php echo $show_search ? '1' : '0'; ?>"
				data-show-types="<?php echo $show_types ? '1' : '0'; ?>"
				data-show-facets="<?php echo ! empty( $facet_keys ) ? '1' : '0'; ?>"
				data-show-accessible="<?php echo $show_accessible ? '1' : '0'; ?>"
				data-show-reset="<?php echo $show_reset ? '1' : '0'; ?>"
				data-show-summary="<?php echo $show_summary ? '1' : '0'; ?>"
			>
				<?php if ( $show_toolbar ) : ?>
					<div class="tccm-map__toolbar">
						<div class="tccm-map__search" data-filter-wrap="search" <?php echo $show_search ? '' : 'hidden'; ?>>
							<label class="screen-reader-text" for="<?php echo esc_attr( $widget_id ); ?>-search"><?php esc_html_e( 'Recherche carte', 'thecore-collectivity-management' ); ?></label>
							<input id="<?php echo esc_attr( $widget_id ); ?>-search" type="search" class="tccm-map__search-input" placeholder="<?php esc_attr_e( 'Rechercher un lieu ou un parcours', 'thecore-collectivity-management' ); ?>" data-filter-search />
						</div>
						<div class="tccm-map__toggle-group" data-filter-wrap="types" <?php echo $show_types ? '' : 'hidden'; ?>>
							<button type="button" class="tccm-map__toggle is-active" data-filter-type="<?php echo esc_attr( TheCore_Collectivity_Maps_Meta::GEOMETRY_POINT ); ?>"><?php esc_html_e( 'Lieux', 'thecore-collectivity-management' ); ?></button>
							<button type="button" class="tccm-map__toggle is-active" data-filter-type="<?php echo esc_attr( TheCore_Collectivity_Maps_Meta::GEOMETRY_ROUTE ); ?>"><?php esc_html_e( 'Circuits', 'thecore-collectivity-management' ); ?></button>
							<button type="button" class="tccm-map__toggle is-active" data-filter-type="transport_route"><?php esc_html_e( 'Lignes de transport', 'thecore-collectivity-management' ); ?></button>
						</div>
						<div class="tccm-map__selects" data-filter-wrap="facets" <?php echo ! empty( $facet_keys ) ? '' : 'hidden'; ?>>
							<?php foreach ( $facet_keys as $facet_key ) : ?>
								<select class="tccm-map__select" data-filter-select="<?php echo esc_attr( $facet_key ); ?>"><option value=""><?php echo esc_html( $facet_labels[ $facet_key ] ); ?></option></select>
							<?php endforeach; ?>
						</div>
						<label class="tccm-map__accessible" data-filter-wrap="accessible" <?php echo $show_accessible ? '' : 'hidden'; ?>>
							<input type="checkbox" data-filter-accessible />
							<span><?php esc_html_e( 'Accessible', 'thecore-collectivity-management' ); ?></span>
						</label>
						<?php if ( $show_reset ) : ?>
							<button type="button" class="tccm-map__reset" data-filter-reset><?php esc_html_e( 'Réinitialiser', 'thecore-collectivity-management' ); ?></button>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="tccm-map__summary" data-filter-summary <?php echo $show_summary ? '' : 'hidden'; ?>>
					<span data-map-count>0</span>
					<span><?php esc_html_e( 'élément(s) visibles', 'thecore-collectivity-management' ); ?></span>
				</div>
			</div>
			<?php
		}

		/**
		 * Return filter select labels keyed by state facet.
		 *
		 * @return array
		 */
		public static function get_filter_select_labels() {
			return array(
				'universes'      => __( 'Tous les univers', 'thecore-collectivity-management' ),
				'categories'     => __( 'Toutes les catégories', 'thecore-collectivity-management' ),
				'themes'         => __( 'Tous les thèmes', 'thecore-collectivity-management' ),
				'audiences'      => __( 'Tous les publics', 'thecore-collectivity-management' ),
				'territories'    => __( 'Tous les territoires', 'thecore-collectivity-management' ),
				'transportModes' => __( 'Tous les modes de transport', 'thecore-collectivity-management' ),
			);
		}

		/**
		 * Get options for a taxonomy control.
		 *
		 * @param string $taxonomy    Taxonomy slug.
		 * @param bool   $include_all Prepend the explicit all option.
		 * @return array
		 */
		private function get_term_options( $taxonomy, $include_all = false ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				return array();
			}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$options = array();
		if ( $include_all ) {
			$options['__all__'] = esc_html__( 'Tous', 'thecore-collectivity-management' );
		}

		foreach ( $terms as $term ) {
			$options[ $term->slug ] = $term->name;
		}

		return $options;
	}

	/**
	 * Normalize a multi-term control value.
	 *
	 * @param mixed $value Raw control value.
	 * @return array
	 */
	private function normalize_term_filter_values( $value, $taxonomy = '' ) {
		$values = array_values(
			array_filter(
				array_map( 'sanitize_key', (array) $value )
			)
		);

		if ( empty( $values ) || in_array( '__all__', $values, true ) ) {
			return array();
		}

		if ( $taxonomy && taxonomy_exists( $taxonomy ) ) {
			$valid_slugs = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'fields'     => 'slugs',
				)
			);

			if ( is_wp_error( $valid_slugs ) ) {
				return array();
			}

			$values = array_values( array_intersect( $values, $valid_slugs ) );
		}

		if ( empty( $values ) ) {
			return array();
		}

		return $values;
	}

	/**
	 * Build the category styles payload for frontend overrides.
	 *
	 * @param mixed $rows Repeater rows.
	 * @return array
	 */
	private function get_category_styles_payload( $rows ) {
		$payload = array();

		foreach ( (array) $rows as $row ) {
			$category = isset( $row['category_slug'] ) ? sanitize_key( (string) $row['category_slug'] ) : '';
			if ( '' === $category ) {
				continue;
			}

			$term                = get_term_by( 'slug', $category, TheCore_Collectivity_Maps_Post_Type::TAXONOMY_CATEGORY );
			$payload[ $category ] = $this->build_style_payload(
				$row,
				$term && ! is_wp_error( $term ) ? $term->name : $category
			);
		}

		return $payload;
	}

	/**
	 * Build the route-specific styles payload.
	 *
	 * @param array $settings Widget settings.
	 * @return array
	 */
	private function get_route_styles_payload( array $settings ) {
		$payload     = array();
		$presets     = $this->get_route_presets_payload( $settings );
		$route_items = $this->get_route_items_index();

		foreach ( (array) ( $settings['route_style_assignments'] ?? array() ) as $row ) {
			$route_id   = $this->normalize_route_item_id( $row['route_item_id'] ?? '' );
			$preset_key = isset( $row['preset_key'] ) ? sanitize_key( (string) $row['preset_key'] ) : '';

			if ( '' === $route_id || ! isset( $route_items[ $route_id ] ) || ! isset( $presets[ $preset_key ] ) ) {
				continue;
			}

			$payload[ $route_id ]            = $presets[ $preset_key ];
			$payload[ $route_id ]['name']    = $route_items[ $route_id ];
			$payload[ $route_id ]['routeId'] = $route_id;
		}

		return $payload;
	}

	/**
	 * Normalize one route item identifier from Elementor settings.
	 *
	 * Supports numeric MAP post IDs and string transport route IDs such as
	 * transport-line-790.
	 *
	 * @param mixed $value Raw route item id.
	 * @return string
	 */
	private function normalize_route_item_id( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = sanitize_text_field( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/^transport-line-\d+$/', $value ) ) {
			return $value;
		}

		$numeric_id = absint( $value );

		return $numeric_id > 0 ? (string) $numeric_id : '';
	}

	/**
	 * Merge MAP items with transport place items.
	 *
	 * @param array $payload  Current MAP payload.
	 * @param array $filters  Transport adapter filters.
	 * @return array
	 */
	private function merge_transport_payload( array $payload, array $filters ) {
		$adapter         = new TheCore_Collectivity_Maps_Transport_Adapter();
		$transport_items = array();

		if ( ! isset( $filters['include_points'] ) || $filters['include_points'] ) {
			$transport_items = array_merge( $transport_items, $adapter->get_transport_place_items( $filters ) );
		}

		if ( ! empty( $filters['include_routes'] ) ) {
			$transport_items = array_merge( $transport_items, $adapter->get_transport_line_items( $filters ) );
		}

		if ( empty( $transport_items ) ) {
			$payload['facets']['transportModes'] = array();
			$payload['stats']                    = $this->recalculate_stats( $payload['items'] ?? array() );
			return $payload;
		}

		$items = array_merge( (array) ( $payload['items'] ?? array() ), $transport_items );
		usort(
			$items,
			static function ( $left, $right ) {
				$left_order  = isset( $left['displayOrder'] ) ? (int) $left['displayOrder'] : 0;
				$right_order = isset( $right['displayOrder'] ) ? (int) $right['displayOrder'] : 0;

				if ( $left_order === $right_order ) {
					return strcasecmp( (string) ( $left['title'] ?? '' ), (string) ( $right['title'] ?? '' ) );
				}

				return $left_order <=> $right_order;
			}
		);

		$payload['items']                     = $items;
		$payload['facets']['transportModes']  = $adapter->get_transport_mode_facets( $transport_items );
		$payload['stats']                     = $this->recalculate_stats( $items );

		return $payload;
	}

	/**
	 * Resolve the universe filter values, with backward compatibility for
	 * previously saved widget settings using the old `collections` key.
	 *
	 * @param array $settings Widget settings.
	 * @return array
	 */
	private function get_universe_filter_values( array $settings ) {
		$universes = isset( $settings['universes'] ) ? (array) $settings['universes'] : array();
		if ( ! empty( $universes ) ) {
			return $universes;
		}

		if ( $this->is_edit_mode() ) {
			return array();
		}

		$legacy = isset( $settings['collections'] ) ? (array) $settings['collections'] : array();
		if ( empty( $legacy ) ) {
			return array();
		}

		$legacy_map = array(
			'randonnees' => 'itineraires',
		);

		return array_map(
			static function ( $value ) use ( $legacy_map ) {
				$value = sanitize_key( (string) $value );
				return isset( $legacy_map[ $value ] ) ? $legacy_map[ $value ] : $value;
			},
			$legacy
		);
	}

	/**
	 * Ensure the current Elementor document carries this widget asset map.
	 *
	 * @return void
	 */
	private function maybe_sync_document_assets() {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$assets = get_post_meta( $post_id, '_elementor_page_assets', true );
		if ( ! is_array( $assets ) ) {
			$assets = array();
		}

		$assets['styles'] = array_values(
			array_unique(
				array_merge(
					(array) ( $assets['styles'] ?? array() ),
					array(
						TheCore_Collectivity_Elementor::HANDLE_LEAFLET_STYLE,
						TheCore_Collectivity_Elementor::HANDLE_MAP_STYLE,
					)
				)
			)
		);

		$assets['scripts'] = array_values(
			array_unique(
				array_merge(
					(array) ( $assets['scripts'] ?? array() ),
					array(
						'elementor-frontend',
						TheCore_Collectivity_Elementor::HANDLE_LEAFLET_SCRIPT,
						TheCore_Collectivity_Elementor::HANDLE_MAP_SCRIPT,
					)
				)
			)
		);

		update_post_meta( $post_id, '_elementor_page_assets', $assets );
	}

	/**
	 * Determine if widget is rendered in Elementor editor or preview.
	 *
	 * @return bool
	 */
	private function is_edit_mode() {
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

		if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
			return false;
		}

		$plugin = \Elementor\Plugin::$instance;

		return $plugin->editor->is_edit_mode() || $plugin->preview->is_preview_mode();
	}

	/**
	 * Sanitize custom SVG markup for frontend icon injection.
	 *
	 * @param string $svg Raw SVG markup.
	 * @return string
	 */
	private function sanitize_svg_markup( $svg ) {
		$svg = trim( (string) $svg );
		if ( '' === $svg ) {
			return '';
		}

		$allowed = array(
			'svg'      => array(
				'xmlns'             => true,
				'viewBox'           => true,
				'viewbox'           => true,
				'fill'              => true,
				'stroke'            => true,
				'stroke-width'      => true,
				'stroke-linecap'    => true,
				'stroke-linejoin'   => true,
				'width'             => true,
				'height'            => true,
				'role'              => true,
				'aria-hidden'       => true,
				'focusable'         => true,
				'class'             => true,
			),
			'g'        => array(
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'transform'       => true,
				'class'           => true,
			),
			'path'     => array(
				'd'                => true,
				'fill'             => true,
				'stroke'           => true,
				'stroke-width'     => true,
				'stroke-linecap'   => true,
				'stroke-linejoin'  => true,
				'transform'        => true,
				'class'            => true,
			),
			'circle'   => array(
				'cx'              => true,
				'cy'              => true,
				'r'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'class'           => true,
			),
			'ellipse'  => array(
				'cx'              => true,
				'cy'              => true,
				'rx'              => true,
				'ry'              => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'class'           => true,
			),
			'rect'     => array(
				'x'               => true,
				'y'               => true,
				'width'           => true,
				'height'          => true,
				'rx'              => true,
				'ry'              => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'class'           => true,
			),
			'line'     => array(
				'x1'              => true,
				'y1'              => true,
				'x2'              => true,
				'y2'              => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'class'           => true,
			),
			'polyline' => array(
				'points'          => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'class'           => true,
			),
			'polygon'  => array(
				'points'          => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'class'           => true,
			),
		);

		return wp_kses( $svg, $allowed );
	}

	/**
	 * Render Elementor icon markup from an ICONS control value.
	 *
	 * @param mixed $icon Icon control value.
	 * @return string
	 */
	private function get_elementor_icon_markup( $icon ) {
		if ( empty( $icon ) || ! is_array( $icon ) || empty( $icon['library'] ) ) {
			return '';
		}

		ob_start();
		Icons_Manager::render_icon(
			$icon,
			array(
				'aria-hidden' => 'true',
			),
			'i'
		);

		return trim( (string) ob_get_clean() );
	}

	/**
	 * Convert a route stroke style into a Leaflet dash array string.
	 *
	 * @param string $style Stroke style slug.
	 * @return string
	 */
	private function get_route_dash_array( $style ) {
		switch ( $style ) {
			case 'dashed':
				return '10 8';
			case 'dotted':
				return '2 8';
			case 'dashdot':
				return '12 8 2 8';
			case 'solid':
			default:
				return '';
		}
	}

	/**
	 * Return the repeater field set used for additional route icons.
	 *
	 * @return array
	 */
	private function get_route_icon_repeater_fields() {
		$repeater = new Repeater();

		$repeater->add_control(
			'marker_name',
			array(
				'label'       => esc_html__( 'Nom interne', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'placeholder' => esc_html__( 'Repère 1', 'thecore-collectivity-management' ),
			)
		);

		$repeater->add_control(
			'icon_type',
			array(
				'label'   => esc_html__( 'Type d’icône', 'thecore-collectivity-management' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'route',
				'options' => $this->get_marker_icon_options(),
			)
		);

		$repeater->add_control(
			'elementor_icon',
			array(
				'label'     => esc_html__( 'Icône Elementor', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::ICONS,
				'skin'      => 'inline',
				'condition' => array(
					'icon_type' => 'elementor_icon',
				),
			)
		);

		$repeater->add_control(
			'icon_label',
			array(
				'label'       => esc_html__( 'Libellé de l’icône', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => false,
				'placeholder' => 'RT',
				'condition'   => array(
					'icon_type' => 'label',
				),
			)
		);

		$repeater->add_control(
			'custom_svg',
			array(
				'label'       => esc_html__( 'SVG personnalisé', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 5,
				'label_block' => true,
				'placeholder' => '<svg viewBox="0 0 24 24" ...>...</svg>',
				'condition'   => array(
					'icon_type' => 'custom_svg',
				),
			)
		);

		$repeater->add_control(
			'icon_color',
			array(
				'label' => esc_html__( 'Couleur', 'thecore-collectivity-management' ),
				'type'  => Controls_Manager::COLOR,
			)
		);

		$repeater->add_control(
			'icon_size',
			array(
				'label'      => esc_html__( 'Taille', 'thecore-collectivity-management' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 22,
						'max' => 56,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 34,
				),
			)
		);

		$repeater->add_control(
			'with_outline',
			array(
				'label'        => esc_html__( 'Afficher un contour', 'thecore-collectivity-management' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Oui', 'thecore-collectivity-management' ),
				'label_off'    => esc_html__( 'Non', 'thecore-collectivity-management' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$repeater->add_control(
			'position',
			array(
				'label'       => esc_html__( 'Position sur le tracé', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => array( '%' ),
				'range'       => array(
					'%' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'default'     => array(
					'unit' => '%',
					'size' => 50,
				),
				'description' => esc_html__( 'Positionne ce repère en pourcentage de la longueur du tracé affiché.', 'thecore-collectivity-management' ),
			)
		);

		return $repeater->get_controls();
	}

	/**
	 * Register a fixed route preset control group.
	 *
	 * @param string $prefix Control prefix.
	 * @param string $label  Visible label.
	 * @return void
	 */
	private function register_route_preset_controls( $prefix, $label ) {
		$this->add_control(
			$prefix . '_heading',
			array(
				'label'     => $label,
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			$prefix . '_color',
			array(
				'label' => esc_html__( 'Couleur', 'thecore-collectivity-management' ),
				'type'  => Controls_Manager::COLOR,
			)
		);

		$this->add_control(
			$prefix . '_icon_type',
			array(
				'label'   => esc_html__( 'Type d’icône', 'thecore-collectivity-management' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'route',
				'options' => $this->get_marker_icon_options(),
			)
		);

		$this->add_control(
			$prefix . '_elementor_icon',
			array(
				'label'     => esc_html__( 'Icône Elementor', 'thecore-collectivity-management' ),
				'type'      => Controls_Manager::ICONS,
				'skin'      => 'inline',
				'condition' => array(
					$prefix . '_icon_type' => 'elementor_icon',
				),
			)
		);

		$this->add_control(
			$prefix . '_icon_label',
			array(
				'label'       => esc_html__( 'Libellé de l’icône', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => false,
				'placeholder' => 'RT',
				'condition'   => array(
					$prefix . '_icon_type' => 'label',
				),
			)
		);

		$this->add_control(
			$prefix . '_custom_svg',
			array(
				'label'       => esc_html__( 'SVG personnalisé', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 6,
				'label_block' => true,
				'placeholder' => '<svg viewBox="0 0 24 24" ...>...</svg>',
				'description' => esc_html__( 'Collez un petit SVG monochrome. Il sera injecté tel quel dans l’icône du marqueur après nettoyage.', 'thecore-collectivity-management' ),
				'condition'   => array(
					$prefix . '_icon_type' => 'custom_svg',
				),
			)
		);

		$this->add_control(
			$prefix . '_route_stroke_width',
			array(
				'label'      => esc_html__( 'Épaisseur du tracé', 'thecore-collectivity-management' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 2,
						'max' => 16,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 4,
				),
			)
		);

		$this->add_control(
			$prefix . '_route_stroke_style',
			array(
				'label'   => esc_html__( 'Type de trait', 'thecore-collectivity-management' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'solid',
				'options' => array(
					'solid'   => esc_html__( 'Continu', 'thecore-collectivity-management' ),
					'dashed'  => esc_html__( 'Tirets', 'thecore-collectivity-management' ),
					'dotted'  => esc_html__( 'Pointillé', 'thecore-collectivity-management' ),
					'dashdot' => esc_html__( 'Tirets et points', 'thecore-collectivity-management' ),
				),
			)
		);

		$this->add_control(
			$prefix . '_route_icon_position',
			array(
				'label'      => esc_html__( 'Position de l’icône sur le parcours', 'thecore-collectivity-management' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( '%' ),
				'range'      => array(
					'%' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'default'    => array(
					'unit' => '%',
					'size' => 50,
				),
			)
		);

		$this->add_control(
			$prefix . '_route_trim_start',
			array(
				'label'       => esc_html__( 'Décalage au début du tracé', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => array( '%' ),
				'range'       => array(
					'%' => array(
						'min' => 0,
						'max' => 95,
					),
				),
				'default'     => array(
					'unit' => '%',
					'size' => 0,
				),
				'description' => esc_html__( '0 = aucun rognage. 100 = jusqu’au milieu du parcours.', 'thecore-collectivity-management' ),
			)
		);

		$this->add_control(
			$prefix . '_route_trim_end',
			array(
				'label'       => esc_html__( 'Décalage à la fin du tracé', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => array( '%' ),
				'range'       => array(
					'%' => array(
						'min' => 0,
						'max' => 95,
					),
				),
				'default'     => array(
					'unit' => '%',
					'size' => 0,
				),
				'description' => esc_html__( '0 = aucun rognage. 100 = jusqu’au milieu du parcours.', 'thecore-collectivity-management' ),
			)
		);

		$this->add_control(
			$prefix . '_route_icons',
			array(
				'label'       => esc_html__( 'Icônes supplémentaires du parcours', 'thecore-collectivity-management' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $this->get_route_icon_repeater_fields(),
				'title_field' => '{{{ marker_name || "Repère" }}}',
			)
		);
	}

	/**
	 * Return the shared icon options list.
	 *
	 * @return array
	 */
	private function get_marker_icon_options() {
		return array(
			'default'        => esc_html__( 'Par défaut', 'thecore-collectivity-management' ),
			'elementor_icon' => esc_html__( 'Icône Elementor / SVG', 'thecore-collectivity-management' ),
			'label'          => esc_html__( 'Libellé', 'thecore-collectivity-management' ),
			'beach'          => esc_html__( 'Plage', 'thecore-collectivity-management' ),
			'church'         => esc_html__( 'Église', 'thecore-collectivity-management' ),
			'commerce'       => esc_html__( 'Commerce', 'thecore-collectivity-management' ),
			'restaurant'     => esc_html__( 'Restaurant', 'thecore-collectivity-management' ),
			'house'          => esc_html__( 'Maison', 'thecore-collectivity-management' ),
			'monument'       => esc_html__( 'Patrimoine', 'thecore-collectivity-management' ),
			'viewpoint'      => esc_html__( 'Point de vue', 'thecore-collectivity-management' ),
			'info'           => esc_html__( 'Information', 'thecore-collectivity-management' ),
			'camera'         => esc_html__( 'Caméra', 'thecore-collectivity-management' ),
			'bus'            => esc_html__( 'Bus', 'thecore-collectivity-management' ),
			'parking'        => esc_html__( 'Parking', 'thecore-collectivity-management' ),
			'bike'           => esc_html__( 'Vélo', 'thecore-collectivity-management' ),
			'train'          => esc_html__( 'Train', 'thecore-collectivity-management' ),
			'route'          => esc_html__( 'Parcours', 'thecore-collectivity-management' ),
			'custom_svg'     => esc_html__( 'SVG personnalisé', 'thecore-collectivity-management' ),
		);
	}

	/**
	 * Return the fixed route preset options.
	 *
	 * @return array
	 */
	private function get_route_preset_options() {
		return array(
			'route_preset_a' => esc_html__( 'Preset A', 'thecore-collectivity-management' ),
			'route_preset_b' => esc_html__( 'Preset B', 'thecore-collectivity-management' ),
			'route_preset_c' => esc_html__( 'Preset C', 'thecore-collectivity-management' ),
			'route_preset_d' => esc_html__( 'Preset D', 'thecore-collectivity-management' ),
		);
	}

	/**
	 * Return route items as selectable options.
	 *
	 * @return array
	 */
	private function get_route_item_options() {
		$options = array();

		foreach ( $this->get_route_items_index() as $route_id => $title ) {
			$options[ (string) $route_id ] = $title;
		}

		return $options;
	}

	/**
	 * Build a route item index keyed by post ID.
	 *
	 * @return array<int,string>
	 */
	private function get_route_items_index() {
		$posts = get_posts(
			array(
				'post_type'      => TheCore_Collectivity_Maps_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => TheCore_Collectivity_Maps_Meta::META_GEOMETRY_TYPE,
						'value'   => TheCore_Collectivity_Maps_Meta::GEOMETRY_ROUTE,
						'compare' => '=',
					),
				),
			)
		);

		$index = array();
		foreach ( $posts as $post_id ) {
			$index[ (int) $post_id ] = get_the_title( $post_id );
		}

		$transport_lines = get_posts(
			array(
				'post_type'      => TheCore_Collectivity_Transports_Post_Types::POST_TYPE_LINE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		foreach ( $transport_lines as $post_id ) {
			$index[ 'transport-line-' . (int) $post_id ] = sprintf(
				/* translators: %s transport line title */
				__( '%s (transport)', 'thecore-collectivity-management' ),
				get_the_title( $post_id )
			);
		}

		asort( $index, SORT_NATURAL | SORT_FLAG_CASE );

		return $index;
	}

	/**
	 * Build the route preset payloads from widget settings.
	 *
	 * @param array $settings Widget settings.
	 * @return array
	 */
	private function get_route_presets_payload( array $settings ) {
		$payload = array();

		foreach ( $this->get_route_preset_options() as $preset_key => $preset_label ) {
			$source = array(
				'color'               => $settings[ $preset_key . '_color' ] ?? '',
				'icon_type'           => $settings[ $preset_key . '_icon_type' ] ?? 'route',
				'icon_label'          => $settings[ $preset_key . '_icon_label' ] ?? '',
				'custom_svg'          => $settings[ $preset_key . '_custom_svg' ] ?? '',
				'elementor_icon'      => $settings[ $preset_key . '_elementor_icon' ] ?? array(),
				'route_stroke_width'  => $settings[ $preset_key . '_route_stroke_width' ] ?? array(),
				'route_stroke_style'  => $settings[ $preset_key . '_route_stroke_style' ] ?? 'solid',
				'route_icon_position' => $settings[ $preset_key . '_route_icon_position' ] ?? array(),
				'route_trim_start'    => $settings[ $preset_key . '_route_trim_start' ] ?? array(),
				'route_trim_end'      => $settings[ $preset_key . '_route_trim_end' ] ?? array(),
				'route_icons'         => $settings[ $preset_key . '_route_icons' ] ?? array(),
			);

			$payload[ $preset_key ] = $this->build_style_payload( $source, $preset_label );
		}

		return $payload;
	}

	/**
	 * Normalize a style definition into frontend payload.
	 *
	 * @param array  $source Raw style source.
	 * @param string $name   Human label.
	 * @return array
	 */
	private function build_style_payload( array $source, $name = '' ) {
		$color               = isset( $source['color'] ) ? sanitize_hex_color( (string) $source['color'] ) : '';
		$icon_type           = isset( $source['icon_type'] ) ? sanitize_key( (string) $source['icon_type'] ) : 'default';
		$icon_label          = isset( $source['icon_label'] ) ? strtoupper( sanitize_text_field( (string) $source['icon_label'] ) ) : '';
		$icon_label          = substr( preg_replace( '/[^A-Z0-9À-Ý]/u', '', $icon_label ), 0, 3 );
		$custom_svg          = isset( $source['custom_svg'] ) ? $this->sanitize_svg_markup( (string) $source['custom_svg'] ) : '';
		$route_width         = isset( $source['route_stroke_width']['size'] ) ? absint( $source['route_stroke_width']['size'] ) : 4;
		$route_style         = isset( $source['route_stroke_style'] ) ? sanitize_key( (string) $source['route_stroke_style'] ) : 'solid';
		$route_icon_position = isset( $source['route_icon_position']['size'] ) ? max( 0, min( 100, (int) $source['route_icon_position']['size'] ) ) : 50;
		$route_trim_start    = isset( $source['route_trim_start']['size'] ) ? max( 0, min( 100, (int) $source['route_trim_start']['size'] ) ) : 0;
		$route_trim_end      = isset( $source['route_trim_end']['size'] ) ? max( 0, min( 100, (int) $source['route_trim_end']['size'] ) ) : 0;

		return array(
			'color'             => $color ? $color : '',
			'iconType'          => $icon_type ? $icon_type : 'default',
			'label'             => $icon_label,
			'customSvg'         => $custom_svg,
			'elementorIconHtml' => $this->get_elementor_icon_markup( $source['elementor_icon'] ?? array() ),
			'name'              => $name,
			'routeWeight'       => max( 2, $route_width ),
			'routeDashArray'    => $this->get_route_dash_array( $route_style ),
			'routeIconPosition' => $route_icon_position,
			'routeTrimStart'    => $route_trim_start,
			'routeTrimEnd'      => $route_trim_end,
			'routeIcons'        => $this->normalize_route_icons_payload( $source['route_icons'] ?? array() ),
		);
	}

	/**
	 * Normalize additional route icon rows into frontend payload.
	 *
	 * @param mixed $rows Raw repeater rows.
	 * @return array
	 */
	private function normalize_route_icons_payload( $rows ) {
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return array();
		}

		$payload = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$icon_type      = isset( $row['icon_type'] ) ? sanitize_key( (string) $row['icon_type'] ) : 'route';
			$icon_label     = isset( $row['icon_label'] ) ? strtoupper( sanitize_text_field( (string) $row['icon_label'] ) ) : '';
			$icon_label     = substr( preg_replace( '/[^A-Z0-9À-Ý]/u', '', $icon_label ), 0, 3 );
			$custom_svg     = isset( $row['custom_svg'] ) ? $this->sanitize_svg_markup( (string) $row['custom_svg'] ) : '';
			$position       = isset( $row['position']['size'] ) ? max( 0, min( 100, (int) $row['position']['size'] ) ) : 50;
			$size           = isset( $row['icon_size']['size'] ) ? max( 22, min( 56, (int) $row['icon_size']['size'] ) ) : 34;
			$color          = isset( $row['icon_color'] ) ? sanitize_hex_color( (string) $row['icon_color'] ) : '';
			$elementor_icon = $this->get_elementor_icon_markup( $row['elementor_icon'] ?? array() );
			$name           = isset( $row['marker_name'] ) ? sanitize_text_field( (string) $row['marker_name'] ) : '';
			$with_outline   = isset( $row['with_outline'] ) && 'yes' === $row['with_outline'];

			$payload[] = array(
				'name'              => $name,
				'iconType'          => $icon_type ? $icon_type : 'route',
				'label'             => $icon_label,
				'customSvg'         => $custom_svg,
				'elementorIconHtml' => $elementor_icon,
				'color'             => $color ? $color : '',
				'size'              => $size,
				'withOutline'       => $with_outline,
				'position'          => $position,
			);
		}

		return $payload;
	}

	/**
	 * Recalculate the payload stats after merging external sources.
	 *
	 * @param array $items Current payload items.
	 * @return array
	 */
	private function recalculate_stats( array $items ) {
		$points = 0;
		$routes = 0;

		foreach ( $items as $item ) {
			if ( empty( $item['geometryType'] ) ) {
				continue;
			}

			if ( TheCore_Collectivity_Maps_Meta::GEOMETRY_ROUTE === $item['geometryType'] ) {
				$routes++;
				continue;
			}

			$points++;
		}

		return array(
			'total'  => count( $items ),
			'points' => $points,
			'routes' => $routes,
		);
	}

	/**
	 * Build initial map view payload from widget settings.
	 *
	 * @param array $settings Widget settings.
	 * @return array
	 */
	private function get_view_payload( array $settings ) {
		$mode = isset( $settings['initial_view_mode'] ) ? sanitize_key( (string) $settings['initial_view_mode'] ) : 'fit_bounds';
		if ( 'manual' !== $mode ) {
			return array(
				'mode' => 'fit_bounds',
			);
		}

		$lat  = isset( $settings['initial_center_lat'] ) && '' !== (string) $settings['initial_center_lat'] ? floatval( $settings['initial_center_lat'] ) : null;
		$lng  = isset( $settings['initial_center_lng'] ) && '' !== (string) $settings['initial_center_lng'] ? floatval( $settings['initial_center_lng'] ) : null;
		$zoom = isset( $settings['initial_zoom'] ) && '' !== (string) $settings['initial_zoom'] ? floatval( $settings['initial_zoom'] ) : 13;

		$is_valid = is_numeric( $lat ) && is_numeric( $lng ) && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;

		return array(
			'mode' => $is_valid ? 'manual' : 'fit_bounds',
			'lat'  => $is_valid ? (float) $lat : null,
			'lng'  => $is_valid ? (float) $lng : null,
			'zoom' => max( 1, min( 20, (float) $zoom ) ),
		);
	}

	/**
	 * Build popup-related CSS variable declarations from widget settings.
	 *
	 * @param array $settings Widget settings.
	 * @return array
	 */
	private function get_popup_style_variables( array $settings ) {
		$variables = array(
			'--tccm-map-popup-bg'             => $settings['popup_background_color'] ?? '',
			'--tccm-map-popup-text'           => $settings['popup_text_color'] ?? '',
			'--tccm-map-popup-muted'          => $settings['popup_muted_color'] ?? '',
			'--tccm-map-popup-title'          => $settings['popup_title_color'] ?? '',
			'--tccm-map-popup-section-title'  => $settings['popup_section_title_color'] ?? '',
			'--tccm-map-popup-border'         => $settings['popup_border_color'] ?? '',
			'--tccm-map-popup-link'           => $settings['popup_link_color'] ?? '',
			'--tccm-map-popup-tag-bg'         => $settings['popup_tag_background_color'] ?? '',
			'--tccm-map-popup-tag-text'       => $settings['popup_tag_text_color'] ?? '',
			'--tccm-map-popup-chip-bg'        => $settings['popup_chip_background_color'] ?? '',
			'--tccm-map-popup-chip-text'      => $settings['popup_chip_text_color'] ?? '',
			'--tccm-map-popup-primary-bg'     => $settings['popup_primary_button_background_color'] ?? '',
			'--tccm-map-popup-primary-text'   => $settings['popup_primary_button_text_color'] ?? '',
			'--tccm-map-popup-primary-border' => $settings['popup_primary_button_border_color'] ?? '',
			'--tccm-map-popup-secondary-bg'   => $settings['popup_secondary_button_background_color'] ?? '',
			'--tccm-map-popup-secondary-text' => $settings['popup_secondary_button_text_color'] ?? '',
			'--tccm-map-popup-secondary-border' => $settings['popup_secondary_button_border_color'] ?? '',
		);

		$declarations = array();
		foreach ( $variables as $name => $value ) {
			$sanitized_value = $this->sanitize_css_color_value( $value );
			if ( '' === $sanitized_value ) {
				continue;
			}

			$declarations[] = $name . ':' . $sanitized_value;
		}

		return $declarations;
	}

	/**
	 * Sanitize CSS color-like values for inline custom properties.
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
