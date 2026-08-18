<?php
/**
 * Plugin Name: WordPress Email Opt-In / Opt-Out Management Plugin
 * Plugin URI: https://aiplugin.dev/ai-plugin/TRuawh0EAq
 * Description: 
 * Version: 2
 * Author: JohnDee
 * License: Copyright (C) AI_PLUGIN_DEV 2026. ALL RIGHTS RESERVED. THIS IS NOT FREE SOFTWARE.
 */

namespace aiplugin5055;

/*
Example PHP require:
require_once plugin_dir_path(__FILE__) . 'src/ExampleClass.php';
*/


\add_action('wp_enqueue_scripts', function(){
        $base = \plugin_dir_url(__FILE__) . '/src/js';
        // Register leaf modules (no dependencies on other theme modules)
        \wp_register_script_module('aiplugin5055/example-js-module.js', "$base/example-js-module.js");

        // Enqueue the entry point (this is what generates the <script type="module"> tag)
        \wp_enqueue_script_module('aiplugin5055', "$base/aiplugin5055.js", [
            'aiplugin5055/example-js-module.js',
        ]);

});


require_once plugin_dir_path(__FILE__) . "src/plugin-update-checker/plugin-update-checker.php";
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://aiplugin.dev/wp-content/aiplugins/aiplugin5055_details.json',
	__FILE__, //Full path to the main plugin file or functions.php.
	'aiplugin5055'
);