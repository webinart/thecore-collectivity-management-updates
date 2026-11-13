<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-thecore-collectivity-event-programme-schema.php';
require_once __DIR__ . '/class-thecore-collectivity-event-programme-admin.php';
require_once __DIR__ . '/class-thecore-collectivity-event-programme-renderer.php';

final class TheCore_Collectivity_Event_Programme {
	const META_KEY = '_tccm_event_programme';
	const STYLE = 'tccm-event-programme';

	public static function register_hooks() {
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register_styles' ) );
		add_action( 'elementor/editor/before_enqueue_styles', array( __CLASS__, 'register_styles' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_styles' ), 5 );
		add_filter( 'elementor/element/is_dynamic_content', array( __CLASS__, 'dynamic_content' ), 20, 3 );
		TheCore_Collectivity_Event_Programme_Admin::register_hooks();
	}

	public static function register_meta() {
		register_post_meta( 'post', self::META_KEY, array(
			'type' => 'object', 'single' => true, 'show_in_rest' => false,
			'auth_callback' => static function ( $allowed, $key, $post_id ) { return current_user_can( 'edit_post', $post_id ); },
		) );
	}

	public static function get( $post_id ) {
		$value = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_array( $value ) ) {
			return TheCore_Collectivity_Event_Programme_Schema::empty_programme();
		}
		$value = TheCore_Collectivity_Event_Programme_Schema::normalize( $value );
		return is_wp_error( $value ) ? TheCore_Collectivity_Event_Programme_Schema::empty_programme() : $value;
	}

	public static function update( $post_id, $value ) {
		if ( 'post' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'tccm_event_programme_forbidden', __( 'Vous ne pouvez pas modifier ce programme.', 'thecore-collectivity-management' ) );
		}
		$value = TheCore_Collectivity_Event_Programme_Schema::normalize( $value );
		if ( is_wp_error( $value ) ) {
			return $value;
		}
		$empty = ! $value['days'] && '' === trim( $value['information'] );
		if ( $empty ) {
			delete_post_meta( $post_id, self::META_KEY );
		} else {
			update_post_meta( $post_id, self::META_KEY, wp_slash( $value ) );
		}
		if ( ( $empty && metadata_exists( 'post', $post_id, self::META_KEY ) ) || ( ! $empty && get_post_meta( $post_id, self::META_KEY, true ) !== $value ) ) {
			return new WP_Error( 'tccm_event_programme_save_failed', __( 'Le programme n’a pas pu être enregistré.', 'thecore-collectivity-management' ) );
		}
		do_action( 'thecore_collectivity/event_programme/updated', $post_id, $value );
		do_action( 'the_core_cache_system_post_updated', $post_id, 'event_programme' );
		return $value;
	}

	public static function register_styles() {
		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style( self::STYLE, THECORE_COLLECTIVITY_MANAGEMENT_URL . 'includes/features/event-agenda/assets/programme.css', array(),
				TheCore_Collectivity_Elementor::get_asset_version( 'includes/features/event-agenda/assets/programme.css' ) );
		}
	}

	public static function dynamic_content( $dynamic, $data, $element ) {
		return ( $data['widgetType'] ?? '' ) === 'tccm-event-programme' ? true : $dynamic;
	}

	public static function register_widget( $manager ) {
		require_once dirname( __DIR__ ) . '/elementor/class-thecore-collectivity-event-programme-widget.php';
		$manager->register( new TheCore_Collectivity_Event_Programme_Widget() );
	}
}
