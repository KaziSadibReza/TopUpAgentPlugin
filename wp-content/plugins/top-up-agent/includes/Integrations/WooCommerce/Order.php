<?php

namespace TopUpAgent\Integrations\WooCommerce;

use TopUpAgent\Enums\LicenseStatus;
use TopUpAgent\Lists\LicensesList;
use TopUpAgent\Models\Resources\License as LicenseResourceModel;
use TopUpAgent\Repositories\Resources\License as LicenseResourceRepository;
use TopUpAgent\Settings;
use WC_Order_Item_Product;
use WC_Product_Simple;
use function WC;
use WC_Order;
use WC_Order_Item;
use WC_Product;

defined('ABSPATH') || exit;

class Order
{
    /**
     * OrderManager constructor.
     */
    public function __construct()
    {
        $this->addOrderStatusHooks();

        add_action('woocommerce_order_action_tua_send_license_keys', array($this, 'processSendLicenseKeysAction'), 10, 1);
        add_action('woocommerce_order_details_after_order_table',      array($this, 'showBoughtLicenses'),           10, 1);
        add_filter('woocommerce_order_actions',                        array($this, 'addSendLicenseKeysAction'),     10, 2);
        add_action('woocommerce_after_order_itemmeta',                 array($this, 'showOrderedLicenses'),          10, 3);
    }

    /**
     * Hooks the license generation method into the woocommerce order status
     * change hooks.
     */
    private function addOrderStatusHooks()
    {
        $orderStatusSettings = Settings::get('tua_license_key_delivery_options', Settings::SECTION_WOOCOMMERCE);

        // The order status settings haven't been configured.
        if (empty($orderStatusSettings)) {
            return;
        }

        foreach ($orderStatusSettings as $status => $settings) {
            if (array_key_exists('send', $settings)) {
                $value = filter_var($settings['send'], FILTER_VALIDATE_BOOLEAN);

                if ($value) {
                    $filterStatus = str_replace('wc-', '', $status);

                    add_action('woocommerce_order_status_' . $filterStatus, array($this, 'generateOrderLicenses'));
                }
            }
        }
    }

    /**
     * Generates licenses for an order.
     *
     * @param int $orderId
     */
    public function generateOrderLicenses($orderId)
    {
         /** @var WC_Order $order */
        $order = wc_get_order($orderId);

        // Keys have already been generated for this order.
        if ( $order->get_meta( 'tua_order_complete')) {
            return;
        }


        // The given order does not exist
        if (!$order) {
            return;
        }

        /** @var WC_Order_Item_Product $orderItem */
        foreach ($order->get_items() as $orderItem) {
            /** @var WC_Product $product */
            $product = $orderItem->get_product();

            // Skip this product because it's not a licensed product.
            if (! $product->get_meta( 'tua_licensed_product', true)) {
                continue;
            }

            $useStock = $product->get_meta('tua_licensed_product_use_stock', true);

            // Skip this product because stock-based selling is not active.
            if (!$useStock) {
                continue;
            }

            $deliveredQuantity = absint(
               $product->get_meta( 
                    'tua_licensed_product_delivered_quantity',
                    true
                )
            );

            // Determines how many times should the license key be delivered
            if (!$deliveredQuantity) {
                $deliveredQuantity = 1;
            }

            // Set the needed delivery amount
            $neededAmount = absint($orderItem->get_quantity()) * $deliveredQuantity;

            // Sell license keys through available stock.
            if ($useStock) {
                // Retrieve the available license keys.
                /** @var LicenseResourceModel[] $licenseKeys */
                $licenseKeys = LicenseResourceRepository::instance()->findAllBy(
                    array(
                        'product_id' => $product->get_id(),
                        'status' => LicenseStatus::ACTIVE
                    )
                );

                // Retrieve the current stock amount
                $availableStock = count($licenseKeys);

                // There are enough keys.
                if ($neededAmount <= $availableStock) {
                    // Set the retrieved license keys as "SOLD".
                    apply_filters(
                        'tua_sell_imported_license_keys',
                        $licenseKeys,
                        $orderId,
                        $neededAmount
                    );
                }

                // There are not enough keys.
                else {
                    // Set the available license keys as "SOLD".
                    apply_filters(
                        'tua_sell_imported_license_keys',
                        $licenseKeys,
                        $orderId,
                        $availableStock
                    );

                    // If there's insufficient stock, create a backorder or handle as needed
                    if ($neededAmount > $availableStock) {
                        // TODO: Handle insufficient stock (e.g., backorder, partial fulfillment)
                    }
                }
            }

            // Set the order as complete.
            $order->update_meta_data( 'tua_order_complete', 1);
            $order->save();
            // Set status to delivered if the setting is on.
            if (Settings::get('tua_auto_delivery' , Settings::SECTION_WOOCOMMERCE)) {
                LicenseResourceRepository::instance()->updateBy(
                    array('order_id' => $orderId),
                    array('status' => LicenseStatus::DELIVERED)
                );
            }

            $orderedLicenseKeys = LicenseResourceRepository::instance()->findAllBy(
                array(
                    'order_id' => $orderId
                )
            );

            /** Plugin event, Type: post, Name: order_license_keys */
            do_action(
                'tua_event_post_order_license_keys',
                array(
                    'orderId'  => $orderId,
                    'licenses' => $orderedLicenseKeys
                )
            );
        }
    }

