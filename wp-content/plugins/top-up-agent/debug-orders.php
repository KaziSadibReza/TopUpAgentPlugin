<?php
/**
 * Temporary debug script to check automation-failed orders
 * Add this to wp-content/plugins/top-up-agent/ and access via browser
 * URL: /wp-content/plugins/top-up-agent/debug-orders.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if WooCommerce is active
if (!function_exists('wc_get_orders')) {
    die('WooCommerce is not active');
}

echo '<h1>Debug: Automation Failed Orders</h1>';

// Get all orders with automation-failed status
$orders = wc_get_orders(array(
    'limit' => -1,
    'status' => array('automation-failed', 'wc-automation-failed'),
    'orderby' => 'date',
    'order' => 'DESC'
));

echo '<h2>Total Orders with automation-failed status: ' . count($orders) . '</h2>';

if (empty($orders)) {
    echo '<p>No orders found with automation-failed status.</p>';
    
    echo '<h2>Checking all custom statuses:</h2>';
    $all_custom = wc_get_orders(array(
        'limit' => -1,
        'status' => array(
            'automation-pending',
            'automation-processing', 
            'automation-failed',
            'automation-completed',
            'wc-automation-pending',
            'wc-automation-processing',
            'wc-automation-failed',
            'wc-automation-completed'
        )
    ));
    echo '<p>Total custom automation status orders: ' . count($all_custom) . '</p>';
    
    foreach ($all_custom as $order) {
        echo '<p>Order #' . $order->get_id() . ' - Status: ' . $order->get_status() . ' - Customer: ' . $order->get_billing_email() . '</p>';
    }
} else {
    echo '<table border="1" cellpadding="10">';
    echo '<tr><th>Order ID</th><th>Status</th><th>Customer Email</th><th>Customer ID</th><th>Date</th></tr>';
    
    foreach ($orders as $order) {
        echo '<tr>';
        echo '<td>#' . $order->get_id() . '</td>';
        echo '<td>' . $order->get_status() . '</td>';
        echo '<td>' . $order->get_billing_email() . '</td>';
        echo '<td>' . $order->get_customer_id() . '</td>';
        echo '<td>' . $order->get_date_created()->date('Y-m-d H:i:s') . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
}

echo '<h2>Registered Order Statuses:</h2>';
echo '<pre>';
print_r(wc_get_order_statuses());
echo '</pre>';
