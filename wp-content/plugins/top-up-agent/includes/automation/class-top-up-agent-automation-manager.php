<?php
class Top_Up_Agent_Automation_Manager {
    private $license_manager;
    private $product_manager;
    private $api;
    
    public function __construct($license_manager, $product_manager) {
        $this->license_manager = $license_manager;
        $this->product_manager = $product_manager;
        require_once plugin_dir_path(__FILE__) . '../api-integration/class-api-client.php';
        $this->api = new Top_Up_Agent_API_Client();
    }

    public function get_automation_enabled_products() {
        return get_option('top_up_agent_products_automation_enabled', []);
    }

    public function update_automation_settings($enabled_products) {
        $enabled_products = array_map('intval', $enabled_products);
        
        // Log what we're trying to save
        error_log('Top Up Agent Automation Manager: Attempting to save products: ' . implode(', ', $enabled_products));
        
        // Get current value for comparison
        $current_value = get_option('top_up_agent_products_automation_enabled', []);
        error_log('Top Up Agent Automation Manager: Current value: ' . implode(', ', $current_value));
        
        // Attempt the update
        $result = update_option('top_up_agent_products_automation_enabled', $enabled_products);
        error_log('Top Up Agent Automation Manager: update_option returned: ' . ($result ? 'true' : 'false'));
        
        // Verify the save worked
        $new_value = get_option('top_up_agent_products_automation_enabled', []);
        error_log('Top Up Agent Automation Manager: Value after save: ' . implode(', ', $new_value));
        
        // Check if the values match what we wanted to save
        $values_match = empty(array_diff($enabled_products, $new_value)) && empty(array_diff($new_value, $enabled_products));
        error_log('Top Up Agent Automation Manager: Values match intended: ' . ($values_match ? 'yes' : 'no'));
        
        // Return true if update_option succeeded OR if the values match what we wanted
        // (update_option returns false if the value didn't change)
        return $result || $values_match;
    }

    public function render_automation_settings_form() {
        $enabled_products = $this->get_automation_enabled_products();
        $all_products = $this->product_manager->get_all_products();
        ?>
<div class="card">
    <h2>Automation Settings</h2>
    <form method="post">
        <?php wp_nonce_field('update_automation_settings'); ?>
        <h3>Enable Automation for Products:</h3>
        <select name="automation_enabled_products[]" id="automation_enabled_products" multiple class="modern-select">
            <?php foreach ($all_products as $product): ?>
            <?php 
                        $enabled = in_array($product['id'], $enabled_products);
                        $has_license_keys = $this->license_manager->get_unused_license_key_count($product['id']);
                        ?>
            <option value="<?php echo $product['id']; ?>" <?php echo $enabled ? 'selected' : ''; ?>
                <?php echo !$has_license_keys ? 'style="color: red;"' : ''; ?>>
                <?php echo esc_html($product['name'] . ' (ID: ' . $product['id'] . ')'); ?>
                <?php if ($product['type'] === 'variation'): ?>
                - Variation
                <?php endif; ?>
                <?php if (!$has_license_keys): ?>
                (No license keys available)
                <?php endif; ?>
            </option>
            <?php endforeach; ?>
        </select>
        <p class="submit">
            <input type="submit" name="update_automation_settings" class="button-primary" value="Update Settings">
        </p>
    </form>
</div>
<?php
    }
}