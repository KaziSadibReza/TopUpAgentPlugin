<?php

namespace TopUpAgent\Integrations\WooCommerce;

use Dompdf\Dompdf;
use Exception;
use TopUpAgent\Settings;
use TopUpAgent\Models\Resources\License;
use TopUpAgent\Repositories\Resources\License as LicenseResourceRepository;
use TopUpAgent\Repositories\Resources\LicenseActivations as ActivationResourceRepository;
use TopUpAgent\Enums\ActivationProcessor;

defined('ABSPATH') || exit;

class MyAccount
{
    /**
     * MyAccount constructor.
     */
    public function __construct()
    {
        add_rewrite_endpoint('view-license-keys', EP_ROOT | EP_PAGES);

        add_filter( 'woocommerce_account_menu_items',                 array( $this, 'accountMenuItems'), 10, 1);
        add_action( 'woocommerce_account_view-license-keys_endpoint', array( $this, 'viewLicenseKeys'));
        add_action( 'tua_myaccount_licenses_single_page_end',       array( $this, 'addSingleLicenseActivationsTable' ), 10, 5 );
        add_action( 'wp_loaded',                                      array( $this, 'handleCustomActions'));
        flush_rewrite_rules(true);
    }

    public function handleCustomActions() {

        $user = wp_get_current_user();
        if (!$user) {
            return;
        }

        $action    = isset( $_POST['tua_action'] ) ? sanitize_text_field( $_POST['tua_action'] ) : '';
        if (array_key_exists('action', $_POST)) {
            $licenseKey =  isset( $_POST['license'] )  ? sanitize_text_field( $_POST['license']) : '';

            if ($_POST['action'] === 'activate' && Settings::get('tua_allow_users_to_activate' , Settings::SECTION_WOOCOMMERCE)) {
                $nonce = wp_verify_nonce($_POST['_wpnonce'], 'tua_myaccount_activate_license');
                if ($nonce) {
                    $args = array();
                    $args['source'] = ActivationProcessor::WEB;
                    $activate = tua_activate_license($licenseKey,$args);
                    if( is_wp_error ( $activate ) ){
                        wc_add_notice(__('License Key is Expired .' , 'top-up-agent'), 'error');
                    }

                }
            }



            if ($_POST['action'] === 'deactivate' && Settings::get('tua_allow_users_to_deactivate' , Settings::SECTION_WOOCOMMERCE)) {
                $token      = $_POST['token'];
                $optional = '';
                $args = array( 
                     'token' => $token 
                );
                $nonce = wp_verify_nonce($_POST['_wpnonce'],'tua_myaccount_deactivate_license');
                if ($nonce) {
                    try {
                        tua_deactivate_license( $optional, $args);
                    }
                    catch (Exception $e) {
                    }
                }
            }

            if ($_POST['action'] === 'reactivate') {

                $token      = $_POST['token'];
                $nonce = wp_verify_nonce($_POST['_wpnonce'],'tua_myaccount_reactivate_license');

                if ($nonce) {
                    try {
                        $reactivation = tua_reactivate_license($token);
                        if( is_wp_error ( $reactivation ) ){

                            wc_add_notice(__('License Key is Expired Cannot be Reactivate. ' , 'top-up-agent'), 'error');
                        }
                    }
                    catch (Exception $e) {
                    }
                }
            }
            
            if ($_POST['action'] === 'delete') {

               $activation_id = isset($_POST['activation_id']) ? $_POST['activation_id'] : '';
               $license_id = isset($_POST['license_id']) ? $_POST['license_id'] : '';

               $nonce = wp_verify_nonce($_POST['_wpnonce'],'tua_myaccount_delete_license');

               if ($nonce) {
                try {
                    tua_delete_activation($activation_id, $license_id);
                }
                catch (Exception $e) {
                }
            }
        }

        if ($_POST['action'] === 'tua_download_license_pdf' && Settings::get('tua_download_certificates' , Settings::SECTION_WOOCOMMERCE)) {

            $nonce = wp_verify_nonce($_POST['_wpnonce'],'tua_myaccount_download_certificates');

            if ($nonce) {
                $this->lmfwcGeneratePDFCertificate($licenseKey);
            }
        }
    }
}

