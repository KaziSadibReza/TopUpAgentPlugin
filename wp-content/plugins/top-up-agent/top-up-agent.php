<?php
/**
 * Plugin Name: Top Up Agent
 * Plugin URI: https://github.com/KaziSadibReza/TopUpAgentPlugin
 * Description: Advanced automation plugin with license management, WooCommerce integration, and comprehensive automation features.
 * Version: 2.0.0
 * Author: Kazi Sadib Reza
 * Author URI: https://github.com/KaziSadibReza
 * Requires PHP: 7.4
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 * Network: false
 * Text Domain: top-up-agent
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

// Autoload Composer dependencies
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Load asset downloader class
require_once __DIR__ . '/includes/core/class-asset-downloader.php';

// Plugin activation hook
register_activation_hook(__FILE__, 'top_up_agent_activate');
function top_up_agent_activate() {
	global $wpdb;
	
	// Download required assets during activation
	\TopUpAgent\Core\AssetDownloader::downloadOnActivation();
	
	// Create license keys table
	$license_table = $wpdb->prefix . 'top_up_agent_license_keys';
	$wpdb->query("CREATE TABLE IF NOT EXISTS $license_table (
		id int(11) NOT NULL AUTO_INCREMENT,
		license_key varchar(255) NOT NULL,
		product_ids text DEFAULT NULL,
		status enum('unused','used') DEFAULT 'unused',
		used_date datetime DEFAULT NULL,
		created_date datetime DEFAULT CURRENT_TIMESTAMP,
		is_group_product tinyint(1) DEFAULT 0,
		group_license_count int(11) DEFAULT 3,
		PRIMARY KEY (id),
		UNIQUE KEY license_key (license_key)
	)");
	
	// Add group columns to existing table (for existing installations)
	$wpdb->query("ALTER TABLE $license_table ADD COLUMN is_group_product tinyint(1) DEFAULT 0");
	$wpdb->query("ALTER TABLE $license_table ADD COLUMN group_license_count int(11) DEFAULT 3");
	
	// Create order automations table (legacy compatibility)
	$automation_table = $wpdb->prefix . 'top_up_agent_order_automations';
	$wpdb->query("CREATE TABLE IF NOT EXISTS $automation_table (
		id int(11) NOT NULL AUTO_INCREMENT,
		order_id int(11) NOT NULL,
		player_id varchar(255) DEFAULT NULL,
		license_key varchar(255) DEFAULT NULL,
		automation_status enum('pending','running','completed','failed') DEFAULT 'pending',
		automation_date datetime DEFAULT NULL,
		created_date datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY order_id (order_id)
	)");
}

// Load core classes
require_once plugin_dir_path(__FILE__) . 'includes/core/class-top-up-agent-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/core/class-top-up-agent-asset-handler.php';

// Load new API integration system
require_once plugin_dir_path(__FILE__) . 'includes/api-integration/class-api-client.php';
require_once plugin_dir_path(__FILE__) . 'includes/api-integration/class-websocket-integration.php';
require_once plugin_dir_path(__FILE__) . 'includes/api-integration/class-top-up-agent-webhook-handler.php';

// Load automation classes
require_once plugin_dir_path(__FILE__) . 'includes/automation/class-top-up-agent-automation-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/automation/class-top-up-agent-automation-database-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/automation/class-top-up-agent-player-id-detector.php';
require_once plugin_dir_path(__FILE__) . 'includes/automation/class-top-up-agent-product-eligibility-checker.php';

// Load license management classes
require_once plugin_dir_path(__FILE__) . 'includes/license-management/class-top-up-agent-license-key-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/license-management/class-top-up-agent-product-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/license-management/class-top-up-agent-license-keys-controller.php';

// Load UI classes
require_once plugin_dir_path(__FILE__) . 'includes/ui/class-top-up-agent-form-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/ui/class-top-up-agent-ui-renderer.php';

// Initialize admin
new Top_Up_Agent_Admin();

// Initialize Asset Handler
Top_Up_Agent_Asset_Handler::get_instance();

// Initialize WebSocket integration
new Top_Up_Agent_WebSocket_Integration();

// Initialize WooCommerce integration if WooCommerce is active
add_action('plugins_loaded', 'top_up_agent_init_woocommerce', 20); // Load after WooCommerce
function top_up_agent_init_woocommerce() {
	if (class_exists('WooCommerce')) {
		require_once plugin_dir_path(__FILE__) . 'includes/woocommerce/class-top-up-agent-woocommerce.php';
		
		// Initialize WooCommerce integration
		global $top_up_agent_woocommerce;
		$top_up_agent_woocommerce = new Top_Up_Agent_WooCommerce_Integration();
		
		// Debug log
		error_log('Top Up Agent: WooCommerce integration loaded successfully');
	} else {
		error_log('Top Up Agent: WooCommerce not detected - integration disabled');
	}
}

// Ensure WooCommerce hooks are ready
add_action('woocommerce_loaded', 'top_up_agent_woocommerce_ready', 10);
function top_up_agent_woocommerce_ready() {
	global $top_up_agent_woocommerce;
	if ($top_up_agent_woocommerce && class_exists('Top_Up_Agent_WooCommerce_Integration')) {
		error_log('Top Up Agent: WooCommerce hooks ready and integration active');
		
		// Test if our hooks are registered
		$hook_priority = has_action('woocommerce_order_status_processing', array($top_up_agent_woocommerce, 'handle_processing_order'));
		error_log('Top Up Agent: Processing hook registered with priority: ' . ($hook_priority !== false ? $hook_priority : 'NOT REGISTERED'));
	}
}

// Add debug hook to test order status changes
add_action('woocommerce_order_status_changed', 'top_up_agent_debug_status_change', 10, 4);
function top_up_agent_debug_status_change($order_id, $old_status, $new_status, $order) {
	error_log("Top Up Agent DEBUG: Order #$order_id status changed from '$old_status' to '$new_status'");
	
	if ($new_status === 'processing') {
		error_log("Top Up Agent DEBUG: Order #$order_id reached PROCESSING status - automation should trigger");
		
		// Check if our hook will fire
		global $top_up_agent_woocommerce;
		if ($top_up_agent_woocommerce) {
			error_log("Top Up Agent DEBUG: WooCommerce integration instance exists");
		} else {
			error_log("Top Up Agent DEBUG: WooCommerce integration instance NOT FOUND!");
		}
	}
}

// Add AJAX handler for real-time logs
add_action('wp_ajax_get_automation_logs', 'handle_get_automation_logs');
add_action('wp_ajax_nopriv_get_automation_logs', 'handle_get_automation_logs');

function handle_get_automation_logs() {
	// Verify nonce
	if (!wp_verify_nonce($_POST['nonce'], 'get_logs')) {
		wp_send_json_error('Invalid nonce');
		return;
	}
	
	// In a real implementation, you would fetch logs from your automation server
	// For demo purposes, we'll generate some sample log data
	$sample_logs = array();
	
	// Check if we have recent activity to log
	$current_time = current_time('timestamp');
	$minute = date('i', $current_time);
	
	// Generate different logs based on current minute (so they change over time)
	if ($minute % 5 == 0) {
		$sample_logs[] = array(
			'message' => '🔄 Queue status check completed',
			'type' => 'info'
		);
	}
	
	if ($minute % 3 == 0) {
		$sample_logs[] = array(
			'message' => '📊 Processing queue item #' . rand(1000, 9999),
			'type' => 'info'
		);
	}
	
	if ($minute % 7 == 0) {
		$sample_logs[] = array(
			'message' => '✅ Automation completed successfully',
			'type' => 'success'
		);
	}
	
	if ($minute % 10 == 0) {
		$sample_logs[] = array(
			'message' => '⚠️ Queue cleanup performed',
			'type' => 'warn'
		);
	}
	
	wp_send_json_success(array('logs' => $sample_logs));
}

// Add AJAX handlers for site-specific database operations
add_action('wp_ajax_check_site_database_support', 'handle_check_site_database_support');
add_action('wp_ajax_clear_site_database', 'handle_clear_site_database');
add_action('wp_ajax_debug_test', 'handle_debug_test');
add_action('wp_ajax_fix_api_key', 'handle_fix_api_key');
add_action('wp_ajax_debug_delete_payload', 'handle_debug_delete_payload');

// Debug delete payload handler
function handle_debug_delete_payload() {
	if (!wp_verify_nonce($_POST['nonce'], 'site_database_operations')) {
		wp_send_json_error('Invalid nonce');
		return;
	}
	
	$current_site = get_site_url();
	$future_date = date('Y-m-d\TH:i:s.v\Z', strtotime('+1 year'));
	
	$payload = [
		'confirmDelete' => true,
		'sourceSite' => $current_site,
		'olderThan' => $future_date
	];
	
	wp_send_json_success([
		'payload' => $payload,
		'endpoint' => '/api/history',
		'method' => 'DELETE',
		'server_url' => get_option('top_up_agent_server_url', 'https://server.uidtopupbd.com'),
		'api_key_set' => !empty(get_option('top_up_agent_api_key', ''))
	]);
}

// Fix API key handler
function handle_fix_api_key() {
	if (!wp_verify_nonce($_POST['nonce'], 'fix_api_key')) {
		wp_send_json_error('Invalid nonce');
		return;
	}
	
	$api_key = sanitize_text_field($_POST['api_key']);
	
	if (update_option('top_up_agent_api_key', $api_key)) {
		wp_send_json_success('API key updated successfully');
	} else {
		wp_send_json_error('Failed to update API key');
	}
}

// Simple debug test handler
function handle_debug_test() {
	wp_send_json_success(array(
		'message' => 'AJAX is working!',
		'timestamp' => current_time('mysql'),
		'site' => get_site_url(),
		'nonce_valid' => wp_verify_nonce($_POST['nonce'] ?? '', 'site_database_operations')
	));
}

function handle_check_site_database_support() {
	// Verify nonce
	if (!wp_verify_nonce($_POST['nonce'], 'site_database_operations')) {
		wp_send_json_error('Invalid nonce');
		return;
	}
	
	// Get API client
	$api_client = new Top_Up_Agent_API_Client();
	
	// Check site-specific support
	$result = $api_client->check_site_support();
	
	if (is_wp_error($result)) {
		wp_send_json_error('Failed to check server capabilities: ' . $result->get_error_message());
		return;
	}
	
	// Extract site info
	$current_site = get_site_url();
	$count = 0;
	
	if (isset($result['success']) && $result['success'] && isset($result['history'])) {
		$count = count($result['history']);
	} elseif (isset($result['pagination']['total'])) {
		$count = $result['pagination']['total'];
	}
	
	wp_send_json_success(array(
		'site' => $current_site,
		'count' => $count,
		'serverSupport' => isset($result['success']) && $result['success']
	));
}

function handle_clear_site_database() {
	// Verify nonce
	if (!wp_verify_nonce($_POST['nonce'], 'site_database_operations')) {
		wp_send_json_error('Invalid nonce');
		return;
	}
	
	// Get and validate site URL
	$site_url = sanitize_url($_POST['site_url'] ?? '');
	$current_site = get_site_url();
	
	// Debug: Log the site URLs
	error_log('WordPress site URL: ' . $current_site);
	error_log('Requested site URL: ' . $site_url);
	
	// Security check: Only allow clearing this site's data
	if ($site_url !== $current_site) {
		wp_send_json_error('Security violation: Site URL mismatch');
		return;
	}
	
	// Get API client
	$api_client = new Top_Up_Agent_API_Client();
	
	// Step 1: Clear site-specific history
	$history_result = $api_client->delete_site_history($current_site, true);
	
	if (is_wp_error($history_result)) {
		wp_send_json_error('Failed to clear site history: ' . $history_result->get_error_message());
		return;
	}
	
	// Step 2: Clear site-specific queue items
	$queue_result = $api_client->clear_site_queue($current_site, true);
	
	// Debug: Log the queue result
	error_log('Queue cleanup result: ' . json_encode($queue_result));
	
	if (is_wp_error($queue_result)) {
		wp_send_json_error('Failed to clear site queue: ' . $queue_result->get_error_message());
		return;
	}
	
	// Step 3: Clear all screenshots
	$screenshots_result = $api_client->clear_screenshots();
	
	if (is_wp_error($screenshots_result)) {
		wp_send_json_error('Failed to clear screenshots: ' . $screenshots_result->get_error_message());
		return;
	}
	
	// Log screenshot result for debugging
	error_log('Screenshot cleanup result: ' . json_encode($screenshots_result));
	
	// Extract deletion counts
	$history_deleted = 0;
	if (isset($history_result['deletedCount'])) {
		$history_deleted = $history_result['deletedCount'];
	} elseif (isset($history_result['deleted'])) {
		$history_deleted = $history_result['deleted'];
	}
	
	$queue_deleted = 0;
	if (isset($queue_result['deletedCount'])) {
		$queue_deleted = $queue_result['deletedCount'];
	} elseif (isset($queue_result['deleted'])) {
		$queue_deleted = $queue_result['deleted'];
	}
	
	$screenshots_deleted = 0;
	if (isset($screenshots_result['deletedCount'])) {
		$screenshots_deleted = $screenshots_result['deletedCount'];
	}
	
	$total_deleted = $history_deleted + $queue_deleted + $screenshots_deleted;
	
	wp_send_json_success(array(
		'deletedCount' => $total_deleted,
		'historyDeleted' => $history_deleted,
		'queueDeleted' => $queue_deleted,
		'screenshotsDeleted' => $screenshots_deleted,
		'site' => $current_site,
		'message' => "Complete cleanup successful: {$history_deleted} history + {$queue_deleted} queue + {$screenshots_deleted} screenshots deleted"
	));
}