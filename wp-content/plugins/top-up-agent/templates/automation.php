<?php
require_once plugin_dir_path(__FILE__) . '../includes/api-integration/class-api-client.php';

if (!class_exists('Top_Up_Agent_API_Client')) {
    echo '<div class="wrap"><div class="notice notice-error"><p>Error: API Client class not found. Please check plugin installation.</p></div></div>';
    return;
}

$api = new Top_Up_Agent_API_Client();

// Get current queue status and recent items
$queue_status = $api->get_queue_status();
$pending_items = $api->get_pending_queue_items();
$running_automations = $api->get_running_automations();
$recent_results = $api->get_results(1, 20);

// Get logs and additional data
$logs_response = $api->get_logs(20); // Get recent 20 logs
$logs = [];
if (!is_wp_error($logs_response) && isset($logs_response['logs'])) {
    $logs = $logs_response['logs'];
}

// Process queue data
$queue_data = ['pending' => 0, 'running' => 0, 'completed' => 0];
if (!is_wp_error($queue_status) && isset($queue_status['stats'])) {
    $stats = $queue_status['stats'];
    $queue_data = [
        'pending' => $stats['pending'] ?? 0,
        'running' => $stats['processing'] ?? 0, // API uses 'processing', UI shows 'running'
        'completed' => $stats['completed'] ?? 0
    ];
}

$pending_data = [];
if (!is_wp_error($pending_items) && isset($pending_items['pendingItems'])) {
    $pending_data = $pending_items['pendingItems'];
}

// For running automations, check the current automation from queue status
$running_data = [];
if (!is_wp_error($queue_status) && isset($queue_status['currentAutomation']) && $queue_status['currentAutomation']) {
    $running_data = [$queue_status['currentAutomation']];
}

// Process results data
$results_data = [];
if (!is_wp_error($recent_results) && isset($recent_results['results'])) {
    $results_data = $recent_results['results'];
}

// Calculate running count
$running_count = is_array($running_data) ? count($running_data) : 0;
?>

