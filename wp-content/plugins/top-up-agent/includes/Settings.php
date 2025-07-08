<?php

namespace TopUpAgent;

use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Key as DefuseCryptoKey;
use TopUpAgent\Enums\LicenseStatus;
use TopUpAgent\Repositories\Resources\License as LicenseResourceRepository;
use TopUpAgent\Repositories\Resources\ApiKey as ApiResourceRepository;
use TopUpAgent\Repositories\Resources\LicenseMeta as LicenseMetaResourceRepository;
use TopUpAgent\Enums\LicenseSource;
use Exception;

defined('ABSPATH') || exit;

class Settings
{
    private static $upload_dir;
    /**
     * @var string
     */
    const SECTION_GENERAL = 'tua_settings_general';

    /**
     * @var string
     */
    const SECTION_WOOCOMMERCE = 'tua_settings_woocommerce';

    /**
     * @var string
     */
    const SECTION_TOOLS = 'tua_settings_tools';

    /**
     * Settings Constructor.
     */
    public function __construct()
    {
        // Initialize the settings classes
        new Settings\General();
        new Settings\WooCommerce();
        new Settings\Tools();
        add_action( 'wp_ajax_tua_handle_tool_process', array( $this, 'handleToolProcess' ), 50 );

    }

    /**
     * Handles tool process
     * @return void
     */
    public function handleToolProcess() {

        if ( ! check_ajax_referer( 'tua_dropdown_search', 'security', false ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.' ) ] );
            exit;
        } 
        $identifier = isset( $_POST['identifier'] ) ? $_POST['identifier'] : '';

        if( $identifier  ==  'migrate' ){
            $pluginSlug = isset( $_POST['plugin_name'] ) ? $_POST['plugin_name'] : '';
            $result  = $this->migrateData( $pluginSlug );
            wp_send_json_success( $result );
            exit;
        }

        wp_send_json_error( [ 'message' => __( 'Invalid operation.' ) ] );
        exit;
    }

    public function migrateData( $pluginSlug ) {

        global $wpdb;
        $preserve_ids = isset( $_POST['preserve_ids'] ) ? intval( $_POST['preserve_ids'] ) : 0;

         if ( $preserve_ids ) {
                LicenseResourceRepository::instance()->truncate();
                LicenseMetaResourceRepository::instance()->truncate();
                ApiResourceRepository::instance()->truncate();
            }

        if ( 'dlm' == $pluginSlug ) {

            $settings_general = get_option( 'dlm_settings_general', array() );
            $settings_woocommerce = get_option( 'dlm_settings_woocommerce', array() );

            $table1 = $wpdb->prefix . 'dlm_licenses';
            $table3 = $wpdb->prefix . 'dlm_api_keys';
            $table5 = $wpdb->prefix . 'dlm_license_meta';

            $licenses = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %1s', $table1 ), ARRAY_A );

            foreach ( $licenses as $row ) {
                $license_key = self::decrypt( $row['license_key'] );
                $new_row_data = array(
                    'order_id'          => $row['order_id'],
                    'product_id'        => $row['product_id'],
                    'user_id'           => $row['user_id'],
                    'license_key'       => apply_filters('tua_encrypt', $license_key ),
                    'hash'              => apply_filters('tua_hash', $license_key ),
                    'expires_at'        => $row['expires_at'],
                    'valid_for'         => $row['valid_for'],
                    'source'            => LicenseSource::MIGRATION,
                    'status'            => $row['status'],
                    'times_activated'   => 0,
                    'times_activated_max' => $row['activations_limit'],
                    'created_at'        => $row['created_at'],
                    'created_by'        => $row['created_by'],
                    'updated_at'        => $row['updated_at'],
                    'updated_by'        => $row['updated_by']
                );

                if ( $preserve_ids ) {
                    $new_row_data['id'] = $row['id'];
                }
                /** @var LicenseResourceModel $new_row */
                $new_row = LicenseResourceRepository::instance()->insert( $new_row_data );

                if ( ! empty( $new_row ) ) {
                    $old_meta_rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %1s where license_id = %d', $table5, $row['id'] ), ARRAY_A );
                    if ( ! empty( $old_meta_rows ) ) {
                        foreach ( $old_meta_rows as $old_meta_row ) {
                            $old_meta_row['license_id'] = $new_row->getId();
                            if ( ! $preserve_ids ) {
                                unset( $old_meta_row['meta_id'] );

                            }
                            LicenseMetaResourceRepository::instance()->insert( $old_meta_row );
                        }
                    }
                }
            }
           
            //apikeys
             $apikeys = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %1s', $table3 ), ARRAY_A );
            foreach ( $apikeys as $row ) {
              
                unset($row['endpoints']);
                if ( ! $preserve_ids ) {
                    unset($row['id']);
                }
                
                ApiResourceRepository::instance()->insert( $row );
            }

            if ( ! function_exists( 'wc_get_products' ) ) {
                return new \WP_Error( 'WooCommerce is not active.' );
            }

            $args = array(
                'meta_key'     => 'dlm_licensed_product',
                'meta_value'   => 1,
                'meta_compare' => '=',
                'type'         => array( 'simple', 'variation' ),
                'return'       => 'objects',
            );

            $query = (array) wc_get_products( $args );
            if ( ! empty( $query['products'] ) ) {
                foreach ( $query['products'] as $product ) {
                    /* @var \WC_Product $product */
                    $quantity      = (int) $product->get_meta( 'dlm_licensed_product_delivered_quantity', true );
                    $product->update_meta_data( 'tua_licensed_product', 1 );
                    $product->update_meta_data( 'tua_licensed_product_delivered_quantity', $quantity );
                    $product->update_meta_data( 'tua_licensed_product_use_stock', '1' );
                    $product->save();
                }
            }

            $general_array = array(
                'tua_hide_license_keys' => isset($settings_general['hide_license_keys']) ? $settings_general['hide_license_keys'] : '',
                'tua_allow_duplicates' => isset($settings_general['allow_duplicates']) ? $settings_general['allow_duplicates'] : '',
                'tua_disable_api_ssl' => isset($settings_general['disable_api_ssl']) ? $settings_general['disable_api_ssl'] : '',
                'tua_expire_format' => isset($settings_general['expiration_format']) ? $settings_general['expiration_format'] : ''
            );
            
            $woocommerce_array = array(
                'tua_auto_delivery' => isset($settings_woocommerce['auto_delivery']) ? $settings_woocommerce['auto_delivery'] : '1',
                'tua_enable_stock_manager' => isset($settings_woocommerce['stock_management']) ? $settings_woocommerce['stock_management'] : '1',
                'tua_license_key_delivery_options' => isset($settings_woocommerce['order_delivery_statuses']) ? $settings_woocommerce['order_delivery_statuses'] : array('wc-completed' => array('send' => '1'))
            );

            update_option('tua_settings_general', $general_array);
            update_option('tua_settings_woocommerce', $woocommerce_array);
            $next = array( 'next_page' => -1 , 'message' => __('Operation Completed' , 'top-up-agent'), 'percent' => 100 );
            wp_send_json_success( $next );
        }
    }

    protected static function decrypt( $license_key ) {
        return \Defuse\Crypto\Crypto::decrypt( $license_key, DefuseCryptoKey::loadFromAsciiSafeString( self::find3rdPartyDefuse() ) );
    }
    protected static function find3rdPartyDefuse() {

        if ( defined( 'DLM_PLUGIN_DEFUSE' ) ) {
            return constant('DLM_PLUGIN_DEFUSE');
        }

        if ( is_null( self::$upload_dir ) ) {
            self::$upload_dir = wp_upload_dir()['basedir'] . '/dlm-files/';
        }

        if ( file_exists( self::$upload_dir . 'defuse.txt' ) ) {
            return (string) file_get_contents( self::$upload_dir . 'defuse.txt' );
        }

        return null;
    }

    /**
     * Helper function to get a setting by name.
     *
     * @param string $field
     * @param string $section
     *
     * @return bool|mixed
     */
    public static function get($field, $section = self::SECTION_GENERAL)
    {
        $settings = get_option($section, array());
        $value    = false;

        if (!$settings) {
            $settings = array();
        }

        if (array_key_exists($field, $settings)) {
            $value = $settings[$field];
        }
        
        return $value;
    }
}