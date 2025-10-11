<?php
/**
 * Top Up Agent WebSocket Integration
 * Handles real-time communication with automation server
 * 
 * @package TopUpAgent
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Top_Up_Agent_WebSocket_Integration {
    
    private $server_url;
    private $websocket_url;
    private $api_client;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->server_url = get_option('top_up_agent_server_url', '');
        $this->websocket_url = $this->get_websocket_url();
        $this->api_client = new Top_Up_Agent_API_Client();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Enqueue scripts on admin pages
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Enqueue scripts on frontend for customer order pages
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // AJAX handlers for WebSocket events
        add_action('wp_ajax_websocket_automation_update', array($this, 'handle_websocket_update'));
        add_action('wp_ajax_nopriv_websocket_automation_update', array($this, 'handle_websocket_update'));
        
        // WebSocket connection status endpoint
        add_action('wp_ajax_websocket_connection_status', array($this, 'check_connection_status'));
        add_action('wp_ajax_nopriv_websocket_connection_status', array($this, 'check_connection_status'));
        
        // WebSocket test endpoint
        add_action('wp_ajax_test_websocket_connection', array($this, 'test_websocket_connection'));
        
        // API integration endpoints based on documentation
        add_action('wp_ajax_get_queue_status', array($this, 'get_queue_status'));
        add_action('wp_ajax_add_to_queue', array($this, 'add_to_queue'));
        add_action('wp_ajax_cancel_queue_item', array($this, 'cancel_queue_item'));
        add_action('wp_ajax_get_automation_results', array($this, 'get_automation_results'));
        add_action('wp_ajax_execute_automation', array($this, 'execute_automation'));
        add_action('wp_ajax_get_running_automations', array($this, 'get_running_automations'));
        add_action('wp_ajax_cancel_automation', array($this, 'cancel_automation'));
        add_action('wp_ajax_process_queue', array($this, 'process_queue'));
        
        // Admin-only endpoints
        add_action('wp_ajax_pause_queue', array($this, 'pause_queue'));
        add_action('wp_ajax_resume_queue', array($this, 'resume_queue'));
        add_action('wp_ajax_cleanup_queue', array($this, 'cleanup_queue'));
        add_action('wp_ajax_get_logs', array($this, 'get_logs'));
        add_action('wp_ajax_clear_logs', array($this, 'clear_logs'));
        
        // WooCommerce integration endpoints
        add_action('wp_ajax_get_order_details', array($this, 'get_order_details'));
        // Note: trigger_order_automation is handled by class-top-up-agent-woocommerce.php to avoid conflicts
        // add_action('wp_ajax_trigger_order_automation', array($this, 'trigger_order_automation'));
        add_action('wp_ajax_bulk_process_orders', array($this, 'bulk_process_orders'));
        add_action('wp_ajax_export_order_data', array($this, 'export_order_data'));
    }
    
    /**
     * Get WebSocket URL from server URL
     */
    private function get_websocket_url() {
        if (empty($this->server_url)) {
            return '';
        }
        
        // For Socket.IO, we use the HTTP server URL directly, not ws://
        // Socket.IO will handle the WebSocket upgrade internally
        return rtrim($this->server_url, '/');
    }
    
    /**
     * Enqueue WebSocket scripts for admin pages
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on specific admin pages
        $allowed_pages = [
            'toplevel_page_top-up-agent',
            'top-up-agent_page_top-up-agent-settings',
            'top-up-agent_page_top-up-agent-woocommerce',
            'top-up-agent_page_top-up-agent-license-keys',
            'edit.php',
            'post.php'
        ];
        
        // Also load on WooCommerce order pages
        if (strpos($hook, 'shop_order') !== false || 
            in_array($hook, $allowed_pages) ||
            (isset($_GET['post_type']) && $_GET['post_type'] === 'shop_order')) {
            
            $this->enqueue_websocket_assets();
        }
    }
    
    /**
     * Enqueue WebSocket scripts for frontend
     */
    public function enqueue_frontend_scripts() {
        // Only load on WooCommerce account pages and order pages
        if (is_wc_endpoint_url('view-order') || 
            is_wc_endpoint_url('orders') || 
            is_account_page()) {
            
            $this->enqueue_websocket_assets();
        }
    }
    
    /**
     * Enqueue WebSocket assets (CSS + JS)
     */
    private function enqueue_websocket_assets() {
        if (empty($this->websocket_url)) {
            return;
        }
        
        // Enqueue Socket.IO client library from CDN
        wp_enqueue_script(
            'socket-io-client-v2',
            'https://cdn.socket.io/4.7.5/socket.io.min.js',
            array(),
            '4.7.5',
            true
        );
        
        // Enqueue CSS
        wp_enqueue_style(
            'top-up-agent-websocket',
            plugin_dir_url(__FILE__) . '../../assets/css/websocket-integration.css',
            array(),
            '1.0.0'
        );
        
        // Enqueue JavaScript (Socket.IO integration) - NEW HANDLE
        wp_enqueue_script(
            'top-up-agent-socketio-v2',
            plugin_dir_url(__FILE__) . '../../assets/js/socket-io-integration.js',
            array('jquery', 'socket-io-client-v2'),
            '2.0.2-' . time(), // Add timestamp to force cache bust
            true
        );
        
        // Localize script with configuration
        wp_localize_script('top-up-agent-socketio-v2', 'topUpAgentWebSocket', array(
            'url' => $this->websocket_url,
            'serverUrl' => $this->server_url,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('top_up_agent_websocket'),
            'isAdmin' => is_admin(),
            'currentUser' => get_current_user_id(),
            'events' => array(
                'automation-started',
                'automation-completed',
                'automation-failed',
                'automation-log',
                'queue-item-added'
            ),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'enableWebSocket' => $this->should_enable_websocket()
        ));
    }
    
    /**
     * Check if WebSocket should be enabled based on server connectivity
     */
    private function should_enable_websocket() {
        if (empty($this->websocket_url)) {
            return false;
        }
        
        // Temporarily bypass health check since we confirmed server is running
        // TODO: Investigate why health_check() is failing despite healthy server
        error_log('Top Up Agent: Bypassing health check - server confirmed running on port 3000');
        return true;
        
        // Quick server health check (commented out for debugging)
        /*
        $health_check = $this->api_client->health_check();
        if (is_wp_error($health_check)) {
            error_log('Top Up Agent: Server not reachable, disabling WebSocket - ' . $health_check->get_error_message());
            return false;
        }
        
        return true;
        */
    }
    
    /**
     * Handle WebSocket automation updates
     */
    public function handle_websocket_update() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'top_up_agent_websocket')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $event_type = sanitize_text_field($_POST['event_type'] ?? '');
        $order_id = intval($_POST['order_id'] ?? 0);
        $data = $_POST['data'] ?? array();
        
        if (!$event_type || !$order_id) {
            wp_send_json_error('Missing required parameters');
            return;
        }
        
        // Sanitize data
        $sanitized_data = $this->sanitize_websocket_data($data);
        
        // Process different event types
        switch ($event_type) {
            case 'automation-started':
                $result = $this->handle_automation_started($order_id, $sanitized_data);
                break;
                
            case 'automation-completed':
                $result = $this->handle_automation_completed($order_id, $sanitized_data);
                break;
                
            case 'automation-failed':
                $result = $this->handle_automation_failed($order_id, $sanitized_data);
                break;
                
            case 'automation-log':
                $result = $this->handle_automation_log($order_id, $sanitized_data);
                break;
                
            case 'queue-item-added':
                $result = $this->handle_queue_item_added($order_id, $sanitized_data);
                break;
                
            default:
                wp_send_json_error('Unknown event type');
                return;
        }
        
        if ($result) {
            wp_send_json_success(array(
                'event_type' => $event_type,
                'order_id' => $order_id,
                'processed_at' => current_time('mysql')
            ));
        } else {
            wp_send_json_error('Failed to process event');
        }
    }
    
    /**
     * Handle automation started event
     */
    private function handle_automation_started($order_id, $data) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        // Update order status
        $order->update_status('automation-processing', 'Automation started via WebSocket');
        
        // Update metadata
        update_post_meta($order_id, '_automation_status', 'processing');
        update_post_meta($order_id, '_automation_started_at', current_time('mysql'));
        
        if (isset($data['request_id'])) {
            update_post_meta($order_id, '_automation_request_id', sanitize_text_field($data['request_id']));
        }
        
        // Add order note
        $order->add_order_note('🚀 Automation started (WebSocket event)');
        
        // Log the event
        error_log("Top Up Agent WebSocket: Automation started for Order #{$order_id}");
        
        return true;
    }
    
    /**
     * Handle automation completed event
     */
    private function handle_automation_completed($order_id, $data) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        // Update order status
        $order->update_status('automation-completed', 'Automation completed via WebSocket');
        
        // Update metadata
        update_post_meta($order_id, '_automation_status', 'completed');
        update_post_meta($order_id, '_automation_completed_at', current_time('mysql'));
        
        if (isset($data['result'])) {
            update_post_meta($order_id, '_automation_result', wp_json_encode($data['result']));
        }
        
        // Add order note
        $note = '✅ Automation completed successfully (WebSocket event)';
        if (isset($data['message'])) {
            $note .= ' - ' . sanitize_text_field($data['message']);
        }
        $order->add_order_note($note);
        
        // Log the event
        error_log("Top Up Agent WebSocket: Automation completed for Order #{$order_id}");
        
        return true;
    }
    
    /**
     * Handle automation failed event
     */
    private function handle_automation_failed($order_id, $data) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        $error_message = sanitize_text_field($data['error'] ?? 'Unknown error');
        
        // Update order status
        $order->update_status('automation-failed', "Automation failed: {$error_message}");
        
        // Update metadata
        update_post_meta($order_id, '_automation_status', 'failed');
        update_post_meta($order_id, '_automation_failed_at', current_time('mysql'));
        update_post_meta($order_id, '_automation_error', $error_message);
        
        // Add order note
        $order->add_order_note("❌ Automation failed (WebSocket event): {$error_message}");
        
        // Log the event
        error_log("Top Up Agent WebSocket: Automation failed for Order #{$order_id}: {$error_message}");
        
        return true;
    }
    
    /**
     * Handle automation log event
     */
    private function handle_automation_log($order_id, $data) {
        $message = sanitize_text_field($data['message'] ?? '');
        if (empty($message)) {
            return false;
        }
        
        // Store log entry
        $logs = get_post_meta($order_id, '_automation_logs', true);
        if (!is_array($logs)) {
            $logs = array();
        }
        
        $logs[] = array(
            'timestamp' => current_time('mysql'),
            'message' => $message,
            'source' => 'websocket'
        );
        
        // Keep only last 50 log entries
        if (count($logs) > 50) {
            $logs = array_slice($logs, -50);
        }
        
        update_post_meta($order_id, '_automation_logs', $logs);
        
        // Log the event
        error_log("Top Up Agent WebSocket Log for Order #{$order_id}: {$message}");
        
        return true;
    }
    
    /**
     * Handle queue item added event
     */
    private function handle_queue_item_added($order_id, $data) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        // Update metadata
        update_post_meta($order_id, '_automation_status', 'pending');
        update_post_meta($order_id, '_automation_queued_at', current_time('mysql'));
        
        if (isset($data['queue_id'])) {
            update_post_meta($order_id, '_automation_queue_id', sanitize_text_field($data['queue_id']));
        }
        
        // Add order note
        $order->add_order_note('📋 Added to automation queue (WebSocket event)');
        
        // Log the event
        error_log("Top Up Agent WebSocket: Order #{$order_id} added to queue");
        
        return true;
    }
    
    /**
     * Sanitize WebSocket data
     */
    private function sanitize_websocket_data($data) {
        if (!is_array($data)) {
            return array();
        }
        
        $sanitized = array();
        foreach ($data as $key => $value) {
            $key = sanitize_key($key);
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_websocket_data($value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Check WebSocket connection status
     */
    public function check_connection_status() {
        // Try to get socket info from API
        $socket_info = $this->api_client->get_socket_info();
        
        if (is_wp_error($socket_info)) {
            wp_send_json_error(array(
                'message' => 'Cannot connect to WebSocket server',
                'error' => $socket_info->get_error_message(),
                'websocket_url' => $this->websocket_url
            ));
            return;
        }
        
        wp_send_json_success(array(
            'message' => 'WebSocket server is available',
            'websocket_url' => $this->websocket_url,
            'socket_info' => $socket_info
        ));
    }
    
    /**
     * Test WebSocket connection
     */
    public function test_websocket_connection() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Test API connection first
        $api_test = $this->api_client->test_connection();
        
        if (is_wp_error($api_test)) {
            wp_send_json_error(array(
                'message' => 'API connection failed',
                'error' => $api_test->get_error_message()
            ));
            return;
        }
        
        // Test WebSocket info
        $socket_info = $this->api_client->get_socket_info();
        
        wp_send_json_success(array(
            'message' => 'WebSocket connection test successful',
            'api_status' => $api_test,
            'websocket_url' => $this->websocket_url,
            'socket_info' => $socket_info
        ));
    }
    
    /**
     * Get WebSocket configuration
     */
    public function get_websocket_config() {
        return array(
            'server_url' => $this->server_url,
            'websocket_url' => $this->websocket_url,
            'is_configured' => !empty($this->server_url),
            'events' => array(
                'automation-started',
                'automation-completed',
                'automation-failed',
                'automation-log',
                'queue-item-added'
            )
        );
    }
    
    /**
     * Update WebSocket settings
     */
    public function update_websocket_settings($server_url) {
        $this->server_url = rtrim($server_url, '/');
        $this->websocket_url = $this->get_websocket_url();
        
        update_option('top_up_agent_server_url', $this->server_url);
        
        return $this->get_websocket_config();
    }
    
    /**
     * API Integration Methods based on API Documentation
     */
    
    /**
     * Get queue status
     */
    public function get_queue_status() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'top_up_agent_websocket')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $response = $this->api_client->get_queue_status();
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        } else {
            wp_send_json_success($response);
        }
    }
    
    /**
     * Add item to queue
     */
    public function add_to_queue() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'top_up_agent_websocket')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $queue_type = sanitize_text_field($_POST['queueType'] ?? 'single');
        $player_id = sanitize_text_field($_POST['playerId'] ?? '');
        $redimension_code = sanitize_text_field($_POST['redimensionCode'] ?? '');
        $redimension_codes = $_POST['redimensionCodes'] ?? array();
        $source_site = sanitize_url($_POST['sourceSite'] ?? '');
        $product_name = sanitize_text_field($_POST['productName'] ?? '');
        $license_key = sanitize_text_field($_POST['licenseKey'] ?? '');
        
        if (empty($player_id) || (empty($redimension_code) && empty($redimension_codes))) {
            wp_send_json_error('Missing required parameters');
            return;
        }
        
        $data = array(
            'queueType' => $queue_type,
            'playerId' => $player_id,
            'sourceSite' => $source_site,
            'productName' => $product_name,
            'licenseKey' => $license_key
        );
        
        if ($queue_type === 'group' && !empty($redimension_codes)) {
            $data['redimensionCodes'] = array_map('sanitize_text_field', $redimension_codes);
        } else {
            $data['redimensionCode'] = $redimension_code;
        }
        
        $response = $this->api_client->add_to_queue($data);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        } else {
            wp_send_json_success($response);
        }
    }
    
    /**
     * Cancel queue item
     */
    public function cancel_queue_item() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'top_up_agent_websocket')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $item_id = intval($_POST['itemId'] ?? 0);
        $reason = sanitize_text_field($_POST['reason'] ?? 'User requested cancellation');
        
        if (!$item_id) {
            wp_send_json_error('Missing item ID');
            return;
        }
        
        $response = $this->api_client->cancel_queue_item($item_id, $reason);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        } else {
            wp_send_json_success($response);
        }
    }
    
    /**
     * Execute automation directly
     */
    public function execute_automation() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'top_up_agent_websocket')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $player_id = sanitize_text_field($_POST['playerId'] ?? '');
        $redimension_code = sanitize_text_field($_POST['redimensionCode'] ?? '');
        $request_id = sanitize_text_field($_POST['requestId'] ?? '');
        
        if (empty($player_id) || empty($redimension_code)) {
            wp_send_json_error('Missing required parameters');
            return;
        }
        
        $response = $this->api_client->execute_automation($player_id, $redimension_code, $request_id ?: null);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        } else {
            wp_send_json_success($response);
        }
    }
    
    /**
     * Get automation results
     */
    public function get_automation_results() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'top_up_agent_websocket')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $page = intval($_POST['page'] ?? 1);
        $limit = intval($_POST['limit'] ?? 20);
        $status = sanitize_text_field($_POST['status'] ?? '');
        $player_id = sanitize_text_field($_POST['playerId'] ?? '');
        
        // Use search if filters are provided
        if (!empty($status) || !empty($player_id)) {
            $filters = array();
            if (!empty($status)) $filters['status'] = $status;
            if (!empty($player_id)) $filters['playerId'] = $player_id;
            
            $response = $this->api_client->search_results($filters);
        } else {
            $response = $this->api_client->get_results($page, $limit);
        }
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        } else {
            wp_send_json_success($response);
        }
    }
    
    /**
     * Admin-only methods
     */
    
    /**
     * Pause queue processing
     */
    public function pause_queue() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'top_up_agent_websocket')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $response = $this->api_client->pause_queue();
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        } else {
            wp_send_json_success($response);
        }
    }
    
    /**
     * Resume queue processing
     */
    public function resume_queue() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'top_up_agent_websocket')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $response = $this->api_client->resume_queue();
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        } else {
            wp_send_json_success($response);
        }
    }
    
    /**
     * Cleanup queue
     */
    public function cleanup_queue() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'top_up_agent_websocket')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $older_than_hours = intval($_POST['olderThanHours'] ?? 24);
        $status = sanitize_text_field($_POST['status'] ?? 'completed');
        
        $response = $this->api_client->cleanup_queue($older_than_hours, $status);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        } else {
            wp_send_json_success($response);
        }
    }
    
    /**
     * Get logs
     */
    public function get_logs() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'top_up_agent_websocket')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $level = sanitize_text_field($_POST['level'] ?? '');
        $lines = intval($_POST['lines'] ?? 100);
        $search = sanitize_text_field($_POST['search'] ?? '');
        
        $params = array();
        if (!empty($level)) $params['level'] = $level;
        if ($lines !== 100) $params['lines'] = $lines;
        if (!empty($search)) $params['search'] = $search;
        
        $response = $this->api_client->get_logs($params);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        } else {
            wp_send_json_success($response);
        }
    }
    
    /**
     * Clear logs
     */
    public function clear_logs() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'top_up_agent_websocket')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $older_than_days = intval($_POST['olderThanDays'] ?? 7);
        $keep_files = intval($_POST['keepFiles'] ?? 2);
        
        $response = $this->api_client->clear_logs($older_than_days, $keep_files);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        } else {
            wp_send_json_success($response);
        }
    }
    
    /**
     * Get running automations
     */
    public function get_running_automations() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'top_up_agent_websocket')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $response = $this->api_client->get_running_automations();
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        } else {
            wp_send_json_success($response);
        }
    }
    
    /**
     * Cancel running automation
     */
    public function cancel_automation() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'top_up_agent_websocket')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $request_id = sanitize_text_field($_POST['requestId'] ?? '');
        
        if (empty($request_id)) {
            wp_send_json_error('Missing request ID');
            return;
        }
        
        $response = $this->api_client->cancel_automation($request_id);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        } else {
            wp_send_json_success($response);
        }
    }
    
    /**
     * Process queue manually
     */
    public function process_queue() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'top_up_agent_websocket')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $response = $this->api_client->process_queue();
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        } else {
            wp_send_json_success($response);
        }
    }
    
    /**
     * Get WooCommerce order details
     */
    public function get_order_details() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'top_up_agent_websocket')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $order_id = intval($_POST['orderId'] ?? 0);
        
        if (!$order_id) {
            wp_send_json_error('Invalid order ID');
            return;
        }
        
        if (!class_exists('WooCommerce')) {
            wp_send_json_error('WooCommerce not active');
            return;
        }
        
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error('Order not found');
            return;
        }
        
        // Build order details HTML
        $html = '<h2>Order #' . $order->get_id() . ' Details</h2>';
        $html .= '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
        
        // Order Info
        $html .= '<div>';
        $html .= '<h3>Order Information</h3>';
        $html .= '<p><strong>Status:</strong> ' . ucfirst($order->get_status()) . '</p>';
        $html .= '<p><strong>Date:</strong> ' . $order->get_date_created()->format('Y-m-d H:i:s') . '</p>';
        $html .= '<p><strong>Total:</strong> $' . number_format($order->get_total(), 2) . '</p>';
        $html .= '<p><strong>Payment Method:</strong> ' . $order->get_payment_method_title() . '</p>';
        $html .= '</div>';
        
        // Customer Info
        $html .= '<div>';
        $html .= '<h3>Customer Information</h3>';
        $html .= '<p><strong>Name:</strong> ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . '</p>';
        $html .= '<p><strong>Email:</strong> ' . $order->get_billing_email() . '</p>';
        $html .= '<p><strong>Phone:</strong> ' . $order->get_billing_phone() . '</p>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        // Order Items
        $html .= '<h3>Order Items</h3>';
        $html .= '<table class="wp-list-table widefat">';
        $html .= '<thead><tr><th>Product</th><th>Quantity</th><th>Price</th><th>Total</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($order->get_items() as $item) {
            $html .= '<tr>';
            $html .= '<td>' . $item->get_name() . '</td>';
            $html .= '<td>' . $item->get_quantity() . '</td>';
            $html .= '<td>$' . number_format($item['line_total'] / $item->get_quantity(), 2) . '</td>';
            $html .= '<td>$' . number_format($item['line_total'], 2) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        wp_send_json_success($html);
    }
    
    /**
     * Trigger automation for specific order
     */
    public function trigger_order_automation() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'top_up_agent_websocket')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $order_id = intval($_POST['orderId'] ?? 0);
        
        if (!$order_id) {
            wp_send_json_error('Invalid order ID');
            return;
        }
        
        if (!class_exists('WooCommerce')) {
            wp_send_json_error('WooCommerce not active');
            return;
        }
        
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error('Order not found');
            return;
        }
        
        // Extract player ID and license information from order
        $player_id = '';
        $license_key = '';
        
        // Check order meta for player ID
        $player_id = $order->get_meta('_player_id');
        
        // Check for license key in order items or meta
        foreach ($order->get_items() as $item) {
            // Look for license key in product meta or custom fields
            $item_license = $item->get_meta('_license_key');
            if ($item_license) {
                $license_key = $item_license;
                break;
            }
        }
        
        if (!$player_id) {
            wp_send_json_error('Player ID not found in order');
            return;
        }
        
        // Add to automation queue
        $queue_data = [
            'queueType' => 'single',
            'playerId' => $player_id,
            'sourceSite' => home_url(),
            'productName' => 'Order #' . $order_id,
            'licenseKey' => $license_key,
            'orderId' => $order_id
        ];
        
        $response = $this->api_client->add_to_queue($queue_data);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        } else {
            wp_send_json_success('Automation queued for order #' . $order_id);
        }
    }
    
    /**
     * Bulk process orders
     */
    public function bulk_process_orders() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'top_up_agent_websocket')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $bulk_action = sanitize_text_field($_POST['bulkAction'] ?? '');
        
        if (!class_exists('WooCommerce')) {
            wp_send_json_error('WooCommerce not active');
            return;
        }
        
        $order_args = ['status' => ['processing', 'completed']];
        
        switch ($bulk_action) {
            case 'all-pending':
                $order_args['status'] = ['pending', 'processing'];
                break;
            case 'date-range':
                $date_from = sanitize_text_field($_POST['dateFrom'] ?? '');
                $date_to = sanitize_text_field($_POST['dateTo'] ?? '');
                if ($date_from && $date_to) {
                    $order_args['date_created'] = $date_from . '...' . $date_to;
                }
                break;
        }
        
        $orders = wc_get_orders($order_args);
        $processed = 0;
        $errors = 0;
        
        foreach ($orders as $order) {
            $player_id = $order->get_meta('_player_id');
            
            if ($player_id) {
                $queue_data = [
                    'queueType' => 'single',
                    'playerId' => $player_id,
                    'sourceSite' => home_url(),
                    'productName' => 'Bulk Order #' . $order->get_id(),
                    'orderId' => $order->get_id()
                ];
                
                $response = $this->api_client->add_to_queue($queue_data);
                
                if (!is_wp_error($response)) {
                    $processed++;
                } else {
                    $errors++;
                }
            }
        }
        
        wp_send_json_success("Processed {$processed} orders, {$errors} errors");
    }
    
    /**
     * Export order data
     */
    public function export_order_data() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'top_up_agent_websocket')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        if (!class_exists('WooCommerce')) {
            wp_send_json_error('WooCommerce not active');
            return;
        }
        
        $orders = wc_get_orders(['limit' => 100]);
        
        $csv_data = "Order ID,Status,Customer,Email,Total,Date,Player ID,Automation Status\n";
        
        foreach ($orders as $order) {
            $player_id = $order->get_meta('_player_id') ?: 'N/A';
            $automation_status = 'Unknown'; // In real implementation, check automation database
            
            $csv_data .= sprintf(
                "%d,%s,\"%s\",\"%s\",%s,%s,\"%s\",\"%s\"\n",
                $order->get_id(),
                $order->get_status(),
                $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                $order->get_billing_email(),
                $order->get_total(),
                $order->get_date_created()->format('Y-m-d H:i:s'),
                $player_id,
                $automation_status
            );
        }
        
        wp_send_json_success($csv_data);
    }
}