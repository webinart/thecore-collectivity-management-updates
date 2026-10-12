<?php
/**
 * Integrity and wire validation for the pinned EcoLoop candidate package.
 *
 * This deliberately implements only the schema vocabulary used by version
 * discovery and the acceptance/admission/read/cancellation profile. It is not
 * a general OpenAPI client, a complete JSON Schema implementation, or release
 * qualification. Unsupported validation keywords fail closed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TheCore_Collectivity_EcoTroc_Contract {
	const VERSION         = '1.0.0-draft.5';
	const MANIFEST_SHA256 = '284706959b6ec462561c05083e0e8b7ca111d1c43d9119c043613dbe24b56ba6';
	const SOURCE_COMMIT   = 'e37ae1920bfc1c0c6202bc844583c1b56a7a36ca';
	const MAX_BYTES       = 1048576;
	const MAX_DEPTH       = 64;
	const MAX_INTEGER     = 9007199254740991;

	/** @var stdClass Public OpenAPI only; never an internal contract. */
	private $openapi;

	/**
	 * Verify the complete package before exposing any schema.
	 *
	 * @param string|null $directory Optional private directory for integrity tests.
	 * @throws RuntimeException With a constant message, never file contents.
	 */
	public function __construct( $directory = null ) {
		$directory = null === $directory ? __DIR__ . '/contract' : $directory;
		if ( ! is_string( $directory ) || is_link( $directory ) || ! is_dir( $directory ) ) {
			$this->invalid_package();
		}
		$names = @scandir( $directory );
		if ( false === $names ) {
			$this->invalid_package();
		}
		$names = array_values( array_diff( $names, array( '.', '..' ) ) );
		sort( $names );
		if ( array( 'fixtures.json', 'manifest.json', 'openapi.json', 'wordpress.json' ) !== $names ) {
			$this->invalid_package();
		}
		$raw = array();
		foreach ( $names as $name ) {
			$path = $directory . '/' . $name;
			$stat = @lstat( $path );
			if ( false === $stat || 0100000 !== ( $stat['mode'] & 0170000 ) || $stat['size'] < 1 || $stat['size'] > self::MAX_BYTES ) {
				$this->invalid_package();
			}
			$raw[ $name ] = @file_get_contents( $path, false, null, 0, self::MAX_BYTES + 1 );
			if ( false === $raw[ $name ] || strlen( $raw[ $name ] ) !== $stat['size'] ) {
				$this->invalid_package();
			}
		}
		if ( ! hash_equals( self::MANIFEST_SHA256, hash( 'sha256', $raw['manifest.json'] ) ) ) {
			$this->invalid_package();
		}
		try {
			$manifest = $this->decode( $raw['manifest.json'] );
			if ( ! ( $manifest instanceof stdClass ) || 1 !== $manifest->formatVersion || 'ecoloop.wordpress-contract' !== $manifest->kind || 'candidate' !== $manifest->status || false !== $manifest->productionReady || self::VERSION !== $manifest->contractVersion || ! ( $manifest->source instanceof stdClass ) || 'webinart/ecoloop' !== $manifest->source->repository || self::SOURCE_COMMIT !== $manifest->source->commit || false !== $manifest->source->workingTreeDirty || ! is_array( $manifest->files ) || 3 !== count( $manifest->files ) ) {
				$this->invalid_package();
			}
			$verified = array();
			foreach ( $manifest->files as $file ) {
				if ( ! ( $file instanceof stdClass ) || ! isset( $file->path, $file->bytes, $file->sha256 ) || ! in_array( $file->path, array( 'fixtures.json', 'openapi.json', 'wordpress.json' ), true ) || isset( $verified[ $file->path ] ) || ! is_int( $file->bytes ) || ! is_string( $file->sha256 ) || strlen( $raw[ $file->path ] ) !== $file->bytes || ! hash_equals( $file->sha256, hash( 'sha256', $raw[ $file->path ] ) ) ) {
					$this->invalid_package();
				}
				$verified[ $file->path ] = true;
			}
			$matrix        = $this->decode( $raw['wordpress.json'] );
			$this->openapi = $this->decode( $raw['openapi.json'] );
			$this->decode( $raw['fixtures.json'] );
			if ( ! ( $matrix instanceof stdClass ) || 1 !== $matrix->formatVersion || 'candidate' !== $matrix->status || false !== $matrix->productionReady || self::VERSION !== $matrix->contractVersion || ! ( $matrix->consumer instanceof stdClass ) || 'webinart/thecore-collectivity-management' !== $matrix->consumer->repository || 'ecotroc' !== $matrix->consumer->module || false !== $matrix->consumer->defaultActive || array() !== $matrix->qualifiedReleases || ! ( $this->openapi instanceof stdClass ) || '3.1.0' !== $this->openapi->openapi || ! ( $this->openapi->info instanceof stdClass ) || self::VERSION !== $this->openapi->info->version ) {
				$this->invalid_package();
			}
		} catch ( InvalidArgumentException $error ) {
			$this->invalid_package();
		}
	}

	/**
	 * Read a local JSON pointer, returning a copy so callers cannot alter the pin.
	 *
	 * @param string $pointer Local fragment such as #/components/schemas/Error.
	 * @return stdClass|array|bool|mixed
	 * @throws RuntimeException When the local fragment is absent or invalid.
	 */
	public function schema( $pointer ) {
		return $this->copy( $this->resolve( $pointer ) );
	}

	/**
	 * Strict JSON decoding: objects remain stdClass, arrays remain JSON lists.
	 * Duplicate names are rejected after unescaping, at every nesting level.
	 *
	 * @param string $raw Wire JSON; no body is ever included in diagnostics.
	 * @return mixed
	 * @throws InvalidArgumentException With a constant, body-free message.
	 */
	public function decode( $raw ) {
		if ( ! is_string( $raw ) || strlen( $raw ) > self::MAX_BYTES ) {
			throw new InvalidArgumentException( 'Invalid EcoLoop JSON.' );
		}
		$value = json_decode( $raw, false, self::MAX_DEPTH + 1 );
		if ( JSON_ERROR_NONE !== json_last_error() || ! $this->json_value( $value, 0 ) ) {
			throw new InvalidArgumentException( 'Invalid EcoLoop JSON.' );
		}
		$offset = 0;
		$this->scan_value( $raw, $offset, 0 );
		$this->skip_space( $raw, $offset );
		if ( $offset !== strlen( $raw ) ) {
			throw new InvalidArgumentException( 'Invalid EcoLoop JSON.' );
		}
		return $value;
	}

	/**
	 * Validate only the supported candidate vocabulary, with no coercion.
	 *
	 * @param mixed                  $value JSON value; objects must be stdClass.
	 * @param string|stdClass|array|bool $schema_or_pointer Fragment or local pointer.
	 * @return bool
	 */
	public function validate( $value, $schema_or_pointer ) {
		try {
			$schema = is_string( $schema_or_pointer ) ? $this->resolve( $schema_or_pointer ) : $schema_or_pointer;
			return $this->json_value( $value, 0 ) && $this->supported( $schema, 0 ) && $this->validate_node( $value, $schema, 0 );
		} catch ( RuntimeException $error ) {
			return false;
		}
	}

	private function invalid_package() {
		throw new RuntimeException( 'Invalid pinned EcoLoop candidate package.' );
	}

	private function resolve( $pointer ) {
		if ( ! is_string( $pointer ) || 0 !== strpos( $pointer, '#/' ) ) {
			throw new RuntimeException( 'Invalid EcoLoop local schema reference.' );
		}
		$value = $this->openapi;
		foreach ( explode( '/', substr( $pointer, 2 ) ) as $segment ) {
			if ( preg_match( '/~(?![01])/', $segment ) ) {
				throw new RuntimeException( 'Invalid EcoLoop local schema reference.' );
			}
			$segment = str_replace( array( '~1', '~0' ), array( '/', '~' ), $segment );
			if ( $value instanceof stdClass && property_exists( $value, $segment ) ) {
				$value = $value->{$segment};
			} elseif ( is_array( $value ) && preg_match( '/^(0|[1-9][0-9]*)$/D', $segment ) && array_key_exists( $segment, $value ) ) {
				$value = $value[ $segment ];
			} else {
				throw new RuntimeException( 'Invalid EcoLoop local schema reference.' );
			}
		}
		return $value;
	}

	private function copy( $value ) {
		if ( $value instanceof stdClass ) {
			$result = new stdClass();
			foreach ( get_object_vars( $value ) as $key => $item ) {
				$result->{$key} = $this->copy( $item );
			}
			return $result;
		}
		if ( is_array( $value ) ) {
			return array_map( array( $this, 'copy' ), $value );
		}
		return $value;
	}

	private function schema_map( $schema ) {
		return $schema instanceof stdClass ? get_object_vars( $schema ) : $schema;
	}

	/** Check all branches, including branches not reached by this particular value. */
	private function supported( $schema, $depth ) {
		if ( $depth > self::MAX_DEPTH ) {
			return false;
		}
		if ( is_bool( $schema ) ) {
			return true;
		}
		if ( ! ( $schema instanceof stdClass ) && ! is_array( $schema ) ) {
			return false;
		}
		$schema = $this->schema_map( $schema );
		$known  = array( '$ref', 'type', 'const', 'enum', 'required', 'properties', 'additionalProperties', 'minProperties', 'maxProperties', 'items', 'minItems', 'maxItems', 'allOf', 'oneOf', 'not', 'if', 'then', 'else', 'minimum', 'maximum', 'minLength', 'maxLength', 'pattern', 'format', 'description', 'title', 'default', 'example', 'examples', 'deprecated', 'readOnly', 'writeOnly', '$comment' );
		foreach ( $schema as $key => $part ) {
			if ( ! in_array( $key, $known, true ) ) {
				return false;
			}
			if ( '$ref' === $key && ! $this->supported( $this->resolve( $part ), $depth + 1 ) ) {
				return false;
			}
			if ( in_array( $key, array( 'additionalProperties', 'items', 'not', 'if', 'then', 'else' ), true ) && ! $this->supported( $part, $depth + 1 ) ) {
				return false;
			}
			if ( in_array( $key, array( 'allOf', 'oneOf' ), true ) ) {
				if ( ! is_array( $part ) || empty( $part ) || ! $this->is_list( $part ) ) {
					return false;
				}
				foreach ( $part as $child ) {
					if ( ! $this->supported( $child, $depth + 1 ) ) {
						return false;
					}
				}
			}
			if ( 'properties' === $key ) {
				if ( ! ( $part instanceof stdClass ) && ! is_array( $part ) ) {
					return false;
				}
				foreach ( $this->schema_map( $part ) as $child ) {
					if ( ! $this->supported( $child, $depth + 1 ) ) {
						return false;
					}
				}
			}
			if ( in_array( $key, array( 'minProperties', 'maxProperties', 'minItems', 'maxItems', 'minLength', 'maxLength' ), true ) && ( ! is_int( $part ) || $part < 0 ) ) {
				return false;
			}
			if ( in_array( $key, array( 'minimum', 'maximum' ), true ) && ( ! is_int( $part ) && ! is_float( $part ) || ! is_finite( (float) $part ) ) ) {
				return false;
			}
			if ( 'required' === $key && ( ! is_array( $part ) || ! $this->is_list( $part ) || count( array_filter( $part, 'is_string' ) ) !== count( $part ) ) ) {
				return false;
			}
			if ( 'enum' === $key && ( ! is_array( $part ) || empty( $part ) || ! $this->is_list( $part ) ) ) {
				return false;
			}
			if ( 'type' === $key ) {
				$types = is_string( $part ) ? array( $part ) : $part;
				if ( ! is_array( $types ) || empty( $types ) || ! $this->is_list( $types ) ) {
					return false;
				}
				foreach ( $types as $type ) {
					if ( ! in_array( $type, array( 'object', 'array', 'string', 'integer', 'number', 'boolean', 'null' ), true ) ) {
						return false;
					}
				}
			}
			if ( 'pattern' === $key && ( ! is_string( $part ) || false === @preg_match( $this->pattern( $part ), '' ) ) ) {
				return false;
			}
			if ( 'format' === $key && 'date-time' !== $part ) {
				return false;
			}
		}
		return true;
	}

	private function validate_node( $value, $schema, $depth ) {
		if ( $depth > self::MAX_DEPTH || false === $schema ) {
			return false;
		}
		if ( true === $schema ) {
			return true;
		}
		$schema = $this->schema_map( $schema );
		if ( isset( $schema['$ref'] ) && ! $this->validate_node( $value, $this->resolve( $schema['$ref'] ), $depth + 1 ) ) {
			return false;
		}
		if ( isset( $schema['type'] ) ) {
			$types   = is_array( $schema['type'] ) ? $schema['type'] : array( $schema['type'] );
			$matched = false;
			foreach ( $types as $type ) {
				$matched = $matched || $this->matches_type( $value, $type );
			}
			if ( ! $matched ) {
				return false;
			}
		}
		if ( array_key_exists( 'const', $schema ) && ! $this->equal( $value, $schema['const'] ) ) {
			return false;
		}
		if ( isset( $schema['enum'] ) ) {
			$matched = false;
			foreach ( $schema['enum'] as $candidate ) {
				$matched = $matched || $this->equal( $value, $candidate );
			}
			if ( ! $matched ) {
				return false;
			}
		}
		if ( isset( $schema['allOf'] ) ) {
			foreach ( $schema['allOf'] as $part ) {
				if ( ! $this->validate_node( $value, $part, $depth + 1 ) ) {
					return false;
				}
			}
		}
		if ( isset( $schema['oneOf'] ) ) {
			$matches = 0;
			foreach ( $schema['oneOf'] as $part ) {
				$matches += $this->validate_node( $value, $part, $depth + 1 ) ? 1 : 0;
			}
			if ( 1 !== $matches ) {
				return false;
			}
		}
		if ( isset( $schema['not'] ) && $this->validate_node( $value, $schema['not'], $depth + 1 ) ) {
			return false;
		}
		if ( array_key_exists( 'if', $schema ) ) {
			$branch = $this->validate_node( $value, $schema['if'], $depth + 1 ) ? 'then' : 'else';
			if ( array_key_exists( $branch, $schema ) && ! $this->validate_node( $value, $schema[ $branch ], $depth + 1 ) ) {
				return false;
			}
		}
		if ( $value instanceof stdClass ) {
			$properties = get_object_vars( $value );
			$declared   = isset( $schema['properties'] ) ? $this->schema_map( $schema['properties'] ) : array();
			foreach ( isset( $schema['required'] ) ? $schema['required'] : array() as $required ) {
				if ( ! property_exists( $value, $required ) ) {
					return false;
				}
			}
			if ( isset( $schema['minProperties'] ) && count( $properties ) < $schema['minProperties'] || isset( $schema['maxProperties'] ) && count( $properties ) > $schema['maxProperties'] ) {
				return false;
			}
			foreach ( $properties as $key => $item ) {
				$part = array_key_exists( $key, $declared ) ? $declared[ $key ] : ( isset( $schema['additionalProperties'] ) ? $schema['additionalProperties'] : true );
				if ( ! $this->validate_node( $item, $part, $depth + 1 ) ) {
					return false;
				}
			}
		}
		if ( is_array( $value ) ) {
			if ( isset( $schema['minItems'] ) && count( $value ) < $schema['minItems'] || isset( $schema['maxItems'] ) && count( $value ) > $schema['maxItems'] ) {
				return false;
			}
			if ( array_key_exists( 'items', $schema ) ) {
				foreach ( $value as $item ) {
					if ( ! $this->validate_node( $item, $schema['items'], $depth + 1 ) ) {
						return false;
					}
				}
			}
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			if ( isset( $schema['minimum'] ) && $value < $schema['minimum'] || isset( $schema['maximum'] ) && $value > $schema['maximum'] ) {
				return false;
			}
		}
		if ( is_string( $value ) ) {
			$length = preg_match_all( '/./us', $value );
			if ( false === $length || isset( $schema['minLength'] ) && $length < $schema['minLength'] || isset( $schema['maxLength'] ) && $length > $schema['maxLength'] || isset( $schema['pattern'] ) && 1 !== preg_match( $this->pattern( $schema['pattern'] ), $value ) || isset( $schema['format'] ) && ! $this->format( $value, $schema['format'] ) ) {
				return false;
			}
		}
		return true;
	}

	private function matches_type( $value, $type ) {
		switch ( $type ) {
			case 'object':
				return $value instanceof stdClass;
			case 'array':
				return is_array( $value ) && $this->is_list( $value );
			case 'string':
				return is_string( $value );
			case 'boolean':
				return is_bool( $value );
			case 'null':
				return null === $value;
			case 'integer':
				return ( is_int( $value ) || is_float( $value ) && is_finite( $value ) && floor( $value ) === $value ) && abs( $value ) <= self::MAX_INTEGER;
			case 'number':
				return is_int( $value ) || is_float( $value ) && is_finite( $value );
		}
		return false;
	}

	private function equal( $left, $right ) {
		if ( ( is_int( $left ) || is_float( $left ) ) && ( is_int( $right ) || is_float( $right ) ) ) {
			return $left == $right;
		}
		if ( $left instanceof stdClass && $right instanceof stdClass ) {
			$left  = get_object_vars( $left );
			$right = get_object_vars( $right );
			if ( count( $left ) !== count( $right ) ) {
				return false;
			}
			foreach ( $left as $key => $value ) {
				if ( ! array_key_exists( $key, $right ) || ! $this->equal( $value, $right[ $key ] ) ) {
					return false;
				}
			}
			return true;
		}
		if ( is_array( $left ) && is_array( $right ) && count( $left ) === count( $right ) ) {
			foreach ( $left as $key => $value ) {
				if ( ! array_key_exists( $key, $right ) || ! $this->equal( $value, $right[ $key ] ) ) {
					return false;
				}
			}
			return true;
		}
		return $left === $right;
	}

	private function pattern( $value ) {
		return '~' . str_replace( '~', '\\~', $value ) . '~u';
	}

	private function format( $value, $format ) {
		if ( 1 !== preg_match( '/^([0-9]{4})-([0-9]{2})-([0-9]{2})[tT]([0-9]{2}):([0-9]{2}):([0-9]{2})(?:\.[0-9]+)?(?:[zZ]|([+-])([0-9]{2}):([0-9]{2}))$/D', $value, $parts ) ) {
			return false;
		}
		if ( ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) || (int) $parts[4] > 23 || (int) $parts[5] > 59 || (int) $parts[6] > 60 || isset( $parts[8] ) && ( (int) $parts[8] > 23 || (int) $parts[9] > 59 ) ) {
			return false;
		}
		if ( 60 === (int) $parts[6] ) {
			$offset  = isset( $parts[8] ) ? ( (int) $parts[8] * 60 + (int) $parts[9] ) * ( '-' === $parts[7] ? -1 : 1 ) : 0;
			$minutes = ( (int) $parts[4] * 60 + (int) $parts[5] - $offset + 1440 ) % 1440;
			return 1439 === $minutes;
		}
		return true;
	}

	private function is_list( $value ) {
		return array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	private function json_value( $value, $depth ) {
		if ( $depth > self::MAX_DEPTH ) {
			return false;
		}
		if ( is_float( $value ) ) {
			return is_finite( $value );
		}
		if ( is_string( $value ) ) {
			return 1 === preg_match( '//u', $value );
		}
		if ( null === $value || is_bool( $value ) || is_int( $value ) ) {
			return true;
		}
		if ( $value instanceof stdClass || is_array( $value ) && $this->is_list( $value ) ) {
			foreach ( $value as $item ) {
				if ( ! $this->json_value( $item, $depth + 1 ) ) {
					return false;
				}
			}
			return true;
		}
		return false;
	}

	private function skip_space( $raw, &$offset ) {
		$length = strlen( $raw );
		while ( $offset < $length && false !== strpos( " \t\n\r", $raw[ $offset ] ) ) {
			++$offset;
		}
	}

	/** Scan only already syntax-checked JSON, retaining no private values. */
	private function scan_value( $raw, &$offset, $depth ) {
		$this->skip_space( $raw, $offset );
		$token = $raw[ $offset ];
		if ( '{' === $token || '[' === $token ) {
			if ( $depth >= self::MAX_DEPTH ) {
				throw new InvalidArgumentException( 'Invalid EcoLoop JSON.' );
			}
			$object = '{' === $token;
			$close  = $object ? '}' : ']';
			$seen   = array();
			++$offset;
			$this->skip_space( $raw, $offset );
			if ( $close === $raw[ $offset ] ) {
				++$offset;
				return;
			}
			while ( true ) {
				if ( $object ) {
					$key = $this->scan_string( $raw, $offset );
					if ( isset( $seen[ $key ] ) ) {
						throw new InvalidArgumentException( 'Invalid EcoLoop JSON.' );
					}
					$seen[ $key ] = true;
					$this->skip_space( $raw, $offset );
					++$offset; // The colon has already been checked by json_decode.
				}
				$this->scan_value( $raw, $offset, $depth + 1 );
				$this->skip_space( $raw, $offset );
				if ( $close === $raw[ $offset ] ) {
					++$offset;
					return;
				}
				++$offset; // Already-checked comma.
				$this->skip_space( $raw, $offset );
			}
		}
		if ( '"' === $token ) {
			$this->scan_string( $raw, $offset );
			return;
		}
		$length = strlen( $raw );
		while ( $offset < $length && false === strpos( " \t\n\r,]}", $raw[ $offset ] ) ) {
			++$offset;
		}
	}

	private function scan_string( $raw, &$offset ) {
		$start = $offset++;
		while ( '"' !== $raw[ $offset ] ) {
			if ( '\\' === $raw[ $offset ] ) {
				++$offset;
			}
			++$offset;
		}
		++$offset;
		return json_decode( substr( $raw, $start, $offset - $start ) );
	}
}
