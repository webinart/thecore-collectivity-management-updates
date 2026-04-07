<?php
/**
 * Procedures post type and taxonomies.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Procedures_Post_Type {
	/**
	 * Post type slug.
	 */
	const POST_TYPE = 'tccm_procedure';

	/**
	 * Taxonomy slugs.
	 */
	const TAXONOMY_THEME = 'tccm_proc_theme';
	const TAXONOMY_TYPE  = 'tccm_proc_type';

	/**
	 * Seed flags.
	 */
	const OPTION_THEME_TERMS_SEEDED = 'tccm_proc_theme_terms_seeded';
	const OPTION_TYPE_TERMS_SEEDED  = 'tccm_proc_type_terms_seeded';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_action( 'admin_init', array( $this, 'maybe_seed_default_terms' ) );
		add_action( 'admin_menu', array( $this, 'cleanup_admin_submenus' ), 999 );
	}

	/**
	 * Register post type.
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'               => __( 'Demarches', 'thecore-collectivity-management' ),
					'singular_name'      => __( 'Demarche', 'thecore-collectivity-management' ),
					'add_new'            => __( 'Ajouter', 'thecore-collectivity-management' ),
					'add_new_item'       => __( 'Ajouter une demarche', 'thecore-collectivity-management' ),
					'edit_item'          => __( 'Modifier la demarche', 'thecore-collectivity-management' ),
					'new_item'           => __( 'Nouvelle demarche', 'thecore-collectivity-management' ),
					'view_item'          => __( 'Voir la demarche', 'thecore-collectivity-management' ),
					'search_items'       => __( 'Rechercher des demarches', 'thecore-collectivity-management' ),
					'all_items'          => __( 'Demarches', 'thecore-collectivity-management' ),
					'not_found'          => __( 'Aucune demarche trouvee', 'thecore-collectivity-management' ),
					'not_found_in_trash' => __( 'Aucune demarche dans la corbeille', 'thecore-collectivity-management' ),
					'menu_name'          => __( 'Demarches', 'thecore-collectivity-management' ),
				),
				'public'             => true,
				'publicly_queryable' => true,
				'exclude_from_search'=> false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'menu_icon'          => 'dashicons-forms',
				'menu_position'      => 27,
				'supports'           => array( 'title', 'editor' ),
				'has_archive'        => false,
				'rewrite'            => array(
					'slug'       => 'demarches',
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Register taxonomies.
	 */
	public function register_taxonomies() {
		register_taxonomy(
			self::TAXONOMY_THEME,
			array( self::POST_TYPE ),
			array(
				'labels' => array(
					'name'          => __( 'Themes de demarches', 'thecore-collectivity-management' ),
					'singular_name' => __( 'Theme de demarche', 'thecore-collectivity-management' ),
					'menu_name'     => __( 'Themes', 'thecore-collectivity-management' ),
				),
				'public'             => true,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_admin_column'  => true,
				'show_in_rest'       => true,
				'hierarchical'       => true,
				'rewrite'            => false,
			)
		);

		register_taxonomy(
			self::TAXONOMY_TYPE,
			array( self::POST_TYPE ),
			array(
				'labels' => array(
					'name'          => __( 'Types de demarches', 'thecore-collectivity-management' ),
					'singular_name' => __( 'Type de demarche', 'thecore-collectivity-management' ),
					'menu_name'     => __( 'Types', 'thecore-collectivity-management' ),
				),
				'public'             => true,
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
	 * Seed default terms.
	 */
	public function maybe_seed_default_terms() {
		if ( taxonomy_exists( self::TAXONOMY_THEME ) && ! get_option( self::OPTION_THEME_TERMS_SEEDED ) ) {
			$themes = array(
				'etat-civil' => __( 'Etat civil', 'thecore-collectivity-management' ),
				'urbanisme'  => __( 'Urbanisme', 'thecore-collectivity-management' ),
			);

			foreach ( $themes as $slug => $label ) {
				if ( ! term_exists( $slug, self::TAXONOMY_THEME ) ) {
					wp_insert_term( $label, self::TAXONOMY_THEME, array( 'slug' => $slug ) );
				}
			}

			update_option( self::OPTION_THEME_TERMS_SEEDED, '1', false );
		}

		if ( taxonomy_exists( self::TAXONOMY_TYPE ) && ! get_option( self::OPTION_TYPE_TERMS_SEEDED ) ) {
			$types = array(
				'acte'        => __( 'Acte', 'thecore-collectivity-management' ),
				'permis'      => __( 'Permis', 'thecore-collectivity-management' ),
				'declaration' => __( 'Declaration', 'thecore-collectivity-management' ),
				'dossier'     => __( 'Dossier', 'thecore-collectivity-management' ),
				'rendez-vous' => __( 'Rendez-vous', 'thecore-collectivity-management' ),
			);

			foreach ( $types as $slug => $label ) {
				if ( ! term_exists( $slug, self::TAXONOMY_TYPE ) ) {
					wp_insert_term( $label, self::TAXONOMY_TYPE, array( 'slug' => $slug ) );
				}
			}

			update_option( self::OPTION_TYPE_TERMS_SEEDED, '1', false );
		}
	}

	/**
	 * Remove redundant submenus added by WordPress.
	 */
	public function cleanup_admin_submenus() {
		remove_submenu_page(
			'edit.php?post_type=' . self::POST_TYPE,
			'post-new.php?post_type=' . self::POST_TYPE
		);
	}
}
