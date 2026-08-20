<?php
/**
 * The three site-level values every action code depends on.
 *
 * The plugin reads them from the `aiplugin5055_settings` option; an external
 * consumer (the Gmail Campaign Manager) reads the same values from its own
 * configuration. Either way they arrive here as one immutable object, so the
 * code-building functions never reach for WordPress and can be exercised with
 * fixed values in a test.
 *
 * @package aiplugin5055\Library
 */

namespace aiplugin5055\Library;

use InvalidArgumentException;

final class SiteSettings {

	/**
	 * Unambiguous, URL-safe, single-case alphabet (PRD Section 13 / 16).
	 *
	 * 32 characters: A-Z without I and O, plus 2-9. No glyph a reader can
	 * confuse (O/0, I/1) and none that needs percent-encoding. This is the
	 * canonical definition; `Support\Settings::BASE_ALPHABET` is an alias of it.
	 */
	const BASE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

	/** Length of the HMAC check value appended to an action code. */
	const DEFAULT_CHECK_VALUE_LENGTH = 3;

	/** The widest check value the plugin will accept. */
	const MAX_CHECK_VALUE_LENGTH = 8;

	/** @var string Site-specific shuffled alphabet. */
	private $alphabet;

	/** @var string Site secret keying the check value. */
	private $secret;

	/** @var int Number of check-value characters. */
	private $check_value_length;

	/**
	 * @param string $alphabet           32 distinct characters; the site's shuffled alphabet.
	 * @param string $secret             Site secret. May be empty only when no check value is used.
	 * @param int    $check_value_length Characters of check value, 0 to MAX_CHECK_VALUE_LENGTH.
	 * @throws InvalidArgumentException When a value could not produce a code the site would accept.
	 */
	public function __construct( $alphabet, $secret, $check_value_length = self::DEFAULT_CHECK_VALUE_LENGTH ) {
		$alphabet           = (string) $alphabet;
		$secret             = (string) $secret;
		$check_value_length = (int) $check_value_length;

		if ( ! self::is_usable_alphabet( $alphabet ) ) {
			throw new InvalidArgumentException(
				'The encoding alphabet must be exactly 32 distinct characters.'
			);
		}

		if ( $check_value_length < 0 || $check_value_length > self::MAX_CHECK_VALUE_LENGTH ) {
			throw new InvalidArgumentException(
				'The check value length must be between 0 and ' . self::MAX_CHECK_VALUE_LENGTH
				. ", got {$check_value_length}."
			);
		}

		// A check value keyed by an empty secret verifies against anything the
		// forger can compute, which is worse than having no check value at all.
		if ( $check_value_length > 0 && '' === $secret ) {
			throw new InvalidArgumentException( 'The site secret is empty; check values would not verify.' );
		}

		$this->alphabet           = $alphabet;
		$this->secret             = $secret;
		$this->check_value_length = $check_value_length;
	}

	/** @return string */
	public function alphabet() {
		return $this->alphabet;
	}

	/** @return string */
	public function secret() {
		return $this->secret;
	}

	/** @return int */
	public function check_value_length() {
		return $this->check_value_length;
	}

	/**
	 * The same settings with a different check-value length.
	 *
	 * @param int $check_value_length New length.
	 * @return self
	 */
	public function with_check_value_length( $check_value_length ) {
		return new self( $this->alphabet, $this->secret, $check_value_length );
	}

	/**
	 * Whether an alphabet can be used to encode and decode at all.
	 *
	 * This is the plugin's own acceptance rule: 32 characters, all distinct.
	 * A repeated character makes decoding ambiguous, which is the failure that
	 * matters here.
	 *
	 * @param string $alphabet Candidate alphabet.
	 * @return bool
	 */
	public static function is_usable_alphabet( $alphabet ) {
		$alphabet = (string) $alphabet;

		return 32 === strlen( $alphabet ) && 32 === count( array_unique( str_split( $alphabet ) ) );
	}

	/**
	 * Whether an alphabet is a permutation of the base alphabet.
	 *
	 * Stricter than `is_usable_alphabet()`, and the right check for a value an
	 * operator typed or pasted: every alphabet the plugin generates is a
	 * shuffle of the base one, so anything else is a copying mistake — and it
	 * would encode to characters the site's own parser strips or rejects.
	 *
	 * @param string $alphabet Candidate alphabet.
	 * @return bool
	 */
	public static function is_base_permutation( $alphabet ) {
		$alphabet = (string) $alphabet;

		if ( 32 !== strlen( $alphabet ) ) {
			return false;
		}

		$candidate = str_split( $alphabet );
		$base      = str_split( self::BASE_ALPHABET );
		sort( $candidate );
		sort( $base );

		return $candidate === $base;
	}
}
