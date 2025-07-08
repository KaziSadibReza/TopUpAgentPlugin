<?php

namespace TopUpAgent\Integrations\WooCommerce;

use TopUpAgent\Integrations\WooCommerce\Emails\CustomerDeliverLicenseKeys;
use TopUpAgent\Integrations\WooCommerce\Emails\CustomerPreorderComplete;
use TopUpAgent\Integrations\WooCommerce\Emails\Templates;
use TopUpAgent\Settings;
use WC_Email;
use WC_Order;

defined('ABSPATH') || exit;

class Email
{
    /**
     * OrderManager constructor.
     */
    public function __construct() {
        add_action('woocommerce_email_after_order_table', array($this, 'afterOrderTable'), 10, 4);
        add_action('woocommerce_email_classes',           array($this, 'registerClasses'), 90, 1);
    }

    /**
     * Adds the bought license keys to the "Order complete" email, or displays a notice - depending on the settings.
     *
     * @param WC_Order $order
     * @param bool     $isAdminEmail
     * @param bool     $plainText
     * @param WC_Email $email
     */
    public function afterOrderTable($order, $isAdminEmail, $plainText, $email)
    {
        $orderStatusSettings = ! empty( Settings::get('tua_license_key_delivery_options', Settings::SECTION_WOOCOMMERCE) ) ? Settings::get('tua_license_key_delivery_options', Settings::SECTION_WOOCOMMERCE) : array();
        
        if ( ! array_key_exists( 'wc-'.$order->get_status(), $orderStatusSettings) ) {
            return;
        }

        if ( ! $data = apply_filters('tua_get_customer_license_keys', $order) ) {
            return;
        }

        if (Settings::get('tua_auto_delivery' , Settings::SECTION_WOOCOMMERCE)) {
            // Send the keys out if the setting is active.
            if ($plainText) {
                echo wp_kses_post(
                    wc_get_template(
                        'emails/plain/tua-email-order-license-keys.php',
                        array(
                            'heading'       => apply_filters('tua_license_keys_table_heading', null),
                            'valid_until'   => apply_filters('tua_license_keys_table_valid_until', null),
                            'data'          => $data,
                            'date_format'   => get_option('date_format'),
                            'order'         => $order,
                            'sent_to_admin' => $isAdminEmail,
                            'plain_text'    => true,
                            'email'         => $email,
                            'args'          => apply_filters('tua_template_args_emails_email_order_license_keys', array())
                        ),
                        '',
                        TUA_TEMPLATES_DIR
                    )
                );
            } else {
                echo wp_kses_post(
                    wc_get_template_html(
                        'emails/tua-email-order-license-keys.php',
                        array(
                            'heading'       => apply_filters('tua_license_keys_table_heading', null),
                            'valid_until'   => apply_filters('tua_license_keys_table_valid_until', null),
                            'data'          => $data,
                            'date_format'   => get_option('date_format'),
                            'order'         => $order,
                            'sent_to_admin' => $isAdminEmail,
                            'plain_text'    => false,
                            'email'         => $email,
                            'args'          => apply_filters('tua_template_args_emails_email_order_license_keys', array())
                        ),
                        '',
                        TUA_TEMPLATES_DIR
                    )
                );
            }
            
        }

        else {
            // Only display a notice.
            if ($plainText) {
                echo wp_kses_post(
                    wc_get_template(
                        'emails/plain/tua-email-order-license-notice.php',
                        array(
                            'args' => apply_filters('tua_template_args_emails_email_order_license_notice', array())
                        ),
                        '',
                        TUA_TEMPLATES_DIR
                    )
                );
            } else {
                echo wp_kses_post(
                    wc_get_template_html(
                        'emails/tua-email-order-license-notice.php',
                        array(
                            'args' => apply_filters('tua_template_args_emails_email_order_license_notice', array())
                        ),
                        '',
                        TUA_TEMPLATES_DIR
                    )
                );
            }            
        }
    }

    /**
     * Registers the plugin email classes to work with WooCommerce.
     *
     * @param array $emails
     *
     * @return array
     */
    public function registerClasses($emails)
    {
        new Templates();

        $pluginEmails = array(
            //'tua_Customer_Preorder_Complete'    => new CustomerPreorderComplete(),
            'tua_Customer_Deliver_License_Keys' => new CustomerDeliverLicenseKeys()
        );

        return array_merge($emails, $pluginEmails);
    }
}
