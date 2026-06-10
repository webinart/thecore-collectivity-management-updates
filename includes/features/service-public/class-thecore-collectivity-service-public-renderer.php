<?php
/**
 * Service-public frontend renderer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Service_Public_Renderer {
	/**
	 * Repository.
	 *
	 * @var TheCore_Collectivity_Service_Public_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param TheCore_Collectivity_Service_Public_Repository $repository Repository.
	 */
	public function __construct( TheCore_Collectivity_Service_Public_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Render one fiche.
	 *
	 * @param string $audience Audience.
	 * @param string $item_id Item ID.
	 * @param array  $args Render args.
	 * @return string
	 */
	public function render_item( $audience, $item_id, array $args = array() ) {
		$this->enqueue_assets();

		$defaults = array(
			'show_title'  => true,
			'show_source' => true,
			'empty_text'  => __( 'Cette fiche Service-public n est pas encore disponible localement.', 'thecore-collectivity-management' ),
		);
		$args      = array_merge( $defaults, $args );
		$cache_key = $this->build_cache_key( 'item', $audience, $item_id, $args );
		$cached    = $this->repository->get_render_cache( $audience, $item_id, $cache_key );

		if ( null !== $cached ) {
			return $cached;
		}

		$item = $this->repository->get_item( $audience, $item_id );

		if ( ! $item ) {
			return '<div class="tccm-service-public tccm-service-public--empty"><p>' . esc_html( $args['empty_text'] ) . '</p></div>';
		}

		$html = '<article class="tccm-service-public tccm-service-public--fiche" data-tccm-sp-audience="' . esc_attr( $audience ) . '" data-tccm-sp-id="' . esc_attr( $item_id ) . '">';

		if ( ! empty( $item['breadcrumb'] ) ) {
			$html .= '<nav class="tccm-service-public__breadcrumb" aria-label="' . esc_attr__( 'Fil d Ariane Service-public', 'thecore-collectivity-management' ) . '">';
			foreach ( $item['breadcrumb'] as $index => $entry ) {
				if ( $index > 0 ) {
					$html .= '<span class="tccm-service-public__breadcrumb-separator">/</span>';
				}
				$html .= '<span>' . esc_html( $entry['label'] ?? '' ) . '</span>';
			}
			$html .= '</nav>';
		}

		if ( $args['show_title'] ) {
			$html .= '<h2 class="tccm-service-public__title">' . esc_html( $item['title'] ) . '</h2>';
		}

		if ( ! empty( $item['summary'] ) ) {
			$html .= '<p class="tccm-service-public__summary">' . esc_html( $item['summary'] ) . '</p>';
		}

		if ( ! empty( $item['content'] ) && is_array( $item['content'] ) ) {
			$html .= '<div class="tccm-service-public__content">';
			foreach ( $item['content'] as $block ) {
				$text = trim( (string) ( $block['text'] ?? '' ) );
				if ( ! $text ) {
					continue;
				}

				$type = sanitize_html_class( $block['type'] ?? 'text' );
				if ( in_array( $type, array( 'chapitre', 'souschapitre' ), true ) ) {
					$html .= '<h3 class="tccm-service-public__block tccm-service-public__block--' . esc_attr( $type ) . '">' . esc_html( $text ) . '</h3>';
				} elseif ( in_array( $type, array( 'avertissement', 'asavoir', 'attention', 'rappel' ), true ) ) {
					$html .= '<div class="tccm-service-public__notice tccm-service-public__notice--' . esc_attr( $type ) . '">' . esc_html( $text ) . '</div>';
				} else {
					$html .= '<p class="tccm-service-public__block tccm-service-public__block--' . esc_attr( $type ) . '">' . esc_html( $text ) . '</p>';
				}
			}
			$html .= '</div>';
		}

		if ( ! empty( $item['official_url'] ) ) {
			$html .= '<p class="tccm-service-public__official"><a href="' . esc_url( $item['official_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Voir la fiche officielle', 'thecore-collectivity-management' ) . '</a></p>';
		}

		if ( $args['show_source'] ) {
			$html .= $this->render_source_notice( $item );
		}

		$html .= '</article>';

		$this->repository->set_render_cache( $audience, $item_id, $cache_key, $html );

		return $html;
	}

	/**
	 * Render summary cards.
	 *
	 * @param string $audience Audience.
	 * @param int    $limit Limit.
	 * @return string
	 */
	public function render_summary( $audience, $limit = 24 ) {
		$this->enqueue_assets();

		$items = $this->repository->get_summary_items( $audience, $limit );

		if ( empty( $items ) ) {
			return '<div class="tccm-service-public tccm-service-public--empty"><p>' . esc_html__( 'Aucun sommaire Service-public importe pour le moment.', 'thecore-collectivity-management' ) . '</p></div>';
		}

		$html = '<div class="tccm-service-public tccm-service-public--summary"><div class="tccm-service-public__cards">';
		foreach ( $items as $item ) {
			$html .= '<article class="tccm-service-public__card">';
			$html .= '<h3><a href="' . esc_url( self::get_public_url( $item['audience'], $item['item_id'] ) ) . '">' . esc_html( $item['title'] ) . '</a></h3>';
			if ( ! empty( $item['summary'] ) ) {
				$html .= '<p>' . esc_html( wp_trim_words( $item['summary'], 24 ) ) . '</p>';
			}
			$html .= '<span class="tccm-service-public__card-id">' . esc_html( $item['item_id'] ) . '</span>';
			$html .= '</article>';
		}
		$html .= '</div></div>';

		return $html;
	}

	/**
	 * Render search form and results.
	 *
	 * @param string $audience Audience.
	 * @param string $query Query.
	 * @param int    $limit Limit.
	 * @return string
	 */
	public function render_search( $audience = '', $query = '', $limit = 20 ) {
		$this->enqueue_assets();

		$query = '' !== $query ? $query : sanitize_text_field( wp_unslash( $_GET['tccm_sp_q'] ?? '' ) );
		$items = $query ? $this->repository->search_items( $query, $audience, $limit ) : array();

		$html  = '<div class="tccm-service-public tccm-service-public--search">';
		$html .= '<form class="tccm-service-public__search-form" method="get">';
		$html .= '<label><span>' . esc_html__( 'Rechercher une demarche', 'thecore-collectivity-management' ) . '</span><input type="search" name="tccm_sp_q" value="' . esc_attr( $query ) . '"></label>';
		$html .= '<button type="submit">' . esc_html__( 'Rechercher', 'thecore-collectivity-management' ) . '</button>';
		$html .= '</form>';

		if ( $query ) {
			$html .= '<div class="tccm-service-public__results">';
			if ( empty( $items ) ) {
				$html .= '<p>' . esc_html__( 'Aucun resultat.', 'thecore-collectivity-management' ) . '</p>';
			} else {
				foreach ( $items as $item ) {
					$html .= '<article class="tccm-service-public__result">';
					$html .= '<h3><a href="' . esc_url( self::get_public_url( $item['audience'], $item['item_id'] ) ) . '">' . esc_html( $item['title'] ) . '</a></h3>';
					$html .= '<p class="tccm-service-public__result-meta">' . esc_html( $item['audience'] . ' / ' . $item['item_id'] ) . '</p>';
					if ( ! empty( $item['summary'] ) ) {
						$html .= '<p>' . esc_html( wp_trim_words( $item['summary'], 32 ) ) . '</p>';
					}
					$html .= '</article>';
				}
			}
			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Return the public URL for a local Service-public item.
	 *
	 * @param string $audience Audience.
	 * @param string $item_id Item ID.
	 * @return string
	 */
	public static function get_public_url( $audience, $item_id ) {
		return home_url( user_trailingslashit( 'service-public/' . rawurlencode( $audience ) . '/' . rawurlencode( $item_id ) ) );
	}

	/**
	 * Enqueue frontend assets with the shared Elementor handle when available.
	 *
	 * @return void
	 */
	private function enqueue_assets() {
		$handle = class_exists( 'TheCore_Collectivity_Elementor', false ) ? TheCore_Collectivity_Elementor::HANDLE_SERVICE_PUBLIC_STYLE : 'tccm-service-public';

		if ( wp_style_is( $handle, 'registered' ) ) {
			wp_enqueue_style( $handle );
			return;
		}

		wp_enqueue_style( 'tccm-service-public' );
	}

	/**
	 * Build a stable render cache key.
	 *
	 * @param string $context Context.
	 * @param string $audience Audience.
	 * @param string $item_id Item ID.
	 * @param array  $args Render args.
	 * @return string
	 */
	private function build_cache_key( $context, $audience, $item_id, array $args ) {
		return $context . ':' . md5(
			wp_json_encode(
				array(
					'audience' => $audience,
					'item_id'  => $item_id,
					'args'     => $args,
				)
			)
		);
	}

	/**
	 * Render source notice.
	 *
	 * @param array $item Item.
	 * @return string
	 */
	private function render_source_notice( array $item ) {
		$source = 'professionnels' === $item['audience'] ? 'Entreprendre.Service-Public.gouv.fr / DILA' : 'Service-Public.gouv.fr / DILA';
		$parts  = array( $source );

		if ( ! empty( $item['source_file_name'] ) ) {
			$parts[] = sprintf( __( 'fichier %s', 'thecore-collectivity-management' ), $item['source_file_name'] );
		}

		if ( ! empty( $item['source_file_date'] ) ) {
			$parts[] = sprintf( __( 'date %s', 'thecore-collectivity-management' ), $item['source_file_date'] );
		}

		$html = '<p class="tccm-service-public__source">' . esc_html__( 'Source : ', 'thecore-collectivity-management' ) . esc_html( implode( ' - ', $parts ) );
		if ( ! empty( $item['source_url'] ) ) {
			$html .= ' <a href="' . esc_url( $item['source_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'telechargement', 'thecore-collectivity-management' ) . '</a>';
		}
		$html .= '</p>';

		return $html;
	}
}
