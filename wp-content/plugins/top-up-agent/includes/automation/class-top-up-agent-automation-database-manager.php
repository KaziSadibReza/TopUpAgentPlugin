<?php
/**
 * Automation Database Manager
 * Handles database operations for automation tracking
 */
class Top_Up_Agent_Automation_Database_Manager {
    
    private static function get_automation_table() {
        global $wpdb;
        return $wpdb->prefix . 'top_up_agent_order_automations';
    }
    
    /**
     * Check if automation already exists for order
     */
    public static function automation_exists($order_id) {
        global $wpdb;
        $automation_table = self::get_automation_table();
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $automation_table WHERE order_id = %d",
            $order_id
        ));
        
        if ($existing) {
            error_log("Top Up Agent: Automation already exists for order #$order_id, skipping");
            return true;
        }
        
        return false;
    }
    
    /**
     * Record successful automation
     */
    public static function record_success($order_id, $player_id, $license_key) {
        global $wpdb;
        $automation_table = self::get_automation_table();
        
        $result = $wpdb->insert($automation_table, [
            'order_id' => $order_id,
            'player_id' => $player_id,
            'license_key' => $license_key,
            'automation_status' => 'running',
            'automation_date' => current_time('mysql')
        ]);
        
        if ($result === false) {
            error_log("Top Up Agent: Failed to record automation success for order #$order_id");
        }
        
        return $result;
    }
    
    /**
     * Record failed automation
     */
    public static function record_failure($order_id, $player_id, $license_key, $error_message) {
        global $wpdb;
        $automation_table = self::get_automation_table();
        
        $result = $wpdb->insert($automation_table, [
            'order_id' => $order_id,
            'player_id' => $player_id,
            'license_key' => $license_key,
            'automation_status' => 'failed',
            'automation_date' => current_time('mysql')
        ]);
        
        if ($result === false) {
            error_log("Top Up Agent: Failed to record automation failure for order #$order_id");
        }
        
        return $result;
    }
    
    /**
     * Get automation status for multiple orders
     */
    public static function get_automation_statuses() {
        global $wpdb;
        $automation_table = self::get_automation_table();
        
        return $wpdb->get_results(
            "SELECT order_id, player_id, license_key, automation_status, automation_date 
             FROM $automation_table",
            OBJECT_K
        );
    }
}
