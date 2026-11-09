<?php
/**
 * Document fields for native Elementor dynamic content, including atomic loops.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Documents_Elementor {
	const GROUP = 'tccm-documents';
	const TAG_FIELD = 'tccm-document-field';
	const TAG_URL = 'tccm-document-url';
	const CONDITION_SECONDARY_URL = 'tccm_document_secondary_url';

	public function register_hooks() {
		add_action( 'elementor/dynamic_tags/register', array( $this, 'register_tags' ) );
	}

	public function register_tags( $manager ) {
		if ( ! class_exists( '\\Elementor\\Core\\DynamicTags\\Tag' ) || ! class_exists( '\\Elementor\\Core\\DynamicTags\\Data_Tag' ) ) {
			return;
		}

		require_once __DIR__ . '/elementor/class-thecore-collectivity-document-field-tag.php';
		require_once __DIR__ . '/elementor/class-thecore-collectivity-document-url-tag.php';

		$manager->register_group( self::GROUP, array( 'title' => __( 'Documents de la collectivite', 'thecore-collectivity-management' ) ) );
		$manager->register( new TheCore_Collectivity_Document_Field_Tag() );
		$manager->register( new TheCore_Collectivity_Document_Url_Tag() );

		if ( class_exists( '\\ElementorPro\\Modules\\DisplayConditions\\Conditions\\Dynamic_Tags_Condition' ) ) {
			add_action( 'elementor/element/dynamic_tags/__settings/before_section_end', array( $this, 'add_display_condition_option' ) );
		}
	}

	/**
	 * The native condition provider resolves all tag configs, but its UI groups
	 * are fixed. Add our option to the existing native control, not a new engine.
	 */
	public function add_display_condition_option( $stack ) {
		if ( ! is_object( $stack ) || ! method_exists( $stack, 'get_controls' ) || ! method_exists( $stack, 'update_control' ) ) {
			return;
		}

		$control = $stack->get_controls( 'dynamic_tag' );
		if ( ! is_array( $control ) || ! isset( $control['options'] ) || ! is_array( $control['options'] ) ) {
			return;
		}

		$options = $control['options'] + array(
			self::GROUP => array(
				'label' => __( 'Documents de la collectivite', 'thecore-collectivity-management' ),
				'type'  => 'group',
			),
			self::CONDITION_SECONDARY_URL => self::secondary_condition_label(),
		);
		$stack->update_control( 'dynamic_tag', array( 'options' => $options ) );
	}

	public static function secondary_condition_label() {
		return __( 'Document - Lien secondaire', 'thecore-collectivity-management' );
	}

	/**
	 * Read only public document fields in the current WordPress loop context.
	 */
	public static function current_document_data() {
		$post_id = (int) get_the_ID();
		if ( $post_id <= 0 ) {
			return array();
		}

		$repository = new TheCore_Collectivity_Documents_Repository();
		return $repository->get_public_document_data( $post_id );
	}

	public static function text_fields() {
		return array(
			'title'          => __( 'Titre', 'thecore-collectivity-management' ),
			'summary'        => __( 'Resume', 'thecore-collectivity-management' ),
			'reference'      => __( 'Reference', 'thecore-collectivity-management' ),
			'version'        => __( 'Version', 'thecore-collectivity-management' ),
			'effective_date' => __( 'Date d effet', 'thecore-collectivity-management' ),
			'updated_at'     => __( 'Date de mise a jour', 'thecore-collectivity-management' ),
			'download_label' => __( 'Libelle du lien de telechargement', 'thecore-collectivity-management' ),
			'download_aria_label' => __( 'Libelle accessible du telechargement', 'thecore-collectivity-management' ),
			'reader_label'   => __( 'Libelle du lien de consultation', 'thecore-collectivity-management' ),
			'reader_aria_label' => __( 'Libelle accessible de la consultation', 'thecore-collectivity-management' ),
			'page_count'     => __( 'Nombre de pages', 'thecore-collectivity-management' ),
			'format_label'   => __( 'Pages et format', 'thecore-collectivity-management' ),
			'mime_type'      => __( 'Type MIME', 'thecore-collectivity-management' ),
			'file_size'      => __( 'Poids du fichier', 'thecore-collectivity-management' ),
		);
	}

	public static function text_value( $field, $date_format = '' ) {
		if ( ! is_string( $field ) || ! array_key_exists( $field, self::text_fields() ) ) {
			return '';
		}

		$data = self::current_document_data();
		$value = $data[ $field ] ?? '';
		if ( ! is_scalar( $value ) || '' === $value ) {
			return '';
		}

		if ( in_array( $field, array( 'effective_date', 'updated_at' ), true ) ) {
			if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) ) {
				return '';
			}
			$date = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $value, wp_timezone() );
			if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
				return '';
			}
			$format = is_string( $date_format ) && '' !== trim( $date_format ) ? $date_format : get_option( 'date_format' );
			return (string) wp_date( $format, $date->getTimestamp(), wp_timezone() );
		}

		return (string) $value;
	}

	public static function url_value( $field ) {
		if ( ! in_array( $field, array( 'url', 'reader_url', 'secondary_url' ), true ) ) {
			return '';
		}

		$data = self::current_document_data();
		$value = $data[ $field ] ?? '';
		return is_string( $value ) ? esc_url_raw( $value, array( 'http', 'https' ) ) : '';
	}
}
