<?php
/**
 * Public action URLs (PRD Section 15).
 *
 * This class decides *where* an action link points, which needs WordPress and
 * differs per site. What gets appended to it — the code, and for opt-out the
 * action parameter — is the shared library's job, because the campaign manager
 * builds the very same links from outside WordPress and a link that arrives
 * without its action parameter is not recognised as an unsubscribe request at
 * all.
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Support;

use aiplugin5055\Library\ActionUrls;
use aiplugin5055\Support\Settings;

require_once __DIR__ . '/../../library/autoload.php';

class Urls {

	/** Query var that selects the action. */
	const ACTION_VAR = ActionUrls::ACTION_VAR;

	/** Query parameter carrying the action code. */
	const CODE_VAR = ActionUrls::CODE_VAR;

	const ACTION_OPT_IN  = ActionUrls::ACTION_OPT_IN;
	const ACTION_OPT_OUT = ActionUrls::ACTION_OPT_OUT;

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
	 * Where the unsubscribe flow is served from on this site.
	 *
	 * Three configurations, in order of preference: the configured WordPress
	 * page, the custom rewrite rule, and — on a site without pretty permalinks
	 * — the home URL carrying the action as a plain query parameter.
	 *
	 * The campaign manager cannot work this out from outside WordPress, which
	 * is why the operator copies the answer into its configuration; this is the
	 * value they should copy.
	 *
	 * @return string
	 */
	public static function opt_out_endpoint() {
		$page_id = Settings::opt_out_page();

		if ( $page_id > 0 && \get_post_status( $page_id ) === 'publish' ) {
			$permalink = \get_permalink( $page_id );

			if ( \is_string( $permalink ) && '' !== $permalink ) {
				return $permalink;
			}
		}

		if ( \get_option( 'permalink_structure' ) ) {
			return \home_url( '/' . self::opt_out_slug() . '/' );
		}

		return \home_url( '/' );
	}

	/**
	 * Build the public URL for an action code.
	 *
	 * @param string $action       ACTION_OPT_IN or ACTION_OPT_OUT.
	 * @param string $action_code  The tracking code.
	 * @return string
	 */
	public static function action_url( $action, $action_code ) {
		if ( self::ACTION_OPT_OUT !== $action ) {
			return ActionUrls::opt_in_url( self::opt_in_destination(), $action_code );
		}

		return ActionUrls::opt_out_url( self::opt_out_endpoint(), $action_code );
	}

	/**
	 * Both action URLs for one code.
	 *
	 * @param string $action_code The tracking code.
	 * @return array
	 */
	public static function both( $action_code ) {
		return ActionUrls::both( self::opt_in_destination(), self::opt_out_endpoint(), $action_code );
	}
}
