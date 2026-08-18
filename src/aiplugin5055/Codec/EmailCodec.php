<?php
/**
 * Reversible, stateless encoding of an email address (PRD Section 16).
 *
 * This is a deliberate obfuscation measure, not encryption: the address is
 * canonicalized, encoded with a Base32-style byte encoding, and the result is
 * mapped through a site-specific shuffled alphabet. Decoding reverses both
 * steps and needs no database row, which is what lets the plugin address
 * recipients that have no WordPress record at all.
 *
 * The functions here are pure so that they can be reasoned about (and tested)
 * without WordPress.
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Codec;

class EmailCodec {

	/**
	 * Canonical form used for encoding, decoding and user lookup.
	 *
	 * @param string $email Raw address.
	 * @return string
	 */
	public static function canonicalize( $email ) {
		return strtolower( trim( (string) $email ) );
	}

	/**
	 * Encode a canonical address into the site alphabet.
	 *
	 * @param string $canonical_email Canonical address.
	 * @param string $alphabet        32-character site alphabet.
	 * @return string
	 */
	public static function encode( $canonical_email, $alphabet ) {
		$bytes  = (string) $canonical_email;
		$length = strlen( $bytes );
		$value  = 0;
		$bits   = 0;
		$out    = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$value = ( $value << 8 ) | ord( $bytes[ $i ] );
			$bits += 8;

			while ( $bits >= 5 ) {
				$bits -= 5;
				$out  .= $alphabet[ ( $value >> $bits ) & 31 ];
				$value &= ( 1 << $bits ) - 1;
			}
		}

		if ( $bits > 0 ) {
			$out .= $alphabet[ ( $value << ( 5 - $bits ) ) & 31 ];
		}

		return $out;
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
		$encoded = (string) $encoded;

		if ( '' === $encoded ) {
			return null;
		}

		$map    = array_flip( str_split( $alphabet ) );
		$length = strlen( $encoded );
		$value  = 0;
		$bits   = 0;
		$out    = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$character = $encoded[ $i ];

			if ( ! isset( $map[ $character ] ) ) {
				return null;
			}

			$value = ( $value << 5 ) | $map[ $character ];
			$bits += 5;

			if ( $bits >= 8 ) {
				$bits -= 8;
				$out  .= chr( ( $value >> $bits ) & 255 );
				$value &= ( 1 << $bits ) - 1;
			}
		}

		// Trailing bits are padding and must be zero. A non-zero remainder
		// means the code was truncated or tampered with.
		if ( $bits > 0 && 0 !== $value ) {
			return null;
		}

		return $out;
	}
}
