<?php
/**
 * Check actual database post_status for orders
 */

// Load WordPress
require_once('../../../wp-load.php');

global $wpdb;

echo '<h1>Database Status Check</h1>';

// Check order #3944 specifically
$order_id = 3944;
$post_status = $wpdb->get_var($wpdb->prepare("SELECT post_status FROM {$wpdb->posts} WHERE ID = %d", $order_id));

echo "<h2>Order #$order_id Database Status</h2>";
echo "<p><strong>post_status in database:</strong> <code>" . esc_html($post_status) . "</code></p>";

// Get WooCommerce order object
$order = wc_get_order($order_id);
if ($order) {
    echo "<p><strong>WC Order get_status():</strong> <code>" . esc_html($order->get_status()) . "</code></p>";
    echo "<p><strong>Customer ID:</strong> " . $order->get_customer_id() . "</p>";
    echo "<p><strong>Billing Email:</strong> " . $order->get_billing_email() . "</p>";
}

// Check all automation-failed orders in database
echo "<h2>All Orders with 'automation-failed' in Database</h2>";
$results = $wpdb->get_results("
    SELECT ID, post_status, post_author 
    FROM {$wpdb->posts} 
    WHERE post_type = 'shop_order' 
    AND (post_status LIKE '%automation-failed%' OR post_status = 'automation-failed' OR post_status = 'wc-automation-failed')
    ORDER BY ID DESC
    LIMIT 20
");

if ($results) {
    echo '<table border="1" cellpadding="10">';
    echo '<tr><th>Order ID</th><th>post_status (raw)</th><th>post_author</th><th>Customer ID (meta)</th></tr>';
    
    foreach ($results as $row) {
        $customer_id = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_customer_user'", $row->ID));
        echo '<tr>';
        echo '<td>#' . $row->ID . '</td>';
        echo '<td><code>' . esc_html($row->post_status) . '</code></td>';
        echo '<td>' . $row->post_author . '</td>';
        echo '<td>' . $customer_id . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p>No orders found with automation-failed status</p>';
}

// Check what statuses are registered
echo '<h2>Registered WooCommerce Statuses</h2>';
echo '<pre>';
print_r(wc_get_order_statuses());
echo '</pre>';

// Try to query orders using WooCommerce for customer 1
echo '<h2>WooCommerce Query Test for Customer ID 1</h2>';
$test_orders = wc_get_orders(array(
    'customer' => 1,
    'status' => 'automation-failed',
    'limit' => 10
));
echo '<p>Found ' . count($test_orders) . ' orders with automation-failed status for customer 1</p>';

// Try with wc- prefix
$test_orders2 = wc_get_orders(array(
    'customer' => 1,
    'status' => 'wc-automation-failed',
    'limit' => 10
));
echo '<p>Found ' . count($test_orders2) . ' orders with wc-automation-failed status for customer 1</p>';

// Try with array of both
$test_orders3 = wc_get_orders(array(
    'customer' => 1,
    'status' => array('automation-failed', 'wc-automation-failed'),
    'limit' => 10
));
echo '<p>Found ' . count($test_orders3) . ' orders with either automation-failed or wc-automation-failed for customer 1</p>';
