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
		$collection = array_key_exists( 'collection', $args ) ? $args['collection'] : null;
		unset( $args['collection'] );
		$defaults = array(
			'post_type'      => TheCore_Collectivity_Documents_Post_Type::POST_TYPE,
			'post_status'    => 'publish',
			'has_password'   => false,
			'posts_per_page' => -1,
			'orderby'        => array(
				'tccm_document_order' => 'ASC',
				'title'          => 'ASC',
			),
			'order'          => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );
		$sort_query = self::get_sort_meta_query();
		$args['meta_query'] = empty( $args['meta_query'] )
			? $sort_query
			: array( 'relation' => 'AND', $sort_query, $args['meta_query'] );
		if ( null !== $collection ) {
			$term = is_int( $collection ) || ( is_string( $collection ) && ctype_digit( $collection ) )
				? get_term( (int) $collection, TheCore_Collectivity_Documents_Post_Type::TAXONOMY_COLLECTION )
				: ( is_string( $collection ) ? get_term_by( 'slug', sanitize_title( $collection ), TheCore_Collectivity_Documents_Post_Type::TAXONOMY_COLLECTION ) : false );
			if ( ! $term || is_wp_error( $term ) || TheCore_Collectivity_Documents_Post_Type::TAXONOMY_COLLECTION !== $term->taxonomy ) {
				$args['post__in'] = array( 0 );
			} else {
				$filter = array(
					'taxonomy' => TheCore_Collectivity_Documents_Post_Type::TAXONOMY_COLLECTION,
					'field' => 'term_id',
					'terms' => array( (int) $term->term_id ),
					'include_children' => true,
				);
				$args['tax_query'] = empty( $args['tax_query'] )
					? array( $filter )
					: array( 'relation' => 'AND', $args['tax_query'], $filter );
			}
		}
		return get_posts( $args );
	}

	/**
	 * Preserve legacy ordering without dropping documents which have no order yet.
	 *
	 * @return array
	 */
	public static function get_sort_meta_query() {
		return array(
			'relation' => 'OR',
			'tccm_document_order' => array(
				'key' => TheCore_Collectivity_Documents_Meta::META_SORT_ORDER,
				'compare' => 'EXISTS',
				'type' => 'NUMERIC',
			),
			'tccm_document_order_missing' => array(
				'key' => TheCore_Collectivity_Documents_Meta::META_SORT_ORDER,
				'compare' => 'NOT EXISTS',
			),
		);
	}

	/**
	 * Allowlisted public data for server-rendered integrations; never local paths.
	 *
	 * @param int $document_id Document id in the current loop context.
	 * @return array Empty for unpublished, password-protected or unrelated content.
	 */
	public function get_public_document_data( $document_id ) {
		$post = get_post( absint( $document_id ) );
		if ( ! $post instanceof WP_Post || TheCore_Collectivity_Documents_Post_Type::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status || '' !== (string) $post->post_password ) {
			return array();
		}
		$meta = $this->get_download_meta( $post->ID );
		$reader_url = self::sanitize_public_url( $this->get_scalar_meta( $post->ID, TheCore_Collectivity_Documents_Meta::META_READER_URL ) );
		$reader_label = $this->get_scalar_meta( $post->ID, TheCore_Collectivity_Documents_Meta::META_READER_LABEL );
		$download_label = $this->get_scalar_meta( $post->ID, TheCore_Collectivity_Documents_Meta::META_DOWNLOAD_LABEL );
		$download_label = '' !== $download_label ? $download_label : __( 'Consulter le document', 'thecore-collectivity-management' );
		$reader_label = '' !== $reader_label ? $reader_label : __( 'Consulter le document', 'thecore-collectivity-management' );
		$page_count = max( 0, (int) $this->get_scalar_meta( $post->ID, TheCore_Collectivity_Documents_Meta::META_PAGE_COUNT ) );
		$path = wp_parse_url( $meta['url'], PHP_URL_PATH );
		$extension = is_string( $path ) ? strtoupper( pathinfo( $path, PATHINFO_EXTENSION ) ) : '';
		$extension = preg_match( '/^[A-Z0-9]{1,10}$/D', $extension ) ? $extension : '';
		$format_label = $extension;
		if ( $page_count > 0 ) {
			$pages = sprintf( _n( '%s page', '%s pages', $page_count, 'thecore-collectivity-management' ), number_format_i18n( $page_count ) );
			$format_label = '' !== $extension ? $pages . " \u{00B7} " . $extension : $pages;
		}
		return array(
			'title' => (string) $post->post_title,
			'summary' => $this->get_scalar_meta( $post->ID, TheCore_Collectivity_Documents_Meta::META_SUMMARY ),
			'reference' => $this->get_scalar_meta( $post->ID, TheCore_Collectivity_Documents_Meta::META_REFERENCE ),
			'version' => $this->get_scalar_meta( $post->ID, TheCore_Collectivity_Documents_Meta::META_VERSION_LABEL ),
			'effective_date' => $this->get_scalar_meta( $post->ID, TheCore_Collectivity_Documents_Meta::META_EFFECTIVE_DATE ),
			'updated_at' => $this->get_scalar_meta( $post->ID, TheCore_Collectivity_Documents_Meta::META_UPDATED_AT ),
			'url' => $meta['url'],
			'download_label' => $download_label,
			'reader_url' => '' !== $reader_url ? $reader_url : $meta['url'],
			'secondary_url' => $reader_url,
			'reader_label' => $reader_label,
			'mime_type' => $meta['mimeType'],
			'file_size' => $meta['fileSize'],
			'page_count' => $page_count,
			'format_label' => $format_label,
			'thumbnail_id' => (int) get_post_thumbnail_id( $post->ID ),
			'download_aria_label' => (string) $post->post_title . ': ' . $download_label,
			'reader_aria_label' => (string) $post->post_title . ': ' . $reader_label,
		);
	}

	/**
	 * @param mixed $url Candidate URL.
	 * @return string Absolute public HTTP(S) URL, or an empty string.
	 */
	public static function sanitize_public_url( $url ) {
		if ( ! is_string( $url ) || ! preg_match( '#^https?://#i', $url ) ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}
		return esc_url_raw( $url, array( 'http', 'https' ) );
	}

	/**
	 * @param int    $document_id Document id.
	 * @param string $key Allowed key supplied by this repository.
	 * @return string
	 */
	private function get_scalar_meta( $document_id, $key ) {
		$value = get_post_meta( $document_id, $key, true );
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Resolve the public URL of a document.
	 *
	 * @param int $document_id Document id.
	 * @return string
	 */
	public function get_document_url( $document_id ) {
		$document_id   = absint( $document_id );
		$attachment_id = (int) $this->get_scalar_meta( $document_id, TheCore_Collectivity_Documents_Meta::META_ATTACHMENT_ID );
		$external_url  = $this->get_scalar_meta( $document_id, TheCore_Collectivity_Documents_Meta::META_EXTERNAL_URL );

		if ( $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id ) ) {
			$url = self::sanitize_public_url( wp_get_attachment_url( $attachment_id ) );
			if ( '' !== $url ) {
				return $url;
			}
		}

		return self::sanitize_public_url( $external_url );
	}

	/**
	 * Get lightweight download meta.
	 *
	 * @param int $document_id Document id.
	 * @return array
	 */
	public function get_download_meta( $document_id ) {
		$document_id   = absint( $document_id );
		$attachment_id = (int) $this->get_scalar_meta( $document_id, TheCore_Collectivity_Documents_Meta::META_ATTACHMENT_ID );
		$url           = $this->get_document_url( $document_id );
		$mime_type     = '';
		$file_size     = '';

		if ( $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id ) ) {
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
