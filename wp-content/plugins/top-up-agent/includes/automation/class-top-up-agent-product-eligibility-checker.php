<?php
/**
 * Product Eligibility Checker
 * Handles checking if products are eligible for automation
 */
class Top_Up_Agent_Product_Eligibility_Checker {
    
    /**
     * Check if order has products eligible for automation
     */
    public static function check_order_eligibility($order) {
        $eligible_products = get_option('top_up_agent_products_automation_enabled', []);
        error_log("Top Up Agent: Eligible products for automation: " . print_r($eligible_products, true));
        
        if (empty($eligible_products)) {
            error_log("Top Up Agent: No products configured for auto-automation");
            return false;
        }
        
        $order_product_ids = self::get_order_product_ids($order);
        error_log("Top Up Agent: Order product IDs: " . print_r($order_product_ids, true));
        
        foreach ($order_product_ids as $product_id) {
            if (in_array($product_id, $eligible_products)) {
                return true;
            }
        }
        
        error_log("Top Up Agent: No eligible products found in order #{$order->get_id()}");
        return false;
    }
    
    /**
     * Get product IDs from order
     */
    public static function get_order_product_ids($order) {
        $product_ids = [];
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
            $product_ids[] = $product_id;
        }
        return $product_ids;
    }
    
    /**
     * Check if specific product has license keys available
     */
    public static function has_license_keys_available($product_ids) {
        global $wpdb;
        $license_table = $wpdb->prefix . 'top_up_agent_license_keys';
        
        foreach ($product_ids as $product_id) {
            $available_key = $wpdb->get_var($wpdb->prepare(
                "SELECT license_key FROM $license_table 
                 WHERE status = 'unused' 
                 AND (product_ids IS NULL OR product_ids = '' OR FIND_IN_SET(%d, product_ids) > 0)
                 LIMIT 1",
                $product_id
            ));
            if ($available_key) {
                return true;
            }
        }
        
        return false;
    }
}
