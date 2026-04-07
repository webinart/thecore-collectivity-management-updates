<?php
/**
 * Alerts post type registration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Alerts_Post_Type {
	/**
	 * Post type slug.
	 */
	const POST_TYPE = 'bellevue_alert';

	/**
	 * Topic taxonomy slug.
	 */
	const TAXONOMY_TOPIC = 'bellevue_alert_topic';

	/**
	 * Seed flag.
	 */
	const OPTION_TOPIC_TERMS_SEEDED = 'bellevue_alert_topic_terms_seeded';

	/**
	 * Default transport topic term slug.
	 */
	const TOPIC_TRANSPORT = 'transport';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'admin_init', array( $this, 'maybe_seed_default_terms' ) );
	}

	/**
	 * Register post type.
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'               => __( 'Alertes', 'bellevue' ),
					'singular_name'      => __( 'Alerte', 'bellevue' ),
					'add_new'            => __( 'Ajouter', 'bellevue' ),
					'add_new_item'       => __( 'Ajouter une alerte', 'bellevue' ),
					'edit_item'          => __( 'Modifier l alerte', 'bellevue' ),
					'new_item'           => __( 'Nouvelle alerte', 'bellevue' ),
					'view_item'          => __( 'Voir l alerte', 'bellevue' ),
					'search_items'       => __( 'Rechercher des alertes', 'bellevue' ),
					'not_found'          => __( 'Aucune alerte trouvee', 'bellevue' ),
					'not_found_in_trash' => __( 'Aucune alerte dans la corbeille', 'bellevue' ),
					'menu_name'          => __( 'Alertes', 'bellevue' ),
				),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'menu_icon'          => 'dashicons-warning',
				'supports'           => array( 'title' ),
				'has_archive'        => false,
				'rewrite'            => false,
			)
		);
	}

	/**
	 * Register alert topic taxonomy.
	 */
	public function register_taxonomy() {
		register_taxonomy(
			self::TAXONOMY_TOPIC,
			array( self::POST_TYPE ),
			array(
				'labels' => array(
					'name'          => __( 'Themes d alerte', 'bellevue' ),
					'singular_name' => __( 'Theme d alerte', 'bellevue' ),
					'search_items'  => __( 'Rechercher des themes', 'bellevue' ),
					'all_items'     => __( 'Tous les themes', 'bellevue' ),
					'edit_item'     => __( 'Modifier le theme', 'bellevue' ),
					'update_item'   => __( 'Mettre a jour le theme', 'bellevue' ),
					'add_new_item'  => __( 'Ajouter un theme', 'bellevue' ),
					'new_item_name' => __( 'Nouveau theme', 'bellevue' ),
					'menu_name'     => __( 'Themes', 'bellevue' ),
				),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_admin_column'  => true,
				'show_in_rest'       => true,
				'hierarchical'       => false,
				'rewrite'            => false,
			)
		);
	}

	/**
	 * Seed default topics.
	 */
	public function maybe_seed_default_terms() {
		if ( get_option( self::OPTION_TOPIC_TERMS_SEEDED ) ) {
			return;
		}

		if ( ! taxonomy_exists( self::TAXONOMY_TOPIC ) ) {
			return;
		}

		$inserted = wp_insert_term(
			__( 'Transport', 'bellevue' ),
			self::TAXONOMY_TOPIC,
			array(
				'slug' => self::TOPIC_TRANSPORT,
			)
		);

		if ( ! is_wp_error( $inserted ) || term_exists( self::TOPIC_TRANSPORT, self::TAXONOMY_TOPIC ) ) {
			update_option( self::OPTION_TOPIC_TERMS_SEEDED, '1', false );
		}
	}
}
