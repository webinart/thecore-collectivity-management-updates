<?php
/**
 * Documents repository.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Documents_Repository {
	/**
	 * Get documents.
	 *
	 * @param array $args Additional args.
	 * @return WP_Post[]
	 */
	public function get_documents( $args = array() ) {
		$defaults = array(
			'post_type'      => TheCore_Collectivity_Documents_Post_Type::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_key'       => TheCore_Collectivity_Documents_Meta::META_SORT_ORDER,
			'orderby'        => array(
				'meta_value_num' => 'ASC',
				'title'          => 'ASC',
			),
			'order'          => 'ASC',
		);

		return get_posts( wp_parse_args( $args, $defaults ) );
	}

	/**
	 * Resolve the public URL of a document.
	 *
	 * @param int $document_id Document id.
	 * @return string
	 */
	public function get_document_url( $document_id ) {
		$document_id   = absint( $document_id );
		$attachment_id = (int) get_post_meta( $document_id, TheCore_Collectivity_Documents_Meta::META_ATTACHMENT_ID, true );
		$external_url  = (string) get_post_meta( $document_id, TheCore_Collectivity_Documents_Meta::META_EXTERNAL_URL, true );

		if ( $attachment_id > 0 ) {
			$url = wp_get_attachment_url( $attachment_id );
			if ( $url ) {
				return (string) $url;
			}
		}

		return $external_url;
	}

	/**
	 * Get lightweight download meta.
	 *
	 * @param int $document_id Document id.
	 * @return array
	 */
	public function get_download_meta( $document_id ) {
		$document_id   = absint( $document_id );
		$attachment_id = (int) get_post_meta( $document_id, TheCore_Collectivity_Documents_Meta::META_ATTACHMENT_ID, true );
		$url           = $this->get_document_url( $document_id );
		$mime_type     = '';
		$file_size     = '';

		if ( $attachment_id > 0 ) {
			$mime_type = (string) get_post_mime_type( $attachment_id );
			$file_path = get_attached_file( $attachment_id );
			if ( $file_path && file_exists( $file_path ) ) {
				$file_size = size_format( (int) filesize( $file_path ) );
			}
		}

		return array(
			'url'      => $url,
			'mimeType' => $mime_type,
			'fileSize' => $file_size,
		);
	}

	/**
	 * Get procedures related to a document.
	 *
	 * @param int $document_id Document id.
	 * @return WP_Post[]
	 */
	public function get_related_procedures( $document_id ) {
		$document_id = absint( $document_id );
		if (
			$document_id <= 0
			|| ! class_exists( 'TheCore_Collectivity_Procedures_Post_Type', false )
			|| ! class_exists( 'TheCore_Collectivity_Procedures_Meta', false )
		) {
			return array();
		}

		$direct_ids = get_post_meta( $document_id, TheCore_Collectivity_Documents_Meta::META_RELATED_PROCEDURE_IDS, true );
		$direct_ids = is_array( $direct_ids ) ? array_map( 'intval', $direct_ids ) : array();

		$reverse_ids = get_posts(
			array(
				'post_type'      => TheCore_Collectivity_Procedures_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => TheCore_Collectivity_Procedures_Meta::META_RELATED_DOCUMENT_IDS,
						'value'   => '"' . $document_id . '"',
						'compare' => 'LIKE',
					),
				),
			)
		);

		$procedure_ids = array_values( array_unique( array_filter( array_merge( $direct_ids, array_map( 'intval', $reverse_ids ) ) ) ) );
		if ( empty( $procedure_ids ) ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'      => TheCore_Collectivity_Procedures_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'post__in'       => $procedure_ids,
				'orderby'        => 'post__in',
			)
		);
	}
}
