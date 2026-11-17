<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_PDF_Client {
	const MAX_BYTES = 20971520;
	private static $configuration;

	public static function configuration_path() {
		return defined( 'THECORE_COLLECTIVITY_PDF_CONFIG' ) ? THECORE_COLLECTIVITY_PDF_CONFIG : dirname( rtrim( ABSPATH, '/\\' ) ) . '/.config/thecore-pdf-' . substr( hash( 'sha256', home_url( '/' ) ), 0, 16 ) . '.json';
	}

	public static function error( $status = 503, $retry = 60 ) {
		return new WP_Error( 'tccm_pdf_unavailable', __( 'Le PDF est temporairement indisponible. Veuillez réessayer dans quelques instants.', 'thecore-collectivity-management' ), array( 'status' => $status, 'retry_after' => $retry ) );
	}

	private static function outside_wordpress( $path ) {
		$path = rtrim( $path, '/\\' ) . '/';
		foreach ( array( ABSPATH, WP_CONTENT_DIR ) as $root ) {
			$root = realpath( $root );
			if ( $root && 0 === strpos( $path, rtrim( $root, '/\\' ) . '/' ) ) {
				return false;
			}
		}
		return true;
	}

	public static function configuration() {
		if ( null !== self::$configuration ) {
			return self::$configuration;
		}
		self::$configuration = self::error();
		$path = realpath( self::configuration_path() );
		if ( ! $path || ! self::outside_wordpress( $path ) || ! is_readable( $path ) || ( fileperms( $path ) & 0077 ) || filesize( $path ) > 16384 ) {
			return self::$configuration;
		}
		$config = json_decode( file_get_contents( $path ), true );
		if ( ! is_array( $config ) || ! is_string( $config['service_url'] ?? null ) || ! is_string( $config['api_key'] ?? null ) || ! preg_match( '/^[A-Za-z0-9_-]{32,256}$/D', $config['api_key'] ) ) {
			return self::$configuration;
		}
		$base = rtrim( $config['service_url'], '/' );
		$parsed = wp_parse_url( $base );
		if ( ! $parsed || 'https' !== ( $parsed['scheme'] ?? '' ) || empty( $parsed['host'] ) || isset( $parsed['user'] ) || isset( $parsed['pass'] ) || isset( $parsed['port'] ) || isset( $parsed['query'] ) || isset( $parsed['fragment'] ) || ! empty( $parsed['path'] ) || filter_var( $parsed['host'], FILTER_VALIDATE_IP ) ) {
			return self::$configuration;
		}
		$certificate = is_string( $config['certificate'] ?? null ) ? realpath( $config['certificate'] ) : false;
		$storage = is_string( $config['storage'] ?? null ) ? realpath( $config['storage'] ) : false;
		if ( ! $certificate || ! is_readable( $certificate ) || ! $storage || ! is_dir( $storage ) || ! is_writable( $storage ) || ! self::outside_wordpress( $storage ) || ( fileperms( $storage ) & 0077 ) ) {
			return self::$configuration;
		}
		self::$configuration = array( 'service_url' => $base, 'api_key' => $config['api_key'], 'certificate' => $certificate, 'storage' => $storage );
		return self::$configuration;
	}

	public static function enabled() {
		return ! is_wp_error( self::configuration() );
	}

	private static function request( $url, array $arguments ) {
		$config = self::configuration();
		if ( is_wp_error( $config ) ) {
			return $config;
		}
		return wp_remote_request( $url, array_merge( array(
			'timeout' => 60, 'redirection' => 0, 'sslverify' => true, 'sslcertificates' => $config['certificate'],
			'reject_unsafe_urls' => true, 'limit_response_size' => 16384,
		), $arguments ) );
	}

	public static function render_to_file( $source, $revision, $filename, array $options = array() ) {
		$config = self::configuration();
		if ( is_wp_error( $config ) ) {
			return $config;
		}
		$directory = realpath( dirname( $filename ) );
		if ( ! $directory || $directory !== $config['storage'] || ! preg_match( '/^https:\/\//D', $source ) ) {
			return self::error();
		}
		$payload = array_intersect_key( $options, array_flip( array( 'format', 'landscape', 'readySelector' ) ) );
		$payload['url'] = $source;
		$payload['revision'] = $revision;
		$response = self::request( $config['service_url'] . '/v1/pdfs', array(
			'method' => 'POST', 'headers' => array( 'Authorization' => 'Bearer ' . $config['api_key'], 'Content-Type' => 'application/json' ),
			'body' => wp_json_encode( $payload ),
		) );
		if ( is_wp_error( $response ) ) {
			return self::error();
		}
		$status = wp_remote_retrieve_response_code( $response );
		if ( ! in_array( $status, array( 200, 201 ), true ) ) {
			return self::error( 503, min( 3600, max( 10, (int) wp_remote_retrieve_header( $response, 'retry-after' ) ) ) );
		}
		$result = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $result ) || 'ready' !== ( $result['status'] ?? '' ) || ! is_string( $result['id'] ?? null ) || ! preg_match( '/^[a-f0-9]{48}$/D', $result['id'] ) || ! is_string( $result['sha256'] ?? null ) || ! preg_match( '/^[a-f0-9]{64}$/D', $result['sha256'] ) || ! is_int( $result['bytes'] ?? null ) || $result['bytes'] < 5 || $result['bytes'] > self::MAX_BYTES || ( $result['pdfUrl'] ?? '' ) !== $config['service_url'] . '/v1/files/' . $result['id'] . '.pdf' ) {
			return self::error();
		}
		$temporary = tempnam( $directory, '.pdf-' );
		if ( ! $temporary ) {
			return self::error();
		}
		chmod( $temporary, 0600 );
		try {
			$download = self::request( $result['pdfUrl'], array( 'method' => 'GET', 'timeout' => 30, 'stream' => true, 'filename' => $temporary, 'limit_response_size' => self::MAX_BYTES + 1 ) );
			clearstatcache( true, $temporary );
			if ( is_wp_error( $download ) || 200 !== wp_remote_retrieve_response_code( $download ) || 'application/pdf' !== strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $download, 'content-type' ) )[0] ) ) || filesize( $temporary ) !== $result['bytes'] || ! hash_equals( $result['sha256'], hash_file( 'sha256', $temporary ) ) || '%PDF-' !== file_get_contents( $temporary, false, null, 0, 5 ) || ! rename( $temporary, $filename ) ) {
				return self::error();
			}
			return array( 'path' => $filename, 'bytes' => $result['bytes'], 'sha256' => $result['sha256'] );
		} finally {
			if ( is_file( $temporary ) ) {
				unlink( $temporary );
			}
		}
	}
}
