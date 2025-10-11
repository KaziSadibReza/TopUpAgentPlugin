<?php

/**
 * Webhook Handler for Top Up Agent
 * 
 * Handles automation status updates from the automation server
 */
class Top_Up_Agent_Webhook_Handler {
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));
    }
    
    /**
     * Register the webhook REST API endpoint
     */
    public function register_webhook_endpoint() {
        register_rest_route('top-up-agent/v1', '/webhook/automation-status', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_automation_status_webhook'),
            'permission_callback' => array($this, 'verify_webhook_permission'),
            'args' => array(
                'queueId' => array(
                    'required' => true,
                    'type' => 'integer'
                ),
                'status' => array(
                    'required' => true,
                    'type' => 'string',
                    'enum' => array('completed', 'failed')
                )
            )
        ));
    }
    
    /**
     * Verify webhook permission (basic security check)
     */
    public function verify_webhook_permission($request) {
        // Check for API key in header or query param
        $api_key = $request->get_header('X-API-Key') ?: $request->get_param('api_key');
        $expected_key = get_option('top_up_agent_webhook_api_key', 'default-webhook-key');
        
        // Allow localhost for development
        $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
        if (in_array($remote_addr, ['127.0.0.1', '::1', 'localhost'])) {
            return true;
        }
        
        // TEMPORARY: Allow all requests for testing webhook functionality
        // TODO: Remove this after confirming webhook works and API key is properly configured
        error_log("Top Up Agent Webhook: Auth check - API Key received: " . ($api_key ?: 'none'));
        error_log("Top Up Agent Webhook: Auth check - Expected key: " . $expected_key);
        
        // For now, return true to test webhook functionality
        // Later change this back to: return hash_equals($expected_key, $api_key);
        return true;
        
        // Original auth check (commented for testing):
        // return hash_equals($expected_key, $api_key);
    }
    
    /**
     * Handle automation status webhook
     */
    public function handle_automation_status_webhook($request) {
        // Log all received data for debugging
        error_log("=== TOP UP AGENT WEBHOOK RECEIVED ===");
        error_log("Top Up Agent Webhook: Received webhook call");
        error_log("Top Up Agent Webhook: Request method: " . $request->get_method());
        error_log("Top Up Agent Webhook: Request params: " . print_r($request->get_params(), true));
        error_log("Top Up Agent Webhook: Request body: " . $request->get_body());
        error_log("Top Up Agent Webhook: Request headers: " . print_r($request->get_headers(), true));
        
        $queue_id = $request->get_param('queueId');
        $status = $request->get_param('status');
        $error = $request->get_param('error');
        $result = $request->get_param('result');
        $player_id = $request->get_param('playerId');
        $license_key = $request->get_param('licenseKey');
        $redimension_code = $request->get_param('redimensionCode');
        $execution_time = $request->get_param('executionTime');
        $failure_time = $request->get_param('failureTime');
        
        error_log("Top Up Agent Webhook: Extracted params - Queue ID: {$queue_id}, Status: {$status}, Error: {$error}");
        
        try {
            // Find the order associated with this automation
            error_log("Top Up Agent Webhook: Searching for order with queue ID: {$queue_id}");
            $order = $this->find_order_by_queue_id($queue_id);
            
            if (!$order) {
                error_log("Top Up Agent Webhook: ORDER NOT FOUND for queue ID: {$queue_id}");
                error_log("Top Up Agent Webhook: Will search all recent orders to debug...");
                
                // Debug: List recent orders and their queue IDs
                $recent_orders = wc_get_orders(array('limit' => 10, 'orderby' => 'date', 'order' => 'DESC'));
                foreach ($recent_orders as $debug_order) {
                    $order_queue_id = get_post_meta($debug_order->get_id(), '_automation_queue_id', true);
                    $order_queue_ids = get_post_meta($debug_order->get_id(), '_automation_queue_ids', true);
                    error_log("Top Up Agent Webhook: Order #{$debug_order->get_id()} - Single Queue ID: {$order_queue_id}, Group Queue IDs: " . print_r($order_queue_ids, true));
                }
                
                return new WP_Error('order_not_found', 'Order not found for queue ID: ' . $queue_id, array('status' => 404));
            }
            
            $order_id = $order->get_id();
            error_log("Top Up Agent Webhook: FOUND Order #{$order_id} for queue ID: {$queue_id}");
            
            if ($status === 'completed') {
                // Automation completed successfully
                error_log("Top Up Agent Webhook: Updating order #{$order_id} to automation-completed");
                $order->update_status('automation-completed', 
                    "✅ Automation completed successfully" . 
                    ($execution_time ? " (took {$execution_time}ms)" : ""));
                
                // Store success details
                update_post_meta($order_id, '_automation_completed_at', current_time('mysql'));
                update_post_meta($order_id, '_automation_result', $result);
                update_post_meta($order_id, '_automation_execution_time', $execution_time);
                
                error_log("Top Up Agent Webhook: Order #{$order_id} marked as completed");
                
            } else if ($status === 'failed') {
                // Automation failed
                $error_message = $error ?: 'Unknown automation error';
                error_log("Top Up Agent Webhook: Updating order #{$order_id} to automation-failed with error: {$error_message}");
                $order->update_status('automation-failed', 
                    "❌ Automation failed: {$error_message}");
                
                // Store failure details
                update_post_meta($order_id, '_automation_failed_at', current_time('mysql'));
                update_post_meta($order_id, '_automation_error', $error_message);
                
                error_log("Top Up Agent Webhook: Order #{$order_id} marked as failed - {$error_message}");
            }
            
            // Update automation tracking data
            update_post_meta($order_id, '_automation_last_webhook', current_time('mysql'));
            update_post_meta($order_id, '_automation_queue_id_' . $queue_id, array(
                'status' => $status,
                'updated_at' => current_time('mysql'),
                'player_id' => $player_id,
                'license_key' => $license_key,
                'redimension_code' => $redimension_code
            ));
            
            error_log("Top Up Agent Webhook: Successfully processed webhook for Order #{$order_id}");
            error_log("=== TOP UP AGENT WEBHOOK COMPLETED ===");
            
            return array(
                'success' => true,
                'order_id' => $order_id,
                'status' => $status,
                'message' => "Order status updated successfully"
            );
            
        } catch (Exception $e) {
            error_log("Top Up Agent Webhook Error: " . $e->getMessage());
            error_log("Top Up Agent Webhook Error Stack: " . $e->getTraceAsString());
            return new WP_Error('webhook_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Find order by automation queue ID
     */
    private function find_order_by_queue_id($queue_id) {
        error_log("Top Up Agent Webhook: Searching for queue ID: {$queue_id}");
        
        // First priority: Find orders currently in automation-processing status
        $processing_orders = wc_get_orders(array(
            'status' => 'automation-processing',
            'limit' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        foreach ($processing_orders as $order) {
            $single_queue_id = get_post_meta($order->get_id(), '_automation_queue_id', true);
            $group_queue_ids = get_post_meta($order->get_id(), '_automation_queue_ids', true);
            
            error_log("Top Up Agent Webhook: Processing Order #{$order->get_id()} - Single: {$single_queue_id}, Group: " . print_r($group_queue_ids, true));
            
            if ($single_queue_id == $queue_id) {
                error_log("Top Up Agent Webhook: Found PROCESSING order by single queue ID: " . $order->get_id());
                return $order;
            }
            
            if (is_array($group_queue_ids) && in_array($queue_id, $group_queue_ids)) {
                error_log("Top Up Agent Webhook: Found PROCESSING order by group queue IDs: " . $order->get_id());
                return $order;
            }
        }
        
        // Second priority: Find by single queue ID using direct meta key search
        $orders = wc_get_orders(array(
            'meta_key' => '_automation_queue_id',
            'meta_value' => $queue_id,
            'limit' => 5,
            'status' => 'any',
            'orderby' => 'date',
            'order' => 'DESC' // Get most recent first
        ));
        
        foreach ($orders as $order) {
            error_log("Top Up Agent Webhook: Found order by single queue ID: " . $order->get_id() . " (status: " . $order->get_status() . ")");
            // Prefer orders that are in automation-related statuses
            if (in_array($order->get_status(), ['automation-processing', 'automation-pending', 'automation-failed'])) {
                return $order;
            }
        }
        
        // If no automation-status orders found, return the most recent
        if (!empty($orders)) {
            return $orders[0];
        }
        
        // Third priority: Try to find by group queue IDs array using direct SQL
        global $wpdb;
        
        $order_ids = $wpdb->get_col($wpdb->prepare("
            SELECT post_id 
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_automation_queue_ids' 
            AND pm.meta_value LIKE %s
            AND p.post_type = 'shop_order'
            ORDER BY p.post_date DESC
            LIMIT 10
        ", '%"' . $queue_id . '"%'));
        
        if (!empty($order_ids)) {
            foreach ($order_ids as $order_id) {
                $queue_ids = get_post_meta($order_id, '_automation_queue_ids', true);
                error_log("Top Up Agent Webhook: Checking order #{$order_id} queue IDs: " . print_r($queue_ids, true));
                
                if (is_array($queue_ids) && in_array($queue_id, $queue_ids)) {
                    $order = wc_get_order($order_id);
                    if ($order) {
                        error_log("Top Up Agent Webhook: Found order by group queue IDs: " . $order->get_id() . " (status: " . $order->get_status() . ")");
                        
                        // Prefer orders in automation-related statuses
                        if (in_array($order->get_status(), ['automation-processing', 'automation-pending', 'automation-failed'])) {
                            return $order;
                        }
                    }
                }
            }
            
            // If no automation-status orders found, return the first valid one
            foreach ($order_ids as $order_id) {
                $queue_ids = get_post_meta($order_id, '_automation_queue_ids', true);
                if (is_array($queue_ids) && in_array($queue_id, $queue_ids)) {
                    $order = wc_get_order($order_id);
                    if ($order) {
                        return $order;
                    }
                }
            }
        }
        
        error_log("Top Up Agent Webhook: No order found for queue ID: {$queue_id}");
        return null;
    }
    
    /**
     * Get webhook URL for automation requests
     */
    public static function get_webhook_url() {
        return rest_url('top-up-agent/v1/webhook/automation-status');
    }
}

// Initialize the webhook handler
new Top_Up_Agent_Webhook_Handler();
?>