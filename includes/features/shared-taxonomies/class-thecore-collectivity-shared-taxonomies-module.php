<?php
/**
 * Shared editorial taxonomies across content types.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Shared_Taxonomies_Module {
	/**
	 * Shared taxonomy slugs.
	 */
	const TAXONOMY_THEME     = 'tccm_theme';
	const TAXONOMY_AUDIENCE  = 'tccm_audience';
	const TAXONOMY_TERRITORY = 'tccm_territory';

	/**
	 * Seed flags.
	 */
	const OPTION_THEME_TERMS_SEEDED     = 'tccm_theme_terms_seeded';
	const OPTION_AUDIENCE_TERMS_SEEDED  = 'tccm_audience_terms_seeded';
	const OPTION_TERRITORY_TERMS_SEEDED = 'tccm_territory_terms_seeded';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_taxonomies' ), 20 );
		add_action( 'admin_init', array( $this, 'maybe_seed_default_terms' ) );
	}

	/**
	 * Register shared taxonomies.
	 *
	 * @return void
	 */
	public function register_taxonomies() {
		$post_types = $this->get_object_types();

		register_taxonomy(
			self::TAXONOMY_THEME,
			$post_types,
			array(
				'labels' => array(
					'name'          => __( 'Thèmes', 'thecore-collectivity-management' ),
					'singular_name' => __( 'Thème', 'thecore-collectivity-management' ),
					'search_items'  => __( 'Rechercher des thèmes', 'thecore-collectivity-management' ),
					'all_items'     => __( 'Tous les thèmes', 'thecore-collectivity-management' ),
					'edit_item'     => __( 'Modifier le thème', 'thecore-collectivity-management' ),
					'update_item'   => __( 'Mettre à jour le thème', 'thecore-collectivity-management' ),
					'add_new_item'  => __( 'Ajouter un thème', 'thecore-collectivity-management' ),
					'new_item_name' => __( 'Nouveau thème', 'thecore-collectivity-management' ),
					'menu_name'     => __( 'Thèmes transversaux', 'thecore-collectivity-management' ),
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
			self::TAXONOMY_AUDIENCE,
			$post_types,
			array(
				'labels' => array(
					'name'          => __( 'Publics', 'thecore-collectivity-management' ),
					'singular_name' => __( 'Public', 'thecore-collectivity-management' ),
					'search_items'  => __( 'Rechercher des publics', 'thecore-collectivity-management' ),
					'all_items'     => __( 'Tous les publics', 'thecore-collectivity-management' ),
					'edit_item'     => __( 'Modifier le public', 'thecore-collectivity-management' ),
					'update_item'   => __( 'Mettre à jour le public', 'thecore-collectivity-management' ),
					'add_new_item'  => __( 'Ajouter un public', 'thecore-collectivity-management' ),
					'new_item_name' => __( 'Nouveau public', 'thecore-collectivity-management' ),
					'menu_name'     => __( 'Publics', 'thecore-collectivity-management' ),
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

		register_taxonomy(
			self::TAXONOMY_TERRITORY,
			$post_types,
			array(
				'labels' => array(
					'name'          => __( 'Territoires', 'thecore-collectivity-management' ),
					'singular_name' => __( 'Territoire', 'thecore-collectivity-management' ),
					'search_items'  => __( 'Rechercher des territoires', 'thecore-collectivity-management' ),
					'all_items'     => __( 'Tous les territoires', 'thecore-collectivity-management' ),
					'edit_item'     => __( 'Modifier le territoire', 'thecore-collectivity-management' ),
					'update_item'   => __( 'Mettre à jour le territoire', 'thecore-collectivity-management' ),
					'add_new_item'  => __( 'Ajouter un territoire', 'thecore-collectivity-management' ),
					'new_item_name' => __( 'Nouveau territoire', 'thecore-collectivity-management' ),
					'menu_name'     => __( 'Territoires', 'thecore-collectivity-management' ),
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
	}

	/**
	 * Seed default terms for shared taxonomies.
	 *
	 * @return void
	 */
	public function maybe_seed_default_terms() {
		$this->seed_terms(
			self::TAXONOMY_THEME,
			self::OPTION_THEME_TERMS_SEEDED,
			array(
				array(
					'name' => __( 'Culture', 'thecore-collectivity-management' ),
					'slug' => 'culture',
				),
				array(
					'name' => __( 'Patrimoine', 'thecore-collectivity-management' ),
					'slug' => 'patrimoine',
				),
				array(
					'name' => __( 'Nature', 'thecore-collectivity-management' ),
					'slug' => 'nature',
				),
				array(
					'name' => __( 'Mobilité', 'thecore-collectivity-management' ),
					'slug' => 'mobilite',
				),
				array(
					'name' => __( 'Jeunesse', 'thecore-collectivity-management' ),
					'slug' => 'jeunesse',
				),
				array(
					'name' => __( 'Sport', 'thecore-collectivity-management' ),
					'slug' => 'sport',
				),
				array(
					'name' => __( 'Solidarité', 'thecore-collectivity-management' ),
					'slug' => 'solidarite',
				),
				array(
					'name' => __( 'Urbanisme', 'thecore-collectivity-management' ),
					'slug' => 'urbanisme',
				),
				array(
					'name' => __( 'Vie pratique', 'thecore-collectivity-management' ),
					'slug' => 'vie-pratique',
				),
				array(
					'name' => __( 'Citoyenneté', 'thecore-collectivity-management' ),
					'slug' => 'citoyennete',
				),
				array(
					'name' => __( 'Tourisme', 'thecore-collectivity-management' ),
					'slug' => 'tourisme',
				),
			)
		);

		$this->seed_terms(
			self::TAXONOMY_AUDIENCE,
			self::OPTION_AUDIENCE_TERMS_SEEDED,
			array(
				array(
					'name' => __( 'Tous publics', 'thecore-collectivity-management' ),
					'slug' => 'tous-publics',
				),
				array(
					'name' => __( 'Familles', 'thecore-collectivity-management' ),
					'slug' => 'familles',
				),
				array(
					'name' => __( 'Enfants', 'thecore-collectivity-management' ),
					'slug' => 'enfants',
				),
				array(
					'name' => __( 'Adolescents', 'thecore-collectivity-management' ),
					'slug' => 'adolescents',
				),
				array(
					'name' => __( 'Jeunes adultes', 'thecore-collectivity-management' ),
					'slug' => 'jeunes-adultes',
				),
				array(
					'name' => __( 'Seniors', 'thecore-collectivity-management' ),
					'slug' => 'seniors',
				),
				array(
					'name' => __( 'Associations', 'thecore-collectivity-management' ),
					'slug' => 'associations',
				),
				array(
					'name' => __( 'Nouveaux habitants', 'thecore-collectivity-management' ),
					'slug' => 'nouveaux-habitants',
				),
				array(
					'name' => __( 'Personnes à mobilité réduite', 'thecore-collectivity-management' ),
					'slug' => 'personnes-a-mobilite-reduite',
				),
				array(
					'name' => __( 'Touristes', 'thecore-collectivity-management' ),
					'slug' => 'touristes',
				),
			)
		);

		$this->seed_terms(
			self::TAXONOMY_TERRITORY,
			self::OPTION_TERRITORY_TERMS_SEEDED,
			array(
				array(
					'name' => __( 'Toute la commune', 'thecore-collectivity-management' ),
					'slug' => 'toute-la-commune',
				),
				array(
					'name' => __( 'Centre-bourg', 'thecore-collectivity-management' ),
					'slug' => 'centre-bourg',
				),
				array(
					'name' => __( 'Gare', 'thecore-collectivity-management' ),
					'slug' => 'gare',
				),
				array(
					'name' => __( 'Quartier Nord', 'thecore-collectivity-management' ),
					'slug' => 'quartier-nord',
				),
				array(
					'name' => __( 'Quartier Sud', 'thecore-collectivity-management' ),
					'slug' => 'quartier-sud',
				),
				array(
					'name' => __( 'Quartier Est', 'thecore-collectivity-management' ),
					'slug' => 'quartier-est',
				),
				array(
					'name' => __( 'Quartier Ouest', 'thecore-collectivity-management' ),
					'slug' => 'quartier-ouest',
				),
				array(
					'name' => __( 'Bord de Marne', 'thecore-collectivity-management' ),
					'slug' => 'bord-de-marne',
				),
			)
		);
	}

	/**
	 * Resolve object types using the current plugin surface and future filters.
	 *
	 * @return array
	 */
	private function get_object_types() {
		$post_types = array( 'post' );

		if ( class_exists( 'TheCore_Collectivity_Procedures_Post_Type', false ) ) {
			$post_types[] = TheCore_Collectivity_Procedures_Post_Type::POST_TYPE;
		}

		if ( class_exists( 'TheCore_Collectivity_Documents_Post_Type', false ) ) {
			$post_types[] = TheCore_Collectivity_Documents_Post_Type::POST_TYPE;
		}

		if ( post_type_exists( 'tccm_map_item' ) ) {
			$post_types[] = 'tccm_map_item';
		}

		$post_types = apply_filters( 'thecore_collectivity/shared_taxonomy_post_types', $post_types );

		return array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', (array) $post_types )
				)
			)
		);
	}

	/**
	 * Synchronize one shared taxonomy vocabulary.
	 *
	 * @param string $taxonomy   Taxonomy slug.
	 * @param string $option_key Seed flag option.
	 * @param array  $terms      List of terms.
	 * @return void
	 */
	private function seed_terms( $taxonomy, $option_key, array $terms ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$has_error = false;

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
