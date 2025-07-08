<?php defined('ABSPATH') || exit;
/**
 * @var string $migrationMode
 */
use TopUpAgent\Migration;
/**
 * Upgrade
 */
if ($migrationMode === Migration::MODE_UP) {
    $tua_settings_general = get_option('tua_settings_general', array());
    $general_array = array(
        'tua_enable_my_account_endpoint' => !empty($tua_settings_general['tua_enable_my_account_endpoint']) ? $tua_settings_general['tua_enable_my_account_endpoint'] : 1,
        'tua_allow_users_to_activate' => !empty($tua_settings_general['tua_allow_users_to_activate']) ? $tua_settings_general['tua_allow_users_to_activate'] : 1,
        'tua_allow_users_to_deactivate' => !empty($tua_settings_general['tua_allow_users_to_deactivate']) ? $tua_settings_general['tua_allow_users_to_deactivate'] : 1,
        'tua_auto_delivery' => !empty($tua_settings_general['tua_auto_delivery']) ? $tua_settings_general['tua_auto_delivery'] : 1,
        'tua_enable_stock_manager' => !empty($tua_settings_general['tua_enable_stock_manager']) ? $tua_settings_general['tua_enable_stock_manager'] : 1
    );
    $tua_settings_orderStatus = get_option('tua_settings_order_status', array());
    $tua_settings_woocommerce = get_option('tua_settings_woocommerce', array());
    $tua_settings_woocommerce = array_merge($general_array, $tua_settings_orderStatus, $tua_settings_woocommerce);
    update_option('tua_settings_woocommerce', $tua_settings_woocommerce);
}

/**
 * Downgrade
 */
if ($migrationMode === Migration::MODE_DOWN) {
    delete_option('tua_settings_woocommerce');
}