<div class="wrap">
    <h1>🤖 Automation Management</h1>
    
    <!-- Real-time status indicator -->
    <div id="websocket-status-automation" class="notice notice-info" style="margin: 20px 0;">
        <p><strong>Real-time Connection:</strong> <span id="connection-status">Connecting...</span></p>
    </div>

    <div class="automation-layout" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
        
        <!-- Add to Queue Section -->
        <div class="automation-card" style="background: white; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
            <h2>➕ Add Manual Automation to Queue</h2>
            
            <div class="automation-form-tabs" style="border-bottom: 1px solid #ccd0d4; margin-bottom: 20px;">
                <button type="button" class="tab-button active" onclick="switchTab('single')">Single</button>
                <button type="button" class="tab-button" onclick="switchTab('group')">Group</button>
                <button type="button" class="tab-button" onclick="switchTab('direct')">Direct Execute</button>
            </div>

            <!-- Single Automation Form -->
            <div id="single-form" class="automation-form">
                <table class="form-table">
                    <tr>
                        <th><label for="single-player-id">Player ID</label></th>
                        <td><input type="text" id="single-player-id" class="regular-text" placeholder="Enter player ID" required></td>
                    </tr>
                    <tr>
                        <th><label for="single-redimension-code">Redimension Code</label></th>
                        <td><input type="text" id="single-redimension-code" class="regular-text" placeholder="5060XXXX" required></td>
                    </tr>
                    <tr>
                        <th><label for="single-source-site">Source Site</label></th>
                        <td><input type="url" id="single-source-site" class="regular-text" placeholder="http://yoursite.com" value="<?php echo esc_attr(home_url()); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="single-product-name">Product Name</label></th>
                        <td><input type="text" id="single-product-name" class="regular-text" placeholder="Product description"></td>
                    </tr>
                    <tr>
                        <th><label for="single-license-key">License Key</label></th>
                        <td><input type="text" id="single-license-key" class="regular-text" placeholder="License key"></td>
                    </tr>
                </table>
                <button type="button" class="button button-primary" onclick="addSingleToQueue()">🚀 Add to Queue</button>
            </div>

            <!-- Group Automation Form -->
            <div id="group-form" class="automation-form" style="display: none;">
                <table class="form-table">
                    <tr>
                        <th><label for="group-player-id">Player ID</label></th>
                        <td><input type="text" id="group-player-id" class="regular-text" placeholder="Enter player ID" required></td>
                    </tr>
                    <tr>
                        <th><label for="group-redimension-codes">Redimension Codes</label></th>
                        <td>
                            <textarea id="group-redimension-codes" rows="4" class="large-text" placeholder="5060XXXX&#10;5060YYYY&#10;5060ZZZZ" required></textarea>
                            <p class="description">Enter one code per line</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="group-source-site">Source Site</label></th>
                        <td><input type="url" id="group-source-site" class="regular-text" placeholder="http://yoursite.com" value="<?php echo esc_attr(home_url()); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="group-product-name">Product Name</label></th>
                        <td><input type="text" id="group-product-name" class="regular-text" placeholder="Group product description"></td>
                    </tr>
                    <tr>
                        <th><label for="group-license-key">License Key</label></th>
                        <td><input type="text" id="group-license-key" class="regular-text" placeholder="License key"></td>
                    </tr>
                </table>
                <button type="button" class="button button-primary" onclick="addGroupToQueue()">🚀 Add Group to Queue</button>
            </div>

            <!-- Direct Execute Form -->
            <div id="direct-form" class="automation-form" style="display: none;">
                <table class="form-table">
                    <tr>
                        <th><label for="direct-player-id">Player ID</label></th>
                        <td><input type="text" id="direct-player-id" class="regular-text" placeholder="Enter player ID" required></td>
                    </tr>
                    <tr>
                        <th><label for="direct-redimension-code">Redimension Code</label></th>
                        <td><input type="text" id="direct-redimension-code" class="regular-text" placeholder="5060XXXX" required></td>
                    </tr>
                    <tr>
                        <th><label for="direct-request-id">Request ID (Optional)</label></th>
                        <td><input type="text" id="direct-request-id" class="regular-text" placeholder="custom-request-id"></td>
                    </tr>
                </table>
                <button type="button" class="button button-primary" onclick="executeDirectAutomation()">⚡ Execute Now</button>
            </div>
        </div>

        <!-- Queue Status Section -->
        <div class="automation-card" style="background: white; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
            <h2>📊 Queue Status</h2>
            <div id="queue-status-automation" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div class="status-item" style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                    <div style="font-size: 24px; font-weight: bold; color: #ffa500;"><?php echo intval($queue_data['pending'] ?? 0); ?></div>
                    <div>Pending</div>
                </div>
                <div class="status-item" style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                    <div style="font-size: 24px; font-weight: bold; color: #007cba;"><?php echo intval($queue_data['running'] ?? 0); ?></div>
                    <div>Running</div>
                </div>
                <div class="status-item" style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                    <div style="font-size: 24px; font-weight: bold; color: #00a32a;"><?php echo intval($queue_data['completed'] ?? 0); ?></div>
                    <div>Completed</div>
                </div>
            </div>
            
            <div class="queue-controls" style="text-align: center;">
                <button type="button" class="button" onclick="refreshQueueStatus()">🔄 Refresh</button>
                <button type="button" class="button" onclick="pauseQueue()">⏸️ Pause</button>
                <button type="button" class="button" onclick="resumeQueue()">▶️ Resume</button>
            </div>
        </div>
    </div>

    <!-- Pending Queue Items -->
    <div class="automation-card" style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
        <h2>⏳ Pending Queue Items</h2>
        <div id="pending-queue-items">
            <?php if (!empty($pending_data)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Player ID</th>
                            <th>Redimension Code</th>
                            <th>Type</th>
                            <th>Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_data as $item): ?>
                        <tr>
                            <td><?php echo esc_html($item['id'] ?? 'N/A'); ?></td>
                            <td><?php echo esc_html($item['playerId'] ?? 'N/A'); ?></td>
                            <td><?php echo esc_html($item['redimensionCode'] ?? 'N/A'); ?></td>
                            <td><?php echo esc_html($item['queueType'] ?? 'single'); ?></td>
                            <td><?php echo esc_html($item['createdAt'] ?? 'N/A'); ?></td>
                            <td>
                                <button type="button" class="button button-small" 
                                        onclick="cancelQueueItem(<?php echo intval($item['id'] ?? 0); ?>)">
                                    ❌ Cancel
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No pending queue items.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Running Automations -->
    <div class="automation-card" style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
        <h2>⚡ Currently Running Automations</h2>
        <div id="running-automations-list">
            <?php if (!empty($running_data)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Player ID</th>
                            <th>Redimension Code</th>
                            <th>Started</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($running_data as $automation): ?>
                        <tr>
                            <td><?php echo esc_html($automation['requestId'] ?? 'N/A'); ?></td>
                            <td><?php echo esc_html($automation['playerId'] ?? 'N/A'); ?></td>
                            <td><?php echo esc_html($automation['redimensionCode'] ?? 'N/A'); ?></td>
                            <td><?php echo esc_html($automation['startedAt'] ?? 'N/A'); ?></td>
                            <td><span class="automation-status running">🔄 Running</span></td>
                            <td>
                                <button type="button" class="button button-small" 
                                        onclick="cancelAutomation('<?php echo esc_attr($automation['requestId'] ?? ''); ?>')">
                                    ❌ Cancel
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No automations currently running.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Real-time Console Log -->
    <div class="automation-card" style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2>📄 Real-time Console Log</h2>
            <div class="log-controls">
                <button type="button" class="button" onclick="clearConsoleLog()">🧹 Clear</button>
                <button type="button" class="button" onclick="downloadLogs()">📥 Download</button>
                <label>
                    <input type="checkbox" id="auto-scroll" checked> Auto-scroll
                </label>
            </div>
        </div>
        <div id="console-log" style="height: 400px; overflow-y: auto; background: #1e1e1e; color: #ffffff; padding: 15px; font-family: 'Courier New', monospace; font-size: 12px; border-radius: 4px;">
            <div class="log-entry info">[<?php echo date('H:i:s'); ?>] Console initialized. Waiting for real-time updates...</div>
        </div>
    </div>

    <!-- Recent Results -->
    <div class="automation-card" style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
        <h2>📈 Recent Automation Results</h2>
        <div id="recent-results">
            <?php if (!empty($results_data)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Player ID</th>
                            <th>Redimension Code</th>
                            <th>Status</th>
                            <th>Completed</th>
                            <th>Duration</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results_data as $result): ?>
                        <tr>
                            <td><?php echo esc_html($result['playerId'] ?? 'N/A'); ?></td>
                            <td><?php echo esc_html($result['redimensionCode'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if (($result['status'] ?? '') === 'completed' || ($result['success'] ?? false)): ?>
                                    <span style="color: green;">✅ Success</span>
                                <?php else: ?>
                                    <span style="color: red;">❌ Failed</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($result['endTime'] ?? $result['updated_at'] ?? 'N/A'); ?></td>
                            <td><?php echo esc_html(isset($result['duration']) ? round($result['duration']/1000, 2) . 's' : 'N/A'); ?></td>
                            <td>
                                <button type="button" class="button button-small" onclick="viewResult('<?php echo esc_attr($result['id'] ?? ''); ?>')">
                                    👁️ View
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No recent automation results available.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.automation-form {
    margin-bottom: 20px;
}

.tab-button {
    background: #f1f1f1;
    border: 1px solid #ccd0d4;
    padding: 8px 16px;
    cursor: pointer;
    border-bottom: none;
}

.tab-button.active {
    background: white;
    border-bottom: 1px solid white;
    margin-bottom: -1px;
}

.automation-status.running {
    color: #007cba;
    font-weight: bold;
}

.log-entry {
    margin-bottom: 3px;
    padding: 2px 0;
    word-wrap: break-word;
}

.log-entry.success {
    color: #00ff00;
}

.log-entry.error {
    color: #ff6b6b;
}

.log-entry.warning {
    color: #ffa500;
}

.log-entry.info {
    color: #87ceeb;
}

.automation-card {
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.automation-card h2 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 1.3em;
}

.log-controls {
    display: flex;
    align-items: center;
    gap: 10px;
}

.log-controls label {
    display: flex;
    align-items: center;
    font-size: 13px;
}

.log-controls input[type="checkbox"] {
    margin-right: 5px;
}

#websocket-status-automation {
    border-left: 4px solid #007cba;
}

#websocket-status-automation.connected {
    border-left-color: #00a32a;
}

#websocket-status-automation.disconnected {
    border-left-color: #d63638;
}
</style>

