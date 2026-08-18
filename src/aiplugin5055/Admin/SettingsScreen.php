<?php
/**
 * Plugin settings screen for configuring opt-in and opt-out pages.
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Admin;

use aiplugin5055\Support\Settings;

class SettingsScreen {

	/** Page slug. */
	const SLUG = 'aiplugin5055-settings';

	const ACTION_SAVE = 'aiplugin5055_save_settings';

	/**
	 * Add the settings screen to the Settings menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		\add_options_page(
			\__( 'Email Campaign Settings', 'aiplugin5055' ),
			\__( 'Email Campaigns', 'aiplugin5055' ),
			Settings::admin_capability(),
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render() {
		$this->authorize();

		$pages  = Settings::get_pages();
		$notice = $this->notice();

		?>
		<div class="wrap">
			<h1><?php \esc_html_e( 'Email Campaign Settings', 'aiplugin5055' ); ?></h1>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo \esc_attr( $notice['type'] ); ?> is-dismissible">
					<p><?php echo \esc_html( $notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>">
				<?php \wp_nonce_field( self::ACTION_SAVE ); ?>
				<input type="hidden" name="action" value="<?php echo \esc_attr( self::ACTION_SAVE ); ?>">

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="aiplugin5055_opt_in_page">
									<?php \esc_html_e( 'Opt-In Page', 'aiplugin5055' ); ?>
								</label>
							</th>
							<td>
								<?php
								\wp_dropdown_pages(
									array(
										'name'              => 'opt_in_page',
										'id'                => 'aiplugin5055_opt_in_page',
										'selected'          => $pages['opt_in_page'],
										'show_option_none'  => \__( '— Select a page —', 'aiplugin5055' ),
										'option_none_value' => '0',
									)
								);
								?>
								<p class="description">
									<?php \esc_html_e( 'Select the WordPress page where users will be directed when they click opt-in (CTA) links. The plugin will display the opt-in confirmation form on this page.', 'aiplugin5055' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="aiplugin5055_opt_out_page">
									<?php \esc_html_e( 'Opt-Out Page', 'aiplugin5055' ); ?>
								</label>
							</th>
							<td>
								<?php
								\wp_dropdown_pages(
									array(
										'name'              => 'opt_out_page',
										'id'                => 'aiplugin5055_opt_out_page',
										'selected'          => $pages['opt_out_page'],
										'show_option_none'  => \__( '— Select a page —', 'aiplugin5055' ),
										'option_none_value' => '0',
									)
								);
								?>
								<p class="description">
									<?php \esc_html_e( 'Select the WordPress page where users will be directed when they click unsubscribe links. The plugin will display the unsubscribe confirmation form on this page.', 'aiplugin5055' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php \submit_button( \__( 'Save Settings', 'aiplugin5055' ) ); ?>
			</form>

			<hr>

			<h2><?php \esc_html_e( 'How It Works', 'aiplugin5055' ); ?></h2>
			<p>
				<?php \esc_html_e( 'When you select pages above, the plugin will:', 'aiplugin5055' ); ?>
			</p>
			<ul style="list-style: disc; margin-left: 2em;">
				<li><?php \esc_html_e( 'Direct opt-in and opt-out links to the selected WordPress pages instead of custom URLs', 'aiplugin5055' ); ?></li>
				<li><?php \esc_html_e( 'Automatically display the appropriate confirmation form on those pages using WordPress content filters', 'aiplugin5055' ); ?></li>
				<li><?php \esc_html_e( 'Preserve all existing functionality including rate limiting, security checks, and action recording', 'aiplugin5055' ); ?></li>
			</ul>
			<p>
				<strong><?php \esc_html_e( 'Note:', 'aiplugin5055' ); ?></strong>
				<?php \esc_html_e( 'If no pages are selected, the plugin will fall back to the original custom URL behavior.', 'aiplugin5055' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle settings save.
	 *
	 * @return void
	 */
	public function handle_save() {
		$this->authorize();
		\check_admin_referer( self::ACTION_SAVE );

		$opt_in_page  = isset( $_POST['opt_in_page'] ) ? (int) $_POST['opt_in_page'] : 0;
		$opt_out_page = isset( $_POST['opt_out_page'] ) ? (int) $_POST['opt_out_page'] : 0;

		Settings::update_pages( $opt_in_page, $opt_out_page );

		$this->redirect_back( array( 'aiplugin5055_notice' => 'saved' ) );
	}

	/**
	 * URL of the settings screen.
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	public static function url( array $args = array() ) {
		return \add_query_arg(
			array_merge( array( 'page' => self::SLUG ), $args ),
			\admin_url( 'options-general.php' )
		);
	}

	/**
	 * The notice to show, derived from the redirect args.
	 *
	 * @return array{type:string,message:string}|null
	 */
	private function notice() {
		if ( isset( $_GET['aiplugin5055_notice'] ) && 'saved' === \sanitize_key( \wp_unslash( $_GET['aiplugin5055_notice'] ) ) ) {
			return array(
				'type'    => 'success',
				'message' => \__( 'Settings saved successfully.', 'aiplugin5055' ),
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
				\esc_html__( 'You are not allowed to manage email campaign settings.', 'aiplugin5055' ),
				403
			);
		}
	}

	/**
	 * Redirect back to the settings page. Never returns.
	 *
	 * @param array $args Query args.
	 * @return void
	 */
	private function redirect_back( array $args ) {
		\wp_safe_redirect( self::url( $args ) );
		exit;
	}
}
