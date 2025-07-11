<?php

namespace TopUpAgent;

defined('ABSPATH') || exit;

class Import
{
    /**
     * Temporary import file name
     */
    const TEMP_IMPORT_FILE = 'import.tmp';

    /**
     * Import constructor.
     */
    public function __construct()
    {
        add_filter('tua_import_license_keys_file',      array($this, 'importLicenseKeysFile'),      10);
        add_filter('tua_import_license_keys_clipboard', array($this, 'importLicenseKeysClipboard'), 10, 1);
    }

    /**
     * Extracts the license keys from the uploaded CSV/TXT file.
     *
     * @return array|false|null
     *
     * @throws \Exception
     */
    public function importLicenseKeysFile()
    {
        $duplicateLicenseKeys = array();
        $licenseKeys          = null;
        $ext                  = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $mimes                = array('application/vnd.ms-excel', 'text/plain', 'text/csv', 'text/tsv');
        $fileName             = $_FILES['file']['tmp_name'];
        $filePath             = TUA_ASSETS_DIR . self::TEMP_IMPORT_FILE;

        // Validate the file extension
        if (!in_array($ext, array('txt', 'csv')) || !in_array($_FILES['file']['type'], $mimes)) {
            AdminNotice::error(__('Invalid file type, only TXT and CSV allowed.', 'top-up-agent'));

            wp_redirect(
                sprintf(
                    'admin.php?page=%s&action=import',
                    AdminMenus::LICENSES_PAGE
                )
            );

            exit();
        }

        // File upload file, return with error.
        if (!move_uploaded_file($fileName, $filePath)) {
            return null;
        }

        // Handle TXT file uploads
        if ($ext == 'txt') {
            $licenseKeys = file(TUA_ASSETS_DIR . self::TEMP_IMPORT_FILE, FILE_IGNORE_NEW_LINES);

            // Check for invalid file contents.
            if (!is_array($licenseKeys)) {
                AdminNotice::error(__('Invalid file content.', 'top-up-agent'));

                wp_redirect(
                    sprintf(
                        'admin.php?page=%s&action=import',
                        AdminMenus::LICENSES_PAGE
                    )
                );
                exit();
            }
        }

        // Handle CSV file uploads
        elseif ($ext == 'csv') {
            $licenseKeys = array();

            if (($handle = fopen(TUA_ASSETS_DIR . self::TEMP_IMPORT_FILE, 'r')) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    if ($data && is_array($data) && count($data) > 0) {
                        $licenseKeys[] = $data[0];
                    }
                }

                fclose($handle);
            }
        }

        // Check for duplicates
        foreach ($licenseKeys as $i => $licenseKey) {
            if (apply_filters('tua_duplicate', $licenseKey)) {
                unset($licenseKeys[$i]);
                $duplicateLicenseKeys[] = $licenseKey;
                continue;
            }
        }

        if (count($duplicateLicenseKeys) > 0) {
            AdminNotice::warning(
                sprintf(
                    // Translators: Warning message displayed when license keys are skipped because they already exist.
                    __('%d license key(s) skipped because they already exist.', 'top-up-agent'),
                    count($duplicateLicenseKeys)
                )
            );            

            if (count($licenseKeys) === 0) {
                wp_redirect(
                    sprintf(
                        'admin.php?page=%s&action=import',
                        AdminMenus::LICENSES_PAGE
                    )
                );
                exit();
            }
        }
        // Delete the temporary file now that we're done.
        unlink(TUA_ASSETS_DIR . self::TEMP_IMPORT_FILE);

        return $licenseKeys;
    }

    /**
     * Extracts license keys from a string.
     *
     * @param string $clipboard
     * @param string $delimiter
     *
     * @return array
     */
    public function importLicenseKeysClipboard($clipboard)
    {
        return explode("\n", $clipboard);
    }
}