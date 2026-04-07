<?php
/**
 * Resolve GTFS source URLs to downloadable archives.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_Transports_GTFS_Source_Resolver {
	/**
	 * Resolve one configured GTFS URL to a concrete downloadable archive URL.
	 *
	 * @param string $configured_url Configured URL.
	 * @return array|WP_Error
	 */
	public function resolve_download( $configured_url ) {
		$configured_url = esc_url_raw( trim( (string) $configured_url ) );
		if ( '' === $configured_url ) {
			return new WP_Error( 'bellevue_transport_gtfs_url_missing', __( 'Aucune URL GTFS n est configuree.', 'bellevue' ) );
		}

		$parsed = wp_parse_url( $configured_url );
		$host   = strtolower( (string) ( $parsed['host'] ?? '' ) );
		$path   = (string) ( $parsed['path'] ?? '' );

		if ( 'transport.data.gouv.fr' === $host && preg_match( '#^/resources/\d+/?$#', $path ) ) {
			return $this->resolve_from_pan_resource_page( $configured_url );
		}

		return array(
			'configured_url' => $configured_url,
			'download_url'   => $configured_url,
			'resolution'     => 'direct',
		);
	}

	/**
	 * Download one GTFS archive to a temporary file.
	 *
	 * @param string $configured_url Configured URL.
	 * @param int    $timeout        Timeout in seconds.
	 * @return array|WP_Error
	 */
	public function download_to_temp_file( $configured_url, $timeout = 180 ) {
		$resolved = $this->resolve_download( $configured_url );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$temp_file = download_url( $resolved['download_url'], $timeout );
		if ( is_wp_error( $temp_file ) ) {
			if ( 'direct' === ( $resolved['resolution'] ?? '' ) && false !== stripos( $temp_file->get_error_message(), 'cURL error 28' ) ) {
				return new WP_Error(
					'bellevue_transport_gtfs_download_timeout',
					__(
						'Le serveur n a pas reussi a joindre l URL GTFS configuree. Si le flux existe sur transport.data.gouv.fr, utilisez plutot l URL de la page ressource du PAN (par exemple https://transport.data.gouv.fr/resources/12345).',
						'bellevue'
					)
				);
			}

			return $temp_file;
		}

		$resolved['temp_file'] = $temp_file;
		return $resolved;
	}

	/**
	 * Resolve the current downloadable GTFS archive from one PAN resource page.
	 *
	 * @param string $resource_url Resource page URL.
	 * @return array|WP_Error
	 */
	private function resolve_from_pan_resource_page( $resource_url ) {
		$response = wp_remote_get(
			$resource_url,
			array(
				'timeout'     => 20,
				'redirection' => 5,
				'sslverify'   => true,
				'user-agent'  => $this->get_user_agent(),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'bellevue_transport_gtfs_resource_unreachable',
				sprintf(
					/* translators: %s: PAN resource page URL */
					__( 'Impossible de charger la page ressource GTFS %s.', 'bellevue' ),
					$resource_url
				)
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		if ( 200 !== $status || '' === trim( (string) $body ) ) {
			return new WP_Error(
				'bellevue_transport_gtfs_resource_invalid',
				sprintf(
					/* translators: %s: PAN resource page URL */
					__( 'La page ressource GTFS %s n a pas renvoye de contenu exploitable.', 'bellevue' ),
					$resource_url
				)
			);
		}

		$download_url = $this->extract_pan_download_url( $body );
		if ( '' === $download_url ) {
			return new WP_Error(
				'bellevue_transport_gtfs_resource_parse_failed',
				sprintf(
					/* translators: %s: PAN resource page URL */
					__( 'Impossible d identifier l archive GTFS a telecharger depuis %s.', 'bellevue' ),
					$resource_url
				)
			);
		}

		return array(
			'configured_url' => $resource_url,
			'download_url'   => $download_url,
			'resolution'     => 'pan_resource_page',
		);
	}

	/**
	 * Extract the most stable GTFS ZIP URL from a PAN resource page HTML.
	 *
	 * @param string $html HTML source.
	 * @return string
	 */
	private function extract_pan_download_url( $html ) {
		$candidates = array();

		if ( preg_match_all( '#https://transport-data-gouv-fr-resource-history-prod\.cellar-c2\.services\.clever-cloud\.com/[0-9]+/[0-9.]+\.zip#i', $html, $matches ) ) {
			$candidates = array_merge( $candidates, $matches[0] );
		}

		if ( preg_match_all( '#href="(https://[^"]+\.zip)"#i', $html, $matches ) ) {
			foreach ( $matches[1] as $candidate ) {
				if ( false !== stripos( $candidate, '.geojson' ) ) {
					continue;
				}
				$candidates[] = $candidate;
			}
		}

		$candidates = array_values(
			array_unique(
				array_filter(
					array_map( 'esc_url_raw', $candidates )
				)
			)
		);

		return ! empty( $candidates ) ? (string) $candidates[0] : '';
	}

	/**
	 * Build a descriptive user agent for PAN requests.
	 *
	 * @return string
	 */
	private function get_user_agent() {
		$version = defined( 'THECORE_COLLECTIVITY_MANAGEMENT_VERSION' ) ? THECORE_COLLECTIVITY_MANAGEMENT_VERSION : 'unknown';
		return 'TheCoreCollectivityManagement/' . $version . '; ' . home_url( '/' );
	}
}
