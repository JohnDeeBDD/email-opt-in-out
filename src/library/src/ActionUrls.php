<?php
/**
 * The public action URLs derived from an action code (PRD Section 15).
 *
 * The plugin decides *where* those URLs point — that needs WordPress, and it
 * differs per site — but *what* is appended to them is fixed, and getting it
 * wrong is silent: a link that reaches the site without the action parameter
 * is not recognised as an unsubscribe request at all, and the recipient is
 * shown an ordinary page instead of the confirmation form.
 *
 * So the query assembly lives here, once, and both the plugin and the campaign
 * manager build their links through it.
 *
 * The opt-out URL always carries the action parameter, even when the site is
 * served through the rewrite rule that would have supplied it: WordPress reads
 * a registered public query var from `$_GET` in preference to the rewritten
 * query, both give the same value, and including it is the only form that also
 * works when the flow is served from a configured page or from a plain query
 * string.
 *
 * @package aiplugin5055\Library
 */

namespace aiplugin5055\Library;

use InvalidArgumentException;

final class ActionUrls {

	/** Query var that selects the action. */
	const ACTION_VAR = 'aiplugin5055_action';

	/** Query parameter carrying the action code. */
	const CODE_VAR = 'c';

	const ACTION_OPT_IN  = 'opt_in';
	const ACTION_OPT_OUT = 'opt_out';

	/**
	 * The call-to-action link.
	 *
	 * Opt-in has no endpoint of its own: the code may ride on any URL of the
	 * site and the visit itself records the opt-in, so nothing but the code is
	 * appended.
	 *
	 * @param string $destination_url Where the recipient should land.
	 * @param string $action_code     The action code.
	 * @return string
	 */
	public static function opt_in_url( $destination_url, $action_code ) {
		return self::with_query(
			$destination_url,
			array( self::CODE_VAR => self::assert_code( $action_code ) )
		);
	}

	/**
	 * The unsubscribe link.
	 *
	 * @param string $endpoint_url Unsubscribe page, endpoint or home URL.
	 * @param string $action_code  The action code.
	 * @return string
	 */
	public static function opt_out_url( $endpoint_url, $action_code ) {
		return self::with_query(
			$endpoint_url,
			array(
				self::ACTION_VAR => self::ACTION_OPT_OUT,
				self::CODE_VAR   => self::assert_code( $action_code ),
			)
		);
	}

	/**
	 * Both links for one code.
	 *
	 * @param string $opt_in_destination Where the call to action lands.
	 * @param string $opt_out_endpoint   Where the unsubscribe flow is served.
	 * @param string $action_code        The action code.
	 * @return array{opt_in_url: string, opt_out_url: string}
	 */
	public static function both( $opt_in_destination, $opt_out_endpoint, $action_code ) {
		return array(
			'opt_in_url'  => self::opt_in_url( $opt_in_destination, $action_code ),
			'opt_out_url' => self::opt_out_url( $opt_out_endpoint, $action_code ),
		);
	}

	/**
	 * Add or replace query arguments on a URL, keeping any fragment.
	 *
	 * Mirrors what `add_query_arg()` does — existing parameters keep their
	 * position, a repeated key is replaced rather than duplicated — so the
	 * plugin's links do not change shape now that it builds them through here.
	 *
	 * @param string $url  Base URL.
	 * @param array  $args Parameters to add or replace, in order.
	 * @return string
	 */
	public static function with_query( $url, array $args ) {
		$url = (string) $url;

		if ( '' === $url ) {
			throw new InvalidArgumentException( 'Cannot build an action URL from an empty URL.' );
		}

		$fragment = '';
		$hash     = strpos( $url, '#' );

		if ( false !== $hash ) {
			$fragment = substr( $url, $hash );
			$url      = substr( $url, 0, $hash );
		}

		$query    = '';
		$question = strpos( $url, '?' );

		if ( false !== $question ) {
			$query = substr( $url, $question + 1 );
			$url   = substr( $url, 0, $question );
		}

		$existing = array();

		if ( '' !== $query ) {
			parse_str( $query, $existing );
		}

		// array_merge keeps the position of a key that is already present and
		// appends the rest in the order given.
		$merged = array_merge( $existing, $args );
		$built  = http_build_query( $merged );

		return $url . ( '' === $built ? '' : '?' . $built ) . $fragment;
	}

	/**
	 * @param string $action_code Candidate code.
	 * @return string
	 * @throws InvalidArgumentException When there is no code to put in the link.
	 */
	private static function assert_code( $action_code ) {
		$action_code = (string) $action_code;

		if ( '' === $action_code ) {
			throw new InvalidArgumentException( 'Cannot build an action URL for an empty action code.' );
		}

		return $action_code;
	}
}