<script>
// Automation page JavaScript
jQuery(document).ready(function($) {
    const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
    const nonce = '<?php echo wp_create_nonce('top_up_agent_websocket'); ?>';
    
    // Update connection status
    if (window.topUpAgentSocketIO) {
        setInterval(function() {
            const status = window.topUpAgentSocketIO.getStatus();
            const statusElement = $('#connection-status');
            const containerElement = $('#websocket-status-automation');
            
            if (status.isConnected) {
                statusElement.html('🟢 Connected to automation server');
                containerElement.removeClass('disconnected').addClass('connected');
            } else {
                statusElement.html('🔴 Disconnected (Attempts: ' + status.reconnectAttempts + ')');
                containerElement.removeClass('connected').addClass('disconnected');
            }
        }, 1000);
    }
});

// Tab switching
function switchTab(tabName) {
    // Hide all forms
    document.querySelectorAll('.automation-form').forEach(form => {
        form.style.display = 'none';
    });
    
    // Remove active class from all tabs
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected form and activate tab
    document.getElementById(tabName + '-form').style.display = 'block';
    event.target.classList.add('active');
}

// Queue management functions
function addSingleToQueue() {
    const data = {
        action: 'add_to_queue',
        nonce: nonce,
        queueType: 'single',
        playerId: document.getElementById('single-player-id').value,
        redimensionCode: document.getElementById('single-redimension-code').value,
        sourceSite: document.getElementById('single-source-site').value,
        productName: document.getElementById('single-product-name').value,
        licenseKey: document.getElementById('single-license-key').value
    };
    
    if (!data.playerId || !data.redimensionCode) {
        alert('Please fill in required fields: Player ID and Redimension Code');
        return;
    }
    
    jQuery.post(ajaxUrl, data, function(response) {
        if (response.success) {
            addConsoleLog('Single automation added to queue: ' + data.playerId, 'success');
            clearForm('single');
            refreshQueueStatus();
        } else {
            addConsoleLog('Failed to add to queue: ' + response.data, 'error');
        }
    });
}

