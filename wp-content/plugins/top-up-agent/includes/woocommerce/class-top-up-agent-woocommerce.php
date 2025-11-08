<?php
/**
 * Advanced WooCommerce Automation Integration
 * Handles single and group license automation with full API integration
 */
class Top_Up_Agent_WooCommerce_Integration {
    private $api;
    private $license_manager;
    
    // Custom order statuses for automation
    const STATUS_AUTOMATION_PENDING = 'automation-pending';
    const STATUS_AUTOMATION_PROCESSING = 'automation-processing';
    const STATUS_AUTOMATION_FAILED = 'automation-failed';
    const STATUS_AUTOMATION_COMPLETED = 'automation-completed';
    
    public function __construct() {
        require_once plugin_dir_path(__FILE__) . '../automation/class-top-up-agent-player-id-detector.php';
        require_once plugin_dir_path(__FILE__) . '../automation/class-top-up-agent-product-eligibility-checker.php';
        require_once plugin_dir_path(__FILE__) . '../license-management/class-top-up-agent-license-key-manager.php';
        require_once plugin_dir_path(__FILE__) . 'class-top-up-agent-woocommerce-template-helper.php';
        
        // Use new API integration if available, fallback to old API
        // Use the new API client system
        require_once plugin_dir_path(__FILE__) . '../api-integration/class-api-client.php';
        $this->api = new Top_Up_Agent_API_Client();
        error_log("Top Up Agent: Using new API integration system");
        
        $this->license_manager = new Top_Up_Agent_License_Key_Manager();
        
        $this->init_hooks();
        $this->register_custom_order_statuses();
        
        error_log("Top Up Agent: Advanced WooCommerce Integration initialized");
    }
    
