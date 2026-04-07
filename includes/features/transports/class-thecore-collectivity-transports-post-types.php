<?php
/**
 * Transport post types and taxonomies.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Transports_Post_Types {
	/**
	 * Post type slugs.
	 */
	const POST_TYPE_LINE  = 'blv_transport_line';
	const POST_TYPE_PLACE = 'blv_transport_place';

	/**
	 * Taxonomy slug.
	 */
	const TAXONOMY_MODE = 'bellevue_transport_mode';

	/**
	 * Default mode term slugs.
	 */
	const MODE_BUS     = 'bus';
	const MODE_PARKING = 'parking';
	const MODE_BIKE    = 'velo';
	const MODE_TRAIN   = 'train';

	/**
	 * Seed flag.
	 */
	const OPTION_MODE_TERMS_SEEDED = 'bellevue_transport_mode_terms_seeded';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'admin_init', array( $this, 'maybe_seed_default_terms' ) );
		add_action( 'admin_menu', array( $this, 'cleanup_admin_submenus' ), 999 );
	}

	/**
	 * Register transport post types.
	 */
	public function register_post_types() {
		register_post_type(
			self::POST_TYPE_LINE,
			array(
				'labels' => array(
					'name'               => __( 'Lignes de transport', 'bellevue' ),
					'singular_name'      => __( 'Ligne de transport', 'bellevue' ),
					'add_new_item'       => __( 'Ajouter une ligne', 'bellevue' ),
					'edit_item'          => __( 'Modifier la ligne', 'bellevue' ),
					'new_item'           => __( 'Nouvelle ligne', 'bellevue' ),
					'view_item'          => __( 'Voir la ligne', 'bellevue' ),
					'search_items'       => __( 'Rechercher des lignes', 'bellevue' ),
					'all_items'          => __( 'Lignes', 'bellevue' ),
					'not_found'          => __( 'Aucune ligne trouvee', 'bellevue' ),
					'not_found_in_trash' => __( 'Aucune ligne dans la corbeille', 'bellevue' ),
					'menu_name'          => __( 'Transports', 'bellevue' ),
				),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'menu_icon'          => 'dashicons-location',
				'menu_position'      => 26,
				'supports'           => array( 'title' ),
				'has_archive'        => false,
				'rewrite'            => false,
			)
		);

		register_post_type(
			self::POST_TYPE_PLACE,
			array(
				'labels' => array(
					'name'               => __( 'Lieux de transport', 'bellevue' ),
					'singular_name'      => __( 'Lieu de transport', 'bellevue' ),
					'add_new_item'       => __( 'Ajouter un lieu', 'bellevue' ),
					'edit_item'          => __( 'Modifier le lieu', 'bellevue' ),
					'new_item'           => __( 'Nouveau lieu', 'bellevue' ),
					'view_item'          => __( 'Voir le lieu', 'bellevue' ),
					'search_items'       => __( 'Rechercher des lieux', 'bellevue' ),
					'all_items'          => __( 'Lieux', 'bellevue' ),
					'not_found'          => __( 'Aucun lieu trouve', 'bellevue' ),
					'not_found_in_trash' => __( 'Aucun lieu dans la corbeille', 'bellevue' ),
					'menu_name'          => __( 'Lieux', 'bellevue' ),
				),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => 'edit.php?post_type=' . self::POST_TYPE_LINE,
				'show_in_rest'       => true,
				'menu_icon'          => 'dashicons-location-alt',
				'supports'           => array( 'title' ),
				'has_archive'        => false,
				'rewrite'            => false,
			)
		);
	}

	/**
	 * Register transport mode taxonomy.
	 */
	public function register_taxonomy() {
		register_taxonomy(
			self::TAXONOMY_MODE,
			array( self::POST_TYPE_LINE, self::POST_TYPE_PLACE ),
			array(
				'labels' => array(
					'name'          => __( 'Modes de transport', 'bellevue' ),
					'singular_name' => __( 'Mode de transport', 'bellevue' ),
					'search_items'  => __( 'Rechercher des modes', 'bellevue' ),
					'all_items'     => __( 'Tous les modes', 'bellevue' ),
					'edit_item'     => __( 'Modifier le mode', 'bellevue' ),
					'update_item'   => __( 'Mettre a jour le mode', 'bellevue' ),
					'add_new_item'  => __( 'Ajouter un mode', 'bellevue' ),
					'new_item_name' => __( 'Nouveau mode', 'bellevue' ),
					'menu_name'     => __( 'Modes', 'bellevue' ),
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
	 * Seed default mode terms.
	 */
	public function maybe_seed_default_terms() {
		if ( get_option( self::OPTION_MODE_TERMS_SEEDED ) ) {
			return;
		}

		if ( ! taxonomy_exists( self::TAXONOMY_MODE ) ) {
			return;
		}

		$terms = array(
			self::MODE_BUS     => __( 'Bus', 'bellevue' ),
			self::MODE_PARKING => __( 'Stationnement', 'bellevue' ),
			self::MODE_BIKE    => __( 'Velo', 'bellevue' ),
			self::MODE_TRAIN   => __( 'Train', 'bellevue' ),
		);

		foreach ( $terms as $slug => $name ) {
			if ( term_exists( $slug, self::TAXONOMY_MODE ) ) {
				continue;
			}

			wp_insert_term(
				$name,
				self::TAXONOMY_MODE,
				array(
					'slug' => $slug,
				)
			);
		}

		update_option( self::OPTION_MODE_TERMS_SEEDED, '1', false );
	}

	/**
	 * Remove redundant transport submenus added by WordPress.
	 */
	public function cleanup_admin_submenus() {
		remove_submenu_page(
			'edit.php?post_type=' . self::POST_TYPE_LINE,
			'post-new.php?post_type=' . self::POST_TYPE_LINE
		);
	}
}
