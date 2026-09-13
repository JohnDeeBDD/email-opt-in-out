<?php
/**
 * Plugin Name: Email Opt-In / Opt-Out Management Plugin
 * Plugin URI: https://aiplugin.dev/ai-plugin/TRuawh0EAq
 * Description: Records explicit per-campaign opt-in and opt-out decisions for recipients of an external email list, creating the WordPress user only when someone explicitly acts.
 * Version: 12
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: JohnDee
 * License: Copyright (C) AI_PLUGIN_DEV 2026. ALL RIGHTS RESERVED. THIS IS NOT FREE SOFTWARE.
 *
 * @package aiplugin5055
 */

namespace aiplugin5055;

use aiplugin5055\Admin\CampaignsScreen;
use aiplugin5055\Admin\UserProfileSection;
use aiplugin5055\Frontend\ActionEndpoint;
use aiplugin5055\Frontend\PageContentFilter;
use aiplugin5055\Frontend\SilentOptIn;
use aiplugin5055\Meta\MetaKeys;
use aiplugin5055\Rest\CampaignsController;
use aiplugin5055\Rest\StateController;
use aiplugin5055\Rest\TrackingCodeController;
use aiplugin5055\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Version used for asset cache busting. */
const AIPLUGIN5055_ASSET_VERSION = '1.0.0';

$aiplugin5055_path = \plugin_dir_path( __FILE__ );

/*
 * The shared action-code library first. It is plain PHP with no WordPress
 * dependency, and it is the single definition of how a tracking code is built,
 * parsed and turned into a link. The Gmail Campaign Manager on this machine
 * loads this same directory directly, so that the codes it mails and the codes
 * this plugin decodes cannot come from two implementations that drift apart.
 * See library/README.md.
 */
require_once $aiplugin5055_path . 'src/library/autoload.php';

require_once $aiplugin5055_path . 'src/aiplugin5055/Support/Settings.php';
require_once $aiplugin5055_path . 'src/aiplugin5055/Support/Urls.php';
require_once $aiplugin5055_path . 'src/aiplugin5055/Support/RateLimiter.php';
require_once $aiplugin5055_path . 'src/aiplugin5055/Support/RejectedCodeLog.php';
require_once $aiplugin5055_path . 'src/aiplugin5055/Codec/EmailCodec.php';
require_once $aiplugin5055_path . 'src/aiplugin5055/Codec/ActionCode.php';
require_once $aiplugin5055_path . 'src/aiplugin5055/Campaigns/CampaignCodeGenerator.php';
require_once $aiplugin5055_path . 'src/aiplugin5055/Campaigns/CampaignRepository.php';
require_once $aiplugin5055_path . 'src/aiplugin5055/Meta/MetaKeys.php';
require_once $aiplugin5055_path . 'src/aiplugin5055/Users/UserProvisioner.php';
require_once $aiplugin5055_path . 'src/aiplugin5055/Actions/CampaignState.php';
require_once $aiplugin5055_path . 'src/aiplugin5055/Actions/ActionRecorder.php';
require_once $aiplugin5055_path . 'src/aiplugin5055/Frontend/ActionPageView.php';
require_once $aiplugin5055_path . 'src/aiplugin5055/Frontend/ActionEndpoint.php';
require_once $aiplugin5055_path . 'src/aiplugin5055/Frontend/SilentOptIn.php';
require_once $aiplugin5055_path . 'src/aiplugin5055/Frontend/PageContentFilter.php';
require_once $aiplugin5055_path . 'src/aiplugin5055/Admin/CampaignsView.php';
require_once $aiplugin5055_path . 'src/aiplugin5055/Admin/CampaignsScreen.php';
require_once $aiplugin5055_path . 'src/aiplugin5055/Admin/UserProfileSection.php';
require_once $aiplugin5055_path . 'src/aiplugin5055/Rest/Permissions.php';
require_once $aiplugin5055_path . 'src/aiplugin5055/Rest/TrackingCodeController.php';
require_once $aiplugin5055_path . 'src/aiplugin5055/Rest/CampaignsController.php';
require_once $aiplugin5055_path . 'src/aiplugin5055/Rest/StateController.php';

/*
 * Activation: settle the encoding configuration and register the public
 * endpoints before flushing, so the rewrite rules exist to be flushed.
 */
\register_activation_hook(
	__FILE__,
	function () {
		Settings::bootstrap();
		ActionEndpoint::register_rewrite_rules();
		\flush_rewrite_rules();
	}
);

\register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

