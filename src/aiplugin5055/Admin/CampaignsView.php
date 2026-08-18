<?php
/**
 * Markup for the campaign management screen (PRD Sections 14 and 38).
 *
 * Rendering only. Every administrator-supplied value is escaped on output.
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Admin;

use aiplugin5055\Actions\CampaignState;
use aiplugin5055\Campaigns\CampaignRepository;
use aiplugin5055\Meta\MetaKeys;
use aiplugin5055\Rest\Permissions;
use aiplugin5055\Support\RejectedCodeLog;
use aiplugin5055\Support\Settings;

class CampaignsView {

	/**
	 * The list screen: create form, campaign table, code generator, reject log.
	 *
	 * @param array|null $notice Notice to display.
	 * @return void
	 */
	public static function render_list( $notice = null ) {
		$campaigns = CampaignRepository::all();
		?>
		<div class="wrap aiplugin5055-admin">
			<h1><?php \esc_html_e( 'Email Campaigns', 'aiplugin5055' ); ?></h1>

			<p class="description">
				<?php \esc_html_e( 'A campaign code authorizes the public opt-in and opt-out links and names the metadata key that recipient decisions are recorded under. The plugin issues the code; you supply the name.', 'aiplugin5055' ); ?>
			</p>

			<?php
			if ( $notice ) {
				self::render_notice( $notice['type'], $notice['message'] );
			}
			?>

			<h2><?php \esc_html_e( 'Create a campaign', 'aiplugin5055' ); ?></h2>

			<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" class="aiplugin5055-create">
				<input type="hidden" name="action" value="<?php echo \esc_attr( CampaignsScreen::ACTION_CREATE ); ?>">
				<?php \wp_nonce_field( CampaignsScreen::ACTION_CREATE ); ?>

				<label class="screen-reader-text" for="aiplugin5055-campaign-name"><?php \esc_html_e( 'Campaign name', 'aiplugin5055' ); ?></label>
				<input type="text" id="aiplugin5055-campaign-name" name="campaign_name" class="regular-text" required
					placeholder="<?php \esc_attr_e( 'August 2026 launch', 'aiplugin5055' ); ?>">

				<?php \submit_button( \__( 'Create campaign', 'aiplugin5055' ), 'primary', 'submit', false ); ?>
			</form>

			<h2><?php \esc_html_e( 'Campaigns', 'aiplugin5055' ); ?></h2>

			<?php if ( empty( $campaigns ) ) : ?>
				<p><?php \esc_html_e( 'No campaigns yet.', 'aiplugin5055' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped aiplugin5055-table">
					<thead>
						<tr>
							<th scope="col"><?php \esc_html_e( 'Name', 'aiplugin5055' ); ?></th>
							<th scope="col"><?php \esc_html_e( 'Campaign code', 'aiplugin5055' ); ?></th>
							<th scope="col"><?php \esc_html_e( 'Metadata key', 'aiplugin5055' ); ?></th>
							<th scope="col"><?php \esc_html_e( 'Created', 'aiplugin5055' ); ?></th>
							<th scope="col"><?php \esc_html_e( 'Status', 'aiplugin5055' ); ?></th>
							<th scope="col"><?php \esc_html_e( 'Opt-ins', 'aiplugin5055' ); ?></th>
							<th scope="col"><?php \esc_html_e( 'Opt-outs', 'aiplugin5055' ); ?></th>
							<th scope="col"><?php \esc_html_e( 'Actions', 'aiplugin5055' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $campaigns as $campaign ) : ?>
						<?php
						$code      = $campaign['campaign_code'];
						$is_active = CampaignRepository::STATUS_ACTIVE === $campaign['status'];
						?>
						<tr>
							<td><strong><?php echo \esc_html( $campaign['name'] ); ?></strong></td>
							<td>
								<code class="aiplugin5055-code"><?php echo \esc_html( $code ); ?></code>
								<button type="button" class="button-link aiplugin5055-copy"
									data-aiplugin5055-copy="<?php echo \esc_attr( $code ); ?>">
									<?php \esc_html_e( 'Copy', 'aiplugin5055' ); ?>
								</button>
							</td>
							<td><code><?php echo \esc_html( MetaKeys::campaign( $code ) ); ?></code></td>
							<td><?php echo \esc_html( self::format_date( $campaign['created_at'] ) ); ?></td>
							<td>
								<span class="aiplugin5055-status aiplugin5055-status--<?php echo \esc_attr( $campaign['status'] ); ?>">
									<?php echo \esc_html( $is_active ? \__( 'Active', 'aiplugin5055' ) : \__( 'Disabled', 'aiplugin5055' ) ); ?>
								</span>
							</td>
							<td><?php echo \esc_html( \number_format_i18n( CampaignState::count_users( $code, CampaignState::OPTED_IN ) ) ); ?></td>
							<td><?php echo \esc_html( \number_format_i18n( CampaignState::count_users( $code, CampaignState::OPTED_OUT ) ) ); ?></td>
							<td class="aiplugin5055-row-actions">
								<a href="<?php echo \esc_url( self::view_url( 'edit', $code ) ); ?>"><?php \esc_html_e( 'Rename', 'aiplugin5055' ); ?></a>

								<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" class="aiplugin5055-inline-form">
									<input type="hidden" name="action" value="<?php echo \esc_attr( CampaignsScreen::ACTION_UPDATE ); ?>">
									<input type="hidden" name="campaign_code" value="<?php echo \esc_attr( $code ); ?>">
									<input type="hidden" name="campaign_status" value="<?php echo \esc_attr( $is_active ? CampaignRepository::STATUS_DISABLED : CampaignRepository::STATUS_ACTIVE ); ?>">
									<?php \wp_nonce_field( CampaignsScreen::ACTION_UPDATE . '_' . $code ); ?>
									<button type="submit" class="button-link">
										<?php echo \esc_html( $is_active ? \__( 'Disable', 'aiplugin5055' ) : \__( 'Re-enable', 'aiplugin5055' ) ); ?>
									</button>
								</form>

								<a class="aiplugin5055-delete-link" href="<?php echo \esc_url( self::view_url( 'delete', $code ) ); ?>">
									<?php \esc_html_e( 'Delete', 'aiplugin5055' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<p class="description">
					<?php \esc_html_e( 'Disabling is the reversible way to shut down a code that has leaked or is being abused. Deleting removes only the campaign record: recorded opt-in and opt-out metadata survives, and the code is never reissued.', 'aiplugin5055' ); ?>
				</p>
			<?php endif; ?>

			<?php
			self::render_generator( $campaigns );
			self::render_rejected_log();
			self::render_settings();
			?>
		</div>
		<?php
	}

	/**
	 * The rename form.
	 *
	 * @param array $campaign Campaign record.
	 * @return void
	 */
	public static function render_edit( array $campaign ) {
		$code = $campaign['campaign_code'];
		?>
		<div class="wrap aiplugin5055-admin">
			<h1><?php \esc_html_e( 'Rename campaign', 'aiplugin5055' ); ?></h1>

			<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo \esc_attr( CampaignsScreen::ACTION_UPDATE ); ?>">
				<input type="hidden" name="campaign_code" value="<?php echo \esc_attr( $code ); ?>">
				<?php \wp_nonce_field( CampaignsScreen::ACTION_UPDATE . '_' . $code ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="aiplugin5055-edit-name"><?php \esc_html_e( 'Name', 'aiplugin5055' ); ?></label></th>
						<td>
							<input type="text" id="aiplugin5055-edit-name" name="campaign_name" class="regular-text"
								value="<?php echo \esc_attr( $campaign['name'] ); ?>" required>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php \esc_html_e( 'Campaign code', 'aiplugin5055' ); ?></th>
						<td>
							<code><?php echo \esc_html( $code ); ?></code>
							<p class="description">
								<?php \esc_html_e( 'The code cannot be changed. Every recorded metadata key embeds it, and changing it would orphan those records.', 'aiplugin5055' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aiplugin5055-edit-status"><?php \esc_html_e( 'Status', 'aiplugin5055' ); ?></label></th>
						<td>
							<select id="aiplugin5055-edit-status" name="campaign_status">
								<option value="<?php echo \esc_attr( CampaignRepository::STATUS_ACTIVE ); ?>" <?php \selected( $campaign['status'], CampaignRepository::STATUS_ACTIVE ); ?>>
									<?php \esc_html_e( 'Active', 'aiplugin5055' ); ?>
								</option>
								<option value="<?php echo \esc_attr( CampaignRepository::STATUS_DISABLED ); ?>" <?php \selected( $campaign['status'], CampaignRepository::STATUS_DISABLED ); ?>>
									<?php \esc_html_e( 'Disabled', 'aiplugin5055' ); ?>
								</option>
							</select>
							<p class="description">
								<?php \esc_html_e( 'A disabled code is refused by the public opt-in and opt-out endpoints.', 'aiplugin5055' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php \submit_button( \__( 'Save campaign', 'aiplugin5055' ) ); ?>
			</form>

			<p><a href="<?php echo \esc_url( CampaignsScreen::url() ); ?>">&larr; <?php \esc_html_e( 'Back to campaigns', 'aiplugin5055' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * The explicit confirmation step required before deletion.
	 *
	 * @param array $campaign Campaign record.
	 * @return void
	 */
	public static function render_delete_confirmation( array $campaign ) {
		$code = $campaign['campaign_code'];
		?>
		<div class="wrap aiplugin5055-admin">
			<h1><?php \esc_html_e( 'Delete campaign', 'aiplugin5055' ); ?></h1>

			<div class="notice notice-warning inline">
				<p>
					<?php
					echo \esc_html(
						sprintf(
							/* translators: 1: campaign name, 2: campaign code. */
							\__( 'You are about to delete “%1$s” (%2$s).', 'aiplugin5055' ),
							$campaign['name'],
							$code
						)
					);
					?>
				</p>
				<p><?php \esc_html_e( 'Deletion cannot be undone. Disabling the campaign is the reversible option, and deletion is really only appropriate for a campaign that was never mailed.', 'aiplugin5055' ); ?></p>
				<p>
					<?php
					echo \esc_html(
						sprintf(
							/* translators: %s: metadata key. */
							\__( 'Recorded recipient decisions are not affected: every %s entry is kept, and this code will never be issued to another campaign.', 'aiplugin5055' ),
							MetaKeys::campaign( $code )
						)
					);
					?>
				</p>
			</div>

			<p>
				<?php
				echo \esc_html(
					sprintf(
						/* translators: 1: opt-in count, 2: opt-out count. */
						\__( 'This campaign currently has %1$s recorded opt-ins and %2$s recorded opt-outs.', 'aiplugin5055' ),
						\number_format_i18n( CampaignState::count_users( $code, CampaignState::OPTED_IN ) ),
						\number_format_i18n( CampaignState::count_users( $code, CampaignState::OPTED_OUT ) )
					)
				);
				?>
			</p>

			<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo \esc_attr( CampaignsScreen::ACTION_DELETE ); ?>">
				<input type="hidden" name="campaign_code" value="<?php echo \esc_attr( $code ); ?>">
				<?php \wp_nonce_field( CampaignsScreen::ACTION_DELETE . '_' . $code ); ?>

				<p>
					<label>
						<input type="checkbox" name="aiplugin5055_confirm_delete" value="1" required>
						<?php \esc_html_e( 'Yes, delete this campaign record permanently.', 'aiplugin5055' ); ?>
					</label>
				</p>

				<?php \submit_button( \__( 'Delete campaign', 'aiplugin5055' ), 'delete' ); ?>
			</form>

			<p><a href="<?php echo \esc_url( CampaignsScreen::url() ); ?>">&larr; <?php \esc_html_e( 'Back to campaigns', 'aiplugin5055' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * The bulk tracking-code generator, which drives the REST API.
	 *
	 * @param array[] $campaigns Campaign records.
	 * @return void
	 */
	private static function render_generator( array $campaigns ) {
		$active = array_filter(
			$campaigns,
			function ( $campaign ) {
				return CampaignRepository::STATUS_ACTIVE === $campaign['status'];
			}
		);

		if ( empty( $active ) ) {
			return;
		}
		?>
		<h2><?php \esc_html_e( 'Generate tracking codes', 'aiplugin5055' ); ?></h2>

		<p class="description">
			<?php \esc_html_e( 'Paste the recipient addresses for a campaign, one per line. The generated CSV is what the mail merge consumes. Generating a code creates no WordPress user and stores no address.', 'aiplugin5055' ); ?>
		</p>

		<script type="application/json" id="aiplugin5055-admin-config">
			<?php
			echo \wp_json_encode(
				array(
					'restUrl' => \esc_url_raw( \rest_url( Permissions::NAMESPACE_V1 . '/tracking-codes' ) ),
					'nonce'   => \wp_create_nonce( 'wp_rest' ),
				)
			);
			?>
		</script>

		<form class="aiplugin5055-generator" data-aiplugin5055-generator onsubmit="return false;">
			<p>
				<label for="aiplugin5055-generator-campaign"><?php \esc_html_e( 'Campaign', 'aiplugin5055' ); ?></label><br>
				<select id="aiplugin5055-generator-campaign" data-aiplugin5055-generator-campaign>
					<?php foreach ( $active as $campaign ) : ?>
						<option value="<?php echo \esc_attr( $campaign['campaign_code'] ); ?>">
							<?php echo \esc_html( $campaign['name'] . ' (' . $campaign['campaign_code'] . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<p>
				<label for="aiplugin5055-generator-emails"><?php \esc_html_e( 'Recipient addresses', 'aiplugin5055' ); ?></label><br>
				<textarea id="aiplugin5055-generator-emails" rows="6" class="large-text code"
					data-aiplugin5055-generator-emails placeholder="john@example.com"></textarea>
			</p>

			<p>
				<button type="submit" class="button button-secondary" data-aiplugin5055-generator-submit>
					<?php \esc_html_e( 'Generate CSV', 'aiplugin5055' ); ?>
				</button>
				<span class="aiplugin5055-generator-status" data-aiplugin5055-generator-status role="status"></span>
			</p>

			<p>
				<label for="aiplugin5055-generator-output"><?php \esc_html_e( 'Result', 'aiplugin5055' ); ?></label><br>
				<textarea id="aiplugin5055-generator-output" rows="8" class="large-text code" readonly
					data-aiplugin5055-generator-output></textarea>
			</p>

			<p class="description">
				<?php \esc_html_e( 'Action URLs contain the recipient address in encoded form. Treat this output as personal data: keep it out of analytics and shared logs.', 'aiplugin5055' ); ?>
			</p>
		</form>
		<?php
	}

	/**
	 * Recently rejected action codes, so abuse of a code is visible.
	 *
	 * @return void
	 */
	private static function render_rejected_log() {
		$entries = RejectedCodeLog::all();

		if ( empty( $entries ) ) {
			return;
		}
		?>
		<h2><?php \esc_html_e( 'Recently rejected codes', 'aiplugin5055' ); ?></h2>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col"><?php \esc_html_e( 'When', 'aiplugin5055' ); ?></th>
					<th scope="col"><?php \esc_html_e( 'Endpoint', 'aiplugin5055' ); ?></th>
					<th scope="col"><?php \esc_html_e( 'Reason', 'aiplugin5055' ); ?></th>
					<th scope="col"><?php \esc_html_e( 'Code prefix', 'aiplugin5055' ); ?></th>
					<th scope="col"><?php \esc_html_e( 'Client', 'aiplugin5055' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( array_slice( $entries, 0, 20 ) as $entry ) : ?>
				<tr>
					<td><?php echo \esc_html( self::format_date( isset( $entry['timestamp'] ) ? $entry['timestamp'] : '' ) ); ?></td>
					<td><?php echo \esc_html( isset( $entry['endpoint'] ) ? $entry['endpoint'] : '' ); ?></td>
					<td><?php echo \esc_html( isset( $entry['reason'] ) ? $entry['reason'] : '' ); ?></td>
					<td><code><?php echo \esc_html( isset( $entry['prefix'] ) ? $entry['prefix'] : '' ); ?></code></td>
					<td><code><?php echo \esc_html( isset( $entry['ip_hash'] ) ? $entry['ip_hash'] : '' ); ?></code></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * @param string $type    success or error.
	 * @param string $message Message text.
	 * @return void
	 */
	public static function render_notice( $type, $message ) {
		$class = 'error' === $type ? 'notice-error' : 'notice-success';
		?>
		<div class="notice <?php echo \esc_attr( $class ); ?> is-dismissible">
			<p><?php echo \esc_html( $message ); ?></p>
		</div>
		<?php
	}

	/**
	 * Nonce-protected link to a sub-view of the screen.
	 *
	 * @param string $view View name.
	 * @param string $code Campaign code.
	 * @return string
	 */
	private static function view_url( $view, $code ) {
		return \wp_nonce_url(
			CampaignsScreen::url(
				array(
					'view'          => $view,
					'campaign_code' => $code,
				)
			),
			CampaignsScreen::SLUG . '_' . $view . '_' . $code
		);
	}

	/**
	 * @param string $iso ISO-8601 timestamp.
	 * @return string
	 */
	private static function format_date( $iso ) {
		if ( empty( $iso ) ) {
			return '';
		}

		$timestamp = strtotime( $iso );

		if ( ! $timestamp ) {
			return (string) $iso;
		}

		return \wp_date( \get_option( 'date_format' ) . ' ' . \get_option( 'time_format' ), $timestamp );
	}

	/**
	 * Render the settings section for opt-in and opt-out pages.
	 *
	 * @return void
	 */
	private static function render_settings() {
		$pages = Settings::get_pages();
		?>
		<hr>

		<h2><?php \esc_html_e( 'Settings', 'aiplugin5055' ); ?></h2>

		<p class="description">
			<?php \esc_html_e( 'Configure the WordPress pages where opt-in and opt-out forms will be displayed.', 'aiplugin5055' ); ?>
		</p>

		<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>">
			<?php \wp_nonce_field( CampaignsScreen::ACTION_SAVE_SETTINGS ); ?>
			<input type="hidden" name="action" value="<?php echo \esc_attr( CampaignsScreen::ACTION_SAVE_SETTINGS ); ?>">

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

		<h3><?php \esc_html_e( 'How It Works', 'aiplugin5055' ); ?></h3>
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
		<?php
	}
}
