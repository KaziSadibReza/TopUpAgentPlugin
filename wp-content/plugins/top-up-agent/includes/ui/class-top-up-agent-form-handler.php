<?php
/**
 * Form Handler - Simple automation forms that use server queue API
 */
class Top_Up_Agent_Form_Handler {
    private $license_manager;
    private $product_manager;
    private $automation_manager;
    private $api_client;
    
    public function __construct($license_manager, $product_manager, $automation_manager) {
        $this->license_manager = $license_manager;
        $this->product_manager = $product_manager;
        $this->automation_manager = $automation_manager;
        
        // Include API client
        require_once plugin_dir_path(__FILE__) . '../api-integration/class-api-client.php';
        $this->api_client = new Top_Up_Agent_API_Client();
    }

    public function handle_requests() {
        $message = '';

        // Handle manual single automation
        if (isset($_POST['manual_automation']) && check_admin_referer('manual_automation')) {
            $license_key = sanitize_text_field($_POST['license_key']);
            $player_id = sanitize_text_field($_POST['player_id']);
            
            if ($license_key && $player_id) {
                $result = $this->api_client->add_to_queue([
                    'type' => 'automation',
                    'license_key' => $license_key,
                    'player_id' => $player_id,
                    'priority' => 1,
                    'metadata' => [
                        'queue_type' => 'manual',
                        'source' => 'form_handler'
                    ]
                ]);
                
                if ($result && !isset($result['error'])) {
                    $message = "<div class='updated'><p>✅ Automation added to server queue successfully!</p></div>";
                } else {
                    $error_msg = isset($result['error']) ? $result['error'] : 'Unknown error';
                    $message = "<div class='error'><p>❌ Failed to add automation to server queue: {$error_msg}</p></div>";
                }
            } else {
                $message = "<div class='error'><p>❌ Please provide both license key and player ID</p></div>";
            }
        }

        // Handle automation settings update
        if (isset($_POST['update_automation_settings']) && check_admin_referer('update_automation_settings')) {
            $enabled_products = isset($_POST['automation_enabled_products']) ? array_map('intval', $_POST['automation_enabled_products']) : [];
            
            // Get current settings to compare
            $current_settings = get_option('top_up_agent_products_automation_enabled', []);
            
            $result = $this->automation_manager->update_automation_settings($enabled_products);
            
            // Verify the settings were actually saved
            $new_settings = get_option('top_up_agent_products_automation_enabled', []);
            
            // Check if settings actually changed (update_option returns false if no change)
            $settings_changed = $current_settings !== $new_settings;
            
            // Consider it successful if settings match what we wanted to save OR if they actually changed
            $arrays_match = empty(array_diff($enabled_products, $new_settings)) && empty(array_diff($new_settings, $enabled_products));
            $success = $result || $arrays_match;
            
            if ($success) {
                $count = count($enabled_products);
                $message = "<div class='updated'><p>✅ Automation settings updated successfully! {$count} products enabled for automation.</p></div>";
            } else {
                $message = "<div class='error'><p>❌ Failed to update automation settings.</p></div>";
            }
        }

        // Handle group automation
        if (isset($_POST['group_automation']) && check_admin_referer('group_automation')) {
            $player_id = sanitize_text_field($_POST['player_id']);
            $license_keys = [];
            
            if (isset($_POST['group_license_keys'])) {
                $license_keys = array_filter(array_map('sanitize_text_field', $_POST['group_license_keys']));
            }
            
            if ($player_id && !empty($license_keys)) {
                // Add each license key as a separate queue item
                $success_count = 0;
                $total_count = count($license_keys);
                
                foreach ($license_keys as $license_key) {
                    $result = $this->api_client->add_to_queue([
                        'type' => 'automation',
                        'license_key' => $license_key,
                        'player_id' => $player_id,
                        'priority' => 2,
                        'metadata' => [
                            'queue_type' => 'group',
                            'source' => 'form_handler',
                            'group_size' => $total_count
                        ]
                    ]);
                    
                    if ($result && !isset($result['error'])) {
                        $success_count++;
                    }
                }
                
                if ($success_count > 0) {
                    $message = "<div class='updated'><p>✅ Group automation added to server queue! Successfully added: {$success_count}/{$total_count} items</p></div>";
                } else {
                    $message = "<div class='error'><p>❌ Failed to add group automation to server queue</p></div>";
                }
            } else {
                $message = "<div class='error'><p>❌ Please provide player ID and at least one license key</p></div>";
            }
        }

        // Handle add license key
        if (isset($_POST['add_license_key']) && check_admin_referer('add_license_key')) {
            $license_key = sanitize_text_field($_POST['license_key']);
            $selected_product = isset($_POST['selected_products']) && !empty($_POST['selected_products']) ? intval($_POST['selected_products']) : null;
            $selected_products = $selected_product ? [$selected_product] : []; // Convert single product to array for compatibility
            $is_group_product = isset($_POST['is_group_product']) ? 1 : 0;
            $group_license_count = isset($_POST['group_license_count']) ? intval($_POST['group_license_count']) : 3;
            
            // Handle group products (multiple license keys as a set)
            if ($is_group_product && isset($_POST['multiple_license_keys']) && !empty($_POST['multiple_license_keys'])) {
                $multiple_keys = array_filter(array_map('trim', explode("\n", $_POST['multiple_license_keys'])));
                
                if (count($multiple_keys) > 0) {
                    $result = $this->license_manager->add_group_license_keys(
                        $multiple_keys, 
                        $selected_products, 
                        $group_license_count, 
                        $this->product_manager
                    );
                    
                    if ($result['success_count'] > 0 && empty($result['errors'])) {
                        $message = "<div class='updated'><p>✅ Successfully added group '{$result['group_name']}' with {$result['success_count']} license keys!</p></div>";
                    } elseif ($result['success_count'] > 0 && !empty($result['errors'])) {
                        $error_list = implode(', ', $result['errors']);
                        $message = "<div class='updated'><p>⚠️ Added group '{$result['group_name']}' with {$result['success_count']} keys. Failed keys: {$error_list}</p></div>";
                    } else {
                        $message = "<div class='error'><p>❌ Failed to add group. All keys may be duplicates or invalid.</p></div>";
                    }
                } else {
                    $message = "<div class='error'><p>❌ Please provide license keys for the group.</p></div>";
                }
            } else {
                // Single license key
                if ($license_key) {
                    $result = $this->license_manager->add_license_key($license_key, $selected_products, $is_group_product, $group_license_count);
                    if ($result) {
                        $message = "<div class='updated'><p>✅ License key added successfully!</p></div>";
                    } else {
                        $message = "<div class='error'><p>❌ Failed to add license key. It may already exist.</p></div>";
                    }
                } else {
                    $message = "<div class='error'><p>❌ Please provide a license key.</p></div>";
                }
            }
        }

        // Handle edit license key (single key)
        if (isset($_POST['edit_license_key']) && check_admin_referer('edit_license_key')) {
            $key_id = intval($_POST['key_id']);
            $license_key = sanitize_text_field($_POST['license_key']);
            $selected_product = isset($_POST['selected_products']) && !empty($_POST['selected_products']) ? intval($_POST['selected_products']) : null;
            $selected_products = $selected_product ? [$selected_product] : []; // Convert single product to array for compatibility
            $is_group_product = isset($_POST['is_group_product']) ? 1 : 0;
            $group_license_count = isset($_POST['group_license_count']) ? intval($_POST['group_license_count']) : 3;
            
            if ($key_id && $license_key) {
                $result = $this->license_manager->update_license_key($key_id, $license_key, $selected_products);
                if ($result) {
                    $message = "<div class='updated'><p>✅ License key updated successfully!</p></div>";
                    // Redirect to avoid resubmission
                    wp_redirect(admin_url('admin.php?page=top-up-agent-license-keys'));
                    exit;
                } else {
                    $message = "<div class='error'><p>❌ Failed to update license key.</p></div>";
                }
            } else {
                $message = "<div class='error'><p>❌ Please provide all required fields.</p></div>";
            }
        }

        // Handle edit group license keys
        if (isset($_POST['edit_group_keys']) && check_admin_referer('edit_group_keys')) {
            $group_id = sanitize_text_field($_POST['group_id']);
            $selected_products = isset($_POST['selected_products']) ? array_map('intval', $_POST['selected_products']) : [];
            $group_license_count = isset($_POST['group_license_count']) ? intval($_POST['group_license_count']) : 3;
            $multiple_keys = array_filter(array_map('trim', explode("\n", $_POST['multiple_license_keys'])));
            
            if ($group_id && !empty($multiple_keys)) {
                $result = $this->license_manager->update_group_license_keys(
                    $group_id, 
                    $multiple_keys, 
                    $selected_products, 
                    $group_license_count, 
                    $this->product_manager
                );
                
                if ($result['success_count'] > 0 && empty($result['errors'])) {
                    $message = "<div class='updated'><p>✅ Successfully updated group '{$result['group_name']}' with {$result['success_count']} license keys!</p></div>";
                    // Redirect to avoid resubmission
                    wp_redirect(admin_url('admin.php?page=top-up-agent-license-keys'));
                    exit;
                } elseif ($result['success_count'] > 0 && !empty($result['errors'])) {
                    $error_list = implode(', ', $result['errors']);
                    $message = "<div class='updated'><p>⚠️ Updated group '{$result['group_name']}' with {$result['success_count']} keys. Failed keys: {$error_list}</p></div>";
                } else {
                    $message = "<div class='error'><p>❌ Failed to update group. All keys may be duplicates or invalid.</p></div>";
                }
            } else {
                $message = "<div class='error'><p>❌ Please provide group ID and license keys.</p></div>";
            }
        }

        // Handle delete group license keys
        if (isset($_POST['delete_group_keys']) && check_admin_referer('delete_group_keys')) {
            $group_id = sanitize_text_field($_POST['group_id']);
            if ($group_id) {
                $result = $this->license_manager->delete_group_license_keys($group_id);
                if ($result) {
                    $message = "<div class='updated'><p>✅ Group license keys deleted successfully!</p></div>";
                } else {
                    $message = "<div class='error'><p>❌ Failed to delete group license keys.</p></div>";
                }
            }
        }

        // Handle delete license key
        if (isset($_POST['delete_license_key']) && check_admin_referer('delete_license_key')) {
            $key_id = intval($_POST['key_id']);
            if ($key_id) {
                $result = $this->license_manager->delete_license_key($key_id);
                if ($result) {
                    $message = "<div class='updated'><p>✅ License key deleted successfully!</p></div>";
                } else {
                    $message = "<div class='error'><p>❌ Failed to delete license key.</p></div>";
                }
            }
        }

        // Handle bulk import
        if (isset($_POST['bulk_import']) && check_admin_referer('bulk_import_license_keys')) {
            $bulk_keys = sanitize_textarea_field($_POST['bulk_license_keys']);
            $selected_product = isset($_POST['bulk_selected_products']) && !empty($_POST['bulk_selected_products']) ? intval($_POST['bulk_selected_products']) : null;
            $selected_products = $selected_product ? [$selected_product] : []; // Convert single product to array for compatibility
            $is_group_product = isset($_POST['bulk_is_group_product']) ? 1 : 0;
            $group_license_count = isset($_POST['bulk_group_license_count']) ? intval($_POST['bulk_group_license_count']) : 3;
            
            // Debug logging
            error_log("Top Up Agent Bulk Import Debug:");
            error_log("- Selected product: " . ($selected_product ? $selected_product : 'none (all products)'));
            error_log("- Selected products array: " . print_r($selected_products, true));
            error_log("- Is group product: " . $is_group_product);
            error_log("- Group license count: " . $group_license_count);
            
            if ($bulk_keys) {
                $keys = array_filter(array_map('trim', explode("\n", $bulk_keys)));
                
                if ($is_group_product) {
                    // For group products, split keys into groups and create multiple group sets
                    $groups = array_chunk($keys, $group_license_count);
                    $total_success = 0;
                    $total_errors = [];
                    $group_names = [];
                    
                    foreach ($groups as $group_keys) {
                        if (count($group_keys) > 0) {
                            $result = $this->license_manager->add_group_license_keys(
                                $group_keys, 
                                $selected_products, 
                                $group_license_count, 
                                $this->product_manager
                            );
                            $total_success += $result['success_count'];
                            $total_errors = array_merge($total_errors, $result['errors']);
                            $group_names[] = $result['group_name'];
                        }
                    }
                    
                    if ($total_success > 0 && empty($total_errors)) {
                        $message = "<div class='updated'><p>✅ Successfully imported {$total_success} license keys into " . count($group_names) . " groups!</p></div>";
                    } elseif ($total_success > 0 && !empty($total_errors)) {
                        $error_count = count($total_errors);
                        $message = "<div class='updated'><p>⚠️ Imported {$total_success} keys into " . count($group_names) . " groups. {$error_count} keys failed (possibly duplicates).</p></div>";
                    } else {
                        $message = "<div class='error'><p>❌ Failed to import license keys. All keys may be duplicates or invalid.</p></div>";
                    }
                } else {
                    // Individual keys
                    $success_count = 0;
                    $error_count = 0;
                    $errors = [];
                    
                    foreach ($keys as $key) {
                        $key = sanitize_text_field($key);
                        if (!empty($key)) {
                            $result = $this->license_manager->add_license_key($key, $selected_products, $is_group_product, $group_license_count);
                            if ($result) {
                                $success_count++;
                            } else {
                                $error_count++;
                                $errors[] = $key;
                            }
                        }
                    }
                    
                    if ($success_count > 0 && $error_count == 0) {
                        $message = "<div class='updated'><p>✅ Successfully imported {$success_count} license keys!</p></div>";
                    } elseif ($success_count > 0 && $error_count > 0) {
                        $message = "<div class='updated'><p>⚠️ Imported {$success_count} license keys successfully. {$error_count} keys failed (possibly duplicates): " . implode(', ', array_slice($errors, 0, 10)) . ($error_count > 10 ? '...' : '') . "</p></div>";
                    } else {
                        $message = "<div class='error'><p>❌ Failed to import license keys. All keys may be duplicates or invalid.</p></div>";
                    }
                }
            } else {
                $message = "<div class='error'><p>❌ Please provide license keys to import.</p></div>";
            }
        }

        // Handle CSV export
        if (isset($_POST['export_all']) && check_admin_referer('export_license_keys')) {
            $this->export_license_keys_csv();
            return ''; // No message needed as this redirects
        }
        
        if (isset($_POST['export_filtered']) && check_admin_referer('export_license_keys')) {
            $status_filter = sanitize_text_field($_POST['export_status'] ?? '');
            $this->export_license_keys_csv($status_filter);
            return ''; // No message needed as this redirects
        }

        return $message;
    }
    
