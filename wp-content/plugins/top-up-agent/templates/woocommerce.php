<?php
/**
 * WooCommerce Orders Management with Automation Control
 * Complete order management with filtering, automation status, and manual retry
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get API client
$api = new Top_Up_Agent_API_Client();

// Get filter parameters
$status_filter = sanitize_text_field($_GET['status'] ?? 'all');
$automation_filter = sanitize_text_field($_GET['automation'] ?? 'all');
$search_query = sanitize_text_field($_GET['search'] ?? '');
$per_page = 20;
$page = max(1, intval($_GET['paged'] ?? 1));

// Define all available order statuses
$wc_statuses = [
    'all' => 'All Statuses',
    'pending' => 'Pending Payment',
    'processing' => 'Processing',
    'on-hold' => 'On Hold',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'refunded' => 'Refunded',
    'failed' => 'Failed',
    'automation-pending' => 'Automation Pending',
    'automation-processing' => 'Automation Processing',
    'automation-failed' => 'Automation Failed',
    'automation-completed' => 'Automation Completed'
];

// Define automation status filters
$automation_statuses = [
    'all' => 'All Automation States',
    'no-automation' => 'No Automation',
    'pending' => 'Pending',
    'running' => 'Running',
    'completed' => 'Completed',
    'failed' => 'Failed'
];

// Build order query
$order_args = [
    'limit' => $per_page,
    'offset' => ($page - 1) * $per_page,
    'orderby' => 'date',
    'order' => 'DESC',
    'type' => 'shop_order'
];

// Apply status filter
if ($status_filter !== 'all') {
    $order_args['status'] = $status_filter;
}

// Apply search filter
if (!empty($search_query)) {
    $order_args['meta_query'] = [
        'relation' => 'OR',
        [
            'key' => '_billing_email',
            'value' => $search_query,
            'compare' => 'LIKE'
        ],
        [
            'key' => '_billing_first_name',
            'value' => $search_query,
            'compare' => 'LIKE'
        ],
        [
            'key' => '_billing_last_name',
            'value' => $search_query,
            'compare' => 'LIKE'
        ]
    ];
}

// Get orders
$orders = wc_get_orders($order_args);
$total_orders = wc_get_orders(array_merge($order_args, ['limit' => -1, 'return' => 'ids']));
$total_pages = ceil(count($total_orders) / $per_page);

// Get current queue data for automation status mapping
$current_queue = $api->get_recent_queue_items();
$queue_map = [];

if (!is_wp_error($current_queue) && isset($current_queue['items'])) {
    foreach ($current_queue['items'] as $queue_item) {
        if (isset($queue_item['order_id']) && $queue_item['order_id']) {
            $order_id = $queue_item['order_id'];
            if (!isset($queue_map[$order_id])) {
                $queue_map[$order_id] = [];
            }
            $queue_map[$order_id][] = $queue_item;
        }
    }
}

// Get recent automation results for username extraction
$recent_results = $api->get_results(1, 100); // Get up to 100 recent results
$player_usernames = [];

// Extract player usernames from automation results metadata
if (!is_wp_error($recent_results) && isset($recent_results['results'])) {
    foreach ($recent_results['results'] as $result) {
        $player_id = $result['player_id'] ?? null;
        if ($player_id) {
            // Extract username from metadata
            if (isset($result['metadata'])) {
                $metadata = is_string($result['metadata']) ? json_decode($result['metadata'], true) : $result['metadata'];
                if (is_array($metadata) && isset($metadata['username'])) {
                    $player_usernames[$player_id] = $metadata['username'];
                }
            }
        }
    }
}

// Process orders and add automation data
$processed_orders = [];
foreach ($orders as $order) {
    $order_id = $order->get_id();
    $order_status = $order->get_status();
    $queue_items = $queue_map[$order_id] ?? [];
    
    // Determine automation status
    $automation_status = null;
    if (in_array($order_status, ['automation-pending', 'automation-processing', 'automation-failed', 'automation-completed'])) {
        $automation_status = [
            'source' => 'woocommerce',
            'status' => str_replace('automation-', '', $order_status),
            'items_count' => count($queue_items)
        ];
    } else if (!empty($queue_items)) {
        $queue_statuses = array_column($queue_items, 'status');
        if (in_array('running', $queue_statuses)) {
            $status = 'running';
        } else if (in_array('failed', $queue_statuses)) {
            $status = 'failed';
        } else if (in_array('pending', $queue_statuses)) {
            $status = 'pending';
        } else {
            $status = 'completed';
        }
        
        $automation_status = [
            'source' => 'queue',
            'status' => $status,
            'items_count' => count($queue_items)
        ];
    }
    
    // Apply automation filter
    if ($automation_filter !== 'all') {
        if ($automation_filter === 'no-automation' && $automation_status) {
            continue;
        } else if ($automation_filter !== 'no-automation' && (!$automation_status || $automation_status['status'] !== $automation_filter)) {
            continue;
        }
    }
    
    $processed_orders[] = [
        'id' => $order_id,
        'order' => $order,
        'status' => $order_status,
        'date' => $order->get_date_created(),
        'total' => $order->get_total(),
        'customer' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) ?: 'Guest',
        'email' => $order->get_billing_email(),
        'items' => $order->get_items(),
        'automation_status' => $automation_status,
        'queue_items' => $queue_items
    ];
}

// Handle manual automation retry
if (isset($_POST['action']) && $_POST['action'] === 'retry_automation' && wp_verify_nonce($_POST['nonce'], 'retry_automation')) {
    $retry_order_id = intval($_POST['order_id']);
    $retry_order = wc_get_order($retry_order_id);
    
    if (!$retry_order) {
        echo '<div class="notice notice-error"><p>❌ Order #' . $retry_order_id . ' not found!</p></div>';
    } else {
        // Get existing error information before retry
        $previous_error = get_post_meta($retry_order_id, '_automation_error', true);
        $automation_status = get_post_meta($retry_order_id, '_automation_status', true);
        
        try {
            // Use the existing WooCommerce integration retry method
            $woocommerce_integration = new Top_Up_Agent_WooCommerce_Integration();
            
            // Clear previous automation metadata (same as ajax_retry_automation does)
            delete_post_meta($retry_order_id, '_automation_status');
            delete_post_meta($retry_order_id, '_automation_error');
            delete_post_meta($retry_order_id, '_automation_queue_id');
            delete_post_meta($retry_order_id, '_automation_queue_ids');
            delete_post_meta($retry_order_id, '_automation_started');
            delete_post_meta($retry_order_id, '_automation_completed');
            delete_post_meta($retry_order_id, '_automation_failed');
            
            // Trigger retry using the existing method
            $result = $woocommerce_integration->handle_processing_order($retry_order_id);
            
            if ($result) {
                echo '<div class="notice notice-success">
                    <p><strong>✅ Automation retry triggered successfully for Order #' . $retry_order_id . '</strong></p>
                    <p>New automation process started. The page will refresh to show updated status.</p>
                </div>';
                
                // Auto-refresh to show new status
                echo '<script>setTimeout(function(){ location.reload(); }, 2000);</script>';
            } else {
                // Get the new error after retry attempt
                $new_error = get_post_meta($retry_order_id, '_automation_error', true);
                $error_to_show = $new_error ?: $previous_error ?: 'Unknown retry failure';
                
                echo '<div class="notice notice-error">
                    <p><strong>❌ Failed to retry automation for Order #' . $retry_order_id . '</strong></p>
                    <p><strong>Error:</strong> ' . esc_html($error_to_show) . '</p>
                    <p><strong>Suggestion:</strong> Check if the order has eligible products and valid player ID.</p>
                </div>';
            }
        } catch (Exception $e) {
            echo '<div class="notice notice-error">
                <p><strong>❌ Exception during retry for Order #' . $retry_order_id . '</strong></p>
                <p><strong>Error:</strong> ' . esc_html($e->getMessage()) . '</p>
            </div>';
        }
    }
}
?>

<div class="wrap">
    <h1>🛒 WooCommerce Orders Management</h1>

    <!-- Server Data Screenshot Section -->
    <div class="postbox" style="margin-bottom: 20px;">
        <h3 style="padding: 10px 15px; margin: 0; background: #f1f1f1; border-bottom: 1px solid #ddd;">
            🌐 Server Database - Products & Screenshots Overview
        </h3>
        <div style="padding: 15px;">
            <p>Click the button below to fetch and display server database information organized by product, including
                screenshots, player usernames, and automation results.</p>
            <button type="button" id="fetch-server-data" class="button button-primary" style="margin-right: 10px;">
                🔄 Fetch Server Data
            </button>
            <button type="button" id="toggle-server-data" class="button" style="display: none;">
                👁️ Toggle Display
            </button>
            <div id="server-data-container" style="margin-top: 15px; display: none;">
                <div id="server-data-loading" style="text-align: center; padding: 20px; display: none;">
                    ⏳ Fetching server data...
                </div>
                <div id="server-data-content"></div>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="tablenav top">
        <form method="GET" action="">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">

            <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 20px;">
                <!-- Order Status Filter -->
                <select name="status" id="status-filter">
                    <?php foreach ($wc_statuses as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($status_filter, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <!-- Automation Status Filter -->
                <select name="automation" id="automation-filter">
                    <?php foreach ($automation_statuses as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($automation_filter, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <!-- Search -->
                <input type="text" name="search" placeholder="Search customer name or email..."
                    value="<?php echo esc_attr($search_query); ?>" style="width: 250px;">

                <!-- Submit -->
                <input type="submit" class="button" value="Filter">

                <!-- Reset -->
                <a href="?page=<?php echo esc_attr($_GET['page']); ?>" class="button">Reset</a>
            </div>
        </form>
    </div>

    <!-- Summary Stats -->
    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
        <div class="postbox" style="padding: 15px; min-width: 200px;">
            <h3>📊 Summary</h3>
            <p><strong>Total Orders:</strong> <?php echo count($processed_orders); ?></p>
            <p><strong>Page:</strong> <?php echo $page; ?> of <?php echo $total_pages; ?></p>
        </div>
    </div>

    <!-- Orders Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Total</th>
                <th>Order Status</th>
                <th>Automation Status</th>
                <th>Items</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($processed_orders)): ?>
            <tr>
                <td colspan="8" style="text-align: center; padding: 40px;">
                    <strong>No orders found matching your criteria.</strong>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($processed_orders as $order_data): ?>
            <?php 
                    $order = $order_data['order'];
                    $automation = $order_data['automation_status'];
                    $queue_items = $order_data['queue_items'];
                    ?>
            <tr>
                <!-- Order # -->
                <td>
                    <strong>#<?php echo $order->get_id(); ?></strong>
                    <br><small><?php echo $order->get_order_key(); ?></small>
                </td>

                <!-- Customer -->
                <td>
                    <strong><?php echo esc_html($order_data['customer']); ?></strong>
                    <br><small><?php echo esc_html($order_data['email']); ?></small>
                </td>

                <!-- Date -->
                <td>
                    <?php echo $order_data['date']->format('M j, Y'); ?>
                    <br><small><?php echo $order_data['date']->format('H:i:s'); ?></small>
                </td>

                <!-- Total -->
                <td>
                    <strong>$<?php echo number_format($order_data['total'], 2); ?></strong>
                </td>

                <!-- Order Status -->
                <td>
                    <?php
                            $status_colors = [
                                'pending' => '#ffba00',
                                'processing' => '#007cba',
                                'completed' => '#00a32a',
                                'cancelled' => '#666',
                                'failed' => '#d63638',
                                'automation-pending' => '#ffba00',
                                'automation-processing' => '#007cba',
                                'automation-failed' => '#d63638',
                                'automation-completed' => '#00a32a'
                            ];
                            $color = $status_colors[$order_data['status']] ?? '#666';
                            ?>
                    <span
                        style="background: <?php echo $color; ?>; color: white; padding: 4px 8px; border-radius: 3px; font-size: 12px;">
                        <?php echo ucwords(str_replace(['-', '_'], ' ', $order_data['status'])); ?>
                    </span>
                </td>

                <!-- Automation Status -->
                <td>
                    <?php if ($automation): ?>
                    <?php
                                $auto_colors = [
                                    'pending' => '#ffba00',
                                    'running' => '#007cba',
                                    'processing' => '#007cba',
                                    'completed' => '#00a32a',
                                    'failed' => '#d63638'
                                ];
                                $auto_color = $auto_colors[$automation['status']] ?? '#666';
                                $icons = [
                                    'pending' => '⏳',
                                    'running' => '🔄',
                                    'processing' => '🔄',
                                    'completed' => '✅',
                                    'failed' => '❌'
                                ];
                                $icon = $icons[$automation['status']] ?? '❓';
                                ?>
                    <span
                        style="background: <?php echo $auto_color; ?>; color: white; padding: 4px 8px; border-radius: 3px; font-size: 12px;">
                        <?php echo $icon; ?> <?php echo ucfirst($automation['status']); ?>
                    </span>
                    <br><small><?php echo $automation['items_count']; ?> queue items</small>

                    <?php if ($automation['status'] === 'failed' && !empty($queue_items)): ?>
                    <br><small style="color: #d63638;">
                        Last Error:
                        <?php echo esc_html(substr($queue_items[0]['error_message'] ?? 'Unknown error', 0, 50)); ?>...
                    </small>
                    <?php endif; ?>
                    <?php else: ?>
                    <span
                        style="background: #666; color: white; padding: 4px 8px; border-radius: 3px; font-size: 12px;">
                        ➖ No Automation
                    </span>
                    <?php endif; ?>
                </td>

                <!-- Items -->
                <td>
                    <?php foreach ($order->get_items() as $item): ?>
                    <div style="margin-bottom: 5px;">
                        <strong><?php echo esc_html($item->get_name()); ?></strong>
                    </div>
                    <?php endforeach; ?>
                </td>

                <!-- Actions -->
                <td>
                    <div style="display: flex; flex-direction: column; gap: 5px;">
                        <!-- View Order -->
                        <a href="<?php echo admin_url('post.php?post=' . $order->get_id() . '&action=edit'); ?>"
                            class="button button-small">
                            👁️ View Order
                        </a>

                        <!-- Retry Automation (if failed or no automation) -->
                        <?php if (!$automation || in_array($automation['status'], ['failed', 'pending'])): ?>
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="action" value="retry_automation">
                            <input type="hidden" name="order_id" value="<?php echo $order->get_id(); ?>">
                            <input type="hidden" name="nonce"
                                value="<?php echo wp_create_nonce('retry_automation'); ?>">
                            <button type="submit" class="button button-small"
                                onclick="return confirm('Retry automation for Order #<?php echo $order->get_id(); ?>?')"
                                style="background: #ff6b35; color: white;">
                                🔄 Retry Automation
                            </button>
                        </form>
                        <?php endif; ?>

                        <!-- View Queue Details (if has queue items) -->
                        <?php if (!empty($queue_items)): ?>
                        <button type="button" class="button button-small"
                            onclick="showQueueDetails(<?php echo $order->get_id(); ?>)">
                            📋 Queue Details
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>

            <!-- Hidden Queue Details Row -->
            <?php if (!empty($queue_items)): ?>
            <tr id="queue-details-<?php echo $order->get_id(); ?>" style="display: none; background: #f9f9f9;">
                <td colspan="8">
                    <div style="padding: 15px;">
                        <h4>🔍 Queue Items for Order #<?php echo $order->get_id(); ?></h4>
                        <table class="widefat" style="margin-top: 10px;">
                            <thead>
                                <tr>
                                    <th>Queue ID</th>
                                    <th>License Key</th>
                                    <th>Player Info</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Error</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($queue_items as $queue_item): ?>
                                <tr>
                                    <td><strong>#<?php echo esc_html($queue_item['id'] ?? 'N/A'); ?></strong></td>
                                    <td><code><?php echo esc_html(substr($queue_item['license_key'] ?? 'N/A', 0, 20)); ?>...</code>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($queue_item['player_id'] ?? 'N/A'); ?></strong>
                                        <?php 
                                        $player_id = $queue_item['player_id'] ?? null;
                                        if ($player_id && isset($player_usernames[$player_id])): 
                                        ?>
                                        <br><small style="color: #0073aa; font-weight: 500;">
                                            👤 <?php echo esc_html($player_usernames[$player_id]); ?>
                                        </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span
                                            style="background: <?php echo $auto_colors[$queue_item['status']] ?? '#666'; ?>; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                            <?php echo ucfirst($queue_item['status'] ?? 'unknown'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, H:i', strtotime($queue_item['created_at'] ?? '')); ?></td>
                                    <td style="color: #d63638; font-size: 12px;">
                                        <?php echo esc_html(substr($queue_item['error_message'] ?? '', 0, 50)); ?>
                                        <?php if (strlen($queue_item['error_message'] ?? '') > 50): ?>...<?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
                $pagination_args = [
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'current' => $page,
                    'total' => $total_pages,
                    'prev_text' => '« Previous',
                    'next_text' => 'Next »'
                ];
                echo paginate_links($pagination_args);
                ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function showQueueDetails(orderId) {
    const row = document.getElementById('queue-details-' + orderId);
    if (row.style.display === 'none') {
        row.style.display = 'table-row';
    } else {
        row.style.display = 'none';
    }
}

// Auto-refresh every 30 seconds to show latest automation status
setInterval(function() {
    if (document.querySelector('[style*="running"], [style*="processing"]')) {
        location.reload();
    }
}, 30000);

// Server Data Screenshot Functionality
document.addEventListener('DOMContentLoaded', function() {
    const fetchButton = document.getElementById('fetch-server-data');
    const toggleButton = document.getElementById('toggle-server-data');
    const container = document.getElementById('server-data-container');
    const loading = document.getElementById('server-data-loading');
    const content = document.getElementById('server-data-content');

    fetchButton.addEventListener('click', function() {
        // Show loading state
        container.style.display = 'block';
        loading.style.display = 'block';
        content.innerHTML = '';
        fetchButton.disabled = true;
        fetchButton.textContent = '⏳ Fetching...';

        // Make AJAX request to fetch server data
        fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'fetch_server_data_screenshot',
                    nonce: '<?php echo wp_create_nonce('server_data_screenshot'); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                loading.style.display = 'none';

                if (data.success) {
                    content.innerHTML = formatServerData(data.data);
                    toggleButton.style.display = 'inline-block';
                } else {
                    content.innerHTML = '<div class="error-message">❌ Error: ' + (data.data ||
                        'Failed to fetch server data') + '</div>';
                }
            })
            .catch(error => {
                loading.style.display = 'none';
                content.innerHTML = '<div class="error-message">❌ Connection error: ' + error
                    .message + '</div>';
            })
            .finally(() => {
                fetchButton.disabled = false;
                fetchButton.textContent = '🔄 Fetch Server Data';
            });
    });

    toggleButton.addEventListener('click', function() {
        if (container.style.display === 'none') {
            container.style.display = 'block';
            toggleButton.textContent = '🙈 Hide Data';
        } else {
            container.style.display = 'none';
            toggleButton.textContent = '👁️ Show Data';
        }
    });

    function formatServerData(data) {
        let html = '<div class="server-data-grid">';

        // Products Data Section (New Primary Display)
        if (data.products_data) {
            html += '<div class="data-section">';
            html += '<h4>�️ Products Database Overview</h4>';

            const products = Object.values(data.products_data);
            if (products.length > 0) {
                products.forEach(product => {
                    html +=
                        '<div class="product-section" style="border: 1px solid #ddd; margin: 15px 0; padding: 15px; border-radius: 5px;">';
                    html += '<h5 style="margin: 0 0 10px 0; color: #2271b1;">📦 ' + (product
                        .product_name || 'Unknown Product') + ' (ID: ' + (product.product_id ||
                        'N/A') + ')</h5>';

                    // Product Statistics
                    html +=
                        '<div class="product-stats" style="background: #f9f9f9; padding: 10px; margin: 10px 0; border-radius: 3px;">';
                    html +=
                        '<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">';
                    html += '<div><strong>Queue Items:</strong> ' + (product.total_queue || 0) +
                        '</div>';
                    html += '<div><strong>Results:</strong> ' + (product.total_results || 0) + '</div>';
                    html += '<div><strong>Successful:</strong> ' + (product.successful_results || 0) +
                        '</div>';
                    html += '<div><strong>Failed:</strong> ' + (product.failed_results || 0) + '</div>';
                    html += '<div><strong>Screenshots:</strong> ' + (product.screenshots ? product
                        .screenshots.length : 0) + '</div>';
                    html += '<div><strong>Players:</strong> ' + (product.players ? Object.keys(product
                        .players).length : 0) + '</div>';
                    html += '</div>';
                    html += '</div>';

                    // Screenshots Section
                    if (product.screenshots && product.screenshots.length > 0) {
                        html += '<div class="screenshots-section" style="margin: 15px 0;">';
                        html += '<h6 style="margin: 0 0 10px 0;">📸 Screenshots (' + product.screenshots
                            .length + ')</h6>';
                        html +=
                            '<div class="screenshots-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px;">';

                        product.screenshots.slice(0, 6).forEach(
                            screenshot => { // Limit to first 6 screenshots
                                html +=
                                    '<div class="screenshot-item" style="border: 1px solid #ddd; padding: 10px; border-radius: 3px; background: white;">';

                                // Handle screenshot display with server URL from settings
                                if (screenshot.path && screenshot.path.trim() !== '') {
                                    let imageSrc = screenshot.path;
                                    const serverUrl =
                                        '<?php echo esc_js(rtrim(get_option('top_up_agent_server_url', 'https://server.uidtopupbd.com'), '/')); ?>';

                                    // Convert server local paths to web accessible URLs
                                    if (screenshot.path.includes('screenshots/') || screenshot.path
                                        .includes('screenshots\\')) {
                                        // Extract filename from path
                                        let filename = screenshot.path.split(/[\\\/]/).pop();
                                        // Use configured server URL with /api/screenshots/ endpoint
                                        imageSrc = serverUrl + '/api/screenshots/' + filename;
                                    } else if (!screenshot.path.startsWith('http')) {
                                        // For other local paths, try to make them web accessible
                                        let filename = screenshot.path.split(/[\\\/]/).pop();
                                        imageSrc = serverUrl + '/api/screenshots/' + filename;
                                    }

                                    html +=
                                        '<div class="screenshot-container" style="position: relative;">';

                                    // Try to load image first, but have better fallback
                                    html += '<img src="' + imageSrc +
                                        '" alt="Screenshot" class="screenshot-image" style="width: 100%; height: 120px; object-fit: cover; border-radius: 3px; margin-bottom: 5px; display: block;" onload="this.nextElementSibling.style.display=\'none\'; this.style.display=\'block\';" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'block\';">';

                                    // Enhanced fallback with screenshot info (hidden by default)
                                    html +=
                                        '<div class="screenshot-fallback" style="display: none; text-align: center; padding: 20px; background: #f5f5f5; color: #666; border-radius: 3px; border: 2px dashed #ccc;">';
                                    html +=
                                        '<div style="font-size: 24px; margin-bottom: 10px;">📸</div>';
                                    html +=
                                        '<div style="font-weight: bold; margin-bottom: 8px;">Screenshot Available</div>';
                                    html +=
                                        '<div style="font-size: 11px; color: #888; margin-bottom: 8px;">File: ' +
                                        (screenshot.path.split(/[\\\/]/).pop() || 'N/A') + '</div>';
                                    html +=
                                        '<div style="font-size: 10px; color: #999;">Image failed to load - click to view directly</div>';
                                    html += '<a href="' + imageSrc +
                                        '" target="_blank" style="margin-top: 8px; padding: 4px 8px; font-size: 10px; background: #2271b1; color: white; border: none; border-radius: 3px; cursor: pointer; text-decoration: none; display: inline-block;">🔗 View Image</a>';
                                    html += '<button onclick="copyToClipboard(\'' + imageSrc +
                                        '\')" style="margin-top: 8px; margin-left: 5px; padding: 4px 8px; font-size: 10px; background: #46b450; color: white; border: none; border-radius: 3px; cursor: pointer;">📋 Copy URL</button>';
                                    html += '</div>';
                                    html += '</div>';
                                } else {
                                    html +=
                                        '<div style="text-align: center; padding: 30px; background: #f5f5f5; color: #666; border-radius: 3px;">';
                                    html += '<div>� No screenshot path</div>';
                                    html +=
                                        '<small style="font-size: 10px; color: #999;">Path is empty or null</small>';
                                    html += '</div>';
                                }

                                html += '<div style="font-size: 12px;">';
                                html += '<div><strong>Player:</strong> ' + (screenshot.player_id ||
                                    'N/A') + '</div>';
                                html += '<div><strong>Status:</strong> ' + (screenshot.success ===
                                    true ? '✅ Success' : screenshot.success === false ?
                                    '❌ Failed' : '❓ Unknown') + '</div>';
                                if (screenshot.timestamp) {
                                    html += '<div><strong>Time:</strong> ' + new Date(screenshot
                                        .timestamp).toLocaleString() + '</div>';
                                }
                                html += '<div style="word-break: break-all; margin-top: 5px;">';
                                html += '<strong>Original Path:</strong><br>';
                                html +=
                                    '<code style="font-size: 9px; background: #f0f0f0; padding: 2px; border-radius: 2px;">' +
                                    (screenshot.path || 'N/A') + '</code>';
                                html += '</div>';
                                if (screenshot.path && !screenshot.path.startsWith('http')) {
                                    const serverUrl =
                                        '<?php echo esc_js(rtrim(get_option('top_up_agent_server_url', 'https://server.uidtopupbd.com'), '/')); ?>';
                                    let filename = screenshot.path.split(/[\\\/]/).pop();
                                    html += '<div style="margin-top: 3px;">';
                                    html += '<strong>Web URL:</strong><br>';
                                    html +=
                                        '<code style="font-size: 9px; background: #e8f4f8; padding: 2px; border-radius: 2px;">' +
                                        serverUrl + '/api/screenshots/' + filename + '</code>';
                                    html +=
                                        '<div style="font-size: 8px; color: #999; margin-top: 2px;">Note: Server needs /api/screenshots/ endpoint</div>';
                                    html += '</div>';
                                }
                                html += '</div>';
                                html += '</div>';
                            });

                        if (product.screenshots.length > 6) {
                            html +=
                                '<div style="text-align: center; padding: 20px; color: #666;">... and ' +
                                (product.screenshots.length - 6) + ' more screenshots</div>';
                        }

                        html += '</div>';
                        html += '</div>';
                    }

                    // Players Section
                    if (product.players && Object.keys(product.players).length > 0) {
                        html += '<div class="players-section" style="margin: 15px 0;">';
                        html += '<h6 style="margin: 0 0 10px 0;">👤 Players (' + Object.keys(product
                            .players).length + ')</h6>';
                        html +=
                            '<div class="players-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px;">';

                        Object.values(product.players).forEach(player => {
                            html +=
                                '<div class="player-item" style="border: 1px solid #ddd; padding: 10px; border-radius: 3px; background: white;">';
                            html += '<div><strong>Player ID:</strong> <code>' + (player
                                .player_id || 'N/A') + '</code></div>';
                            if (player.order_id) {
                                html += '<div><strong>Order ID:</strong> ' + player.order_id +
                                    '</div>';
                            }
                            html += '<div><strong>Status:</strong> <span class="status-' + (
                                player.status || 'unknown') + '">' + (player.status ||
                                'Unknown') + '</span></div>';

                            if (player.last_result) {
                                html +=
                                    '<div style="margin-top: 5px; padding-top: 5px; border-top: 1px solid #eee;">';
                                html += '<div><strong>Last Result:</strong> ' + (player
                                    .last_result.success === true ? '✅ Success' : player
                                    .last_result.success === false ? '❌ Failed' :
                                    '❓ Unknown') + '</div>';
                                if (player.last_result.has_screenshot) {
                                    html += '<div>📸 Has Screenshot</div>';
                                }
                                if (player.last_result.timestamp) {
                                    html += '<div><small>' + new Date(player.last_result
                                        .timestamp).toLocaleString() + '</small></div>';
                                }
                                html += '</div>';
                            }
                            html += '</div>';
                        });

                        html += '</div>';
                        html += '</div>';
                    }

                    html += '</div>'; // End product section
                });
            } else {
                html += '<p class="no-data">No product data found</p>';
            }
            html += '</div>';
        }

        // Server Stats Section
        if (data.server_stats) {
            html += '<div class="data-section">';
            html += '<h4>📈 Overall Server Statistics</h4>';
            html +=
                '<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">';

            const stats = data.server_stats;
            html += '<div class="stat-item"><strong>Total Queue Items:</strong> ' + (stats.total_queue || 0) +
                '</div>';
            html += '<div class="stat-item"><strong>Pending Items:</strong> ' + (stats.pending_queue || 0) +
                '</div>';
            html += '<div class="stat-item"><strong>Running Items:</strong> ' + (stats.running_queue || 0) +
                '</div>';
            html += '<div class="stat-item"><strong>Total Results:</strong> ' + (stats.total_results || 0) +
                '</div>';
            html += '<div class="stat-item"><strong>Successful:</strong> ' + (stats.successful_results || 0) +
                '</div>';
            html += '<div class="stat-item"><strong>Failed:</strong> ' + (stats.failed_results || 0) + '</div>';

            html += '</div>';
            html += '</div>';
        }

        // Database Stats Section (if available from server)
        if (data.database_stats) {
            html += '<div class="data-section">';
            html += '<h4>💾 Database Statistics</h4>';
            html +=
                '<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">';

            const dbStats = data.database_stats;
            if (dbStats.tables) {
                Object.keys(dbStats.tables).forEach(tableName => {
                    const tableInfo = dbStats.tables[tableName];
                    html += '<div class="stat-item">';
                    html += '<strong>' + tableName + ':</strong> ' + (tableInfo.count || 0) +
                        ' records';
                    if (tableInfo.size) {
                        html += '<br><small>Size: ' + tableInfo.size + '</small>';
                    }
                    html += '</div>';
                });
            }

            html += '</div>';
            html += '</div>';
        }

        html += '</div>'; // Close server-data-grid
        return html;
    }

    // Copy to clipboard function for screenshot URLs
    window.copyToClipboard = function(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('URL copied to clipboard!');
        }, function(err) {
            console.error('Could not copy text: ', err);
            // Fallback for older browsers
            const textArea = document.createElement("textarea");
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            alert('URL copied to clipboard!');
        });
    };
});
</script>

<style>
.wp-list-table th,
.wp-list-table td {
    vertical-align: top;
}

.button-small {
    font-size: 11px !important;
    height: auto !important;
    padding: 3px 8px !important;
}

.widefat .column-order_status,
.widefat .column-automation_status {
    width: 120px;
}

.notice {
    margin: 20px 0;
}

/* Server Data Screenshot Styles */
.server-data-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
}

