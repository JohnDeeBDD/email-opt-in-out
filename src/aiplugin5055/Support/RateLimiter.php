<?php
/**
 * Transient-backed per-IP rate limiting for the public endpoints
 * (PRD Sections 19 and 35).
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Support;

class RateLimiter {

	const PREFIX = 'aiplugin5055_rl_';

	/**
	 * Consume one unit from a bucket.
	 *
	 * @param string $bucket Bucket name, e.g. "view" or "submit".
	 * @param int    $limit  Requests allowed per window.
	 * @param int    $window Window length in seconds.
	 * @return bool True when the request is within the limit.
	 */
	public static function consume( $bucket, $limit, $window ) {
		$limit  = (int) \apply_filters( 'aiplugin5055_rate_limit', $limit, $bucket );
		$window = (int) \apply_filters( 'aiplugin5055_rate_limit_window', $window, $bucket );

		if ( $limit <= 0 ) {
			return true;
		}

		$key   = self::PREFIX . md5( $bucket . '|' . self::client_ip() );
		$count = \get_transient( $key );

		if ( false === $count ) {
			\set_transient( $key, 1, $window );

			return true;
		}

		$count = (int) $count + 1;

		// Re-setting keeps the original window length; a client that keeps
		// hitting the endpoint simply stays blocked for the rest of it.
		\set_transient( $key, $count, $window );

		return $count <= $limit;
	}

	/**
	 * Whether a bucket is already spent, without consuming from it.
	 *
	 * Lets a caller charge only the requests it wants to limit (a run of bad
	 * codes, say) while still refusing everything once the bucket is empty.
	 *
	 * @param string $bucket Bucket name.
	 * @param int    $limit  Requests allowed per window.
	 * @return bool True when the client is over the limit.
	 */
	public static function exceeded( $bucket, $limit ) {
		$limit = (int) \apply_filters( 'aiplugin5055_rate_limit', $limit, $bucket );

		if ( $limit <= 0 ) {
			return false;
		}

		$count = \get_transient( self::PREFIX . md5( $bucket . '|' . self::client_ip() ) );

		return false !== $count && (int) $count >= $limit;
	}

	/**
	 * A stable, non-reversible identifier for the requesting client.
	 *
	 * Used as provenance on recorded actions; the raw address is never stored.
	 *
	 * @return string
	 */
	public static function client_ip_hash() {
		return substr( hash_hmac( 'sha256', self::client_ip(), Settings::secret() ), 0, 16 );
	}

	/**
	 * Best-effort client IP.
	 *
	 * @return string
	 */
	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? \wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		$ip = \apply_filters( 'aiplugin5055_client_ip', $ip );

		if ( ! is_string( $ip ) ) {
			return '';
		}

		$validated = filter_var( $ip, FILTER_VALIDATE_IP );

		return $validated ? $validated : '';
	}
}