    /**
     * Sends out the ordered license keys.
     *
     * @param WC_Order $order
     */
    public function processSendLicenseKeysAction($order)
    {
        /** @var object $email */
        $email = WC()->mailer()->emails['tua_Customer_Deliver_License_Keys'];
        // @phpstan-ignore-next-line
        $email->trigger($order->get_id(), $order);
    }

    /**
     * Displays the bought licenses in the order view inside "My Account" -> "Orders".
     *
     * @param WC_Order $order
     */
    public function showBoughtLicenses($order)
    {
        // Check if the parameter is an order ID and get the WC_Order object if necessary
        if (is_int($order)) {
            $order = wc_get_order($order);
        }
        // Return if the order isn't complete.
        if ($order->get_status() != 'completed'
            && ! $order->get_meta( 'tua_order_complete')
        ) {
            return;
        }

        $data = apply_filters('tua_get_customer_license_keys', $order);

        // No license keys found, nothing to do.
        if (!$data) {
            return;
        }

        // Add missing style.
        if (!wp_style_is('tua_admin_css', $list = 'enqueued' )) {
            wp_enqueue_style('tua_admin_css', TUA_CSS_URL . 'main.css');
        }
        echo wp_kses_post(
            wc_get_template_html(
                'myaccount/tua-license-keys.php',
                array(
                    'heading'       => apply_filters('tua_license_keys_table_heading', null),
                    'valid_until'   => apply_filters('tua_license_keys_table_valid_until', null),
                    'data'          => $data,
                    'date_format'   => tua_expiration_format(),
                    'args'          => apply_filters('tua_template_args_myaccount_license_keys', array())
                ),
                '',
                TUA_TEMPLATES_DIR
            )
        );

    }

    /**
     * Adds a new order action used to resend the sold license keys.
     *
     * @param array $actions
     *
     * @return array
     */
    public function addSendLicenseKeysAction($actions, $order)
    {
        global $post;

        if (!empty(LicenseResourceRepository::instance()->findAllBy(array('order_id' => $order->get_id())))) {
            $actions['tua_send_license_keys'] = __('Send license key(s) to customer', 'top-up-agent');
        }

        return $actions;
    }


    /**
     * Hook into the WordPress Order Item Meta Box and display the license key(s).
     *
     * @param int                    $itemId
     * @param WC_Order_Item_Product  $item
     * @param WC_Product_Simple|bool $product
     */
    public function showOrderedLicenses($itemId, $item, $product)
    {
        // Not a WC_Order_Item_Product object? Nothing to do...
        if (!($item instanceof WC_Order_Item_Product)) {
            return;
        }
    
        // The product does not exist anymore
        if (!$product) {
            return;
        }
    
        /** @var LicenseResourceModel[] $licenses */
        $licenses = LicenseResourceRepository::instance()->findAllBy(
            array(
                'order_id' => $item->get_order_id(),
                'product_id' => $product->get_id()
            )
        );
    
        // No license keys? Nothing to do...
        if (!$licenses) {
            return;
        }
    
        $html = sprintf('<p>%s:</p>', esc_html__('The following license keys have been sold by this order', 'top-up-agent'));
        $html .= '<ul class="tua-license-list">';
    
        if (!Settings::get('tua_hide_license_keys')) {
            /** @var LicenseResourceModel $license */
            foreach ($licenses as $license) {
                $html .= sprintf(
                    '<li></span> <code class="tua-placeholder">%s</code></li>',
                    esc_html($license->getDecryptedLicenseKey())
                );
            }
    
            $html .= '</ul>';
    
            $html .= '<span class="tua-txt-copied-to-clipboard" style="display: none">' . esc_html__('Copied to clipboard', 'top-up-agent') . '</span>';
        } else {
            /** @var LicenseResourceModel $license */
            foreach ($licenses as $license) {
                $html .= sprintf(
                    '<li><code class="tua-placeholder empty" data-id="%d"></code></li>',
                    esc_attr($license->getId())
                );
            }
    
            $html .= '</ul>';
            $html .= '<p>';
    
            $html .= sprintf(
                '<a class="button tua-license-keys-show-all" data-order-id="%d">%s</a>',
                $item->get_order_id(),
                esc_html__('Show license key(s)', 'top-up-agent')
            );
    
            $html .= sprintf(
                '<a class="button tua-license-keys-hide-all" data-order-id="%d">%s</a>',
                $item->get_order_id(),
                esc_html__('Hide license key(s)', 'top-up-agent')
            );
    
            $html .= sprintf(
                '<img class="tua-spinner" alt="%s" src="%s">',
                esc_attr__('Please wait...', 'top-up-agent'),
                esc_url(LicensesList::SPINNER_URL)
            );
    
            $html .= '<span class="tua-txt-copied-to-clipboard" style="display: none">' . esc_html__('Copied to clipboard', 'top-up-agent') . '</span>';
    
            $html .= '</p>';
        }
    
       echo wp_kses($html, tua_shapeSpace_allowed_html());
    }    
}