<?php
/**
 * WooCommerce Template Helper
 * Handles display logic for WooCommerce template
 */
class Top_Up_Agent_WooCommerce_Template_Helper {
    
    /**
     * Get recent WooCommerce orders
     */
    public static function get_recent_orders($limit = 50) {
        return wc_get_orders([
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => ['processing', 'completed'],
            'type' => 'shop_order',
            'return' => 'objects'
        ]);
    }
    
    /**
     * Check if order should be processed
     */
    public static function should_process_order($order) {
        // Skip if not a proper order object or if it's a refund
        if (!$order || !is_a($order, 'WC_Order') || $order->get_type() === 'shop_order_refund') {
            return false;
        }
        return true;
    }
    
    /**
     * Get safe customer name
     */
    public static function get_safe_customer_name($order) {
        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();
        
        if ($first_name || $last_name) {
            return trim($first_name . ' ' . $last_name);
        }
        
        return 'N/A';
    }
    
    /**
     * Get product names from order
     */
    public static function get_product_names($order) {
        $products = [];
        foreach ($order->get_items() as $item) {
            $products[] = $item->get_name();
        }
        return $products;
    }
    
    /**
     * Render automation status
     */
    public static function render_automation_status($automation_status) {
        if (!$automation_status) {
            echo '<span style="color: gray;">No automation</span>';
            return;
        }
        
        $status_colors = [
            'pending' => 'orange',
            'running' => 'blue',
            'completed' => 'green',
            'failed' => 'red'
        ];
        
        $color = $status_colors[$automation_status->automation_status] ?? 'gray';
        echo '<span style="color: ' . $color . ';">' . 
             ucfirst($automation_status->automation_status) . 
             '</span>';
        
        if ($automation_status->automation_date) {
            echo '<br><small>' . date('Y-m-d H:i', strtotime($automation_status->automation_date)) . '</small>';
        }
    }
    
    /**
     * Render debug meta information
     */
    public static function render_debug_meta($order) {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <details style="margin-top:5px;">
            <summary style="cursor:pointer; font-size:11px;">Debug: Show available meta keys</summary>
            <div style="font-size:11px; background:#fff; padding:5px; margin:5px 0; border:1px solid #ddd;">
                <strong>Order Meta:</strong>
                <?php 
                $order_meta = $order->get_meta_data();
                if ($order_meta) {
                    foreach ($order_meta as $meta) {
                        $value = is_object($meta->value) || is_array($meta->value) ? 
                                json_encode($meta->value) : $meta->value;
                        echo '<br>- ' . esc_html($meta->key) . ': ' . esc_html($value);
                    }
                } else {
                    echo '<br>No order meta found';
                }
                ?>
                
                <br><br><strong>Item Meta:</strong>
                <?php 
                foreach ($order->get_items() as $item_id => $item) {
                    echo '<br><strong>Item: ' . esc_html($item->get_name()) . '</strong>';
                    $item_meta = $item->get_meta_data();
                    if ($item_meta) {
                        foreach ($item_meta as $meta) {
                            $value = is_object($meta->value) || is_array($meta->value) ? 
                                    json_encode($meta->value) : $meta->value;
                            echo '<br>- ' . esc_html($meta->key) . ': ' . esc_html($value);
                        }
                    } else {
                        echo '<br>No item meta found';
                    }
                }
                ?>
            </div>
        </details>
        <?php
    }
    
    /**
     * Check if license keys are available for order products
     */
    public static function check_license_availability($order) {
        $product_ids = Top_Up_Agent_Product_Eligibility_Checker::get_order_product_ids($order);
        return Top_Up_Agent_Product_Eligibility_Checker::has_license_keys_available($product_ids);
    }
}
