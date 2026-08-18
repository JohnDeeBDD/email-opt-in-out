<?php
/**
 * The user metadata keys written by this plugin (PRD Section 8).
 *
 * The authoritative record for a campaign lives under:
 *
 *   email_campaign_{CAMPAIGN_CODE}
 *
 * A second, derived key holds only the current state so that "who opted in to
 * this campaign?" is answerable with an indexed meta query instead of a scan:
 *
 *   email_campaign_{CAMPAIGN_CODE}_state
 *
 * Both are attributable to exactly one campaign by inspection of the key, with
 * no join or lookup. Campaign codes are five characters from an alphabet that
 * contains no underscore, so the two key shapes can never collide.
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Meta;

class MetaKeys {

	/** Literal prefix required by the PRD. */
	const PREFIX = 'email_campaign_';

	/** Suffix of the derived, queryable state key. */
	const STATE_SUFFIX = '_state';

	/** Provenance of accounts this plugin created (PRD Section 29). */
	const ACCOUNT_CREATED_BY       = 'aiplugin5055_account_created_by';
	const ACCOUNT_CREATED_REASON   = 'aiplugin5055_account_created_reason';
	const ACCOUNT_CREATED_CAMPAIGN = 'aiplugin5055_account_created_campaign';
	const ACCOUNT_CREATED_AT       = 'aiplugin5055_account_created_at';

	/**
	 * Authoritative per-campaign metadata key.
	 *
	 * @param string $campaign_code Campaign code in canonical case.
	 * @return string
	 */
	public static function campaign( $campaign_code ) {
		return self::PREFIX . strtoupper( (string) $campaign_code );
	}

	/**
	 * Derived per-campaign state key.
	 *
	 * @param string $campaign_code Campaign code in canonical case.
	 * @return string
	 */
	public static function campaign_state( $campaign_code ) {
		return self::campaign( $campaign_code ) . self::STATE_SUFFIX;
	}

	/**
	 * Recover the campaign code from an authoritative metadata key.
	 *
	 * @param string $meta_key Metadata key.
	 * @return string|null Campaign code, or null when the key is not one of ours.
	 */
	public static function campaign_code_from_key( $meta_key ) {
		$meta_key = (string) $meta_key;

		if ( 0 !== strpos( $meta_key, self::PREFIX ) ) {
			return null;
		}

		$suffix = substr( $meta_key, strlen( self::PREFIX ) );

		return \aiplugin5055\Campaigns\CampaignCodeGenerator::is_well_formed( $suffix ) ? $suffix : null;
	}

	/**
	 * Keep the plugin's metadata out of the default custom-fields UI.
	 *
	 * These values are the record of historical explicit actions, not editable
	 * profile preferences (PRD Section 8).
	 *
	 * @param bool   $protected Whether the key is protected.
	 * @param string $meta_key  Metadata key.
	 * @param string $meta_type Object type.
	 * @return bool
	 */
	public static function protect( $protected, $meta_key, $meta_type ) {
		if ( 'user' !== $meta_type ) {
			return $protected;
		}

		if ( 0 === strpos( (string) $meta_key, self::PREFIX ) || 0 === strpos( (string) $meta_key, 'aiplugin5055_' ) ) {
			return true;
		}

		return $protected;
	}
}
