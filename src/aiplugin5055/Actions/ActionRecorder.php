<?php
/**
 * Records an explicit opt-in or opt-out (PRD Sections 9, 10, 11, 29, 30, 37).
 *
 * This is the only place in the plugin that writes user data, and the only
 * effect it is capable of producing is: find or create the user behind the
 * decoded address, then set the opt-in/opt-out metadata of the single campaign
 * named in the action code.
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Actions;

use aiplugin5055\Meta\MetaKeys;
use aiplugin5055\Users\UserProvisioner;

class ActionRecorder {

	const ACTION_OPT_IN  = 'opt_in';
	const ACTION_OPT_OUT = 'opt_out';

	/** Cap on retained history entries per campaign. */
	const HISTORY_LIMIT = 50;

	/**
	 * Record an explicit action.
	 *
	 * @param string $email    Canonical address decoded from the action code.
	 * @param array  $campaign Campaign record the code authorized.
	 * @param string $action   self::ACTION_OPT_IN or self::ACTION_OPT_OUT.
	 * @param array  $context  Provenance details (mechanism, reason, endpoint, ip_hash...).
	 * @return array|\WP_Error {
	 *     @type int    $user_id User the action was recorded against.
	 *     @type bool   $created Whether the action created the account.
	 *     @type string $state   Resulting per-campaign state.
	 * }
	 */
	public static function record( $email, array $campaign, $action, array $context = array() ) {
		if ( ! in_array( $action, array( self::ACTION_OPT_IN, self::ACTION_OPT_OUT ), true ) ) {
			return new \WP_Error( 'aiplugin5055_invalid_action', \__( 'Unsupported action.', 'aiplugin5055' ) );
		}

		$campaign_code = strtoupper( (string) $campaign['campaign_code'] );

		$resolved = UserProvisioner::find_or_create( $email );

		if ( \is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$user_id = $resolved['user_id'];
		$created = $resolved['created'];
		$state   = self::ACTION_OPT_IN === $action ? CampaignState::OPTED_IN : CampaignState::OPTED_OUT;

		$record = self::build_record( $user_id, $campaign, $action, $state, $created, $context );

		$written = self::write( $user_id, $campaign_code, $record, $state );

		if ( ! $written ) {
			// User creation and the metadata write are one logical operation
			// (PRD Section 37). Roll the account back rather than leaving a
			// user with no known state behind.
			if ( $created ) {
				self::rollback_user( $user_id );
			}

			return new \WP_Error(
				'aiplugin5055_write_failed',
				\__( 'The action could not be recorded.', 'aiplugin5055' )
			);
		}

		if ( $created ) {
			self::record_account_provenance( $user_id, $campaign_code, $action );
		}

		/**
		 * Fires after an explicit opt-in or opt-out has been recorded.
		 *
		 * @param int    $user_id       User the action was recorded against.
		 * @param string $campaign_code Campaign the action belongs to.
		 * @param string $action        opt_in or opt_out.
		 * @param bool   $created       Whether the action created the account.
		 */
		\do_action( 'aiplugin5055_action_recorded', $user_id, $campaign_code, $action, $created );

		return array(
			'user_id' => $user_id,
			'created' => $created,
			'state'   => $state,
		);
	}

	/**
	 * Merge the new action into the campaign's existing record.
	 *
	 * Nothing is ever cleared: an opt-out keeps the historical opt-in, and a
	 * later opt-in keeps the historical opt-out (PRD Section 11).
	 *
	 * @param int    $user_id  User ID.
	 * @param array  $campaign Campaign record.
	 * @param string $action   opt_in or opt_out.
	 * @param string $state    Resulting state.
	 * @param bool   $created  Whether this action created the account.
	 * @param array  $context  Provenance details.
	 * @return array
	 */
	private static function build_record( $user_id, array $campaign, $action, $state, $created, array $context ) {
		$campaign_code = strtoupper( (string) $campaign['campaign_code'] );
		$now           = \gmdate( 'c' );

		$record = CampaignState::record_for_user( $user_id, $campaign_code );
		$record = is_array( $record ) ? $record : array();

		$previous_state = isset( $record['state'] ) ? $record['state'] : CampaignState::NO_RECORD;

		$entry = isset( $record[ $action ] ) && is_array( $record[ $action ] ) ? $record[ $action ] : array();

		if ( empty( $entry['first_timestamp'] ) ) {
			$entry['first_timestamp'] = $now;
			$entry['count']           = 0;
		}

		$entry['timestamp'] = $now;
		$entry['count']     = ( isset( $entry['count'] ) ? (int) $entry['count'] : 0 ) + 1;
		$entry['reason']    = isset( $context['reason'] ) && '' !== $context['reason']
			? (string) $context['reason']
			: ( self::ACTION_OPT_IN === $action
				? \__( 'Recipient explicitly opted in', 'aiplugin5055' )
				: \__( 'Recipient explicitly unsubscribed', 'aiplugin5055' ) );
		$entry['source']    = isset( $context['source'] ) ? (string) $context['source'] : 'email_campaign';
		$entry['details']   = self::details( $campaign, $context );

		$record['campaign_code'] = $campaign_code;
		$record['campaign_name'] = isset( $campaign['name'] ) ? (string) $campaign['name'] : '';
		$record['state']         = $state;
		$record[ $action ]       = $entry;
		$record['updated_at']    = $now;

		if ( empty( $record['first_recorded_at'] ) ) {
			$record['first_recorded_at'] = $now;
		}

		// Keep the opposing action key present so the shape of the record is
		// predictable for readers.
		$opposite = self::ACTION_OPT_IN === $action ? self::ACTION_OPT_OUT : self::ACTION_OPT_IN;

		if ( ! isset( $record[ $opposite ] ) ) {
			$record[ $opposite ] = null;
		}

		if ( $previous_state !== $state ) {
			$record['history'] = self::append_history(
				isset( $record['history'] ) && is_array( $record['history'] ) ? $record['history'] : array(),
				array(
					'action'         => $action,
					'state'          => $state,
					'previous_state' => $previous_state,
					'timestamp'      => $now,
					'source'         => $entry['source'],
					'created_user'   => (bool) $created,
				)
			);
		}

		if ( $created ) {
			$record['account_created_by']     = 'email_action';
			$record['account_created_reason'] = $action;
			$record['account_created_at']     = $now;
		}

		return $record;
	}

	/**
	 * Provenance details attached to an action.
	 *
	 * @param array $campaign Campaign record.
	 * @param array $context  Caller-supplied context.
	 * @return array
	 */
	private static function details( array $campaign, array $context ) {
		$details = array(
			'campaign_name' => isset( $campaign['name'] ) ? (string) $campaign['name'] : '',
			'mechanism'     => isset( $context['mechanism'] ) ? (string) $context['mechanism'] : 'public_action_page',
		);

		foreach ( array( 'endpoint', 'ip_hash', 'original_list' ) as $key ) {
			if ( isset( $context[ $key ] ) && '' !== $context[ $key ] ) {
				$details[ $key ] = (string) $context[ $key ];
			}
		}

		return $details;
	}

	/**
	 * Append to the per-campaign history, keeping the first entry when the
	 * cap is reached so the origin of the record is never lost.
	 *
	 * @param array $history Existing history.
	 * @param array $entry   New entry.
	 * @return array
	 */
	private static function append_history( array $history, array $entry ) {
		$history[] = $entry;

		if ( count( $history ) > self::HISTORY_LIMIT ) {
			$first   = array_shift( $history );
			$history = array_slice( $history, -( self::HISTORY_LIMIT - 1 ) );
			array_unshift( $history, $first );
		}

		return $history;
	}

	/**
	 * Persist the record and its derived state key, then read back to confirm.
	 *
	 * @param int    $user_id       User ID.
	 * @param string $campaign_code Campaign code.
	 * @param array  $record        Record to store.
	 * @param string $state         Resulting state.
	 * @return bool
	 */
	private static function write( $user_id, $campaign_code, array $record, $state ) {
		\update_user_meta( $user_id, MetaKeys::campaign( $campaign_code ), $record );
		\update_user_meta( $user_id, MetaKeys::campaign_state( $campaign_code ), $state );

		// update_user_meta() returns false for an unchanged value, so the write
		// is verified by reading it back instead.
		$stored       = \get_user_meta( $user_id, MetaKeys::campaign( $campaign_code ), true );
		$stored_state = \get_user_meta( $user_id, MetaKeys::campaign_state( $campaign_code ), true );

		return is_array( $stored )
			&& isset( $stored['state'] )
			&& $stored['state'] === $state
			&& $stored_state === $state;
	}

	/**
	 * Record why an account exists (PRD Section 29).
	 *
	 * @param int    $user_id       User ID.
	 * @param string $campaign_code Campaign that caused the account.
	 * @param string $action        opt_in or opt_out.
	 * @return void
	 */
	private static function record_account_provenance( $user_id, $campaign_code, $action ) {
		\update_user_meta( $user_id, MetaKeys::ACCOUNT_CREATED_BY, 'email_action' );
		\update_user_meta( $user_id, MetaKeys::ACCOUNT_CREATED_REASON, $action );
		\update_user_meta( $user_id, MetaKeys::ACCOUNT_CREATED_CAMPAIGN, $campaign_code );
		\update_user_meta( $user_id, MetaKeys::ACCOUNT_CREATED_AT, \gmdate( 'c' ) );
	}

	/**
	 * Remove an account this call had just created, after a failed write.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private static function rollback_user( $user_id ) {
		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		if ( \is_multisite() ) {
			\wpmu_delete_user( $user_id );

			return;
		}

		\wp_delete_user( $user_id );
	}
}
