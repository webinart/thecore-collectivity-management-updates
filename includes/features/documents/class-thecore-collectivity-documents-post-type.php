<?php
/**
 * Documents post type and taxonomies.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Documents_Post_Type {
	/**
	 * Post type slug.
	 */
	const POST_TYPE = 'tccm_document';

	/**
	 * Taxonomy slugs.
	 */
	const TAXONOMY_THEME = 'tccm_doc_theme';
	const TAXONOMY_TYPE  = 'tccm_doc_type';

	/**
	 * Seed flags.
	 */
	const OPTION_THEME_TERMS_SEEDED = 'tccm_doc_theme_terms_seeded';
	const OPTION_TYPE_TERMS_SEEDED  = 'tccm_doc_type_terms_seeded';

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
		$show_in_menu = class_exists( 'TheCore_Collectivity_Procedures_Post_Type', false )
			? 'edit.php?post_type=' . TheCore_Collectivity_Procedures_Post_Type::POST_TYPE
			: true;

		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'               => __( 'Documents', 'thecore-collectivity-management' ),
					'singular_name'      => __( 'Document', 'thecore-collectivity-management' ),
					'add_new'            => __( 'Ajouter', 'thecore-collectivity-management' ),
					'add_new_item'       => __( 'Ajouter un document', 'thecore-collectivity-management' ),
					'edit_item'          => __( 'Modifier le document', 'thecore-collectivity-management' ),
					'new_item'           => __( 'Nouveau document', 'thecore-collectivity-management' ),
					'view_item'          => __( 'Voir le document', 'thecore-collectivity-management' ),
					'search_items'       => __( 'Rechercher des documents', 'thecore-collectivity-management' ),
					'all_items'          => __( 'Documents', 'thecore-collectivity-management' ),
					'not_found'          => __( 'Aucun document trouve', 'thecore-collectivity-management' ),
					'not_found_in_trash' => __( 'Aucun document dans la corbeille', 'thecore-collectivity-management' ),
					'menu_name'          => __( 'Documents', 'thecore-collectivity-management' ),
				),
				'public'             => true,
				'publicly_queryable' => false,
				'exclude_from_search'=> true,
				'show_ui'            => true,
					'show_in_menu'       => $show_in_menu,
				'show_in_rest'       => true,
				'supports'           => array( 'title', 'editor' ),
				'has_archive'        => false,
				'rewrite'            => false,
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
					'name'          => __( 'Themes documentaires', 'thecore-collectivity-management' ),
					'singular_name' => __( 'Theme documentaire', 'thecore-collectivity-management' ),
					'menu_name'     => __( 'Themes des documents', 'thecore-collectivity-management' ),
				),
				'public'             => false,
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
					'name'          => __( 'Types de documents', 'thecore-collectivity-management' ),
					'singular_name' => __( 'Type de document', 'thecore-collectivity-management' ),
					'menu_name'     => __( 'Types de documents', 'thecore-collectivity-management' ),
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
				'cerfa'       => __( 'Cerfa', 'thecore-collectivity-management' ),
				'formulaire'  => __( 'Formulaire', 'thecore-collectivity-management' ),
				'guide'       => __( 'Guide', 'thecore-collectivity-management' ),
				'reglement'   => __( 'Reglement', 'thecore-collectivity-management' ),
				'plan'        => __( 'Plan', 'thecore-collectivity-management' ),
				'deliberation'=> __( 'Deliberation', 'thecore-collectivity-management' ),
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
	 * Remove redundant submenus.
	 */
	public function cleanup_admin_submenus() {
		if ( ! class_exists( 'TheCore_Collectivity_Procedures_Post_Type', false ) ) {
			return;
		}

		remove_submenu_page(
			'edit.php?post_type=' . TheCore_Collectivity_Procedures_Post_Type::POST_TYPE,
			'post-new.php?post_type=' . self::POST_TYPE
		);
	}
}
