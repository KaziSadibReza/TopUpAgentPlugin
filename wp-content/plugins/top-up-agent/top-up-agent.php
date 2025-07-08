<?php
/**
 * Plugin Name: Top Up Agent
 * Plugin URI: https://github.com/KaziSadibReza/TopUpAgentPlugin
 * Description: This plugin automates the Unipin top-up redemption process and woocommerce license management.
 * Version: 1.0.0
 * Author: Kazi Sadib Reza
 * Author URI: https://github.com/KaziSadibReza
 * Requires PHP: 7.0
 * WC requires at least: 5.0
 * WC tested up to: 9.6
 */

namespace TopUpAgent;

defined('ABSPATH') || exit;

// Include autoloader only if not already included
if (!class_exists('ComposerAutoloaderInitTopUpAgent')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/functions/tua-core-functions.php';
require_once __DIR__ . '/functions/tua-license-functions.php';
require_once __DIR__ . '/functions/tua-meta-functions.php';

// Define TUA_PLUGIN_FILE.
if (!defined('TUA_PLUGIN_FILE')) {
    define('TUA_PLUGIN_FILE', __FILE__);
    define('TUA_PLUGIN_DIR', __DIR__);
}

// Define TUA_PLUGIN_URL.
if (!defined('TUA_PLUGIN_URL')) {
    define('TUA_PLUGIN_URL', plugins_url('', __FILE__) . '/');
}

// Define TUA_VERSION.
if (!defined('TUA_VERSION')) {
    define('TUA_VERSION', '1.0.0');
}

// Define TUA_MIGRATIONS_DIR.
if (!defined('TUA_MIGRATIONS_DIR')) {
    define('TUA_MIGRATIONS_DIR', __DIR__ . '/migrations/');
}
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
} );

/**
 * Main instance of TopUpAgent.
 *
 * Returns the main instance of SN to prevent the need to use globals.
 *
 * @return Main
 */
function tua()
{
    return Main::instance();
}

// Global for backwards compatibility.
$GLOBALS['top-up-agent'] = tua();