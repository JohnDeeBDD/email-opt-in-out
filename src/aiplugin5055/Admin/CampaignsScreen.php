<?php
/**
 * The campaign management screen under Tools (PRD Section 14).
 *
 * This class is the controller: it registers the page, handles the three write
 * operations and dispatches to the view. Every write is a POST through
 * admin-post.php, protected by a nonce and a capability check.
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Admin;

use aiplugin5055\Campaigns\CampaignRepository;
use aiplugin5055\Support\Settings;

class CampaignsScreen {

	/** Page slug, also the admin-post action prefix. */
	const SLUG = 'aiplugin5055-campaigns';

	const ACTION_CREATE = 'aiplugin5055_create_campaign';
	const ACTION_UPDATE = 'aiplugin5055_update_campaign';
	const ACTION_DELETE = 'aiplugin5055_delete_campaign';
	const ACTION_SAVE_SETTINGS = 'aiplugin5055_save_settings';

	/**
	 * Add the screen to the Tools menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		\add_management_page(
			\__( 'Email Campaigns', 'aiplugin5055' ),
			\__( 'Email Campaigns', 'aiplugin5055' ),
			Settings::admin_capability(),
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Render whichever view the request asks for.
	 *
	 * @return void
	 */
	public function render() {
		$this->authorize();

		$view = isset( $_GET['view'] ) ? \sanitize_key( \wp_unslash( $_GET['view'] ) ) : 'list';
		$code = isset( $_GET['campaign_code'] ) ? strtoupper( \sanitize_text_field( \wp_unslash( $_GET['campaign_code'] ) ) ) : '';

		if ( in_array( $view, array( 'edit', 'delete' ), true ) ) {
			\check_admin_referer( self::SLUG . '_' . $view . '_' . $code );

			$campaign = CampaignRepository::get( $code );

			if ( $campaign && 'edit' === $view ) {
				CampaignsView::render_edit( $campaign );

				return;
			}

			if ( $campaign ) {
				CampaignsView::render_delete_confirmation( $campaign );

				return;
			}

			CampaignsView::render_list(
				array(
					'type'    => 'error',
					'message' => \__( 'Unknown campaign.', 'aiplugin5055' ),
				)
			);

			return;
		}

		CampaignsView::render_list( $this->notice() );
	}

	/**
	 * Create a campaign from the screen.
	 *
	 * @return void
	 */
	public function handle_create() {
		$this->authorize();
		\check_admin_referer( self::ACTION_CREATE );

		$name     = isset( $_POST['campaign_name'] ) ? \wp_unslash( $_POST['campaign_name'] ) : '';
		$campaign = CampaignRepository::create( $name );

		if ( \is_wp_error( $campaign ) ) {
			$this->redirect_back( array( 'aiplugin5055_error' => $campaign->get_error_code() ) );
		}

		$this->redirect_back(
			array(
				'aiplugin5055_notice' => 'created',
				'aiplugin5055_code'   => $campaign['campaign_code'],
			)
		);
	}

	/**
	 * Rename a campaign or change its status.
	 *
	 * The campaign code is never read from the form as a new value; it only
	 * identifies which record to change.
	 *
	 * @return void
	 */
	public function handle_update() {
		$this->authorize();

		$code = isset( $_POST['campaign_code'] ) ? strtoupper( \sanitize_text_field( \wp_unslash( $_POST['campaign_code'] ) ) ) : '';

		\check_admin_referer( self::ACTION_UPDATE . '_' . $code );

		$changes = array();

		if ( isset( $_POST['campaign_name'] ) ) {
			$changes['name'] = \wp_unslash( $_POST['campaign_name'] );
		}

		if ( isset( $_POST['campaign_status'] ) ) {
			$changes['status'] = \wp_unslash( $_POST['campaign_status'] );
		}

		$campaign = CampaignRepository::update( $code, $changes );

		if ( \is_wp_error( $campaign ) ) {
			$this->redirect_back( array( 'aiplugin5055_error' => $campaign->get_error_code() ) );
		}

		$this->redirect_back(
			array(
				'aiplugin5055_notice' => 'updated',
				'aiplugin5055_code'   => $campaign['campaign_code'],
			)
		);
	}

	/**
	 * Delete a campaign record after the confirmation step.
	 *
	 * @return void
	 */
	public function handle_delete() {
		$this->authorize();

		$code = isset( $_POST['campaign_code'] ) ? strtoupper( \sanitize_text_field( \wp_unslash( $_POST['campaign_code'] ) ) ) : '';

		\check_admin_referer( self::ACTION_DELETE . '_' . $code );

		if ( empty( $_POST['aiplugin5055_confirm_delete'] ) ) {
			$this->redirect_back( array( 'aiplugin5055_error' => 'aiplugin5055_confirmation_required' ) );
		}

		$deleted = CampaignRepository::delete( $code );

		if ( \is_wp_error( $deleted ) ) {
			$this->redirect_back( array( 'aiplugin5055_error' => $deleted->get_error_code() ) );
		}

		$this->redirect_back(
			array(
				'aiplugin5055_notice' => 'deleted',
				'aiplugin5055_code'   => $code,
			)
		);
	}

	/**
	 * Handle settings save for the unsubscribe page.
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		$this->authorize();
		\check_admin_referer( self::ACTION_SAVE_SETTINGS );

		$opt_out_page = isset( $_POST['opt_out_page'] ) ? (int) $_POST['opt_out_page'] : 0;

		Settings::update_opt_out_page( $opt_out_page );

		$this->redirect_back( array( 'aiplugin5055_notice' => 'settings_saved' ) );
	}

	/**
	 * URL of the screen, optionally with extra query args.
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	public static function url( array $args = array() ) {
		return \add_query_arg(
			array_merge( array( 'page' => self::SLUG ), $args ),
			\admin_url( 'tools.php' )
		);
	}

	/**
	 * The notice to show above the list, derived from the redirect args.
	 *
	 * Only recognised codes are rendered; nothing from the URL is echoed.
	 *
	 * @return array{type:string,message:string}|null
	 */
	private function notice() {
		$code = isset( $_GET['aiplugin5055_code'] ) ? strtoupper( \sanitize_text_field( \wp_unslash( $_GET['aiplugin5055_code'] ) ) ) : '';

		if ( isset( $_GET['aiplugin5055_notice'] ) ) {
			switch ( \sanitize_key( \wp_unslash( $_GET['aiplugin5055_notice'] ) ) ) {
				case 'created':
					return array(
						'type'    => 'success',
						/* translators: %s: campaign code. */
						'message' => sprintf( \__( 'Campaign created. Its code is %s.', 'aiplugin5055' ), $code ),
					);
				case 'updated':
					return array(
						'type'    => 'success',
						'message' => \__( 'Campaign updated.', 'aiplugin5055' ),
					);
				case 'deleted':
					return array(
						'type'    => 'success',
						/* translators: %s: campaign code. */
						'message' => sprintf(
							\__( 'Campaign record deleted. Recorded opt-in and opt-out metadata was preserved, and %s will never be reissued.', 'aiplugin5055' ),
							$code
						),
					);
				case 'settings_saved':
					return array(
						'type'    => 'success',
						'message' => \__( 'Settings saved successfully.', 'aiplugin5055' ),
					);
			}
		}

		if ( isset( $_GET['aiplugin5055_error'] ) ) {
			$errors = array(
				'aiplugin5055_missing_name'            => \__( 'A campaign name is required.', 'aiplugin5055' ),
				'aiplugin5055_invalid_status'          => \__( 'Status must be active or disabled.', 'aiplugin5055' ),
				'aiplugin5055_unknown_campaign'        => \__( 'Unknown campaign.', 'aiplugin5055' ),
				'aiplugin5055_code_generation_failed'  => \__( 'Could not generate a unique campaign code. Please try again.', 'aiplugin5055' ),
				'aiplugin5055_confirmation_required'   => \__( 'Deletion was not confirmed, so nothing was deleted.', 'aiplugin5055' ),
			);

			$key = \sanitize_key( \wp_unslash( $_GET['aiplugin5055_error'] ) );

			return array(
				'type'    => 'error',
				'message' => isset( $errors[ $key ] ) ? $errors[ $key ] : \__( 'That could not be completed.', 'aiplugin5055' ),
			);
		}

		return null;
	}

	/**
	 * @return void
	 */
	private function authorize() {
		if ( ! \current_user_can( Settings::admin_capability() ) ) {
			\wp_die(
				\esc_html__( 'You are not allowed to manage email campaigns.', 'aiplugin5055' ),
				403
			);
		}
	}

	/**
	 * Redirect back to the list. Never returns.
	 *
	 * @param array $args Query args.
	 * @return void
	 */
	private function redirect_back( array $args ) {
		\wp_safe_redirect( self::url( $args ) );
		exit;
	}
}
