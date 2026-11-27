<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_School_Menus_Settings {
	const CATERING_THEME_CHOICE = 'tccm_school_menus_catering_theme_choice';

	const SUPPLIER_DEFAULTS = 'tccm_school_menus_default_suppliers';
	const FRANCE_POSTER = 'tccm_school_menus_france_poster';
	const FRANCE_INGREDIENTS = 'tccm_school_menus_france_ingredients';
	const FRANCE_KEYS = array(
		'fruits-legumes-france' => 'produce_fr',
		'oeufs-france' => 'eggs_fr',
		'viande-bovine-francaise' => 'beef_fr',
		'porc-francais' => 'pork_fr',
	);

	public static function ingredient_origins_enabled() {
		return '1' === (string) get_option( self::FRANCE_INGREDIENTS, '1' );
	}

	public static function france_terms() {
		$terms = get_terms( array(
			'taxonomy' => TheCore_Collectivity_School_Menus_Data::TAXONOMY,
			'hide_empty' => false, 'slug' => array_keys( self::FRANCE_KEYS ),
			'meta_key' => '_tccm_marker_family', 'meta_value' => 'origin',
		) );
		return is_wp_error( $terms ) ? array() : array_column( $terms, null, 'slug' );
	}

	public static function default_badge_ids() {
		$selected = get_option( self::FRANCE_POSTER, array() );
		if ( ! is_array( $selected ) || ! $selected ) {
			return array();
		}
		$terms = self::france_terms();
		$ids = array();
		foreach ( self::FRANCE_KEYS as $slug => $key ) {
			if ( isset( $terms[ $slug ] ) && in_array( $slug, $selected, true ) ) {
				$ids[] = (int) $terms[ $slug ]->term_id;
			}
		}
		return $ids;
	}

	public static function suppliers() {
		$terms = get_terms( array(
			'taxonomy' => TheCore_Collectivity_School_Menus_Data::TAXONOMY, 'hide_empty' => false,
			'meta_key' => '_tccm_marker_family', 'meta_value' => 'origin',
		) );
		return is_wp_error( $terms ) ? array() : array_filter( $terms, static function ( $term ) {
			return ! isset( self::FRANCE_KEYS[ $term->slug ] );
		} );
	}

	public static function default_supplier_ids() {
		$selected = get_option( self::SUPPLIER_DEFAULTS, array() );
		if ( ! is_array( $selected ) || ! $selected ) {
			return array();
		}
		return array_values( array_intersect( array_map( 'intval', array_column( self::suppliers(), 'term_id' ) ), $selected ) );
	}

	public static function catering_theme_choice_enabled() {
		return '1' === (string) get_option( self::CATERING_THEME_CHOICE, '1' );
	}

	public static function register_hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_tccm_school_menus_toggle', array( __CLASS__, 'save' ) );
		add_shortcode( 'thecore_school_menus', array( __CLASS__, 'shortcode' ) );
	}

	public static function menu() {
		add_options_page( __( 'Menus scolaires', 'thecore-collectivity-management' ), __( 'Menus scolaires', 'thecore-collectivity-management' ), 'manage_options', 'tccm-school-menus', array( __CLASS__, 'render' ) );
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$active = TheCore_Collectivity_Management::instance()->is_module_active( 'school-menus' );
		echo '<div class="wrap"><h1>' . esc_html__( 'Gestion des menus scolaires', 'thecore-collectivity-management' ) . '</h1>';
		if ( isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Réglage enregistré.', 'thecore-collectivity-management' ) . '</p></div>';
		}
		echo '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post"><input type="hidden" name="action" value="tccm_school_menus_toggle">';
		wp_nonce_field( 'tccm_school_menus_toggle' );
		echo '<p><label><input type="checkbox" name="enabled" value="1" ' . checked( $active, true, false ) . '> ' . esc_html__( 'Activer la gestion des menus scolaires', 'thecore-collectivity-management' ) . '</label></p>';
		echo '<p class="description">' . esc_html__( 'Une publication par semaine, des repères associés à chaque aliment et un widget d’affichage. La désactivation conserve les menus et les repères.', 'thecore-collectivity-management' ) . '</p>';
		echo '<input type="hidden" name="catering_theme_choice" value="0">';
		echo '<p><label><input type="checkbox" name="catering_theme_choice" value="1" ' . checked( self::catering_theme_choice_enabled(), true, false ) . '> ' . esc_html__( 'Autoriser l’équipe de restauration à choisir le modèle graphique de chaque semaine', 'thecore-collectivity-management' ) . '</label></p>';
		if ( $active ) {
			$suppliers = self::suppliers();
			if ( $suppliers ) {
				$defaults = self::default_supplier_ids();
				echo '<fieldset><legend><strong>' . esc_html__( 'Fournisseurs précochés pour les nouvelles semaines', 'thecore-collectivity-management' ) . '</strong></legend><input type="hidden" name="suppliers_present" value="1">';
				foreach ( $suppliers as $term ) {
					echo '<p><label><input type="checkbox" name="default_suppliers[]" value="' . esc_attr( $term->term_id ) . '" ' . checked( in_array( (int) $term->term_id, $defaults, true ), true, false ) . '> ' . esc_html( $term->name ) . '</label></p>';
				}
				echo '</fieldset>';
			}
			$terms = self::france_terms();
			$selected = get_option( self::FRANCE_POSTER, array() );
			echo '<h2>' . esc_html__( 'Origines France', 'thecore-collectivity-management' ) . '</h2>';
			if ( $terms ) {
				echo '<fieldset><legend>' . esc_html__( 'Logos en légende des nouvelles semaines', 'thecore-collectivity-management' ) . '</legend>';
				echo '<input type="hidden" name="france_poster_present" value="1">';
				foreach ( self::FRANCE_KEYS as $slug => $key ) {
					if ( isset( $terms[ $slug ] ) ) {
						echo '<p><label><input type="checkbox" name="france_poster[]" value="' . esc_attr( $slug ) . '" ' . checked( in_array( $slug, (array) $selected, true ), true, false ) . '> ' . esc_html( $terms[ $slug ]->name ) . '</label></p>';
					}
				}
				echo '</fieldset><p class="description">' . esc_html__( 'Chaque nouvelle semaine reprend ces logos sur le menu et son PDF.', 'thecore-collectivity-management' ) . '</p>';
			} else {
				echo '<p>' . esc_html__( 'Ajoutez les repères Origines France dans la famille Origine et fournisseur pour choisir les logos de la légende.', 'thecore-collectivity-management' ) . '</p>';
			}
			echo '<input type="hidden" name="france_ingredients" value="0">';
			echo '<p><label><input type="checkbox" name="france_ingredients" value="1" ' . checked( self::ingredient_origins_enabled(), true, false ) . '> ' . esc_html__( 'Proposer les origines France lors de la saisie de chaque aliment', 'thecore-collectivity-management' ) . '</label></p>';
		}
		submit_button();
		echo '</form>';
		if ( $active ) {
			echo '<p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=tccm_school_menu' ) ) . '">' . esc_html__( 'Gérer les menus', 'thecore-collectivity-management' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=tccm_food_marker&post_type=tccm_school_menu' ) ) . '">' . esc_html__( 'Gérer les repères', 'thecore-collectivity-management' ) . '</a></p>';
		}
		echo '</div>';
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'thecore-collectivity-management' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'tccm_school_menus_toggle' );
		if ( ( $_POST['suppliers_present'] ?? '' ) === '1' ) {
			$submitted = $_POST['default_suppliers'] ?? array();
			$allowed = array_map( 'strval', array_column( self::suppliers(), 'term_id' ) );
			$ids = array_values( array_filter( $allowed, static function ( $id ) use ( $submitted ) {
				return is_array( $submitted ) && in_array( $id, $submitted, true );
			} ) );
			if ( count( $ids ) > 12 ) {
				wp_die( esc_html__( 'Choisissez au maximum douze fournisseurs pour la légende.', 'thecore-collectivity-management' ), '', array( 'response' => 400, 'back_link' => true ) );
			}
			update_option( self::SUPPLIER_DEFAULTS, array_map( 'intval', $ids ), false );
		}
		$states = get_option( TheCore_Collectivity_Module_Registry::OPTION_MODULE_STATES, array() );
		$states = is_array( $states ) ? $states : array();
		$states['school-menus'] = isset( $_POST['enabled'] ) && is_string( $_POST['enabled'] ) && '1' === $_POST['enabled'];
		update_option( TheCore_Collectivity_Module_Registry::OPTION_MODULE_STATES, $states, false );
		if ( isset( $_POST['catering_theme_choice'] ) && in_array( $_POST['catering_theme_choice'], array( '0', '1' ), true ) ) {
			update_option( self::CATERING_THEME_CHOICE, $_POST['catering_theme_choice'], false );
		}
		if ( ( $_POST['france_poster_present'] ?? '' ) === '1' ) {
			$selected = $_POST['france_poster'] ?? array();
			if ( is_array( $selected ) ) {
				$slugs = array_filter( array_keys( self::FRANCE_KEYS ), static function ( $slug ) use ( $selected ) {
					return in_array( $slug, $selected, true );
				} );
				update_option( self::FRANCE_POSTER, array_values( $slugs ), false );
			}
		}
		if ( isset( $_POST['france_ingredients'] ) && in_array( $_POST['france_ingredients'], array( '0', '1' ), true ) ) {
			update_option( self::FRANCE_INGREDIENTS, $_POST['france_ingredients'], false );
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=tccm-school-menus&saved=1' ) );
		exit;
	}

	public static function shortcode( $attributes = array() ) {
		if ( ! TheCore_Collectivity_Management::instance()->is_module_active( 'school-menus' ) ) {
			return '';
		}
		$attributes = shortcode_atts( array( 'limit' => 8, 'menu' => 0, 'illustrated' => '0' ), $attributes, 'thecore_school_menus' );
		return TheCore_Collectivity_School_Menus_Renderer::render( absint( $attributes['menu'] ), max( 1, min( 52, (int) $attributes['limit'] ) ), '1' === $attributes['illustrated'] );
	}
}
