<?php

namespace TopUpAgent\Controllers;

use Exception;
use TopUpAgent\AdminMenus;
use TopUpAgent\AdminNotice;
use TopUpAgent\Enums\LicenseSource;
use TopUpAgent\Enums\LicenseStatus as LicenseStatusEnum;
use TopUpAgent\Models\Resources\License as LicenseResourceModel;
use TopUpAgent\Repositories\Resources\License as LicenseResourceRepository;

defined('ABSPATH') || exit;

class License
{
    /**
     * License constructor.
     */
    public function __construct()
    {
        // Admin POST requests
        add_action('admin_post_tua_import_license_keys',   array($this, 'importLicenseKeys'),   10);
        add_action('admin_post_tua_add_license_key',       array($this, 'addLicenseKey'),       10);
        add_action('admin_post_tua_update_license_key',    array($this, 'updateLicenseKey'),    10);

        // AJAX calls
        add_action('wp_ajax_tua_show_license_key',      array($this, 'showLicenseKey'),     10);
        add_action('wp_ajax_tua_show_all_license_keys', array($this, 'showAllLicenseKeys'), 10);
    }

    /**
     * Import licenses from a compatible CSV or TXT file into the database.
     */
    public function importLicenseKeys()
    {
        // Check the nonce.
        check_admin_referer('tua_import_license_keys');

        $orderId     = null;
        $productId   = null;
        $userId      = null;
        $status      = LicenseStatusEnum::ACTIVE;
        $source      = $_POST['source'];
        $licenseKeys = array();

        if (array_key_exists('order_id', $_POST) && $_POST['order_id']) {
            $orderId = $_POST['order_id'];
        }

        if (array_key_exists('product_id', $_POST) && $_POST['product_id']) {
            $productId = $_POST['product_id'];
        }

        if (array_key_exists('user_id', $_POST) && $_POST['user_id']) {
            $userId = $_POST['user_id'];
        }

        if (array_key_exists('status', $_POST)
            && $_POST['status']
            && in_array($_POST['status'], LicenseStatusEnum::$status)
        ) {
            $status = intval($_POST['status']);
        }

        if ($source === 'file') {
            $licenseKeys = apply_filters('tua_import_license_keys_file', null);
        }

        elseif ($source === 'clipboard') {
            $licenseKeys = apply_filters('tua_import_license_keys_clipboard', $_POST['clipboard']);
        }

        if (!is_array($licenseKeys) || count($licenseKeys) === 0) {
            AdminNotice::error(__('There was a problem importing the license keys.', 'top-up-agent'));
            wp_redirect(sprintf('admin.php?page=%s&action=import', AdminMenus::LICENSES_PAGE));
            exit();
        }

        // Save the imported keys
        try {
            $result = apply_filters(
                'tua_insert_imported_license_keys',
                $licenseKeys,
                $status,
                $orderId,
                $productId,
                $userId,
                $_POST['valid_for'],
                $_POST['times_activated_max']
            );
        } catch (Exception $e) {
            AdminNotice::error(
                sprintf(
                    // Translators: Error message displayed when an exception is thrown.
                    __('Error: %s', 'top-up-agent'),
                    $e->getMessage()
                )
            );
            wp_redirect(admin_url(sprintf('admin.php?page=%s&action=import', AdminMenus::LICENSES_PAGE)));
            exit();

        }

        // Redirect according to $result.
        if ($result['failed'] == 0 && $result['added'] == 0) {
            AdminNotice::error(__('There was a problem importing the license keys.', 'top-up-agent'));
            wp_redirect(sprintf('admin.php?page=%s&action=import', AdminMenus::LICENSES_PAGE));
            exit();
        }

        if ($result['failed'] == 0 && $result['added'] > 0) {
            // Update the stock
            if ($status === LicenseStatusEnum::ACTIVE) {
                apply_filters('tua_stock_increase', $productId, $result['added']);
            }

            
            AdminNotice::success(
                sprintf(
                    // Translators: Success message displayed when license keys are added successfully.
                    __('%d license key(s) added successfully.', 'top-up-agent'),
                    intval($result['added'])
                )
            );

            wp_redirect(sprintf('admin.php?page=%s&action=import', AdminMenus::LICENSES_PAGE));
            exit();
        }

        if ($result['failed'] > 0 && $result['added'] == 0) {
            AdminNotice::error(__('There was a problem importing the license keys.', 'top-up-agent'));
            wp_redirect(sprintf('admin.php?page=%s&action=import', AdminMenus::LICENSES_PAGE));
            exit();
        }

        if ($result['failed'] > 0 && $result['added'] > 0) {
            // Update the stock
            if ($status === LicenseStatusEnum::ACTIVE) {
                apply_filters('tua_stock_increase', $productId, $result['added']);
            }

            AdminNotice::warning(
                sprintf(
                    // Translators: Warning message displayed when importing license keys, indicating the number of keys imported successfully and failed.
                    __('%1$d key(s) have been imported successfully, while %2$d key(s) failed to import.', 'top-up-agent'),
                    intval($result['added']),
                    intval($result['failed'])
                )
            );

            wp_redirect(sprintf('admin.php?page=%s&action=import', AdminMenus::LICENSES_PAGE));
            exit();
        }
    }

