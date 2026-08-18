<?php
/**
 * Public action URLs (PRD Section 15).
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Support;

use aiplugin5055\Support\Settings;

class Urls {

	/** Query var that selects the action. */
	const ACTION_VAR = 'aiplugin5055_action';

	/** Query parameter carrying the action code. */
	const CODE_VAR = 'c';

	const ACTION_OPT_IN  = 'opt_in';
	const ACTION_OPT_OUT = 'opt_out';

	/**
	 * Path segment for the unsubscribe link.
	 *
	 * @return string
	 */
	public static function opt_out_slug() {
		return \sanitize_title( \apply_filters( 'aiplugin5055_opt_out_slug', 'email-unsubscribe' ) );
	}

	/**
	 * Where a call-to-action link should land.
	 *
	 * Opt-in has no page of its own: the code may ride on any URL of the site,
	 * and the visit records the opt-in silently. The site's front page is only
	 * the default destination — filter it to send a campaign's recipients to a
	 * landing page, or simply append the code parameter to any URL by hand.
	 *
	 * @return string
	 */
	public static function opt_in_destination() {
		$destination = \apply_filters( 'aiplugin5055_opt_in_destination', \home_url( '/' ) );

		return \is_string( $destination ) && '' !== $destination ? $destination : \home_url( '/' );
	}

	/**
	 * Build the public URL for an action code.
	 *
	 * The opt-in URL is the campaign's destination with the code appended; the
	 * opt-out URL is the unsubscribe page if one is configured, and the custom
	 * endpoint otherwise.
	 *
	 * @param string $action       ACTION_OPT_IN or ACTION_OPT_OUT.
	 * @param string $action_code  The tracking code.
	 * @return string
	 */
	public static function action_url( $action, $action_code ) {
		if ( self::ACTION_OPT_OUT !== $action ) {
			return \add_query_arg( self::CODE_VAR, $action_code, self::opt_in_destination() );
		}

		$page_id = Settings::opt_out_page();

		if ( $page_id > 0 && \get_post_status( $page_id ) === 'publish' ) {
			// Use the configured WordPress page.
			return \add_query_arg(
				array(
					self::ACTION_VAR => self::ACTION_OPT_OUT,
					self::CODE_VAR   => $action_code,
				),
				\get_permalink( $page_id )
			);
		}

		// Fall back to the custom rewrite rule.
		if ( \get_option( 'permalink_structure' ) ) {
			return \add_query_arg( self::CODE_VAR, $action_code, \home_url( '/' . self::opt_out_slug() . '/' ) );
		}

		// Fall back to a plain query string.
		return \add_query_arg(
			array(
				self::ACTION_VAR => self::ACTION_OPT_OUT,
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
