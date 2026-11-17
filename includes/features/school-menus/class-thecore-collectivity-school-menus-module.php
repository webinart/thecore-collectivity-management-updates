<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-thecore-collectivity-school-menus-data.php';
require_once __DIR__ . '/class-thecore-collectivity-school-menus-admin.php';
require_once __DIR__ . '/class-thecore-collectivity-school-menus-downloads.php';
require_once __DIR__ . '/class-thecore-collectivity-school-menus-renderer.php';

final class TheCore_Collectivity_School_Menus_Module extends TheCore_Collectivity_Abstract_Module {
	public function get_id() { return 'school-menus'; }

	public function register_hooks() {
		add_action( 'init', array( $this, 'register_content' ), 6 );
		add_action( 'admin_init', array( $this, 'seed_markers' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_action( 'elementor/frontend/after_register_styles', array( $this, 'register_assets' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
		add_filter( 'wp_insert_post_data', array( 'TheCore_Collectivity_School_Menus_Data', 'guard_publication' ), 20, 2 );
		add_filter( 'elementor/element/is_dynamic_content', static function ( $dynamic, $data ) { return 'tccm-school-menus' === ( $data['widgetType'] ?? '' ) ? true : $dynamic; }, 20, 2 );
		TheCore_Collectivity_School_Menus_Admin::register_hooks();
		TheCore_Collectivity_School_Menus_Downloads::register_hooks();
	}

	public function register_content() {
		register_post_type( TheCore_Collectivity_School_Menus_Data::POST_TYPE, array(
			'labels' => array( 'name' => __( 'Menus scolaires', 'thecore-collectivity-management' ), 'singular_name' => __( 'Menu scolaire', 'thecore-collectivity-management' ), 'add_new_item' => __( 'Ajouter une semaine', 'thecore-collectivity-management' ), 'edit_item' => __( 'Modifier la semaine', 'thecore-collectivity-management' ), 'all_items' => __( 'Toutes les semaines', 'thecore-collectivity-management' ) ),
			'public' => false, 'publicly_queryable' => false, 'exclude_from_search' => true, 'show_ui' => true, 'show_in_rest' => false,
			'show_in_nav_menus' => false, 'show_in_admin_bar' => false, 'has_archive' => false, 'rewrite' => false, 'query_var' => false,
			'menu_icon' => 'dashicons-carrot', 'supports' => array( 'title', 'revisions' ), 'capability_type' => 'post', 'map_meta_cap' => true,
			'taxonomies' => array( TheCore_Collectivity_School_Menus_Data::TAXONOMY ),
		) );
		register_taxonomy( TheCore_Collectivity_School_Menus_Data::TAXONOMY, array( TheCore_Collectivity_School_Menus_Data::POST_TYPE ), array(
			'labels' => array( 'name' => __( 'Repères des aliments', 'thecore-collectivity-management' ), 'singular_name' => __( 'Repère', 'thecore-collectivity-management' ), 'add_new_item' => __( 'Ajouter un repère', 'thecore-collectivity-management' ), 'edit_item' => __( 'Modifier le repère', 'thecore-collectivity-management' ) ),
			'public' => false, 'publicly_queryable' => false, 'show_ui' => true, 'show_in_rest' => false, 'show_in_nav_menus' => false,
			'rewrite' => false, 'query_var' => false, 'meta_box_cb' => false, 'show_admin_column' => false, 'show_in_quick_edit' => false,
		) );
		foreach ( array( TheCore_Collectivity_School_Menus_Data::META => 'object', TheCore_Collectivity_School_Menus_Data::START => 'string', TheCore_Collectivity_School_Menus_Data::END => 'string' ) as $key => $type ) {
			register_post_meta( TheCore_Collectivity_School_Menus_Data::POST_TYPE, $key, array( 'type' => $type, 'single' => true, 'show_in_rest' => false, 'revisions_enabled' => true, 'auth_callback' => static function ( $allowed, $meta_key, $post_id ) { return current_user_can( 'edit_post', $post_id ); } ) );
		}
	}

	public function seed_markers() {
		if ( ! current_user_can( 'manage_categories' ) || get_option( 'tccm_school_markers_seeded' ) ) {
			return;
		}
		foreach ( array( 'organic' => array( 'Bio', 'leaf' ), 'homemade' => array( 'Fait maison', 'house' ), 'onsite' => array( 'Préparé sur place', 'chef' ) ) as $slug => $definition ) {
			$term = term_exists( $slug, TheCore_Collectivity_School_Menus_Data::TAXONOMY );
			if ( ! $term ) {
				$term = wp_insert_term( $definition[0], TheCore_Collectivity_School_Menus_Data::TAXONOMY, array( 'slug' => $slug ) );
				if ( is_wp_error( $term ) ) {
					return;
				}
				update_term_meta( $term['term_id'], '_tccm_marker_family', 'preparation' );
				update_term_meta( $term['term_id'], '_tccm_marker_icon', $definition[1] );
			}
		}
		update_option( 'tccm_school_markers_seeded', true, false );
	}

	public function register_assets() {
		$base = 'includes/features/school-menus/assets/';
		wp_register_style( 'tccm-school-menus', THECORE_COLLECTIVITY_MANAGEMENT_URL . $base . 'menus.css', array(), TheCore_Collectivity_Elementor::get_asset_version( $base . 'menus.css' ) );
		wp_register_script( 'tccm-school-menus', THECORE_COLLECTIVITY_MANAGEMENT_URL . $base . 'menus.js', array(), TheCore_Collectivity_Elementor::get_asset_version( $base . 'menus.js' ), true );
		$post = get_queried_object();
		if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'thecore_school_menus' ) ) {
			wp_enqueue_style( 'tccm-school-menus' );
			wp_enqueue_script( 'tccm-school-menus' );
		}
	}

	public function register_widget( $manager ) {
		if ( class_exists( '\Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base' ) ) {
			require_once __DIR__ . '/elementor/class-thecore-collectivity-school-menus-widget.php';
			$manager->register( new TheCore_Collectivity_School_Menus_Widget() );
		}
	}
}
