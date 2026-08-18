<?php
/**
 * Campaign CRUD over REST — the programmatic counterpart of the Tools screen
 * (PRD Sections 14 and 17).
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Rest;

use aiplugin5055\Actions\CampaignState;
use aiplugin5055\Campaigns\CampaignRepository;
use aiplugin5055\Meta\MetaKeys;

class CampaignsController {

	/** Pattern matching a campaign code in a route. */
	const CODE_PATTERN = '(?P<campaign_code>[A-Za-z0-9]{5})';

	/**
	 * @return void
	 */
	public function register_routes() {
		\register_rest_route(
			Permissions::NAMESPACE_V1,
			'/campaigns',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( Permissions::class, 'require_admin' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create' ),
					'permission_callback' => array( Permissions::class, 'require_admin' ),
					'args'                => array(
						'name' => array(
							'required' => true,
							'type'     => 'string',
						),
					),
				),
			)
		);

		\register_rest_route(
			Permissions::NAMESPACE_V1,
			'/campaigns/' . self::CODE_PATTERN,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( Permissions::class, 'require_admin' ),
				),
				array(
					'methods'             => array( 'PUT', 'PATCH' ),
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( Permissions::class, 'require_admin' ),
					'args'                => array(
						'name'   => array( 'type' => 'string' ),
						'status' => array(
							'type' => 'string',
							'enum' => array( CampaignRepository::STATUS_ACTIVE, CampaignRepository::STATUS_DISABLED ),
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => array( Permissions::class, 'require_admin' ),
					'args'                => array(
						'force' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => 'Explicit confirmation. Deletion is irreversible; disabling is not.',
						),
					),
				),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function index( $request ) {
		$campaigns = array_map( array( $this, 'present' ), CampaignRepository::all() );

		return new \WP_REST_Response( array( 'campaigns' => $campaigns ), 200 );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( $request ) {
		$campaign = CampaignRepository::get( $request['campaign_code'] );

		if ( ! $campaign ) {
			return $this->not_found();
		}

		return new \WP_REST_Response( $this->present( $campaign ), 200 );
	}

	/**
	 * Create a campaign. The plugin, not the caller, issues the code.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( $request ) {
		if ( null !== $request->get_param( 'campaign_code' ) ) {
			return new \WP_Error(
				'aiplugin5055_code_not_accepted',
				\__( 'The campaign code is assigned by the plugin and cannot be supplied.', 'aiplugin5055' ),
				array( 'status' => 400 )
			);
		}

		$campaign = CampaignRepository::create( $request['name'] );

		if ( \is_wp_error( $campaign ) ) {
			return $campaign;
		}

		return new \WP_REST_Response( $this->present( $campaign ), 201 );
	}

	/**
	 * Rename a campaign or change its status. The code is immutable.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update( $request ) {
		// The code arrives in the route; finding one in the body means the
		// caller is trying to change it (PRD Section 14).
		if ( $this->body_has_code( $request ) ) {
			return new \WP_Error(
				'aiplugin5055_code_immutable',
				\__( 'The campaign code cannot be changed; existing metadata keys embed it.', 'aiplugin5055' ),
				array( 'status' => 400 )
			);
		}

		$changes = array();

		if ( null !== $request->get_param( 'name' ) ) {
			$changes['name'] = $request->get_param( 'name' );
		}

		if ( null !== $request->get_param( 'status' ) ) {
			$changes['status'] = $request->get_param( 'status' );
		}

		if ( empty( $changes ) ) {
			return new \WP_Error(
				'aiplugin5055_nothing_to_update',
				\__( 'Supply a name or a status.', 'aiplugin5055' ),
				array( 'status' => 400 )
			);
		}

		$campaign = CampaignRepository::update( $request['campaign_code'], $changes );

		if ( \is_wp_error( $campaign ) ) {
			return $campaign;
		}

		return new \WP_REST_Response( $this->present( $campaign ), 200 );
	}

	/**
	 * Delete the campaign record. Recorded user metadata is left untouched.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete( $request ) {
		if ( ! $request->get_param( 'force' ) ) {
			return new \WP_Error(
				'aiplugin5055_confirmation_required',
				\__( 'Deletion is irreversible. Pass force=true to confirm, or disable the campaign instead.', 'aiplugin5055' ),
				array( 'status' => 400 )
			);
		}

		$campaign = CampaignRepository::get( $request['campaign_code'] );

		if ( ! $campaign ) {
			return $this->not_found();
		}

		$deleted = CampaignRepository::delete( $request['campaign_code'] );

		if ( \is_wp_error( $deleted ) ) {
			return $deleted;
		}

		return new \WP_REST_Response(
			array(
				'deleted'  => true,
				'campaign' => $this->present( $campaign ),
				'notice'   => \__( 'The campaign record was deleted. Recorded per-campaign user metadata was preserved and the code will never be reissued.', 'aiplugin5055' ),
			),
			200
		);
	}

	/**
	 * Whether the request body carries a campaign_code field.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	private function body_has_code( $request ) {
		$body = $request->get_json_params();

		if ( is_array( $body ) && array_key_exists( 'campaign_code', $body ) ) {
			return true;
		}

		$body = $request->get_body_params();

		return is_array( $body ) && array_key_exists( 'campaign_code', $body );
	}

	/**
	 * Campaign record plus the derived facts an operator needs.
	 *
	 * @param array $campaign Campaign record.
	 * @return array
	 */
	private function present( array $campaign ) {
		$code = $campaign['campaign_code'];

		return array(
			'campaign_code' => $code,
			'name'          => isset( $campaign['name'] ) ? $campaign['name'] : '',
			'created_at'    => isset( $campaign['created_at'] ) ? $campaign['created_at'] : '',
			'status'        => isset( $campaign['status'] ) ? $campaign['status'] : CampaignRepository::STATUS_ACTIVE,
			'meta_key'      => MetaKeys::campaign( $code ),
			'opt_in_count'  => CampaignState::count_users( $code, CampaignState::OPTED_IN ),
			'opt_out_count' => CampaignState::count_users( $code, CampaignState::OPTED_OUT ),
		);
	}

	/**
	 * @return \WP_Error
	 */
	private function not_found() {
		return new \WP_Error(
			'aiplugin5055_unknown_campaign',
			\__( 'Unknown campaign.', 'aiplugin5055' ),
			array( 'status' => 404 )
		);
	}
}