    private function init_hooks() {
        // Cart restrictions - single product, quantity 1 only
        add_filter('woocommerce_add_to_cart_validation', array($this, 'restrict_cart_to_single_product'), 10, 3);
        add_filter('woocommerce_quantity_input_args', array($this, 'force_quantity_one'), 10, 2);
        add_filter('woocommerce_cart_item_quantity', array($this, 'disable_quantity_input'), 10, 3);
        add_filter('woocommerce_update_cart_validation', array($this, 'prevent_quantity_update'), 10, 4);
        add_action('woocommerce_before_calculate_totals', array($this, 'enforce_quantity_one'), 10, 1);
        add_action('woocommerce_add_to_cart', array($this, 'enforce_single_product_in_cart'), 10, 6);
        add_action('woocommerce_before_cart', array($this, 'show_cart_restriction_notice'));
        
        // Hook into order status changes - ONLY processing status triggers automation
        add_action('woocommerce_order_status_processing', array($this, 'handle_processing_order'), 10, 1);
        
        // Also hook into general status changes for debugging
        add_action('woocommerce_order_status_changed', array($this, 'debug_order_status_change'), 5, 4);
        
        // Monitor automation status updates - REMOVED wp_loaded to prevent rate limiting
        // Status updates are now handled by WebSocket and occasional cron job only
        
        // Add custom columns to orders table
        add_filter('manage_edit-shop_order_columns', array($this, 'add_automation_column'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'display_automation_status'), 10, 2);
        
        // Add automation status filters to orders list
        add_action('restrict_manage_posts', array($this, 'add_automation_status_filter'));
        add_filter('parse_query', array($this, 'filter_orders_by_automation_status'));
        
        // Add automation actions to order page
        add_action('woocommerce_order_actions', array($this, 'add_order_actions'));
        add_action('woocommerce_order_action_retry_automation', array($this, 'retry_automation'));
        add_action('woocommerce_order_action_cancel_automation', array($this, 'cancel_automation'));
        add_action('woocommerce_order_action_complete_automation_order', array($this, 'manual_complete_automation_order'));
        
        // Add automation details to order admin page
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'add_automation_order_details'));
        add_action('add_meta_boxes', array($this, 'add_automation_admin_controls'));
        
        // Add automation completion indicator and auto-complete orders
        add_action('woocommerce_admin_order_actions_end', array($this, 'add_automation_completion_indicator'));
        add_action('woocommerce_order_status_changed', array($this, 'auto_complete_automation_orders'), 10, 4);
        add_action('top_up_agent_automation_completed', array($this, 'handle_automation_completion'), 10, 2);
        
        // AJAX handlers for automation controls
        add_action('wp_ajax_top_up_agent_trigger_automation', array($this, 'ajax_trigger_automation'));
        add_action('wp_ajax_top_up_agent_retry_automation', array($this, 'ajax_retry_automation'));
        add_action('wp_ajax_top_up_agent_cancel_automation', array($this, 'ajax_cancel_automation'));
        
        // AJAX handler for checking order queue status
        add_action('wp_ajax_check_order_queue_status', array($this, 'ajax_check_order_queue_status'));
        add_action('wp_ajax_nopriv_check_order_queue_status', array($this, 'ajax_check_order_queue_status'));
        
        // Register delayed order completion handler
        add_action('top_up_agent_delayed_order_completion', array($this, 'delayed_order_completion'));
        
        // AJAX handler for triggering automation from WooCommerce page
        add_action('wp_ajax_trigger_order_automation', array($this, 'ajax_trigger_order_automation'));
        
        // AJAX handler for testing connectivity (debug)
        add_action('wp_ajax_test_ajax_connection', array($this, 'ajax_test_connection'));
        
        // AJAX handler for enabling product automation (admin)
        add_action('wp_ajax_enable_product_automation', array($this, 'ajax_enable_product_automation'));
        
        // AJAX handlers for automation status updates
        add_action('wp_ajax_update_automation_status', array($this, 'ajax_update_automation_status'));
        add_action('wp_ajax_nopriv_update_automation_status', array($this, 'ajax_update_automation_status'));
        
        // WebSocket real-time status updates
        add_action('wp_ajax_websocket_automation_update', array($this, 'ajax_websocket_automation_update'));
        add_action('wp_ajax_nopriv_websocket_automation_update', array($this, 'ajax_websocket_automation_update'));
        
        // WebSocket support for real-time automation updates
        add_action('wp_enqueue_scripts', array($this, 'enqueue_websocket_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_websocket_scripts'));
        
        // Schedule automation status checks (reduced frequency - fallback only)
        add_action('wp', array($this, 'schedule_status_checks'));
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
        add_action('top_up_agent_check_automation_status', array($this, 'check_automation_status_updates'));
    }
    
    /**
     * Restrict cart to single product only
     */
    public function restrict_cart_to_single_product($valid, $product_id, $quantity) {
        // Check if cart already has items
        if (!WC()->cart->is_empty()) {
            // Clear any existing items first
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                WC()->cart->remove_cart_item($cart_item_key);
            }
            
            // Show notice about replacing the previous item
            wc_add_notice(__('Previous product has been removed. You can only purchase one product at a time.', 'top-up-agent'), 'notice');
        }
        
        return $valid;
    }
    
    /**
     * Force quantity to always be 1
     */
    public function force_quantity_one($args, $product) {
        $args['input_value'] = 1;
        $args['max_value'] = 1;
        $args['min_value'] = 1;
        $args['step'] = 1;
        return $args;
    }
    
    /**
     * Disable quantity input field - display as text
     */
    public function disable_quantity_input($product_quantity, $cart_item_key, $cart_item) {
        return '1'; // Just show "1" as plain text
    }
    
    /**
     * Prevent quantity updates in cart
     */
    public function prevent_quantity_update($passed, $cart_item_key, $values, $quantity) {
        // Force quantity to remain 1
        if ($quantity != 1) {
            wc_add_notice(__('Quantity cannot be changed. Each order is limited to 1 product with quantity 1.', 'top-up-agent'), 'error');
            return false;
        }
        return $passed;
    }
    
    /**
     * Enforce quantity as 1 for all cart items
     */
    public function enforce_quantity_one($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            // Force quantity to 1 if it's not already
            if ($cart_item['quantity'] != 1) {
                $cart->set_quantity($cart_item_key, 1, false);
            }
        }
    }
    
    /**
     * Enforce single product in cart - remove old items when new one is added
     */
    public function enforce_single_product_in_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        // Get all cart items
        $cart_items = WC()->cart->get_cart();
        
        // If more than one item in cart, keep only the newest one
        if (count($cart_items) > 1) {
            foreach ($cart_items as $key => $item) {
                // Remove all items except the one just added
                if ($key !== $cart_item_key) {
                    WC()->cart->remove_cart_item($key);
                }
            }
        }
    }
    
    /**
     * Show notice about cart restrictions
     */
    public function show_cart_restriction_notice() {
        wc_print_notice(__('Note: You can only purchase one product with quantity 1 per order.', 'top-up-agent'), 'notice');
    }
    
    /**
     * Register custom order statuses for automation tracking
     */
    private function register_custom_order_statuses() {
        add_action('init', function() {
            register_post_status(self::STATUS_AUTOMATION_PENDING, array(
                'label' => 'Automation Pending',
                'public' => true,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                'label_count' => _n_noop('Automation Pending <span class="count">(%s)</span>', 'Automation Pending <span class="count">(%s)</span>')
            ));
            
            register_post_status(self::STATUS_AUTOMATION_PROCESSING, array(
                'label' => 'Automation Processing',
                'public' => true,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                'label_count' => _n_noop('Automation Processing <span class="count">(%s)</span>', 'Automation Processing <span class="count">(%s)</span>')
            ));
            
            register_post_status(self::STATUS_AUTOMATION_FAILED, array(
                'label' => 'Automation Failed',
                'public' => true,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                'label_count' => _n_noop('Automation Failed <span class="count">(%s)</span>', 'Automation Failed <span class="count">(%s)</span>')
            ));
            
            register_post_status(self::STATUS_AUTOMATION_COMPLETED, array(
                'label' => 'Automation Completed',
                'public' => true,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                'label_count' => _n_noop('Automation Completed <span class="count">(%s)</span>', 'Automation Completed <span class="count">(%s)</span>')
            ));
        });
        
        // Add to WooCommerce order statuses
        add_filter('wc_order_statuses', function($order_statuses) {
            $order_statuses['wc-' . self::STATUS_AUTOMATION_PENDING] = 'Automation Pending';
            $order_statuses['wc-' . self::STATUS_AUTOMATION_PROCESSING] = 'Automation Processing';  
            $order_statuses['wc-' . self::STATUS_AUTOMATION_FAILED] = 'Automation Failed';
            $order_statuses['wc-' . self::STATUS_AUTOMATION_COMPLETED] = 'Automation Completed';
            return $order_statuses;
        });
    }
    
    /**
     * Debug order status changes
     */
    public function debug_order_status_change($order_id, $old_status, $new_status, $order) {
        error_log("Top Up Agent WooCommerce: Order #$order_id status changed: '$old_status' → '$new_status'");
        
        if ($new_status === 'processing') {
            error_log("Top Up Agent WooCommerce: Order #$order_id reached PROCESSING - handle_processing_order should be called");
        }
    }
    
    /**
     * Handle orders that reach processing status
     * This is the main trigger for automation
     * Returns true if automation was successfully triggered, false otherwise
     */
    public function handle_processing_order($order_id) {
        error_log("Top Up Agent: handle_processing_order called for Order #$order_id");
        
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log("Top Up Agent: Order #$order_id not found");
            return false;
        }
        
        error_log("Top Up Agent: Order #$order_id found, checking automation eligibility");
        
        // Check if automation is already running/completed for this order
        if ($this->is_automation_already_handled($order_id)) {
            error_log("Top Up Agent: Automation already handled for order #$order_id");
            return false; // Already handled, not an error but no new automation needed
        }
        
        error_log("Top Up Agent: Order #$order_id - no previous automation found, checking product eligibility");
        
        // Check if any product in the order is eligible for automation
        if (!Top_Up_Agent_Product_Eligibility_Checker::check_order_eligibility($order)) {
            error_log("Top Up Agent: Order #$order_id has no products eligible for automation");
            $order->add_order_note('ℹ️ Order checked for automation - no eligible products found');
            return false; // No eligible products
        }
        
        error_log("Top Up Agent: Order #$order_id has eligible products, extracting player ID");
        
        // Extract player ID from order
        $player_id = $this->extract_player_id($order);
        if (!$player_id) {
            error_log("Top Up Agent: No player ID found for order #$order_id");
            $order->add_order_note('❌ Automation failed: No player ID found in order details');
            return false; // Missing player ID
        }
        
        error_log("Top Up Agent: Order #$order_id - Player ID found: $player_id, starting automation");
        
        // Start automation process
        $result = $this->start_automation_process($order, $player_id);
        
        if ($result) {
            error_log("Top Up Agent: Automation successfully started for order #$order_id");
            return true;
        } else {
            error_log("Top Up Agent: Failed to start automation for order #$order_id");
            return false;
        }
    }
    
    /**
     * Enqueue WebSocket scripts for real-time automation updates
     */
    public function enqueue_websocket_scripts() {
        // Only enqueue on order pages and WooCommerce admin pages
        if (!is_admin() && !is_wc_endpoint_url('view-order')) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style('top-up-agent-automation-status', 
            plugin_dir_url(__FILE__) . '../../assets/css/automation-status.css', 
            array(), 
            '1.0.0'
        );
        
        // Enqueue Socket.IO client library from CDN
        wp_enqueue_script(
            'socket-io-client-v2',
            'https://cdn.socket.io/4.7.5/socket.io.min.js',
            array(),
            '4.7.5',
            true
        );
        
        // Enqueue JavaScript (Socket.IO integration) - NEW HANDLE
        wp_enqueue_script('top-up-agent-socketio-v2', 
            plugin_dir_url(__FILE__) . '../../assets/js/socket-io-integration.js', 
            array('jquery', 'socket-io-client-v2'), 
            '2.0.2-' . time(), // Add timestamp to force cache bust
            true
        );
        
        // Get server URL from settings
        $server_url = get_option('top_up_agent_server_url', '');
        // For Socket.IO, we use the HTTP server URL directly, not ws://
        $socket_url = rtrim($server_url, '/');
        
        wp_localize_script('top-up-agent-socketio-v2', 'topUpAgentWebSocket', array(
            'url' => $socket_url,
            'serverUrl' => $server_url,
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
            'enableWebSocket' => true // Force enable since we bypassed the health check
        ));
    }
    
    /**
     * Check if automation is already handled for this order
     */
    private function is_automation_already_handled($order_id) {
        $automation_status = get_post_meta($order_id, '_automation_status', true);
        return in_array($automation_status, ['processing', 'completed', 'failed']);
    }
    
    /**
     * Check if order has products eligible for automation
     */
    private function is_order_eligible_for_automation($order) {
        $order_id = $order->get_id();
        error_log("Top Up Agent: Checking automation eligibility for order #$order_id");
        
        foreach ($order->get_items() as $item) {
            // Ensure we have a proper WooCommerce order item
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }
            
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            
            // Use variation ID if available, otherwise product ID
            $check_id = $variation_id ? $variation_id : $product_id;
            
            error_log("Top Up Agent: Checking product ID $check_id (product: $product_id, variation: $variation_id)");
            
            // Check if product has automation enabled
            $automation_enabled = get_post_meta($check_id, '_automation_enabled', true);
            error_log("Top Up Agent: Product $check_id automation_enabled meta: " . ($automation_enabled ?: 'not set'));
            
            if ($automation_enabled === 'yes') {
                error_log("Top Up Agent: Product $check_id is eligible for automation");
                return true;
            }
            
            // Also check the main product if we checked variation
            if ($variation_id) {
                $main_automation_enabled = get_post_meta($product_id, '_automation_enabled', true);
                error_log("Top Up Agent: Main product $product_id automation_enabled meta: " . ($main_automation_enabled ?: 'not set'));
                
                if ($main_automation_enabled === 'yes') {
                    error_log("Top Up Agent: Main product $product_id is eligible for automation");
                    return true;
                }
            }
        }
        
        error_log("Top Up Agent: No eligible products found for order #$order_id");
        return false;
    }
    
    /**
     * Extract player ID from order using multiple methods
     */
    private function extract_player_id($order) {
        // Method 1: Custom field from checkout
        $player_id = $order->get_meta('_player_id');
        if ($player_id) return $player_id;
        
        // Method 2: From billing fields
        $player_id = $order->get_meta('_billing_player_id');
        if ($player_id) return $player_id;
        
        // Method 3: From order item variation data using configured meta key
        $configured_meta_key = get_option('top_up_agent_player_id_meta_key', 'player_id');
        
        foreach ($order->get_items() as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }
            
            // First check the exact configured meta key
            $player_id = $item->get_meta($configured_meta_key);
            if ($player_id) {
                return $player_id;
            }
            
            // Also check with underscore prefix (WooCommerce convention)
            $player_id = $item->get_meta('_' . $configured_meta_key);
            if ($player_id) {
                return $player_id;
            }
            
            // Check all variation meta data for the configured key
            $variation_data = $item->get_meta_data();
            foreach ($variation_data as $meta) {
                $key = $meta->get_data()['key'] ?? '';
                $value = $meta->get_data()['value'] ?? '';
                
                // Check if the key matches the configured meta key (case insensitive)
                if (strcasecmp($key, $configured_meta_key) === 0 && $value) {
                    return $value;
                }
                
                // Also check with underscore prefix
                if (strcasecmp($key, '_' . $configured_meta_key) === 0 && $value) {
                    return $value;
                }
            }
            
            // Fallback: Check formatted meta data for display names
            $formatted_meta = $item->get_formatted_meta_data();
            foreach ($formatted_meta as $meta_id => $meta) {
                // Check if display key contains the configured meta key or common Player ID patterns
                if ((stripos($meta->display_key, str_replace('_', ' ', $configured_meta_key)) !== false ||
                     preg_match('/player[_\s]*id|uid|user[_\s]*id/i', $meta->display_key)) && 
                    $meta->display_value) {
                    $cleaned_value = trim(strip_tags($meta->display_value));
                    if ($cleaned_value) {
                        return $cleaned_value;
                    }
                }
            }
        }
        
        // Method 4: From order notes (legacy)
        $player_id = Top_Up_Agent_Player_ID_Detector::detect_player_id($order);
        if ($player_id) return $player_id;
        
        // Method 5: Extract from customer note
        $customer_note = $order->get_customer_note();
        if (preg_match('/player[_\s]*id[:\s]*([a-zA-Z0-9]+)/i', $customer_note, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Start the automation process for an order
     * Returns true if automation was successfully queued, false otherwise
     */
    private function start_automation_process($order, $player_id) {
        $order_id = $order->get_id();
        
        error_log("Top Up Agent: Starting automation for order #$order_id with player ID: $player_id");
        
        // Update order status to automation pending
        $order->update_status(self::STATUS_AUTOMATION_PENDING, 'Automation process starting...');
        
        // Store automation metadata
        update_post_meta($order_id, '_automation_status', 'pending');
        update_post_meta($order_id, '_automation_player_id', $player_id);
        update_post_meta($order_id, '_automation_started', current_time('mysql'));
        
        // Get eligible products and their license requirements
        $automation_data = $this->prepare_automation_data($order, $player_id);
        
        if (empty($automation_data)) {
            $this->handle_automation_failure($order, 'No license keys available for automation');
            return false;
        }
        
        // Send to automation queue based on license type
        $result = $this->queue_automation($order, $automation_data);
        
        if ($result) {
            error_log("Top Up Agent: Automation successfully queued for order #$order_id");
            return true;
        } else {
            error_log("Top Up Agent: Failed to queue automation for order #$order_id");
            return false;
        }
    }
    
    /**
     * Prepare automation data based on products and license types
     */
    private function prepare_automation_data($order, $player_id) {
        $automation_items = [];
        
        foreach ($order->get_items() as $item) {
            // Ensure we have a proper WooCommerce order item
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }
            
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $check_id = $variation_id ? $variation_id : $product_id;
            
            // Check automation enabled using NEW global settings system
            $enabled_products_option = get_option('top_up_agent_products_automation_enabled', []);
            $automation_enabled_global = in_array($check_id, $enabled_products_option) || in_array($product_id, $enabled_products_option);
            
            // FALLBACK: Also check the old individual meta system for backward compatibility
            $automation_enabled_meta = get_post_meta($check_id, '_automation_enabled', true) === 'yes';
            $main_automation_enabled_meta = $variation_id ? get_post_meta($product_id, '_automation_enabled', true) === 'yes' : false;
            
            // Product is eligible if it's in the global settings OR has individual meta enabled
            $is_eligible = $automation_enabled_global || $automation_enabled_meta || $main_automation_enabled_meta;
            
            // Skip if automation not enabled for this product
            if (!$is_eligible) {
                error_log("Top Up Agent: Automation not enabled for product ID $check_id (variation of $product_id)");
                continue;
            }
            
            // Get available license key for this product (quantity is always 1)
            $license_data = $this->get_license_for_product($check_id);
            
            if ($license_data) {
                $automation_items[] = [
                    'product_id' => $check_id,
                    'product_name' => $item->get_name(),
                    'quantity' => 1,
                    'license_data' => $license_data
                ];
                error_log("Top Up Agent: Created automation item for product $check_id");
            } else {
                error_log("Top Up Agent: No license key available for product $check_id");
            }
        }
        
        return $automation_items;
    }
    
    /**
     * Get license key(s) for a specific product
     */
    private function get_license_for_product($product_id) {
        error_log("Top Up Agent: Checking licenses for product ID: $product_id");
        
        // First check for group licenses
        $group_license_data = $this->license_manager->get_unused_group_license_by_product($product_id);
        
        if (!empty($group_license_data) && is_array($group_license_data) && isset($group_license_data['license_keys'])) {
            error_log("Top Up Agent: Found group license data: " . print_r($group_license_data, true));
            
            // Make sure we have valid license keys array
            $license_keys = $group_license_data['license_keys'] ?? [];
            error_log("Top Up Agent: License keys from group: " . print_r($license_keys, true));
            
            // Ensure license keys are an array and not empty
            if (!is_array($license_keys) || empty($license_keys)) {
                error_log("Top Up Agent: Invalid license keys array for group license");
                return null;
            }
            
            return [
                'type' => 'group',
                'group_id' => $group_license_data['group_id'] ?? '',
                'group_name' => $group_license_data['group_name'] ?? 'Unknown Group',
                'license_keys' => $license_keys,
                'redimension_codes' => $license_keys // These should be the actual redimension codes
            ];
        }
        
        error_log("Top Up Agent: No group licenses found, checking individual licenses");
        
        // Fallback to individual license
        $individual_license = $this->license_manager->find_available_license_key($product_id);
        
        if ($individual_license) {
            error_log("Top Up Agent: Found individual license: " . $individual_license->license_key);
            return [
                'type' => 'single',
                'license_key' => $individual_license->license_key,
                'redimension_code' => $individual_license->license_key // Using license_key as redimension code
            ];
        }
        
        error_log("Top Up Agent: No licenses found for product ID: $product_id");
        return null;
    }
    
    /**
     * Queue automation based on license type
     * Returns true if at least one automation was successfully queued, false otherwise
     */
    private function queue_automation($order, $automation_data) {
        $order_id = $order->get_id();
        $player_id = get_post_meta($order_id, '_automation_player_id', true);
        $success_count = 0;
        $total_count = count($automation_data);
        
        foreach ($automation_data as $item) {
            $license_data = $item['license_data'];
            
            if ($license_data['type'] === 'group') {
                // Group Automation - use group-specific API endpoint
                $redimension_codes = $license_data['redimension_codes'] ?? [];
                $license_keys = $license_data['license_keys'] ?? [];
                
                // Log the actual redimension codes being sent
                error_log("Top Up Agent: Group license keys: " . print_r($license_keys, true));
                error_log("Top Up Agent: Group redimension codes: " . print_r($redimension_codes, true));
                
                // Validate that we have valid license keys and redimension codes
                if (!is_array($license_keys) || empty($license_keys)) {
                    error_log("Top Up Agent: Invalid or empty license keys for group automation");
                    $this->handle_automation_failure($order, 'Invalid group license data: No license keys available');
                    continue;
                }
                
                if (!is_array($redimension_codes) || empty($redimension_codes)) {
                    error_log("Top Up Agent: Invalid or empty redimension codes for group automation");
                    $this->handle_automation_failure($order, 'Invalid group license data: No redimension codes available');
                    continue;
                }
                
                $group_queue_data = [
                    'sourceSite' => get_site_url(),
                    'orderId' => $order_id,
                    'productName' => $item['product_name'],
                    'licenseKeys' => $license_keys, // Array of license keys for reference
                    'redimensionCodes' => $redimension_codes, // Array of actual redimension codes
                    'playerId' => $player_id,
                    'priority' => 1,
                    'webhookUrl' => Top_Up_Agent_Webhook_Handler::get_webhook_url() // Add webhook URL for status updates
                ];
                
                error_log("Top Up Agent: Sending group automation data: " . print_r($group_queue_data, true));
                
                // Use the group-specific API endpoint
                $result = $this->api->add_group_to_queue($group_queue_data);
                
                // Check for WP_Error first
                if (is_wp_error($result)) {
                    $this->handle_automation_failure($order, 'API Error: ' . $result->get_error_message());
                    error_log("Top Up Agent: API Error for order #$order_id: " . $result->get_error_message());
                    continue;
                }
                
                if (isset($result['success']) && $result['success']) {
                    // Mark group licenses as used
                    $this->license_manager->mark_group_license_used($license_data['group_id']);
                    
                    // Store group queue references (API returns groupId and queueIds array for group endpoint)
                    if (isset($result['groupId']) && !empty($result['groupId'])) {
                        update_post_meta($order_id, '_automation_group_queue_id', $result['groupId']);
                    }
                    if (isset($result['queueIds']) && is_array($result['queueIds'])) {
                        update_post_meta($order_id, '_automation_queue_ids', $result['queueIds']);
                    }
                    update_post_meta($order_id, '_automation_type', 'group');
                    update_post_meta($order_id, '_automation_group_id', $license_data['group_id']);
                    
                    $total_queued = isset($result['totalAdded']) ? $result['totalAdded'] : count($license_keys);
                    $order->update_status(self::STATUS_AUTOMATION_PROCESSING, 
                        "🔄 Group automation queued with {$total_queued} license keys");
                    
                    error_log("Top Up Agent: Group automation queued for order #$order_id - Group ID: {$result['groupId']}, Total: {$total_queued}");
                    $success_count++;
                } else {
                    $this->handle_automation_failure($order, 'Failed to queue group automation: ' . ($result['error'] ?? 'Unknown error'));
                }
                
            } else {
                // Single Automation
                $queue_data = [
                    'queueType' => 'single',
                    'playerId' => $player_id,
                    'redimensionCode' => $license_data['redimension_code'],
                    'sourceSite' => get_site_url(),
                    'productName' => $item['product_name'],
                    'licenseKey' => $license_data['license_key'],
                    'orderId' => $order_id,
                    'webhookUrl' => Top_Up_Agent_Webhook_Handler::get_webhook_url() // Add webhook URL for status updates
                ];
                
                $result = $this->api->add_to_queue($queue_data);
                
                // Debug: Log the API response
                error_log("Top Up Agent DEBUG: API Response for order #$order_id: " . print_r($result, true));
                
                // Check for WP_Error first
                if (is_wp_error($result)) {
                    $this->handle_automation_failure($order, 'API Error: ' . $result->get_error_message());
                    error_log("Top Up Agent: API Error for order #$order_id: " . $result->get_error_message());
                    continue;
                }
                
                if (isset($result['success']) && $result['success']) {
                    // Mark license as used
                    $this->license_manager->mark_as_used($license_data['license_key'], $order_id);
                    
                    // Debug: Check what ID fields exist in the response
                    error_log("Top Up Agent DEBUG: Looking for queue ID in response - Full result: " . print_r($result, true));
                    
                    // Try multiple possible response structures
                    $queue_id = null;
                    if (isset($result['data']['id'])) {
                        $queue_id = $result['data']['id'];
                        error_log("Top Up Agent DEBUG: Found queue ID in result['data']['id']: $queue_id");
                    } elseif (isset($result['id'])) {
                        $queue_id = $result['id'];
                        error_log("Top Up Agent DEBUG: Found queue ID in result['id']: $queue_id");
                    } elseif (isset($result['queueId'])) {
                        $queue_id = $result['queueId'];
                        error_log("Top Up Agent DEBUG: Found queue ID in result['queueId']: $queue_id");
                    } else {
                        error_log("Top Up Agent WARNING: No queue ID found in API response!");
                    }
                    
                    // Store queue reference if we found it
                    if (!empty($queue_id)) {
                        update_post_meta($order_id, '_automation_queue_id', $queue_id);
                        error_log("Top Up Agent: Stored queue ID $queue_id for order #$order_id");
                    }
                    
                    update_post_meta($order_id, '_automation_type', 'single');
                    update_post_meta($order_id, '_automation_license_key', $license_data['license_key']);
                    
                    $order->update_status(self::STATUS_AUTOMATION_PROCESSING, 
                        "🔄 Single automation queued with license: " . substr($license_data['license_key'], 0, 10) . "...");
                    
                    error_log("Top Up Agent: Single automation queued for order #$order_id");
                    $success_count++;
                } else {
                    $this->handle_automation_failure($order, 'Failed to queue single automation: ' . ($result['error'] ?? 'Unknown error'));
                }
            }
        }
        
        // Return true if at least one automation was successfully queued
        $success = $success_count > 0;
        error_log("Top Up Agent: queue_automation result for order #$order_id: $success_count/$total_count succeeded = " . ($success ? 'true' : 'false'));
        return $success;
    }

    /**
     * Handle automation failure
     *
     * @param WC_Order $order
     * @param string $error_message
     */
    private function handle_automation_failure($order, $error_message) {
        $order_id = $order->get_id();
        
        // Update order status to failed
        $order->update_status(self::STATUS_AUTOMATION_FAILED, "❌ Automation failed: $error_message");
        
        // Store failure details
        update_post_meta($order_id, '_automation_error', $error_message);
        update_post_meta($order_id, '_automation_failed_at', current_time('mysql'));
        
        // Log the failure
        error_log("Top Up Agent: Automation failed for order #$order_id - $error_message");
        
        // Optionally send admin notification
        $this->maybe_send_failure_notification($order, $error_message);
    }

    /**
     * Send failure notification to admin
     *
     * @param WC_Order $order
     * @param string $error_message
     */
    private function maybe_send_failure_notification($order, $error_message) {
        $send_notifications = get_option('top_up_agent_send_failure_notifications', 'yes');
        
        if ($send_notifications === 'yes') {
            $admin_email = get_option('admin_email');
            $subject = 'Top Up Agent - Automation Failed for Order #' . $order->get_id();
            $message = "Automation failed for order #{$order->get_id()}\n\n";
            $message .= "Error: $error_message\n";
            $message .= "Order Date: " . $order->get_date_created()->format('Y-m-d H:i:s') . "\n";
            $message .= "Customer: " . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . "\n";
            
            wp_mail($admin_email, $subject, $message);
        }
    }

    /**
     * Check and update automation status for all pending orders
     */
    public function check_automation_status_updates() {
        // Part 1: Check existing automation statuses
        $this->check_existing_automations();
        
        // Part 2: Check for orders that need automation but don't have it
        $this->check_missing_automations();
    }
    
    /**
     * Check status of existing automations
     */
    private function check_existing_automations() {
        try {
            $pending_orders = get_posts(array(
                'post_type' => 'shop_order',
                'meta_query' => array(
                    array(
                        'key' => '_automation_status',
                        'value' => array('pending', 'processing'),
                        'compare' => 'IN'
                    )
                ),
                'posts_per_page' => 50,
                'post_status' => array('wc-automation-pending', 'wc-automation-processing')
            ));

            // Skip if no pending orders
            if (empty($pending_orders)) {
                return;
            }

            // Get recent queue items to check status with timeout protection
            $recent_items_result = null;
            try {
                $recent_items_result = $this->api->get_recent_queue_items();
            } catch (Exception $e) {
                error_log("Top Up Agent: API call failed in check_existing_automations: " . $e->getMessage());
                return; // Skip status checking if API is unavailable
            }
            
            if (is_wp_error($recent_items_result)) {
                error_log("Top Up Agent: Failed to get recent queue items: " . $recent_items_result->get_error_message());
                return; // Skip status checking if API returned error
            }

            $recent_items = $recent_items_result['items'] ?? [];
            
            foreach ($pending_orders as $order_post) {
                try {
                    $order = wc_get_order($order_post->ID);
                    if (!$order) continue;

                    $order_id = $order->get_id();
                    $queue_id = get_post_meta($order_id, '_automation_queue_id', true);
                    
                    // Find this order in recent items
                    $queue_item = null;
                    foreach ($recent_items as $item) {
                        if ($item['order_id'] == $order_id || $item['id'] == $queue_id) {
                            $queue_item = $item;
                            break;
                        }
                    }
                    
                    if ($queue_item) {
                        $this->update_order_from_queue_status($order, $queue_item);
                    } else {
                        error_log("Top Up Agent: Order #$order_id not found in recent queue items");
                    }
                } catch (Exception $e) {
                    error_log("Top Up Agent: Error processing order {$order_post->ID} in status check: " . $e->getMessage());
                    continue; // Skip this order and continue with others
                }
            }
        } catch (Exception $e) {
            error_log("Top Up Agent: Fatal error in check_existing_automations: " . $e->getMessage());
            return; // Gracefully fail without causing 502 errors
        }
    }
    
    /**
     * Enhanced check for missing automations with license key validation
     */
    private function check_missing_automations() {
        // Only check if auto-run is enabled
        if (!get_option('top_up_agent_auto_run_on_processing', false)) {
            return;
        }
        
        // Get processing orders without automation
        $processing_orders = get_posts(array(
            'post_type' => 'shop_order',
            'post_status' => 'wc-processing',
            'posts_per_page' => 20,
            'meta_query' => array(
                array(
                    'key' => '_automation_status',
                    'compare' => 'NOT EXISTS'
                )
            ),
            'date_query' => array(
                array(
                    'after' => '1 hour ago', // Only check recent orders
                )
            )
        ));
        
        foreach ($processing_orders as $order_post) {
            $order = wc_get_order($order_post->ID);
            if (!$order) continue;
            
            // Check automation status more thoroughly
            $enabled_products = get_option('top_up_agent_products_automation_enabled', []);
            $has_automation_products = false;
            $has_available_licenses = false;
            
            foreach ($order->get_items() as $item) {
                // Ensure we have a proper WooCommerce order item
                if (!$item instanceof WC_Order_Item_Product) {
                    continue;
                }
                
                $product_id = $item->get_product_id();
                $variation_id = $item->get_variation_id();
                
                if (in_array($product_id, $enabled_products) || 
                    ($variation_id && in_array($variation_id, $enabled_products))) {
                    $has_automation_products = true;
                    
                    // Check license availability
                    $check_product_id = $variation_id ?: $product_id;
                    global $wpdb;
                    $license_table = $wpdb->prefix . 'top_up_agent_license_keys';
                    
                    $available_licenses = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $license_table 
                         WHERE status = 'unused' 
                         AND (product_ids IS NULL OR product_ids = '' OR FIND_IN_SET(%s, product_ids) > 0)",
                        $check_product_id
                    ));
                    
                    if ($available_licenses > 0) {
                        $has_available_licenses = true;
                        break;
                    }
                }
            }
            
            if ($has_automation_products) {
                if ($has_available_licenses) {
                    error_log("Top Up Agent: Found processing order #{$order->get_id()} that needs automation");
                    
                    // Mark as needs automation and try to trigger
                    update_post_meta($order->get_id(), '_automation_status', 'needs_automation');
                    update_post_meta($order->get_id(), '_automation_reason', 'Processing order with automation-enabled products and available license keys');
                    
                    // Try to trigger automation
                    $this->handle_processing_order($order->get_id());
                } else {
                    // Has automation products but no license keys
                    update_post_meta($order->get_id(), '_automation_status', 'no_licenses');
                    update_post_meta($order->get_id(), '_automation_reason', 'Automation-enabled products but no available license keys');
                }
            } else {
                // No automation products
                update_post_meta($order->get_id(), '_automation_status', 'disabled');
                update_post_meta($order->get_id(), '_automation_reason', 'No automation-enabled products in order');
            }
        }
    }
    
    /**
     * Check if order should trigger automation
     */
    private function should_trigger_automation($order) {
        // Check if any products in the order are automation-enabled
        $enabled_products = get_option('top_up_agent_products_automation_enabled', []);
        
        if (empty($enabled_products)) {
            return false;
        }
        
        foreach ($order->get_items() as $item) {
            // Ensure we have a proper WooCommerce order item
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }
            
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            
            // Check if product or variation is enabled for automation
            if (in_array($product_id, $enabled_products) || 
                ($variation_id && in_array($variation_id, $enabled_products))) {
                
                // Check if there are available license keys for this product
                $check_product_id = $variation_id ?: $product_id;
                global $wpdb;
                $license_table = $wpdb->prefix . 'top_up_agent_license_keys';
                
                $available_licenses = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $license_table 
                     WHERE status = 'unused' 
                     AND (product_ids IS NULL OR product_ids = '' OR FIND_IN_SET(%s, product_ids) > 0)",
                    $check_product_id
                ));
                
                if ($available_licenses > 0) {
                    return true; // Has automation products AND available license keys
                }
            }
        }
        
        return false;
    }
    
    /**
     * Update order status based on queue status
     *
     * @param WC_Order $order
     * @param array $queue_status
     */
    private function update_order_from_queue_status($order, $queue_status) {
        $order_id = $order->get_id();
        $current_status = $queue_status['status'] ?? '';
        $stored_status = get_post_meta($order_id, '_automation_status', true);

        // Only update if status has changed
        if ($current_status === $stored_status) {
            return;
        }

        switch ($current_status) {
            case 'completed':
                $order->update_status(self::STATUS_AUTOMATION_COMPLETED, 
                    "✅ Automation completed successfully");
                update_post_meta($order_id, '_automation_status', 'completed');
                update_post_meta($order_id, '_automation_completed_at', current_time('mysql'));
                update_post_meta($order_id, '_automation_completed', 'yes');
                
                // Store any result data
                if (!empty($queue_status['result'])) {
                    update_post_meta($order_id, '_automation_result', $queue_status['result']);
                }
                
                // Trigger auto-completion check
                do_action('top_up_agent_automation_completed', $order_id, $queue_status);
                break;

            case 'failed':
                $error_message = $queue_status['error'] ?? 'Unknown automation error';
                $this->handle_automation_failure($order, $error_message);
                update_post_meta($order_id, '_automation_status', 'failed');
                break;

            case 'processing':
                if ($stored_status !== 'processing') {
                    $order->update_status(self::STATUS_AUTOMATION_PROCESSING, 
                        "🔄 Automation is now being processed");
                    update_post_meta($order_id, '_automation_status', 'processing');
                }
                break;
        }

        // Update progress if available
        if (isset($queue_status['progress'])) {
            update_post_meta($order_id, '_automation_progress', $queue_status['progress']);
        }
    }

    /**
     * Add automation info to order details (admin)
     *
     * @param WC_Order $order
     */
    public function add_automation_order_details($order) {
        $automation_status = get_post_meta($order->get_id(), '_automation_status', true);
        
        if (!$automation_status) {
            return;
        }

        echo '<div class="top-up-agent-automation-details">';
        echo '<h3>🎮 Top Up Agent Automation</h3>';
        
        $status_icons = array(
            'pending' => '⏳',
            'processing' => '🔄',
            'completed' => '✅',
            'failed' => '❌'
        );
        
        $icon = $status_icons[$automation_status] ?? '❓';
        echo '<p><strong>Status:</strong> ' . $icon . ' ' . ucfirst($automation_status) . '</p>';
        
        $automation_type = get_post_meta($order->get_id(), '_automation_type', true);
        if ($automation_type) {
            echo '<p><strong>Type:</strong> ' . ucfirst($automation_type) . ' License</p>';
        }
        
        $license_key = get_post_meta($order->get_id(), '_automation_license_key', true);
        if ($license_key) {
            echo '<p><strong>License:</strong> ' . esc_html($license_key) . '</p>';
        }
        
        $player_id = get_post_meta($order->get_id(), '_automation_player_id', true);
        if ($player_id) {
            echo '<p><strong>Player ID:</strong> ' . esc_html($player_id) . '</p>';
        }
        
        $progress = get_post_meta($order->get_id(), '_automation_progress', true);
        if ($progress) {
            echo '<p><strong>Progress:</strong> ' . intval($progress) . '%</p>';
        }
        
        $error = get_post_meta($order->get_id(), '_automation_error', true);
        if ($error) {
            echo '<p><strong>Error:</strong> <span style="color: red;">' . esc_html($error) . '</span></p>';
        }
        
        echo '</div>';
        echo '<style>
        .top-up-agent-automation-details {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .top-up-agent-automation-details h3 {
            margin-top: 0;
            color: #333;
        }
        </style>';
    }

    /**
     * AJAX handler for manually triggering automation
     */
    public function ajax_trigger_automation() {
        check_ajax_referer('top_up_agent_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized');
        }
        
        $order_id = intval($_POST['order_id'] ?? 0);
        if (!$order_id) {
            wp_send_json_error('Invalid order ID');
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Order not found');
        }
        
        // Check if order is in processing status
        if ($order->get_status() !== 'processing') {
            wp_send_json_error('Order must be in processing status to trigger automation. Current status: ' . $order->get_status());
        }
        
        // Run comprehensive diagnosis
        $diagnosis = $this->diagnose_automation_issues($order);
        
        // Check if there are critical errors that prevent automation
        if (!empty($diagnosis['errors'])) {
            $error_message = "Cannot trigger automation due to the following issues:\n\n";
            foreach ($diagnosis['errors'] as $error) {
                $error_message .= "• " . strip_tags($error) . "\n";
            }
            $error_message .= "\nPlease fix these issues first, then try again.";
            wp_send_json_error($error_message);
            return;
        }
        
        // Add order note about manual trigger
        $order->add_order_note('🚀 Automation manually triggered by admin');
        
        // Trigger automation
        $result = $this->handle_processing_order($order_id);
        
        if ($result) {
            wp_send_json_success('Automation triggered successfully');
        } else {
            wp_send_json_error('Failed to start automation. Check the order diagnosis above for details.');
        }
    }

    /**
     * AJAX handler for manual automation retry
     */
    public function ajax_retry_automation() {
        check_ajax_referer('top_up_agent_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized');
        }
        
        $order_id = intval($_POST['order_id'] ?? 0);
        if (!$order_id) {
            wp_send_json_error('Invalid order ID');
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Order not found');
        }
        
        // Run diagnosis before retrying
        $diagnosis = $this->diagnose_automation_issues($order);
        
        // Check if there are critical errors that prevent automation
        if (!empty($diagnosis['errors'])) {
            $error_message = "Cannot retry automation due to the following issues:\n\n";
            foreach ($diagnosis['errors'] as $error) {
                $error_message .= "• " . strip_tags($error) . "\n";
            }
            $error_message .= "\nPlease fix these issues first, then try again.";
            wp_send_json_error($error_message);
            return;
        }
        
        // Reset automation status
        delete_post_meta($order_id, '_automation_status');
        delete_post_meta($order_id, '_automation_error');
        delete_post_meta($order_id, '_automation_queue_id');
        delete_post_meta($order_id, '_automation_started');
        delete_post_meta($order_id, '_automation_completed_at');
        delete_post_meta($order_id, '_automation_failed_at');
        
        // Add order note about retry
        $order->add_order_note('🔄 Automation retry initiated by admin');
        
        // Trigger automation again
        $result = $this->handle_processing_order($order_id);
        
        if ($result) {
            wp_send_json_success('Automation retry initiated successfully');
        } else {
            wp_send_json_error('Failed to start automation. Check the order diagnosis above for details.');
        }
    }

    /**
     * AJAX handler for canceling automation
     */
    public function ajax_cancel_automation() {
        check_ajax_referer('top_up_agent_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized');
        }
        
        $order_id = intval($_POST['order_id'] ?? 0);
        if (!$order_id) {
            wp_send_json_error('Invalid order ID');
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Order not found');
        }
        
        $queue_id = get_post_meta($order_id, '_automation_queue_id', true);
        if ($queue_id) {
            // Cancel on server using the API instance
            $result = $this->api->cancel_queue_item($queue_id);
            
            if (is_wp_error($result)) {
                wp_send_json_error('Failed to cancel automation: ' . $result->get_error_message());
            }
        }
        
        // Update order status
        $order->update_status('processing', 'Automation cancelled by admin');
        
        // Clean up automation meta
        delete_post_meta($order_id, '_automation_status');
        delete_post_meta($order_id, '_automation_queue_id');
        delete_post_meta($order_id, '_automation_type');
        delete_post_meta($order_id, '_automation_license_key');
        
        wp_send_json_success('Automation cancelled');
    }

    /**
     * AJAX handler for checking order queue status
     */
    public function ajax_check_order_queue_status() {
        // Start output buffering to prevent any unwanted output
        ob_start();
        
        try {
            // Check nonce
            if (!check_ajax_referer('top_up_agent_websocket', 'nonce', false)) {
                ob_end_clean();
                wp_send_json_error('Security check failed');
                return;
            }
            
            // Check permissions
            if (!current_user_can('manage_woocommerce')) {
                ob_end_clean();
                wp_send_json_error('Unauthorized');
                return;
            }
            
            // Validate order ID
            $order_id = intval($_POST['orderId'] ?? 0);
            if (!$order_id) {
                ob_end_clean();
                wp_send_json_error('Invalid order ID');
                return;
            }
            
            // Get pending queue items with error handling
            $pending_items = null;
            try {
                $pending_items = $this->api->get_pending_queue_items();
            } catch (Exception $e) {
                error_log("Top Up Agent: API error in ajax_check_order_queue_status: " . $e->getMessage());
                ob_end_clean();
                wp_send_json_error('API connection failed');
                return;
            }
            
            if (is_wp_error($pending_items)) {
                error_log("Top Up Agent: WP_Error in ajax_check_order_queue_status: " . $pending_items->get_error_message());
                ob_end_clean();
                wp_send_json_error('Failed to check queue status');
                return;
            }
            
            if (isset($pending_items['pendingItems'])) {
                foreach ($pending_items['pendingItems'] as $item) {
                    // Check if this queue item matches our order ID
                    if (isset($item['metadata']['order_id']) && $item['metadata']['order_id'] == $order_id) {
                        ob_end_clean();
                        wp_send_json_success([
                            'status' => $item['status'] ?? 'pending',
                            'queue_id' => $item['id'] ?? null,
                            'priority' => $item['priority'] ?? null,
                            'created_at' => $item['created_at'] ?? null
                        ]);
                        return;
                    }
                }
            }
            
            // Also check if order has a stored queue ID in meta
            $queue_id = get_post_meta($order_id, '_automation_queue_id', true);
            if ($queue_id) {
                // Check the specific queue item status with error handling
                try {
                    $queue_status = $this->api->get_queue_status($queue_id);
                    if (!is_wp_error($queue_status)) {
                        ob_end_clean();
                        wp_send_json_success([
                            'status' => $queue_status['status'] ?? 'unknown',
                            'queue_id' => $queue_id
                        ]);
                        return;
                    }
                } catch (Exception $e) {
                    error_log("Top Up Agent: Error checking specific queue status: " . $e->getMessage());
                    // Continue to return null result instead of failing
                }
            }
            
            // Order not found in queue
            ob_end_clean();
            wp_send_json_success(null);
            
        } catch (Exception $e) {
            // Clean any buffered output before sending error
            ob_end_clean();
            error_log("Top Up Agent: Fatal error in ajax_check_order_queue_status: " . $e->getMessage());
            wp_send_json_error('Internal server error');
        }
    }

    /**
     * AJAX handler for triggering automation from WooCommerce page
     */
    public function ajax_trigger_order_automation() {
        // Start output buffering to prevent any unwanted output
        ob_start();
        
        // Log the request for debugging
        error_log('Ajax trigger_order_automation called with data: ' . print_r($_POST, true));
        
        try {
            // Check nonce
            if (!check_ajax_referer('top_up_agent_websocket', 'nonce', false)) {
                ob_end_clean(); // Clear any buffered output
                wp_send_json_error('Security check failed - invalid nonce');
                return;
            }
            
            // Check user permissions
            if (!current_user_can('manage_woocommerce')) {
                ob_end_clean(); // Clear any buffered output
                wp_send_json_error('Unauthorized - insufficient permissions');
                return;
            }
            
            // Validate order ID
            $order_id = intval($_POST['orderId'] ?? 0);
            if (!$order_id) {
                ob_end_clean(); // Clear any buffered output
                wp_send_json_error('Invalid order ID provided: ' . ($_POST['orderId'] ?? 'null'));
                return;
            }
            
            // Check if WooCommerce is active
            if (!class_exists('WooCommerce')) {
                ob_end_clean(); // Clear any buffered output
                wp_send_json_error('WooCommerce is not active');
                return;
            }
            
            // Get the order
            $order = wc_get_order($order_id);
            if (!$order) {
                ob_end_clean(); // Clear any buffered output
                wp_send_json_error('Order not found with ID: ' . $order_id);
                return;
            }
            
            // Log order details for debugging
            error_log('Processing order #' . $order_id . ' - Status: ' . $order->get_status());
            error_log('Order customer: ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
            error_log('Order total: ' . $order->get_total());
            
            // Log order items for debugging
            foreach ($order->get_items() as $item) {
                if ($item instanceof WC_Order_Item_Product) {
                    error_log('Order item: ' . $item->get_name() . ' (Product ID: ' . $item->get_product_id() . ')');
                }
            }
            
            // Trigger automation for this order
            $result = $this->handle_processing_order($order_id);
            
            // Clear any buffered output before sending JSON
            ob_end_clean();
            
            if ($result) {
                $message = 'Automation triggered successfully for order #' . $order_id;
                error_log($message);
                wp_send_json_success($message);
            } else {
                $error = 'Failed to trigger automation for order #' . $order_id . ' - Check error logs for details';
                error_log($error);
                wp_send_json_error($error);
            }
            
        } catch (Exception $e) {
            // Clear any buffered output before sending JSON
            ob_end_clean();
            $error_message = 'Error triggering automation: ' . $e->getMessage();
            error_log($error_message . ' - Trace: ' . $e->getTraceAsString());
            wp_send_json_error($error_message);
        }
    }

    /**
     * AJAX handler for testing connection (debug purposes)
     */
    // public function ajax_test_connection() {
    //     // Start output buffering to prevent any unwanted output
    //     ob_start();
        
    //     try {
    //         // Check nonce
    //         if (!check_ajax_referer('top_up_agent_websocket', 'nonce', false)) {
    //             ob_end_clean();
    //             wp_send_json_error('Security check failed');
    //             return;
    //         }
            
    //         $test_data = array(
    //             'timestamp' => current_time('mysql'),
    //             'user_id' => get_current_user_id(),
    //             'user_can_manage_woocommerce' => current_user_can('manage_woocommerce'),
    //             'woocommerce_active' => class_exists('WooCommerce'),
    //             'plugin_version' => defined('TOP_UP_AGENT_VERSION') ? TOP_UP_AGENT_VERSION : 'unknown',
    //             'server_info' => array(
    //                 'php_version' => PHP_VERSION,
    //                 'wp_version' => get_bloginfo('version'),
    //                 'wc_version' => class_exists('WooCommerce') ? WC()->version : 'not installed'
    //             )
    //         );
            
    //         ob_end_clean();
    //         wp_send_json_success($test_data);
            
    //     } catch (Exception $e) {
    //         ob_end_clean();
    //         error_log("Top Up Agent: Error in ajax_test_connection: " . $e->getMessage());
    //         wp_send_json_error('Connection test failed');
    //     }
    // }

    /**
     * AJAX handler for enabling automation on products (debug/admin purposes)
     */
    public function ajax_enable_product_automation() {
        // Start output buffering to prevent any unwanted output
        ob_start();
        
        try {
            // Check nonce
            if (!check_ajax_referer('top_up_agent_websocket', 'nonce', false)) {
                ob_end_clean();
                wp_send_json_error('Security check failed');
                return;
            }
            
            if (!current_user_can('manage_woocommerce')) {
                ob_end_clean();
                wp_send_json_error('Unauthorized - insufficient permissions');
                return;
            }
            
            $product_id = intval($_POST['productId'] ?? 0);
            $enable = $_POST['enable'] ?? 'yes';
            
            if (!$product_id) {
                ob_end_clean();
                wp_send_json_error('Invalid product ID');
                return;
            }
            
            // Enable/disable automation for the product
            update_post_meta($product_id, '_automation_enabled', $enable);
            
            // Also set some default automation settings if enabling
            if ($enable === 'yes') {
                // Auto-detect if this is a Free Fire product and set appropriate settings
                $product = wc_get_product($product_id);
                if ($product) {
                    $product_name = $product->get_name();
                    
                    // If it's a Free Fire product, enable automation
                    if (stripos($product_name, 'free fire') !== false || 
                        stripos($product_name, 'diamond') !== false ||
                        stripos($product_name, 'top up') !== false) {
                        
                        update_post_meta($product_id, '_automation_game', 'free_fire');
                        update_post_meta($product_id, '_requires_player_id', 'yes');
                        
                        error_log("Top Up Agent: Automation enabled for Free Fire product #$product_id: $product_name");
                    }
                }
            }
            
            $status = $enable === 'yes' ? 'enabled' : 'disabled';
            ob_end_clean();
            wp_send_json_success("Automation $status for product #$product_id");
            
        } catch (Exception $e) {
            ob_end_clean();
            error_log("Top Up Agent: Error in ajax_enable_product_automation: " . $e->getMessage());
            wp_send_json_error('Failed to update product automation');
        }
    }

    /**
     * Add automation controls to order admin page
     */
    public function add_automation_admin_controls() {
        $screen = get_current_screen();
        
        if ($screen && $screen->id === 'shop_order') {
            add_meta_box(
                'top-up-agent-automation',
                '🎮 Top Up Agent Automation',
                array($this, 'render_automation_metabox'),
                'shop_order',
                'normal',
                'high'
            );
        }
    }

    /**
     * Render automation metabox content
     */
    public function render_automation_metabox($post) {
        $order = wc_get_order($post->ID);
        if (!$order) {
            echo '<p>Unable to load order information.</p>';
            return;
        }
        
        $automation_status = get_post_meta($post->ID, '_automation_status', true);
        
        wp_nonce_field('top_up_agent_nonce', 'top_up_agent_nonce');
        
        echo '<div class="top-up-agent-admin-controls">';
        
        if (!$automation_status) {
            echo '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin-bottom: 15px;">';
            echo '<h4 style="margin-top: 0; color: #856404;">⚠️ Automation Diagnosis</h4>';
            
            // Run comprehensive automation diagnosis
            $diagnosis = $this->diagnose_automation_issues($order);
            
            if (!empty($diagnosis['errors'])) {
                echo '<div style="color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 3px; margin-bottom: 10px;">';
                echo '<strong>❌ Issues Found:</strong><ul style="margin: 5px 0 0 20px;">';
                foreach ($diagnosis['errors'] as $error) {
                    echo '<li>' . $error . '</li>';
                }
                echo '</ul></div>';
            }
            
            if (!empty($diagnosis['warnings'])) {
                echo '<div style="color: #856404; background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 3px; margin-bottom: 10px;">';
                echo '<strong>⚠️ Warnings:</strong><ul style="margin: 5px 0 0 20px;">';
                foreach ($diagnosis['warnings'] as $warning) {
                    echo '<li>' . $warning . '</li>';
                }
                echo '</ul></div>';
            }
            
            if (!empty($diagnosis['info'])) {
                echo '<div style="color: #004085; background: #cce7ff; border: 1px solid #b3d7ff; padding: 10px; border-radius: 3px; margin-bottom: 10px;">';
                echo '<strong>ℹ️ Information:</strong><ul style="margin: 5px 0 0 20px;">';
                foreach ($diagnosis['info'] as $info) {
                    echo '<li>' . $info . '</li>';
                }
                echo '</ul></div>';
            }
            
            // Show action button if order can potentially be automated
            if ($diagnosis['can_trigger'] && $order->get_status() === 'processing') {
                echo '<p><button type="button" class="button button-primary" id="trigger-automation" data-order-id="' . $post->ID . '">🚀 Trigger Automation</button></p>';
                echo '<p><small>Fix the issues above first, then use this button to trigger automation.</small></p>';
            } elseif ($order->get_status() !== 'processing') {
                echo '<p><button type="button" class="button button-secondary" disabled>🚀 Trigger Automation</button></p>';
                echo '<p><small>Order must be in "Processing" status to trigger automation.</small></p>';
            } else {
                echo '<p><button type="button" class="button button-secondary" disabled>🚀 Trigger Automation</button></p>';
                echo '<p><small>Fix the critical issues above before automation can be triggered.</small></p>';
            }
            
            echo '</div>';
            echo '</div>';
            return;
        }
        
        // Display current automation details
        $this->add_automation_order_details($order);
        
        echo '<h4 style="margin-top: 20px;">🎮 Automation Controls</h4>';
        
        if (in_array($automation_status, ['failed'])) {
            echo '<button type="button" class="button button-primary" id="retry-automation" data-order-id="' . $post->ID . '">🔄 Retry Automation</button> ';
        }
        
        if (in_array($automation_status, ['pending', 'processing'])) {
            echo '<button type="button" class="button button-secondary" id="cancel-automation" data-order-id="' . $post->ID . '">❌ Cancel Automation</button>';
        }
        
        echo '</div>';
        
        // Add JavaScript for controls
        ?>
<script>
jQuery(document).ready(function($) {
    // Function to show better formatted error messages
    function showErrorMessage(title, message) {
        // Create a modal-style dialog
        var dialog = $('<div>').attr({
            'title': title,
            'style': 'max-height: 400px; overflow-y: auto;'
        }).html('<pre style="white-space: pre-wrap; font-family: Arial, sans-serif; font-size: 12px;">' +
            message + '</pre>');

        // Try to use jQuery UI dialog if available, otherwise use alert
        if ($.fn.dialog) {
            dialog.dialog({
                modal: true,
                width: 600,
                maxHeight: 500,
                buttons: {
                    "OK": function() {
                        $(this).dialog("close");
                    }
                }
            });
        } else {
            alert(title + '\n\n' + message);
        }
    }

    $('#trigger-automation').on('click', function() {
        var orderId = $(this).data('order-id');
        var button = $(this);

        button.prop('disabled', true).text('Triggering...');

        $.post(ajaxurl, {
            action: 'top_up_agent_trigger_automation',
            order_id: orderId,
            nonce: $('#top_up_agent_nonce').val()
        }, function(response) {
            if (response.success) {
                alert('Success: ' + response.data);
                location.reload();
            } else {
                showErrorMessage('Automation Trigger Failed', response.data || 'Unknown error');
                button.prop('disabled', false).text('🚀 Trigger Automation');
            }
        }).fail(function() {
            showErrorMessage('Connection Error',
                'Failed to communicate with the server. Please try again.');
            button.prop('disabled', false).text('🚀 Trigger Automation');
        });
    });

    $('#retry-automation').on('click', function() {
        var orderId = $(this).data('order-id');
        var button = $(this);

        if (!confirm('Are you sure you want to retry the automation for this order?')) {
            return;
        }

        button.prop('disabled', true).text('Retrying...');

        $.post(ajaxurl, {
            action: 'top_up_agent_retry_automation',
            order_id: orderId,
            nonce: $('#top_up_agent_nonce').val()
        }, function(response) {
            if (response.success) {
                alert('Success: ' + response.data);
                location.reload();
            } else {
                showErrorMessage('Automation Retry Failed', response.data || 'Unknown error');
                button.prop('disabled', false).text('🔄 Retry Automation');
            }
        }).fail(function() {
            showErrorMessage('Connection Error',
                'Failed to communicate with the server. Please try again.');
            button.prop('disabled', false).text('🔄 Retry Automation');
        });
    });

    $('#cancel-automation').on('click', function() {
        if (!confirm('Are you sure you want to cancel this automation?')) {
            return;
        }

        var orderId = $(this).data('order-id');
        var button = $(this);

        button.prop('disabled', true).text('Cancelling...');

        $.post(ajaxurl, {
            action: 'top_up_agent_cancel_automation',
            order_id: orderId,
            nonce: $('#top_up_agent_nonce').val()
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + (response.data || 'Unknown error'));
                button.prop('disabled', false).text('❌ Cancel Automation');
            }
        });
    });
});
</script>
<style>
.top-up-agent-admin-controls {
    background: #f9f9f9;
    border: 1px solid #ddd;
    padding: 15px;
    margin: 15px 0;
    border-radius: 5px;
}

