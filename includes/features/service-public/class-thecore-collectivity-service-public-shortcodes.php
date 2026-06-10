<?php
/**
 * Service-public shortcodes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Service_Public_Shortcodes {
	/**
	 * Renderer.
	 *
	 * @var TheCore_Collectivity_Service_Public_Renderer
	 */
	private $renderer;

	/**
	 * Constructor.
	 *
	 * @param TheCore_Collectivity_Service_Public_Renderer $renderer Renderer.
	 */
	public function __construct( TheCore_Collectivity_Service_Public_Renderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_shortcode( 'tccm_service_public_fiche', array( $this, 'render_fiche_shortcode' ) );
		add_shortcode( 'tccm_service_public_sommaire', array( $this, 'render_sommaire_shortcode' ) );
		add_shortcode( 'tccm_service_public_recherche', array( $this, 'render_recherche_shortcode' ) );
	}

	/**
	 * Render fiche shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function render_fiche_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'audience'    => 'particuliers',
				'id'          => '',
				'show_title'  => '1',
				'show_source' => '1',
			),
			(array) $atts,
			'tccm_service_public_fiche'
		);

		if ( empty( $atts['id'] ) ) {
			return '';
		}

		return $this->renderer->render_item(
			sanitize_key( $atts['audience'] ),
			sanitize_text_field( $atts['id'] ),
			array(
				'show_title'  => (bool) intval( $atts['show_title'] ),
				'show_source' => (bool) intval( $atts['show_source'] ),
			)
		);
	}

	/**
	 * Render summary shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function render_sommaire_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'audience' => 'particuliers',
				'limit'    => 24,
			),
			(array) $atts,
			'tccm_service_public_sommaire'
		);

		return $this->renderer->render_summary( sanitize_key( $atts['audience'] ), (int) $atts['limit'] );
	}

	/**
	 * Render search shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function render_recherche_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'audience' => '',
				'limit'    => 20,
			),
			(array) $atts,
			'tccm_service_public_recherche'
		);

		return $this->renderer->render_search( sanitize_key( $atts['audience'] ), '', (int) $atts['limit'] );
	}
}