/* Public opt-out endpoint. */
\add_action( 'init', array( ActionEndpoint::class, 'register_rewrite_rules' ) );
\add_filter( 'query_vars', array( ActionEndpoint::class, 'register_query_vars' ) );
\add_action(
	'template_redirect',
	function () {
		( new ActionEndpoint() )->handle();
	},
	0 // Ahead of redirect_canonical, which would otherwise rewrite these URLs.
);

/*
 * Silent opt-in. Any front-end URL carrying an action code records the opt-in
 * for the encoded address; the request itself is left alone, so the visitor
 * sees only the page they asked for. Runs after the unsubscribe endpoint,
 * which exits before this on its own requests.
 */
\add_action(
	'template_redirect',
	function () {
		( new SilentOptIn() )->handle();
	},
	1
);

/*
 * Keep the plugin's user metadata out of the default custom-fields UI. These
 * values record historical explicit actions; they are not editable preferences.
 */
\add_filter( 'is_protected_meta', array( MetaKeys::class, 'protect' ), 10, 3 );

/* Campaign management screen, under Tools. */
\add_action(
	'admin_menu',
	function () {
		( new CampaignsScreen() )->register_menu();
	}
);

\add_action(
	'admin_post_' . CampaignsScreen::ACTION_CREATE,
	function () {
		( new CampaignsScreen() )->handle_create();
	}
);

\add_action(
	'admin_post_' . CampaignsScreen::ACTION_UPDATE,
	function () {
		( new CampaignsScreen() )->handle_update();
	}
);

\add_action(
	'admin_post_' . CampaignsScreen::ACTION_DELETE,
	function () {
		( new CampaignsScreen() )->handle_delete();
	}
);

\add_action(
	'admin_post_' . CampaignsScreen::ACTION_SAVE_SETTINGS,
	function () {
		( new CampaignsScreen() )->handle_save_settings();
	}
);

/* Content filter for the configured unsubscribe page. */
\add_filter(
	'the_content',
	array( PageContentFilter::class, 'filter_content' ),
	20
);

/* Per-campaign state on the user edit screen. */
foreach ( array( 'show_user_profile', 'edit_user_profile' ) as $aiplugin5055_profile_hook ) {
	\add_action(
		$aiplugin5055_profile_hook,
		function ( $user ) {
			( new UserProfileSection() )->render( $user );
		}
	);
}

/* Administrator-only REST API. */
\add_action(
	'rest_api_init',
	function () {
		( new TrackingCodeController() )->register_routes();
		( new CampaignsController() )->register_routes();
		( new StateController() )->register_routes();
	}
);

/* Frontend assets: only on the public unsubscribe page. */
\add_action(
	'wp_enqueue_scripts',
	function () {
		if ( '' === ActionEndpoint::current_action() ) {
			return;
		}

		$base_url = \plugin_dir_url( __FILE__ );

		\wp_enqueue_style(
			'aiplugin5055-public',
			$base_url . 'src/css/public.css',
			array(),
			AIPLUGIN5055_ASSET_VERSION
		);

		// The Script Modules API landed in WordPress 6.5. The JavaScript is
		// progressive enhancement only, so an older site degrades rather than
		// fataling.
		if ( function_exists( 'wp_enqueue_script_module' ) ) {
			\wp_enqueue_script_module(
				'aiplugin5055-frontend',
				$base_url . 'src/js/frontend.js',
				array(),
				AIPLUGIN5055_ASSET_VERSION
			);
		}
	}
);

/* Admin assets: only on the campaign management screen. */
\add_action(
	'admin_enqueue_scripts',
	function ( $hook_suffix ) {
		if ( 'tools_page_' . CampaignsScreen::SLUG !== $hook_suffix ) {
			return;
		}

		$base_url = \plugin_dir_url( __FILE__ );

		\wp_enqueue_style(
			'aiplugin5055-admin',
			$base_url . 'src/css/admin.css',
			array(),
			AIPLUGIN5055_ASSET_VERSION
		);

		if ( function_exists( 'wp_enqueue_script_module' ) ) {
			\wp_enqueue_script_module(
				'aiplugin5055-admin',
				$base_url . 'src/js/admin.js',
				array(),
				AIPLUGIN5055_ASSET_VERSION
			);
		}
	}
);

/* A direct link from the plugins list to the campaign screen. */
\add_filter(
	'plugin_action_links_' . \plugin_basename( __FILE__ ),
	function ( $links ) {
		$links[] = '<a href="' . \esc_url( CampaignsScreen::url() ) . '">' . \esc_html__( 'Campaigns', 'aiplugin5055' ) . '</a>';

		return $links;
	}
);

require_once $aiplugin5055_path . 'src/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://aiplugin.dev/wp-content/aiplugins/aiplugin5055_details.json',
	__FILE__, // Full path to the main plugin file or functions.php.
	'aiplugin5055'
);