    /**
     * Export license keys to CSV
     */
    private function export_license_keys_csv($status_filter = '', $product_filter = '') {
        $license_keys = $this->license_manager->export_to_csv($status_filter, $product_filter);
        
        // Set headers for CSV download
        $filename = 'license-keys-' . date('Y-m-d-H-i-s') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Create file pointer
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, [
            'ID',
            'License Key',
            'Product IDs',
            'Status',
            'Is Group Product',
            'Group License Count',
            'Created Date',
            'Used Date'
        ]);
        
        // Add data rows
        foreach ($license_keys as $key) {
            fputcsv($output, [
                $key->id,
                $key->license_key,
                $key->product_ids ?: 'All Products',
                ucfirst($key->status),
                isset($key->is_group_product) && $key->is_group_product ? 'Yes' : 'No',
                isset($key->group_license_count) ? $key->group_license_count : '',
                $key->created_date,
                $key->used_date ?: ''
            ]);
        }
        
        fclose($output);
        exit;
    }

    public function render_manual_automation_form() {
        ?>
<div class="postbox">
    <h2 class="hndle">🚀 Manual Single Automation</h2>
    <div class="inside">
        <form method="post">
            <?php wp_nonce_field('manual_automation'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="license_key">License Key</label></th>
                    <td><input type="text" name="license_key" id="license_key" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th><label for="player_id">Player ID</label></th>
                    <td><input type="text" name="player_id" id="player_id" class="regular-text" required /></td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="manual_automation" class="button button-primary">Add to Server
                    Queue</button>
            </p>
        </form>
    </div>
</div>
<?php
    }

    public function render_group_automation_form() {
        ?>
<div class="postbox">
    <h2 class="hndle">📦 Group Automation</h2>
    <div class="inside">
        <form method="post">
            <?php wp_nonce_field('group_automation'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="group_player_id">Player ID</label></th>
                    <td><input type="text" name="player_id" id="group_player_id" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th><label>License Keys</label></th>
                    <td>
                        <div id="group-license-keys">
                            <input type="text" name="group_license_keys[]" placeholder="License Key 1"
                                class="regular-text" style="margin-bottom: 8px;" />
                            <input type="text" name="group_license_keys[]" placeholder="License Key 2"
                                class="regular-text" style="margin-bottom: 8px;" />
                            <input type="text" name="group_license_keys[]" placeholder="License Key 3"
                                class="regular-text" style="margin-bottom: 8px;" />
                        </div>
                        <button type="button" onclick="addLicenseKeyInput()" class="button button-secondary">Add
                            More</button>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="group_automation" class="button button-primary">Add Group to Server
                    Queue</button>
            </p>
        </form>
    </div>
</div>

<script>
function addLicenseKeyInput() {
    const container = document.getElementById('group-license-keys');
    const count = container.children.length + 1;
    const input = document.createElement('input');
    input.type = 'text';
    input.name = 'group_license_keys[]';
    input.placeholder = 'License Key ' + count;
    input.className = 'regular-text';
    input.style.marginBottom = '8px';
    container.insertBefore(input, container.lastElementChild);
}
</script>
<?php
    }
}