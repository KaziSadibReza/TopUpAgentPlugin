<?php defined('ABSPATH') || exit; ?>

<div class="wrap lmfwc">
    <?php
        if ($action === 'list'
            || $action === 'activate'
            || $action === 'deactivate'
            || $action === 'delete'
        ) {
            include_once('licenses/page-list.php');
        } elseif ($action === 'add') {
            include_once('licenses/page-add.php');
        } elseif ($action === 'import') {
            include_once('licenses/page-import.php');
        } elseif ($action === 'edit') {
            include_once('licenses/page-edit.php');
        }
        elseif ($action === 'export_date') {
            include_once('licenses/export-date.php');
        } elseif ($action === 'process_date_export') {
            $nonce = wp_verify_nonce($_POST['_wpnonce'], 'export_date');
            
             // if ( $nonce ) {
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'];
                $unassigned = $_POST['unassigned'];

                global $wpdb;

                if ( isset($unassigned ) && 'on' === $unassigned ) {

                    $result = $wpdb->get_results(
                        $wpdb->prepare('SELECT * FROM '. $wpdb->prefix .'tua_licenses WHERE user_id IS NULL AND order_id IS NULL')
                    );

                } else {

                    $result = $wpdb->get_results(
                        $wpdb->prepare('SELECT * FROM '. $wpdb->prefix .'tua_licenses WHERE 1=1')
                    );

                }
            

                $licenseKeysIds = array();
                foreach($result as $value) {

                    $license_created_at = date('Y-m-d', strtotime( $value->created_at ) );
                    if ( $license_created_at >= $start_date && $license_created_at <= $end_date ) {
                        array_push($licenseKeysIds, $value->id);
                    }
                    
                }

                if ( ! empty($licenseKeysIds) ) {
                    do_action('tua_export_license_keys_by_date_csv', $licenseKeysIds);
                } else {
                    wp_redirect( admin_url('admin.php?page=tua_licenses&action=export_date&_wpnonce=4d4cc32bc4&error=1') );
                }
                
        }
    ?>
</div>
