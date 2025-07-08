<?php
/**
 * Main plugin file.
 * PHP Version: 5.6
 *
 * @category WordPress
 * @package  TopUpAgent
 * @author   Top Up Agent Team
 * @license  GNUv3 https://www.gnu.org/licenses/gpl-3.0.en.html
 * @link     https://www.wpexperts.io/
 */

namespace TopUpAgent;

use TopUpAgent\Abstracts\Singleton;
use TopUpAgent\Integrations\WooCommerce\Controller;
use TopUpAgent\Controllers\ApiKey as ApiKeyController;
use TopUpAgent\Controllers\License as LicenseController;
use TopUpAgent\Controllers\Dropdowns as DropdownsController;


use TopUpAgent\Enums\LicenseStatus;

defined('ABSPATH') || exit;

/**
 * TopUpAgent
 *
 * @category WordPress
 * @package  TopUpAgent
 * @author   Top Up Agent Team
 * @license  GNUv3 https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version  Release: <1.0.0>
 * @link     https://www.wpexperts.io/
 */
final class Main extends Singleton
{
    /**
     * Main constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->_defineConstants();
        $this->_initHooks();
        
        add_action('init', array($this, 'init'));

        new Api\Authentication();
    }

    /**
     * Define plugin constants.
     *
     * @return void
     */
    private function _defineConstants()
    {
        if (!defined('ABSPATH_LENGTH')) {
            define('ABSPATH_LENGTH', strlen(ABSPATH));
        }

        define('TUA_ABSPATH',         dirname(TUA_PLUGIN_FILE) . '/');
        define('TUA_PLUGIN_BASENAME', plugin_basename(TUA_PLUGIN_FILE));

        // Directories
        define('TUA_ASSETS_DIR',     TUA_ABSPATH       . 'assets/');
        define('TUA_LOG_DIR',        TUA_ABSPATH       . 'logs/');
        define('TUA_TEMPLATES_DIR',  TUA_ABSPATH       . 'templates/');
        if (!defined('TUA_MIGRATIONS_DIR')) {
            define('TUA_MIGRATIONS_DIR', TUA_ABSPATH       . 'migrations/');
        }
        define('TUA_CSS_DIR',        TUA_ASSETS_DIR    . 'css/');

        // URL's
        define('TUA_ASSETS_URL', TUA_PLUGIN_URL . 'assets/');
        define('TUA_ETC_URL',    TUA_ASSETS_URL . 'etc/');
        define('TUA_CSS_URL',    TUA_ASSETS_URL . 'css/');
        define('TUA_JS_URL',     TUA_ASSETS_URL . 'js/');
        define('TUA_IMG_URL',    TUA_ASSETS_URL . 'img/');
    }
    /**
     * Include JS and CSS files.
     *
     * @param string $hook
     *
     * @return void
     */
    public function adminEnqueueScripts($hook)
    {
        // Select2
        wp_register_style(
            'tua_select2_cdn',
            'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css'
        );
        wp_register_script(
            'tua_select2_cdn',
            'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js'
        );
        wp_register_style(
            'tua_select2',
            TUA_CSS_URL . 'select2.css'
        );

        // CSS
        wp_enqueue_style(
            'tua_admin_css',
            TUA_CSS_URL . 'main.css',
            array(),
            TUA_VERSION
        );

        $current_screen = get_current_screen();

        if ( $hook === 'product_page_tua_licenses' || $current_screen->id === 'shop_order' || $current_screen->id === 'woocommerce_page_wc-orders' ) {
            // JavaScript
            wp_enqueue_script(
                'tua_admin_js',
                TUA_JS_URL . 'script.js',
                array(),
                TUA_VERSION
            );

            // Script localization
            wp_localize_script(
                'tua_admin_js',
                'license',
                array(
                    'show'     => wp_create_nonce('tua_show_license_key'),
                    'show_all' => wp_create_nonce('tua_show_all_license_keys'),
                )
            );
        }

        // jQuery UI
        wp_register_style(
            'tua-jquery-ui-datepicker',
            'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css',
            array(),
            '1.12.1'
        );
        if ( $hook === 'woocommerce_page_wc-settings' && isset( $_GET['tab'] ) && $_GET['tab'] === 'tua_settings' ) {
            $extra_css = 'p.submit:not(.wrap.tua p.submit){display:none;}';
            wp_add_inline_style('tua_admin_css', $extra_css);
        }
        if ($hook === 'product_page_tua_licenses' || ( $hook === 'woocommerce_page_wc-settings' && isset( $_GET['tab'] ) && $_GET['tab'] === 'tua_settings' ) ) {
            wp_enqueue_script('tua_select2_cdn');
            wp_enqueue_style('tua_select2_cdn');
            wp_enqueue_style('tua_select2');
            wp_enqueue_script('select2');
        }

        // Licenses page
        if ($hook === 'product_page_tua_licenses') {
            wp_enqueue_script('tua_licenses_page_js', TUA_JS_URL . 'licenses_page.js');

            wp_localize_script(
                'tua_licenses_page_js',
                'i18n',
                array(
                    'placeholderSearchOrders'    => __('Search by order ID or customer email', 'top-up-agent'),
                    'placeholderSearchProducts'  => __('Search by product ID or product name', 'top-up-agent'),
                    'placeholderSearchUsers'     => __('Search by user login, name or email', 'top-up-agent')
                )
            );

            wp_localize_script(
                'tua_licenses_page_js',
                'security',
                array(
                    'dropdownSearch' => wp_create_nonce('tua_dropdown_search')
                )
            );
        }

        // Settings page
        if ( $hook === 'woocommerce_page_wc-settings' && isset( $_GET['tab'] ) && $_GET['tab'] === 'tua_settings' ) {
            wp_enqueue_media();
            wp_enqueue_script('tua_select2_cdn');
            wp_enqueue_style('tua_select2_cdn');
            wp_enqueue_script('select2');
            wp_enqueue_script('tua_settings_page_js', TUA_JS_URL . 'settings_page.js');
            wp_localize_script(
                'tua_settings_page_js',
                'security',
                array(
                    'dropdownSearch' => wp_create_nonce('tua_dropdown_search'),
                    'ajaxurl' => admin_url('admin-ajax.php')
                )
            );
        }
    }

