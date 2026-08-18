<?php
/**
 * Read-only per-campaign state on the user edit screen (PRD Section 38).
 *
 * Answers, for one user: which campaigns they acted on, what the current state
 * is for each, when and how it happened, and whether an email action is the
 * reason the account exists at all.
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Admin;

use aiplugin5055\Actions\CampaignState;
use aiplugin5055\Meta\MetaKeys;
use aiplugin5055\Support\Settings;

class UserProfileSection {

	/**
	 * @param \WP_User $user User being edited.
	 * @return void
	 */
	public function render( $user ) {
		if ( ! \current_user_can( Settings::admin_capability() ) ) {
			return;
		}

		$summary     = CampaignState::summary_for_user( $user->ID );
		$created_by  = \get_user_meta( $user->ID, MetaKeys::ACCOUNT_CREATED_BY, true );
		?>
		<h2><?php \esc_html_e( 'Email campaign opt-in / opt-out', 'aiplugin5055' ); ?></h2>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php \esc_html_e( 'Email address', 'aiplugin5055' ); ?></th>
				<td><?php echo \esc_html( $user->user_email ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php \esc_html_e( 'Why this account exists', 'aiplugin5055' ); ?></th>
				<td>
					<?php if ( 'email_action' === $created_by ) : ?>
						<?php
						$reason   = \get_user_meta( $user->ID, MetaKeys::ACCOUNT_CREATED_REASON, true );
						$campaign = \get_user_meta( $user->ID, MetaKeys::ACCOUNT_CREATED_CAMPAIGN, true );

						echo \esc_html(
							sprintf(
								/* translators: 1: opt_in or opt_out, 2: campaign code, 3: date. */
								\__( 'Created by an explicit %1$s on campaign %2$s (%3$s).', 'aiplugin5055' ),
								$reason,
								$campaign,
								\get_user_meta( $user->ID, MetaKeys::ACCOUNT_CREATED_AT, true )
							)
						);
						?>
					<?php else : ?>
						<?php \esc_html_e( 'Not created by this plugin.', 'aiplugin5055' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php \esc_html_e( 'Campaigns acted on', 'aiplugin5055' ); ?></th>
				<td>
					<?php if ( empty( $summary['campaigns'] ) ) : ?>
						<p>
							<?php \esc_html_e( 'This user has never explicitly acted on a campaign. That is not an opt-out — it is simply no record.', 'aiplugin5055' ); ?>
						</p>
					<?php else : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th scope="col"><?php \esc_html_e( 'Campaign', 'aiplugin5055' ); ?></th>
									<th scope="col"><?php \esc_html_e( 'Metadata key', 'aiplugin5055' ); ?></th>
									<th scope="col"><?php \esc_html_e( 'State', 'aiplugin5055' ); ?></th>
									<th scope="col"><?php \esc_html_e( 'Opt-in', 'aiplugin5055' ); ?></th>
									<th scope="col"><?php \esc_html_e( 'Opt-out', 'aiplugin5055' ); ?></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ( $summary['campaigns'] as $entry ) : ?>
								<tr>
									<td>
										<?php echo \esc_html( '' !== $entry['campaign_name'] ? $entry['campaign_name'] : $entry['campaign_code'] ); ?>
										<br><code><?php echo \esc_html( $entry['campaign_code'] ); ?></code>
										<?php if ( ! $entry['campaign_exists'] ) : ?>
											<br><em><?php \esc_html_e( 'Campaign record deleted; the decision is kept.', 'aiplugin5055' ); ?></em>
										<?php endif; ?>
									</td>
									<td><code><?php echo \esc_html( $entry['meta_key'] ); ?></code></td>
									<td><strong><?php echo \esc_html( $entry['state'] ); ?></strong></td>
									<td><?php echo \esc_html( self::describe( $entry['opt_in'] ) ); ?></td>
									<td><?php echo \esc_html( self::describe( $entry['opt_out'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * One-line summary of an action entry.
	 *
	 * @param array|null $entry Action entry.
	 * @return string
	 */
	private static function describe( $entry ) {
		if ( ! is_array( $entry ) || empty( $entry['timestamp'] ) ) {
			return \__( '—', 'aiplugin5055' );
		}

		$source = isset( $entry['source'] ) ? $entry['source'] : '';
		$count  = isset( $entry['count'] ) ? (int) $entry['count'] : 1;

		return sprintf(
			/* translators: 1: timestamp, 2: source, 3: number of times the action was taken. */
			\__( '%1$s via %2$s (recorded %3$d time(s))', 'aiplugin5055' ),
			$entry['timestamp'],
			$source,
			$count
		);
	}
}
