<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_School_Menus_Settings {
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
		$states = get_option( TheCore_Collectivity_Module_Registry::OPTION_MODULE_STATES, array() );
		$states = is_array( $states ) ? $states : array();
		$states['school-menus'] = isset( $_POST['enabled'] ) && is_string( $_POST['enabled'] ) && '1' === $_POST['enabled'];
		update_option( TheCore_Collectivity_Module_Registry::OPTION_MODULE_STATES, $states, false );
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
