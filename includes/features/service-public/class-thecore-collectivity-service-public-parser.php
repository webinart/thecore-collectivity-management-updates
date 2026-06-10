<?php
/**
 * Service-public XML parser.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Service_Public_Parser {
	/**
	 * Parse one DILA XML document into the V1 internal shape.
	 *
	 * @param string $xml XML payload.
	 * @param string $audience Audience.
	 * @param array  $source Source metadata.
	 * @param string $fallback_id ID from file name.
	 * @return array|null
	 */
	public function parse( $xml, $audience, array $source, $fallback_id = '' ) {
		if ( '' === trim( (string) $xml ) ) {
			return null;
		}

		$previous = libxml_use_internal_errors( true );
		$node     = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $node ) {
			return null;
		}

		$item_id = $this->detect_identifier( $node, $fallback_id );
		if ( ! $item_id ) {
			return null;
		}

		$title   = $this->first_text( $node, array( 'dc:title', 'Titre', 'TitrePrincipal' ) );
		$summary = $this->first_text( $node, array( 'Introduction', 'Resume', 'Description' ) );
		$type    = $this->first_text( $node, array( 'dc:type', 'Type' ) );

		if ( ! $title ) {
			$title = $this->first_non_empty_text( $node, array( 'Titre' ) );
		}

		if ( ! $summary ) {
			$summary = $this->first_non_empty_text( $node, array( 'Paragraphe', 'Texte' ) );
		}

		if ( ! $type ) {
			$type = $node->getName();
		}

		$content = $this->extract_content_preview( $node );
		$search  = trim(
			implode(
				' ',
				array_filter(
					array(
						$item_id,
						$title,
						$summary,
						$this->collapse_whitespace( wp_strip_all_tags( $this->xml_text( $node ) ) ),
					)
				)
			)
		);

		$official_base = 'professionnels' === $audience ? 'https://entreprendre.service-public.gouv.fr/vosdroits/' : 'https://www.service-public.gouv.fr/particuliers/vosdroits/';

		return array(
			'item_id'               => sanitize_text_field( $item_id ),
			'item_type'             => sanitize_text_field( $type ),
			'title'                 => sanitize_text_field( $this->collapse_whitespace( $title ?: $item_id ) ),
			'slug'                  => sanitize_title( $title ?: $item_id ),
			'summary'               => sanitize_textarea_field( $this->truncate( $this->collapse_whitespace( $summary ), 1200 ) ),
			'breadcrumb'            => $this->extract_breadcrumb( $node ),
			'content'               => $content,
			'raw_xml'               => $xml,
			'official_url'          => esc_url_raw( $official_base . rawurlencode( $item_id ) ),
			'source_url'            => esc_url_raw( $source['resource_url'] ?? '' ),
			'source_file_name'      => sanitize_file_name( $source['resource_file_name'] ?? '' ),
			'source_file_date'      => sanitize_text_field( $source['resource_date'] ?? '' ),
			'source_updated_at_gmt' => null,
			'search_text'           => sanitize_textarea_field( $this->truncate( $search, 50000 ) ),
		);
	}

	/**
	 * Detect document identifier.
	 *
	 * @param SimpleXMLElement $node XML node.
	 * @param string           $fallback_id Fallback ID.
	 * @return string
	 */
	private function detect_identifier( SimpleXMLElement $node, $fallback_id ) {
		$attrs = $node->attributes();
		foreach ( array( 'ID', 'id', 'dc:identifier', 'identifier' ) as $attr ) {
			if ( isset( $attrs[ $attr ] ) && (string) $attrs[ $attr ] ) {
				return (string) $attrs[ $attr ];
			}
		}

		$id = $this->first_text( $node, array( 'dc:identifier', 'Identifiant', 'ID' ) );
		if ( $id ) {
			return $id;
		}

		return $fallback_id;
	}

	/**
	 * Extract breadcrumb labels when present.
	 *
	 * @param SimpleXMLElement $node XML node.
	 * @return array[]
	 */
	private function extract_breadcrumb( SimpleXMLElement $node ) {
		$breadcrumb = array();
		$matches    = $node->xpath( '//*[local-name()="FildAriane"]//*[local-name()="Niveau" or local-name()="LienInterne" or local-name()="Element"]' );

		foreach ( $matches ?: array() as $entry ) {
			$label = $this->collapse_whitespace( $this->xml_text( $entry ) );
			if ( $label ) {
				$breadcrumb[] = array( 'label' => sanitize_text_field( $label ) );
			}
		}

		return $breadcrumb;
	}

	/**
	 * Extract a conservative content preview for V1 rendering.
	 *
	 * @param SimpleXMLElement $node XML node.
	 * @return array[]
	 */
	private function extract_content_preview( SimpleXMLElement $node ) {
		$content = array();
		$nodes   = $node->xpath( '//*[local-name()="Introduction" or local-name()="Chapitre" or local-name()="SousChapitre" or local-name()="Paragraphe" or local-name()="Texte" or local-name()="Avertissement" or local-name()="ASavoir" or local-name()="Attention" or local-name()="Rappel"]' );

		foreach ( $nodes ?: array() as $entry ) {
			$text = $this->collapse_whitespace( $this->xml_text( $entry ) );
			if ( ! $text || strlen( $text ) < 8 ) {
				continue;
			}

			$content[] = array(
				'type' => sanitize_key( $entry->getName() ),
				'text' => sanitize_textarea_field( $this->truncate( $text, 2200 ) ),
			);

			if ( count( $content ) >= 20 ) {
				break;
			}
		}

		return $content;
	}

	/**
	 * Return first text from a list of element names.
	 *
	 * @param SimpleXMLElement $node XML node.
	 * @param array            $names Names.
	 * @return string
	 */
	private function first_text( SimpleXMLElement $node, array $names ) {
		foreach ( $names as $name ) {
			$local_name = false !== strpos( $name, ':' ) ? substr( $name, strpos( $name, ':' ) + 1 ) : $name;
			$matches    = $node->xpath( '//*[local-name()="' . $local_name . '"]' );
			if ( ! empty( $matches[0] ) ) {
				$text = $this->collapse_whitespace( $this->xml_text( $matches[0] ) );
				if ( $text ) {
					return $text;
				}
			}
		}

		return '';
	}

	/**
	 * Return first non-empty text from matching elements.
	 *
	 * @param SimpleXMLElement $node XML node.
	 * @param array            $names Element names.
	 * @return string
	 */
	private function first_non_empty_text( SimpleXMLElement $node, array $names ) {
		foreach ( $names as $name ) {
			$matches = $node->xpath( '//*[local-name()="' . $name . '"]' );
			foreach ( $matches ?: array() as $match ) {
				$text = $this->collapse_whitespace( $this->xml_text( $match ) );
				if ( $text ) {
					return $text;
				}
			}
		}

		return '';
	}

	/**
	 * Extract XML node text.
	 *
	 * @param SimpleXMLElement $node XML node.
	 * @return string
	 */
	private function xml_text( SimpleXMLElement $node ) {
		return dom_import_simplexml( $node ) ? dom_import_simplexml( $node )->textContent : (string) $node;
	}

	/**
	 * Collapse whitespace.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private function collapse_whitespace( $text ) {
		return trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
	}

	/**
	 * Truncate without hard failing on non-mb environments.
	 *
	 * @param string $text Text.
	 * @param int    $length Length.
	 * @return string
	 */
	private function truncate( $text, $length ) {
		$text = (string) $text;
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			return mb_strlen( $text ) > $length ? mb_substr( $text, 0, $length ) . '...' : $text;
		}

		return strlen( $text ) > $length ? substr( $text, 0, $length ) . '...' : $text;
	}
}
