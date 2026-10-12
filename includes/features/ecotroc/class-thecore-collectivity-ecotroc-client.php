<?php
/**
 * Manual, request-scoped WP HTTP connector for the pinned public candidate.
 * EcoLoop owns identity, authorization, business state and command execution.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-thecore-collectivity-ecotroc-config.php';
require_once __DIR__ . '/class-thecore-collectivity-ecotroc-contract.php';

final class TheCore_Collectivity_EcoTroc_Client {
	const MAX_RESPONSE_BYTES = 262144;
	const MAX_REQUEST_BYTES  = 65536;

	private $config;
	private $context_provider;
	private $contract;
	private $version_checked = false;

	/**
	 * The provider is an explicit test/integration dependency, not a production
	 * BFF or SSO implementation. It supplies an ephemeral bearer for each call.
	 *
	 * @param TheCore_Collectivity_EcoTroc_Config $config Site-bound settings.
	 * @param callable|null $context_provider Trusted active-context dependency.
	 */
	public function __construct( TheCore_Collectivity_EcoTroc_Config $config, $context_provider = null ) {
		$this->config           = $config;
		$this->context_provider = $context_provider;
		try {
			$this->contract = new TheCore_Collectivity_EcoTroc_Contract();
		} catch ( Throwable $error ) {
			$this->contract = null;
		}
	}

	/** Read the actual version/capability strings, without fabricating names. */
	public function get_version() {
		$this->version_checked = false;
		$problem = $this->configuration_problem();
		if ( $problem ) {
			return $problem;
		}
		$result = $this->send( 'GET', '/version', null, array(), '', 'version', 200, '#/components/schemas/VersionInfo', false );
		if ( $result['ok'] ) {
			if ( TheCore_Collectivity_EcoTroc_Contract::VERSION !== $result['data']->version ) {
				return $this->failure( 'unsupported_version', 200, 'not_sent' );
			}
			$this->version_checked = true;
		}
		return $result;
	}

	/** Return an admission receipt, never an accepted Exchange. */
	public function accept_proposal( $proposal_id, $body, $key, $etag ) {
		if ( ! $this->identifier_valid( $proposal_id, '#/components/schemas/ExchangeProposalId' ) || ! $this->valid( $body, '#/components/schemas/ExchangeProposalAcceptRequest' ) || ! $this->command_headers_valid( $key, $etag, 'ProposalIfMatch', $proposal_id ) ) {
			return $this->failure( 'invalid_request', 0, 'not_sent' );
		}
		return $this->private_request( 'acceptExchangeProposal', 'POST', '/exchange-proposals/' . rawurlencode( $proposal_id ) . '/accept', $body, array( 'Idempotency-Key' => $key, 'If-Match' => $etag ), 'admission_receipt', 202, '#/components/schemas/ExchangeAcceptanceAdmission', $proposal_id );
	}

	/** Re-read current state; neither an error nor a timeout proves an outcome. */
	public function get_attempt( $attempt_id ) {
		if ( ! $this->identifier_valid( $attempt_id, '#/components/schemas/ExchangeAcceptanceAttemptId' ) ) {
			return $this->failure( 'invalid_request', 0, 'not_sent' );
		}
		return $this->private_request( 'getExchangeAcceptanceAttempt', 'GET', '/exchange-acceptance-attempts/' . rawurlencode( $attempt_id ), null, array(), 'attempt', 200, '#/components/schemas/ExchangeAcceptanceAttempt', $attempt_id );
	}

	/** Explicit discovery, never background polling of an entire collection. */
	public function list_attempts( $query = array() ) {
		if ( ! is_array( $query ) || array_diff( array_keys( $query ), array( 'rootProposalId', 'cursor', 'limit' ) ) ) {
			return $this->failure( 'invalid_request', 0, 'not_sent' );
		}
		$schemas = array( 'rootProposalId' => '#/components/schemas/ExchangeProposalId', 'cursor' => '#/components/parameters/AcceptanceCursor/schema', 'limit' => '#/components/parameters/LimitParam/schema' );
		foreach ( $query as $name => $value ) {
			if ( ! $this->valid( $value, $schemas[ $name ] ) || ( is_string( $value ) && preg_match( '/[\x00-\x1f\x7f]/', $value ) ) ) {
				return $this->failure( 'invalid_request', 0, 'not_sent' );
			}
		}
		$query['tenantId'] = $this->config->get_tenant_id();
		$path = '/exchange-acceptance-attempts?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		return $this->private_request( 'listExchangeAcceptanceAttempts', 'GET', $path, null, array(), 'attempt_collection', 200, '#/components/schemas/ExchangeAcceptanceAttemptCollection' );
	}

	/** Cooperative intake only. No production-work gate or local cancellation. */
	public function request_cancellation( $attempt_id, $key, $etag ) {
		if ( ! $this->identifier_valid( $attempt_id, '#/components/schemas/ExchangeAcceptanceAttemptId' ) || ! $this->command_headers_valid( $key, $etag, 'AcceptanceAttemptIfMatch', $attempt_id ) ) {
			return $this->failure( 'invalid_request', 0, 'not_sent' );
		}
		return $this->private_request( 'requestExchangeAcceptanceCancellation', 'POST', '/exchange-acceptance-attempts/' . rawurlencode( $attempt_id ) . '/cancellations', new stdClass(), array( 'Idempotency-Key' => $key, 'If-Match' => $etag ), 'cancellation_receipt', 202, '#/components/schemas/ExchangeAcceptanceCancellationReceipt', $attempt_id );
	}

	private function private_request( $operation, $method, $path, $body, $headers, $kind, $status, $schema, $target = null ) {
		$problem = $this->configuration_problem();
		if ( $problem ) {
			return $problem;
		}
		if ( ! is_callable( $this->context_provider ) ) {
			return $this->failure( 'context_unavailable', 0, 'not_sent' );
		}
		if ( ! $this->version_checked ) {
			$version = $this->get_version();
			if ( ! $version['ok'] ) {
				// Failure of this prerequisite says nothing about older commands.
				$version['error']['outcome']  = 'not_sent';
				$version['error']['recovery'] = 'none';
				return $version;
			}
		}
		try {
			$context = call_user_func( $this->context_provider, array( 'blog_id' => $this->config->get_blog_id(), 'tenant_id' => $this->config->get_tenant_id(), 'operation_id' => $operation, 'base_url' => $this->config->get_base_url() ) );
		} catch ( Throwable $error ) {
			return $this->failure( 'context_unavailable', 0, 'not_sent' );
		}
		if ( ! is_array( $context ) || ( $context['blog_id'] ?? null ) !== $this->config->get_blog_id() || ( $context['tenant_id'] ?? null ) !== $this->config->get_tenant_id() || ! is_string( $context['access_token'] ?? null ) || strlen( $context['access_token'] ) > 8192 || ! preg_match( '/\A[A-Za-z0-9._~+\/-]+=*\z/', $context['access_token'] ) ) {
			return $this->failure( 'context_unavailable', 0, 'not_sent' );
		}
		// A callback may switch sites; recheck before sending the bearer.
		if ( ! $this->config->is_current_site() ) {
			return $this->failure( 'site_mismatch', 0, 'not_sent' );
		}
		return $this->send( $method, $path, $body, $headers, $context['access_token'], $kind, $status, $schema, true, $target );
	}

	private function configuration_problem() {
		if ( ! $this->config->is_current_site() ) {
			return $this->failure( 'site_mismatch', 0, 'not_sent' );
		}
		if ( ! $this->config->is_valid() || ! $this->contract ) {
			return $this->failure( 'configuration_error', 0, 'not_sent' );
		}
		return null;
	}

	private function command_headers_valid( $key, $etag, $parameter, $target ) {
		return is_string( $key ) && ! preg_match( '/[\x00-\x20\x7f]/', $key ) && is_string( $etag ) && ! preg_match( '/[\x00-\x1f\x7f]/', $etag ) && $this->valid( $key, '#/components/parameters/IdempotencyKey/schema' ) && $this->valid( $etag, '#/components/parameters/' . $parameter . '/schema' ) && 0 === strpos( $etag, '"' . $target . ':' );
	}

	private function identifier_valid( $value, $schema ) {
		return is_string( $value ) && ! preg_match( '/[\x00-\x1f\x7f]/', $value ) && $this->valid( $value, $schema );
	}

	private function valid( $value, $schema ) {
		if ( ! $this->contract ) {
			return false;
		}
		try {
			return $this->contract->validate( $value, $schema );
		} catch ( Throwable $error ) {
			return false;
		}
	}

	/** Exactly one WP HTTP call; no redirects, retries, refresh or write queue. */
	private function send( $method, $path, $body, $headers, $token, $kind, $expected_status, $schema, $private, $target = null ) {
		if ( ! $this->config->is_current_site() ) {
			return $this->failure( 'site_mismatch', 0, 'not_sent' );
		}
		$headers['Accept']        = 'application/json';
		$headers['Cache-Control'] = 'no-store';
		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}
		$args = array( 'method' => $method, 'timeout' => $this->config->get_timeout(), 'redirection' => 0, 'sslverify' => true, 'reject_unsafe_urls' => true, 'blocking' => true, 'stream' => false, 'cookies' => array(), 'headers' => $headers, 'limit_response_size' => self::MAX_RESPONSE_BYTES + 1 );
		if ( 'POST' === $method ) {
			$encoded = wp_json_encode( $body );
			if ( ! is_string( $encoded ) || strlen( $encoded ) > self::MAX_REQUEST_BYTES ) {
				return $this->failure( 'invalid_request', 0, 'not_sent' );
			}
			$args['body']                    = $encoded;
			$args['headers']['Content-Type'] = 'application/json';
		}
		try {
			$response = wp_safe_remote_request( $this->config->get_base_url() . $path, $args );
		} catch ( Throwable $error ) {
			return $this->failure( 'network_error', 0, 'unknown' );
		}
		if ( is_wp_error( $response ) ) {
			return $this->failure( 'network_error', 0, 'unknown' );
		}
		// HTTP filters can switch blogs. Never return a private view in that site.
		if ( ! $this->config->is_current_site() ) {
			return $this->failure( 'site_mismatch', 0, 'unknown' );
		}
		if ( ! is_array( $response ) ) {
			return $this->failure( 'invalid_response', 0, 'unknown' );
		}
		$wire_status = wp_remote_retrieve_response_code( $response );
		if ( ! is_int( $wire_status ) && ( ! is_string( $wire_status ) || ! preg_match( '/\A[0-9]{3}\z/', $wire_status ) ) ) {
			return $this->failure( 'invalid_response', 0, 'unknown' );
		}
		$status = (int) $wire_status;
		$raw    = wp_remote_retrieve_body( $response );
		$type   = wp_remote_retrieve_header( $response, 'content-type' );
		$cache  = wp_remote_retrieve_header( $response, 'cache-control' );
		if ( $status < 100 || $status > 599 || ! is_string( $raw ) || strlen( $raw ) > self::MAX_RESPONSE_BYTES || ! is_string( $type ) || ! preg_match( '/\Aapplication\/json(?:\s*;\s*charset=(?:utf-8|"utf-8"))?\z/i', $type ) || ( $private && 'no-store' !== $cache ) ) {
			return $this->failure( 'invalid_response', $status, 'unknown' );
		}
		try {
			$data = $this->contract->decode( $raw );
		} catch ( Throwable $error ) {
			return $this->failure( 'invalid_response', $status, 'unknown' );
		}
		if ( $status !== $expected_status ) {
			return $this->remote_error( $status, $data, $response, $private, 'POST' === $method, $token );
		}
		if ( ! $this->valid( $data, $schema ) ) {
			return $this->failure( 'invalid_response', $status, 'unknown' );
		}
		$etag = null;
		if ( 'admission_receipt' === $kind || 'cancellation_receipt' === $kind ) {
			$href = '/v1/exchange-acceptance-attempts/' . $data->attemptId;
			if ( $data->href !== $href || wp_remote_retrieve_header( $response, 'location' ) !== $href || '' !== wp_remote_retrieve_header( $response, 'etag' ) || ( 'admission_receipt' === $kind && $data->proposalId !== $target ) || ( 'cancellation_receipt' === $kind && $data->attemptId !== $target ) ) {
				return $this->failure( 'invalid_response', $status, 'unknown' );
			}
		}
		if ( 'attempt' === $kind ) {
			$etag = wp_remote_retrieve_header( $response, 'etag' );
			if ( $data->id !== $target || ! is_string( $etag ) || $etag !== '"' . $data->id . ':' . $data->version . '"' ) {
				return $this->failure( 'invalid_response', $status, 'unknown' );
			}
		}
		$views = 'attempt_collection' === $kind ? $data->items : ( in_array( $kind, array( 'attempt', 'admission_receipt' ), true ) ? array( $data ) : array() );
		foreach ( $views as $view ) {
			// Enforce the public receipt invariant, without inferring a state from it.
			try {
				$admitted = new DateTimeImmutable( $view->admittedAt );
				$deadline = new DateTimeImmutable( $view->confirmationDeadline );
			} catch ( Throwable $error ) {
				return $this->failure( 'invalid_response', $status, 'unknown' );
			}
			preg_match( '/:\d{2}(?:\.([0-9]+))?(?:[zZ]|[+-][0-9]{2}:[0-9]{2})\z/', $view->admittedAt, $admitted_fraction );
			preg_match( '/:\d{2}(?:\.([0-9]+))?(?:[zZ]|[+-][0-9]{2}:[0-9]{2})\z/', $view->confirmationDeadline, $deadline_fraction );
			if ( $deadline->format( 'U' ) !== $admitted->modify( '+60 seconds' )->format( 'U' ) || rtrim( $admitted_fraction[1] ?? '', '0' ) !== rtrim( $deadline_fraction[1] ?? '', '0' ) ) {
				return $this->failure( 'invalid_response', $status, 'unknown' );
			}
		}
		return array( 'ok' => true, 'kind' => $kind, 'http_status' => $status, 'data' => $data, 'etag' => $etag, 'error' => null );
	}

	/** Discard messages/details; only a validated command-owner 503 proves refusal. */
	private function remote_error( $status, $data, $response, $private, $mutation, $token ) {
		if ( ! $private || $status < 400 ) {
			return $this->failure( 'invalid_response', $status, 'unknown' );
		}
		$schema = $mutation && 503 === $status ? '#/components/responses/CommandSecurityUnavailableResponse/content/application~1json/schema' : '#/components/schemas/Error';
		if ( ! $this->valid( $data, $schema ) ) {
			return $this->failure( 'invalid_response', $status, 'unknown' );
		}
		$retry_after = null;
		if ( 429 === $status ) {
			$retry = wp_remote_retrieve_header( $response, 'retry-after' );
			if ( ! is_string( $retry ) || ! preg_match( '/\A[0-9]{1,9}\z/', $retry ) || (int) $retry < 1 ) {
				return $this->failure( 'invalid_response', $status, 'unknown' );
			}
			$retry_after = (int) $retry;
		}
		$certain = $mutation && 503 === $status && 'COMMAND_REJECTED_SECURITY_UNAVAILABLE' === $data->code;
		$result  = $this->failure( 'remote_error', $status, $certain ? 'not_accepted' : 'unknown' );
		$result['error']['remote_code'] = preg_match( '/\A[A-Z][A-Z0-9_]{0,79}\z/', $data->code ) && false === strpos( $data->code, $token ) ? $data->code : null;
		$result['error']['trace_id']    = preg_match( '/\A[A-Za-z0-9_.:-]{1,128}\z/', $data->traceId ) && false === strpos( $data->traceId, $token ) ? $data->traceId : null;
		$result['error']['retry_after'] = $retry_after;
		return $result;
	}

	private function failure( $code, $status, $outcome ) {
		return array( 'ok' => false, 'kind' => 'error', 'http_status' => $status, 'data' => null, 'etag' => null, 'error' => array( 'code' => $code, 'remote_code' => null, 'trace_id' => null, 'outcome' => $outcome, 'recovery' => 'not_accepted' === $outcome ? 'user_resubmit' : ( 'unknown' === $outcome ? 'reconcile' : 'none' ), 'retry_after' => null ) );
	}
}
