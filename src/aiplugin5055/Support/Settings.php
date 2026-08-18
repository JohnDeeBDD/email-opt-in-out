<?php
/**
 * Plugin configuration: the site-specific encoding alphabet and the site secret.
 *
 * Both values are generated once, on demand, and then never change. Existing
 * action codes would stop decoding if the alphabet were regenerated, and the
 * check values of already-mailed codes would stop verifying if the secret were
 * rotated, so both are treated as immutable for the life of the install.
 *
 * @package aiplugin5055
 */

namespace aiplugin5055\Support;

class Settings {

	/** Option holding the whole settings array. */
	const OPTION = 'aiplugin5055_settings';

	/** Option holding the unsubscribe page configuration. */
	const PAGES_OPTION = 'aiplugin5055_pages';

	/**
	 * Unambiguous, URL-safe, single-case alphabet (PRD Section 13 / 16).
	 * 32 characters: A-Z without I and O, plus 2-9. No characters that a
	 * reader can confuse (O/0, I/1) and none that require percent-encoding.
	 */
	const BASE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

	/** Length of the HMAC check value appended to an action code. */
	const CHECK_VALUE_LENGTH = 3;

	/** @var array|null Runtime cache. */
	private static $cache = null;

	/**
	 * Return the full settings array, creating it on first use.
	 *
	 * @return array
	 */
	public static function get() {
		if ( is_array( self::$cache ) ) {
			return self::$cache;
		}

		$stored = \get_option( self::OPTION, array() );

		if ( ! self::is_valid( $stored ) ) {
			$stored = self::create();
		}

		self::$cache = $stored;

		return $stored;
	}

	/**
	 * The site-specific shuffled alphabet used to encode email addresses.
	 *
	 * @return string
	 */
	public static function alphabet() {
		$settings = self::get();

		return $settings['alphabet'];
	}

	/**
	 * The site secret used to key action-code check values.
	 *
	 * @return string
	 */
	public static function secret() {
		$settings = self::get();

		return $settings['secret'];
	}

	/**
	 * Number of check-value characters appended to an action code.
	 *
	 * Returning 0 disables the check value entirely (PRD Section 16 makes it
	 * RECOMMENDED, not mandatory); the accepted risks of Section 19 then apply
	 * in full.
	 *
	 * @return int
	 */
	public static function check_value_length() {
		$length = (int) \apply_filters( 'aiplugin5055_check_value_length', self::CHECK_VALUE_LENGTH );

		return max( 0, min( 8, $length ) );
	}

	/**
	 * Role assigned to users created by an explicit opt-in or opt-out.
	 *
	 * Guarded so that a misconfiguration cannot hand out a privileged role.
	 *
	 * @return string
	 */
	public static function new_user_role() {
		$role = \apply_filters( 'aiplugin5055_new_user_role', \get_option( 'default_role', 'subscriber' ) );
		$role = \is_string( $role ) ? \sanitize_key( $role ) : '';

		if ( '' === $role || ! \get_role( $role ) ) {
			$role = 'subscriber';
		}

		$capabilities = \get_role( $role );

		// Never let an explicit action mint a privileged account (PRD Section 32).
		if ( $capabilities && ( ! empty( $capabilities->capabilities['edit_posts'] ) || ! empty( $capabilities->capabilities['manage_options'] ) ) ) {
			$role = 'subscriber';
		}

		return $role;
	}

	/**
		* Capability required for every administrative operation (PRD Section 18).
		*
		* @return string
		*/
	public static function admin_capability() {
		$capability = \apply_filters( 'aiplugin5055_admin_capability', 'manage_options' );

		return \is_string( $capability ) && '' !== $capability ? $capability : 'manage_options';
	}

	/**
		* The WordPress page that renders the unsubscribe flow, if one is set.
		*
		* Opt-in has no page: it happens silently on any URL carrying a code.
		*
		* @return int Page ID, or 0 when no page is configured.
		*/
	public static function opt_out_page() {
		$pages = \get_option( self::PAGES_OPTION, array() );

		return isset( $pages['opt_out_page'] ) ? (int) $pages['opt_out_page'] : 0;
	}

	/**
		* Set the WordPress page that renders the unsubscribe flow.
		*
		* @param int $opt_out_page_id Page ID, or 0 for none.
		* @return bool
		*/
	public static function update_opt_out_page( $opt_out_page_id ) {
		return \update_option( self::PAGES_OPTION, array( 'opt_out_page' => (int) $opt_out_page_id ) );
	}

	/**
	 * Create and persist the settings on first use.
	 *
	 * @return array
	 */
	private static function create() {
		$settings = array(
			'alphabet'   => self::shuffle_alphabet( self::BASE_ALPHABET ),
			'secret'     => \wp_generate_password( 64, true, true ),
			'created_at' => \gmdate( 'c' ),
		);

		// add_option() does not overwrite, so a parallel request cannot clobber
		// an alphabet that another request has already handed out.
		if ( ! \add_option( self::OPTION, $settings, '', 'yes' ) ) {
			$existing = \get_option( self::OPTION, array() );

			if ( self::is_valid( $existing ) ) {
				return $existing;
			}

			\update_option( self::OPTION, $settings );
		}

		return $settings;
	}

	/**
	 * @param mixed $settings Candidate settings array.
	 * @return bool
	 */
	private static function is_valid( $settings ) {
		if ( ! is_array( $settings ) || empty( $settings['alphabet'] ) || empty( $settings['secret'] ) ) {
			return false;
		}

		$alphabet = (string) $settings['alphabet'];

		return strlen( $alphabet ) === 32 && count( array_unique( str_split( $alphabet ) ) ) === 32;
	}

	/**
	 * Cryptographically shuffle the base alphabet (Fisher-Yates).
	 *
	 * @param string $alphabet Base alphabet.
	 * @return string
	 */
	private static function shuffle_alphabet( $alphabet ) {
		$characters = str_split( $alphabet );

		for ( $i = count( $characters ) - 1; $i > 0; $i-- ) {
			$j                = random_int( 0, $i );
			$swap             = $characters[ $i ];
			$characters[ $i ] = $characters[ $j ];
			$characters[ $j ] = $swap;
		}

		return implode( '', $characters );
	}

	/**
	 * Make sure the configuration exists (called on activation).
	 *
	 * @return void
	 */
	public static function bootstrap() {
		self::get();
	}
}
