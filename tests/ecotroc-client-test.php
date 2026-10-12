<?php
/**
 * Exercise the real EcoTroc PHP client against a simulated WordPress HTTP API.
 * No WordPress bootstrap, backend, database or outbound network is used.
 */

ini_set( 'log_errors', '0' );
ini_set( 'display_errors', 'stderr' );
define( 'ABSPATH', dirname( __DIR__ ) . '/' );

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code = $code;
		$this->message = $message;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function get_current_blog_id() { return $GLOBALS['ecotroc_test_blog']; }
function get_option( $name, $default = false ) {
	return $GLOBALS['ecotroc_test_options'][ get_current_blog_id() ][ $name ] ?? $default;
}
function wp_json_encode( $value, $flags = 0, $depth = 512 ) { return json_encode( $value, $flags, $depth ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function ecotroc_forbidden_side_effect( $name ) {
	$GLOBALS['ecotroc_test_side_effects'][] = $name;
	throw new RuntimeException( 'Forbidden persistence or WordPress authorization: ' . $name );
}
function update_option( ...$args ) { return ecotroc_forbidden_side_effect( __FUNCTION__ ); }
function add_option( ...$args ) { return ecotroc_forbidden_side_effect( __FUNCTION__ ); }
function delete_option( ...$args ) { return ecotroc_forbidden_side_effect( __FUNCTION__ ); }
function set_transient( ...$args ) { return ecotroc_forbidden_side_effect( __FUNCTION__ ); }
function get_transient( ...$args ) { return ecotroc_forbidden_side_effect( __FUNCTION__ ); }
function delete_transient( ...$args ) { return ecotroc_forbidden_side_effect( __FUNCTION__ ); }
function wp_cache_get( ...$args ) { return ecotroc_forbidden_side_effect( __FUNCTION__ ); }
function wp_cache_set( ...$args ) { return ecotroc_forbidden_side_effect( __FUNCTION__ ); }
function update_user_meta( ...$args ) { return ecotroc_forbidden_side_effect( __FUNCTION__ ); }
function update_post_meta( ...$args ) { return ecotroc_forbidden_side_effect( __FUNCTION__ ); }
function current_user_can( ...$args ) { return ecotroc_forbidden_side_effect( __FUNCTION__ ); }

function wp_safe_remote_request( $url, $args = array() ) {
	$GLOBALS['ecotroc_test_calls'][] = array( 'url' => $url, 'args' => $args );
	if ( ! $GLOBALS['ecotroc_test_responses'] ) {
		$GLOBALS['ecotroc_test_unexpected_http'][] = $url;
		throw new RuntimeException( 'Unscripted HTTP request.' );
	}
	$response = array_shift( $GLOBALS['ecotroc_test_responses'] );
	if ( $response instanceof Throwable ) {
		throw $response;
	}
	return is_callable( $response ) ? $response( $url, $args ) : $response;
}
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code'] ?? ''; }
function wp_remote_retrieve_body( $response ) { return $response['body'] ?? ''; }
function wp_remote_retrieve_header( $response, $name ) {
	foreach ( $response['headers'] ?? array() as $key => $value ) {
		if ( strtolower( $key ) === strtolower( $name ) ) {
			return $value;
		}
	}
	return '';
}

$GLOBALS['ecotroc_test_assertions'] = 0;
function ecotroc_check( $condition, $message ) {
	++$GLOBALS['ecotroc_test_assertions'];
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}
function ecotroc_equal( $expected, $actual, $message ) {
	ecotroc_check( $expected === $actual, $message . ' Expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
}
function ecotroc_reset() {
	$GLOBALS['ecotroc_test_blog'] = 1;
	$GLOBALS['ecotroc_test_options'] = array(
		1 => array( 'tccm_ecotroc_connection' => array( 'base_url' => 'https://api.example.test/v1', 'tenant_id' => 'ten_a', 'timeout' => 5 ) ),
		2 => array( 'tccm_ecotroc_connection' => array( 'base_url' => 'https://other-api.example.test/v1', 'tenant_id' => 'ten_b', 'timeout' => 7 ) ),
	);
	$GLOBALS['ecotroc_test_calls'] = array();
	$GLOBALS['ecotroc_test_responses'] = array();
	$GLOBALS['ecotroc_test_provider_calls'] = array();
	$GLOBALS['ecotroc_test_side_effects'] = array();
	$GLOBALS['ecotroc_test_unexpected_http'] = array();
}
function ecotroc_response( $status, $data, $headers = array(), $raw = false ) {
	return array(
		'response' => array( 'code' => $status ),
		'headers' => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
		'body' => $raw ? $data : json_encode( $data, JSON_UNESCAPED_SLASHES ),
	);
}
function ecotroc_queue( $response ) { $GLOBALS['ecotroc_test_responses'][] = $response; }
function ecotroc_provider( $context ) {
	$GLOBALS['ecotroc_test_provider_calls'][] = $context;
	return array( 'blog_id' => $context['blog_id'], 'tenant_id' => $context['tenant_id'], 'access_token' => 'test-token-' . $context['tenant_id'] );
}
function ecotroc_version_data( $version = '1.0.0-draft.5', $capabilities = array() ) {
	return (object) array( 'name' => 'EcoLoop API', 'version' => $version, 'capabilities' => $capabilities );
}
function ecotroc_result_shape( $result ) {
	ecotroc_check( is_array( $result ), 'Every call returns a result array.' );
	$keys = array_keys( $result );
	sort( $keys );
	ecotroc_equal( array( 'data', 'error', 'etag', 'http_status', 'kind', 'ok' ), $keys, 'Stable result envelope.' );
	ecotroc_check( is_bool( $result['ok'] ) && is_int( $result['http_status'] ), 'Result status types.' );
	ecotroc_check( null === $result['data'] || $result['data'] instanceof stdClass, 'Private DTOs retain JSON object identity.' );
	ecotroc_check( null === $result['etag'] || is_string( $result['etag'] ), 'ETag type.' );
	if ( null !== $result['error'] ) {
		$error_keys = array_keys( $result['error'] );
		sort( $error_keys );
		ecotroc_equal( array( 'code', 'outcome', 'recovery', 'remote_code', 'retry_after', 'trace_id' ), $error_keys, 'Stable error envelope.' );
	}
}
function ecotroc_error( $result, $code, $outcome = 'unknown', $recovery = 'reconcile' ) {
	ecotroc_result_shape( $result );
	ecotroc_equal( false, $result['ok'], 'Failure is explicit.' );
	ecotroc_equal( 'error', $result['kind'], 'Failure kind.' );
	ecotroc_equal( null, $result['data'], 'Failure does not expose a private or invalid payload.' );
	ecotroc_equal( $code, $result['error']['code'], 'Failure code.' );
	ecotroc_equal( $outcome, $result['error']['outcome'], 'Failure preserves execution uncertainty.' );
	ecotroc_equal( $recovery, $result['error']['recovery'], 'Failure recovery requires the specified next action.' );
}
function ecotroc_idle() {
	ecotroc_equal( array(), $GLOBALS['ecotroc_test_responses'], 'Only explicitly requested HTTP exchanges are performed.' );
	ecotroc_equal( array(), $GLOBALS['ecotroc_test_unexpected_http'], 'No implicit retries, polling or backend calls.' );
	ecotroc_equal( array(), $GLOBALS['ecotroc_test_side_effects'], 'No WordPress persistence, shared cache or inferred permissions.' );
}
function ecotroc_ready( $provider = 'ecotroc_provider' ) {
	ecotroc_reset();
	$client = new TheCore_Collectivity_EcoTroc_Client( new TheCore_Collectivity_EcoTroc_Config(), $provider );
	ecotroc_equal( 0, count( $GLOBALS['ecotroc_test_calls'] ), 'Constructing the adapter never calls the backend.' );
	ecotroc_queue( ecotroc_response( 200, ecotroc_version_data() ) );
	$result = $client->get_version();
	ecotroc_result_shape( $result );
	ecotroc_equal( true, $result['ok'], 'Exact public draft version is supported.' );
	ecotroc_equal( 'version', $result['kind'], 'Version response kind.' );
	ecotroc_equal( array(), $GLOBALS['ecotroc_test_provider_calls'], 'Public version does not request actor credentials.' );
	ecotroc_equal( 'https://api.example.test/v1/version', $GLOBALS['ecotroc_test_calls'][0]['url'], 'Public version destination.' );
	ecotroc_check( ! isset( $GLOBALS['ecotroc_test_calls'][0]['args']['headers']['Authorization'] ), 'Public version remains anonymous.' );
	return $client;
}
function ecotroc_last_call() { return $GLOBALS['ecotroc_test_calls'][ count( $GLOBALS['ecotroc_test_calls'] ) - 1 ]; }
function ecotroc_header( $call, $name ) {
	foreach ( $call['args']['headers'] ?? array() as $key => $value ) {
		if ( strtolower( $key ) === strtolower( $name ) ) { return $value; }
	}
	return null;
}
function ecotroc_private_call( $operation, $method, $path ) {
	$call = ecotroc_last_call();
	ecotroc_equal( 'https://api.example.test/v1' . $path, $call['url'], 'Fixed configured API destination.' );
	ecotroc_equal( $method, $call['args']['method'], 'HTTP method for ' . $operation );
	ecotroc_equal( 'Bearer test-token-ten_a', ecotroc_header( $call, 'Authorization' ), 'Actor token is supplied per request.' );
	$expected_context = array( 'blog_id' => 1, 'tenant_id' => 'ten_a', 'base_url' => 'https://api.example.test/v1', 'operation_id' => $operation );
	$actual_context = end( $GLOBALS['ecotroc_test_provider_calls'] );
	ksort( $expected_context );
	ksort( $actual_context );
	ecotroc_equal( $expected_context, $actual_context, 'Credential provider receives explicit site, tenant, API audience and operation.' );
	ecotroc_equal( 0, $call['args']['redirection'], 'Redirects cannot move credentials to another destination.' );
	ecotroc_equal( true, $call['args']['sslverify'], 'TLS verification is mandatory.' );
	ecotroc_equal( true, $call['args']['reject_unsafe_urls'], 'WordPress must reject unsafe network destinations.' );
	ecotroc_equal( array(), $call['args']['cookies'], 'WordPress cookies never travel to the EcoLoop API.' );
	ecotroc_equal( false, $call['args']['stream'], 'Private response bodies are never streamed to disk.' );
	ecotroc_equal( 5.0, (float) $call['args']['timeout'], 'Bounded configured request timeout.' );
	ecotroc_equal( 'application/json', ecotroc_header( $call, 'Accept' ), 'Request the pinned JSON representation.' );
	if ( 'POST' === $method ) {
		ecotroc_equal( 'application/json', ecotroc_header( $call, 'Content-Type' ), 'JSON command media type.' );
	}
	return $call;
}
function ecotroc_view_headers( $value ) {
	return array( 'Cache-Control' => 'no-store', 'ETag' => '"' . $value->id . ':' . $value->version . '"' );
}

ecotroc_reset();
require dirname( __DIR__ ) . '/includes/features/ecotroc/class-thecore-collectivity-ecotroc-client.php';
$fixture_path = dirname( __DIR__ ) . '/includes/features/ecotroc/contract/fixtures.json';
$fixtures = json_decode( file_get_contents( $fixture_path ) );
ecotroc_equal( JSON_ERROR_NONE, json_last_error(), 'Pinned fixture package parses.' );
ecotroc_equal( 54, count( $fixtures->cases ), 'Exercise every public package case.' );
ecotroc_equal( 4, count( $fixtures->traces ), 'Exercise every public package trace.' );
$cases = array();
foreach ( $fixtures->cases as $case ) { $cases[ $case->id ] = $case; }

/* Every exported case reaches a real client method through the HTTP boundary. */
foreach ( $fixtures->cases as $case ) {
	$client = ecotroc_ready();
	$value = $case->value;
	if ( 0 === strpos( $case->id, 'acceptance/admission' ) ) {
		ecotroc_queue( ecotroc_response( 202, $value, array( 'Cache-Control' => 'no-store', 'Location' => $value->href ) ) );
		$result = $client->accept_proposal( 'exp_flow-child', new stdClass(), 'admission-key-0001', '"exp_flow-child:3"' );
		$kind = 'admission_receipt';
		$call = ecotroc_private_call( 'acceptExchangeProposal', 'POST', '/exchange-proposals/exp_flow-child/accept' );
		ecotroc_equal( '{}', $call['args']['body'], 'Admission sends an object, never an empty array.' );
	} elseif ( 0 === strpos( $case->id, 'acceptance/cancellation' ) ) {
		ecotroc_queue( ecotroc_response( 202, $value, array( 'Cache-Control' => 'no-store', 'Location' => $value->href ) ) );
		$result = $client->request_cancellation( 'eat_flow', 'cancellation-key-0001', '"eat_flow:1"' );
		$kind = 'cancellation_receipt';
		$call = ecotroc_private_call( 'requestExchangeAcceptanceCancellation', 'POST', '/exchange-acceptance-attempts/eat_flow/cancellations' );
		ecotroc_equal( '{}', $call['args']['body'], 'Cancellation body is exactly {}.' );
	} elseif ( 0 === strpos( $case->id, 'acceptance/view/' ) ) {
		ecotroc_queue( ecotroc_response( 200, $value, ecotroc_view_headers( $value ) ) );
		$result = $client->get_attempt( 'eat_flow' );
		$kind = 'attempt';
		ecotroc_private_call( 'getExchangeAcceptanceAttempt', 'GET', '/exchange-acceptance-attempts/eat_flow' );
	} else {
		$is_cache_case = is_string( $value );
		$error_value = $is_cache_case ? $cases['security/qualified-public']->value : $value;
		$status = in_array( $case->id, array( 'security/ordinary-policy-error', 'security/reserved-code-not-generic' ), true ) ? 403 : 503;
		ecotroc_queue( ecotroc_response( $status, $error_value, array( 'Cache-Control' => $is_cache_case ? $value : 'no-store' ) ) );
		$result = $client->accept_proposal( 'exp_flow-child', new stdClass(), 'admission-key-0001', '"exp_flow-child:3"' );
		ecotroc_private_call( 'acceptExchangeProposal', 'POST', '/exchange-proposals/exp_flow-child/accept' );
		if ( $case->valid ) {
			$qualified = 'COMMAND_REJECTED_SECURITY_UNAVAILABLE' === $error_value->code;
			ecotroc_error( $result, 'remote_error', $qualified ? 'not_accepted' : 'unknown', $qualified ? 'user_resubmit' : 'reconcile' );
			ecotroc_equal( $error_value->code, $result['error']['remote_code'], 'Validated remote error code: ' . $case->id );
			ecotroc_equal( $error_value->traceId, $result['error']['trace_id'], 'Validated trace ID: ' . $case->id );
		} else {
			ecotroc_error( $result, 'invalid_response' );
		}
		ecotroc_equal( 2, count( $GLOBALS['ecotroc_test_calls'] ), 'One private request per fixture: ' . $case->id );
		ecotroc_idle();
		continue;
	}
	if ( $case->valid ) {
		ecotroc_result_shape( $result );
		ecotroc_equal( true, $result['ok'], 'Valid fixture succeeds: ' . $case->id );
		ecotroc_equal( $kind, $result['kind'], 'DTO kind: ' . $case->id );
		ecotroc_equal( json_encode( $value ), json_encode( $result['data'] ), 'Preserve public DTO without invented outcomes: ' . $case->id );
		if ( 'attempt' === $kind ) {
			ecotroc_equal( ecotroc_view_headers( $value )['ETag'], $result['etag'], 'Attempt ETag retained.' );
		} else {
			ecotroc_equal( null, $result['etag'], 'Historical receipt is not a current state ETag.' );
		}
	} else {
		ecotroc_error( $result, 'invalid_response' );
	}
	ecotroc_equal( 2, count( $GLOBALS['ecotroc_test_calls'] ), 'One private request per fixture: ' . $case->id );
	ecotroc_idle();
}

/* Replay the four published traces, using the same intent, key and original ETag. */
foreach ( $fixtures->traces as $trace ) {
	$client = ecotroc_ready();
	$receipt = $cases[ $trace->replayedAdmission ]->value;
	$headers = array( 'Cache-Control' => 'no-store', 'Location' => $receipt->href );
	if ( 'accepted_after_lost_admission_response' === $trace->name ) {
		ecotroc_queue( new WP_Error( 'http_request_failed', 'Response lost after admission.' ) );
		ecotroc_error( $client->accept_proposal( 'exp_flow-child', new stdClass(), 'trace-admission-key', '"exp_flow-child:3"' ), 'network_error' );
		ecotroc_equal( 2, count( $GLOBALS['ecotroc_test_calls'] ), 'Lost admission is never automatically retried.' );
	}
	ecotroc_queue( ecotroc_response( 202, $receipt, $headers ) );
	$admission = $client->accept_proposal( 'exp_flow-child', new stdClass(), 'trace-admission-key', '"exp_flow-child:3"' );
	ecotroc_equal( 'admission_receipt', $admission['kind'], '202 is durable admission only.' );
	$cancelled = false;
	foreach ( $trace->views as $view_id ) {
		if ( 'acceptance/view/cancellation_requested' === $view_id && isset( $trace->replayedCancellation ) ) {
			$cancellation = $cases[ $trace->replayedCancellation ]->value;
			$cancellation_headers = array( 'Cache-Control' => 'no-store', 'Location' => $cancellation->href );
			ecotroc_queue( ecotroc_response( 202, $cancellation, $cancellation_headers ) );
			$result = $client->request_cancellation( 'eat_flow', 'trace-cancellation-key', '"eat_flow:1"' );
			ecotroc_equal( 'cancellation_receipt', $result['kind'], 'Cancellation request does not prove stopped or resource release.' );
			$cancelled = true;
		}
		$view = $cases[ $view_id ]->value;
		$before = count( $GLOBALS['ecotroc_test_calls'] );
		ecotroc_queue( ecotroc_response( 200, $view, ecotroc_view_headers( $view ) ) );
		$result = $client->get_attempt( 'eat_flow' );
		ecotroc_equal( true, $result['ok'], 'Trace view validates: ' . $trace->name . '/' . $view_id );
		ecotroc_equal( json_encode( $view ), json_encode( $result['data'] ), 'Public authoritative decision and resolution remain distinct.' );
		ecotroc_equal( $before + 1, count( $GLOBALS['ecotroc_test_calls'] ), 'Only explicit GET, even after deadline or pending resolution.' );
	}
	ecotroc_queue( ecotroc_response( 202, $receipt, $headers ) );
	$replay = $client->accept_proposal( 'exp_flow-child', new stdClass(), 'trace-admission-key', '"exp_flow-child:3"' );
	ecotroc_equal( json_encode( $admission['data'] ), json_encode( $replay['data'] ), 'Admission replay remains its original immutable receipt after later decisions.' );
	if ( $cancelled ) {
		ecotroc_queue( ecotroc_response( 202, $cancellation, $cancellation_headers ) );
		$replay = $client->request_cancellation( 'eat_flow', 'trace-cancellation-key', '"eat_flow:1"' );
		ecotroc_equal( json_encode( $cancellation ), json_encode( $replay['data'] ), 'Cancellation replay remains historical, including when acceptance won.' );
	}
	foreach ( array( 'accept' => 'trace-admission-key', 'cancellations' => 'trace-cancellation-key' ) as $suffix => $key ) {
		$requests = array_values( array_filter( $GLOBALS['ecotroc_test_calls'], static function ( $call ) use ( $suffix ) { return substr( $call['url'], -strlen( '/' . $suffix ) ) === '/' . $suffix; } ) );
		foreach ( $requests as $request ) {
			ecotroc_equal( $key, ecotroc_header( $request, 'Idempotency-Key' ), 'Caller idempotency key is preserved on every replay.' );
			ecotroc_equal( 'accept' === $suffix ? '"exp_flow-child:3"' : '"eat_flow:1"', ecotroc_header( $request, 'If-Match' ), 'Caller precondition is preserved on replay.' );
			ecotroc_equal( '{}', $request['args']['body'], 'Replay preserves the exact command intent.' );
		}
	}
	ecotroc_idle();
}

/* Transport losses and malformed bodies never become business refusals. */
foreach ( array( new WP_Error( 'timeout', 'test-secret-token should stay private' ), new RuntimeException( 'test-secret-token should stay private' ) ) as $failure ) {
	$client = ecotroc_ready();
	ecotroc_queue( $failure );
	$result = $client->accept_proposal( 'exp_flow-child', new stdClass(), 'network-key-0001', '"exp_flow-child:3"' );
	ecotroc_error( $result, 'network_error' );
	ecotroc_check( false === strpos( json_encode( $result ), 'test-secret-token' ), 'Transport diagnostics do not leak credentials or private text.' );
	ecotroc_equal( 2, count( $GLOBALS['ecotroc_test_calls'] ), 'Network failures have no automatic retry.' );
	ecotroc_idle();
}
$qualified = $cases['security/qualified-public']->value;
$duplicate_error = '{"code":"SERVICE_UNAVAILABLE","code":"COMMAND_REJECTED_SECURITY_UNAVAILABLE","message":"Unavailable","traceId":"4bf92f3577b34da6a3ce929d0e0e4736","details":[{"executionOutcome":"not_accepted","recoveryAction":"user_resubmit"}]}';
foreach ( array( '', '{', '<html>gateway error</html>', 'null', 'false', '42', '"text"', '[]', '[' . json_encode( $qualified ) . ']', $duplicate_error ) as $body ) {
	$client = ecotroc_ready();
	ecotroc_queue( ecotroc_response( 503, $body, array( 'Cache-Control' => 'no-store' ), true ) );
	ecotroc_error( $client->accept_proposal( 'exp_flow-child', new stdClass(), 'malformed-key-0001', '"exp_flow-child:3"' ), 'invalid_response' );
	ecotroc_idle();
}

/* Status, no-store, Location and strong ETag are part of the public contract. */
$receipt = $cases['acceptance/admission']->value;
foreach ( array(
	array( 201, array( 'Cache-Control' => 'no-store', 'Location' => $receipt->href ) ),
	array( 200, array( 'Cache-Control' => 'no-store', 'Location' => $receipt->href ) ),
	array( 202, array( 'Location' => $receipt->href ) ),
	array( 202, array( 'Cache-Control' => 'public, max-age=60', 'Location' => $receipt->href ) ),
	array( 202, array( 'Cache-Control' => 'no-store' ) ),
	array( 202, array( 'Cache-Control' => 'no-store', 'Location' => 'https://cell.example.test' . $receipt->href ) ),
	array( 202, array( 'Cache-Control' => 'no-store', 'Location' => '/v1/exchange-acceptance-attempts/eat_other' ) ),
	array( 202, array( 'Cache-Control' => 'no-store', 'Location' => array( $receipt->href, $receipt->href ) ) ),
) as $case ) {
	$client = ecotroc_ready();
	ecotroc_queue( ecotroc_response( $case[0], $receipt, $case[1] ) );
	ecotroc_error( $client->accept_proposal( 'exp_flow-child', new stdClass(), 'header-key-0001', '"exp_flow-child:3"' ), 'invalid_response' );
	ecotroc_idle();
}
$view = $cases['acceptance/view/confirming']->value;
foreach ( array( null, 'W/"eat_flow:1"', '"exp_flow-child:1"', '"eat_flow:2"', '*', '"eat_flow:1", "eat_flow:2"', array( '"eat_flow:1"', '"eat_flow:1"' ) ) as $etag ) {
	$client = ecotroc_ready();
	$headers = array( 'Cache-Control' => 'no-store' );
	if ( null !== $etag ) { $headers['ETag'] = $etag; }
	ecotroc_queue( ecotroc_response( 200, $view, $headers ) );
	ecotroc_error( $client->get_attempt( 'eat_flow' ), 'invalid_response' );
	ecotroc_idle();
}
foreach ( array( 301, 302, 304, 500, 502 ) as $status ) {
	$client = ecotroc_ready();
	ecotroc_queue( ecotroc_response( $status, '<html>upstream</html>', array( 'Location' => 'https://evil.example.test', 'Cache-Control' => 'no-store' ), true ) );
	ecotroc_error( $client->get_attempt( 'eat_flow' ), 'invalid_response' );
	ecotroc_equal( 2, count( $GLOBALS['ecotroc_test_calls'] ), 'Unexpected status never follows redirects or polls.' );
	ecotroc_idle();
}
$client = ecotroc_ready();
ecotroc_queue( ecotroc_response( '200foo', $view, ecotroc_view_headers( $view ) ) );
ecotroc_error( $client->get_attempt( 'eat_flow' ), 'invalid_response' );
ecotroc_idle();
$client = ecotroc_ready();
ecotroc_queue( ecotroc_response( 200, $view, array( 'cAcHe-CoNtRoL' => 'no-store', 'eTaG' => '"eat_flow:1"' ) ) );
ecotroc_equal( true, $client->get_attempt( 'eat_flow' )['ok'], 'HTTP header names are case-insensitive.' );
ecotroc_idle();
$client = ecotroc_ready();
ecotroc_queue( ecotroc_response( 200, $view, array_merge( ecotroc_view_headers( $view ), array( 'Content-Type' => 'application/json; charset="utf-8"' ) ) ) );
ecotroc_equal( true, $client->get_attempt( 'eat_flow' )['ok'], 'A quoted UTF-8 charset parameter is a legal JSON media type.' );
ecotroc_idle();
$client = ecotroc_ready();
$duplicate_view = str_replace( '"version":1', '"version":2,"version":1', json_encode( $view ) );
ecotroc_queue( ecotroc_response( 200, $duplicate_view, ecotroc_view_headers( $view ), true ) );
ecotroc_error( $client->get_attempt( 'eat_flow' ), 'invalid_response' );
ecotroc_idle();
/* The list is an explicit discovery request, never a polling substitute. */
$client = ecotroc_ready();
$collection = (object) array( 'items' => array( $view ), 'page' => (object) array( 'hasMore' => false, 'limit' => 20 ) );
ecotroc_queue( ecotroc_response( 200, $collection, array( 'Cache-Control' => 'no-store' ) ) );
$result = $client->list_attempts( array( 'rootProposalId' => 'exp_flow-root', 'cursor' => 'opaque+/= cursor', 'limit' => 20 ) );
ecotroc_equal( true, $result['ok'], 'Authorized attempt collection validates.' );
ecotroc_equal( 'attempt_collection', $result['kind'], 'Collection has its own result kind.' );
$call = ecotroc_last_call();
parse_str( parse_url( $call['url'], PHP_URL_QUERY ), $query );
ecotroc_equal( 'https://api.example.test/v1/exchange-acceptance-attempts', strtok( $call['url'], '?' ), 'Discovery stays on the configured endpoint.' );
ecotroc_equal( 'ten_a', $query['tenantId'], 'Discovery binds configured tenant explicitly.' );
ecotroc_equal( 'opaque+/= cursor', $query['cursor'], 'Cursor is opaque and encoded exactly once.' );
ecotroc_equal( 'exp_flow-root', $query['rootProposalId'], 'Root filter preserved.' );
ecotroc_equal( '20', $query['limit'], 'Bounded list limit preserved.' );
ecotroc_idle();
$client = ecotroc_ready();
$foreign_view = clone $view;
$foreign_view->tenantId = 'ten_b';
ecotroc_queue( ecotroc_response( 200, $foreign_view, ecotroc_view_headers( $foreign_view ) ) );
$federated_result = $client->get_attempt( 'eat_flow' );
ecotroc_equal( true, $federated_result['ok'], 'The backend may authorize a participant from another tenant.' );
ecotroc_equal( json_encode( $foreign_view ), json_encode( $federated_result['data'] ), 'DTO tenant identifies its coordinator; the adapter does not reinterpret it as actor authorization.' );
ecotroc_private_call( 'getExchangeAcceptanceAttempt', 'GET', '/exchange-acceptance-attempts/eat_flow' );
ecotroc_idle();
$client = ecotroc_ready();
$foreign_collection = (object) array( 'items' => array( $foreign_view ), 'page' => (object) array( 'hasMore' => false, 'limit' => 20 ) );
ecotroc_queue( ecotroc_response( 200, $foreign_collection, array( 'Cache-Control' => 'no-store' ) ) );
$federated_result = $client->list_attempts();
ecotroc_equal( true, $federated_result['ok'], 'Authorized federated discovery preserves coordinator tenants.' );
ecotroc_equal( json_encode( $foreign_collection ), json_encode( $federated_result['data'] ), 'Discovery does not add a local business authorization rule.' );
parse_str( parse_url( ecotroc_last_call()['url'], PHP_URL_QUERY ), $query );
ecotroc_equal( 'ten_a', $query['tenantId'], 'Federated discovery still binds the configured actor context.' );
ecotroc_idle();

/* Caller errors must stop before credentials or HTTP are requested. */
foreach ( array(
	static function ( $c ) { return $c->accept_proposal( 'exp_flow-child', new stdClass(), '', '"exp_flow-child:3"' ); },
	static function ( $c ) { return $c->accept_proposal( 'exp_flow-child', new stdClass(), 'valid-key-0001', 'W/"exp_flow-child:3"' ); },
	static function ( $c ) { return $c->accept_proposal( 'exp_flow-child', new stdClass(), 'valid-key-0001', "\"exp_flow-child:3\"\n" ); },
	static function ( $c ) { return $c->accept_proposal( 'exp_flow-child', (object) array( 'accountId' => 'usr_private' ), 'valid-key-0001', '"exp_flow-child:3"' ); },
	static function ( $c ) { return $c->accept_proposal( 'https://evil.example.test', new stdClass(), 'valid-key-0001', '"exp_flow-child:3"' ); },
	static function ( $c ) { return $c->get_attempt( '../eat_other' ); },
	static function ( $c ) { return $c->get_attempt( "eat_flow\n" ); },
	static function ( $c ) { return $c->request_cancellation( 'eat_flow', 'valid-key-0001', '"exp_flow-child:3"' ); },
	static function ( $c ) { return $c->request_cancellation( 'eat_flow', "bad-key\r\nAuthorization: injected", '"eat_flow:1"' ); },
	static function ( $c ) { return $c->list_attempts( array( 'tenantId' => 'ten_b' ) ); },
	static function ( $c ) { return $c->list_attempts( array( 'limit' => 101 ) ); },
	static function ( $c ) { return $c->list_attempts( array( 'unexpected' => 'field' ) ); },
) as $invoke ) {
	$client = ecotroc_ready();
	ecotroc_error( $invoke( $client ), 'invalid_request', 'not_sent', 'none' );
	ecotroc_equal( 1, count( $GLOBALS['ecotroc_test_calls'] ), 'Invalid request is not sent.' );
	ecotroc_equal( array(), $GLOBALS['ecotroc_test_provider_calls'], 'Invalid request does not request actor tokens.' );
	ecotroc_idle();
}

/* Public version gate pins the contract without inventing capability labels. */
foreach ( array( '1.0.0-draft.4', '1.0.0-draft.6', '1.0.0', '2.0.0' ) as $version ) {
	ecotroc_reset();
	$client = new TheCore_Collectivity_EcoTroc_Client( new TheCore_Collectivity_EcoTroc_Config(), 'ecotroc_provider' );
	ecotroc_queue( ecotroc_response( 200, ecotroc_version_data( $version ) ) );
	ecotroc_error( $client->get_attempt( 'eat_flow' ), 'unsupported_version', 'not_sent', 'none' );
	ecotroc_equal( 1, count( $GLOBALS['ecotroc_test_calls'] ), 'Unsupported version only performs the anonymous gate request.' );
	ecotroc_check( null === ecotroc_header( ecotroc_last_call(), 'Authorization' ), 'Unsupported version sends no actor credentials over HTTP.' );
	ecotroc_equal( array(), $GLOBALS['ecotroc_test_provider_calls'], 'The public version gate runs before requesting actor credentials.' );
	ecotroc_idle();
}
foreach ( array(
	array( new WP_Error( 'timeout', 'Anonymous version unavailable.' ), 'network_error' ),
	array( new RuntimeException( 'Anonymous version transport failed.' ), 'network_error' ),
	array( ecotroc_response( 200, '{', array(), true ), 'invalid_response' ),
	array( ecotroc_response( 200, (object) array( 'name' => 'Wrong API', 'version' => '1.0.0-draft.5', 'capabilities' => array() ) ), 'invalid_response' ),
) as $case ) {
	ecotroc_reset();
	$client = new TheCore_Collectivity_EcoTroc_Client( new TheCore_Collectivity_EcoTroc_Config(), 'ecotroc_provider' );
	ecotroc_queue( $case[0] );
	ecotroc_error( $client->accept_proposal( 'exp_flow-child', new stdClass(), 'version-loss-key', '"exp_flow-child:3"' ), $case[1], 'not_sent', 'none' );
	ecotroc_equal( 1, count( $GLOBALS['ecotroc_test_calls'] ), 'A failed prerequisite performs no command or retry.' );
	ecotroc_equal( 'https://api.example.test/v1/version', ecotroc_last_call()['url'], 'Only the prerequisite GET was sent.' );
	ecotroc_equal( array(), $GLOBALS['ecotroc_test_provider_calls'], 'A failed version prerequisite requests no actor token.' );
	ecotroc_idle();
}
ecotroc_reset();
$client = new TheCore_Collectivity_EcoTroc_Client( new TheCore_Collectivity_EcoTroc_Config(), 'ecotroc_provider' );
ecotroc_queue( ecotroc_response( 200, ecotroc_version_data( '1.0.0-draft.5', array( 'unrelated-future-capability' ) ) ) );
ecotroc_queue( ecotroc_response( 200, $view, ecotroc_view_headers( $view ) ) );
ecotroc_equal( true, $client->get_attempt( 'eat_flow' )['ok'], 'Exact pin works without an invented capabilities mapping.' );
ecotroc_queue( ecotroc_response( 200, $view, ecotroc_view_headers( $view ) ) );
ecotroc_equal( true, $client->get_attempt( 'eat_flow' )['ok'], 'Version gate lives only in this request-local client memory.' );
ecotroc_equal( 3, count( $GLOBALS['ecotroc_test_calls'] ), 'One anonymous version gate and two explicit private reads.' );
ecotroc_equal( 2, count( $GLOBALS['ecotroc_test_provider_calls'] ), 'Actor credentials are requested again for every private call.' );
ecotroc_idle();
$client = ecotroc_ready();
$GLOBALS['ecotroc_test_options'][1]['tccm_ecotroc_connection']['base_url'] = 'https://changed.example.test/v1';
$GLOBALS['ecotroc_test_options'][1]['tccm_ecotroc_connection']['tenant_id'] = 'ten_changed';
ecotroc_queue( ecotroc_response( 200, $view, ecotroc_view_headers( $view ) ) );
ecotroc_equal( true, $client->get_attempt( 'eat_flow' )['ok'], 'Existing config binds immutable destination and tenant for this client.' );
ecotroc_private_call( 'getExchangeAcceptanceAttempt', 'GET', '/exchange-acceptance-attempts/eat_flow' );
ecotroc_idle();
$client = ecotroc_ready();
ecotroc_queue( static function ( $url, $args ) use ( $view ) {
	$GLOBALS['ecotroc_test_blog'] = 2;
	return ecotroc_response( 200, $view, ecotroc_view_headers( $view ) );
} );
ecotroc_error( $client->get_attempt( 'eat_flow' ), 'site_mismatch', 'unknown', 'reconcile' );
ecotroc_equal( 2, count( $GLOBALS['ecotroc_test_calls'] ), 'A transport callback changing blogs never causes another request.' );
ecotroc_idle();
$client = ecotroc_ready();
$precise_receipt = clone $receipt;
$precise_receipt->admittedAt = '2026-10-02T12:00:00.0000000Z';
$precise_receipt->confirmationDeadline = '2026-10-02T12:01:00.0000001Z';
ecotroc_queue( ecotroc_response( 202, $precise_receipt, array( 'Cache-Control' => 'no-store', 'Location' => $precise_receipt->href ) ) );
ecotroc_error( $client->accept_proposal( 'exp_flow-child', new stdClass(), 'precision-key-0001', '"exp_flow-child:3"' ), 'invalid_response' );
ecotroc_idle();
$client = ecotroc_ready();
$precise_receipt->admittedAt = '2026-10-02T12:00:00.123456789Z';
$precise_receipt->confirmationDeadline = '2026-10-02T12:01:00.123456789000Z';
ecotroc_queue( ecotroc_response( 202, $precise_receipt, array( 'Cache-Control' => 'no-store', 'Location' => $precise_receipt->href ) ) );
$result = $client->accept_proposal( 'exp_flow-child', new stdClass(), 'precision-key-0001', '"exp_flow-child:3"' );
ecotroc_equal( true, $result['ok'], 'Exact 60-second deadline remains valid at arbitrary fractional precision.' );
ecotroc_equal( json_encode( $precise_receipt ), json_encode( $result['data'] ), 'The adapter preserves precision rather than truncating public timestamps.' );
ecotroc_idle();

/* No provider or mismatched scope cannot be substituted by WordPress rights. */
foreach ( array(
	null,
	static function ( $context ) { return null; },
	static function ( $context ) { return array( 'blog_id' => 2, 'tenant_id' => 'ten_a', 'access_token' => 'test-token' ); },
	static function ( $context ) { return array( 'blog_id' => 1, 'tenant_id' => 'ten_b', 'access_token' => 'test-token' ); },
	static function ( $context ) { return array( 'blog_id' => 1, 'tenant_id' => 'ten_a', 'access_token' => "token\r\nInjected: yes" ); },
	static function ( $context ) { throw new RuntimeException( 'private-provider-secret' ); },
) as $provider ) {
	$client = ecotroc_ready( $provider );
	$result = $client->get_attempt( 'eat_flow' );
	ecotroc_error( $result, 'context_unavailable', 'not_sent', 'none' );
	ecotroc_check( false === strpos( json_encode( $result ), 'private-provider-secret' ), 'Provider exception details are not exposed.' );
	ecotroc_equal( 1, count( $GLOBALS['ecotroc_test_calls'] ), 'Unbound actor context performs no private request.' );
	ecotroc_idle();
}
$client = ecotroc_ready();
$GLOBALS['ecotroc_test_blog'] = 2;
ecotroc_error( $client->get_attempt( 'eat_flow' ), 'site_mismatch', 'not_sent', 'none' );
ecotroc_equal( 1, count( $GLOBALS['ecotroc_test_calls'] ), 'An existing client cannot cross switch_to_blog boundaries.' );
ecotroc_equal( array(), $GLOBALS['ecotroc_test_provider_calls'], 'Stale client never obtains credentials for another site.' );
$other = new TheCore_Collectivity_EcoTroc_Client( new TheCore_Collectivity_EcoTroc_Config(), 'ecotroc_provider' );
ecotroc_queue( ecotroc_response( 200, ecotroc_version_data() ) );
$other_view = clone $view;
$other_view->tenantId = 'ten_b';
ecotroc_queue( ecotroc_response( 200, $other_view, ecotroc_view_headers( $other_view ) ) );
ecotroc_equal( true, $other->get_attempt( 'eat_flow' )['ok'], 'A newly constructed client binds the new blog and tenant.' );
$call = ecotroc_last_call();
ecotroc_equal( 'https://other-api.example.test/v1/exchange-acceptance-attempts/eat_flow', $call['url'], 'Destination remains local to the new blog configuration.' );
ecotroc_equal( 'Bearer test-token-ten_b', ecotroc_header( $call, 'Authorization' ), 'Actor token remains local to the new tenant.' );
ecotroc_equal( 7.0, (float) $call['args']['timeout'], 'Timeout remains local to the new blog.' );
ecotroc_idle();

/* Reject unsafe or ambiguous API roots before any request. */
foreach ( array( 'http://api.example.test/v1', 'https://user:pass@api.example.test/v1', 'https://api.example.test/v2', 'https://api.example.test/v1?tenantId=ten_b', 'https://api.example.test/v1#fragment' ) as $base_url ) {
	ecotroc_reset();
	$GLOBALS['ecotroc_test_options'][1]['tccm_ecotroc_connection']['base_url'] = $base_url;
	$client = new TheCore_Collectivity_EcoTroc_Client( new TheCore_Collectivity_EcoTroc_Config(), 'ecotroc_provider' );
	ecotroc_error( $client->get_version(), 'configuration_error', 'not_sent', 'none' );
	ecotroc_equal( 0, count( $GLOBALS['ecotroc_test_calls'] ), 'Invalid API root is never contacted.' );
	ecotroc_idle();
}

fwrite( STDOUT, 'EcoTroc client tests passed: 54 package cases, 4 traces, transport and context regressions (' . $GLOBALS['ecotroc_test_assertions'] . " assertions).\n" );
