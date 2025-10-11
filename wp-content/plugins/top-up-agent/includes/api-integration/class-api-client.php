<?php

/**
 * API Client for Top Up Agent
 * 
 * Handles communication with the automation server API
 */
class Top_Up_Agent_API_Client {
    
    private $server_url;
    private $api_key;
    
    public function __construct() {
        $this->server_url = rtrim(get_option('top_up_agent_server_url', 'https://server.uidtopupbd.com'), '/');
        $this->api_key = get_option('top_up_agent_api_key', '');
    }
    
    /**
     * Make HTTP request to API
     */
    private function make_request($endpoint, $method = 'GET', $data = null) {
        $url = $this->server_url . $endpoint;
        
        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ];
        
        if ($this->api_key) {
            $args['headers']['x-api-key'] = $this->api_key;
        }
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code >= 400) {
            return new WP_Error('api_error', 'API request failed with code ' . $code . ': ' . $body);
        }
        
        $decoded = json_decode($body, true);
        return $decoded !== null ? $decoded : $body;
    }
    
    /**
     * Health check
     */
    public function health_check() {
        return $this->make_request('/health');
    }
    
    /**
     * Get server status (alias for health_check for compatibility)
     */
    public function get_status() {
        return $this->health_check();
    }
    
    /**
     * Get queue status
     */
    public function get_queue_status($queue_id = null) {
        if ($queue_id) {
            return $this->make_request('/api/queue/status/' . $queue_id);
        }
        return $this->make_request('/api/queue/status');
    }
    
    /**
     * Add item to queue
     */
    public function add_to_queue($data) {
        return $this->make_request('/api/queue/add', 'POST', $data);
    }
    
    /**
     * Add group automation to queue
     */
    public function add_group_to_queue($data) {
        return $this->make_request('/api/queue/add-group', 'POST', $data);
    }
    
    /**
     * Get pending queue items
     */
    public function get_pending_queue_items() {
        return $this->make_request('/api/queue/pending');
    }
    
    /**
     * Get recent queue items
     */
    public function get_recent_queue_items() {
        return $this->make_request('/api/queue/recent');
    }
    
    /**
     * Cancel queue item
     */
    public function cancel_queue_item($item_id, $reason = '') {
        return $this->make_request('/api/queue/cancel/' . $item_id, 'DELETE', ['reason' => $reason]);
    }
    
    /**
     * Process queue manually
     */
    public function process_queue() {
        return $this->make_request('/api/queue/process', 'POST');
    }
    
    /**
     * Pause queue
     */
    public function pause_queue() {
        return $this->make_request('/api/queue/pause', 'POST');
    }
    
    /**
     * Resume queue
     */
    public function resume_queue() {
        return $this->make_request('/api/queue/resume', 'POST');
    }
    
    /**
     * Execute automation directly
     */
    public function execute_automation($player_id, $redimension_code, $request_id = null) {
        $data = [
            'playerId' => $player_id,
            'redimensionCode' => $redimension_code
        ];
        
        if ($request_id) {
            $data['requestId'] = $request_id;
        }
        
        return $this->make_request('/api/automation/execute', 'POST', $data);
    }
    
    /**
     * Get running automations
     */
    public function get_running_automations() {
        return $this->make_request('/api/automation/running');
    }
    
    /**
     * Cancel running automation
     */
    public function cancel_automation($request_id) {
        return $this->make_request('/api/automation/cancel/' . $request_id, 'DELETE');
    }
    
    /**
     * Get automation results
     */
    public function get_results($page = 1, $limit = 20) {
        return $this->make_request('/api/results?page=' . $page . '&limit=' . $limit);
    }
    
    /**
     * Get automation result by ID
     */
    public function get_result_by_id($result_id) {
        return $this->make_request('/api/results/' . $result_id);
    }
    
    /**
     * Get database statistics
     */
    public function get_database_stats() {
        return $this->make_request('/api/stats/database');
    }
    
    /**
     * Get history statistics
     */
    public function get_history_stats() {
        return $this->make_request('/api/stats/history');
    }
    
    /**
     * Get logs
     */
    public function get_logs($lines = 100) {
        return $this->make_request('/api/logs?lines=' . $lines);
    }
    
    /**
     * Clear logs
     */
    public function clear_logs() {
        return $this->make_request('/api/logs/clear', 'DELETE');
    }
    
    /**
     * Cleanup queue with custom parameters
     */
    public function cleanup_queue($hours = 24, $status = 'completed') {
        $data = [
            'confirmCleanup' => true,
            'olderThanHours' => $hours,
            'status' => $status
        ];
        return $this->make_request('/api/queue/cleanup', 'DELETE', $data);
    }
    
    /**
     * Get server configuration
     */
    public function get_config() {
        return $this->make_request('/api/config');
    }
    
    /**
     * Update server configuration
     */
    public function update_config($config) {
        return $this->make_request('/api/config', 'PUT', $config);
    }
    
    /**
     * Test automation with sample data
     */
    public function test_automation($data) {
        return $this->make_request('/api/automation/test', 'POST', $data);
    }
    
    /**
     * Get system metrics
     */
    public function get_metrics() {
        return $this->make_request('/api/metrics');
    }
    
    /**
     * Get socket information
     */
    public function get_socket_info() {
        return $this->make_request('/api/socket/info');
    }
    
    /**
     * Test connection to server
     */
    public function test_connection() {
        return $this->health_check();
    }
    
    /**
     * Search automation results with filters
     */
    public function search_results($filters = []) {
        $query_params = http_build_query($filters);
        return $this->make_request('/api/results/search?' . $query_params);
    }
    
    /**
     * Get running queue items
     */
    public function get_running_queue_items() {
        return $this->make_request('/api/queue/running');
    }
    
    /**
     * Clear database (with confirmation)
     */
    public function clear_database($confirm = false) {
        $data = ['confirmDelete' => $confirm];
        return $this->make_request('/api/database/clear', 'DELETE', $data);
    }
    
    /**
     * Get site-specific automation history
     */
    public function get_site_history($source_site = null, $page = 1, $limit = 20) {
        $source_site = $source_site ?: get_site_url();
        $query_params = http_build_query([
            'sourceSite' => $source_site,
            'page' => $page,
            'limit' => $limit
        ]);
        return $this->make_request('/api/history?' . $query_params);
    }
    
    /**
     * Delete site-specific automation history
     */
    public function delete_site_history($source_site = null, $confirm = false) {
        $source_site = $source_site ?: get_site_url();
        
        // Use olderThan with a future date to delete all records for this site
        $future_date = date('Y-m-d\TH:i:s.v\Z', strtotime('+1 year'));
        
        $data = [
            'confirmDelete' => $confirm,
            'sourceSite' => $source_site,
            'olderThan' => $future_date
        ];
        return $this->make_request('/api/history', 'DELETE', $data);
    }
    
    /**
     * Get site-specific queue items
     */
    public function get_site_queue_items($source_site = null) {
        $source_site = $source_site ?: get_site_url();
        $query_params = http_build_query(['sourceSite' => $source_site]);
        return $this->make_request('/api/queue/recent?' . $query_params);
    }
    
    /**
     * Check if server supports site-specific operations
     */
    public function check_site_support($source_site = null) {
        $source_site = $source_site ?: get_site_url();
        $query_params = http_build_query([
            'sourceSite' => $source_site,
            'limit' => 1
        ]);
        return $this->make_request('/api/history?' . $query_params);
    }
    
    /**
     * Clear site-specific queue items
     */
    public function clear_site_queue($source_site = null, $confirm = false) {
        $source_site = $source_site ?: get_site_url();
        $data = [
            'confirmCleanup' => $confirm,
            'sourceSite' => $source_site,
            'status' => 'all' // Clear all status types for this site
        ];
        return $this->make_request('/api/queue/cleanup', 'DELETE', $data);
    }
    
    /**
     * Clear all screenshot files
     */
    public function clear_screenshots() {
        return $this->make_request('/api/database/screenshots', 'DELETE');
    }
    
    /**
     * Check if a screenshot is accessible
     */
    public function check_screenshot($filename) {
        $url = $this->server_url . '/api/screenshots/' . $filename;
        
        $args = [
            'method' => 'HEAD', // Use HEAD to check if file exists without downloading
            'timeout' => 10,
            'headers' => [
                'Accept' => 'image/*'
            ]
        ];
        
        if ($this->api_key) {
            $args['headers']['x-api-key'] = $this->api_key;
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        return $code === 200;
    }
    
    /**
     * Get screenshot URL
     */
    public function get_screenshot_url($filename) {
        return $this->server_url . '/api/screenshots/' . $filename;
    }
}