    /**
     * Add additional links to the plugin row meta.
     *
     * @param array  $links Array of already present links
     * @param string $file  File name
     *
     * @return array
     */
    public function pluginRowMeta($links, $file)
    {
        if (strpos($file, 'top-up-agent.php') !== false ) {
            $newLinks = array(
                'github' => sprintf(
                    '<a href="%s" target="_blank">%s</a>',
                    'https://github.com/KaziSadibReza/TopUpAgentPlugin',
                    'GitHub'
                )
            );

            $links = array_merge($links, $newLinks);
        }

        return $links;
    }

    /**
     * Hook into actions and filters.
     *
     * @return void
     */
    private function _initHooks()
    {
        register_activation_hook(
            TUA_PLUGIN_FILE,
            array('\TopUpAgent\Setup', 'install')
        );
        register_deactivation_hook(
            TUA_PLUGIN_FILE,
            array('\TopUpAgent\Setup', 'deactivate')
        );
        register_uninstall_hook(
            TUA_PLUGIN_FILE,
            array('\TopUpAgent\Setup', 'uninstall')
        );

        add_action('admin_enqueue_scripts', array($this, 'adminEnqueueScripts'));
        add_filter('plugin_row_meta', array($this, 'pluginRowMeta'), 10, 2);
    }

    /**
     * Init TopUpAgent when WordPress Initialises.
     *
     * @return void
     */
    public function init()
    {
        
        Setup::migrate();

        $this->publicHooks();

        new Crypto();
        new Import();
        new Export();
        new AdminMenus();
        new AdminNotice();
        new LicenseController();
        new DropdownsController();
        new ApiKeyController();
        new Api\Setup();

        if ($this->isPluginActive('woocommerce/woocommerce.php')) {
            new Integrations\WooCommerce\Controller();
        }

        if (Settings::get('tua_allow_duplicates')) {
            add_filter('tua_duplicate', '__return_false', PHP_INT_MAX);
        }
    }

    /**
     * Defines all public hooks
     *
     * @return void
     */
    protected function publicHooks()
    {
        add_filter(
            'tua_license_keys_table_heading',
            function($text) {
                $default = __('Your license key(s)', 'top-up-agent');

                if (!$text) {
                    return $default;
                }

                return sanitize_text_field($text);
            },
            10,
            1
        );

        add_filter(
            'tua_license_keys_table_valid_until',
            function($text) {
                $default = __('Valid until', 'top-up-agent');

                if (!$text) {
                    return $default;
                }

                return sanitize_text_field($text);
            },
            10,
            1
        );
    }

    /**
     * Checks if a plugin is active.
     *
     * @param string $pluginName
     *
     * @return bool
     */
    private function isPluginActive($pluginName)
    {
        return in_array($pluginName, apply_filters('active_plugins', get_option('active_plugins')));
    }
}