<?php
/**
 * Read access to recorded per-campaign state (PRD Sections 17 and 38).
 *
 * Responses carry recipient email addresses, which are personal data, so every
 * route is administrator-only.
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Rest;

use aiplugin5055\Actions\CampaignState;
use aiplugin5055\Campaigns\CampaignRepository;
use aiplugin5055\Codec\EmailCodec;
use aiplugin5055\Meta\MetaKeys;

class StateController {

	/**
	 * @return void
	 */
	public function register_routes() {
		\register_rest_route(
			Permissions::NAMESPACE_V1,
			'/campaigns/' . CampaignsController::CODE_PATTERN . '/recipients',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'recipients' ),
				'permission_callback' => array( Permissions::class, 'require_admin' ),
				'args'                => array(
					'state'    => array(
						'type'    => 'string',
						'enum'    => array( CampaignState::OPTED_IN, CampaignState::OPTED_OUT ),
						'default' => CampaignState::OPTED_IN,
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 100,
						'minimum' => 1,
						'maximum' => 500,
					),
				),
			)
		);

		\register_rest_route(
			Permissions::NAMESPACE_V1,
			'/recipients/state',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'recipient_state' ),
				'permission_callback' => array( Permissions::class, 'require_admin' ),
				'args'                => array(
					'email' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);
	}

	/**
	 * Which users hold a given state for a campaign.
	 *
	 * The campaign record may have been deleted; the recorded decisions
	 * survive it, so this reports on the metadata rather than the campaign.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function recipients( $request ) {
		$code     = strtoupper( $request['campaign_code'] );
		$state    = $request['state'];
		$per_page = (int) $request['per_page'];
		$offset   = ( (int) $request['page'] - 1 ) * $per_page;

		$users      = CampaignState::users_with_state( $code, $state, $per_page, $offset );
		$recipients = array();

		foreach ( $users as $user ) {
			$record = CampaignState::record_for_user( $user->ID, $code );

			$recipients[] = array(
				'user_id'    => (int) $user->ID,
				'email'      => $user->user_email,
				'state'      => $state,
				'opt_in'     => isset( $record['opt_in'] ) ? $record['opt_in'] : null,
				'opt_out'    => isset( $record['opt_out'] ) ? $record['opt_out'] : null,
				'updated_at' => isset( $record['updated_at'] ) ? $record['updated_at'] : '',
			);
		}

		$campaign = CampaignRepository::get( $code );

		return new \WP_REST_Response(
			array(
				'campaign_code'   => $code,
				'campaign_name'   => $campaign ? $campaign['name'] : '',
				'campaign_exists' => (bool) $campaign,
				'meta_key'        => MetaKeys::campaign( $code ),
				'state'           => $state,
				'total'           => CampaignState::count_users( $code, $state ),
				'recipients'      => $recipients,
			),
			200
		);
	}

	/**
	 * Everything recorded about one address.
	 *
	 * An address with no WordPress user has taken no explicit action; that is
	 * reported as such, and never as an opt-out.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function recipient_state( $request ) {
		$email = EmailCodec::canonicalize( $request['email'] );

		if ( ! \is_email( $email ) ) {
			return new \WP_Error(
				'aiplugin5055_invalid_email',
				\__( 'A valid email address is required.', 'aiplugin5055' ),
				array( 'status' => 400 )
			);
		}

		$user = \get_user_by( 'email', $email );

		if ( ! $user ) {
			return new \WP_REST_Response(
				array(
					'email'      => $email,
					'user_id'    => null,
					'user_exists' => false,
					'opted_in'   => array(),
					'opted_out'  => array(),
					'campaigns'  => array(),
					'note'       => \__( 'No WordPress user exists for this address, so no explicit action has ever been recorded.', 'aiplugin5055' ),
				),
				200
			);
		}

		$summary = CampaignState::summary_for_user( $user->ID );

		return new \WP_REST_Response(
			array_merge(
				array(
					'email'       => $user->user_email,
					'user_id'     => (int) $user->ID,
					'user_exists' => true,
					'account_created_by'       => \get_user_meta( $user->ID, MetaKeys::ACCOUNT_CREATED_BY, true ),
					'account_created_reason'   => \get_user_meta( $user->ID, MetaKeys::ACCOUNT_CREATED_REASON, true ),
					'account_created_campaign' => \get_user_meta( $user->ID, MetaKeys::ACCOUNT_CREATED_CAMPAIGN, true ),
					'account_created_at'       => \get_user_meta( $user->ID, MetaKeys::ACCOUNT_CREATED_AT, true ),
				),
				$summary
			),
			200
		);
	}
}
