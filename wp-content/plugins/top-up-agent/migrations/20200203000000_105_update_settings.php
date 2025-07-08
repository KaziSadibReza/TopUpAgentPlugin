<?php

defined('ABSPATH') || exit;

/**
 * @var string $migrationMode
 */

use TopUpAgent\Migration;

/**
 * Upgrade
 */
if ($migrationMode === Migration::MODE_UP) {
    $defaultSettingsWooCommerce = array(
        'tua_license_key_delivery_options' => array(
            'wc-completed' => array(
                'send' => '1'
            )
        )
    );

    update_option('tua_settings_woocommerce', $defaultSettingsWooCommerce);
}

/**
 * Downgrade
 */
if ($migrationMode === Migration::MODE_DOWN) {
    delete_option('tua_settings_woocommerce');
}