    /**
     * Add a single license key to the database.
     */
    public function addLicenseKey()
    {
        // Check the nonce
        check_admin_referer('tua_add_license_key');

        $status            = absint($_POST['status']);
        $orderId           = null;
        $productId         = null;
        $userId            = null;
        $validFor          = null;
        $expiresAt         = null;
        $timesActivatedMax = null;

        if (array_key_exists('order_id', $_POST) && $_POST['order_id']) {
            $orderId = $_POST['order_id'];
        }

        if (array_key_exists('product_id', $_POST) && $_POST['product_id']) {
            $productId = $_POST['product_id'];
        }

        if (array_key_exists('user_id', $_POST) && $_POST['user_id']) {
            $userId = $_POST['user_id'];
        }

        if (array_key_exists('valid_for', $_POST) && $_POST['valid_for']) {
            $validFor  = $_POST['valid_for'];
            $expiresAt = null;
        }

        if (array_key_exists('expires_at', $_POST) && $_POST['expires_at']) {
            $validFor  = null;
            $expiresAt = $_POST['expires_at'];
        }

        if (array_key_exists('times_activated_max', $_POST) && $_POST['times_activated_max']) {
            $timesActivatedMax = absint($_POST['times_activated_max']);
        }

        if (apply_filters('tua_duplicate', $_POST['license_key'])) {
            AdminNotice::error(__('The license key already exists.', 'top-up-agent'));
            wp_redirect(sprintf('admin.php?page=%s&action=add', AdminMenus::LICENSES_PAGE));
            exit;
        }
          // empty license key
          if(empty($_POST['license_key'])){
            AdminNotice::error(__('The license key field is empty.', 'top-up-agent'));
            wp_redirect(sprintf('admin.php?page=%s&action=add', AdminMenus::LICENSES_PAGE));
            exit;
        };

        /** @var LicenseResourceModel $license */
        $license = LicenseResourceRepository::instance()->insert(
            array(
                'order_id'            => $orderId,
                'product_id'          => $productId,
                'user_id'             => $userId,
                'license_key'         => apply_filters('tua_encrypt', $_POST['license_key']),
                'hash'                => apply_filters('tua_hash', $_POST['license_key']),
                'expires_at'          => $expiresAt,
                'valid_for'           => $validFor,
                'source'              => LicenseSource::IMPORT,
                'status'              => $status,
                'times_activated_max' => $timesActivatedMax
            )
        );

        // Redirect with message
        if ($license) {
            AdminNotice::success(__('1 license key(s) added successfully.', 'top-up-agent'));

            // Update the stock
            if ($license->getStatus() == LicenseStatusEnum::ACTIVE) {
                apply_filters('tua_stock_increase', $productId);
            }
        }

        else {
            AdminNotice::error(__('There was a problem adding the license key.', 'top-up-agent'));
        }

        wp_redirect(sprintf('admin.php?page=%s&action=add', AdminMenus::LICENSES_PAGE));
        exit();
    }

