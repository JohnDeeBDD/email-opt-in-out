<?php
/**
 * A short ring buffer of rejected action codes, so abuse of a campaign code is
 * detectable (PRD Sections 19 and 35).
 *
 * Action codes are personal data by construction, so only a truncated form of
 * the submitted code and a hashed client identifier are retained.
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Support;

class RejectedCodeLog {

	const OPTION = 'aiplugin5055_rejected_codes';

	/** Entries retained. */
	const LIMIT = 100;

	/**
	 * Record a rejection.
	 *
	 * @param string $reason      Machine-readable failure reason.
	 * @param string $raw_code    Code as submitted.
	 * @param string $endpoint    Endpoint that rejected it.
	 * @return void
	 */
	public static function record( $reason, $raw_code, $endpoint ) {
		$entries = self::all();

		array_unshift(
			$entries,
			array(
				'timestamp' => \gmdate( 'c' ),
				'reason'    => \sanitize_key( $reason ),
				'endpoint'  => \sanitize_key( $endpoint ),
				// Enough to correlate repeated abuse, not enough to recover an
				// address from the log.
				'prefix'    => strtoupper( substr( preg_replace( '/[^A-Za-z0-9]/', '', (string) $raw_code ), 0, 5 ) ),
				'length'    => strlen( (string) $raw_code ),
				'ip_hash'   => RateLimiter::client_ip_hash(),
			)
		);

		\update_option( self::OPTION, array_slice( $entries, 0, self::LIMIT ), false );
	}

	/**
	 * @return array[]
	 */
	public static function all() {
		$entries = \get_option( self::OPTION, array() );

		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * @return void
	 */
	public static function clear() {
		\update_option( self::OPTION, array(), false );
	}
}
