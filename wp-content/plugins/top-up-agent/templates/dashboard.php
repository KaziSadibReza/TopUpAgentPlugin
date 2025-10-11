<?php
/**
 * Automation Dashboard - Complete Queue Management & Real-time Monitoring
 * Control center for all automation operations, queue management, and server monitoring
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get API client
$api = new Top_Up_Agent_API_Client();

// Handle dashboard actions
$action_message = '';
$action_type = '';

if (isset($_POST['action']) && $_POST['action'] && wp_verify_nonce($_POST['nonce'], 'dashboard_actions')) {
    switch ($_POST['action']) {
        case 'pause_queue':
            $result = $api->pause_queue();
            if (!is_wp_error($result)) {
                $action_message = '⏸️ Queue paused successfully';
                $action_type = 'success';
            } else {
                $action_message = '❌ Failed to pause queue: ' . $result->get_error_message();
                $action_type = 'error';
            }
            break;
            
        case 'resume_queue':
            $result = $api->resume_queue();
            if (!is_wp_error($result)) {
                $action_message = '▶️ Queue resumed successfully';
                $action_type = 'success';
            } else {
                $action_message = '❌ Failed to resume queue: ' . $result->get_error_message();
                $action_type = 'error';
            }
            break;
            
        case 'process_queue':
            $result = $api->process_queue();
            if (!is_wp_error($result)) {
                $action_message = '🚀 Queue processing triggered manually';
                $action_type = 'success';
            } else {
                $action_message = '❌ Failed to trigger queue processing: ' . $result->get_error_message();
                $action_type = 'error';
            }
            break;
            
        case 'cleanup_queue':
            $hours = intval($_POST['cleanup_hours'] ?? 24);
            $result = $api->cleanup_queue($hours);
            if (!is_wp_error($result)) {
                $action_message = '🧹 Queue cleaned up successfully (older than ' . $hours . ' hours)';
                $action_type = 'success';
            } else {
                $action_message = '❌ Failed to cleanup queue: ' . $result->get_error_message();
                $action_type = 'error';
            }
            break;
            
        case 'clear_database':
            $confirm = $_POST['confirm_clear'] ?? '';
            if ($confirm === 'yes') {
                $result = $api->clear_database(true);
                if (!is_wp_error($result)) {
                    $action_message = '🗑️ Database cleared successfully';
                    $action_type = 'success';
                } else {
                    $action_message = '❌ Failed to clear database: ' . $result->get_error_message();
                    $action_type = 'error';
                }
            } else {
                $action_message = '❌ Database clear cancelled - confirmation required';
                $action_type = 'error';
            }
            break;
            
        case 'cancel_queue_item':
            $queue_id = intval($_POST['queue_id']);
            if ($queue_id) {
                $result = $api->cancel_queue_item($queue_id);
                if (!is_wp_error($result)) {
                    $action_message = '❌ Queue item #' . $queue_id . ' cancelled successfully';
                    $action_type = 'success';
                } else {
                    $action_message = '❌ Failed to cancel queue item: ' . $result->get_error_message();
                    $action_type = 'error';
                }
            }
            break;
    }
}

// Get current data
$queue_status = $api->get_queue_status();
$recent_queue = $api->get_recent_queue_items();
$pending_queue = $api->get_pending_queue_items();
$running_queue = $api->get_running_queue_items();
$database_stats = $api->get_database_stats();

// Process data
$queue_data = ['pending' => 0, 'running' => 0, 'completed' => 0, 'paused' => false];
if (!is_wp_error($queue_status)) {
    if (isset($queue_status['stats'])) {
        // New API format with nested stats
        $stats = $queue_status['stats'];
        $queue_data = [
            'pending' => $stats['pending'] ?? 0,
            'running' => $stats['processing'] ?? 0,
            'completed' => $stats['completed'] ?? 0,
            'failed' => $stats['failed'] ?? 0,
            'paused' => false
        ];
    } else {
        // Legacy format
        $queue_data = $queue_status;
    }
}
$recent_items = (!is_wp_error($recent_queue) && isset($recent_queue['items'])) ? $recent_queue['items'] : [];
$pending_items = (!is_wp_error($pending_queue) && isset($pending_queue['items'])) ? $pending_queue['items'] : [];
$running_items = (!is_wp_error($running_queue) && isset($running_queue['items'])) ? $running_queue['items'] : [];
$db_stats = !is_wp_error($database_stats) ? $database_stats : ['total' => 0, 'pending' => 0, 'completed' => 0, 'failed' => 0];
?>

<div class="wrap">
    <h1>🎮 Automation Dashboard</h1>
    <p>Real-time automation monitoring and queue management control center</p>

    <!-- Action Message -->
    <?php if ($action_message): ?>
    <div class="notice notice-<?php echo $action_type; ?> is-dismissible">
        <p><?php echo esc_html($action_message); ?></p>
    </div>
    <?php endif; ?>

    <!-- Real-time Status Indicator -->
    <div id="realtime-status" class="notice notice-info" style="margin: 20px 0;">
        <p><strong>🔄 Real-time Connection:</strong> <span id="connection-status">Connecting...</span></p>
    </div>

    <!-- Overview Stats Cards -->
    <div class="dashboard-stats"
        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div class="stat-card"
            style="background: #f0f9ff; border-left: 4px solid #0ea5e9; padding: 20px; border-radius: 8px;">
            <h3 style="margin: 0; color: #0ea5e9;">⏳ Pending</h3>
            <p style="font-size: 24px; font-weight: bold; margin: 10px 0 0 0;">
                <?php echo $queue_data['pending'] ?? 0; ?></p>
        </div>
        <div class="stat-card"
            style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 20px; border-radius: 8px;">
            <h3 style="margin: 0; color: #f59e0b;">🔄 Running</h3>
            <p style="font-size: 24px; font-weight: bold; margin: 10px 0 0 0;">
                <?php echo $queue_data['running'] ?? 0; ?></p>
        </div>
        <div class="stat-card"
            style="background: #dcfce7; border-left: 4px solid #22c55e; padding: 20px; border-radius: 8px;">
            <h3 style="margin: 0; color: #22c55e;">✅ Completed</h3>
            <p style="font-size: 24px; font-weight: bold; margin: 10px 0 0 0;">
                <?php echo $queue_data['completed'] ?? 0; ?></p>
        </div>
        <div class="stat-card"
            style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 20px; border-radius: 8px;">
            <h3 style="margin: 0; color: #ef4444;">❌ Failed</h3>
            <p style="font-size: 24px; font-weight: bold; margin: 10px 0 0 0;"><?php echo $queue_data['failed'] ?? 0; ?>
            </p>
        </div>
    </div>

    <!-- Queue Control Panel -->
    <div class="postbox" style="margin-bottom: 30px;">
        <h2 style="padding: 15px; margin: 0; background: #f1f1f1; border-bottom: 1px solid #ddd;">🎛️ Queue Control
            Panel</h2>
        <div style="padding: 20px;">
            <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                <!-- Pause/Resume -->
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('dashboard_actions'); ?>">
                    <?php if (!empty($queue_data['paused'])): ?>
                    <input type="hidden" name="action" value="resume_queue">
                    <button type="submit" class="button button-primary"
                        style="background: #22c55e; border-color: #22c55e;">
                        ▶️ Resume Queue
                    </button>
                    <?php else: ?>
                    <input type="hidden" name="action" value="pause_queue">
                    <button type="submit" class="button button-secondary">
                        ⏸️ Pause Queue
                    </button>
                    <?php endif; ?>
                </form>

                <!-- Manual Process -->
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('dashboard_actions'); ?>">
                    <input type="hidden" name="action" value="process_queue">
                    <button type="submit" class="button button-primary">
                        🚀 Process Queue Now
                    </button>
                </form>

                <!-- Cleanup -->
                <form method="POST" style="display: inline-flex; align-items: center; gap: 10px;">
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('dashboard_actions'); ?>">
                    <input type="hidden" name="action" value="cleanup_queue">
                    <select name="cleanup_hours" style="height: 32px;">
                        <option value="1">1 hour</option>
                        <option value="6">6 hours</option>
                        <option value="24" selected>24 hours</option>
                        <option value="168">1 week</option>
                    </select>
                    <button type="submit" class="button"
                        onclick="return confirm('Clean up completed queue items older than selected time?')">
                        🧹 Cleanup Old Items
                    </button>
                </form>

                <!-- Clear Database (Site-Specific) -->
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('dashboard_actions'); ?>">
                    <input type="hidden" name="action" value="clear_database">
                    <input type="hidden" name="confirm_clear" id="confirm_clear" value="">
                    <button type="button" class="button"
                        style="background: #ef4444; border-color: #ef4444; color: white; font-weight: bold; padding: 8px 16px;"
                        onclick="clearDatabase()" title="Delete all automation data for THIS WordPress site only">
                        🗑️ Clear Database
                    </button>
                    <br><small style="color: #666; font-size: 11px;">Only affects THIS WordPress site</small>
                </form>

                <!-- Debug Test Button -->
                <button type="button" class="button"
                    style="background: #f59e0b; border-color: #f59e0b; color: white; margin-left: 10px;"
                    onclick="testAjaxConnection()" title="Test AJAX connection and debug issues">
                    🐛 Test Connection
                </button>

                <!-- Direct API Test -->
                <button type="button" class="button"
                    style="background: #8b5cf6; border-color: #8b5cf6; color: white; margin-left: 5px;"
                    onclick="testDirectAPI()" title="Test direct API connection to automation server">
                    🔗 Test API
                </button>

                <!-- Quick Fix API Key -->
                <button type="button" class="button"
                    style="background: #10b981; border-color: #10b981; color: white; margin-left: 5px;"
                    onclick="fixAPIKey()" title="Fix API key automatically">
                    🔧 Fix API Key
                </button>

                <!-- Refresh -->
                <button type="button" class="button" onclick="location.reload()">
                    🔄 Refresh
                </button>
            </div>
        </div>
    </div>

    <!-- Tabs for different views -->
    <div class="nav-tab-wrapper" style="margin-bottom: 20px;">
        <a href="javascript:void(0)" class="nav-tab nav-tab-active" onclick="showTab('running')">🔄 Running
            (<?php echo count($running_items); ?>)</a>
        <a href="javascript:void(0)" class="nav-tab" onclick="showTab('pending')">⏳ Pending
            (<?php echo count($pending_items); ?>)</a>
        <a href="javascript:void(0)" class="nav-tab" onclick="showTab('recent')">📊 Recent
            (<?php echo count($recent_items); ?>)</a>
        <a href="javascript:void(0)" class="nav-tab" onclick="showTab('logs')">📝 Real-time Logs</a>
    </div>

    <!-- Running Queue Items -->
    <div id="tab-running" class="tab-content">
        <div class="postbox">
            <h2 style="padding: 15px; margin: 0; background: #fef3c7; border-bottom: 1px solid #ddd;">🔄 Currently
                Running Automations</h2>
            <div style="padding: 20px;">
                <?php if (empty($running_items)): ?>
                <p style="text-align: center; color: #666; font-style: italic;">No automations currently running</p>
                <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Queue ID</th>
                            <th>Order ID</th>
                            <th>Player ID</th>
                            <th>License Key</th>
                            <th>Started</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($running_items as $item): ?>
                        <tr>
                            <td><strong>#<?php echo esc_html($item['id']); ?></strong></td>
                            <td>
                                <?php if ($item['order_id']): ?>
                                <a href="<?php echo admin_url('post.php?post=' . $item['order_id'] . '&action=edit'); ?>"
                                    target="_blank">
                                    #<?php echo esc_html($item['order_id']); ?>
                                </a>
                                <?php else: ?>
                                <em>Manual</em>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo esc_html($item['player_id']); ?></code></td>
                            <td><small><?php echo esc_html(substr($item['license_key'], 0, 20)); ?>...</small></td>
                            <td><?php echo date('H:i:s', strtotime($item['started_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="nonce"
                                        value="<?php echo wp_create_nonce('dashboard_actions'); ?>">
                                    <input type="hidden" name="action" value="cancel_queue_item">
                                    <input type="hidden" name="queue_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="button button-small"
                                        style="background: #ef4444; color: white; border-color: #ef4444;"
                                        onclick="return confirm('Cancel this running automation?')">
                                        ❌ Cancel
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Pending Queue Items -->
    <div id="tab-pending" class="tab-content" style="display: none;">
        <div class="postbox">
            <h2 style="padding: 15px; margin: 0; background: #f0f9ff; border-bottom: 1px solid #ddd;">⏳ Pending
                Automations</h2>
            <div style="padding: 20px;">
                <?php if (empty($pending_items)): ?>
                <p style="text-align: center; color: #666; font-style: italic;">No pending automations</p>
                <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Queue ID</th>
                            <th>Type</th>
                            <th>Order ID</th>
                            <th>Player ID</th>
                            <th>License Key</th>
                            <th>Scheduled</th>
                            <th>Priority</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_items as $item): ?>
                        <tr>
                            <td><strong>#<?php echo esc_html($item['id']); ?></strong></td>
                            <td>
                                <span
                                    style="background: #e5e7eb; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                    <?php echo ucfirst($item['automation_type'] ?? 'single'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($item['order_id']): ?>
                                <a href="<?php echo admin_url('post.php?post=' . $item['order_id'] . '&action=edit'); ?>"
                                    target="_blank">
                                    #<?php echo esc_html($item['order_id']); ?>
                                </a>
                                <?php else: ?>
                                <em>Manual</em>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo esc_html($item['player_id']); ?></code></td>
                            <td><small><?php echo esc_html(substr($item['license_key'], 0, 20)); ?>...</small></td>
                            <td><?php echo date('M j, H:i', strtotime($item['scheduled_at'])); ?></td>
                            <td>
                                <span
                                    style="background: <?php echo $item['priority'] > 0 ? '#fbbf24' : '#94a3b8'; ?>; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                    <?php echo $item['priority']; ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="nonce"
                                        value="<?php echo wp_create_nonce('dashboard_actions'); ?>">
                                    <input type="hidden" name="action" value="cancel_queue_item">
                                    <input type="hidden" name="queue_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="button button-small"
                                        onclick="return confirm('Cancel this pending automation?')">
                                        ❌ Cancel
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Queue Items -->
    <div id="tab-recent" class="tab-content" style="display: none;">
        <div class="postbox">
            <h2 style="padding: 15px; margin: 0; background: #f3f4f6; border-bottom: 1px solid #ddd;">📊 Recent
                Automation History</h2>
            <div style="padding: 20px;">
                <?php if (empty($recent_items)): ?>
                <p style="text-align: center; color: #666; font-style: italic;">No recent automations</p>
                <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Queue ID</th>
                            <th>Status</th>
                            <th>Order ID</th>
                            <th>Player ID</th>
                            <th>Duration</th>
                            <th>Completed</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($recent_items, 0, 50) as $item): ?>
                        <tr>
                            <td><strong>#<?php echo esc_html($item['id']); ?></strong></td>
                            <td>
                                <?php
                                        $status_colors = [
                                            'completed' => '#22c55e',
                                            'failed' => '#ef4444',
                                            'running' => '#f59e0b',
                                            'pending' => '#6b7280'
                                        ];
                                        $status = $item['status'];
                                        $color = $status_colors[$status] ?? '#6b7280';
                                        ?>
                                <span
                                    style="background: <?php echo $color; ?>; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px;">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($item['order_id']): ?>
                                <a href="<?php echo admin_url('post.php?post=' . $item['order_id'] . '&action=edit'); ?>"
                                    target="_blank">
                                    #<?php echo esc_html($item['order_id']); ?>
                                </a>
                                <?php else: ?>
                                <em>Manual</em>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo esc_html($item['player_id']); ?></code></td>
                            <td>
                                <?php if ($item['started_at'] && $item['completed_at']): ?>
                                <?php
                                            $duration = strtotime($item['completed_at']) - strtotime($item['started_at']);
                                            echo $duration > 0 ? $duration . 's' : 'N/A';
                                            ?>
                                <?php else: ?>
                                N/A
                                <?php endif; ?>
                            </td>
                            <td><?php echo $item['completed_at'] ? date('M j, H:i', strtotime($item['completed_at'])) : 'N/A'; ?>
                            </td>
                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                <?php if ($item['error_message']): ?>
                                <small style="color: #ef4444;" title="<?php echo esc_attr($item['error_message']); ?>">
                                    <?php echo esc_html(substr($item['error_message'], 0, 50)); ?>...
                                </small>
                                <?php else: ?>
                                <span style="color: #6b7280;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Real-time Logs -->
    <div id="tab-logs" class="tab-content" style="display: none;">
        <div class="postbox">
            <h2 style="padding: 15px; margin: 0; background: #f9fafb; border-bottom: 1px solid #ddd;">
                📝 Real-time Automation Logs
                <span id="log-status" style="float: right; font-size: 12px; color: #10b981;">🔄 Live</span>
            </h2>
            <div style="padding: 20px;">
                <div style="background: #1f2937; color: #f9fafb; padding: 15px; border-radius: 5px; font-family: monospace; height: 400px; overflow-y: auto;"
                    id="realtime-logs">
                    <div style="color: #10b981;">🔄 Connecting to real-time log stream...</div>
                </div>
                <div style="margin-top: 10px;">
                    <button type="button" class="button" onclick="clearLogs()">🧹 Clear Logs</button>
                    <button type="button" class="button" onclick="toggleAutoScroll()" id="autoscroll-btn">📜
                        Auto-scroll: ON</button>
                    <span style="margin-left: 15px; font-size: 12px; color: #6b7280;">
                        Last update: <span id="last-update">Connecting...</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Socket.IO Client Library -->
<script src="https://cdn.socket.io/4.7.4/socket.io.min.js"></script>

<script>
// Tab switching
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.style.display = 'none';
    });

    // Remove active class from all nav tabs
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.classList.remove('nav-tab-active');
    });

    // Show selected tab
    document.getElementById('tab-' + tabName).style.display = 'block';

    // Add active class to clicked nav tab
    event.target.classList.add('nav-tab-active');
}

// Test AJAX connection and debug issues
function testAjaxConnection() {
    addLogMessage('🐛 Testing AJAX connection...', 'info');

    const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
    const nonce = '<?php echo wp_create_nonce('site_database_operations'); ?>';

    addLogMessage('🔧 AJAX URL: ' + ajaxUrl, 'info');
    addLogMessage('🔧 Nonce: ' + nonce, 'info');
    addLogMessage('🔧 Current Site: ' + window.location.origin, 'info');

    // First test - simple debug endpoint
    addLogMessage('🧪 Step 1: Testing basic AJAX...', 'info');

    fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=debug_test&nonce=${nonce}`
        })
        .then(response => {
            addLogMessage('🔧 Basic AJAX Status: ' + response.status, 'info');
            return response.text();
        })
        .then(text => {
            addLogMessage('🔧 Basic AJAX Response: ' + text.substring(0, 200), 'info');

            try {
                const data = JSON.parse(text);
                if (data.success) {
                    addLogMessage('✅ Basic AJAX working!', 'success');
                    addLogMessage('🔧 Nonce Valid: ' + data.data.nonce_valid, 'info');

                    // Now test the actual endpoint
                    testDatabaseSupport();
                } else {
                    addLogMessage('❌ Basic AJAX failed: ' + (data.data || 'unknown'), 'error');
                }
            } catch (e) {
                addLogMessage('❌ Basic AJAX response not JSON: ' + e.message, 'error');
            }
        })
        .catch(error => {
            addLogMessage('❌ Basic AJAX failed: ' + error.message, 'error');
        });
}

function testDatabaseSupport() {
    addLogMessage('🧪 Step 2: Testing database support check...', 'info');

    const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
    const nonce = '<?php echo wp_create_nonce('site_database_operations'); ?>';

    // Test the check_site_database_support endpoint
    fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=check_site_database_support&nonce=${nonce}`
        })
        .then(response => {
            addLogMessage('🔧 DB Check Status: ' + response.status, 'info');
            return response.text();
        })
        .then(text => {
            addLogMessage('🔧 DB Check Response: ' + text.substring(0, 200) + '...', 'info');

            try {
                const data = JSON.parse(text);
                addLogMessage('🔧 DB Check JSON parsed successfully', 'success');

                if (data.success) {
                    addLogMessage('✅ Database check working!', 'success');
                    addLogMessage('📊 Site: ' + (data.data.site || 'unknown'), 'info');
                    addLogMessage('📊 Records: ' + (data.data.count || 0), 'info');
                    addLogMessage('📊 Server Support: ' + (data.data.serverSupport || false), 'info');
                } else {
                    addLogMessage('❌ Database check failed: ' + (data.data || 'unknown error'), 'error');
                }
            } catch (e) {
                addLogMessage('❌ DB Check JSON parse error: ' + e.message, 'error');
                addLogMessage('🔧 Raw response: ' + text, 'error');
            }
        })
        .catch(error => {
            addLogMessage('❌ Database check network error: ' + error.message, 'error');
            console.error('Database support test error:', error);
        });
}

// Test direct API connection to automation server
function testDirectAPI() {
    addLogMessage('🔗 Testing direct API connection...', 'info');

    // Get the API key from WordPress settings (same as the plugin uses)
    const apiKey =
        '<?php echo esc_js(get_option('top_up_agent_api_key', '63bb16f0d85a2b1a90b329a2a8d39e3cf885a238a1ea632be6c375a97957e3e9')); ?>';
    const serverUrl = '<?php echo esc_js(get_option('top_up_agent_server_url', 'https://server.uidtopupbd.com')); ?>';

    // Test direct connection to automation server
    const currentSite = window.location.origin;
    const testUrl = `${serverUrl}/api/history?sourceSite=${encodeURIComponent(currentSite)}&limit=1`;

    addLogMessage('🔧 Server URL: ' + serverUrl, 'info');
    addLogMessage('🔧 API Key: ' + (apiKey ? apiKey.substring(0, 10) + '...' : 'NOT SET'), 'info');
    addLogMessage('🔧 Test URL: ' + testUrl, 'info');
    addLogMessage('🔧 Current Site: ' + currentSite, 'info');

    if (!apiKey) {
        addLogMessage('❌ API Key not configured!', 'error');
        addLogMessage('🔧 Go to plugin settings to set API key', 'warn');
        return;
    }

    fetch(testUrl, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'x-api-key': apiKey
            }
        })
        .then(response => {
            addLogMessage('🔧 API Response Status: ' + response.status, 'info');

            if (response.status === 401) {
                addLogMessage('❌ API Key authentication failed (401)', 'error');
                addLogMessage('🔧 Current API Key: ' + apiKey.substring(0, 10) + '...', 'error');
                addLogMessage('🔧 Check if API key matches server configuration', 'warn');
                return response.text();
            }

            if (response.status === 404) {
                addLogMessage('❌ API endpoint not found (404)', 'error');
                addLogMessage('🔧 Check if automation server is running', 'warn');
                return response.text();
            }

            return response.text();
        })
        .then(text => {
            addLogMessage('🔧 API Raw Response: ' + text.substring(0, 200) + '...', 'info');

            try {
                const data = JSON.parse(text);
                if (data.success || data.history || Array.isArray(data)) {
                    addLogMessage('✅ Direct API connection working!', 'success');
                    const count = data.history ? data.history.length : (Array.isArray(data) ? data.length : 0);
                    addLogMessage('📊 Found ' + count + ' records via direct API', 'info');
                } else {
                    addLogMessage('⚠️ API responded but format unexpected', 'warn');
                    addLogMessage('🔧 Response: ' + JSON.stringify(data), 'info');
                }
            } catch (e) {
                addLogMessage('❌ API response not valid JSON', 'error');
                addLogMessage('🔧 Raw response: ' + text, 'error');
            }
        })
        .catch(error => {
            addLogMessage('❌ Direct API connection failed: ' + error.message, 'error');
            addLogMessage('🔧 Make sure automation server is running on ' + serverUrl, 'warn');
        });
}

// Fix API key automatically
function fixAPIKey() {
    addLogMessage('🔧 Fixing API key configuration...', 'info');

    const correctAPIKey =
        '<?php echo esc_js(get_option('top_up_agent_api_key', '63bb16f0d85a2b1a90b329a2a8d39e3cf885a238a1ea632be6c375a97957e3e9')); ?>';
    const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
    const nonce = '<?php echo wp_create_nonce('fix_api_key'); ?>';

    fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=fix_api_key&nonce=${nonce}&api_key=${encodeURIComponent(correctAPIKey)}`
        })
        .then(response => response.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    addLogMessage('✅ API key fixed successfully!', 'success');
                    addLogMessage('🔧 API Key: ' + correctAPIKey.substring(0, 10) + '...', 'info');
                    addLogMessage('🔄 Try testing the API connection now', 'info');
                } else {
                    addLogMessage('❌ Failed to fix API key: ' + (data.data || 'unknown error'), 'error');
                }
            } catch (e) {
                addLogMessage('❌ Error fixing API key: ' + e.message, 'error');
            }
        })
        .catch(error => {
            addLogMessage('❌ Network error fixing API key: ' + error.message, 'error');
        });
}

// Clear database with confirmation and site-specific safety
function clearDatabase() {
    // Debug information
    console.log('🐛 clearDatabase() called at:', new Date().toLocaleString());

    // Simple first confirmation
    if (!confirm(
            '🗑️ Clear Database?\n\nThis will delete ALL automation data for THIS WordPress site only.\n\nOther sites will NOT be affected.\n\nClick OK to continue...'
        )) {
        addLogMessage('❌ Database cleanup cancelled', 'info');
        return;
    }

    // Show loading state immediately
    addLogMessage('🔍 Checking automation records for this site...', 'info');
    const statusElement = document.getElementById('log-status');
    if (statusElement) {
        statusElement.innerHTML = '🔄 Checking...';
        statusElement.style.color = '#f59e0b';
    }

    // Use WordPress AJAX to leverage PHP API client with proper site detection
    const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
    const nonce = '<?php echo wp_create_nonce('site_database_operations'); ?>';
    console.log('🐛 AJAX URL:', ajaxUrl);
    console.log('🐛 Nonce:', nonce);

    fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=check_site_database_support&nonce=<?php echo wp_create_nonce('site_database_operations'); ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const recordCount = data.data.count || 0;
                const siteName = data.data.site || window.location.hostname;

                addLogMessage(`� Found ${recordCount} automation records for this site`, 'info');

                if (recordCount === 0) {
                    addLogMessage('✅ No automation data found - nothing to delete', 'success');
                    updateLogStatus();
                    return;
                }

                // Final simple confirmation with record count
                const finalConfirm = confirm(
                    `⚠️ FINAL CONFIRMATION\n\n` +
                    `📊 Records to delete: ${recordCount}\n` +
                    `🌐 Site: ${siteName}\n\n` +
                    `This action cannot be undone!\n\n` +
                    `Click OK to permanently delete these ${recordCount} records.`
                );

                if (finalConfirm) {
                    performSiteSpecificCleanup(siteName);
                } else {
                    addLogMessage('❌ Database cleanup cancelled', 'info');
                    updateLogStatus();
                }
            } else {
                addLogMessage('❌ Error checking automation records: ' + (data.data || 'Unknown error'), 'error');
                addLogMessage('🚨 Cleanup cancelled for safety', 'warn');
                updateLogStatus();
            }
        })
        .catch(error => {
            addLogMessage('❌ Failed to check server capabilities', 'error');
            addLogMessage('� Database cleanup cancelled for safety', 'warn');
            console.error('Server check error:', error);
        });
}

function performSiteSpecificCleanup(siteUrl) {
    // Show loading state
    const statusElement = document.getElementById('log-status');
    if (statusElement) {
        statusElement.innerHTML = '🔄 Deleting...';
        statusElement.style.color = '#f59e0b';
    }

    addLogMessage(`🧹 Clearing all automation data for: ${siteUrl}`, 'warn');
    addLogMessage(`⏳ Deleting history, queue items, and screenshots...`, 'info');

    // Use WordPress AJAX to perform site-specific cleanup
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=clear_site_database&nonce=<?php echo wp_create_nonce('site_database_operations'); ?>&site_url=${encodeURIComponent(siteUrl)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const deletedCount = data.data.deletedCount || 0;
                const historyDeleted = data.data.historyDeleted || 0;
                const queueDeleted = data.data.queueDeleted || 0;
                const screenshotsDeleted = data.data.screenshotsDeleted || 0;

                addLogMessage(`✅ Complete database cleanup successful!`, 'success');
                addLogMessage(`� Total deleted: ${deletedCount} records`, 'success');
                addLogMessage(`🗃️ History records: ${historyDeleted}`, 'success');
                addLogMessage(`⏳ Queue items: ${queueDeleted}`, 'success');
                addLogMessage(`📸 Screenshots: ${screenshotsDeleted}`, 'success');
                addLogMessage(`🔒 Only THIS site's data was affected`, 'success');

                if (deletedCount > 0) {
                    addLogMessage('� Refreshing dashboard in 3 seconds...', 'info');
                    // Refresh the page to show updated stats
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                } else {
                    addLogMessage('ℹ️ No records were found to delete', 'info');
                    updateLogStatus();
                }
            } else {
                addLogMessage(`❌ Cleanup failed: ${data.data || data.error || 'Unknown error'}`, 'error');
                addLogMessage(`🚨 No data was deleted`, 'warn');
                updateLogStatus();
            }
        })
        .catch(error => {
            addLogMessage('❌ Failed to perform site-specific cleanup', 'error');
            addLogMessage('🚨 No data was deleted', 'warn');
            console.error('Site-specific cleanup error:', error);
            updateLogStatus();
        });
}

// Real-time logs functionality
let autoScroll = true;
let logContainer;

function clearLogs() {
    if (logContainer) {
        logContainer.innerHTML = '<div style="color: #10b981;">📜 Logs cleared</div>';
    }
}

function toggleAutoScroll() {
    autoScroll = !autoScroll;
    const btn = document.getElementById('autoscroll-btn');
    btn.textContent = '📜 Auto-scroll: ' + (autoScroll ? 'ON' : 'OFF');
}

function addLogMessage(message, type = 'info') {
    if (!logContainer) return;

    const colors = {
        info: '#10b981',
        warn: '#f59e0b',
        error: '#ef4444',
        success: '#22c55e'
    };

    const timestamp = new Date().toLocaleTimeString();
    const logDiv = document.createElement('div');
    logDiv.style.color = colors[type] || '#10b981';
    logDiv.innerHTML = `[${timestamp}] ${message}`;

    logContainer.appendChild(logDiv);

    if (autoScroll) {
        logContainer.scrollTop = logContainer.scrollHeight;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    logContainer = document.getElementById('realtime-logs');

    // Connect to real automation server with Socket.IO
    setTimeout(() => {
        document.getElementById('connection-status').innerHTML =
            '<span style="color: #f59e0b;">🔄 Connecting...</span>';

        // Initialize Socket.IO connection
        initializeSocketConnection();
    }, 1000);

    // Auto-refresh queue data every 30 seconds
    setInterval(() => {
        // Only refresh if we're viewing running or pending tabs
        const activeTab = document.querySelector('.nav-tab-active').textContent;
        if (activeTab.includes('Running') || activeTab.includes('Pending')) {
            location.reload();
        }
    }, 30000);
});

// Socket.IO real-time connection
let socket = null;

function initializeSocketConnection() {
    const serverUrl = '<?php echo esc_js(get_option('top_up_agent_server_url', 'https://server.uidtopupbd.com')); ?>';

    try {
        // Connect to Socket.IO server with options
        socket = io(serverUrl, {
            reconnection: true,
            reconnectionDelay: 1000,
            reconnectionAttempts: 5,
            timeout: 20000
        });

        // Connection events
        socket.on('connect', () => {
            document.getElementById('connection-status').innerHTML =
                '<span style="color: #22c55e;">✅ Connected</span>';
            addLogMessage('🔗 Connected to automation server via Socket.IO', 'success');
            updateLogStatus();

            // Join automation events channel
            socket.emit('join-automation');
            addLogMessage('📡 Joined automation events channel', 'info');

            // Get initial status
            fetchInitialStatus();
        });

        socket.on('reconnect', (attemptNumber) => {
            addLogMessage(`🔄 Reconnected to server (attempt ${attemptNumber})`, 'success');
        });

        socket.on('reconnect_attempt', (attemptNumber) => {
            document.getElementById('connection-status').innerHTML =
                '<span style="color: #f59e0b;">🔄 Reconnecting...</span>';
            addLogMessage(`🔄 Reconnection attempt ${attemptNumber}`, 'warn');
        });

        socket.on('disconnect', (reason) => {
            document.getElementById('connection-status').innerHTML =
                '<span style="color: #ef4444;">❌ Disconnected</span>';
            addLogMessage(`❌ Disconnected from automation server: ${reason}`, 'error');
        });

        socket.on('connect_error', (error) => {
            document.getElementById('connection-status').innerHTML =
                '<span style="color: #ef4444;">❌ Connection Failed</span>';
            addLogMessage(`❌ Failed to connect to Socket.IO server at ${serverUrl}`, 'error');
            addLogMessage('🔧 Make sure your automation server is running with Socket.IO enabled', 'warn');
            console.error('Socket.IO connection error:', error);
        });

        // Real-time automation events
        socket.on('automation-log', (logData) => {
            addLogMessage(logData.message || logData.text || JSON.stringify(logData), logData.level || 'info');
            updateLogStatus();
        });

        socket.on('automation-started', (data) => {
            addLogMessage(`🚀 Automation started: ${data.playerId || data.id} - ${data.type || 'Unknown type'}`,
                'info');
            updateLogStatus();
        });

        socket.on('automation-completed', (data) => {
            const status = data.success ? 'success' : 'error';
            const icon = data.success ? '✅' : '❌';
            addLogMessage(
                `${icon} Automation completed: ${data.playerId || data.id} - ${data.result || data.status || 'Done'}`,
                status);
            updateLogStatus();
        });

        socket.on('queue-item-added', (data) => {
            addLogMessage(
                `➕ New queue item added: ${data.playerId || data.id} - ${data.type || 'Unknown type'}`,
                'info');
            updateLogStatus();
        });

        socket.on('queue-status-changed', (data) => {
            if (data.stats) {
                const stats = data.stats;
                addLogMessage(
                    `📊 Queue Status: ${stats.total} total, ${stats.pending} pending, ${stats.processing} processing, ${stats.completed} completed, ${stats.failed} failed`,
                    'info');
            }
            updateLogStatus();
        });

    } catch (error) {
        console.error('Socket.IO initialization error:', error);
        addLogMessage('❌ Socket.IO not available - falling back to polling', 'warn');
        // Fallback to polling if Socket.IO is not available
        startPollingFallback();
    }
}

// Fetch initial status once on connection
function fetchInitialStatus() {
    const serverUrl = '<?php echo esc_js(get_option('top_up_agent_server_url', 'https://server.uidtopupbd.com')); ?>';
    const apiKey =
        '<?php echo esc_js(get_option('top_up_agent_api_key', '63bb16f0d85a2b1a90b329a2a8d39e3cf885a238a1ea632be6c375a97957e3e9')); ?>';

    fetch(`${serverUrl}/api/queue/status`, {
            headers: {
                'x-api-key': apiKey
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.stats) {
                const stats = data.stats;
                addLogMessage(
                    `📊 Initial Queue Status: ${stats.total} total, ${stats.pending} pending, ${stats.processing} processing, ${stats.completed} completed, ${stats.failed} failed`,
                    'info');

                if (data.currentAutomation) {
                    addLogMessage(
                        `🔄 Currently processing: ${data.currentAutomation.id} - ${data.currentAutomation.status}`,
                        'info');
                }

                if (stats.failed > 0) {
                    addLogMessage(`⚠️ ${stats.failed} failed automations need attention`, 'warn');
                }
            }
        })
        .catch(error => {
            addLogMessage('❌ Failed to fetch initial status', 'error');
            console.error('Initial status fetch error:', error);
        });
}

// Fallback polling function (only used if Socket.IO fails)
function startPollingFallback() {
    addLogMessage('🔄 Using polling fallback (5-second intervals)', 'warn');
    setInterval(fetchInitialStatus, 5000);
}

function updateLogStatus() {
    const statusElement = document.getElementById('log-status');
    const lastUpdateElement = document.getElementById('last-update');

    if (statusElement) {
        statusElement.innerHTML = '🔄 Live';
        statusElement.style.color = '#10b981';
    }

    if (lastUpdateElement) {
        lastUpdateElement.textContent = new Date().toLocaleTimeString();
    }
}

// Track last known status to avoid duplicate logs
let lastKnownStats = null;

function shouldLogStatusChange(newStats) {
    if (!lastKnownStats) {
        lastKnownStats = newStats;
        return true;
    }

    // Only log if there are meaningful changes
    const hasChanges =
        newStats.pending !== lastKnownStats.pending ||
        newStats.processing !== lastKnownStats.processing ||
        newStats.completed !== lastKnownStats.completed ||
        newStats.failed !== lastKnownStats.failed;

    if (hasChanges) {
        lastKnownStats = newStats;
        return true;
    }

    return false;
}
</script>

<style>
.dashboard-stats {
    margin-bottom: 30px;
}

.stat-card {
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.button-danger {
    background: #ef4444 !important;
    border-color: #ef4444 !important;
    color: white !important;
}

.button-danger:hover {
    background: #dc2626 !important;
    border-color: #dc2626 !important;
}

.wp-list-table th {
    background: #f9fafb;
}

.wp-list-table tbody tr:hover {
    background: #f8fafc;
}

#realtime-logs::-webkit-scrollbar {
    width: 8px;
}

#realtime-logs::-webkit-scrollbar-track {
    background: #374151;
}

#realtime-logs::-webkit-scrollbar-thumb {
    background: #6b7280;
    border-radius: 4px;
}

#realtime-logs::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>