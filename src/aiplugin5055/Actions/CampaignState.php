<?php
/**
 * Reading recorded per-campaign state (PRD Sections 12 and 38).
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Actions;

use aiplugin5055\Campaigns\CampaignRepository;
use aiplugin5055\Meta\MetaKeys;

class CampaignState {

	const OPTED_IN  = 'OPTED_IN';
	const OPTED_OUT = 'OPTED_OUT';

	/** No metadata entry exists. Neither consent nor refusal. */
	const NO_RECORD = 'NO_RECORD';

	/**
	 * Current state of one user for one campaign.
	 *
	 * @param int    $user_id       User ID.
	 * @param string $campaign_code Campaign code.
	 * @return string One of the class constants.
	 */
	public static function for_user( $user_id, $campaign_code ) {
		$record = self::record_for_user( $user_id, $campaign_code );

		if ( ! $record || empty( $record['state'] ) ) {
			return self::NO_RECORD;
		}

		return in_array( $record['state'], array( self::OPTED_IN, self::OPTED_OUT ), true )
			? $record['state']
			: self::NO_RECORD;
	}

	/**
	 * The full authoritative record for one user and one campaign.
	 *
	 * @param int    $user_id       User ID.
	 * @param string $campaign_code Campaign code.
	 * @return array|null
	 */
	public static function record_for_user( $user_id, $campaign_code ) {
		$record = \get_user_meta( (int) $user_id, MetaKeys::campaign( $campaign_code ), true );

		return is_array( $record ) ? $record : null;
	}

	/**
	 * Every campaign this user has explicitly acted on.
	 *
	 * @param int $user_id User ID.
	 * @return array[] Records keyed by campaign code.
	 */
	public static function records_for_user( $user_id ) {
		$all     = \get_user_meta( (int) $user_id );
		$records = array();

		if ( ! is_array( $all ) ) {
			return $records;
		}

		foreach ( $all as $meta_key => $values ) {
			$campaign_code = MetaKeys::campaign_code_from_key( $meta_key );

			if ( null === $campaign_code ) {
				continue;
			}

			$value = is_array( $values ) && isset( $values[0] ) ? \maybe_unserialize( $values[0] ) : null;

			if ( is_array( $value ) ) {
				$records[ $campaign_code ] = $value;
			}
		}

		ksort( $records );

		return $records;
	}

	/**
	 * Derived cross-campaign summary (PRD Section 12).
	 *
	 * The per-campaign entries remain the authoritative record; this is a
	 * convenience view over them.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function summary_for_user( $user_id ) {
		$records    = self::records_for_user( $user_id );
		$opted_in   = array();
		$opted_out  = array();
		$campaigns  = array();

		foreach ( $records as $campaign_code => $record ) {
			$state    = isset( $record['state'] ) ? $record['state'] : self::NO_RECORD;
			$campaign = CampaignRepository::get( $campaign_code );

			if ( self::OPTED_IN === $state ) {
				$opted_in[] = $campaign_code;
			} elseif ( self::OPTED_OUT === $state ) {
				$opted_out[] = $campaign_code;
			}

			$campaigns[] = array(
				'campaign_code'   => $campaign_code,
				'campaign_name'   => $campaign ? $campaign['name'] : ( isset( $record['campaign_name'] ) ? $record['campaign_name'] : '' ),
				'campaign_exists' => (bool) $campaign,
				'meta_key'        => MetaKeys::campaign( $campaign_code ),
				'state'           => $state,
				'opt_in'          => isset( $record['opt_in'] ) ? $record['opt_in'] : null,
				'opt_out'         => isset( $record['opt_out'] ) ? $record['opt_out'] : null,
				'history'         => isset( $record['history'] ) ? $record['history'] : array(),
			);
		}

		return array(
			'opted_in'  => $opted_in,
			'opted_out' => $opted_out,
			'campaigns' => $campaigns,
		);
	}

	/**
	 * How many users hold a given state for a campaign.
	 *
	 * @param string $campaign_code Campaign code.
	 * @param string $state         OPTED_IN or OPTED_OUT.
	 * @return int
	 */
	public static function count_users( $campaign_code, $state ) {
		$query = new \WP_User_Query(
			array(
				'meta_key'    => MetaKeys::campaign_state( $campaign_code ),
				'meta_value'  => $state,
				'fields'      => 'ID',
				'number'      => 1,
				'count_total' => true,
			)
		);

		return (int) $query->get_total();
	}

	/**
	 * Users holding a given state for a campaign.
	 *
	 * @param string $campaign_code Campaign code.
	 * @param string $state         OPTED_IN or OPTED_OUT.
	 * @param int    $number        Page size.
	 * @param int    $offset        Offset.
	 * @return \WP_User[]
	 */
	public static function users_with_state( $campaign_code, $state, $number = 100, $offset = 0 ) {
		$query = new \WP_User_Query(
			array(
				'meta_key'    => MetaKeys::campaign_state( $campaign_code ),
				'meta_value'  => $state,
				'number'      => (int) $number,
				'offset'      => (int) $offset,
				'orderby'     => 'ID',
				'order'       => 'ASC',
				'count_total' => false,
			)
		);

		return $query->get_results();
	}
}
