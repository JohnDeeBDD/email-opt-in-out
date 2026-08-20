<?php
/**
 * Generates the five-character campaign codes (PRD Section 13).
 *
 * The shape of a code, and the alphabet it is drawn from, live in
 * `aiplugin5055\Library\CampaignCode` — the campaign manager validates the code
 * an operator pasted into a campaign file against the same definition. What
 * stays here is the part that needs the site: not reissuing a code that has
 * ever been used.
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Campaigns;

use aiplugin5055\Library\CampaignCode;

require_once __DIR__ . '/../../library/autoload.php';

class CampaignCodeGenerator {

	/** Campaign codes are exactly five characters long. */
	const LENGTH = CampaignCode::LENGTH;

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
		return CampaignCode::random();
	}

	/**
	 * Whether a string is shaped like a campaign code.
	 *
	 * @param string $code Candidate.
	 * @return bool
	 */
	public static function is_well_formed( $code ) {
		return CampaignCode::is_well_formed( $code );
	}
}