function addGroupToQueue() {
    const codes = document.getElementById('group-redimension-codes').value.split('\n').filter(code => code.trim());
    
    const data = {
        action: 'add_to_queue',
        nonce: nonce,
        queueType: 'group',
        playerId: document.getElementById('group-player-id').value,
        redimensionCodes: codes,
        sourceSite: document.getElementById('group-source-site').value,
        productName: document.getElementById('group-product-name').value,
        licenseKey: document.getElementById('group-license-key').value
    };
    
    if (!data.playerId || codes.length === 0) {
        alert('Please fill in required fields: Player ID and Redimension Codes');
        return;
    }
    
    jQuery.post(ajaxUrl, data, function(response) {
        if (response.success) {
            addConsoleLog('Group automation added to queue: ' + data.playerId + ' (' + codes.length + ' codes)', 'success');
            clearForm('group');
            refreshQueueStatus();
        } else {
            addConsoleLog('Failed to add group to queue: ' + response.data, 'error');
        }
    });
}

function executeDirectAutomation() {
    const data = {
        action: 'execute_automation',
        nonce: nonce,
        playerId: document.getElementById('direct-player-id').value,
        redimensionCode: document.getElementById('direct-redimension-code').value,
        requestId: document.getElementById('direct-request-id').value
    };
    
    if (!data.playerId || !data.redimensionCode) {
        alert('Please fill in required fields: Player ID and Redimension Code');
        return;
    }
    
    jQuery.post(ajaxUrl, data, function(response) {
        if (response.success) {
            addConsoleLog('Direct automation executed: ' + data.playerId, 'success');
            clearForm('direct');
            refreshRunningAutomations();
        } else {
            addConsoleLog('Failed to execute automation: ' + response.data, 'error');
        }
    });
}

