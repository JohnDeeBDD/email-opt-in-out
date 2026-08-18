<?php
/**
 * Silent opt-in: any front-end request carrying a valid action code records an
 * opt-in for the encoded address, and the site says nothing about it.
 *
 * Unlike the unsubscribe endpoint, opt-in is not a page of its own. The action
 * code may be appended to any URL on the site — a landing page, a post, the
 * home page — and the visit itself is the opt-in. The request is otherwise
 * untouched: no output, no redirect, no interruption of the page the visitor
 * asked for, and no WordPress session of any kind.
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Frontend;

use aiplugin5055\Actions\ActionRecorder;
use aiplugin5055\Actions\CampaignState;
use aiplugin5055\Campaigns\CampaignRepository;
use aiplugin5055\Codec\ActionCode;
use aiplugin5055\Support\RateLimiter;
use aiplugin5055\Support\RejectedCodeLog;
use aiplugin5055\Support\Settings;
use aiplugin5055\Support\Urls;

class SilentOptIn {

	/**
	 * Rejected codes allowed per window, per IP.
	 *
	 * Only failures are counted, so a run of guessed codes is cut off while a
	 * genuine recipient — whose code parses on the first try — is never
	 * refused an opt-in because someone shares their address.
	 */
	const REJECTION_LIMIT = 20;

	/** Rate-limit window, in seconds. */
	const WINDOW = 600;

	/**
	 * Record the opt-in, if this request carries a code that earns one.
	 *
	 * @return void
	 */
	public function handle() {
		// Cheapest test first: the overwhelming majority of front-end requests
		// carry no code at all.
		$raw_code = $this->submitted_code();

		if ( '' === $raw_code || ! $this->is_candidate_request() ) {
			return;
		}

		// The URL is personal data by construction, so it must not be stored in
		// a shared page cache.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		if ( RateLimiter::exceeded( 'opt_in_probe', self::REJECTION_LIMIT ) ) {
			return;
		}

		$parsed = ActionCode::parse( $raw_code );

		if ( ! $parsed['ok'] ) {
			$this->reject( $parsed['reason'], $raw_code );

			return;
		}

		$campaign = CampaignRepository::get( $parsed['campaign_code'] );

		// Unknown, deleted and disabled campaigns are all refused, silently.
		if ( ! $campaign || ! CampaignRepository::is_usable( $parsed['campaign_code'] ) ) {
			$this->reject( 'campaign_not_usable', $raw_code );

			return;
		}

		if ( $this->already_opted_in( $parsed['email'], $parsed['campaign_code'] ) ) {
			return;
		}

		ActionRecorder::record(
			$parsed['email'],
			$campaign,
			ActionRecorder::ACTION_OPT_IN,
			array(
				'source'    => 'email_campaign',
				'mechanism' => 'silent_link',
				'reason'    => \__( 'Recipient followed their personalized campaign link', 'aiplugin5055' ),
				'endpoint'  => Urls::ACTION_OPT_IN,
				'ip_hash'   => RateLimiter::client_ip_hash(),
			)
		);

		// A failed write is deliberately not surfaced: the visitor asked for a
		// page, not for a report on our bookkeeping.
	}

	/**
	 * Whether this request is one that may carry an opt-in.
	 *
	 * Only plain front-end GETs qualify. The unsubscribe endpoint and the page
	 * hosting it are excluded outright, so an unsubscribe link can never opt
	 * someone in — not even one that arrives with its action parameter
	 * stripped by a mail client.
	 *
	 * @return bool
	 */
	private function is_candidate_request() {
		if ( \is_admin() || \wp_doing_ajax() || \wp_doing_cron() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( \sanitize_text_field( \wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

		if ( 'GET' !== $method ) {
			return false;
		}

		if ( '' !== ActionEndpoint::current_action() ) {
			return false;
		}

		$opt_out_page = Settings::opt_out_page();

		return ! ( $opt_out_page && \is_page( $opt_out_page ) );
	}

	/**
	 * The action code as submitted.
	 *
	 * The recipient's identity comes from this code and from nowhere else; no
	 * email parameter in the request is ever consulted (PRD Invariant 7).
	 *
	 * @return string
	 */
	private function submitted_code() {
		if ( ! isset( $_GET[ Urls::CODE_VAR ] ) || ! is_string( $_GET[ Urls::CODE_VAR ] ) ) {
			return '';
		}

		return \sanitize_text_field( \wp_unslash( $_GET[ Urls::CODE_VAR ] ) );
	}

	/**
	 * Whether this address already holds an opt-in for this campaign.
	 *
	 * Re-following the link is common — a reload, a second click, a link
	 * scanner — and must not inflate the recorded action counts. A recipient
	 * who has since unsubscribed is not skipped: visiting the link again is a
	 * fresh explicit opt-in.
	 *
	 * @param string $email         Canonical address from the code.
	 * @param string $campaign_code Campaign code from the code.
	 * @return bool
	 */
	private function already_opted_in( $email, $campaign_code ) {
		$user = \get_user_by( 'email', $email );

		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		return CampaignState::OPTED_IN === CampaignState::for_user( $user->ID, $campaign_code );
	}

	/**
	 * Log the rejection and charge it against the probe limit.
	 *
	 * @param string $reason   Failure reason.
	 * @param string $raw_code Submitted code.
	 * @return void
	 */
	private function reject( $reason, $raw_code ) {
		RateLimiter::consume( 'opt_in_probe', self::REJECTION_LIMIT, self::WINDOW );
		RejectedCodeLog::record( $reason, $raw_code, Urls::ACTION_OPT_IN );
	}
}
