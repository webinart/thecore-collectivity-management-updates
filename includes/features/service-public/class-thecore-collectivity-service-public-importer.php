<?php
/**
 * Service-public importer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Service_Public_Importer {
	/**
	 * Repository.
	 *
	 * @var TheCore_Collectivity_Service_Public_Repository
	 */
	private $repository;

	/**
	 * Parser.
	 *
	 * @var TheCore_Collectivity_Service_Public_Parser
	 */
	private $parser;

	/**
	 * Constructor.
	 *
	 * @param TheCore_Collectivity_Service_Public_Repository $repository Repository.
	 * @param TheCore_Collectivity_Service_Public_Parser     $parser Parser.
	 */
	public function __construct( TheCore_Collectivity_Service_Public_Repository $repository, TheCore_Collectivity_Service_Public_Parser $parser ) {
		$this->repository = $repository;
		$this->parser     = $parser;
	}

	/**
	 * Import all enabled sources.
	 *
	 * @return array
	 */
	public function import_enabled_sources() {
		$results = array();
		foreach ( $this->repository->get_enabled_sources() as $source ) {
			$results[ $source['audience'] ] = $this->import_source( (int) $source['id'] );
		}

		return $results;
	}

	/**
	 * Import one configured source.
	 *
	 * @param int $source_id Source ID.
	 * @return array|WP_Error
	 */
	public function import_source( $source_id ) {
		$source = $this->repository->get_source( $source_id );
		if ( ! $source ) {
			return new WP_Error( 'tccm_sp_source_missing', __( 'Source Service-public introuvable.', 'thecore-collectivity-management' ) );
		}

		$discovered = $this->discover_latest_resource( $source );
		if ( is_wp_error( $discovered ) ) {
			return $discovered;
		}

		if ( ! empty( $discovered['resource_url'] ) ) {
			$source = array_merge( $source, $discovered );
			$this->repository->update_source_resource_metadata( (int) $source['id'], $discovered );
		}

		if ( empty( $source['resource_url'] ) ) {
			return new WP_Error( 'tccm_sp_resource_missing', __( 'URL de ressource Service-public manquante.', 'thecore-collectivity-management' ) );
		}

		$import_id = $this->repository->create_import( $source );
		$tmp_file  = $this->download_resource( $source['resource_url'] );

		if ( is_wp_error( $tmp_file ) ) {
			$this->repository->finish_import( $import_id, 'error', 0, 0, $tmp_file->get_error_message() );
			return $tmp_file;
		}

		$result = $this->parse_archive( $tmp_file, $source );
		@unlink( $tmp_file );

		if ( is_wp_error( $result ) ) {
			$this->repository->finish_import( $import_id, 'error', 0, 0, $result->get_error_message() );
			return $result;
		}

		$replace = $this->repository->replace_items_for_audience( $source['audience'], $result['items'] );
		$this->repository->finish_import(
			$import_id,
			'success',
			$replace['inserted'],
			$replace['deleted'],
			sprintf(
				/* translators: 1: imported items, 2: parsed XML files */
				__( '%1$d contenus importes sur %2$d fichiers XML analyses.', 'thecore-collectivity-management' ),
				$replace['inserted'],
				$result['parsed']
			)
		);

		return array(
			'imported' => $replace['inserted'],
			'deleted'  => $replace['deleted'],
			'parsed'   => $result['parsed'],
		);
	}

	/**
	 * Discover latest XML resource from data.gouv metadata.
	 *
	 * @param array $source Source.
	 * @return array|WP_Error
	 */
	private function discover_latest_resource( array $source ) {
		if ( empty( $source['dataset_api_url'] ) ) {
			return array();
		}

		$response = wp_remote_get(
			$source['dataset_api_url'],
			array(
				'timeout' => 20,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'tccm_sp_dataset_http_error', sprintf( 'data.gouv a repondu avec le code HTTP %d.', $code ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['resources'] ) || ! is_array( $body['resources'] ) ) {
			return array();
		}

		$candidate      = null;
		$candidate_score = -999;
		foreach ( $body['resources'] as $resource ) {
			$format = strtolower( (string) ( $resource['format'] ?? '' ) );
			$title  = strtolower( (string) ( $resource['title'] ?? '' ) );
			$url    = (string) ( $resource['url'] ?? '' );

			if ( ! $url ) {
				continue;
			}

			$score = 0;
			if ( 'xml' === $format ) {
				$score += 100;
			}
			if ( false !== strpos( $url, 'lecomarquage.service-public.gouv.fr' ) ) {
				$score += 50;
			}
			if ( false !== strpos( $url, 'vosdroits' ) ) {
				$score += 30;
			}
			if ( false !== strpos( $title, 'fiches pratiques' ) ) {
				$score += 10;
			}
			if ( false !== strpos( $url, 'schema' ) || false !== strpos( $title, 'schema' ) || false !== strpos( $title, 'sch' ) ) {
				$score -= 60;
			}
			if ( 'pdf' === $format ) {
				$score -= 100;
			}

			if ( $score > $candidate_score ) {
				$candidate       = $resource;
				$candidate_score = $score;
			}
		}

		if ( ! $candidate || $candidate_score < 50 ) {
			return array();
		}

		return array(
			'resource_url'       => esc_url_raw( $candidate['url'] ?? '' ),
			'resource_title'     => sanitize_text_field( $candidate['title'] ?? '' ),
			'resource_file_name' => sanitize_file_name( basename( wp_parse_url( $candidate['url'] ?? '', PHP_URL_PATH ) ?: 'vosdroits-latest.zip' ) ),
			'resource_date'      => sanitize_text_field( $candidate['last_modified'] ?? $body['last_update'] ?? '' ),
		);
	}

	/**
	 * Download resource to temporary file.
	 *
	 * @param string $url Resource URL.
	 * @return string|WP_Error
	 */
	private function download_resource( $url ) {
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		return download_url( $url, 120 );
	}

	/**
	 * Parse downloaded ZIP archive.
	 *
	 * @param string $tmp_file Temporary file.
	 * @param array  $source Source row.
	 * @return array|WP_Error
	 */
	private function parse_archive( $tmp_file, array $source ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'tccm_sp_zip_missing', __( 'ZipArchive est requis pour importer les archives Service-public.', 'thecore-collectivity-management' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp_file ) ) {
			return new WP_Error( 'tccm_sp_zip_open_failed', __( 'Impossible d ouvrir l archive Service-public.', 'thecore-collectivity-management' ) );
		}

		$items  = array();
		$parsed = 0;

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = $zip->getNameIndex( $i );
			if ( ! $name || '/' === substr( $name, -1 ) || ! preg_match( '/\.xml$/i', $name ) ) {
				continue;
			}

			$xml = $zip->getFromIndex( $i );
			if ( false === $xml ) {
				continue;
			}

			$fallback_id = preg_replace( '/\.xml$/i', '', basename( $name ) );
			$item        = $this->parser->parse( $xml, $source['audience'], $source, $fallback_id );
			if ( $item ) {
				$items[] = $item;
			}

			$parsed++;
		}

		$zip->close();

		if ( empty( $items ) ) {
			return new WP_Error( 'tccm_sp_no_items', __( 'Aucun contenu Service-public exploitable trouve dans l archive.', 'thecore-collectivity-management' ) );
		}

		return array(
			'items'  => $items,
			'parsed' => $parsed,
		);
	}
}