.data-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 5px;
    overflow: hidden;
}

.data-section h4 {
    margin: 0;
    padding: 12px 15px;
    background: #f8f9fa;
    border-bottom: 1px solid #ddd;
    font-size: 14px;
    font-weight: 600;
}

.data-table-container {
    max-height: 400px;
    overflow-y: auto;
}

.data-table-container table {
    margin: 0;
}

.data-table-container th,
.data-table-container td {
    padding: 8px 12px;
    font-size: 12px;
}

.no-data {
    text-align: center;
    padding: 20px;
    color: #666;
    font-style: italic;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    padding: 15px;
}

.stat-item {
    padding: 10px;
    background: #f9f9f9;
    border-radius: 3px;
    border-left: 3px solid #0073aa;
}

.error-message {
    padding: 15px;
    background: #fee;
    border: 1px solid #fcc;
    border-radius: 3px;
    color: #dc3232;
}

.status-pending {
    color: #ffa500;
    font-weight: bold;
}

.status-running {
    color: #0073aa;
    font-weight: bold;
}

.status-completed {
    color: #46b450;
    font-weight: bold;
}

.status-failed {
    color: #dc3232;
    font-weight: bold;
}

.status-unknown {
    color: #666;
}

.player-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 10px;
    padding: 15px;
}

