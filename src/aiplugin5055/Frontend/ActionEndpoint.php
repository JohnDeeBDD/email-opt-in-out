<?php
/**
 * The public opt-out (unsubscribe) endpoint (PRD Sections 20, 22, 24, 25, 36).
 *
 * This page runs under an unauthenticated action code, not a WordPress
 * session. Its entire capability is: for the single address encoded in the
 * code, find or create the user and set the opt-out metadata of the single
 * campaign named in the code. Merely loading the URL does nothing; the
 * recipient has to confirm.
 *
 * Opt-in has no endpoint of its own — see Frontend\SilentOptIn.
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Frontend;

use aiplugin5055\Actions\ActionRecorder;
use aiplugin5055\Campaigns\CampaignRepository;
use aiplugin5055\Codec\ActionCode;
use aiplugin5055\Support\RateLimiter;
use aiplugin5055\Support\RejectedCodeLog;
use aiplugin5055\Support\Settings;
use aiplugin5055\Support\Urls;

class ActionEndpoint {

	/** Requests allowed per window for page views. */
	const VIEW_LIMIT = 60;

	/** Requests allowed per window for state-changing submissions. */
	const SUBMIT_LIMIT = 15;

	/** Rate-limit window, in seconds. */
	const WINDOW = 600;

	/**
	 * Rewrite rule for the public endpoint.
	 *
	 * Registered on init and again from the activation hook, so that the flush
	 * performed on activation has something to flush.
	 *
	 * @return void
	 */
	public static function register_rewrite_rules() {
		\add_rewrite_rule(
			'^' . Urls::opt_out_slug() . '/?$',
			'index.php?' . Urls::ACTION_VAR . '=' . Urls::ACTION_OPT_OUT,
			'top'
		);
	}

	/**
	 * Expose the action query var.
	 *
	 * @param string[] $vars Registered query vars.
	 * @return string[]
	 */
	public static function register_query_vars( $vars ) {
		$vars[] = Urls::ACTION_VAR;

		return $vars;
	}

	/**
	 * Which action, if any, this request is for.
	 *
	 * @return string Empty string when the request is not ours.
	 */
	public static function current_action() {
		$action = \get_query_var( Urls::ACTION_VAR );

		if ( '' === $action || null === $action ) {
			// A POST to the plain-permalink form carries the action in the body.
			$action = isset( $_POST[ Urls::ACTION_VAR ] ) ? \sanitize_key( \wp_unslash( $_POST[ Urls::ACTION_VAR ] ) ) : '';
		}

		$action = \sanitize_key( (string) $action );

		return Urls::ACTION_OPT_OUT === $action ? $action : '';
	}

	/**
	 * Handle an action request, if this is one.
	 *
	 * This method handles both custom URL endpoints and WordPress page-based endpoints.
	 * When using WordPress pages, it stores the action data in a transient for the
	 * content filter to retrieve.
	 *
	 * @return void
	 */
	public function handle() {
		$action = self::current_action();

		if ( '' === $action ) {
			return;
		}

		// Check if we're on a WordPress page (not a custom endpoint).
		$on_wp_page = $this->is_on_wordpress_page();

		if ( ! $on_wp_page ) {
			$this->prepare_response();
		}

		$is_submission = $this->is_submission();

		if ( ! RateLimiter::consume(
			$is_submission ? 'submit' : 'view',
			$is_submission ? self::SUBMIT_LIMIT : self::VIEW_LIMIT,
			self::WINDOW
		) ) {
			if ( $on_wp_page ) {
				$this->store_action_data( 'error', array( 'status' => 429 ) );
				return;
			}
			ActionPageView::render_error( 429 );
			exit;
		}

		$raw_code = $this->submitted_code( $is_submission );
		$parsed   = ActionCode::parse( $raw_code );

		if ( ! $parsed['ok'] ) {
			if ( $on_wp_page ) {
				$this->reject_on_page( $parsed['reason'], $raw_code, $action );
				return;
			}
			$this->reject( $parsed['reason'], $raw_code, $action );
		}

		$campaign = CampaignRepository::get( $parsed['campaign_code'] );

		// Unknown, deleted and disabled campaigns are all refused, and the
		// refusal looks identical to every other failure.
		if ( ! CampaignRepository::is_usable( $parsed['campaign_code'] ) || ! $campaign ) {
			if ( $on_wp_page ) {
				$this->reject_on_page( 'campaign_not_usable', $raw_code, $action );
				return;
			}
			$this->reject( 'campaign_not_usable', $raw_code, $action );
		}

		$campaign['name'] = isset( $campaign['name'] ) ? (string) $campaign['name'] : '';

		// The code echoed back into the form is rebuilt from the parsed parts,
		// never reflected from the request.
		$action_code = ActionCode::build( $parsed['campaign_code'], $parsed['email'] );

		if ( ! $is_submission ) {
			if ( $on_wp_page ) {
				$this->store_action_data( 'confirm', array(
					'action'      => $action,
					'campaign'    => $campaign,
					'email'       => $parsed['email'],
					'action_code' => $action_code,
				) );
				return;
			}
			ActionPageView::render_confirm( $action, $campaign, $parsed['email'], $action_code );
			exit;
		}

		$result = ActionRecorder::record(
			$parsed['email'],
			$campaign,
			ActionRecorder::ACTION_OPT_OUT,
			array(
				'source'    => 'email_campaign',
				'mechanism' => 'public_action_page',
				'endpoint'  => $action,
				'ip_hash'   => RateLimiter::client_ip_hash(),
			)
		);

		if ( \is_wp_error( $result ) ) {
			if ( $on_wp_page ) {
				$this->store_action_data( 'error', array( 'status' => 500 ) );
				return;
			}
			ActionPageView::render_error( 500 );
			exit;
		}

		if ( $on_wp_page ) {
			$this->store_action_data( 'result', array(
				'action'   => $action,
				'campaign' => $campaign,
				'email'    => $parsed['email'],
			) );
			return;
		}

		ActionPageView::render_result( $action, $campaign, $parsed['email'] );
		exit;
	}

	/**
	 * Whether the recipient explicitly confirmed the action.
	 *
	 * Nothing but a POST carrying the confirmation field may change state, so
	 * link scanners, previewers and prefetchers cannot act on the recipient's
	 * behalf.
	 *
	 * @return bool
	 */
	private function is_submission() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( \sanitize_text_field( \wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

		return 'POST' === $method && ! empty( $_POST['aiplugin5055_confirm'] );
	}

	/**
	 * The action code as submitted.
	 *
	 * The recipient's identity comes from this code and from nowhere else; no
	 * email parameter in the request is ever consulted (PRD Invariant 7).
	 *
	 * @param bool $is_submission Whether this is the confirming POST.
	 * @return string
	 */
	private function submitted_code( $is_submission ) {
		$source = $is_submission ? $_POST : $_GET;

		if ( ! isset( $source[ Urls::CODE_VAR ] ) || ! is_string( $source[ Urls::CODE_VAR ] ) ) {
			return '';
		}

		return \sanitize_text_field( \wp_unslash( $source[ Urls::CODE_VAR ] ) );
	}

	/**
	 * Log the rejection and render the generic failure page. Never returns.
	 *
	 * @param string $reason   Failure reason.
	 * @param string $raw_code Submitted code.
	 * @param string $endpoint Endpoint that rejected it.
	 * @return void
	 */
	private function reject( $reason, $raw_code, $endpoint ) {
		RejectedCodeLog::record( $reason, $raw_code, $endpoint );

		ActionPageView::render_error( 200 );
		exit;
	}

	/**
	 * Make sure the response is treated as a real, uncacheable page.
	 *
	 * @return void
	 */
	private function prepare_response() {
		global $wp_query;

		if ( $wp_query instanceof \WP_Query ) {
			$wp_query->is_404 = false;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		\nocache_headers();
		\status_header( 200 );
		\show_admin_bar( false );
	}

	/**
	 * Check if we're on a WordPress page (not a custom endpoint).
	 *
	 * @return bool
	 */
	private function is_on_wordpress_page() {
		$page_id = Settings::opt_out_page();

		if ( ! $page_id ) {
			return false;
		}

		return \is_page( $page_id );
	}

	/**
	 * Store action data in a transient for the content filter to retrieve.
	 *
	 * @param string $type Type of data: 'confirm', 'result', or 'error'.
	 * @param array  $data The data to store.
	 * @return void
	 */
	private function store_action_data( $type, array $data ) {
		$key = $this->get_action_data_key();
		
		\set_transient(
			$key,
			array(
				'type' => $type,
				'data' => $data,
			),
			300 // 5 minutes
		);
	}

	/**
	 * Get the transient key for storing action data.
	 *
	 * @return string
	 */
	private function get_action_data_key() {
		// Use a combination of session ID and IP hash for uniqueness.
		$identifier = RateLimiter::client_ip_hash();
		
		return 'aiplugin5055_action_' . substr( $identifier, 0, 16 );
	}

	/**
	 * Log rejection and store error data for WordPress page display.
	 *
	 * @param string $reason   Failure reason.
	 * @param string $raw_code Submitted code.
	 * @param string $endpoint Endpoint that rejected it.
	 * @return void
	 */
	private function reject_on_page( $reason, $raw_code, $endpoint ) {
		RejectedCodeLog::record( $reason, $raw_code, $endpoint );
		$this->store_action_data( 'error', array( 'status' => 200 ) );
	}
}
