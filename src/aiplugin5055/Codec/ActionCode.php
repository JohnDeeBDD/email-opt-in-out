<?php
/**
 * The action code — the tracking code that travels in the campaign email.
 *
 *   [ 5-character campaign code ][ encoded email address ][ check value ]
 *
 * The construction lives in `aiplugin5055\Library\ActionCode`, because the
 * Gmail Campaign Manager has to produce byte-identical codes and does it by
 * loading that library from this plugin's directory rather than by keeping a
 * copy. What this class adds is the WordPress half: where the settings come
 * from, and which address validator applies.
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Codec;

use aiplugin5055\Library\ActionCode as Library;
use aiplugin5055\Support\Settings;

require_once __DIR__ . '/../../../library/autoload.php';

class ActionCode {

	/** Fixed width of the campaign-code prefix. */
	const CAMPAIGN_CODE_LENGTH = Library::CAMPAIGN_CODE_LENGTH;

	/** Defensive upper bound on an accepted code. */
	const MAX_LENGTH = Library::MAX_LENGTH;

	/**
	 * Build the action code for one recipient of one campaign.
	 *
	 * Pure transformation: nothing is stored and no user is created
	 * (PRD Sections 5 and 17).
	 *
	 * @param string $campaign_code Five-character campaign code.
	 * @param string $email         Recipient address, any casing.
	 * @return string
	 * @throws \InvalidArgumentException When the campaign code or address is unusable.
	 */
	public static function build( $campaign_code, $email ) {
		return Library::build( $campaign_code, $email, Settings::site_settings() );
	}

	/**
	 * Parse an action code back into a campaign code and an email address.
	 *
	 * Performs every check that does not need the campaign record; the caller
	 * is responsible for confirming that the campaign exists and is active.
	 * WordPress's own `is_email()` decides what counts as an address, so the
	 * endpoint accepts exactly the addresses the rest of the site does.
	 *
	 * @param string $raw Code as it arrived from the request.
	 * @return array {
	 *     @type bool   $ok            Whether the code is well formed.
	 *     @type string $reason        Machine-readable failure reason.
	 *     @type string $campaign_code Campaign code (on success).
	 *     @type string $email         Canonical address (on success).
	 * }
	 */
	public static function parse( $raw ) {
		return Library::parse( $raw, Settings::site_settings(), 'is_email' );
	}
}