        /**
     * Prints out the licenses activation table
     *
     * @param License $license
     * @param $order
     * @param $product
     * @param $dateFormat
     * @param $licenseKey
     *
     * @return string
     */
        public static function addSingleLicenseActivationsTable ( $license, $order = null, $product = null, $dateFormat = null, $licenseKey = null ) {


            if ( is_null( $order ) ) {
                $order = wc_get_order( $license->getOrderId() );
            }

            if ( is_null( $product ) ) {
                $product = wc_get_order( $license->getProductId() );
            }

            if ( is_null( $dateFormat ) ) {
                $dateFormat = get_option( 'date_format' );
            }

            if ( is_null( $licenseKey ) ) {
                $licenseKey = $license->getDecryptedLicenseKey();
            }
            $activations = apply_filters('tua_get_license_activations', $license->getId());

            echo wp_kses(
                wc_get_template_html(
                    'myaccount/single-table-activations.php',
                    array(
                        'license'                    => $license,
                        'license_key'                => $licenseKey,
                        'product'                    => $product,
                        'order'                      => $order,
                        'date_format'                => $dateFormat,
                        'activations'                => $activations,
                        'nonce'                      => wp_create_nonce( 'tua_nonce' ),
                    ),
                    '',
                    TUA_TEMPLATES_DIR
                ),
                tua_shapeSpace_allowed_html()
            );
        }

    /**
     * Adds the plugin pages to the "My account" section.
     *
     * @param array $items
     *
     * @return array
     */
    public function accountMenuItems($items)
    {
        $customItems = array();
        $customItems['view-license-keys'] = __('License keys', 'top-up-agent');
        $customItems = array_slice( $items, 0, 2, true ) + $customItems + array_slice( $items, 2, count( $items ), true );
        return $customItems;
    }

    /**
     * Creates an overview of all purchased license keys.
    //  */
    public function viewLicenseKeys() {
        global $wp_query;
        wp_enqueue_style('tua_admin_css', TUA_CSS_URL . 'main.css');
        $user_id = get_current_user_id();
        $licenseID = null;
        $page = 1;
        
        if ($wp_query->query['view-license-keys']) {

            $page = intval($wp_query->query['view-license-keys']);
            if ( ! empty( $page ) ) {
                $parts = explode( '/', $page );
                if ( count( $parts ) === 2 && $parts[0] === 'page' ) {
                    $paged = (int) $parts[1];
                } else {
                    $licenseID = sanitize_text_field( $parts[0] );
                }
            }

        }

        

        if(  !$licenseID ) {
            $licenseKeys = apply_filters('tua_get_all_customer_license_keys', $user_id);
            echo wp_kses(
                wc_get_template_html(
                    'myaccount/tua-view-license-keys.php',
                    array(
                        'dateFormat'  => get_option('date_format'),
                        'licenseKeys' => $licenseKeys,
                        'page'        => $page
                    ),
                    '',
                    TUA_TEMPLATES_DIR
                ),
                tua_shapeSpace_allowed_html()
            );
        } 
        
        else {

         /** @var License|false $license */
         $license = LicenseResourceRepository::instance()->findBy(
            array(
                'id' => $licenseID
            )
        );

         if ( is_wp_error( $license ) || !$license || $license->getUserId() != $user_id ) {
            echo sprintf( '<h3>%s</h3>', esc_html__( 'Not found', 'top-up-agent' ) );
            echo sprintf( '<p>%s</p>', esc_html__( 'The license you are looking for is not found.', 'top-up-agent' ) );

            return;

        }

        /** @var string|\WP_Error $decrypted */
        $decrypted = $license->getDecryptedLicenseKey();
        if (is_wp_error($decrypted)) {
            echo sprintf('<p>%s</p>', esc_html($decrypted->get_error_message()));
        
            return;
        }
        echo wp_kses(
            wc_get_template_html(
                'myaccount/single.php',
                array(
                    'license'     => $license,
                    'license_key' => $license->getDecryptedLicenseKey(),
                    'product'     => ! empty( $license->getProductId() ) ? wc_get_product( $license->getProductId() ) : null,
                    'order'       => ! empty( $license->getOrderId() ) ? wc_get_order( $license->getOrderId() ) : null,
                    'date_format' => get_option( 'date_format' ),
                ),
                '',
                TUA_TEMPLATES_DIR
            ),
            tua_shapeSpace_allowed_html()
        );

    }

}

