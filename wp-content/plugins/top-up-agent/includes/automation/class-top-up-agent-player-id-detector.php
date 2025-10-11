<?php
/**
 * Player ID Detection Service
 * Handles detection of Player IDs from various sources (order meta, item meta, user meta)
 */
class Top_Up_Agent_Player_ID_Detector {
    
    /**
     * Detect Player ID from multiple sources
     */
    public static function detect_player_id($order) {
        $player_id_meta_key = get_option('top_up_agent_player_id_meta_key', 'player_id');
        $player_id = '';
        
        error_log("Top Up Agent: Looking for Player ID using meta key: $player_id_meta_key");
        
        // First check order meta
        $player_id = self::get_from_order_meta($order, $player_id_meta_key);
        
        // If not found, check item meta (where Player ID Code is often stored)
        if (!$player_id) {
            $player_id = self::get_from_item_meta($order, $player_id_meta_key);
        }
        
        // If still not found, check user meta
        if (!$player_id) {
            $player_id = self::get_from_user_meta($order, $player_id_meta_key);
        }
        
        error_log("Top Up Agent: Final Player ID for order #{$order->get_id()}: " . ($player_id ?: 'NOT FOUND'));
        
        return $player_id;
    }
    
    /**
     * Get Player ID from order meta
     */
    private static function get_from_order_meta($order, $player_id_meta_key) {
        $player_id = $order->get_meta('_' . $player_id_meta_key);
        error_log("Top Up Agent: Order meta check for '_$player_id_meta_key': " . ($player_id ?: 'not found'));
        return $player_id;
    }
    
    /**
     * Get Player ID from item meta
     */
    private static function get_from_item_meta($order, $player_id_meta_key) {
        foreach ($order->get_items() as $item_id => $item) {
            // Check for common Player ID meta keys in item
            $item_meta_keys = [
                'Player ID Code',
                'player_id_code', 
                'player_id',
                '_player_id_code',
                '_player_id',
                $player_id_meta_key
            ];
            
            error_log("Top Up Agent: Checking item #$item_id meta");
            foreach ($item_meta_keys as $meta_key) {
                $item_player_id = wc_get_order_item_meta($item_id, $meta_key, true);
                if ($item_player_id) {
                    error_log("Top Up Agent: Found Player ID '$item_player_id' in item meta key '$meta_key'");
                    return $item_player_id;
                }
            }
        }
        return '';
    }
    
    /**
     * Get Player ID from user meta
     */
    private static function get_from_user_meta($order, $player_id_meta_key) {
        $customer_id = $order->get_customer_id();
        if ($customer_id) {
            $user_player_id = get_user_meta($customer_id, $player_id_meta_key, true);
            if ($user_player_id) {
                error_log("Top Up Agent: Found Player ID '$user_player_id' in user meta");
                return $user_player_id;
            }
        }
        return '';
    }
    
    /**
     * Get Player ID for display (no logging)
     */
    public static function get_display_player_id($order) {
        $player_id_meta_key = get_option('top_up_agent_player_id_meta_key', 'player_id');
        
        // Check order meta
        $player_id = $order->get_meta('_' . $player_id_meta_key);
        if ($player_id) return $player_id;
        
        // Check item meta
        foreach ($order->get_items() as $item_id => $item) {
            $item_meta_keys = [
                'Player ID Code',
                'player_id_code', 
                'player_id',
                '_player_id_code',
                '_player_id',
                $player_id_meta_key
            ];
            
            foreach ($item_meta_keys as $meta_key) {
                $item_player_id = wc_get_order_item_meta($item_id, $meta_key, true);
                if ($item_player_id) {
                    return $item_player_id;
                }
            }
        }
        
        // Check user meta
        $customer_id = $order->get_customer_id();
        if ($customer_id) {
            return get_user_meta($customer_id, $player_id_meta_key, true);
        }
        
        return '';
    }
}
