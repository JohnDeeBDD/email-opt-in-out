<?php
/**
 * Reversible, stateless encoding of an email address (PRD Section 16).
 *
 * The encoding itself lives in `aiplugin5055\Library\EmailCodec`, which the
 * campaign manager loads from disk and calls directly. This class is the
 * plugin's name for it and nothing more: every method here forwards, so there
 * is one implementation and it cannot drift.
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Codec;

use aiplugin5055\Library\EmailCodec as Library;

require_once __DIR__ . '/../../library/autoload.php';

class EmailCodec {

	/**
	 * Canonical form used for encoding, decoding and user lookup.
	 *
	 * @param string $email Raw address.
	 * @return string
	 */
	public static function canonicalize( $email ) {
		return Library::canonicalize( $email );
	}

	/**
	 * Encode a canonical address into the site alphabet.
	 *
	 * @param string $canonical_email Canonical address.
	 * @param string $alphabet        32-character site alphabet.
	 * @return string
	 */
	public static function encode( $canonical_email, $alphabet ) {
		return Library::encode( $canonical_email, $alphabet );
	}

	/**
	 * Decode a string produced by encode().
	 *
	 * @param string $encoded  Encoded payload.
	 * @param string $alphabet 32-character site alphabet.
	 * @return string|null Canonical address, or null when the payload is not
	 *                     a well-formed encoding.
	 */
	public static function decode( $encoded, $alphabet ) {
		return Library::decode( $encoded, $alphabet );
	}
}
