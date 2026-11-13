<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Event_Programme_Admin {
	const NONCE = 'tccm_event_programme_nonce';
	const ACTION = 'tccm_event_programme_save';
	const BOX = 'tccm-event-programme';

	public static function register_hooks() {
		add_action( 'add_meta_boxes_post', array( __CLASS__, 'register_box' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'save_post_post', array( __CLASS__, 'save' ), 20, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_filter( 'postbox_classes_post_' . self::BOX, static function ( $classes ) { return array_values( array_diff( $classes, array( 'closed' ) ) ); } );
	}

	public static function register_box( $post ) {
		if ( current_user_can( 'edit_post', $post->ID ) ) {
			add_meta_box( self::BOX, __( 'Programme de l’événement', 'thecore-collectivity-management' ), array( __CLASS__, 'render' ), 'post', 'normal', 'default', array( '__block_editor_compatible_meta_box' => true ) );
		}
	}

	public static function assets( $hook ) {
		$screen = get_current_screen();
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! $screen || 'post' !== $screen->post_type ) {
			return;
		}
		$base = 'includes/features/event-agenda/assets/';
		wp_enqueue_style( 'tccm-event-programme-admin', THECORE_COLLECTIVITY_MANAGEMENT_URL . $base . 'programme-admin.css', array(), TheCore_Collectivity_Elementor::get_asset_version( $base . 'programme-admin.css' ) );
		$dependencies = array( 'wp-dom-ready' );
		if ( $screen->is_block_editor() ) {
			$dependencies[] = 'wp-data';
			$dependencies[] = 'wp-editor';
		}
		wp_enqueue_script( 'tccm-event-programme-admin', THECORE_COLLECTIVITY_MANAGEMENT_URL . $base . 'programme-admin.js', $dependencies, TheCore_Collectivity_Elementor::get_asset_version( $base . 'programme-admin.js' ), true );
		wp_localize_script( 'tccm-event-programme-admin', 'TCCMProgrammeLabels', array(
			'day' => __( 'Journée', 'thecore-collectivity-management' ), 'date' => __( 'Date', 'thecore-collectivity-management' ),
			'title' => __( 'Intitulé', 'thecore-collectivity-management' ), 'start' => __( 'Début', 'thecore-collectivity-management' ),
			'end' => __( 'Fin (facultatif)', 'thecore-collectivity-management' ), 'duration' => __( 'Durée en minutes (sans heure de fin)', 'thecore-collectivity-management' ),
			'description' => __( 'Précisions (facultatif)', 'thecore-collectivity-management' ), 'location' => __( 'Lieu (facultatif)', 'thecore-collectivity-management' ),
			'addItem' => __( 'Ajouter une animation', 'thecore-collectivity-management' ), 'removeItem' => __( 'Retirer l’animation', 'thecore-collectivity-management' ),
			'removeDay' => __( 'Retirer la journée', 'thecore-collectivity-management' ), 'up' => __( 'Monter', 'thecore-collectivity-management' ),
			'down' => __( 'Descendre', 'thecore-collectivity-management' ), 'empty' => __( 'Ajoutez une journée pour commencer le programme.', 'thecore-collectivity-management' ),
			'confirm' => __( 'Retirer cet élément du programme ? La modification sera appliquée à l’enregistrement.', 'thecore-collectivity-management' ),
			'invalid' => __( 'Vérifiez les dates, les intitulés et les horaires du programme avant d’enregistrer.', 'thecore-collectivity-management' ),
			'limit' => __( 'La limite de 31 journées ou de 500 animations est atteinte.', 'thecore-collectivity-management' ),
			'choice' => __( 'Renseignez une heure de fin ou une durée, pas les deux.', 'thecore-collectivity-management' ),
			'duplicate' => __( 'Cette date figure déjà dans le programme.', 'thecore-collectivity-management' ),
		) );
	}

	public static function render( $post ) {
		$value = TheCore_Collectivity_Event_Programme::get( $post->ID );
		wp_nonce_field( self::ACTION, self::NONCE );
		$date = get_post_meta( $post->ID, TheCore_Collectivity_Event_Agenda::META_START_TS, true );
		echo '<div class="tccm-programme-editor" data-tccm-programme-editor data-start-date="' . esc_attr( $date ? wp_date( 'Y-m-d', (int) $date ) : wp_date( 'Y-m-d' ) ) . '">';
		echo '<p class="description">' . esc_html__( 'Les journées et les animations sont présentées par ordre chronologique. Les activités peuvent se chevaucher.', 'thecore-collectivity-management' ) . '</p>';
		echo '<input type="hidden" name="tccm_event_programme_payload" value="' . esc_attr( wp_json_encode( array( 'version' => 1, 'days' => $value['days'] ) ) ) . '">';
		echo '<div data-tccm-programme-days></div><p><button type="button" class="button" data-tccm-programme-add-day>' . esc_html__( 'Ajouter une journée', 'thecore-collectivity-management' ) . '</button></p>';
		echo '<p class="notice notice-error inline" data-tccm-programme-error role="alert" hidden></p>';
		echo '<h3>' . esc_html__( 'Informations complémentaires', 'thecore-collectivity-management' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Restauration, accès ou informations communes à toutes les journées.', 'thecore-collectivity-management' ) . '</p>';
		wp_editor( $value['information'], 'tccm_event_programme_information', array( 'textarea_name' => 'tccm_event_programme_information', 'textarea_rows' => 8, 'media_buttons' => false, 'teeny' => true ) );
		echo '</div>';
	}

	public static function save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| ! current_user_can( 'edit_post', $post_id ) || 'post' !== $post->post_type ) {
			return;
		}
		$nonce = $_POST[ self::NONCE ] ?? '';
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), self::ACTION ) ) {
			return;
		}
		$payload = $_POST['tccm_event_programme_payload'] ?? null;
		$information = $_POST['tccm_event_programme_information'] ?? null;
		if ( ! is_string( $payload ) || strlen( $payload ) > 524288 || ! is_string( $information ) ) {
			self::save_error( $post_id );
			return;
		}
		$value = json_decode( wp_unslash( $payload ), true, 32 );
		if ( ! is_array( $value ) || JSON_ERROR_NONE !== json_last_error() ) {
			self::save_error( $post_id );
			return;
		}
		$value['information'] = wp_unslash( $information );
		if ( is_wp_error( TheCore_Collectivity_Event_Programme::update( $post_id, $value ) ) ) {
			self::save_error( $post_id );
		}
	}

	private static function save_error( $post_id ) {
		set_transient( 'tccm_programme_error_' . get_current_user_id() . '_' . $post_id, true, 120 );
	}

	public static function notice() {
		$post_id = isset( $_GET['post'] ) && is_scalar( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$key = 'tccm_programme_error_' . get_current_user_id() . '_' . $post_id;
		if ( $post_id && current_user_can( 'edit_post', $post_id ) && get_transient( $key ) ) {
			delete_transient( $key );
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Le programme n’a pas été enregistré : vérifiez les dates, les horaires et les intitulés. Le programme précédemment enregistré a été conservé.', 'thecore-collectivity-management' ) . '</p></div>';
		}
	}
}