function clearForm(formType) {
    document.querySelectorAll('#' + formType + '-form input').forEach(input => {
        if (input.type !== 'url' || input.id.indexOf('source-site') === -1) {
            input.value = '';
        }
    });
    document.querySelectorAll('#' + formType + '-form textarea').forEach(textarea => {
        textarea.value = '';
    });
}

function refreshQueueStatus() {
    jQuery.post(ajaxUrl, {
        action: 'get_queue_status',
        nonce: nonce
    }, function(response) {
        if (response.success && response.data) {
            const data = response.data;
            jQuery('#queue-status-automation').html(`
                <div class="status-item" style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                    <div style="font-size: 24px; font-weight: bold; color: #ffa500;">${data.pending || 0}</div>
                    <div>Pending</div>
                </div>
                <div class="status-item" style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                    <div style="font-size: 24px; font-weight: bold; color: #007cba;">${data.running || 0}</div>
                    <div>Running</div>
                </div>
                <div class="status-item" style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                    <div style="font-size: 24px; font-weight: bold; color: #00a32a;">${data.completed || 0}</div>
                    <div>Completed</div>
                </div>
            `);
            addConsoleLog('Queue status refreshed', 'info');
        }
    });
}

function refreshRunningAutomations() {
    jQuery.post(ajaxUrl, {
        action: 'get_running_automations',
        nonce: nonce
    }, function(response) {
        if (response.success) {
            addConsoleLog('Running automations refreshed', 'info');
            // Refresh the page to update the running automations table
            setTimeout(() => location.reload(), 1000);
        }
    });
}

function pauseQueue() {
    if (!confirm('Are you sure you want to pause the automation queue?')) return;
    
    jQuery.post(ajaxUrl, {
        action: 'pause_queue',
        nonce: nonce
    }, function(response) {
        if (response.success) {
            addConsoleLog('Queue paused', 'info');
        } else {
            addConsoleLog('Failed to pause queue: ' + response.data, 'error');
        }
    });
}

function resumeQueue() {
    jQuery.post(ajaxUrl, {
        action: 'resume_queue',
        nonce: nonce
    }, function(response) {
        if (response.success) {
            addConsoleLog('Queue resumed', 'success');
        } else {
            addConsoleLog('Failed to resume queue: ' + response.data, 'error');
        }
    });
}

function cancelQueueItem(itemId) {
    if (!confirm('Are you sure you want to cancel this queue item?')) return;
    
    jQuery.post(ajaxUrl, {
        action: 'cancel_queue_item',
        nonce: nonce,
        itemId: itemId,
        reason: 'User requested cancellation'
    }, function(response) {
        if (response.success) {
            addConsoleLog('Queue item cancelled: ' + itemId, 'info');
            setTimeout(() => location.reload(), 1000);
        } else {
            addConsoleLog('Failed to cancel queue item: ' + response.data, 'error');
        }
    });
}

function cancelAutomation(requestId) {
    if (!confirm('Are you sure you want to cancel this running automation?')) return;
    
    jQuery.post(ajaxUrl, {
        action: 'cancel_automation',
        nonce: nonce,
        requestId: requestId
    }, function(response) {
        if (response.success) {
            addConsoleLog('Automation cancelled: ' + requestId, 'info');
            refreshRunningAutomations();
        } else {
            addConsoleLog('Failed to cancel automation: ' + response.data, 'error');
        }
    });
}

