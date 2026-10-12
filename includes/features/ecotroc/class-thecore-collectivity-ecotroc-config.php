<?php
/**
 * Site-local connection settings. No credentials or private responses live here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_EcoTroc_Config {
	const OPTION = 'tccm_ecotroc_connection';

	private $blog_id;
	private $base_url = '';
	private $tenant_id = '';
	private $timeout = 5;
	private $valid = false;

	/** Read the current site's option, never a network option or user metadata. */
	public function __construct() {
		$this->blog_id = (int) get_current_blog_id();
		$settings      = get_option( self::OPTION, array() );
		if ( ! is_array( $settings ) || array_diff( array_keys( $settings ), array( 'base_url', 'tenant_id', 'timeout' ) ) ) {
			return;
		}
		$url    = $settings['base_url'] ?? null;
		$tenant = $settings['tenant_id'] ?? null;
		if ( ! is_string( $url ) || ! is_string( $tenant ) || '' === $tenant || strlen( $tenant ) > 160 || preg_match( '/[\x00-\x20\x7f]/', $tenant ) ) {
			return;
		}
		$parts = parse_url( $url );
		if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			return;
		}
		$host = $parts['host'] ?? '';
		if ( ! preg_match( '/\A[a-zA-Z0-9](?:[a-zA-Z0-9.-]*[a-zA-Z0-9])?\z/', $host ) || false === strpos( $host, '.' ) || filter_var( $host, FILTER_VALIDATE_IP ) || ( isset( $parts['port'] ) && 443 !== $parts['port'] ) || '/v1' !== rtrim( $parts['path'] ?? '', '/' ) || preg_match( '/[\x00-\x20\x7f\\\\]/', $url ) ) {
			return;
		}
		$timeout = $settings['timeout'] ?? 5;
		if ( ! is_int( $timeout ) && ! is_float( $timeout ) ) {
			return;
		}
		if ( ! is_finite( (float) $timeout ) ) {
			return;
		}
		$this->base_url  = rtrim( $url, '/' );
		$this->tenant_id = $tenant;
		$this->timeout  = max( 1, min( 10, (float) $timeout ) );
		$this->valid    = true;
	}

	public function get_blog_id() { return $this->blog_id; }
	public function get_base_url() { return $this->base_url; }
	public function get_tenant_id() { return $this->tenant_id; }
	public function get_timeout() { return $this->timeout; }
	public function is_valid() { return $this->valid; }
	public function is_current_site() { return $this->blog_id === (int) get_current_blog_id(); }
}
