<?php
/**
 * Maps post type and local taxonomies.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Maps_Post_Type {
	/**
	 * Post type slug.
	 */
	const POST_TYPE = 'tccm_map_item';

	/**
	 * Local taxonomies.
	 */
	const TAXONOMY_UNIVERSE      = 'tccm_map_universe';
	const TAXONOMY_CATEGORY      = 'tccm_map_category';
	const TAXONOMY_ACCESSIBILITY = 'tccm_map_accessibility';

	/**
	 * Seed flags.
	 */
	const OPTION_UNIVERSE_TERMS_SEEDED      = 'tccm_map_universe_terms_seeded';
	const OPTION_CATEGORY_TERMS_SEEDED      = 'tccm_map_category_terms_seeded';
	const OPTION_ACCESSIBILITY_TERMS_SEEDED = 'tccm_map_accessibility_terms_seeded';

	/**
	 * Accessibility term slug.
	 */
	const TERM_ACCESSIBLE = 'accessible';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_action( 'admin_init', array( $this, 'maybe_seed_default_terms' ) );
		add_action( 'admin_menu', array( $this, 'cleanup_admin_submenus' ), 999 );
	}

	/**
	 * Register the unified map item post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'               => __( 'Cartographie', 'thecore-collectivity-management' ),
					'singular_name'      => __( 'Élément cartographique', 'thecore-collectivity-management' ),
					'add_new'            => __( 'Ajouter', 'thecore-collectivity-management' ),
					'add_new_item'       => __( 'Ajouter un élément cartographique', 'thecore-collectivity-management' ),
					'edit_item'          => __( 'Modifier l’élément cartographique', 'thecore-collectivity-management' ),
					'new_item'           => __( 'Nouvel élément cartographique', 'thecore-collectivity-management' ),
					'view_item'          => __( 'Voir l’élément cartographique', 'thecore-collectivity-management' ),
					'search_items'       => __( 'Rechercher des éléments cartographiques', 'thecore-collectivity-management' ),
					'all_items'          => __( 'Éléments cartographiques', 'thecore-collectivity-management' ),
					'not_found'          => __( 'Aucun élément cartographique trouvé', 'thecore-collectivity-management' ),
					'not_found_in_trash' => __( 'Aucun élément cartographique dans la corbeille', 'thecore-collectivity-management' ),
					'menu_name'          => __( 'Cartographie', 'thecore-collectivity-management' ),
				),
				'public'             => true,
				'publicly_queryable' => true,
				'exclude_from_search'=> true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'menu_icon'          => 'dashicons-location-alt',
				'menu_position'      => 28,
				'supports'           => array( 'title', 'editor', 'thumbnail' ),
				'has_archive'        => false,
				'rewrite'            => array(
					'slug'       => 'cartographie',
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Register local taxonomies.
	 *
	 * @return void
	 */
	public function register_taxonomies() {
		register_taxonomy(
			self::TAXONOMY_UNIVERSE,
			array( self::POST_TYPE ),
			array(
				'labels' => array(
					'name'          => __( 'Univers cartographiques', 'thecore-collectivity-management' ),
					'singular_name' => __( 'Univers cartographique', 'thecore-collectivity-management' ),
					'menu_name'     => __( 'Univers', 'thecore-collectivity-management' ),
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
			self::TAXONOMY_CATEGORY,
			array( self::POST_TYPE ),
			array(
				'labels' => array(
					'name'          => __( 'Catégories cartographiques', 'thecore-collectivity-management' ),
					'singular_name' => __( 'Catégorie cartographique', 'thecore-collectivity-management' ),
					'menu_name'     => __( 'Catégories', 'thecore-collectivity-management' ),
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
			self::TAXONOMY_ACCESSIBILITY,
			array( self::POST_TYPE ),
			array(
				'labels' => array(
					'name'          => __( 'Accessibilité cartographique', 'thecore-collectivity-management' ),
					'singular_name' => __( 'Accès', 'thecore-collectivity-management' ),
					'menu_name'     => __( 'Accessibilité', 'thecore-collectivity-management' ),
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
	 * Synchronize the local vocabularies.
	 *
	 * @return void
	 */
	public function maybe_seed_default_terms() {
		$this->sync_terms(
			self::TAXONOMY_UNIVERSE,
			self::OPTION_UNIVERSE_TERMS_SEEDED,
			array(
				array(
					'name' => __( 'Patrimoine', 'thecore-collectivity-management' ),
					'slug' => 'patrimoine',
				),
				array(
					'name' => __( 'Itinéraires', 'thecore-collectivity-management' ),
					'slug' => 'itineraires',
				),
				array(
					'name' => __( 'Tourisme', 'thecore-collectivity-management' ),
					'slug' => 'tourisme',
				),
				array(
					'name' => __( 'Équipements', 'thecore-collectivity-management' ),
					'slug' => 'equipements',
				),
			),
			array(
				'randonnees' => 'itineraires',
			)
		);

		$this->sync_terms(
			self::TAXONOMY_CATEGORY,
			self::OPTION_CATEGORY_TERMS_SEEDED,
			array(
				array(
					'name' => __( 'Plage', 'thecore-collectivity-management' ),
					'slug' => 'plage',
				),
				array(
					'name' => __( 'Chapelle', 'thecore-collectivity-management' ),
					'slug' => 'chapelle',
				),
				array(
					'name' => __( 'Église', 'thecore-collectivity-management' ),
					'slug' => 'eglise',
				),
				array(
					'name' => __( 'Maison historique', 'thecore-collectivity-management' ),
					'slug' => 'maison-historique',
				),
				array(
					'name' => __( 'Campanile', 'thecore-collectivity-management' ),
					'slug' => 'campanile',
				),
				array(
					'name' => __( 'Dolmen', 'thecore-collectivity-management' ),
					'slug' => 'dolmen',
				),
				array(
					'name' => __( 'Office de tourisme', 'thecore-collectivity-management' ),
					'slug' => 'office-de-tourisme',
				),
				array(
					'name' => __( 'Commerce', 'thecore-collectivity-management' ),
					'slug' => 'commerce',
				),
				array(
					'name' => __( 'Restaurant', 'thecore-collectivity-management' ),
					'slug' => 'restaurant',
				),
				array(
					'name' => __( 'Point de vue', 'thecore-collectivity-management' ),
					'slug' => 'point-de-vue',
				),
				array(
					'name' => __( 'Circuit', 'thecore-collectivity-management' ),
					'slug' => 'circuit',
				),
				array(
					'name' => __( 'Balade', 'thecore-collectivity-management' ),
					'slug' => 'balade',
				),
				array(
					'name' => __( 'Randonnée', 'thecore-collectivity-management' ),
					'slug' => 'randonnee',
				),
				array(
					'name' => __( 'Fontaine', 'thecore-collectivity-management' ),
					'slug' => 'fontaine',
				),
			),
			array()
		);

		$this->sync_terms(
			self::TAXONOMY_ACCESSIBILITY,
			self::OPTION_ACCESSIBILITY_TERMS_SEEDED,
			array(
				array(
					'name' => __( 'Accessible', 'thecore-collectivity-management' ),
					'slug' => self::TERM_ACCESSIBLE,
				),
			)
		);
	}

	/**
	 * Remove the redundant "add new" submenu.
	 *
	 * @return void
	 */
	public function cleanup_admin_submenus() {
		remove_submenu_page(
			'edit.php?post_type=' . self::POST_TYPE,
			'post-new.php?post_type=' . self::POST_TYPE
		);
	}

	/**
	 * Synchronize one taxonomy vocabulary.
	 *
	 * @param string $taxonomy   Taxonomy slug.
	 * @param string $option_key Seed flag option.
	 * @param array  $terms      Terms to upsert.
	 * @param array  $aliases    Optional old slug => new slug map.
	 * @return void
	 */
	private function sync_terms( $taxonomy, $option_key, array $terms, array $aliases = array() ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$has_error = false;

		foreach ( $aliases as $old_slug => $new_slug ) {
			$old_term = get_term_by( 'slug', sanitize_title( (string) $old_slug ), $taxonomy );
			$new_term = get_term_by( 'slug', sanitize_title( (string) $new_slug ), $taxonomy );

			if ( ! $old_term || $new_term ) {
				continue;
			}

			$target_name = '';
			foreach ( $terms as $term ) {
				if ( isset( $term['slug'] ) && sanitize_title( (string) $term['slug'] ) === sanitize_title( (string) $new_slug ) ) {
					$target_name = isset( $term['name'] ) ? (string) $term['name'] : '';
					break;
				}
			}

			$updated = wp_update_term(
				(int) $old_term->term_id,
				$taxonomy,
				array(
					'slug' => sanitize_title( (string) $new_slug ),
					'name' => $target_name ? $target_name : $old_term->name,
				)
			);

			if ( is_wp_error( $updated ) ) {
				$has_error = true;
			}
		}

		foreach ( $terms as $term ) {
			$slug = isset( $term['slug'] ) ? sanitize_title( (string) $term['slug'] ) : '';
			$name = isset( $term['name'] ) ? (string) $term['name'] : '';

			if ( '' === $slug || '' === $name ) {
				continue;
			}

			$existing = get_term_by( 'slug', $slug, $taxonomy );
			if ( $existing ) {
				if ( $existing->name !== $name ) {
					$updated = wp_update_term(
						(int) $existing->term_id,
						$taxonomy,
						array(
							'name' => $name,
						)
					);

					if ( is_wp_error( $updated ) ) {
						$has_error = true;
					}
				}

				continue;
			}

			$inserted = wp_insert_term(
				$name,
				$taxonomy,
				array(
					'slug' => $slug,
				)
			);

			if ( is_wp_error( $inserted ) ) {
				$has_error = true;
			}
		}

		if ( ! $has_error ) {
			update_option( $option_key, '1', false );
		}
	}
}
