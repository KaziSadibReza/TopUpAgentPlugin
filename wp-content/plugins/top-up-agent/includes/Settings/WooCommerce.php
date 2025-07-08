<?php

namespace TopUpAgent\Settings;

defined('ABSPATH') || exit;

class WooCommerce
{
    /**
     * @var array
     */
    private $settings;

    /**
     * WooCommerce constructor.
     */
    public function __construct()
    {
        $this->settings = get_option('tua_settings_woocommerce', array());

        /**
         * @see https://developer.wordpress.org/reference/functions/register_setting/#parameters
         */
        $args = array(
            'sanitize_callback' => array($this, 'sanitize')
        );

        // Register the initial settings group.
        register_setting('tua_settings_group_woocommerce', 'tua_settings_woocommerce', $args);
        
        // Initialize essential WooCommerce integration settings
        $this->initEssentialSettings();
    }

    /**
     * @param array $settings
     *
     * @return array
     */
    public function sanitize($settings)
    {
        if ($settings === null) {
            return array();
        }
        
        return $settings;
    }

    /**
     * Initialize essential WooCommerce integration settings.
     *
     * @return void
     */
    private function initEssentialSettings()
    {
        // Set default values for essential WooCommerce integration
        $defaultSettings = array(
            'tua_auto_delivery' => '1',
            'tua_license_key_delivery_options' => array(
                'wc-completed' => array('send' => '1')
            ),
            'tua_enable_stock_manager' => '1'
        );

        // Only set defaults if no settings exist
        if (empty($this->settings)) {
            update_option('tua_settings_woocommerce', $defaultSettings);
            $this->settings = $defaultSettings;
        }
    }
}
