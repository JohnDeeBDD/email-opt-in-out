<?php
/**
 * Authorization for every administrative endpoint (PRD Section 18).
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Rest;

use aiplugin5055\Support\Settings;

class Permissions {

	/** REST namespace shared by all controllers. */
	const NAMESPACE_V1 = 'aiplugin5055/v1';

	/**
	 * Only authenticated administrators may reach the API. Responses carry
	 * recipient addresses and action URLs, which are personal data.
	 *
	 * @return true|\WP_Error
	 */
	public static function require_admin() {
		if ( ! \is_user_logged_in() ) {
			return new \WP_Error(
				'aiplugin5055_not_authenticated',
				\__( 'Authentication is required.', 'aiplugin5055' ),
				array( 'status' => 401 )
			);
		}

		if ( ! \current_user_can( Settings::admin_capability() ) ) {
			return new \WP_Error(
				'aiplugin5055_forbidden',
				\__( 'You are not allowed to do that.', 'aiplugin5055' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}
}
