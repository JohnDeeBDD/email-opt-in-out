<?php
/**
 * Storage for campaign records (PRD Sections 13 and 14).
 *
 * Campaigns live in two options rather than a custom table:
 *
 *   aiplugin5055_campaigns    the live records, keyed by campaign code
 *   aiplugin5055_issued_codes every code ever issued, so that a deleted
 *                             campaign's code is never handed out again
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Campaigns;

class CampaignRepository {

	const OPTION_CAMPAIGNS = 'aiplugin5055_campaigns';
	const OPTION_ISSUED    = 'aiplugin5055_issued_codes';

	const STATUS_ACTIVE   = 'active';
	const STATUS_DISABLED = 'disabled';

	/**
	 * All campaigns, newest first.
	 *
	 * @return array[] List of campaign records.
	 */
	public static function all() {
		$campaigns = \get_option( self::OPTION_CAMPAIGNS, array() );

		if ( ! is_array( $campaigns ) ) {
			return array();
		}

		$campaigns = array_values( array_filter( $campaigns, 'is_array' ) );

		usort(
			$campaigns,
			function ( $a, $b ) {
				$left  = isset( $a['created_at'] ) ? (string) $a['created_at'] : '';
				$right = isset( $b['created_at'] ) ? (string) $b['created_at'] : '';

				return strcmp( $right, $left );
			}
		);

		return $campaigns;
	}

	/**
	 * One campaign by code.
	 *
	 * @param string $code Campaign code.
	 * @return array|null
	 */
	public static function get( $code ) {
		$code      = strtoupper( (string) $code );
		$campaigns = \get_option( self::OPTION_CAMPAIGNS, array() );

		if ( is_array( $campaigns ) && isset( $campaigns[ $code ] ) && is_array( $campaigns[ $code ] ) ) {
			return $campaigns[ $code ];
		}

		return null;
	}

	/**
	 * Whether a campaign code may authorize a public action.
	 *
	 * Unknown, deleted and disabled codes are all unusable (PRD Section 13).
	 *
	 * @param string $code Campaign code.
	 * @return bool
	 */
	public static function is_usable( $code ) {
		$campaign = self::get( $code );

		return $campaign && isset( $campaign['status'] ) && self::STATUS_ACTIVE === $campaign['status'];
	}

	/**
	 * Create a campaign. The administrator supplies only the name.
	 *
	 * @param string $name Human-readable campaign name.
	 * @return array|\WP_Error The stored record.
	 */
	public static function create( $name ) {
		$name = \sanitize_text_field( (string) $name );
		$name = trim( $name );

		if ( '' === $name ) {
			return new \WP_Error(
				'aiplugin5055_missing_name',
				\__( 'A campaign name is required.', 'aiplugin5055' ),
				array( 'status' => 400 )
			);
		}

		$code = CampaignCodeGenerator::generate_unique(
			function ( $candidate ) {
				return self::has_ever_been_issued( $candidate );
			}
		);

		if ( null === $code ) {
			return new \WP_Error(
				'aiplugin5055_code_generation_failed',
				\__( 'Could not generate a unique campaign code. Please try again.', 'aiplugin5055' ),
				array( 'status' => 500 )
			);
		}

		$campaign = array(
			'campaign_code' => $code,
			'name'          => $name,
			'created_at'    => \gmdate( 'c' ),
			'status'        => self::STATUS_ACTIVE,
		);

		$campaigns          = self::raw();
		$campaigns[ $code ] = $campaign;

		self::save( $campaigns );
		self::remember_issued_code( $code );

		/**
		 * Fires after a campaign has been created.
		 *
		 * @param array $campaign The stored record.
		 */
		\do_action( 'aiplugin5055_campaign_created', $campaign );

		return $campaign;
	}

	/**
	 * Update the mutable fields of a campaign.
	 *
	 * The campaign code is immutable: changing it would orphan every
	 * email_campaign_{CAMPAIGN_CODE} metadata entry already recorded.
	 *
	 * @param string $code    Campaign code.
	 * @param array  $changes Accepts 'name' and 'status'.
	 * @return array|\WP_Error The stored record.
	 */
	public static function update( $code, array $changes ) {
		$code     = strtoupper( (string) $code );
		$campaign = self::get( $code );

		if ( ! $campaign ) {
			return new \WP_Error(
				'aiplugin5055_unknown_campaign',
				\__( 'Unknown campaign.', 'aiplugin5055' ),
				array( 'status' => 404 )
			);
		}

		if ( array_key_exists( 'name', $changes ) ) {
			$name = trim( \sanitize_text_field( (string) $changes['name'] ) );

			if ( '' === $name ) {
				return new \WP_Error(
					'aiplugin5055_missing_name',
					\__( 'A campaign name is required.', 'aiplugin5055' ),
					array( 'status' => 400 )
				);
			}

			$campaign['name'] = $name;
		}

		if ( array_key_exists( 'status', $changes ) ) {
			$status = \sanitize_key( (string) $changes['status'] );

			if ( ! in_array( $status, array( self::STATUS_ACTIVE, self::STATUS_DISABLED ), true ) ) {
				return new \WP_Error(
					'aiplugin5055_invalid_status',
					\__( 'Status must be active or disabled.', 'aiplugin5055' ),
					array( 'status' => 400 )
				);
			}

			$campaign['status'] = $status;
		}

		$campaigns          = self::raw();
		$campaigns[ $code ] = $campaign;

		self::save( $campaigns );

		/**
		 * Fires after a campaign has been updated.
		 *
		 * @param array $campaign The stored record.
		 */
		\do_action( 'aiplugin5055_campaign_updated', $campaign );

		return $campaign;
	}

	/**
	 * Delete a campaign record.
	 *
	 * Only the record goes. Every email_campaign_{CAMPAIGN_CODE} user metadata
	 * entry survives, because those entries are the historical record of
	 * explicit recipient decisions (PRD Invariant 10).
	 *
	 * @param string $code Campaign code.
	 * @return true|\WP_Error
	 */
	public static function delete( $code ) {
		$code     = strtoupper( (string) $code );
		$campaign = self::get( $code );

		if ( ! $campaign ) {
			return new \WP_Error(
				'aiplugin5055_unknown_campaign',
				\__( 'Unknown campaign.', 'aiplugin5055' ),
				array( 'status' => 404 )
			);
		}

		$campaigns = self::raw();
		unset( $campaigns[ $code ] );

		self::save( $campaigns );

		// The code stays on the issued list forever so it can never be reissued.
		self::remember_issued_code( $code );

		/**
		 * Fires after a campaign record has been deleted.
		 *
		 * @param array $campaign The record as it was before deletion.
		 */
		\do_action( 'aiplugin5055_campaign_deleted', $campaign );

		return true;
	}

	/**
	 * Whether a code has ever been issued, including to deleted campaigns.
	 *
	 * @param string $code Candidate code.
	 * @return bool
	 */
	public static function has_ever_been_issued( $code ) {
		$issued = \get_option( self::OPTION_ISSUED, array() );

		return is_array( $issued ) && in_array( strtoupper( (string) $code ), $issued, true );
	}

	/**
	 * Every code ever issued on this site.
	 *
	 * @return string[]
	 */
	public static function issued_codes() {
		$issued = \get_option( self::OPTION_ISSUED, array() );

		return is_array( $issued ) ? $issued : array();
	}

	/**
	 * @param string $code Campaign code.
	 * @return void
	 */
	private static function remember_issued_code( $code ) {
		$code   = strtoupper( (string) $code );
		$issued = self::issued_codes();

		if ( ! in_array( $code, $issued, true ) ) {
			$issued[] = $code;
			\update_option( self::OPTION_ISSUED, $issued, false );
		}
	}

	/**
	 * @return array Campaign records keyed by code.
	 */
	private static function raw() {
		$campaigns = \get_option( self::OPTION_CAMPAIGNS, array() );

		return is_array( $campaigns ) ? $campaigns : array();
	}

	/**
	 * @param array $campaigns Campaign records keyed by code.
	 * @return void
	 */
	private static function save( array $campaigns ) {
		\update_option( self::OPTION_CAMPAIGNS, $campaigns, true );
	}
}