    /**
     * Generate license certificate in PDF
     * @param string $licenseKey
     *
     * @return void
     */
    public function lmfwcGeneratePDFCertificate( $licenseKey ) {

       /** @var License|\WP_Error $license */
       $license = LicenseResourceRepository::instance()->findBy(
        array(
            'hash' => apply_filters('tua_hash', $licenseKey)
        )
    );

       $errors = array();
       $order  = null;

       if ( is_wp_error( $license ) ) {
        array_push( $errors, $license->get_error_message() );
    } else {
        $order = wc_get_order( $license->getOrderId() );
        if ( empty( $order ) ) {
            array_push( $errors, __( 'Permission denied.', 'top-up-agent' ) );
        }
    }

        /**
         *  Validate customer
         */
        if ( ! $order || get_current_user_id() !== $order->get_customer_id() ) {
            array_push( $errors, __( 'Permission denied.', 'top-up-agent' ) );
        }
        if ( ! empty( $errors ) ) {
            wp_die(esc_html($errors[0]));
        }

        /**
         * Render the template
         */
        $html = wc_get_template_html(
            'myaccount/single-certificate.php',
            $this->lmfwcGetCertificateData( $license ),
            '',
            TUA_TEMPLATES_DIR
        );

        /**
         * Output the template
         */

        $pdf = new DOMPDF();
        $pdf->set_option('enable_html5_parser', true);
        $pdf->set_option('isRemoteEnabled', true);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();
        $pdf->stream(date('Y-m-d') . '_license_certificate.pdf', array('attachment'=>true));
    }


    /**
     * Return the license certification data
     *
     * @param License $license
     *
     * @return mixed|void
     */
    private function lmfwcGetCertificateData( $license ) {

        /**
         * The data template
         */
        $data = array(
            'title'                => '',
            'logo'                 => '',
            'license_product_name' => '',
            'license_details'      => array(), // eg. array('title' => 'Product Name', 'value' => 'Counter Strike')
        );

        /**
         * Add option to developers to add their own data and skip our data generation process
         */
        $data = apply_filters( 'tua_license_certification_prefilter_data', $data, $license );
        if ( ! empty( $data['is_final'] ) ) {
            return apply_filters( $data, 'tua_license_certification_data', $data, $license );
        }


        /**
         * Get the logo
         */
        $logo = Settings::get( 'tua_company_logo', Settings::SECTION_WOOCOMMERCE );
        if ( ! is_numeric( $logo ) ) {
            $logo = get_theme_mod( 'tua_company_logo' );
        }

        /**
         * Get basic details
         */
        $product  = $license->getProductId() ? wc_get_product( $license->getProductId() ) : null;
        $order    = $license->getOrderId() ? wc_get_order( $license->getOrderId() ) : null;
        $customer = $order ? $order->get_customer_id() : null;


        /**
         * Setup the license details
         */

        
        $expiry_date = $license->getExpiresAt();
        if ( empty( $expiry_date ) ) {
            $expiry_date = esc_html__( 'Never Expires', 'top-up-agent' );
        } else {
            $expiry_date = wp_date( tua_expiration_format(), strtotime( $expiry_date ) );
        }


        $license_details = array(
            array(
                'title' => esc_html__( 'License ID', 'top-up-agent' ),
                'value' => sprintf( '#%d', $license->getId() ),
            ),
            array(
                'title' => esc_html__( 'License Key', 'top-up-agent' ),
                'value' => $license->getDecryptedLicenseKey(),
            ),
            array(
                'title' => esc_html__( 'Expiry Date', 'top-up-agent' ),
                'value' => $expiry_date,
            )
        );
        if ( $customer ) {
            $customer          = get_user_by( 'id', $customer );
            $license_details[] = array(
                'title' => esc_html__( 'Licensee', 'top-up-agent' ),
                'value' => sprintf(
                    '%s (#%d - %s)',
                    $customer->display_name,
                    $customer->ID,
                    $customer->user_email
                )
            );
            if ( $order ) {
                $license_details[] = array(
                    'title' => esc_html__( 'Order ID', 'top-up-agent' ),
                    'value' => sprintf( '#%d', $order->get_id() ),
                );
                $license_details[] = array(
                    'title' => esc_html__( 'Order Date', 'top-up-agent' ),
                    'value' => date_i18n( wc_date_format(), strtotime( $order->get_date_paid() ?? '' ) ),
                );
            }
        }
        if ( $product ) {
            $license_details[] = array(
                'title' => esc_html__( 'Product Name', 'top-up-agent' ),
                'value' => $product->get_formatted_name(),
            );
            $license_details[] = array(
                'title' => esc_html__( 'Product URL', 'top-up-agent' ),
                'value' => $product->get_permalink(),
            );
        }

        /**
         * Setup the data
         */
        $data['title']                = get_bloginfo( 'name' );
        $data['logo']                 = is_numeric( $logo ) ? wp_get_attachment_image_url( $logo, 'full' ) : null;
        $data['license_product_name'] = $product ? $product->get_formatted_name() : null;
        $data['license_details']      = $license_details;

        return apply_filters( 'tua_license_certification_data', $data, $license );
    }






}