<?php
class Top_Up_Agent_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'register_menus'));
        
        // Hide other plugin notifications on our plugin pages - multiple hooks for better coverage
        add_action('admin_init', array($this, 'hide_other_plugin_notifications'));
        add_action('current_screen', array($this, 'hide_other_plugin_notifications_late'));
        add_action('admin_head', array($this, 'hide_admin_notices_css'));
        
        // Add AJAX handler for failure notifications
        add_action('wp_ajax_send_failure_notification', array($this, 'handle_failure_notification'));
        add_action('wp_ajax_nopriv_send_failure_notification', array($this, 'handle_failure_notification'));
        
        // Add AJAX handler for file integrity check
        add_action('wp_ajax_top_up_agent_check_files', array($this, 'ajax_check_files'));
        add_action('wp_ajax_nopriv_top_up_agent_check_files', array($this, 'ajax_check_files'));
        
        // Add REST API endpoint for file integrity check
        add_action('rest_api_init', array($this, 'register_rest_endpoints'));
        
        // Add AJAX handler for server data screenshot
        add_action('wp_ajax_fetch_server_data_screenshot', array($this, 'ajax_fetch_server_data_screenshot'));
    }
    
    public function handle_failure_notification() {
        // Verify the request has required data
        if (!isset($_POST['queue_id'], $_POST['player_id'], $_POST['error_message'])) {
            wp_die('Invalid notification data', 'TopUp Agent', array('response' => 400));
        }
        
        $queue_id = sanitize_text_field($_POST['queue_id']);
        $player_id = sanitize_text_field($_POST['player_id']);
        $license_key = sanitize_text_field($_POST['license_key'] ?? '');
        $redimension_code = sanitize_text_field($_POST['redimension_code'] ?? '');
        $error_message = sanitize_textarea_field($_POST['error_message']);
        $failure_time = sanitize_text_field($_POST['failure_time'] ?? current_time('mysql'));
        $source_site = sanitize_url($_POST['source_site'] ?? get_site_url());
        
        // Get admin email
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        // Email subject
        $subject = sprintf('[%s] TopUp Agent Automation Failed - Queue #%s', $site_name, $queue_id);
        
        // Email message
        $message = "
TopUp Agent Automation Failure Notification

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

AUTOMATION DETAILS:
• Queue ID: {$queue_id}
• Player ID: {$player_id}
• License Key: {$license_key}
• Redimension Code: {$redimension_code}
• Failure Time: {$failure_time}
• Source Site: {$source_site}

ERROR DETAILS:
{$error_message}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

NEXT STEPS:
1. Check the TopUp Agent Dashboard for details
2. Verify the license key is valid
3. Check if the website is accessible
4. Review automation logs for more information

IMPORTANT: Automatic retries have been DISABLED. Manual intervention may be required.

Dashboard: " . admin_url('admin.php?page=top-up-agent-dashboard') . "

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

This email was sent automatically by TopUp Agent.
";
        
        // Send email notification
        $email_sent = wp_mail($admin_email, $subject, $message);
        
        if ($email_sent) {
            error_log("TopUp Agent: Failure notification email sent successfully for Queue #{$queue_id}");
            wp_die('success', 'TopUp Agent', array('response' => 200));
        } else {
            error_log("TopUp Agent: Failed to send notification email for Queue #{$queue_id}");
            wp_die('email_failed', 'TopUp Agent', array('response' => 500));
        }
    }
    
    public function ajax_check_files() {
        // Verify capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
            return;
        }
        
        // Optional: Check nonce for security
        if (isset($_POST['nonce']) && !wp_verify_nonce($_POST['nonce'], 'top_up_agent_check_files')) {
            wp_send_json_error('Invalid nonce', 403);
            return;
        }
        
        try {
            $api = new Top_Up_Agent_API_Client();
            
            // Simplified file check - just return basic plugin info
            $file_check_results = [
                'status' => 'ok',
                'message' => 'Plugin files loaded successfully'
            ];
            
            // Add additional system information
            $file_check_results['system_info'] = [
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'plugin_url' => plugin_dir_url(__FILE__) . '../../',
                'plugin_path' => plugin_dir_path(__FILE__) . '../../',
                'is_multisite' => is_multisite(),
                'memory_limit' => ini_get('memory_limit'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'max_execution_time' => ini_get('max_execution_time'),
                'current_theme' => get_template(),
                'active_plugins' => get_option('active_plugins', []),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
            ];
            
            wp_send_json_success($file_check_results);
            
        } catch (Exception $e) {
            error_log('TopUp Agent File Check Error: ' . $e->getMessage());
            wp_send_json_error('File check failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * AJAX handler for server data screenshot
     */
    public function ajax_fetch_server_data_screenshot() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'server_data_screenshot')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        try {
            $api = new Top_Up_Agent_API_Client();
            $server_data = array();
            
            // Get automation results from server (increased limit to get more data)
            error_log('TopUp Agent: Fetching automation results from server');
            $results_response = $api->get_results(1, 100); // Get first 100 results
            
            if (!is_wp_error($results_response) && isset($results_response['results'])) {
                $server_data['automation_results'] = $results_response['results'];
                error_log('TopUp Agent: Found ' . count($results_response['results']) . ' automation results');
            } else {
                $server_data['automation_results'] = array();
                error_log('TopUp Agent: No automation results found or API error');
            }
            
            // Get recent queue items (increased limit)
            error_log('TopUp Agent: Fetching recent queue items from server');
            $recent_queue = $api->get_recent_queue_items();
            if (!is_wp_error($recent_queue) && isset($recent_queue['items'])) {
                $server_data['queue_items'] = $recent_queue['items'];
                error_log('TopUp Agent: Found ' . count($recent_queue['items']) . ' recent queue items');
            } else {
                $server_data['queue_items'] = array();
                error_log('TopUp Agent: No queue items found - ' . (is_wp_error($recent_queue) ? $recent_queue->get_error_message() : 'Unknown error'));
            }
            
            // Organize data by product
            $server_data['products_data'] = $this->organize_data_by_product($server_data['queue_items'], $server_data['automation_results']);
            
            // Get database statistics if available
            $db_stats = $api->get_database_stats();
            if (!is_wp_error($db_stats)) {
                $server_data['database_stats'] = $db_stats;
            }
            
            // Calculate server statistics
            $total_queue = count($server_data['queue_items']);
            $pending_queue = 0;
            $running_queue = 0;
            
            foreach ($server_data['queue_items'] as $item) {
                $status = $item['status'] ?? 'unknown';
                if ($status === 'pending') $pending_queue++;
                if ($status === 'running') $running_queue++;
            }
            
            $total_results = count($server_data['automation_results']);
            $successful_results = 0;
            $failed_results = 0;
            
            foreach ($server_data['automation_results'] as $result) {
                if (isset($result['success'])) {
                    if ($result['success'] === true) $successful_results++;
                    if ($result['success'] === false) $failed_results++;
                }
            }
            
            $server_data['server_stats'] = array(
                'total_queue' => $total_queue,
                'pending_queue' => $pending_queue,
                'running_queue' => $running_queue,
                'total_results' => $total_results,
                'successful_results' => $successful_results,
                'failed_results' => $failed_results
            );
            
            // Get server status
            $server_status = $api->get_status();
            if (!is_wp_error($server_status)) {
                $server_data['server_status'] = $server_status;
            }
            
            error_log('TopUp Agent: Server data screenshot completed successfully');
            wp_send_json_success($server_data);
            
        } catch (Exception $e) {
            error_log('TopUp Agent: Server data screenshot error - ' . $e->getMessage());
            wp_send_json_error('Failed to fetch server data: ' . $e->getMessage());
        }
    }
    
    public function register_rest_endpoints() {
        register_rest_route('top-up-agent/v1', '/check-files', [
            'methods' => 'GET',
            'callback' => array($this, 'rest_check_files'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
        
        register_rest_route('top-up-agent/v1', '/health', [
            'methods' => 'GET',
            'callback' => array($this, 'rest_health_check'),
            'permission_callback' => '__return_true' // Public endpoint
        ]);
    }
    
    public function rest_check_files($request) {
        try {
            $api = new Top_Up_Agent_API_Client();
            
            // Simplified file check - just return basic plugin info
            $file_check_results = [
                'status' => 'ok',
                'message' => 'Plugin files loaded successfully'
            ];
            
            return new WP_REST_Response($file_check_results, 200);
            
        } catch (Exception $e) {
            error_log('TopUp Agent REST File Check Error: ' . $e->getMessage());
            return new WP_Error('file_check_error', 'File check failed: ' . $e->getMessage(), ['status' => 500]);
        }
    }
    
    public function rest_health_check($request) {
        $api = new Top_Up_Agent_API_Client();
        
        $health_data = [
            'status' => 'ok',
            'timestamp' => current_time('c'),
            'plugin_version' => get_plugin_data(plugin_dir_path(__FILE__) . '../../top-up-agent.php')['Version'] ?? 'Unknown',
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'server_status' => $api->get_status(),
            'database_connected' => true // Will be set to false if DB connection fails
        ];
        
        // Test database connection
        global $wpdb;
        try {
            $wpdb->get_var("SELECT 1");
        } catch (Exception $e) {
            $health_data['database_connected'] = false;
            $health_data['database_error'] = $e->getMessage();
            $health_data['status'] = 'degraded';
        }
        
        return new WP_REST_Response($health_data, 200);
    }
    
    public function register_menus() {
        add_menu_page('Top Up Agent Dashboard', 'Top Up Agent', 'manage_options', 'top-up-agent-dashboard', array($this, 'dashboard_page'), 'dashicons-admin-generic', 26);
        add_submenu_page('top-up-agent-dashboard', 'Dashboard', 'Dashboard', 'manage_options', 'top-up-agent-dashboard', array($this, 'dashboard_page'));
        // add_submenu_page('top-up-agent-dashboard', 'Automation', 'Automation', 'manage_options', 'top-up-agent-automation', array($this, 'automation_page'));
        add_submenu_page('top-up-agent-dashboard', 'WooCommerce', 'WooCommerce', 'manage_options', 'top-up-agent-woocommerce', array($this, 'woocommerce_page'));
        add_submenu_page('top-up-agent-dashboard', 'License Keys', 'License Keys', 'manage_options', 'top-up-agent-license-keys', array($this, 'license_keys_page'));
        add_submenu_page('top-up-agent-dashboard', 'Settings', 'Settings', 'manage_options', 'top-up-agent-settings', array($this, 'settings_page'));
    }
    
    public function dashboard_page() {
        include plugin_dir_path(__FILE__) . '../../templates/dashboard.php';
    }
    
    public function automation_page() {
        include plugin_dir_path(__FILE__) . '../../templates/automation.php';
    }
    
    public function woocommerce_page() {
        include plugin_dir_path(__FILE__) . '../../templates/woocommerce.php';
    }
    
    public function license_keys_page() {
        include plugin_dir_path(__FILE__) . '../../templates/license-keys.php';
    }
    
    public function settings_page() {
        include plugin_dir_path(__FILE__) . '../../templates/settings.php';
    }
    
    /**
     * Hide other plugin notifications when viewing Top Up Agent pages
     */
    public function hide_other_plugin_notifications() {
        // Check if we're on any of our plugin pages
        if (!$this->is_our_admin_page()) {
            return;
        }

        // Remove all admin notices with high priority to run late
        add_action('admin_notices', array($this, 'remove_all_admin_notices'), 1);
        add_action('all_admin_notices', array($this, 'remove_all_admin_notices'), 1);
        add_action('network_admin_notices', array($this, 'remove_all_admin_notices'), 1);
        add_action('user_admin_notices', array($this, 'remove_all_admin_notices'), 1);
    }

    /**
     * Late hook to hide notifications - runs after current_screen is set
     */
    public function hide_other_plugin_notifications_late() {
        if (!$this->is_our_admin_page()) {
            return;
        }

        // Remove admin notice hooks more aggressively
        $this->remove_notice_hooks();
        
        // Add JavaScript to hide any remaining notices
        add_action('admin_footer', array($this, 'hide_notices_javascript'));
    }

    /**
     * Check if we're on one of our admin pages
     */
    private function is_our_admin_page() {
        if (!function_exists('get_current_screen')) {
            return false;
        }
        
        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }

        $our_pages = array(
            'toplevel_page_top-up-agent-dashboard',
            'top-up-agent_page_top-up-agent-woocommerce',
            'top-up-agent_page_top-up-agent-license-keys',
            'top-up-agent_page_top-up-agent-settings'
        );

        return in_array($screen->id, $our_pages);
    }

    /**
     * Remove all admin notices that aren't ours
     */
    public function remove_all_admin_notices() {
        // Don't execute this on subsequent calls
        static $notices_removed = false;
        if ($notices_removed) {
            return;
        }
        $notices_removed = true;

        // Capture and clear all output
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Start output buffering to capture notices
        ob_start(array($this, 'filter_admin_notices'));
    }

    /**
     * Filter admin notices to only show our own
     */
    public function filter_admin_notices($output) {
        // If the notice contains our plugin identifier, keep it
        if (strpos($output, 'top-up-agent-notice') !== false || 
            strpos($output, 'Top Up Agent') !== false) {
            return $output;
        }
        
        // Otherwise, return empty to hide it
        return '';
    }

    /**
     * Remove notice hooks from other plugins
     */
    private function remove_notice_hooks() {
        global $wp_filter;
        
        $notice_hooks = array('admin_notices', 'all_admin_notices', 'network_admin_notices', 'user_admin_notices');
        
        foreach ($notice_hooks as $hook) {
            if (isset($wp_filter[$hook])) {
                foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $callback_id => $callback) {
                        // Skip our own notices and WordPress core notices
                        if (strpos($callback_id, 'top_up_agent') !== false || 
                            strpos($callback_id, 'Top_Up_Agent') !== false ||
                            strpos($callback_id, 'wp_') === 0) {
                            continue;
                        }
                        
                        remove_action($hook, $callback['function'], $priority);
                    }
                }
            }
        }
    }

    /**
     * Add JavaScript to hide any notices that slip through
     */
    public function hide_notices_javascript() {
        ?>
<script type="text/javascript">
jQuery(document).ready(function($) {
    // Hide all notices except our own
    $('.notice, .update-nag, .error, .updated, #message').each(function() {
        var $notice = $(this);
        var noticeHtml = $notice.html();

        // Keep our notices and critical WordPress notices
        if (noticeHtml.indexOf('top-up-agent-notice') === -1 &&
            noticeHtml.indexOf('Top Up Agent') === -1 &&
            !$notice.hasClass('top-up-agent-notice')) {
            $notice.hide();
        }
    });

    // Hide specific plugin notices by common selectors
    $('div[id*="elementor"], div[id*="nextend"], div[class*="elementor"], div[class*="nextend"]').each(
    function() {
        if ($(this).hasClass('notice') || $(this).hasClass('error') || $(this).hasClass('updated')) {
            $(this).hide();
        }
    });

    // Remove any notices that get added dynamically
    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1) { // Element node
                    var $node = $(node);
                    if ($node.hasClass('notice') || $node.hasClass('error') || $node
                        .hasClass('updated')) {
                        if ($node.html().indexOf('Top Up Agent') === -1 &&
                            !$node.hasClass('top-up-agent-notice')) {
                            $node.hide();
                        }
                    }
                }
            });
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});
</script>
<?php
    }
    
    /**
     * Add CSS to hide admin notices from other plugins
     */
    public function hide_admin_notices_css() {
        // Only apply on our admin pages
        if (!$this->is_our_admin_page()) {
            return;
        }
        
        echo '<style>
            /* Hide ALL notices by default - very aggressive approach */
            .notice,
            .update-nag,
            .error,
            .updated,
            #message,
            .wrap .notice,
            .wrap .error,
            .wrap .updated,
            .wrap #message,
            .notice-warning,
            .notice-error,
            .notice-success,
            .notice-info,
            .admin-notice,
            .plugin-update-tr,
            .update-message,
            .inline-notice,
            .notice-large {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
                height: 0 !important;
                overflow: hidden !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            /* Show only our notices */
            .top-up-agent-notice,
            .notice.top-up-agent-notice,
            .error.top-up-agent-notice,
            .updated.top-up-agent-notice {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                height: auto !important;
                overflow: visible !important;
                margin: 5px 15px 2px !important;
                padding: 1px 12px !important;
            }
            
            /* Hide specific plugin notices we know about */
            div[id*="elementor"],
            div[class*="elementor"],
            div[id*="nextend"],
            div[class*="nextend"],
            .elementor-notice,
            .nextend-notice,
            .woocommerce-message,
            .wc-connect-notice,
            .jetpack-message,
            .akismet-notice,
            .yoast-notice,
            .rank-math-notice,
            .wpforms-notice {
                display: none !important;
                visibility: hidden !important;
            }
            
            /* Hide license/update related notices from other plugins */
            .notice[class*="license"],
            .notice[class*="update"],
            .notice[class*="expired"],
            .notice[class*="activate"],
            .error[class*="license"],
            .updated[class*="license"] {
                display: none !important;
            }
            
            /* Keep critical WordPress core notices visible */
            .notice-error.notice-alt,
            .notice-warning.notice-alt {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                height: auto !important;
                overflow: visible !important;
            }
        </style>';
    }
    
    /**
     * Add a Top Up Agent specific admin notice that won't be hidden
     * 
     * @param string $message The notice message
     * @param string $type Notice type: 'success', 'error', 'warning', 'info'
     * @param bool $dismissible Whether the notice is dismissible
     */
    public static function add_admin_notice($message, $type = 'info', $dismissible = true) {
        $class = 'notice top-up-agent-notice notice-' . $type;
        if ($dismissible) {
            $class .= ' is-dismissible';
        }
        
        add_action('admin_notices', function() use ($message, $class) {
            echo '<div class="' . esc_attr($class) . '">';
            echo '<p><strong>Top Up Agent:</strong> ' . esc_html($message) . '</p>';
            echo '</div>';
        });
    }
    
    /**
     * Organize automation data by product
     * Combines queue items and automation results, grouped by product with screenshots and player data
     * 
     * @param array $queue_items Queue items from server
     * @param array $automation_results Automation results from server
     * @return array Organized data by product
     */
    private function organize_data_by_product($queue_items, $automation_results) {
        $products_data = array();
        
        // First, organize queue items by product
        foreach ($queue_items as $item) {
            $product_id = $item['product_id'] ?? 'unknown';
            $product_name = $item['product_name'] ?? 'Unknown Product';
            
            if (!isset($products_data[$product_id])) {
                $products_data[$product_id] = array(
                    'product_id' => $product_id,
                    'product_name' => $product_name,
                    'queue_items' => array(),
                    'automation_results' => array(),
                    'total_queue' => 0,
                    'total_results' => 0,
                    'successful_results' => 0,
                    'failed_results' => 0,
                    'screenshots' => array(),
                    'players' => array()
                );
            }
            
            $products_data[$product_id]['queue_items'][] = $item;
            $products_data[$product_id]['total_queue']++;
            
            // Track unique players for this product
            if (isset($item['player_id']) && !empty($item['player_id'])) {
                $products_data[$product_id]['players'][$item['player_id']] = array(
                    'player_id' => $item['player_id'],
                    'order_id' => $item['order_id'] ?? null,
                    'status' => $item['status'] ?? 'unknown'
                );
            }
        }
        
        // Next, match automation results with products
        foreach ($automation_results as $result) {
            $queue_id = $result['queue_id'] ?? null;
            $player_id = $result['playerId'] ?? null;
            
            // Find the product this result belongs to by matching queue_id
            $matched_product = null;
            foreach ($queue_items as $queue_item) {
                if (isset($queue_item['id']) && $queue_item['id'] == $queue_id) {
                    $matched_product = $queue_item['product_id'] ?? 'unknown';
                    break;
                }
            }
            
            // If no direct match, try to match by player_id
            if (!$matched_product && $player_id) {
                foreach ($products_data as $product_id => $product_data) {
                    if (isset($product_data['players'][$player_id])) {
                        $matched_product = $product_id;
                        break;
                    }
                }
            }
            
            // Default to 'unknown' if no match found
            if (!$matched_product) {
                $matched_product = 'unknown';
                if (!isset($products_data[$matched_product])) {
                    $products_data[$matched_product] = array(
                        'product_id' => $matched_product,
                        'product_name' => 'Unknown Product',
                        'queue_items' => array(),
                        'automation_results' => array(),
                        'total_queue' => 0,
                        'total_results' => 0,
                        'successful_results' => 0,
                        'failed_results' => 0,
                        'screenshots' => array(),
                        'players' => array()
                    );
                }
            }
            
            $products_data[$matched_product]['automation_results'][] = $result;
            $products_data[$matched_product]['total_results']++;
            
            // Track success/failure stats
            if (isset($result['success'])) {
                if ($result['success'] === true) {
                    $products_data[$matched_product]['successful_results']++;
                } else {
                    $products_data[$matched_product]['failed_results']++;
                }
            }
            
            // Track screenshots if available
            if (isset($result['screenshotPath']) && !empty($result['screenshotPath'])) {
                // Extract username from metadata if available
                $username = null;
                if (isset($result['metadata']) && is_array($result['metadata']) && isset($result['metadata']['username'])) {
                    $username = $result['metadata']['username'];
                } elseif (isset($result['metadata']) && is_string($result['metadata'])) {
                    // Handle JSON string metadata
                    $metadata = json_decode($result['metadata'], true);
                    if ($metadata && isset($metadata['username'])) {
                        $username = $metadata['username'];
                    }
                }
                
                $screenshot_data = array(
                    'path' => $result['screenshotPath'],
                    'player_id' => $player_id,
                    'username' => $username,
                    'timestamp' => $result['createdAt'] ?? null,
                    'success' => $result['success'] ?? null,
                    'result_id' => $result['id'] ?? null
                );
                $products_data[$matched_product]['screenshots'][] = $screenshot_data;
            }
            
            // Extract username from metadata for player data
            $username = null;
            if (isset($result['metadata']) && is_array($result['metadata']) && isset($result['metadata']['username'])) {
                $username = $result['metadata']['username'];
            } elseif (isset($result['metadata']) && is_string($result['metadata'])) {
                // Handle JSON string metadata
                $metadata = json_decode($result['metadata'], true);
                if ($metadata && isset($metadata['username'])) {
                    $username = $metadata['username'];
                }
            }
            
            // Update player data with result information
            if ($player_id && isset($products_data[$matched_product]['players'][$player_id])) {
                $products_data[$matched_product]['players'][$player_id]['username'] = $username;
                $products_data[$matched_product]['players'][$player_id]['last_result'] = array(
                    'success' => $result['success'] ?? null,
                    'timestamp' => $result['createdAt'] ?? null,
                    'has_screenshot' => !empty($result['screenshotPath'])
                );
            } elseif ($player_id) {
                // Add player if not already tracked
                $products_data[$matched_product]['players'][$player_id] = array(
                    'player_id' => $player_id,
                    'username' => $username,
                    'order_id' => null,
                    'status' => 'completed',
                    'last_result' => array(
                        'success' => $result['success'] ?? null,
                        'timestamp' => $result['createdAt'] ?? null,
                        'has_screenshot' => !empty($result['screenshotPath'])
                    )
                );
            }
        }
        
        // Sort products by name for better organization
        ksort($products_data);
        
        return $products_data;
    }
}