    /**
     * Updates an existing license keys.
     *
     * @throws Exception
     */
    public function updateLicenseKey()
    {
        // Check the nonce
        check_admin_referer('tua_update_license_key');

        $licenseId         = absint($_POST['license_id']);
        $status            = absint($_POST['status']);
        $orderId           = null;
        $productId         = null;
        $userId            = null;
        $validFor          = null;
        $expiresAt         = null;
        $timesActivatedMax = null;

        /** @var LicenseResourceModel $oldLicense */
        $oldLicense = LicenseResourceRepository::instance()->find($licenseId);

        if (array_key_exists('order_id', $_POST) && $_POST['order_id']) {
            $orderId = $_POST['order_id'];
        }

        if (array_key_exists('product_id', $_POST) && $_POST['product_id']) {
            $productId = $_POST['product_id'];
        }

        if (array_key_exists('user_id', $_POST) && $_POST['user_id']) {
            $userId = $_POST['user_id'];
        }

        if (array_key_exists('valid_for', $_POST) && $_POST['valid_for']) {
            $validFor  = $_POST['valid_for'];
            $expiresAt = null;
        }

        if (array_key_exists('expires_at', $_POST) && $_POST['expires_at']) {
            $validFor  = null;
            $expiresAt = $_POST['expires_at'];
        }

        if (array_key_exists('times_activated_max', $_POST) && $_POST['times_activated_max']) {
            $timesActivatedMax = absint($_POST['times_activated_max']);
        }

        // Check for duplicates
        if (apply_filters('tua_duplicate', $_POST['license_key'], $licenseId)) {
            AdminNotice::error(__('The license key already exists.', 'top-up-agent'));
            wp_redirect(sprintf('admin.php?page=%s&action=edit&id=%d', AdminMenus::LICENSES_PAGE, $licenseId));
            exit;
        }
         // empty license key
         if(empty($_POST['license_key'])){
            AdminNotice::error(__('The license key field is empty.', 'top-up-agent'));
            wp_redirect(sprintf('admin.php?page=%s&action=add', AdminMenus::LICENSES_PAGE));
            exit;
        };

        // Update the stock
        if ($oldLicense->getProductId() !== null && $oldLicense->getStatus() === LicenseStatusEnum::ACTIVE) {
            apply_filters('tua_stock_decrease', $oldLicense->getProductId());
        }

        /** @var LicenseResourceModel $license */
        $license = LicenseResourceRepository::instance()->update(
            $licenseId,
            array(
                'order_id'            => $orderId,
                'product_id'          => $productId,
                'user_id'             => $userId,
                'license_key'         => apply_filters('tua_encrypt', $_POST['license_key']),
                'hash'                => apply_filters('tua_hash', $_POST['license_key']),
                'expires_at'          => $expiresAt,
                'valid_for'           => $validFor,
                'source'              => $_POST['source'],
                'status'              => $status,
                'times_activated_max' => $timesActivatedMax
            )
        );

        if ($license) {
            // Update the stock
            if ($license->getProductId() !== null && $license->getStatus() === LicenseStatusEnum::ACTIVE) {
                apply_filters('tua_stock_increase', $license->getProductId());
            }

            // Display a success message
            AdminNotice::success(__('Your license key has been updated successfully.', 'top-up-agent'));
        }

        else {
            //Display an error message
            AdminNotice::error(__('There was a problem updating the license key.', 'top-up-agent'));
        }

        wp_redirect(sprintf('admin.php?page=%s&action=edit&id=%d', AdminMenus::LICENSES_PAGE, $licenseId));
        exit();
    }

    /**
     * Show a single license key.
     */
    public function showLicenseKey()
    {
        // Check if the current user has the required capability
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Invalid request.', 'top-up-agent'));
        }

        // Validate request.
        check_ajax_referer('tua_show_license_key', 'show');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_die(esc_html__('Invalid request.', 'top-up-agent'));
        }

        /** @var LicenseResourceModel $license */
        $license = LicenseResourceRepository::instance()->findBy(array('id' => $_POST['id']));

        wp_send_json($license->getDecryptedLicenseKey());

        wp_die();
    }

    /**
     * Shows all visible license keys.
     */
    public function showAllLicenseKeys()
    {
        // Check if the current user has the required capability
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Invalid request.', 'top-up-agent'));
        }

        // Validate request.
        check_ajax_referer('tua_show_all_license_keys', 'show_all');

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            wp_die(esc_html__('Invalid request.', 'top-up-agent'));
        }

        $licenseKeysIds = array();

        foreach (json_decode($_POST['ids']) as $licenseKeyId) {
            /** @var LicenseResourceModel $license */
            $license = LicenseResourceRepository::instance()->find($licenseKeyId);

            $licenseKeysIds[$licenseKeyId] = $license->getDecryptedLicenseKey();
        }

        wp_send_json($licenseKeysIds);
    }
}
