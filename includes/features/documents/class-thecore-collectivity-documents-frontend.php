<?php
/**
 * Frontend helpers for documents.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Documents_Frontend {
	/**
	 * Elementor query ids.
	 */
	const QUERY_ALL               = 'tccm_documents';
	const QUERY_FEATURED          = 'tccm_featured_documents';
	const QUERY_RELATED_DOCUMENTS = 'tccm_related_documents';
	const QUERY_ETAT_CIVIL        = 'tccm_documents_etat_civil';
	const QUERY_URBANISME         = 'tccm_documents_urbanisme';

	/**
	 * Shortcodes.
	 */
	const SHORTCODE_META               = 'tccm_document_meta';
	const SHORTCODE_LINK               = 'tccm_document_link';
	const SHORTCODE_RELATED_PROCEDURES = 'tccm_document_related_procedures';

	/**
	 * Repository instance.
	 *
	 * @var TheCore_Collectivity_Documents_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param TheCore_Collectivity_Documents_Repository|null $repository Repository.
	 */
	public function __construct( $repository = null ) {
		$this->repository = $repository instanceof TheCore_Collectivity_Documents_Repository
			? $repository
			: new TheCore_Collectivity_Documents_Repository();
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'elementor/query/' . self::QUERY_ALL, array( $this, 'apply_all_query' ) );
		add_action( 'elementor/query/' . self::QUERY_FEATURED, array( $this, 'apply_featured_query' ) );
		add_action( 'elementor/query/' . self::QUERY_RELATED_DOCUMENTS, array( $this, 'apply_related_documents_query' ) );
		add_action( 'elementor/query/' . self::QUERY_ETAT_CIVIL, array( $this, 'apply_etat_civil_query' ) );
		add_action( 'elementor/query/' . self::QUERY_URBANISME, array( $this, 'apply_urbanisme_query' ) );

		add_shortcode( self::SHORTCODE_META, array( $this, 'shortcode_meta' ) );
		add_shortcode( self::SHORTCODE_LINK, array( $this, 'shortcode_link' ) );
		add_shortcode( self::SHORTCODE_RELATED_PROCEDURES, array( $this, 'shortcode_related_procedures' ) );
	}

	/**
	 * Apply all documents query to Elementor.
	 *
	 * @param WP_Query $query Query object.
	 * @return void
	 */
	public function apply_all_query( $query ) {
		$this->apply_base_query( $query );
	}

	/**
	 * Apply featured documents query to Elementor.
	 *
	 * @param WP_Query $query Query object.
	 * @return void
	 */
	public function apply_featured_query( $query ) {
		$this->apply_base_query( $query );

		$meta_query   = $query->get( 'meta_query' );
		$meta_query   = is_array( $meta_query ) ? $meta_query : array();
		$meta_query[] = array(
			'key'     => TheCore_Collectivity_Documents_Meta::META_FEATURED,
			'value'   => '1',
			'compare' => '=',
		);

		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Apply related documents query for current procedure context.
	 *
	 * @param WP_Query $query Query object.
	 * @return void
	 */
	public function apply_related_documents_query( $query ) {
		$this->apply_base_query( $query );
		if ( ! class_exists( 'TheCore_Collectivity_Procedures_Meta', false ) ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}

		$procedure_id = $this->resolve_post_id();
		if ( $procedure_id <= 0 ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}

		$documents    = array();
		$document_ids = get_post_meta( $procedure_id, TheCore_Collectivity_Procedures_Meta::META_RELATED_DOCUMENT_IDS, true );
		$document_ids = is_array( $document_ids ) ? array_map( 'intval', $document_ids ) : array();

		if ( empty( $document_ids ) && class_exists( 'TheCore_Collectivity_Procedures_Repository' ) ) {
			$procedures_repository = new TheCore_Collectivity_Procedures_Repository();
			$documents             = $procedures_repository->get_related_documents( $procedure_id );
			$document_ids          = wp_list_pluck( $documents, 'ID' );
		}

		$document_ids = array_values( array_filter( array_map( 'intval', $document_ids ) ) );
		if ( empty( $document_ids ) ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}

		$query->set( 'post__in', $document_ids );
		$query->set( 'orderby', 'post__in' );
	}

	/**
	 * Apply documents query filtered on "Etat civil".
	 *
	 * @param WP_Query $query Query object.
	 * @return void
	 */
	public function apply_etat_civil_query( $query ) {
		$this->apply_base_query( $query );
		$this->apply_theme_filter( $query, 'etat-civil' );
	}

	/**
	 * Apply documents query filtered on "Urbanisme".
	 *
	 * @param WP_Query $query Query object.
	 * @return void
	 */
	public function apply_urbanisme_query( $query ) {
		$this->apply_base_query( $query );
		$this->apply_theme_filter( $query, 'urbanisme' );
	}

	/**
	 * Render document scalar meta.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_meta( $atts ) {
		$atts = shortcode_atts(
			array(
				'post_id'     => 0,
				'field'       => 'summary',
				'separator'   => ' / ',
				'date_format' => 'j F Y',
				'empty'       => '',
			),
			$atts,
			self::SHORTCODE_META
		);

		$post_id = $this->resolve_post_id( $atts['post_id'] );
		if ( $post_id <= 0 ) {
			return (string) $atts['empty'];
		}

		$field = sanitize_key( $atts['field'] );
		$value = $this->get_meta_value( $post_id, $field, (string) $atts['separator'], (string) $atts['date_format'] );

		if ( '' === $value ) {
			return (string) $atts['empty'];
		}

		if ( in_array( $field, array( 'url', 'download_url' ), true ) ) {
			return esc_url( $value );
		}

		return esc_html( $value );
	}

	/**
	 * Render document link.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_link( $atts ) {
		$atts = shortcode_atts(
			array(
				'post_id' => 0,
				'part'    => 'link',
				'label'   => '',
			),
			$atts,
			self::SHORTCODE_LINK
		);

		$post_id = $this->resolve_post_id( $atts['post_id'] );
		if ( $post_id <= 0 ) {
			return '';
		}

		$url = $this->repository->get_document_url( $post_id );
		if ( '' === $url ) {
			return '';
		}

		$part  = sanitize_key( $atts['part'] );
		$label = '' !== (string) $atts['label'] ? (string) $atts['label'] : esc_html__( 'Consulter le document', 'thecore-collectivity-management' );

		if ( 'url' === $part ) {
			return esc_url( $url );
		}

		if ( 'label' === $part ) {
			return esc_html( $label );
		}

		return sprintf(
			'<a class="tccm-document-link" href="%1$s" target="_blank" rel="noopener">%2$s</a>',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	/**
	 * Render related procedures list.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_related_procedures( $atts ) {
		$atts = shortcode_atts(
			array(
				'post_id' => 0,
			),
			$atts,
			self::SHORTCODE_RELATED_PROCEDURES
		);

		$post_id = $this->resolve_post_id( $atts['post_id'] );
		if ( $post_id <= 0 ) {
			return '';
		}

		$procedures = $this->repository->get_related_procedures( $post_id );
		if ( empty( $procedures ) ) {
			return '';
		}

		$html = '<div class="tccm-related-procedures">';

		foreach ( $procedures as $procedure ) {
			$permalink = get_permalink( $procedure );
			$summary   = class_exists( 'TheCore_Collectivity_Procedures_Meta', false ) ? (string) get_post_meta( $procedure->ID, TheCore_Collectivity_Procedures_Meta::META_SUMMARY, true ) : '';

			$html .= '<div class="tccm-related-procedures__item">';
			if ( $permalink ) {
				$html .= '<a class="tccm-related-procedures__link" href="' . esc_url( $permalink ) . '">' . esc_html( get_the_title( $procedure ) ) . '</a>';
			} else {
				$html .= '<span class="tccm-related-procedures__title">' . esc_html( get_the_title( $procedure ) ) . '</span>';
			}

			if ( '' !== $summary ) {
				$html .= '<div class="tccm-related-procedures__summary">' . esc_html( $summary ) . '</div>';
			}

			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Apply base documents query.
	 *
	 * @param WP_Query $query Query object.
	 * @return void
	 */
	private function apply_base_query( $query ) {
		$query->set( 'post_type', TheCore_Collectivity_Documents_Post_Type::POST_TYPE );
		$query->set( 'post_status', 'publish' );
		$query->set( 'ignore_sticky_posts', true );
		$query->set( 'meta_key', TheCore_Collectivity_Documents_Meta::META_SORT_ORDER );
		$query->set(
			'orderby',
			array(
				'meta_value_num' => 'ASC',
				'title'          => 'ASC',
			)
		);
		$query->set( 'order', 'ASC' );

		if ( ! $query->get( 'posts_per_page' ) ) {
			$query->set( 'posts_per_page', -1 );
		}
	}

	/**
	 * Merge a theme taxonomy filter into a query.
	 *
	 * @param WP_Query $query Query object.
	 * @param string   $theme_slug Theme term slug.
	 * @return void
	 */
	private function apply_theme_filter( $query, $theme_slug ) {
		$tax_query   = $query->get( 'tax_query' );
		$tax_query   = is_array( $tax_query ) ? $tax_query : array();
		$tax_query[] = array(
			'taxonomy' => TheCore_Collectivity_Documents_Post_Type::TAXONOMY_THEME,
			'field'    => 'slug',
			'terms'    => array( sanitize_title( $theme_slug ) ),
		);

		$query->set( 'tax_query', $tax_query );
	}

	/**
	 * Resolve a document meta field.
	 *
	 * @param int    $post_id Post id.
	 * @param string $field Field slug.
	 * @param string $separator Separator.
	 * @param string $date_format Date format.
	 * @return string
	 */
	private function get_meta_value( $post_id, $field, $separator, $date_format ) {
		switch ( $field ) {
			case 'summary':
				return (string) get_post_meta( $post_id, TheCore_Collectivity_Documents_Meta::META_SUMMARY, true );

			case 'reference':
				return (string) get_post_meta( $post_id, TheCore_Collectivity_Documents_Meta::META_REFERENCE, true );

			case 'version':
			case 'version_label':
				return (string) get_post_meta( $post_id, TheCore_Collectivity_Documents_Meta::META_VERSION_LABEL, true );

			case 'theme':
				return $this->get_joined_term_names( $post_id, TheCore_Collectivity_Documents_Post_Type::TAXONOMY_THEME, $separator );

			case 'type':
				return $this->get_joined_term_names( $post_id, TheCore_Collectivity_Documents_Post_Type::TAXONOMY_TYPE, $separator );

			case 'effective_date':
				return $this->format_date( (string) get_post_meta( $post_id, TheCore_Collectivity_Documents_Meta::META_EFFECTIVE_DATE, true ), $date_format );

			case 'updated_at':
				return $this->format_date( (string) get_post_meta( $post_id, TheCore_Collectivity_Documents_Meta::META_UPDATED_AT, true ), $date_format );

			case 'url':
			case 'download_url':
				return $this->repository->get_document_url( $post_id );

			case 'mime':
			case 'mime_type':
				$download = $this->repository->get_download_meta( $post_id );
				return isset( $download['mimeType'] ) ? (string) $download['mimeType'] : '';

			case 'file_size':
			case 'size':
				$download = $this->repository->get_download_meta( $post_id );
				return isset( $download['fileSize'] ) ? (string) $download['fileSize'] : '';
		}

		return '';
	}

	/**
	 * Format a stored Y-m-d date.
	 *
	 * @param string $value Stored date.
	 * @param string $date_format Output format.
	 * @return string
	 */
	private function format_date( $value, $date_format ) {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value . ' 00:00:00' );
		if ( false === $timestamp ) {
			return '';
		}

		return wp_date( $date_format, $timestamp, wp_timezone() );
	}

	/**
	 * Get joined taxonomy term names.
	 *
	 * @param int    $post_id Post id.
	 * @param string $taxonomy Taxonomy slug.
	 * @param string $separator Separator.
	 * @return string
	 */
	private function get_joined_term_names( $post_id, $taxonomy, $separator ) {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		$names = wp_list_pluck( $terms, 'name' );
		$names = array_values( array_filter( array_map( 'trim', array_map( 'strval', $names ) ) ) );

		return implode( $separator, $names );
	}

	/**
	 * Resolve current post id.
	 *
	 * @param int|string $post_id Optional explicit post id.
	 * @return int
	 */
	private function resolve_post_id( $post_id = 0 ) {
		$post_id = absint( $post_id );
		if ( $post_id > 0 ) {
			return $post_id;
		}

		$post_id = get_the_ID();
		if ( $post_id ) {
			return absint( $post_id );
		}

		$queried_id = get_queried_object_id();
		if ( $queried_id ) {
			return absint( $queried_id );
		}

		global $post;

		return ( $post instanceof WP_Post ) ? (int) $post->ID : 0;
	}
}