.top-up-agent-admin-controls h4 {
    margin-top: 0;
}

.top-up-agent-automation-details {
    background: #f9f9f9;
    border: 1px solid #ddd;
    padding: 15px;
    margin: 15px 0;
    border-radius: 5px;
}

.top-up-agent-automation-details h3 {
    margin-top: 0;
    color: #333;
}
</style>
<?php
    }

    /**
     * Schedule status check cron job (reduced frequency - WebSocket is primary)
     */
    public function schedule_status_checks() {
        if (!wp_next_scheduled('top_up_agent_check_automation_status')) {
            wp_schedule_event(time(), 'every_thirty_minutes', 'top_up_agent_check_automation_status');
        }
    }

    /**
     * Add custom cron interval (30 minutes as fallback to WebSocket)
     */
    public function add_cron_intervals($schedules) {
        $schedules['every_thirty_minutes'] = array(
            'interval' => 30 * 60, // 30 minutes (reduced from 5 minutes)
            'display' => __('Every 30 Minutes')
        );
        return $schedules;
    }

    /**
     * Add automation column to orders table
     */
    public function add_automation_column($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Add automation column after order status
            if ($key === 'order_status') {
                $new_columns['automation_status'] = '🎮 Automation';
            }
        }
        
        return $new_columns;
    }

    /**
     * Display automation status in orders table
     */
    public function display_automation_status($column, $post_id) {
        if ($column === 'automation_status') {
            $automation_status = get_post_meta($post_id, '_automation_status', true);
            
            if (empty($automation_status)) {
                echo '<span style="color: #999;">—</span>';
                return;
            }
            
            $status_icons = array(
                'pending' => '<span style="color: #ffa500;">⏳ Pending</span>',
                'processing' => '<span style="color: #0073aa;">🔄 Processing</span>',
                'completed' => '<span style="color: #46b450;">✅ Completed</span>',
                'failed' => '<span style="color: #dc3232;">❌ Failed</span>'
            );
            
            echo $status_icons[$automation_status] ?? '<span style="color: #999;">❓ Unknown</span>';
            
            // Show progress if available
            $progress = get_post_meta($post_id, '_automation_progress', true);
            if ($progress && $automation_status === 'processing') {
                echo '<br><small>' . intval($progress) . '%</small>';
            }
        }
    }

    /**
     * Add automation status filter dropdown to orders list
     */
    public function add_automation_status_filter() {
        global $typenow;
        
        if ($typenow === 'shop_order') {
            $current_filter = isset($_GET['automation_status_filter']) ? $_GET['automation_status_filter'] : '';
            
            echo '<select name="automation_status_filter" id="automation_status_filter">';
            echo '<option value="">All Automation Status</option>';
            echo '<option value="none"' . selected($current_filter, 'none', false) . '>No Automation</option>';
            echo '<option value="pending"' . selected($current_filter, 'pending', false) . '>⏳ Pending</option>';
            echo '<option value="processing"' . selected($current_filter, 'processing', false) . '>🔄 Processing</option>';
            echo '<option value="completed"' . selected($current_filter, 'completed', false) . '>✅ Completed</option>';
            echo '<option value="failed"' . selected($current_filter, 'failed', false) . '>❌ Failed</option>';
            echo '</select>';
        }
    }

    /**
     * Filter orders based on automation status
     */
    public function filter_orders_by_automation_status($query) {
        global $pagenow, $typenow;
        
        if ($pagenow === 'edit.php' && $typenow === 'shop_order' && isset($_GET['automation_status_filter']) && $_GET['automation_status_filter'] !== '') {
            $filter_value = sanitize_text_field($_GET['automation_status_filter']);
            
            if ($filter_value === 'none') {
                // Show orders with no automation
                $meta_query = array(
                    'relation' => 'OR',
                    array(
                        'key' => '_automation_status',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => '_automation_status',
                        'value' => '',
                        'compare' => '='
                    )
                );
            } else {
                // Show orders with specific automation status
                $meta_query = array(
                    array(
                        'key' => '_automation_status',
                        'value' => $filter_value,
                        'compare' => '='
                    )
                );
            }
            
            $query->set('meta_query', $meta_query);
        }
    }

    /**
     * Add automation actions to order actions dropdown
     */
    public function add_order_actions($actions) {
        global $post;
        
        if (!$post || $post->post_type !== 'shop_order') {
            return $actions;
        }
        
        $automation_status = get_post_meta($post->ID, '_automation_status', true);
        
        if ($automation_status === 'failed') {
            $actions['retry_automation'] = __('🔄 Retry Automation', 'top-up-agent');
        }
        
        if (in_array($automation_status, ['pending', 'processing'])) {
            $actions['cancel_automation'] = __('❌ Cancel Automation', 'top-up-agent');
        }
        
        // Add manual completion action for automation-completed orders
        if ($automation_status === 'completed' && $post->post_status === 'wc-automation-completed') {
            $actions['complete_automation_order'] = __('🎮 Complete Order', 'top-up-agent');
        }
        
        return $actions;
    }

    /**
     * Handle retry automation action
     */
    public function retry_automation($order) {
        $order_id = $order->get_id();
        
        // Reset automation status
        delete_post_meta($order_id, '_automation_status');
        delete_post_meta($order_id, '_automation_error');
        delete_post_meta($order_id, '_automation_queue_id');
        
        // Add order note
        $order->add_order_note('🔄 Automation retry initiated by admin');
        
        // Trigger automation again
        $this->handle_processing_order($order_id);
        
        // Redirect back with success message
        wp_redirect(add_query_arg('message', 'automation_retried', wp_get_referer()));
        exit;
    }

    /**
     * Handle cancel automation action
     */
    public function cancel_automation($order) {
        $order_id = $order->get_id();
        $queue_id = get_post_meta($order_id, '_automation_queue_id', true);
        
        if ($queue_id) {
            // Try to cancel on server
            $result = $this->api->cancel_queue_item($queue_id);
            
            if (is_wp_error($result)) {
                $order->add_order_note('❌ Failed to cancel automation on server: ' . $result->get_error_message());
            } else {
                $order->add_order_note('❌ Automation cancelled by admin');
            }
        }
        
        // Clean up automation meta
        delete_post_meta($order_id, '_automation_status');
        delete_post_meta($order_id, '_automation_queue_id');
        delete_post_meta($order_id, '_automation_type');
        delete_post_meta($order_id, '_automation_license_key');
        
        // Update order status back to processing
        $order->update_status('processing', 'Automation cancelled by admin');
        
        // Redirect back with success message
        wp_redirect(add_query_arg('message', 'automation_cancelled', wp_get_referer()));
        exit;
    }

    /**
     * Handle manual completion of automation orders
     */
    public function manual_complete_automation_order($order) {
        $order_id = $order->get_id();
        
        // Set the automation completed flag if not already set
        update_post_meta($order_id, '_automation_completed', 'yes');
        
        // Complete the order
        $order->update_status('completed', 
            '🎮 Order manually completed after successful automation');
        
        // Add order note
        $order->add_order_note(
            'Top-Up Agent: Order manually completed by admin after automation success.',
            false,
            true
        );
        
        // Log the completion
        error_log("Top Up Agent: Order #{$order_id} manually completed by admin");
        
        // Redirect back with success message
        wp_redirect(add_query_arg('message', 'automation_order_completed', wp_get_referer()));
        exit;
    }

    /**
     * AJAX handler for automation status updates
     */
    public function ajax_update_automation_status() {
        check_ajax_referer('top_up_agent_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized');
        }
        
        $order_id = intval($_POST['order_id'] ?? 0);
        $new_status = sanitize_text_field($_POST['status'] ?? '');
        
        if (!$order_id || !$new_status) {
            wp_send_json_error('Invalid parameters');
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Order not found');
        }
        
        // Update automation status
        update_post_meta($order_id, '_automation_status', $new_status);
        
        // Add order note
        $order->add_order_note("🎮 Automation status updated to: $new_status");
        
        wp_send_json_success("Status updated to $new_status");
    }
    
    /**
     * Handle WebSocket automation status updates
     */
    public function ajax_websocket_automation_update() {
        error_log("Top Up Agent: WebSocket AJAX endpoint called with data: " . print_r($_POST, true));
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'top_up_agent_websocket')) {
            error_log("Top Up Agent: WebSocket nonce verification failed");
            wp_die('Security check failed');
        }
        
        $order_id = intval($_POST['order_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        $message = sanitize_text_field($_POST['message'] ?? '');
        
        error_log("Top Up Agent: Processing WebSocket update - Order: $order_id, Status: $status, Message: $message");
        
        if (!$order_id || !$status) {
            error_log("Top Up Agent: WebSocket update failed - missing parameters");
            wp_send_json_error('Missing required parameters');
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log("Top Up Agent: WebSocket update failed - order $order_id not found");
            wp_send_json_error('Order not found');
            return;
        }
        
        // Update order based on WebSocket status
        switch ($status) {
            case 'processing':
                $order->update_status(self::STATUS_AUTOMATION_PROCESSING, $message);
                update_post_meta($order_id, '_automation_status', 'processing');
                break;
                
            case 'completed':
                $order->update_status(self::STATUS_AUTOMATION_COMPLETED, $message);
                update_post_meta($order_id, '_automation_status', 'completed');
                update_post_meta($order_id, '_automation_completed_at', current_time('mysql'));
                break;
                
            case 'failed':
                $order->update_status(self::STATUS_AUTOMATION_FAILED, $message);
                update_post_meta($order_id, '_automation_status', 'failed');
                update_post_meta($order_id, '_automation_error', $message);
                update_post_meta($order_id, '_automation_failed_at', current_time('mysql'));
                break;
        }
        
        // Log the WebSocket update
        error_log("Top Up Agent: WebSocket update for Order #{$order_id}: {$status} - {$message}");
        
        wp_send_json_success(array(
            'order_id' => $order_id,
            'status' => $status,
            'message' => $message,
            'updated_at' => current_time('mysql')
        ));
    }
    
    /**
     * Comprehensive automation diagnosis for orders
     * Returns detailed information about why automation might not be working
     */
    private function diagnose_automation_issues($order) {
        $order_id = $order->get_id();
        $errors = [];
        $warnings = [];
        $info = [];
        $can_trigger = true;
        
        // Check 1: Order Status
        $order_status = $order->get_status();
        if ($order_status !== 'processing') {
            $errors[] = "Order status is '{$order_status}' but should be 'processing' for automation to trigger automatically.";
            if (!in_array($order_status, ['processing', 'automation-pending'])) {
                $can_trigger = false;
            }
        } else {
            $info[] = "✅ Order status is 'processing' - ready for automation.";
        }
        
        // Check 2: Product Automation Settings
        $eligible_products = [];
        $ineligible_products = [];
        $products_without_redimension = [];
        
        // Get enabled products from the global automation settings
        $enabled_products_option = get_option('top_up_agent_products_automation_enabled', []);
        
        foreach ($order->get_items() as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }
            
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $check_id = $variation_id ? $variation_id : $product_id;
            $product_name = $item->get_name();
            
            // Check automation enabled using NEW global settings system
            $automation_enabled_global = in_array($check_id, $enabled_products_option) || in_array($product_id, $enabled_products_option);
            
            // FALLBACK: Also check the old individual meta system for backward compatibility
            $automation_enabled_meta = get_post_meta($check_id, '_automation_enabled', true) === 'yes';
            $main_automation_enabled_meta = $variation_id ? get_post_meta($product_id, '_automation_enabled', true) === 'yes' : false;
            
            // Product is eligible if it's in the global settings OR has individual meta enabled
            $is_eligible = $automation_enabled_global || $automation_enabled_meta || $main_automation_enabled_meta;
            
            if ($is_eligible) {
                $source = $automation_enabled_global ? 'global settings' : 'individual meta';
                $eligible_products[] = $product_name . " (via {$source})";
                
                // Check for redimension code
                $redimension_code = get_post_meta($check_id, '_redimension_code', true);
                if (!$redimension_code && $variation_id) {
                    $redimension_code = get_post_meta($product_id, '_redimension_code', true);
                }
                
                if (!$redimension_code) {
                    $products_without_redimension[] = $product_name;
                }
            } else {
                $ineligible_products[] = $product_name . " (ID: {$check_id})";
            }
        }
        
        if (empty($eligible_products)) {
            $errors[] = "No products in this order have automation enabled.";
            $errors[] = "Products in order: " . implode(', ', $ineligible_products);
            $errors[] = "To fix: Go to 'Top Up Agent → License Keys → Automation Settings' and select these products, OR edit each product → Product Data → Top Up Agent → Check 'Enable Automation'";
            $can_trigger = false;
        } else {
            $info[] = "✅ Automation-enabled products: " . implode(', ', $eligible_products);
        }
        
        if (!empty($products_without_redimension)) {
            $warnings[] = "Products missing redimension codes: " . implode(', ', $products_without_redimension);
            $warnings[] = "To fix: Edit product → Product Data → Top Up Agent → Set 'Redimension Code'";
        }
        
        // Check 3: Player ID Detection
        $player_id_sources = [];
        $configured_meta_key = get_option('top_up_agent_player_id_meta_key', 'player_id');
        
        // Method 1: Direct meta
        $player_id_meta = $order->get_meta('_player_id');
        if ($player_id_meta) {
            $player_id_sources[] = "_player_id meta: '{$player_id_meta}'";
        }
        
        // Method 2: Billing meta
        $player_id_billing = $order->get_meta('_billing_player_id');
        if ($player_id_billing) {
            $player_id_sources[] = "_billing_player_id meta: '{$player_id_billing}'";
        }
        
        // Method 3: Order item variation data using configured meta key
        foreach ($order->get_items() as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }
            
            // Check the exact configured meta key
            $player_id = $item->get_meta($configured_meta_key);
            if ($player_id) {
                $player_id_sources[] = "Item meta '{$configured_meta_key}': '{$player_id}' (from {$item->get_name()})";
            }
            
            // Check with underscore prefix
            $player_id = $item->get_meta('_' . $configured_meta_key);
            if ($player_id) {
                $player_id_sources[] = "Item meta '_{$configured_meta_key}': '{$player_id}' (from {$item->get_name()})";
            }
            
            // Check variation meta data
            $variation_data = $item->get_meta_data();
            foreach ($variation_data as $meta) {
                $key = $meta->get_data()['key'] ?? '';
                $value = $meta->get_data()['value'] ?? '';
                
                if ((strcasecmp($key, $configured_meta_key) === 0 || 
                     strcasecmp($key, '_' . $configured_meta_key) === 0) && $value) {
                    $player_id_sources[] = "Item variation '{$key}': '{$value}' (from {$item->get_name()})";
                }
            }
            
            // Check formatted meta data
            $formatted_meta = $item->get_formatted_meta_data();
            foreach ($formatted_meta as $meta_id => $meta) {
                if ((stripos($meta->display_key, str_replace('_', ' ', $configured_meta_key)) !== false ||
                     preg_match('/player[_\s]*id|uid|user[_\s]*id/i', $meta->display_key)) && 
                    $meta->display_value) {
                    $cleaned_value = trim(strip_tags($meta->display_value));
                    if ($cleaned_value) {
                        $player_id_sources[] = "Item field '{$meta->display_key}': '{$cleaned_value}' (from {$item->get_name()})";
                    }
                }
            }
        }
        
        // Method 4: Customer note
        $customer_note = $order->get_customer_note();
        if ($customer_note && preg_match('/player[_\s]*id[:\s]*([a-zA-Z0-9]+)/i', $customer_note, $matches)) {
            $player_id_sources[] = "Customer note: '{$matches[1]}' (extracted from: '{$customer_note}')";
        }
        
        // Method 5: Order notes
        $order_notes = wc_get_order_notes(['order_id' => $order_id, 'type' => 'customer']);
        foreach ($order_notes as $note) {
            if (preg_match('/player[_\s]*id[:\s]*([a-zA-Z0-9]+)/i', $note->content, $matches)) {
                $player_id_sources[] = "Order note: '{$matches[1]}' (from note: '" . substr($note->content, 0, 50) . "...')";
                break;
            }
        }
        
        if (empty($player_id_sources)) {
            $errors[] = "No Player ID found in order.";
            $errors[] = "Configured meta key: '{$configured_meta_key}' (check Settings → Player ID Meta Key)";
            $errors[] = "Player ID search locations: _player_id meta, _billing_player_id meta, item meta '{$configured_meta_key}', customer notes, order notes";
            $errors[] = "To fix: Add custom field '_player_id' with the player's game ID, or ensure the item has '{$configured_meta_key}' field, or add 'Player ID: 123456789' to customer notes";
            $can_trigger = false;
        } else {
            $info[] = "✅ Player ID found in: " . implode(', ', $player_id_sources);
            $info[] = "✅ Using configured meta key: '{$configured_meta_key}'";
        }
        
        // Check 4: License Key Availability (if we have eligible products)
        if (!empty($eligible_products)) {
            global $wpdb;
            $license_table = $wpdb->prefix . 'top_up_agent_license_keys';
            
            // Get enabled products from global settings
            $enabled_products_option = get_option('top_up_agent_products_automation_enabled', []);
            
            $product_ids = [];
            foreach ($order->get_items() as $item) {
                if (!$item instanceof WC_Order_Item_Product) continue;
                $product_id = $item->get_product_id();
                $variation_id = $item->get_variation_id();
                $check_id = $variation_id ? $variation_id : $product_id;
                
                // Check if product is enabled using NEW global settings system
                $automation_enabled_global = in_array($check_id, $enabled_products_option) || in_array($product_id, $enabled_products_option);
                
                // FALLBACK: Also check individual meta for backward compatibility
                $automation_enabled_meta = get_post_meta($check_id, '_automation_enabled', true) === 'yes';
                
                if ($automation_enabled_global || $automation_enabled_meta) {
                    $product_ids[] = $check_id;
                    // Also add parent product ID if we're checking a variation
                    if ($variation_id && !in_array($product_id, $product_ids)) {
                        $product_ids[] = $product_id;
                    }
                }
            }
            
            $available_keys = 0;
            $key_details = [];
            foreach ($product_ids as $product_id) {
                $key_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $license_table 
                     WHERE status = 'unused' 
                     AND (product_ids IS NULL OR product_ids = '' OR FIND_IN_SET(%d, product_ids) > 0)",
                    $product_id
                ));
                $key_count = (int)$key_count;
                $available_keys += $key_count;
                
                if ($key_count > 0) {
                    $key_details[] = "Product ID {$product_id}: {$key_count} keys";
                }
            }
            
            if ($available_keys === 0) {
                $errors[] = "No unused license keys available for the automation-enabled products.";
                $errors[] = "To fix: Go to Top Up Agent → License Keys → Add license keys for these products";
                if (!empty($key_details)) {
                    $info[] = "Available keys found: " . implode(', ', $key_details);
                }
                $can_trigger = false;
            } else {
                $info[] = "✅ {$available_keys} unused license key(s) available for automation.";
                if (!empty($key_details)) {
                    $info[] = "Key distribution: " . implode(', ', $key_details);
                }
            }
        }
        
        // Check 5: API Connection
        $server_url = get_option('top_up_agent_server_url', '');
        $api_key = get_option('top_up_agent_api_key', '');
        
        if (!$server_url) {
            $errors[] = "Server URL not configured.";
            $errors[] = "To fix: Go to Top Up Agent → Settings → Set 'Server URL'";
            $can_trigger = false;
        } else {
            $info[] = "✅ Server URL configured: {$server_url}";
        }
        
        if (!$api_key) {
            $errors[] = "API Key not configured.";
            $errors[] = "To fix: Go to Top Up Agent → Settings → Set 'API Key'";
            $can_trigger = false;
        } else {
            $info[] = "✅ API Key configured: " . substr($api_key, 0, 8) . "...";
        }
        
        // Check 6: Previous automation attempts
        $automation_already_handled = $this->is_automation_already_handled($order_id);
        if ($automation_already_handled) {
            $warnings[] = "This order has already been processed by automation.";
            $warnings[] = "Check the order notes and automation status above for details.";
        }
        
        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'info' => $info,
            'can_trigger' => $can_trigger && !$automation_already_handled
        ];
    }

    /**
     * Add automation completion indicator to order actions
     * 
     * @param int $order_id
     */
    public function add_automation_completion_indicator($order_id) {
        $automation_completed = get_post_meta($order_id, '_automation_completed', true);
        
        if ($automation_completed === 'yes') {
            echo '<div class="top-up-agent-completion-indicator" style="
                background: linear-gradient(135deg, var(--wp-admin-theme-color, #2271b1) 0%, #135e96 100%);
                color: white;
                padding: 8px 12px;
                border-radius: 6px;
                margin: 8px 0;
                font-size: 12px;
                font-weight: 600;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            ">
                <span style="font-size: 14px;">🎮</span>
                <span>Top-Up Automation Completed</span>
            </div>';
        }
    }

    /**
     * Handle delayed order completion
     * 
     * @param int $order_id
     */
    public function delayed_order_completion($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Double-check automation is still completed
        $automation_completed = get_post_meta($order_id, '_automation_completed', true);
        
        if ($automation_completed === 'yes' && $order->get_status() !== 'completed') {
            $order->update_status('completed', 
                '🎮 Order automatically completed after successful automation (delayed processing)');
            
            error_log("Top Up Agent: Order #{$order_id} completed via delayed processing");
        }
    }

    /**
     * Handle automation completion and potentially auto-complete order
     * 
     * @param int $order_id
     * @param array $queue_status
     */
    public function handle_automation_completion($order_id, $queue_status) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Check if order should be auto-completed (could be a setting in the future)
        $auto_complete_enabled = apply_filters('top_up_agent_auto_complete_orders', true);
        
        if ($auto_complete_enabled && $order->get_status() === str_replace('wc-', '', self::STATUS_AUTOMATION_COMPLETED)) {
            // Delay the completion slightly to ensure all hooks are processed
            wp_schedule_single_event(time() + 2, 'top_up_agent_delayed_order_completion', array($order_id));
        }
    }

    /**
     * Automatically complete orders when automation is finished
     * 
     * @param int $order_id
     * @param string $from_status
     * @param string $to_status
     * @param WC_Order $order
     */
    public function auto_complete_automation_orders($order_id, $from_status, $to_status, $order) {
        // Only process if order status changed to automation-completed
        if ($to_status !== self::STATUS_AUTOMATION_COMPLETED) {
            return;
        }

        // Check if automation is marked as completed
        $automation_completed = get_post_meta($order_id, '_automation_completed', true);
        
        if ($automation_completed === 'yes') {
            // Change order status to completed
            $order->update_status('completed', 
                '🎮 Order automatically completed after successful automation');
            
            // Add order note for transparency
            $order->add_order_note(
                'Top-Up Agent: Order automatically marked as completed following successful automation completion.',
                false, // not customer note
                true   // added by system
            );
            
            // Log the auto-completion
            error_log("Top Up Agent: Order #{$order_id} automatically completed after automation success");
        }
    }
}