.player-item {
    padding: 10px;
    background: #f9f9f9;
    border-radius: 3px;
    border-left: 3px solid #0073aa;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Product Section Styles */
.product-section {
    margin: 20px 0;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.product-section h5 {
    background: linear-gradient(135deg, #2271b1, #135e96);
    color: white;
    padding: 15px;
    margin: -1px -1px 0 -1px;
    border-radius: 8px 8px 0 0;
    font-size: 16px;
}

.product-stats {
    background: #f8f9fa;
    border-radius: 5px;
    margin: 15px 0;
}

.screenshots-section,
.players-section {
    border-top: 1px solid #e5e5e5;
    padding-top: 15px;
}

.screenshots-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 15px;
    margin-top: 10px;
}

.screenshot-item {
    border: 1px solid #ddd;
    border-radius: 6px;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.screenshot-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.screenshot-item img {
    width: 100%;
    height: 140px;
    object-fit: cover;
    transition: opacity 0.3s ease;
}

.screenshot-item img:hover {
    opacity: 0.9;
}

.players-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
    margin-top: 10px;
}

.player-item {
    background: #ffffff;
    border: 1px solid #e1e5e9;
    border-radius: 6px;
    padding: 12px;
    transition: border-color 0.2s ease;
}

.player-item:hover {
    border-color: #2271b1;
}

.player-item code {
    background: #f1f3f4;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
    color: #1d2327;
}

.player-id {
    font-family: 'Courier New', monospace;
    background: #e8e8e8;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}

.player-details {
    text-align: right;
    color: #666;
    font-size: 11px;
}

.error-list {
    padding: 15px;
}

.error-item {
    padding: 10px;
    margin-bottom: 10px;
    background: #ffeaea;
    border-left: 3px solid #dc3232;
    border-radius: 3px;
}

.error-item:last-child {
    margin-bottom: 0;
}

.error-message {
    color: #dc3232;
    font-style: italic;
}

.error-date {
    color: #666;
}
</style>