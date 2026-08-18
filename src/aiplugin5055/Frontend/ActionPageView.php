<?php
/**
 * Rendering for the public action pages (PRD Sections 23, 24, 36).
 *
 * Every page is a self-contained document: recipients arrive from an email
 * with no WordPress session, and the page must work regardless of the theme.
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Frontend;

use aiplugin5055\Support\Urls;

class ActionPageView {

	/**
	 * The confirmation form. Loading this page changes nothing; the state
	 * change requires the recipient to submit it (PRD Section 25).
	 *
	 * @param string $action      opt_in or opt_out.
	 * @param array  $campaign    Campaign record.
	 * @param string $email       Decoded address.
	 * @param string $action_code Normalized action code.
	 * @return void
	 */
	public static function render_confirm( $action, array $campaign, $email, $action_code ) {
		$is_opt_out = Urls::ACTION_OPT_OUT === $action;

		$title = $is_opt_out
			? \__( 'Unsubscribe', 'aiplugin5055' )
			: \__( 'Confirm your subscription', 'aiplugin5055' );

		$intro = $is_opt_out
			? \__( 'You are about to unsubscribe from this campaign. No account, password or explanation is required.', 'aiplugin5055' )
			: \__( 'You are about to opt in to this campaign. No account or password is required.', 'aiplugin5055' );

		$button = $is_opt_out
			? \__( 'Unsubscribe', 'aiplugin5055' )
			: \__( 'Yes, subscribe me', 'aiplugin5055' );

		self::layout(
			$title,
			function () use ( $action, $campaign, $email, $action_code, $title, $intro, $button, $is_opt_out ) {
				?>
				<h1 class="aiplugin5055-title"><?php echo \esc_html( $title ); ?></h1>
				<p class="aiplugin5055-intro"><?php echo \esc_html( $intro ); ?></p>

				<dl class="aiplugin5055-summary">
					<dt><?php \esc_html_e( 'Email address', 'aiplugin5055' ); ?></dt>
					<dd><?php echo \esc_html( $email ); ?></dd>
					<dt><?php \esc_html_e( 'Campaign', 'aiplugin5055' ); ?></dt>
					<dd><?php echo \esc_html( $campaign['name'] ); ?></dd>
				</dl>

				<p class="aiplugin5055-scope">
					<?php
					echo \esc_html(
						$is_opt_out
							? \__( 'This applies only to the campaign named above. Any other campaign you have acted on is unaffected.', 'aiplugin5055' )
							: \__( 'This applies only to the campaign named above.', 'aiplugin5055' )
					);
					?>
				</p>

				<form method="post" action="<?php echo \esc_url( Urls::action_url( $action, $action_code ) ); ?>" class="aiplugin5055-form" data-aiplugin5055-action-form>
					<input type="hidden" name="<?php echo \esc_attr( Urls::ACTION_VAR ); ?>" value="<?php echo \esc_attr( $action ); ?>">
					<input type="hidden" name="<?php echo \esc_attr( Urls::CODE_VAR ); ?>" value="<?php echo \esc_attr( $action_code ); ?>">
					<input type="hidden" name="aiplugin5055_confirm" value="1">
					<button type="submit" class="aiplugin5055-button<?php echo $is_opt_out ? ' aiplugin5055-button--danger' : ''; ?>">
						<?php echo \esc_html( $button ); ?>
					</button>
				</form>
				<?php
			}
		);
	}

	/**
	 * Confirmation shown once the action has been recorded.
	 *
	 * @param string $action   opt_in or opt_out.
	 * @param array  $campaign Campaign record.
	 * @param string $email    Decoded address.
	 * @return void
	 */
	public static function render_result( $action, array $campaign, $email ) {
		$is_opt_out = Urls::ACTION_OPT_OUT === $action;

		$title = $is_opt_out
			? \__( 'You have been unsubscribed', 'aiplugin5055' )
			: \__( 'You are subscribed', 'aiplugin5055' );

		$message = $is_opt_out
			? \__( 'We have recorded your request. You will not receive further email from this campaign at this address.', 'aiplugin5055' )
			: \__( 'We have recorded your request. Thank you.', 'aiplugin5055' );

		self::layout(
			$title,
			function () use ( $campaign, $email, $title, $message ) {
				?>
				<h1 class="aiplugin5055-title"><?php echo \esc_html( $title ); ?></h1>
				<p class="aiplugin5055-intro"><?php echo \esc_html( $message ); ?></p>

				<dl class="aiplugin5055-summary">
					<dt><?php \esc_html_e( 'Email address', 'aiplugin5055' ); ?></dt>
					<dd><?php echo \esc_html( $email ); ?></dd>
					<dt><?php \esc_html_e( 'Campaign', 'aiplugin5055' ); ?></dt>
					<dd><?php echo \esc_html( $campaign['name'] ); ?></dd>
				</dl>

				<p class="aiplugin5055-scope">
					<?php \esc_html_e( 'Following this link again is safe and will not change anything further.', 'aiplugin5055' ); ?>
				</p>
				<?php
			}
		);
	}

	/**
	 * The single generic failure page.
	 *
	 * It never reveals which part of the code failed, nor whether an address
	 * is known to the site (PRD Section 36).
	 *
	 * @param int $status HTTP status code.
	 * @return void
	 */
	public static function render_error( $status = 200 ) {
		\status_header( (int) $status );

		$title = \__( 'This link is not valid', 'aiplugin5055' );

		self::layout(
			$title,
			function () use ( $title ) {
				?>
				<h1 class="aiplugin5055-title"><?php echo \esc_html( $title ); ?></h1>
				<p class="aiplugin5055-intro">
					<?php \esc_html_e( 'We could not process this link. It may be incomplete, or it may no longer be active. Nothing has been changed.', 'aiplugin5055' ); ?>
				</p>
				<p class="aiplugin5055-scope">
					<?php \esc_html_e( 'If you were trying to unsubscribe, please reply to the message you received and we will remove you.', 'aiplugin5055' ); ?>
				</p>
				<?php
			}
		);
	}

	/**
	 * Shared document wrapper.
	 *
	 * @param string   $title Document title.
	 * @param callable $body  Body renderer.
	 * @return void
	 */
	private static function layout( $title, callable $body ) {
		?>
<!doctype html>
<html <?php \language_attributes(); ?>>
<head>
	<meta charset="<?php echo \esc_attr( \get_bloginfo( 'charset' ) ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow, noarchive">
	<meta name="referrer" content="no-referrer">
	<title><?php echo \esc_html( $title . ' — ' . \get_bloginfo( 'name' ) ); ?></title>
	<?php \wp_head(); ?>
</head>
<body class="aiplugin5055-body">
	<main class="aiplugin5055-card">
		<?php $body(); ?>
		<p class="aiplugin5055-site"><?php echo \esc_html( \get_bloginfo( 'name' ) ); ?></p>
	</main>
	<?php \wp_footer(); ?>
</body>
</html>
		<?php
	}

	/**
		* Render confirmation form content only (for WordPress page content filter).
		*
		* @param string $action      opt_in or opt_out.
		* @param array  $campaign    Campaign record.
		* @param string $email       Decoded address.
		* @param string $action_code Normalized action code.
		* @return void
		*/
	public static function render_confirm_content( $action, array $campaign, $email, $action_code ) {
		$is_opt_out = Urls::ACTION_OPT_OUT === $action;

		$title = $is_opt_out
			? \__( 'Unsubscribe', 'aiplugin5055' )
			: \__( 'Confirm your subscription', 'aiplugin5055' );

		$intro = $is_opt_out
			? \__( 'You are about to unsubscribe from this campaign. No account, password or explanation is required.', 'aiplugin5055' )
			: \__( 'You are about to opt in to this campaign. No account or password is required.', 'aiplugin5055' );

		$button = $is_opt_out
			? \__( 'Unsubscribe', 'aiplugin5055' )
			: \__( 'Yes, subscribe me', 'aiplugin5055' );

		?>
		<div class="aiplugin5055-content">
			<h2 class="aiplugin5055-title"><?php echo \esc_html( $title ); ?></h2>
			<p class="aiplugin5055-intro"><?php echo \esc_html( $intro ); ?></p>

			<dl class="aiplugin5055-summary">
				<dt><?php \esc_html_e( 'Email address', 'aiplugin5055' ); ?></dt>
				<dd><?php echo \esc_html( $email ); ?></dd>
				<dt><?php \esc_html_e( 'Campaign', 'aiplugin5055' ); ?></dt>
				<dd><?php echo \esc_html( $campaign['name'] ); ?></dd>
			</dl>

			<p class="aiplugin5055-scope">
				<?php
				echo \esc_html(
					$is_opt_out
						? \__( 'This applies only to the campaign named above. Any other campaign you have acted on is unaffected.', 'aiplugin5055' )
						: \__( 'This applies only to the campaign named above.', 'aiplugin5055' )
				);
				?>
			</p>

			<form method="post" action="<?php echo \esc_url( Urls::action_url( $action, $action_code ) ); ?>" class="aiplugin5055-form" data-aiplugin5055-action-form>
				<input type="hidden" name="<?php echo \esc_attr( Urls::ACTION_VAR ); ?>" value="<?php echo \esc_attr( $action ); ?>">
				<input type="hidden" name="<?php echo \esc_attr( Urls::CODE_VAR ); ?>" value="<?php echo \esc_attr( $action_code ); ?>">
				<input type="hidden" name="aiplugin5055_confirm" value="1">
				<button type="submit" class="aiplugin5055-button<?php echo $is_opt_out ? ' aiplugin5055-button--danger' : ''; ?>">
					<?php echo \esc_html( $button ); ?>
				</button>
			</form>
		</div>
		<?php
	}

	/**
		* Render result content only (for WordPress page content filter).
		*
		* @param string $action   opt_in or opt_out.
		* @param array  $campaign Campaign record.
		* @param string $email    Decoded address.
		* @return void
		*/
	public static function render_result_content( $action, array $campaign, $email ) {
		$is_opt_out = Urls::ACTION_OPT_OUT === $action;

		$title = $is_opt_out
			? \__( 'You have been unsubscribed', 'aiplugin5055' )
			: \__( 'You are subscribed', 'aiplugin5055' );

		$message = $is_opt_out
			? \__( 'We have recorded your request. You will not receive further email from this campaign at this address.', 'aiplugin5055' )
			: \__( 'We have recorded your request. Thank you.', 'aiplugin5055' );

		?>
		<div class="aiplugin5055-content">
			<h2 class="aiplugin5055-title"><?php echo \esc_html( $title ); ?></h2>
			<p class="aiplugin5055-intro"><?php echo \esc_html( $message ); ?></p>

			<dl class="aiplugin5055-summary">
				<dt><?php \esc_html_e( 'Email address', 'aiplugin5055' ); ?></dt>
				<dd><?php echo \esc_html( $email ); ?></dd>
				<dt><?php \esc_html_e( 'Campaign', 'aiplugin5055' ); ?></dt>
				<dd><?php echo \esc_html( $campaign['name'] ); ?></dd>
			</dl>

			<p class="aiplugin5055-scope">
				<?php \esc_html_e( 'Following this link again is safe and will not change anything further.', 'aiplugin5055' ); ?>
			</p>
		</div>
		<?php
	}

	/**
		* Render error content only (for WordPress page content filter).
		*
		* @param int $status HTTP status code.
		* @return void
		*/
	public static function render_error_content( $status = 200 ) {
		if ( $status !== 200 ) {
			\status_header( (int) $status );
		}

		$title = \__( 'This link is not valid', 'aiplugin5055' );

		?>
		<div class="aiplugin5055-content">
			<h2 class="aiplugin5055-title"><?php echo \esc_html( $title ); ?></h2>
			<p class="aiplugin5055-intro">
				<?php \esc_html_e( 'We could not process this link. It may be incomplete, or it may no longer be active. Nothing has been changed.', 'aiplugin5055' ); ?>
			</p>
			<p class="aiplugin5055-scope">
				<?php \esc_html_e( 'If you were trying to unsubscribe, please reply to the message you received and we will remove you.', 'aiplugin5055' ); ?>
			</p>
		</div>
		<?php
	}
}
