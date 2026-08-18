<?php
/**
 * Generates the five-character campaign codes (PRD Section 13).
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Campaigns;

use aiplugin5055\Support\Settings;

class CampaignCodeGenerator {

	/** Campaign codes are exactly five characters long. */
	const LENGTH = 5;

	/** Attempts before giving up on finding an unused code. */
	const MAX_ATTEMPTS = 50;

	/**
	 * Produce a code that has never been issued on this site.
	 *
	 * Codes are never reissued — not even after their campaign is deleted —
	 * because user metadata keys still embed them.
	 *
	 * @param callable $is_taken Receives a candidate code, returns true when it
	 *                           has been issued before.
	 * @return string|null Unique code, or null if none could be found.
	 */
	public static function generate_unique( callable $is_taken ) {
		for ( $attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++ ) {
			$candidate = self::random_code();

			if ( ! $is_taken( $candidate ) ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * One random code from the unambiguous alphabet.
	 *
	 * @return string
	 */
	public static function random_code() {
		$alphabet = Settings::BASE_ALPHABET;
		$maximum  = strlen( $alphabet ) - 1;
		$code     = '';

		for ( $i = 0; $i < self::LENGTH; $i++ ) {
			$code .= $alphabet[ random_int( 0, $maximum ) ];
		}

		return $code;
	}

	/**
	 * Whether a string is shaped like a campaign code.
	 *
	 * @param string $code Candidate.
	 * @return bool
	 */
	public static function is_well_formed( $code ) {
		return (bool) preg_match(
			'/^[' . preg_quote( Settings::BASE_ALPHABET, '/' ) . ']{' . self::LENGTH . '}$/',
			(string) $code
		);
	}
}
