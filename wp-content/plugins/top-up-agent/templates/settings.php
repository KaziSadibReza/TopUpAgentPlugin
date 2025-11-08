<?php
// Handle form submission
$message = '';
if (isset($_POST['top_up_agent_settings_submit']) && check_admin_referer('top_up_agent_settings')) {
    update_option('top_up_agent_server_url', sanitize_text_field($_POST['top_up_agent_server_url']));
    update_option('top_up_agent_api_key', sanitize_text_field($_POST['top_up_agent_api_key']));
    update_option('top_up_agent_auto_run_on_processing', isset($_POST['top_up_agent_auto_run_on_processing']));
    update_option('top_up_agent_player_id_meta_key', sanitize_text_field($_POST['top_up_agent_player_id_meta_key']));
    update_option('top_up_agent_support_whatsapp', sanitize_text_field($_POST['top_up_agent_support_whatsapp']));
    
    $message = '<div class="updated"><p>Settings saved successfully.</p></div>';
}

$server_url = esc_attr(get_option('top_up_agent_server_url', 'https://server.uidtopupbd.com'));
$api_key = esc_attr(get_option('top_up_agent_api_key', ''));
$auto_run_on_processing = get_option('top_up_agent_auto_run_on_processing', false);
$player_id_meta_key = esc_attr(get_option('top_up_agent_player_id_meta_key', 'player_id'));
$support_whatsapp = esc_attr(get_option('top_up_agent_support_whatsapp', ''));

// Get server status
require_once plugin_dir_path(__FILE__) . '../includes/api-integration/class-api-client.php';
$api = new Top_Up_Agent_API_Client();
$status_data = $api->health_check();

$status = '';
if (is_wp_error($status_data)) {
    $status = '<span style="color:red;">Server not reachable: ' . esc_html($status_data->get_error_message()) . '</span>';
} elseif (!empty($status_data['status'])) {
    $status = '<span style="color:green;">Server running (v' . esc_html($status_data['version'] ?? 'Unknown') . ')</span>';
} else {
    $status = '<span style="color:orange;">Server reachable, but unexpected response.</span>';
}

// Get WooCommerce products
$products = [];
if (class_exists('WooCommerce')) {
    $products = wc_get_products(['limit' => -1, 'status' => 'publish']);
}
?>

<div class="wrap">
    <h1>Settings</h1>
    <?php echo $message; ?>

    <form method="post">
        <?php wp_nonce_field('top_up_agent_settings'); ?>

        <h2>Server Configuration</h2>
        <table class="form-table">
            <tr>
                <th><label for="top_up_agent_server_url">Server URL</label></th>
                <td><input type="text" id="top_up_agent_server_url" name="top_up_agent_server_url"
                        value="<?php echo $server_url; ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="top_up_agent_api_key">API Key</label></th>
                <td><input type="text" id="top_up_agent_api_key" name="top_up_agent_api_key"
                        value="<?php echo $api_key; ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th>Server Status</th>
                <td><?php echo $status; ?></td>
            </tr>
        </table>

        <?php if (class_exists('WooCommerce')): ?>
        <h2>WooCommerce Automation</h2>
        <table class="form-table">
            <tr>
                <th><label for="top_up_agent_player_id_meta_key">Player ID Meta Key</label></th>
                <td>
                    <input type="text" id="top_up_agent_player_id_meta_key" name="top_up_agent_player_id_meta_key"
                        value="<?php echo $player_id_meta_key; ?>" class="regular-text" required>
                    <p class="description">The meta key used to store Player ID in user profiles and orders. Default:
                        'player_id'</p>
                </td>
            </tr>
            <tr>
                <th>Auto-run on Processing Orders</th>
                <td>
                    <label>
                        <input type="checkbox" name="top_up_agent_auto_run_on_processing"
                            <?php checked($auto_run_on_processing); ?>>
                        Automatically run automation when orders change to "Processing" status
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="top_up_agent_support_whatsapp">Customer Support WhatsApp Number</label></th>
                <td>
                    <input type="text" id="top_up_agent_support_whatsapp" name="top_up_agent_support_whatsapp"
                        value="<?php echo $support_whatsapp; ?>" class="regular-text" 
                        placeholder="+8801234567890">
                    <p class="description">Enter WhatsApp number with country code (e.g., +8801234567890). A "Message Support" button will appear for customers when automation fails.</p>
                </td>
            </tr>
            <tr>
                <th>Product Automation Settings</th>
                <td>
                    <p class="description">
                        <strong>Product automation settings have been moved to the <a
                                href="<?php echo admin_url('admin.php?page=top-up-agent-license-keys'); ?>">License
                                Keys</a> page.</strong><br>
                        Configure which products should trigger automation in the License Key Management section.
                    </p>
                </td>
            </tr>
        </table>
        <?php else: ?>
        <h2>WooCommerce Integration</h2>
        <p><em>WooCommerce is not installed or activated. Install WooCommerce to enable automatic order processing.</em>
        </p>
        <?php endif; ?>

        <p><input type="submit" name="top_up_agent_settings_submit" class="button-primary" value="Save Changes"></p>
    </form>
</div>