<?php
/**
 * Standalone candidate-package and JSON boundary tests; no WordPress or HTTP.
 * The real-client fixture corpus is exercised by ecotroc-client-test.php.
 */

define( 'ABSPATH', __DIR__ . '/' );
require_once dirname( __DIR__ ) . '/includes/features/ecotroc/class-thecore-collectivity-ecotroc-contract.php';

$tccm_ecotroc_contract_checks = 0;

function tccm_ecotroc_contract_assert( $condition, $message ) {
	global $tccm_ecotroc_contract_checks;
	++$tccm_ecotroc_contract_checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function tccm_ecotroc_contract_reject_json( $contract, $raw, $message ) {
	try {
		$contract->decode( $raw );
	} catch ( InvalidArgumentException $error ) {
		tccm_ecotroc_contract_assert( 'Invalid EcoLoop JSON.' === $error->getMessage(), $message . ': diagnostics must contain no body.' );
		return;
	}
	tccm_ecotroc_contract_assert( false, $message );
}

function tccm_ecotroc_contract_cleanup( $directory ) {
	if ( is_link( $directory ) || ! is_dir( $directory ) ) {
		unlink( $directory );
		return;
	}
	foreach ( array_diff( scandir( $directory ), array( '.', '..' ) ) as $name ) {
		tccm_ecotroc_contract_cleanup( $directory . '/' . $name );
	}
	rmdir( $directory );
}

function tccm_ecotroc_contract_tamper( $source, $mutation, $message ) {
	$directory = sys_get_temp_dir() . '/tccm-ecotroc-contract-' . bin2hex( random_bytes( 8 ) );
	if ( ! mkdir( $directory, 0700 ) ) {
		throw new RuntimeException( 'Cannot create the private test directory.' );
	}
	try {
		foreach ( array( 'openapi.json', 'fixtures.json', 'wordpress.json', 'manifest.json' ) as $name ) {
			if ( ! copy( $source . '/' . $name, $directory . '/' . $name ) ) {
				throw new RuntimeException( 'Cannot prepare the test package.' );
			}
		}
		$mutation( $directory );
		try {
			new TheCore_Collectivity_EcoTroc_Contract( $directory );
		} catch ( RuntimeException $error ) {
			tccm_ecotroc_contract_assert( 'Invalid pinned EcoLoop candidate package.' === $error->getMessage(), $message . ': diagnostics must contain no path or payload.' );
			return;
		}
		tccm_ecotroc_contract_assert( false, $message );
	} finally {
		tccm_ecotroc_contract_cleanup( $directory );
	}
}

try {
	$source   = dirname( __DIR__ ) . '/includes/features/ecotroc/contract';
	$contract = new TheCore_Collectivity_EcoTroc_Contract();
	new TheCore_Collectivity_EcoTroc_Contract( $source );
	tccm_ecotroc_contract_assert( TheCore_Collectivity_EcoTroc_Contract::MANIFEST_SHA256 === hash_file( 'sha256', $source . '/manifest.json' ), 'The package manifest must match the independent pin.' );
	$manifest = $contract->decode( file_get_contents( $source . '/manifest.json' ) );
	$matrix   = $contract->decode( file_get_contents( $source . '/wordpress.json' ) );
	tccm_ecotroc_contract_assert( TheCore_Collectivity_EcoTroc_Contract::SOURCE_COMMIT === $manifest->source->commit && false === $manifest->source->workingTreeDirty, 'The pin must name the exact clean candidate source.' );
	tccm_ecotroc_contract_assert( 'candidate' === $matrix->status && false === $matrix->productionReady && false === $matrix->consumer->defaultActive && array() === $matrix->qualifiedReleases, 'Candidate metadata must not qualify a release or activate the module.' );
	tccm_ecotroc_contract_assert( '1.0.0-draft.5' === $contract->schema( '#/info/version' ), 'The public contract version must remain pinned.' );
	$copy = $contract->schema( '#/components/schemas/ExchangeAcceptanceAttempt' );
	$copy->properties->id->const = 'eat_changed';
	tccm_ecotroc_contract_assert( ! property_exists( $contract->schema( '#/components/schemas/ExchangeAcceptanceAttempt' )->properties->id, 'const' ), 'Returned fragments must not mutate the loaded contract.' );
	foreach ( array( '#/does-not-exist', 'https://example.test/schema', '#/components/~2schemas/Error' ) as $pointer ) {
		try {
			$contract->schema( $pointer );
			tccm_ecotroc_contract_assert( false, 'Invalid local references must be rejected.' );
		} catch ( RuntimeException $error ) {
			tccm_ecotroc_contract_assert( 'Invalid EcoLoop local schema reference.' === $error->getMessage(), 'Schema errors must contain no caller-supplied reference.' );
		}
	}

	$bad_json = array(
		'{"a":1,"a":2}',
		'{"a":1,"\\u0061":2}',
		'{"nested":[{"x":1,"x":2}]}',
		'{"é":1,"\\u00e9":2}',
		'{"😀":1,"\\ud83d\\ude00":2}',
		'{"1":true,"\\u0031":false}',
		'{"private":"sentinel-private-body",',
		'{"x":NaN}',
		'1e9999',
		"\"\xFF\"",
		'"\\ud800"',
		str_repeat( '[', 65 ) . '0' . str_repeat( ']', 65 ),
	);
	foreach ( $bad_json as $raw ) {
		tccm_ecotroc_contract_reject_json( $contract, $raw, 'Malformed, ambiguous or unbounded JSON must be rejected.' );
	}
	$decoded = $contract->decode( json_encode( (object) array( 'a' => null, 'items' => array(), 'nested' => (object) array( 'a' => 'quote " and slash \\' ) ) ) );
	tccm_ecotroc_contract_assert( $decoded instanceof stdClass && null === $decoded->a && array() === $decoded->items && $decoded->nested instanceof stdClass, 'Decoding must preserve JSON object/list/null distinctions.' );
	tccm_ecotroc_contract_assert( null === $contract->decode( 'null' ) && true === $contract->decode( 'true' ), 'Valid JSON scalars must remain valid.' );
	tccm_ecotroc_contract_assert( is_array( $contract->decode( str_repeat( '[', 64 ) . '0' . str_repeat( ']', 64 ) ) ), 'The documented depth boundary must admit 64 containers.' );

	$checks = array(
		'integral JSON number'       => $contract->validate( 1.0, array( 'type' => 'integer' ) ),
		'fractional number rejected' => ! $contract->validate( 1.1, array( 'type' => 'integer' ) ),
		'numeric string rejected'    => ! $contract->validate( '1', array( 'type' => 'integer' ) ),
		'boolean integer rejected'   => ! $contract->validate( true, array( 'type' => 'integer' ) ),
		'safe integer maximum'       => $contract->validate( 9007199254740991, array( 'type' => 'integer' ) ),
		'unsafe integer rejected'    => ! $contract->validate( 9007199254740992, array( 'type' => 'integer' ) ),
		'infinite number rejected'   => ! $contract->validate( INF, true ),
		'empty object'               => $contract->validate( new stdClass(), array( 'type' => 'object', 'maxProperties' => 0 ) ),
		'empty array is not object'  => ! $contract->validate( array(), array( 'type' => 'object' ) ),
		'object is not array'        => ! $contract->validate( new stdClass(), array( 'type' => 'array' ) ),
		'associative PHP array'      => ! $contract->validate( array( 'a' => 1 ), true ),
		'boolean false schema'       => ! $contract->validate( null, false ),
		'boolean true schema'        => $contract->validate( null, true ),
		'required property'          => ! $contract->validate( new stdClass(), array( 'required' => array( 'a' ) ) ),
		'null property is present'   => $contract->validate( (object) array( 'a' => null ), array( 'required' => array( 'a' ) ) ),
		'closed object'              => ! $contract->validate( (object) array( 'a' => true ), array( 'additionalProperties' => false ) ),
		'additional schema'          => ! $contract->validate( (object) array( 'a' => true ), array( 'additionalProperties' => array( 'type' => 'string' ) ) ),
		'bounded array'              => ! $contract->validate( array( 1, 2 ), array( 'maxItems' => 1 ) ),
		'array item type'            => ! $contract->validate( array( 1 ), array( 'items' => array( 'type' => 'string' ) ) ),
		'allOf combined limits'      => ! $contract->validate( 3, array( 'allOf' => array( array( 'minimum' => 1 ), array( 'maximum' => 2 ) ) ) ),
		'oneOf ambiguous'            => ! $contract->validate( 1, array( 'oneOf' => array( array( 'type' => 'integer' ), array( 'type' => 'number' ) ) ) ),
		'oneOf exactly one'          => $contract->validate( 1, array( 'oneOf' => array( array( 'type' => 'integer' ), array( 'type' => 'string' ) ) ) ),
		'not with const'             => ! $contract->validate( 'blocked', array( 'not' => array( 'const' => 'blocked' ) ) ),
		'if then branch'             => ! $contract->validate( 1, array( 'if' => array( 'type' => 'integer' ), 'then' => array( 'minimum' => 2 ) ) ),
		'if else branch'             => ! $contract->validate( 'a', array( 'if' => array( 'type' => 'integer' ), 'else' => array( 'minLength' => 2 ) ) ),
		'local reference siblings'   => ! $contract->validate( 'eat_a', array( '$ref' => '#/components/schemas/ExchangeAcceptanceAttemptId', 'const' => 'eat_b' ) ),
		'external reference'         => ! $contract->validate( null, array( '$ref' => 'https://example.test/schema' ) ),
		'unknown keyword'            => ! $contract->validate( 1, array( 'multipleOf' => 1 ) ),
		'unknown skipped branch'     => ! $contract->validate( 1, array( 'if' => false, 'then' => array( 'multipleOf' => 1 ) ) ),
		'unsupported format'         => ! $contract->validate( 'a', array( 'format' => 'unknown' ) ),
		'parameter annotation'       => $contract->validate( 20, $contract->schema( '#/components/parameters/LimitParam/schema' ) ),
		'unicode codepoint length'   => $contract->validate( 'é😀', array( 'minLength' => 2, 'maxLength' => 2 ) ),
		'unicode pattern'            => $contract->validate( 'é😀', array( 'pattern' => '^..$' ) ),
		'enum no coercion'           => ! $contract->validate( '1', array( 'enum' => array( 1 ) ) ),
		'enum numeric semantics'     => $contract->validate( 1.0, array( 'enum' => array( 1 ) ) ),
		'invalid calendar date'      => ! $contract->validate( '2026-02-30T12:00:00Z', array( 'format' => 'date-time' ) ),
		'valid leap date'            => $contract->validate( '2024-02-29T12:00:00.123456+01:00', array( 'format' => 'date-time' ) ),
		'invalid leap date'          => ! $contract->validate( '2026-02-29T12:00:00Z', array( 'format' => 'date-time' ) ),
		'timezone required'          => ! $contract->validate( '2026-10-02T12:00:00', array( 'format' => 'date-time' ) ),
		'timezone bounded'           => ! $contract->validate( '2026-10-02T12:00:00+24:00', array( 'format' => 'date-time' ) ),
		'leap second UTC'            => $contract->validate( '2016-12-31T23:59:60Z', array( 'format' => 'date-time' ) ),
		'leap second invalid hour'   => ! $contract->validate( '2016-12-31T12:00:60Z', array( 'format' => 'date-time' ) ),
	);
	foreach ( $checks as $message => $condition ) {
		tccm_ecotroc_contract_assert( $condition, $message );
	}

	tccm_ecotroc_contract_tamper( $source, static function ( $directory ) {
		unlink( $directory . '/fixtures.json' );
	}, 'Missing file must reject the package' );
	tccm_ecotroc_contract_tamper( $source, static function ( $directory ) {
		file_put_contents( $directory . '/extra.json', '{}' );
	}, 'Additional file must reject the package' );
	tccm_ecotroc_contract_tamper( $source, static function ( $directory ) {
		file_put_contents( $directory . '/.hidden', 'sentinel-private-body' );
	}, 'Hidden file must reject the package' );
	tccm_ecotroc_contract_tamper( $source, static function ( $directory ) use ( $source ) {
		unlink( $directory . '/fixtures.json' );
		symlink( $source . '/fixtures.json', $directory . '/fixtures.json' );
	}, 'Symlink must reject the package' );
	tccm_ecotroc_contract_tamper( $source, static function ( $directory ) {
		unlink( $directory . '/fixtures.json' );
		mkdir( $directory . '/fixtures.json' );
	}, 'Non-regular file must reject the package' );
	tccm_ecotroc_contract_tamper( $source, static function ( $directory ) {
		file_put_contents( $directory . '/fixtures.json', ' ', FILE_APPEND );
	}, 'Size change must reject the package' );
	tccm_ecotroc_contract_tamper( $source, static function ( $directory ) {
		$path = $directory . '/openapi.json';
		file_put_contents( $path, str_replace( '1.0.0-draft.5', '1.0.0-draft.6', file_get_contents( $path ) ) );
	}, 'Equal-size digest change must reject the package' );
	tccm_ecotroc_contract_tamper( $source, static function ( $directory ) {
		$path = $directory . '/manifest.json';
		file_put_contents( $path, str_replace( '1.0.0-draft.5', '1.0.0-draft.6', file_get_contents( $path ) ) );
	}, 'Changed contract pin must reject the package' );
	tccm_ecotroc_contract_tamper( $source, static function ( $directory ) {
		$path = $directory . '/wordpress.json';
		file_put_contents( $path, str_replace( '"defaultActive": false', '"defaultActive": true ', file_get_contents( $path ) ) );
	}, 'Changed activation metadata must reject the package' );

	fwrite( STDOUT, 'EcoTroc contract boundary tests passed (' . $tccm_ecotroc_contract_checks . " checks).\n" );
} catch ( Throwable $error ) {
	fwrite( STDERR, 'FAIL: ' . $error->getMessage() . "\n" );
	exit( 1 );
}
