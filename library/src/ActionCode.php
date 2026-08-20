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
 * This class is the single definition of that construction. The WordPress
 * plugin calls it with settings read from the options table; the Gmail
 * Campaign Manager calls it with the same settings read from its own
 * configuration file. Nothing here touches WordPress, the filesystem, the
 * clock or a random source: the same inputs produce the same code in either
 * process, which is the property the whole scheme rests on — a mailed link
 * cannot be recalled if the two sides disagree.
 *
 * @package aiplugin5055\Library
 */

namespace aiplugin5055\Library;

use InvalidArgumentException;

final class ActionCode {

	/** Fixed width of the campaign-code prefix. */
	const CAMPAIGN_CODE_LENGTH = CampaignCode::LENGTH;

	/** Defensive upper bound on an accepted code. */
	const MAX_LENGTH = 512;

	/**
	 * Build the action code for one recipient of one campaign.
	 *
	 * Pure transformation: nothing is stored and no user is created
	 * (PRD Sections 5 and 17).
	 *
	 * @param string       $campaign_code Five-character campaign code, any casing.
	 * @param string       $email         Recipient address, any casing.
	 * @param SiteSettings $settings      Site alphabet, secret and check length.
	 * @return string
	 * @throws InvalidArgumentException When the inputs cannot produce a code the site would accept.
	 */
	public static function build( $campaign_code, $email, SiteSettings $settings ) {
		// Uppercased before the check value is taken, so a campaign code
		// written in lower case still produces the code the site verifies.
		$campaign_code = strtoupper( (string) $campaign_code );

		if ( ! CampaignCode::is_well_formed( $campaign_code ) ) {
			throw new InvalidArgumentException( "Malformed campaign code: {$campaign_code}" );
		}

		$canonical = EmailCodec::canonicalize( $email );

		if ( '' === $canonical ) {
			throw new InvalidArgumentException( 'Cannot build an action code for an empty address.' );
		}

		$payload = EmailCodec::encode( $canonical, $settings->alphabet() );

		return $campaign_code . $payload . self::check_value( $canonical, $campaign_code, $settings );
	}

	/**
	 * Parse an action code back into a campaign code and an email address.
	 *
	 * Performs every check that does not need the campaign record; the caller
	 * is responsible for confirming that the campaign exists and is active.
	 *
	 * @param string       $raw      Code as it arrived from the request.
	 * @param SiteSettings $settings Site alphabet, secret and check length.
	 * @param callable     $is_email Optional address validator; defaults to
	 *                               FILTER_VALIDATE_EMAIL. WordPress passes
	 *                               `is_email` so the plugin keeps its own rules.
	 * @return array {
	 *     @type bool   $ok            Whether the code is well formed.
	 *     @type string $reason        Machine-readable failure reason.
	 *     @type string $campaign_code Campaign code (on success).
	 *     @type string $email         Canonical address (on success).
	 * }
	 */
	public static function parse( $raw, SiteSettings $settings, $is_email = null ) {
		$raw = (string) $raw;

		if ( strlen( $raw ) > self::MAX_LENGTH ) {
			return self::failure( 'too_long' );
		}

		if ( null === $is_email ) {
			$is_email = array( __CLASS__, 'looks_like_an_email' );
		}

		// Mail clients and link rewriters may introduce whitespace or soft line
		// breaks; the alphabet is alphanumeric, so anything else is noise.
		$code = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $raw ) );

		$check_length = $settings->check_value_length();
		$minimum      = self::CAMPAIGN_CODE_LENGTH + 1 + $check_length;

		if ( strlen( $code ) < $minimum ) {
			return self::failure( 'too_short' );
		}

		$campaign_code = substr( $code, 0, self::CAMPAIGN_CODE_LENGTH );

		if ( ! CampaignCode::is_well_formed( $campaign_code ) ) {
			return self::failure( 'malformed_campaign_code' );
		}

		$remainder = substr( $code, self::CAMPAIGN_CODE_LENGTH );

		if ( $check_length > 0 ) {
			$payload = substr( $remainder, 0, -$check_length );
			$check   = substr( $remainder, -$check_length );
		} else {
			$payload = $remainder;
			$check   = '';
		}

		if ( '' === $payload ) {
			return self::failure( 'empty_payload' );
		}

		$email = EmailCodec::decode( $payload, $settings->alphabet() );

		if ( null === $email || '' === $email ) {
			return self::failure( 'undecodable' );
		}

		if ( ! call_user_func( $is_email, $email ) ) {
			return self::failure( 'not_an_email' );
		}

		if ( $check_length > 0
			&& ! hash_equals( self::check_value( $email, $campaign_code, $settings ), $check ) ) {
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
	 * The HMAC is taken raw (binary), and each output character is one raw byte
	 * reduced modulo 32 into the *base* alphabet — never the site's shuffled
	 * one. 256 is a multiple of 32, so the reduction carries no modulo bias.
	 *
	 * @param string       $canonical_email Canonical address.
	 * @param string       $campaign_code   Uppercased campaign code.
	 * @param SiteSettings $settings        Site secret and check length.
	 * @return string
	 */
	public static function check_value( $canonical_email, $campaign_code, SiteSettings $settings ) {
		$length = $settings->check_value_length();

		if ( 0 === $length ) {
			return '';
		}

		$raw      = hash_hmac( 'sha256', $canonical_email . '|' . $campaign_code, $settings->secret(), true );
		$alphabet = SiteSettings::BASE_ALPHABET;
		$check    = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$check .= $alphabet[ ord( $raw[ $i ] ) % 32 ];
		}

		return $check;
	}

	/**
	 * Default address validator, used when the caller supplies none.
	 *
	 * @param string $email Decoded address.
	 * @return bool
	 */
	public static function looks_like_an_email( $email ) {
		return false !== filter_var( (string) $email, FILTER_VALIDATE_EMAIL );
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
