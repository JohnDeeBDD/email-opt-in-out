<?php
/**
 * The action code — the tracking code that travels in the campaign email.
 *
 *   [ 5-character campaign code ][ encoded email address ][ check value ]
 *
 * The campaign code authorizes the request and names the campaign whose
 * metadata key the action writes. The encoded portion carries the recipient's
 * identity. The optional check value (PRD Section 16) is a truncated HMAC over
 * the canonical address and the campaign code, which detects mangled links and
 * stops a recipient who holds one valid code from forging codes for other
 * addresses in the same campaign.
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Codec;

use aiplugin5055\Support\Settings;

class ActionCode {

	/** Fixed width of the campaign-code prefix. */
	const CAMPAIGN_CODE_LENGTH = 5;

	/** Defensive upper bound on an accepted code. */
	const MAX_LENGTH = 512;

	/**
	 * Build the action code for one recipient of one campaign.
	 *
	 * Pure transformation: nothing is stored and no user is created
	 * (PRD Sections 5 and 17).
	 *
	 * @param string $campaign_code Five-character campaign code.
	 * @param string $email         Recipient address, any casing.
	 * @return string
	 */
	public static function build( $campaign_code, $email ) {
		$campaign_code = strtoupper( (string) $campaign_code );
		$canonical     = EmailCodec::canonicalize( $email );
		$payload       = EmailCodec::encode( $canonical, Settings::alphabet() );

		return $campaign_code . $payload . self::check_value( $canonical, $campaign_code );
	}

	/**
	 * Parse an action code back into a campaign code and an email address.
	 *
	 * Performs every check that does not need the campaign record; the caller
	 * is responsible for confirming that the campaign exists and is active.
	 *
	 * @param string $raw Code as it arrived from the request.
	 * @return array {
	 *     @type bool   $ok            Whether the code is well formed.
	 *     @type string $reason        Machine-readable failure reason.
	 *     @type string $campaign_code Campaign code (on success).
	 *     @type string $email         Canonical address (on success).
	 * }
	 */
	public static function parse( $raw ) {
		$raw = (string) $raw;

		if ( strlen( $raw ) > self::MAX_LENGTH ) {
			return self::failure( 'too_long' );
		}

		// Mail clients and link rewriters may introduce whitespace or soft line
		// breaks; the alphabet is alphanumeric, so anything else is noise.
		$code = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $raw ) );

		$check_length = Settings::check_value_length();
		$minimum      = self::CAMPAIGN_CODE_LENGTH + 1 + $check_length;

		if ( strlen( $code ) < $minimum ) {
			return self::failure( 'too_short' );
		}

		$campaign_code = substr( $code, 0, self::CAMPAIGN_CODE_LENGTH );

		if ( ! preg_match( '/^[' . preg_quote( Settings::BASE_ALPHABET, '/' ) . ']{5}$/', $campaign_code ) ) {
			return self::failure( 'malformed_campaign_code' );
		}

		$remainder = substr( $code, self::CAMPAIGN_CODE_LENGTH );

		if ( $check_length > 0 ) {
			$payload   = substr( $remainder, 0, -$check_length );
			$check     = substr( $remainder, -$check_length );
		} else {
			$payload = $remainder;
			$check   = '';
		}

		if ( '' === $payload ) {
			return self::failure( 'empty_payload' );
		}

		$email = EmailCodec::decode( $payload, Settings::alphabet() );

		if ( null === $email || '' === $email ) {
			return self::failure( 'undecodable' );
		}

		if ( ! \is_email( $email ) ) {
			return self::failure( 'not_an_email' );
		}

		if ( $check_length > 0 && ! hash_equals( self::check_value( $email, $campaign_code ), $check ) ) {
			return self::failure( 'bad_check_value' );
		}

		return array(
			'ok'            => true,
			'reason'        => '',
			'campaign_code' => $campaign_code,
			'email'         => $email,
		);
	}

	/**
	 * Truncated keyed hash over the canonical address and the campaign code.
	 *
	 * @param string $canonical_email Canonical address.
	 * @param string $campaign_code   Campaign code.
	 * @return string
	 */
	private static function check_value( $canonical_email, $campaign_code ) {
		$length = Settings::check_value_length();

		if ( 0 === $length ) {
			return '';
		}

		$raw      = hash_hmac( 'sha256', $canonical_email . '|' . $campaign_code, Settings::secret(), true );
		$alphabet = Settings::BASE_ALPHABET;
		$check    = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$check .= $alphabet[ ord( $raw[ $i ] ) % 32 ];
		}

		return $check;
	}

	/**
	 * @param string $reason Failure reason.
	 * @return array
	 */
	private static function failure( $reason ) {
		return array(
			'ok'            => false,
			'reason'        => $reason,
			'campaign_code' => '',
			'email'         => '',
		);
	}
}
