<?php
class Top_Up_Agent_License_Keys_Controller {
    private $license_manager;
    private $product_manager;
    private $automation_manager;
    private $form_handler;
    private $ui_renderer;
    
    public function __construct() {
        $this->init_classes();
    }
    
    private function init_classes() {
        // Load all the required classes from organized folders
        require_once plugin_dir_path(__FILE__) . 'class-top-up-agent-license-key-manager.php';
        require_once plugin_dir_path(__FILE__) . 'class-top-up-agent-product-manager.php';
        require_once plugin_dir_path(__FILE__) . '../automation/class-top-up-agent-automation-manager.php';
        require_once plugin_dir_path(__FILE__) . '../ui/class-top-up-agent-form-handler.php';
        require_once plugin_dir_path(__FILE__) . '../ui/class-top-up-agent-ui-renderer.php';

        // Initialize all managers
        $this->license_manager = new Top_Up_Agent_License_Key_Manager();
        $this->product_manager = new Top_Up_Agent_Product_Manager();
        $this->automation_manager = new Top_Up_Agent_Automation_Manager($this->license_manager, $this->product_manager);
        $this->form_handler = new Top_Up_Agent_Form_Handler($this->license_manager, $this->product_manager, $this->automation_manager);
        $this->ui_renderer = new Top_Up_Agent_UI_Renderer($this->license_manager, $this->product_manager, $this->automation_manager);
    }
    
    public function render_page() {
        // Handle form submissions
        $message = $this->form_handler->handle_requests();

        // Get edit key data if editing
        $edit_key = null;
        if (isset($_GET['edit']) && !empty($_GET['edit'])) {
            $edit_key_id = intval($_GET['edit']);
            $edit_key = $this->license_manager->get_license_key($edit_key_id);
        }
        
        // Get edit group data if editing group
        $edit_group_id = null;
        if (isset($_GET['edit_group']) && !empty($_GET['edit_group'])) {
            $edit_group_id = sanitize_text_field($_GET['edit_group']);
        }
        
        // Render the page
        ?>
<div class="wrap">
    <h1>License Key Management</h1>

    <?php echo $message; ?>

    <!-- Edit License Key Form -->
    <?php if ($edit_key): ?>
    <?php $this->ui_renderer->render_edit_form($edit_key); ?>
    <?php endif; ?>

    <!-- Edit Group License Keys Form -->
    <?php if ($edit_group_id): ?>
    <?php $this->ui_renderer->render_edit_group_form($edit_group_id); ?>
    <?php endif; ?>

    <!-- Section Toggle Controls -->
    <?php if (!$edit_key && !$edit_group_id): ?>
    <div class="section-toggles">
        <button type="button" class="toggle-btn" data-target="add-license-section">
            <span class="text">Add New License Key</span>
            <span class="section-status hidden">Hidden</span>
        </button>
        <button type="button" class="toggle-btn" data-target="bulk-import-section">
            <span class="text">Bulk Import License Keys</span>
            <span class="section-status hidden">Hidden</span>
        </button>
        <button type="button" class="toggle-btn" data-target="automation-section">
            <span class="text">Automation Settings</span>
            <span class="section-status hidden">Hidden</span>
        </button>
    </div>

    <!-- Add New License Key -->
    <div id="add-license-section" class="collapsible-section hidden">
        <?php $this->ui_renderer->render_add_form(); ?>
    </div>

    <!-- Automation Settings -->
    <div id="automation-section" class="collapsible-section hidden">
        <?php $this->automation_manager->render_automation_settings_form(); ?>
    </div>

    <!-- Bulk Import -->
    <div id="bulk-import-section" class="collapsible-section hidden">
        <?php $this->ui_renderer->render_bulk_import_form(); ?>
    </div>
    <?php endif; ?>

    <!-- License Keys List -->
    <?php $this->ui_renderer->render_license_keys_table(); ?>
</div>

<?php
    }
    
    // Getter methods for external access if needed
    public function get_license_manager() {
        return $this->license_manager;
    }
    
    public function get_product_manager() {
        return $this->product_manager;
    }
    
    public function get_automation_manager() {
        return $this->automation_manager;
    }
}