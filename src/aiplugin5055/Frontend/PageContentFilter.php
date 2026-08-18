<?php
/**
 * Content filter for rendering the unsubscribe UI on a WordPress page.
 *
 * When a WordPress page is configured for opt-out, this filter replaces the
 * page content with the unsubscribe UI. Opt-in has no page and no UI.
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Frontend;

use aiplugin5055\Support\RateLimiter;
use aiplugin5055\Support\Settings;

class PageContentFilter {

	/**
	 * Filter the content of the configured unsubscribe page.
	 *
	 * @param string $content The page content.
	 * @return string
	 */
	public static function filter_content( $content ) {
		// Only filter on singular pages.
		if ( ! \is_singular( 'page' ) ) {
			return $content;
		}

		$opt_out_page = Settings::opt_out_page();

		// Check if this is our configured page.
		if ( ! $opt_out_page || \get_the_ID() !== $opt_out_page ) {
			return $content;
		}

		// Check if there's an action in the query string.
		$action = ActionEndpoint::current_action();
		
		if ( '' === $action ) {
			// No action parameter, show instructions.
			return self::render_instructions();
		}

		// Retrieve the action data stored by ActionEndpoint.
		$action_data = self::get_action_data();

		if ( ! $action_data ) {
			// No data available, something went wrong.
			return self::render_no_data();
		}

		// Render the appropriate UI based on the data type.
		return self::render_action_ui( $action_data );
	}

	/**
	 * Get the action data from the transient.
	 *
	 * @return array|false
	 */
	private static function get_action_data() {
		$identifier = RateLimiter::client_ip_hash();
		$key        = 'aiplugin5055_action_' . substr( $identifier, 0, 16 );
		$data       = \get_transient( $key );

		if ( $data && is_array( $data ) && isset( $data['type'], $data['data'] ) ) {
			// Delete the transient after retrieving it (one-time use).
			\delete_transient( $key );
			return $data;
		}

		return false;
	}

	/**
	 * Render the action UI based on the stored data.
	 *
	 * @param array $action_data The action data.
	 * @return string
	 */
	private static function render_action_ui( array $action_data ) {
		\ob_start();

		switch ( $action_data['type'] ) {
			case 'confirm':
				ActionPageView::render_confirm_content(
					$action_data['data']['action'],
					$action_data['data']['campaign'],
					$action_data['data']['email'],
					$action_data['data']['action_code']
				);
				break;

			case 'result':
				ActionPageView::render_result_content(
					$action_data['data']['action'],
					$action_data['data']['campaign'],
					$action_data['data']['email']
				);
				break;

			case 'error':
				ActionPageView::render_error_content(
					isset( $action_data['data']['status'] ) ? $action_data['data']['status'] : 200
				);
				break;

			default:
				echo '<p>' . \esc_html__( 'An error occurred.', 'aiplugin5055' ) . '</p>';
		}

		return \ob_get_clean();
	}

	/**
	 * Render instructions when no action is present.
	 *
	 * @return string
	 */
	private static function render_instructions() {
		\ob_start();
		?>
		<div class="aiplugin5055-instructions">
			<p>
				<?php \esc_html_e( 'This page is configured to handle email unsubscribe requests. Users will be directed here when they click unsubscribe links in campaign emails.', 'aiplugin5055' ); ?>
			</p>
		</div>
		<?php
		return \ob_get_clean();
	}

	/**
	 * Render a message when no action data is available.
	 *
	 * @return string
	 */
	private static function render_no_data() {
		\ob_start();
		?>
		<div class="aiplugin5055-no-data">
			<p><?php \esc_html_e( 'This link is not valid or has expired. Please try again or contact support.', 'aiplugin5055' ); ?></p>
		</div>
		<?php
		return \ob_get_clean();
	}
}
