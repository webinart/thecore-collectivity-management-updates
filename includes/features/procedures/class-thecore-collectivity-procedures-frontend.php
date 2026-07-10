<?php
/**
 * Frontend helpers for procedures.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Procedures_Frontend {
	/**
	 * Elementor query ids.
	 */
	const QUERY_ALL                  = 'tccm_procedures';
	const QUERY_FEATURED             = 'tccm_featured_procedures';
	const QUERY_RELATED_PROCEDURES   = 'tccm_related_procedures';
	const QUERY_ETAT_CIVIL           = 'tccm_procedures_etat_civil';
	const QUERY_FEATURED_ETAT_CIVIL  = 'tccm_featured_procedures_etat_civil';
	const QUERY_URBANISME            = 'tccm_procedures_urbanisme';
	const QUERY_FEATURED_URBANISME   = 'tccm_featured_procedures_urbanisme';

	/**
	 * Shortcodes.
	 */
	const SHORTCODE_META              = 'tccm_procedure_meta';
	const SHORTCODE_BADGES            = 'tccm_procedure_badges';
	const SHORTCODE_LIST              = 'tccm_procedure_list';
	const SHORTCODE_CTA               = 'tccm_procedure_cta';
	const SHORTCODE_FAQ               = 'tccm_procedure_faq';
	const SHORTCODE_RELATED_DOCUMENTS = 'tccm_procedure_related_documents';

	/**
	 * Repository instance.
	 *
	 * @var TheCore_Collectivity_Procedures_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param TheCore_Collectivity_Procedures_Repository|null $repository Repository.
	 */
	public function __construct( $repository = null ) {
		$this->repository = $repository instanceof TheCore_Collectivity_Procedures_Repository
			? $repository
			: new TheCore_Collectivity_Procedures_Repository();
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'elementor/query/' . self::QUERY_ALL, array( $this, 'apply_all_query' ) );
		add_action( 'elementor/query/' . self::QUERY_FEATURED, array( $this, 'apply_featured_query' ) );
		add_action( 'elementor/query/' . self::QUERY_RELATED_PROCEDURES, array( $this, 'apply_related_procedures_query' ) );
		add_action( 'elementor/query/' . self::QUERY_ETAT_CIVIL, array( $this, 'apply_etat_civil_query' ) );
		add_action( 'elementor/query/' . self::QUERY_FEATURED_ETAT_CIVIL, array( $this, 'apply_featured_etat_civil_query' ) );
		add_action( 'elementor/query/' . self::QUERY_URBANISME, array( $this, 'apply_urbanisme_query' ) );
		add_action( 'elementor/query/' . self::QUERY_FEATURED_URBANISME, array( $this, 'apply_featured_urbanisme_query' ) );

		add_shortcode( self::SHORTCODE_META, array( $this, 'shortcode_meta' ) );
		add_shortcode( self::SHORTCODE_BADGES, array( $this, 'shortcode_badges' ) );
		add_shortcode( self::SHORTCODE_LIST, array( $this, 'shortcode_list' ) );
		add_shortcode( self::SHORTCODE_CTA, array( $this, 'shortcode_cta' ) );
		add_shortcode( self::SHORTCODE_FAQ, array( $this, 'shortcode_faq' ) );
		add_shortcode( self::SHORTCODE_RELATED_DOCUMENTS, array( $this, 'shortcode_related_documents' ) );
	}

	/**
	 * Apply base procedures query to Elementor.
	 *
	 * @param WP_Query $query Query object.
	 * @return void
	 */
	public function apply_all_query( $query ) {
		$this->apply_base_query( $query );
	}

	/**
	 * Apply featured procedures query to Elementor.
	 *
	 * @param WP_Query $query Query object.
	 * @return void
	 */
	public function apply_featured_query( $query ) {
		$this->apply_base_query( $query );

		$meta_query   = $query->get( 'meta_query' );
		$meta_query   = is_array( $meta_query ) ? $meta_query : array();
		$meta_query[] = array(
			'key'     => TheCore_Collectivity_Procedures_Meta::META_FEATURED,
			'value'   => '1',
			'compare' => '=',
		);

		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Apply related procedures query for current document context.
	 *
	 * @param WP_Query $query Query object.
	 * @return void
	 */
	public function apply_related_procedures_query( $query ) {
		$this->apply_base_query( $query );
		if ( ! class_exists( 'TheCore_Collectivity_Documents_Meta', false ) ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}

		$document_id = $this->resolve_post_id();
		if ( $document_id <= 0 ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}

		$procedure_ids = get_post_meta( $document_id, TheCore_Collectivity_Documents_Meta::META_RELATED_PROCEDURE_IDS, true );
		$procedure_ids = is_array( $procedure_ids ) ? array_map( 'intval', $procedure_ids ) : array();

		if ( empty( $procedure_ids ) ) {
			$related_procedures = array();

			if ( class_exists( 'TheCore_Collectivity_Documents_Repository' ) ) {
				$documents_repository = new TheCore_Collectivity_Documents_Repository();
				$related_procedures   = $documents_repository->get_related_procedures( $document_id );
			}

			$procedure_ids = wp_list_pluck( $related_procedures, 'ID' );
		}

		$procedure_ids = array_values( array_filter( array_map( 'intval', $procedure_ids ) ) );
		if ( empty( $procedure_ids ) ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}

		$query->set( 'post__in', $procedure_ids );
		$query->set( 'orderby', 'post__in' );
	}

	/**
	 * Apply procedures query filtered on "Etat civil".
	 *
	 * @param WP_Query $query Query object.
	 * @return void
	 */
	public function apply_etat_civil_query( $query ) {
		$this->apply_base_query( $query );
		$this->apply_theme_filter( $query, 'etat-civil' );
	}

	/**
	 * Apply featured procedures query filtered on "Etat civil".
	 *
	 * @param WP_Query $query Query object.
	 * @return void
	 */
	public function apply_featured_etat_civil_query( $query ) {
		$this->apply_featured_query( $query );
		$this->apply_theme_filter( $query, 'etat-civil' );
	}

	/**
	 * Apply procedures query filtered on "Urbanisme".
	 *
	 * @param WP_Query $query Query object.
	 * @return void
	 */
	public function apply_urbanisme_query( $query ) {
		$this->apply_base_query( $query );
		$this->apply_theme_filter( $query, 'urbanisme' );
	}

	/**
	 * Apply featured procedures query filtered on "Urbanisme".
	 *
	 * @param WP_Query $query Query object.
	 * @return void
	 */
	public function apply_featured_urbanisme_query( $query ) {
		$this->apply_featured_query( $query );
		$this->apply_theme_filter( $query, 'urbanisme' );
	}

	/**
	 * Render scalar procedure meta.
	 *
	 * Usage:
	 * - [tccm_procedure_meta field="summary"]
	 * - [tccm_procedure_meta field="theme"]
	 * - [tccm_procedure_meta field="primary_cta_url"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_meta( $atts ) {
		$atts = shortcode_atts(
			array(
				'post_id'   => 0,
				'field'     => 'summary',
				'separator' => ' / ',
				'empty'     => '',
			),
			$atts,
			self::SHORTCODE_META
		);

		$post_id = $this->resolve_post_id( $atts['post_id'] );
		if ( $post_id <= 0 ) {
			return (string) $atts['empty'];
		}

		$field = sanitize_key( $atts['field'] );
		$value = $this->get_meta_value( $post_id, $field, (string) $atts['separator'] );

		if ( '' === $value ) {
			return (string) $atts['empty'];
		}

		if ( false !== strpos( $field, '_url' ) ) {
			return esc_url( $value );
		}

		return esc_html( $value );
	}

	/**
	 * Render procedure badges.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_badges( $atts ) {
		$atts = shortcode_atts(
			array(
				'post_id' => 0,
			),
			$atts,
			self::SHORTCODE_BADGES
		);

		$post_id = $this->resolve_post_id( $atts['post_id'] );
		if ( $post_id <= 0 ) {
			return '';
		}

		$badges = array();

		if ( '1' === (string) get_post_meta( $post_id, TheCore_Collectivity_Procedures_Meta::META_AVAILABLE_ONLINE, true ) ) {
			$badges[] = '<span class="tccm-procedure-badge tccm-procedure-badge--online">' . esc_html__( 'En ligne', 'thecore-collectivity-management' ) . '</span>';
		}

		if ( '1' === (string) get_post_meta( $post_id, TheCore_Collectivity_Procedures_Meta::META_APPOINTMENT_REQUIRED, true ) ) {
			$badges[] = '<span class="tccm-procedure-badge tccm-procedure-badge--appointment">' . esc_html__( 'Rendez-vous requis', 'thecore-collectivity-management' ) . '</span>';
		}

		if ( empty( $badges ) ) {
			return '';
		}

		return '<div class="tccm-procedure-badges">' . implode( '', $badges ) . '</div>';
	}

	/**
	 * Render procedure line lists.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_list( $atts ) {
		$atts = shortcode_atts(
			array(
				'post_id' => 0,
				'field'   => 'required_documents',
				'format'  => 'ul',
			),
			$atts,
			self::SHORTCODE_LIST
		);

		$post_id = $this->resolve_post_id( $atts['post_id'] );
		if ( $post_id <= 0 ) {
			return '';
		}

		$field = sanitize_key( $atts['field'] );
		$items = $this->get_list_values( $post_id, $field );
		if ( empty( $items ) ) {
			return '';
		}

		$tag = 'ol' === sanitize_key( $atts['format'] ) ? 'ol' : 'ul';
		$html = '<' . $tag . ' class="tccm-procedure-list tccm-procedure-list--' . esc_attr( $field ) . '">';

		foreach ( $items as $item ) {
			$html .= '<li class="tccm-procedure-list__item">' . esc_html( $item ) . '</li>';
		}

		$html .= '</' . $tag . '>';

		return $html;
	}

	/**
	 * Render procedure CTA.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_cta( $atts ) {
		$atts = shortcode_atts(
			array(
				'post_id' => 0,
				'type'    => 'primary',
				'part'    => 'link',
				'label'   => '',
			),
			$atts,
			self::SHORTCODE_CTA
		);

		$post_id = $this->resolve_post_id( $atts['post_id'] );
		if ( $post_id <= 0 ) {
			return '';
		}

		$type = 'secondary' === sanitize_key( $atts['type'] ) ? 'secondary' : 'primary';
		$part = sanitize_key( $atts['part'] );

		$label_key = 'secondary' === $type ? TheCore_Collectivity_Procedures_Meta::META_SECONDARY_CTA_LABEL : TheCore_Collectivity_Procedures_Meta::META_PRIMARY_CTA_LABEL;
		$url_key   = 'secondary' === $type ? TheCore_Collectivity_Procedures_Meta::META_SECONDARY_CTA_URL : TheCore_Collectivity_Procedures_Meta::META_PRIMARY_CTA_URL;

		$label = (string) get_post_meta( $post_id, $label_key, true );
		$url   = (string) get_post_meta( $post_id, $url_key, true );

		if ( '' !== (string) $atts['label'] ) {
			$label = (string) $atts['label'];
		}

		if ( '' === $url ) {
			return '';
		}

		if ( 'url' === $part ) {
			return esc_url( $url );
		}

		if ( 'label' === $part ) {
			return esc_html( $label );
		}

		if ( '' === $label ) {
			$label = esc_html__( 'Consulter', 'thecore-collectivity-management' );
		}

		return sprintf(
			'<a class="tccm-procedure-cta tccm-procedure-cta--%1$s" href="%2$s">%3$s</a>',
			esc_attr( $type ),
			esc_url( $url ),
			esc_html( $label )
		);
	}

	/**
	 * Render procedure FAQ.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_faq( $atts ) {
		$atts = shortcode_atts(
			array(
				'post_id' => 0,
			),
			$atts,
			self::SHORTCODE_FAQ
		);

		$post_id = $this->resolve_post_id( $atts['post_id'] );
		if ( $post_id <= 0 ) {
			return '';
		}

		$rows = get_post_meta( $post_id, TheCore_Collectivity_Procedures_Meta::META_FAQ, true );
		$rows = is_array( $rows ) ? $rows : array();
		$rows = array_values(
			array_filter(
				$rows,
				static function( $row ) {
					return ! empty( $row['question'] ) || ! empty( $row['answer'] );
				}
			)
		);

		if ( empty( $rows ) ) {
			return '';
		}

		$html = '<div class="tccm-procedure-faq">';

		foreach ( $rows as $row ) {
			$question = isset( $row['question'] ) ? trim( (string) $row['question'] ) : '';
			$answer   = isset( $row['answer'] ) ? trim( (string) $row['answer'] ) : '';

			if ( '' === $question && '' === $answer ) {
				continue;
			}

			$html .= '<div class="tccm-procedure-faq__item">';
			if ( '' !== $question ) {
				$html .= '<div class="tccm-procedure-faq__question">' . esc_html( $question ) . '</div>';
			}
			if ( '' !== $answer ) {
				$html .= '<div class="tccm-procedure-faq__answer"><p>' . nl2br( esc_html( $answer ) ) . '</p></div>';
			}
			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render related documents list.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_related_documents( $atts ) {
		$atts = shortcode_atts(
			array(
				'post_id'   => 0,
				'show_meta' => '1',
			),
			$atts,
			self::SHORTCODE_RELATED_DOCUMENTS
		);

		$post_id = $this->resolve_post_id( $atts['post_id'] );
		if ( $post_id <= 0 ) {
			return '';
		}

		$documents = $this->repository->get_related_documents( $post_id );
		if ( empty( $documents ) ) {
			return '';
		}

		$documents_repository = class_exists( 'TheCore_Collectivity_Documents_Repository' ) ? new TheCore_Collectivity_Documents_Repository() : null;
		$show_meta            = '0' !== (string) $atts['show_meta'];
		$html                 = '<div class="tccm-related-documents">';

		foreach ( $documents as $document ) {
			$url        = $documents_repository ? $documents_repository->get_document_url( $document->ID ) : '';
			$reference  = class_exists( 'TheCore_Collectivity_Documents_Meta' ) ? (string) get_post_meta( $document->ID, TheCore_Collectivity_Documents_Meta::META_REFERENCE, true ) : '';
			$summary    = class_exists( 'TheCore_Collectivity_Documents_Meta' ) ? (string) get_post_meta( $document->ID, TheCore_Collectivity_Documents_Meta::META_SUMMARY, true ) : '';
			$item_class = 'tccm-related-documents__item';

			$html .= '<div class="' . esc_attr( $item_class ) . '">';
			if ( $url ) {
				$html .= '<a class="tccm-related-documents__link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( get_the_title( $document ) ) . '</a>';
			} else {
				$html .= '<span class="tccm-related-documents__title">' . esc_html( get_the_title( $document ) ) . '</span>';
			}

			if ( $show_meta && '' !== $reference ) {
				$html .= '<div class="tccm-related-documents__reference">' . esc_html( $reference ) . '</div>';
			}

			if ( $show_meta && '' !== $summary ) {
				$html .= '<div class="tccm-related-documents__summary">' . esc_html( $summary ) . '</div>';
			}

			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Apply base procedures query.
	 *
	 * @param WP_Query $query Query object.
	 * @return void
	 */
	private function apply_base_query( $query ) {
		$query->set( 'post_type', TheCore_Collectivity_Procedures_Post_Type::POST_TYPE );
		$query->set( 'post_status', 'publish' );
		$query->set( 'ignore_sticky_posts', true );
		$query->set( 'meta_key', TheCore_Collectivity_Procedures_Meta::META_SORT_ORDER );
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
			'taxonomy' => TheCore_Collectivity_Procedures_Post_Type::TAXONOMY_THEME,
			'field'    => 'slug',
			'terms'    => array( sanitize_title( $theme_slug ) ),
		);

		$query->set( 'tax_query', $tax_query );
	}

	/**
	 * Resolve a procedure meta field.
	 *
	 * @param int    $post_id Procedure id.
	 * @param string $field   Field slug.
	 * @param string $separator Join separator.
	 * @return string
	 */
	private function get_meta_value( $post_id, $field, $separator ) {
		switch ( $field ) {
			case 'summary':
				return (string) get_post_meta( $post_id, TheCore_Collectivity_Procedures_Meta::META_SUMMARY, true );

			case 'delay':
			case 'delay_label':
				return (string) get_post_meta( $post_id, TheCore_Collectivity_Procedures_Meta::META_DELAY_LABEL, true );

			case 'fee':
			case 'fee_label':
				return (string) get_post_meta( $post_id, TheCore_Collectivity_Procedures_Meta::META_FEE_LABEL, true );

			case 'theme':
				return $this->get_joined_term_names( $post_id, TheCore_Collectivity_Procedures_Post_Type::TAXONOMY_THEME, $separator );

			case 'type':
				return $this->get_joined_term_names( $post_id, TheCore_Collectivity_Procedures_Post_Type::TAXONOMY_TYPE, $separator );

			case 'primary_cta_label':
				return (string) get_post_meta( $post_id, TheCore_Collectivity_Procedures_Meta::META_PRIMARY_CTA_LABEL, true );

			case 'primary_cta_url':
				return (string) get_post_meta( $post_id, TheCore_Collectivity_Procedures_Meta::META_PRIMARY_CTA_URL, true );

			case 'secondary_cta_label':
				return (string) get_post_meta( $post_id, TheCore_Collectivity_Procedures_Meta::META_SECONDARY_CTA_LABEL, true );

			case 'secondary_cta_url':
				return (string) get_post_meta( $post_id, TheCore_Collectivity_Procedures_Meta::META_SECONDARY_CTA_URL, true );

			case 'available_online':
				return '1' === (string) get_post_meta( $post_id, TheCore_Collectivity_Procedures_Meta::META_AVAILABLE_ONLINE, true ) ? '1' : '';

			case 'appointment_required':
				return '1' === (string) get_post_meta( $post_id, TheCore_Collectivity_Procedures_Meta::META_APPOINTMENT_REQUIRED, true ) ? '1' : '';
		}

		return '';
	}

	/**
	 * Get list values for a procedure field.
	 *
	 * @param int    $post_id Procedure id.
	 * @param string $field   Field slug.
	 * @return array
	 */
	private function get_list_values( $post_id, $field ) {
		$meta_key = '';

		switch ( $field ) {
			case 'required_documents':
				$meta_key = TheCore_Collectivity_Procedures_Meta::META_REQUIRED_DOCUMENTS;
				break;

			case 'steps':
				$meta_key = TheCore_Collectivity_Procedures_Meta::META_STEPS;
				break;

			case 'cases':
				$meta_key = TheCore_Collectivity_Procedures_Meta::META_CASES;
				break;
		}

		if ( '' === $meta_key ) {
			return array();
		}

		$values = get_post_meta( $post_id, $meta_key, true );
		$values = is_array( $values ) ? $values : array();

		return array_values(
			array_filter(
				array_map( 'trim', array_map( 'strval', $values ) )
			)
		);
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
