<?php
/**
 * Deliver Order license key(s) to Customer.
 */
defined('ABSPATH') || exit;

/**
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email);

/**
 * @hooked \TopUpAgent\Emails\Main Adds the ordered license keys table.
 */
do_action('tua_email_order_license_keys', $order, $sent_to_admin, $plain_text, $email);

/**
 * @hooked \TopUpAgent\Emails\Main Adds basic order details.
 */
do_action('tua_email_order_details', $order, $sent_to_admin, $plain_text, $email);

/**
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);
