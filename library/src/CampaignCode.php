<?php
/**
 * The five-character campaign code (PRD Section 13).
 *
 * Shape and randomness live here so that the site that mints codes and the
 * campaign manager that validates the one an operator pasted into a campaign
 * file agree on what a campaign code is.
 *
 * @package aiplugin5055\Library
 */

namespace aiplugin5055\Library;

final class CampaignCode {

	/** Campaign codes are exactly five characters long. */
	const LENGTH = 5;

	/**
	 * Whether a string is shaped like a campaign code.
	 *
	 * @param string $code Candidate.
	 * @return bool
	 */
	public static function is_well_formed( $code ) {
		return (bool) preg_match(
			'/^[' . preg_quote( SiteSettings::BASE_ALPHABET, '/' ) . ']{' . self::LENGTH . '}$/',
			(string) $code
		);
	}

	/**
	 * One random code from the unambiguous alphabet.
	 *
	 * The only function in this library that is not pure. It is called once
	 * when a campaign is created, never on the code-building path.
	 *
	 * @return string
	 */
	public static function random() {
		$alphabet = SiteSettings::BASE_ALPHABET;
		$maximum  = strlen( $alphabet ) - 1;
		$code     = '';

		for ( $i = 0; $i < self::LENGTH; $i++ ) {
			$code .= $alphabet[ random_int( 0, $maximum ) ];
		}

		return $code;
	}
}
