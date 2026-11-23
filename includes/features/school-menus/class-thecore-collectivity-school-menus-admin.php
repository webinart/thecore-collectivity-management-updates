<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_School_Menus_Admin {
	public static function register_hooks() {
		add_action( 'add_meta_boxes_' . TheCore_Collectivity_School_Menus_Data::POST_TYPE, array( __CLASS__, 'box' ) );
		add_action( 'save_post_' . TheCore_Collectivity_School_Menus_Data::POST_TYPE, array( __CLASS__, 'save' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'wp_ajax_inline-save', array( __CLASS__, 'guard_inline_publication' ), 0 );
		add_action( TheCore_Collectivity_School_Menus_Data::TAXONOMY . '_add_form_fields', array( __CLASS__, 'marker_fields' ) );
		add_action( TheCore_Collectivity_School_Menus_Data::TAXONOMY . '_edit_form_fields', array( __CLASS__, 'marker_fields' ) );
		add_action( 'created_' . TheCore_Collectivity_School_Menus_Data::TAXONOMY, array( __CLASS__, 'save_marker' ) );
		add_action( 'edited_' . TheCore_Collectivity_School_Menus_Data::TAXONOMY, array( __CLASS__, 'save_marker' ) );
		add_filter( 'postbox_classes_' . TheCore_Collectivity_School_Menus_Data::POST_TYPE . '_tccm-school-menu', static function ( $classes ) { return array_values( array_diff( $classes, array( 'closed' ) ) ); } );
		add_filter( 'manage_' . TheCore_Collectivity_School_Menus_Data::POST_TYPE . '_posts_columns', static function ( $columns ) { unset( $columns['taxonomy-tccm_food_marker'] ); $columns['school_week'] = __( 'Semaine', 'thecore-collectivity-management' ); return $columns; } );
		add_action( 'manage_' . TheCore_Collectivity_School_Menus_Data::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'week_column' ), 10, 2 );
	}

	public static function week_column( $column, $post_id ) {
		if ( 'school_week' !== $column ) {
			return;
		}
		echo esc_html( get_post_meta( $post_id, TheCore_Collectivity_School_Menus_Data::START, true ) );
		$week = TheCore_Collectivity_School_Menus_Data::get( $post_id );
		if ( 'publish' === get_post_status( $post_id ) && $week && TheCore_Collectivity_School_Menus_Data::is_past( $week ) ) {
			echo '<br><span class="description">' . esc_html__( 'Semaine terminée', 'thecore-collectivity-management' ) . '</span>';
		}
	}

	public static function box( $post ) {
		add_meta_box( 'tccm-school-menu', __( 'Menus de la semaine', 'thecore-collectivity-management' ), array( __CLASS__, 'editor' ), TheCore_Collectivity_School_Menus_Data::POST_TYPE, 'normal', 'high' );
	}

	public static function editor( $post ) {
		$value = TheCore_Collectivity_School_Menus_Data::get( $post->ID ) ?: TheCore_Collectivity_School_Menus_Data::empty_week();
		wp_nonce_field( 'tccm_school_menu_save', 'tccm_school_menu_nonce' );
		echo '<div class="tccm-menu-editor" data-school-menu-editor><input type="hidden" name="tccm_school_menu_payload" value="' . esc_attr( wp_json_encode( $value ) ) . '">';
		echo '<p class="description">' . esc_html__( 'Saisissez les aliments séparément. Pour une recette, vous pouvez distinguer plusieurs fragments et associer les repères uniquement à l’ingrédient concerné.', 'thecore-collectivity-management' ) . '</p>';
		echo '<div data-school-menu-fields></div><p class="notice notice-error inline" data-school-menu-error role="alert" hidden></p><noscript><p>' . esc_html__( 'Activez JavaScript pour modifier le menu. Les données déjà enregistrées sont conservées.', 'thecore-collectivity-management' ) . '</p></noscript></div>';
	}

	public static function assets() {
		$screen = get_current_screen();
		if ( ! $screen || ( TheCore_Collectivity_School_Menus_Data::POST_TYPE !== $screen->post_type && TheCore_Collectivity_School_Menus_Data::TAXONOMY !== $screen->taxonomy ) ) {
			return;
		}
		$base = 'includes/features/school-menus/assets/';
		wp_enqueue_media();
		wp_enqueue_style( 'tccm-school-menus-admin', THECORE_COLLECTIVITY_MANAGEMENT_URL . $base . 'admin.css', array(), TheCore_Collectivity_Elementor::get_asset_version( $base . 'admin.css' ) );
		wp_enqueue_script( 'tccm-school-menus-admin', THECORE_COLLECTIVITY_MANAGEMENT_URL . $base . 'admin.js', array(), TheCore_Collectivity_Elementor::get_asset_version( $base . 'admin.js' ), true );
		wp_localize_script( 'tccm-school-menus-admin', 'TCCMSchoolMenus', array( 'markers' => TheCore_Collectivity_School_Menus_Data::markers(), 'hiddenIngredientSlugs' => TheCore_Collectivity_School_Menus_Settings::ingredient_origins_enabled() ? array() : array_keys( TheCore_Collectivity_School_Menus_Settings::FRANCE_KEYS ), 'courses' => TheCore_Collectivity_School_Menus_Data::courses(), 'locale' => str_replace( '_', '-', get_locale() ) ) );
	}

	public static function save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || TheCore_Collectivity_School_Menus_Data::POST_TYPE !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$nonce = $_POST['tccm_school_menu_nonce'] ?? null;
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'tccm_school_menu_save' ) ) {
			return;
		}
		$payload = $_POST['tccm_school_menu_payload'] ?? null;
		$value = is_string( $payload ) && strlen( $payload ) <= 1048576 ? json_decode( wp_unslash( $payload ), true, 32 ) : null;
		$result = TheCore_Collectivity_School_Menus_Data::update( $post_id, $value );
		if ( is_wp_error( $result ) ) {
			self::save_error( $post_id, $result->get_error_message() );
		}
	}

	public static function save_error( $post_id, $message, $preserved = true ) {
		set_transient( 'tccm_menu_error_' . get_current_user_id() . '_' . $post_id, array( 'message' => $message, 'preserved' => $preserved ), 120 );
	}

	public static function guard_inline_publication() {
		$post_id = isset( $_POST['post_ID'] ) && is_scalar( $_POST['post_ID'] ) ? absint( $_POST['post_ID'] ) : 0;
		$nonce = $_POST['_inline_edit'] ?? null;
		if ( ! $post_id || TheCore_Collectivity_School_Menus_Data::POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'inlineeditnonce' ) ) {
			return;
		}
		$status = isset( $_POST['keep_private'] ) && 'private' === $_POST['keep_private'] ? 'private' : ( $_POST['_status'] ?? '' );
		if ( ! is_string( $status ) || ! in_array( $status, array( 'publish', 'private', 'future' ), true ) ) {
			return;
		}
		$value = TheCore_Collectivity_School_Menus_Data::get( $post_id );
		if ( ! $value || TheCore_Collectivity_School_Menus_Data::conflict( $post_id, $value['start'] ) ) {
			$message = $value ? __( 'Publication refusée : un menu publié ou programmé existe déjà pour cette semaine. Modifiez la semaine dans l’éditeur ou ouvrez le menu existant.', 'thecore-collectivity-management' ) : __( 'Publication refusée : renseignez un menu valide dans l’éditeur avant de publier.', 'thecore-collectivity-management' );
			wp_die( esc_html( $message ), '', array( 'response' => 200 ) );
		}
	}

	public static function notice() {
		$post_id = isset( $_GET['post'] ) && is_scalar( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$key = 'tccm_menu_error_' . get_current_user_id() . '_' . $post_id;
		if ( $post_id && current_user_can( 'edit_post', $post_id ) && get_transient( $key ) ) {
			$notice = get_transient( $key );
			$notice = is_array( $notice ) ? $notice : array( 'message' => $notice, 'preserved' => true );
			echo '<div class="notice notice-error"><p>' . esc_html( $notice['message'] ) . ( $notice['preserved'] ? ' ' . esc_html__( 'Le menu précédemment enregistré a été conservé.', 'thecore-collectivity-management' ) : '' ) . '</p></div>';
			delete_transient( $key );
		}
	}

	public static function marker_fields( $term ) {
		$editing = is_object( $term );
		$term_id = $editing ? $term->term_id : 0;
		$family = $term_id ? get_term_meta( $term_id, '_tccm_marker_family', true ) : 'preparation';
		$icon = $term_id ? get_term_meta( $term_id, '_tccm_marker_icon', true ) : 'leaf';
		$logo = $term_id ? (int) get_term_meta( $term_id, '_tccm_marker_logo', true ) : 0;
		$crop = $term_id ? get_term_meta( $term_id, '_tccm_marker_logo_crop', true ) : array();
		$crop_fields = '';
		foreach ( array( 'x' => 'Position X', 'y' => 'Position Y', 'width' => 'Largeur', 'height' => 'Hauteur' ) as $field => $label ) {
			$crop_fields .= '<label>' . esc_html( $label ) . ' <input type="number" min="0" class="small-text" name="tccm_marker_logo_crop[' . esc_attr( $field ) . ']" value="' . esc_attr( is_array( $crop ) ? ( $crop[ $field ] ?? '' ) : '' ) . '"></label> ';
		}
		$fields = array(
			'Famille' => '<select name="tccm_marker_family"><option value="preparation" ' . selected( $family, 'preparation', false ) . '>Qualité et préparation</option><option value="origin" ' . selected( $family, 'origin', false ) . '>Origine et fournisseur</option></select>',
			'Pictogramme de remplacement' => '<select name="tccm_marker_icon"><option value="leaf" ' . selected( $icon, 'leaf', false ) . '>Feuille — bio</option><option value="house" ' . selected( $icon, 'house', false ) . '>Maison — fait maison</option><option value="chef" ' . selected( $icon, 'chef', false ) . '>Toque — préparé sur place</option><option value="origin" ' . selected( $icon, 'origin', false ) . '>Provenance</option><option value="grain" ' . selected( $icon, 'grain', false ) . '>Épi — cultures</option><option value="barn" ' . selected( $icon, 'barn', false ) . '>Ferme — élevage</option></select>',
			'Logo' => '<input id="tccm-marker-logo" type="number" min="0" name="tccm_marker_logo" value="' . esc_attr( $logo ) . '"><p><button type="button" class="button" data-school-marker-logo>Choisir dans la médiathèque</button> <button type="button" class="button" data-school-marker-clear>Retirer le logo</button></p><p class="description">Le logo choisi remplace le pictogramme. Utilisez une image dédiée ou renseignez le recadrage du logo dans l’image.</p>',
			'Recadrage du logo (facultatif)' => $crop_fields . '<p class="description">Coordonnées en pixels dans l’image originale. Laissez les quatre champs vides pour afficher l’image entière.</p>',
		);
		foreach ( $fields as $label => $html ) {
			echo $editing ? '<tr class="form-field"><th scope="row">' . esc_html( $label ) . '</th><td>' . $html . '</td></tr>' : '<div class="form-field"><label>' . esc_html( $label ) . '</label>' . $html . '</div>';
		}
		if ( $editing ) {
			echo '<tr hidden><td colspan="2">';
		}
		wp_nonce_field( 'tccm_school_marker_save', 'tccm_school_marker_nonce' );
		if ( $editing ) {
			echo '</td></tr>';
		}
	}

	public static function save_marker( $term_id ) {
		$nonce = $_POST['tccm_school_marker_nonce'] ?? null;
		if ( ! current_user_can( 'manage_categories' ) || ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'tccm_school_marker_save' ) ) {
			return;
		}
		$family = $_POST['tccm_marker_family'] ?? '';
		$icon = $_POST['tccm_marker_icon'] ?? '';
		$logo = $_POST['tccm_marker_logo'] ?? '';
		if ( ! is_string( $family ) || ! in_array( $family, array( 'preparation', 'origin' ), true ) || ! is_string( $icon ) || ! in_array( $icon, array( 'leaf', 'house', 'chef', 'origin', 'grain', 'barn' ), true ) || ! is_string( $logo ) || ! ctype_digit( $logo ) || ( (int) $logo && ! wp_attachment_is_image( (int) $logo ) ) ) {
			return;
		}
		$crop = $_POST['tccm_marker_logo_crop'] ?? array();
		if ( ! is_array( $crop ) ) {
			return;
		}
		$rectangle = array();
		foreach ( array( 'x', 'y', 'width', 'height' ) as $field ) {
			$value = $crop[ $field ] ?? '';
			if ( ! is_string( $value ) || ( '' !== $value && ! ctype_digit( $value ) ) ) {
				return;
			}
			$rectangle[ $field ] = '' === $value ? null : (int) $value;
		}
		if ( (int) $logo && count( array_filter( $rectangle, static function ( $value ) { return null !== $value; } ) ) ) {
			$validated = TheCore_Collectivity_School_Menus_Data::logo_crop( (int) $logo, $rectangle );
			if ( ! $validated ) {
				return;
			}
			update_term_meta( $term_id, '_tccm_marker_logo_crop', $rectangle );
		} else {
			delete_term_meta( $term_id, '_tccm_marker_logo_crop' );
		}
		update_term_meta( $term_id, '_tccm_marker_family', $family );
		update_term_meta( $term_id, '_tccm_marker_icon', $icon );
		update_term_meta( $term_id, '_tccm_marker_logo', (int) $logo );
	}
}
