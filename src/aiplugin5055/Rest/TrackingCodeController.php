<?php
/**
 * The administrator-only tracking code API (PRD Section 17).
 *
 * Generating a code is a pure transformation: it creates no WordPress user and
 * stores no recipient address.
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Rest;

use aiplugin5055\Campaigns\CampaignRepository;
use aiplugin5055\Codec\ActionCode;
use aiplugin5055\Codec\EmailCodec;
use aiplugin5055\Support\Urls;

class TrackingCodeController {

	/**
	 * @return void
	 */
	public function register_routes() {
		\register_rest_route(
			Permissions::NAMESPACE_V1,
			'/tracking-code',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_one' ),
				'permission_callback' => array( Permissions::class, 'require_admin' ),
				'args'                => array(
					'email'         => array(
						'required' => true,
						'type'     => 'string',
					),
					'campaign_code' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);

		\register_rest_route(
			Permissions::NAMESPACE_V1,
			'/tracking-codes',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_many' ),
				'permission_callback' => array( Permissions::class, 'require_admin' ),
				'args'                => array(
					'emails'        => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array( 'type' => 'string' ),
					),
					'campaign_code' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);
	}

	/**
	 * One tracking code and its two action URLs.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_one( $request ) {
		$campaign = $this->resolve_campaign( $request['campaign_code'] );

		if ( \is_wp_error( $campaign ) ) {
			return $campaign;
		}

		$generated = $this->generate( $request['email'], $campaign );

		if ( \is_wp_error( $generated ) ) {
			return $generated;
		}

		return new \WP_REST_Response( $generated, 200 );
	}

	/**
	 * Codes for a batch of recipients, for feeding a mail merge.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_many( $request ) {
		$campaign = $this->resolve_campaign( $request['campaign_code'] );

		if ( \is_wp_error( $campaign ) ) {
			return $campaign;
		}

		$emails = (array) $request['emails'];

		if ( count( $emails ) > 2000 ) {
			return new \WP_Error(
				'aiplugin5055_too_many',
				\__( 'A maximum of 2000 addresses may be generated per request.', 'aiplugin5055' ),
				array( 'status' => 400 )
			);
		}

		$codes    = array();
		$rejected = array();

		foreach ( $emails as $email ) {
			$generated = $this->generate( $email, $campaign );

			if ( \is_wp_error( $generated ) ) {
				$rejected[] = array(
					'email'  => \sanitize_text_field( (string) $email ),
					'reason' => $generated->get_error_code(),
				);

				continue;
			}

			$codes[] = $generated;
		}

		return new \WP_REST_Response(
			array(
				'campaign_code' => $campaign['campaign_code'],
				'campaign_name' => $campaign['name'],
				'codes'         => $codes,
				'rejected'      => $rejected,
			),
			200
		);
	}

	/**
	 * Build the code and URLs for one address.
	 *
	 * @param string $email    Recipient address, existing WordPress user or not.
	 * @param array  $campaign Campaign record.
	 * @return array|\WP_Error
	 */
	private function generate( $email, array $campaign ) {
		$canonical = EmailCodec::canonicalize( $email );

		if ( ! \is_email( $canonical ) ) {
			return new \WP_Error(
				'aiplugin5055_invalid_email',
				\__( 'A valid email address is required.', 'aiplugin5055' ),
				array( 'status' => 400 )
			);
		}

		$tracking_code = ActionCode::build( $campaign['campaign_code'], $canonical );

		return array_merge(
			array(
				'email'         => $canonical,
				'campaign_code' => $campaign['campaign_code'],
				'tracking_code' => $tracking_code,
			),
			Urls::both( $tracking_code )
		);
	}

	/**
	 * Look up the campaign, refusing unknown and disabled codes.
	 *
	 * @param string $campaign_code Campaign code.
	 * @return array|\WP_Error
	 */
	private function resolve_campaign( $campaign_code ) {
		$campaign = CampaignRepository::get( $campaign_code );

		if ( ! $campaign ) {
			return new \WP_Error(
				'aiplugin5055_unknown_campaign',
				\__( 'Unknown campaign code.', 'aiplugin5055' ),
				array( 'status' => 404 )
			);
		}

		if ( CampaignRepository::STATUS_ACTIVE !== $campaign['status'] ) {
			return new \WP_Error(
				'aiplugin5055_disabled_campaign',
				\__( 'That campaign is disabled.', 'aiplugin5055' ),
				array( 'status' => 409 )
			);
		}

		return $campaign;
	}
}