function addConsoleLog(message, type = 'info') {
    const timestamp = new Date().toLocaleTimeString();
    const logContainer = jQuery('#console-log');
    const entry = `<div class="log-entry ${type}">[${timestamp}] ${message}</div>`;
    
    logContainer.append(entry);
    
    // Auto-scroll if enabled
    if (document.getElementById('auto-scroll').checked) {
        logContainer.scrollTop(logContainer[0].scrollHeight);
    }
    
    // Keep only last 200 entries
    const entries = logContainer.find('.log-entry');
    if (entries.length > 200) {
        entries.first().remove();
    }
}

function clearConsoleLog() {
    jQuery('#console-log').html('<div class="log-entry info">[' + new Date().toLocaleTimeString() + '] Console cleared</div>');
}

function downloadLogs() {
    jQuery.post(ajaxUrl, {
        action: 'get_logs',
        nonce: nonce,
        lines: 1000
    }, function(response) {
        if (response.success && response.data) {
            const blob = new Blob([JSON.stringify(response.data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'automation-logs-' + new Date().toISOString().slice(0, 10) + '.json';
            a.click();
            URL.revokeObjectURL(url);
            addConsoleLog('Logs downloaded', 'success');
        } else {
            addConsoleLog('Failed to download logs', 'error');
        }
    });
}

function viewResult(resultId) {
    // TODO: Implement result viewer modal
    addConsoleLog('View result: ' + resultId, 'info');
}

// Listen for Socket.IO events
if (window.topUpAgentSocketIO && window.topUpAgentSocketIO.socket) {
    window.topUpAgentSocketIO.socket.on('automation-started', function(data) {
        addConsoleLog(`🚀 Automation started: ${data.playerId} (${data.redimensionCode})`, 'success');
        refreshQueueStatus();
        refreshRunningAutomations();
    });
    
    window.topUpAgentSocketIO.socket.on('automation-completed', function(data) {
        addConsoleLog(`✅ Automation completed: ${data.playerId} (${data.redimensionCode})`, 'success');
        refreshQueueStatus();
        refreshRunningAutomations();
    });
    
    window.topUpAgentSocketIO.socket.on('automation-failed', function(data) {
        addConsoleLog(`❌ Automation failed: ${data.playerId} - ${data.error}`, 'error');
        refreshQueueStatus();
        refreshRunningAutomations();
    });
    
    window.topUpAgentSocketIO.socket.on('queue-item-added', function(data) {
        addConsoleLog(`📝 Queue item added: ${data.playerId}`, 'info');
        refreshQueueStatus();
    });
    
    window.topUpAgentSocketIO.socket.on('automation-log', function(data) {
        const levelColors = {
            'error': 'error',
            'warn': 'warning', 
            'info': 'info',
            'debug': 'info'
        };
        addConsoleLog(`[${data.level.toUpperCase()}] ${data.message}`, levelColors[data.level] || 'info');
    });
}
</script>
                <th><label for="top_up_agent_license_key">License Key</label></th>
                <td><input type="text" id="top_up_agent_license_key" name="top_up_agent_license_key" class="regular-text" required></td>
            </tr>
        </table>
        <p><input type="submit" name="top_up_agent_run_automation" class="button-primary" value="Run Automation"></p>
    </form>
    
    <h2>Recent Logs</h2>
    <?php if ($logs): ?>
    <table class="widefat">
        <thead>
            <tr>
                <th>Time</th>
                <th>Level</th>
                <th>Message</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?php echo esc_html($log['timestamp'] ?? ''); ?></td>
                <td><?php echo esc_html($log['level'] ?? ''); ?></td>
                <td><?php echo esc_html($log['message'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p><em>No logs found.</em></p>
    <?php endif; ?>
    
    <h2>Running Automations</h2>
    <p>Currently running: <strong><?php echo $running_count; ?></strong> automation(s)</p>
    
    <h2>Automation History</h2>
    <p><em>View detailed automation history in the server logs above.</em></p>
</div>
