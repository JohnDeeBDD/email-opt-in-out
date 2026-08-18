<?php
/**
 * Finds or creates the WordPress user behind a decoded email address
 * (PRD Sections 6, 7, 32).
 *
 * Nothing in this class runs unless an explicit opt-in or opt-out has already
 * happened. Generating a code, sending an email or loading a landing page must
 * never reach it.
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Users;

use aiplugin5055\Codec\EmailCodec;
use aiplugin5055\Support\Settings;

class UserProvisioner {

	/**
	 * Resolve an address to a WordPress user, creating one if necessary.
	 *
	 * @param string $email Canonical email address.
	 * @return array|\WP_Error {
	 *     @type int  $user_id User ID.
	 *     @type bool $created Whether this call created the account.
	 * }
	 */
	public static function find_or_create( $email ) {
		$email = EmailCodec::canonicalize( $email );

		if ( ! \is_email( $email ) ) {
			return new \WP_Error( 'aiplugin5055_invalid_email', \__( 'Invalid email address.', 'aiplugin5055' ) );
		}

		$existing = \get_user_by( 'email', $email );

		if ( $existing instanceof \WP_User ) {
			// Existing accounts are reused, never duplicated, and nothing about
			// them is touched beyond the campaign metadata (PRD Section 31).
			return array(
				'user_id' => (int) $existing->ID,
				'created' => false,
			);
		}

		$user_id = self::create( $email );

		if ( \is_wp_error( $user_id ) ) {
			return $user_id;
		}

		return array(
			'user_id' => (int) $user_id,
			'created' => true,
		);
	}

	/**
	 * Create a non-privileged account for an address that has none.
	 *
	 * @param string $email Canonical email address.
	 * @return int|\WP_Error
	 */
	private static function create( $email ) {
		$userdata = array(
			'user_login'   => self::generate_username( $email ),
			'user_email'   => $email,
			// Strong, random, never displayed and never emailed (PRD Section 32).
			'user_pass'    => \wp_generate_password( 32, true, true ),
			'role'         => Settings::new_user_role(),
			'show_admin_bar_front' => 'false',
		);

		$userdata['display_name'] = $userdata['user_login'];
		$userdata['nickname']     = $userdata['user_login'];

		/**
		 * Whether WordPress should send its "new user" notifications.
		 *
		 * Off by default: the recipient never registered for an account
		 * (PRD Section 32).
		 *
		 * @param bool   $send  Whether to notify.
		 * @param string $email Recipient address.
		 */
		$notify = (bool) \apply_filters( 'aiplugin5055_send_new_user_notification', false, $email );

		if ( ! $notify ) {
			\add_filter( 'wp_send_new_user_notification_to_user', '__return_false', 99 );
			\add_filter( 'wp_send_new_user_notification_to_admin', '__return_false', 99 );
		}

		$user_id = \wp_insert_user( $userdata );

		if ( ! $notify ) {
			\remove_filter( 'wp_send_new_user_notification_to_user', '__return_false', 99 );
			\remove_filter( 'wp_send_new_user_notification_to_admin', '__return_false', 99 );
		}

		return $user_id;
	}

	/**
	 * Derive a valid, unused username from the address.
	 *
	 * @param string $email Canonical email address.
	 * @return string
	 */
	private static function generate_username( $email ) {
		$local = substr( $email, 0, strpos( $email, '@' ) );
		$base  = \sanitize_user( $local, true );
		$base  = strtolower( trim( $base, '.-_' ) );

		if ( '' === $base ) {
			$base = 'recipient';
		}

		$base = substr( $base, 0, 50 );

		if ( ! \username_exists( $base ) ) {
			return $base;
		}

		for ( $suffix = 2; $suffix <= 25; $suffix++ ) {
			$candidate = $base . '-' . $suffix;

			if ( ! \username_exists( $candidate ) ) {
				return $candidate;
			}
		}

		do {
			$candidate = $base . '-' . strtolower( \wp_generate_password( 8, false, false ) );
		} while ( \username_exists( $candidate ) );

		return $candidate;
	}
}
