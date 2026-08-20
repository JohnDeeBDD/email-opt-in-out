<?php
/**
 * Entry point for the shared action-code library.
 *
 * Everything under `library/` is plain PHP: no WordPress functions, no
 * globals, no side effects beyond declaring these five classes. That is what
 * lets a process outside WordPress — the Gmail Campaign Manager, running on
 * this same machine — build the codes the site will decode by calling the
 * site's own implementation, rather than keeping a copy of it.
 *
 * From another application:
 *
 *     require_once '/var/www/html/wp-content/plugins/aiplugin5055/src/library/autoload.php';
 *
 *     $settings = new aiplugin5055\Library\SiteSettings( $alphabet, $secret, 3 );
 *     $code     = aiplugin5055\Library\ActionCode::build( 'A7K2Q', 'john@example.com', $settings );
 *
 * See library/README.md. Requiring this file twice is harmless.
 *
 * @package aiplugin5055\Library
 */

/**
 * Contract version of the library.
 *
 * Bump the minor part for additions, the major part for a change that would
 * make an existing consumer produce a different code or fail to load. A
 * consumer can require a minimum, which is how the campaign manager refuses to
 * mail links built against a plugin it does not understand.
 */
if ( ! defined( 'AIPLUGIN5055_LIBRARY_VERSION' ) ) {
	define( 'AIPLUGIN5055_LIBRARY_VERSION', '1.0.0' );
}

require_once __DIR__ . '/src/SiteSettings.php';
require_once __DIR__ . '/src/CampaignCode.php';
require_once __DIR__ . '/src/EmailCodec.php';
require_once __DIR__ . '/src/ActionCode.php';
require_once __DIR__ . '/src/ActionUrls.php';
