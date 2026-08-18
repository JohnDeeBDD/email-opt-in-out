<?php
/**
 * Public action URLs (PRD Section 15).
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Support;

class Urls {

	/** Query var that selects the action. */
	const ACTION_VAR = 'aiplugin5055_action';

	/** Query parameter carrying the action code. */
	const CODE_VAR = 'c';

	const ACTION_OPT_IN  = 'opt_in';
	const ACTION_OPT_OUT = 'opt_out';

	/**
	 * Path segment for the call-to-action (opt-in) link.
	 *
	 * @return string
	 */
	public static function opt_in_slug() {
		return \sanitize_title( \apply_filters( 'aiplugin5055_opt_in_slug', 'email-action' ) );
	}

	/**
	 * Path segment for the unsubscribe link.
	 *
	 * @return string
	 */
	public static function opt_out_slug() {
		return \sanitize_title( \apply_filters( 'aiplugin5055_opt_out_slug', 'email-unsubscribe' ) );
	}

	/**
	 * Build the public URL for an action code.
	 *
	 * Falls back to a plain query string when the site is not using pretty
	 * permalinks, so the links work on any configuration.
	 *
	 * @param string $action       ACTION_OPT_IN or ACTION_OPT_OUT.
	 * @param string $action_code  The tracking code.
	 * @return string
	 */
	public static function action_url( $action, $action_code ) {
		$action = self::ACTION_OPT_OUT === $action ? self::ACTION_OPT_OUT : self::ACTION_OPT_IN;

		if ( \get_option( 'permalink_structure' ) ) {
			$slug = self::ACTION_OPT_OUT === $action ? self::opt_out_slug() : self::opt_in_slug();

			return \add_query_arg( self::CODE_VAR, $action_code, \home_url( '/' . $slug . '/' ) );
		}

		return \add_query_arg(
			array(
				self::ACTION_VAR => $action,
				self::CODE_VAR   => $action_code,
			),
			\home_url( '/' )
		);
	}

	/**
	 * Both action URLs for one code.
	 *
	 * @param string $action_code The tracking code.
	 * @return array
	 */
	public static function both( $action_code ) {
		return array(
			'opt_in_url'  => self::action_url( self::ACTION_OPT_IN, $action_code ),
			'opt_out_url' => self::action_url( self::ACTION_OPT_OUT, $action_code ),
		);
	}
}
