<?php
class Top_Up_Agent_UI_Renderer {
    private $license_manager;
    private $product_manager;
    private $automation_manager;
    
    public function __construct($license_manager, $product_manager, $automation_manager) {
        $this->license_manager = $license_manager;
        $this->product_manager = $product_manager;
        $this->automation_manager = $automation_manager;
    }

    public function render_license_keys_table() {
        // Get filter parameters
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $product_filter = isset($_GET['product']) ? intval($_GET['product']) : '';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        // Get license keys with filters (individual keys only)
        $license_keys = $this->license_manager->get_all_license_keys($search, $status_filter, $product_filter, $per_page, $offset);
        $total_keys = $this->license_manager->get_license_keys_count($search, $status_filter, $product_filter);
        $total_pages = ceil($total_keys / $per_page);
        
        // Get groups
        $groups = $this->license_manager->get_all_groups();
        
        // Get statistics with null safety
        $stats = $this->license_manager->get_statistics();
        
        ?>
<!-- Modern Statistics Dashboard -->
<div class="license-stats-grid">
    <div class="stat-card stat-total">
        <div class="stat-icon"></div>
        <div class="stat-content">
            <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
            <div class="stat-label">Total Keys</div>
        </div>
    </div>
    <div class="stat-card stat-available">
        <div class="stat-icon"></div>
        <div class="stat-content">
            <div class="stat-number"><?php echo number_format($stats['unused'] ?? 0); ?></div>
            <div class="stat-label">Available</div>
        </div>
    </div>
    <div class="stat-card stat-used">
        <div class="stat-icon"></div>
        <div class="stat-content">
            <div class="stat-number"><?php echo number_format($stats['used'] ?? 0); ?></div>
            <div class="stat-label">Used</div>
        </div>
    </div>
    <div class="stat-card stat-group">
        <div class="stat-icon"></div>
        <div class="stat-content">
            <div class="stat-number"><?php echo number_format($stats['group_products'] ?? 0); ?></div>
            <div class="stat-label">Group Keys</div>
        </div>
    </div>
    <div class="stat-card stat-recent">
        <div class="stat-icon"></div>
        <div class="stat-content">
            <div class="stat-number"><?php echo number_format($stats['recent_additions'] ?? 0); ?></div>
            <div class="stat-label">Added This Week</div>
        </div>
    </div>
</div>

<!-- Advanced Search and Filter Panel -->
<div class="search-filter-panel license-manager-filters">
    <h3>🔍 Search & Filter License Keys</h3>
    <form method="get" class="filter-form">
        <input type="hidden" name="page" value="top-up-agent-license-keys">

        <div class="filter-grid">
            <div class="filter-group">
                <label for="search">Search License Keys</label>
                <input type="text" name="search" id="search" value="<?php echo esc_attr($search); ?>"
                    placeholder="Search by license key..." class="filter-input custom_filter">
            </div>

            <div class="filter-group">
                <label for="status">Status Filter</label>
                <select name="status" id="status" class="filter-select">
                    <option value="">All Statuses</option>
                    <option value="unused" <?php selected($status_filter, 'unused'); ?>>✅ Available Only</option>
                    <option value="used" <?php selected($status_filter, 'used'); ?>>❌ Used Only</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="product">Product Filter</label>
                <select name="product" id="product" class="filter-select">
                    <option value="">All Products</option>
                    <?php 
                            $products = $this->product_manager->get_all_products();
                            foreach ($products as $product): 
                            ?>
                    <option value="<?php echo $product['id']; ?>" <?php selected($product_filter, $product['id']); ?>>
                        <?php echo esc_html($product['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <?php if ($search || $status_filter || $product_filter): ?>
                <a href="<?php echo admin_url('admin.php?page=top-up-agent-license-keys'); ?>"
                    class="btn btn-secondary">Clear All</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <?php if ($search || $status_filter || $product_filter): ?>
    <div class="active-filters">
        <strong>Active Filters:</strong>
        <?php if ($search): ?>
        <span class="filter-tag">Search: "<?php echo esc_html($search); ?>"</span>
        <?php endif; ?>
        <?php if ($status_filter): ?>
        <span class="filter-tag">Status: <?php echo ucfirst($status_filter); ?></span>
        <?php endif; ?>
        <?php if ($product_filter): ?>
        <?php $filtered_product = $this->product_manager->get_product_by_id($product_filter); ?>
        <span class="filter-tag">Product:
            <?php echo $filtered_product ? esc_html($filtered_product['name']) : 'Unknown'; ?></span>
        <?php endif; ?>
        <span class="results-info">Showing <?php echo number_format(count($license_keys)); ?> of
            <?php echo number_format($total_keys); ?> results</span>
    </div>
    <?php endif; ?>
</div>

<!-- Export Tools Panel -->
<div class="export-panel">
    <h3>📤 Export Tools</h3>
    <div class="export-buttons">
        <form method="post" style="display: inline-block;">
            <?php wp_nonce_field('export_license_keys'); ?>
            <button type="submit" name="export_all" class="btn btn-export">📄 Export All Keys (CSV)</button>
        </form>
        <form method="post" style="display: inline-block;">
            <?php wp_nonce_field('export_license_keys'); ?>
            <input type="hidden" name="export_status" value="unused">
            <button type="submit" name="export_filtered" class="btn btn-export">✅ Export Available Keys</button>
        </form>
        <form method="post" style="display: inline-block;">
            <?php wp_nonce_field('export_license_keys'); ?>
            <input type="hidden" name="export_status" value="used">
            <button type="submit" name="export_filtered" class="btn btn-export">❌ Export Used Keys</button>
        </form>
    </div>
</div>

<!-- Group License Keys Section -->
<?php if (!empty($groups)): ?>
<div class="license-table-container">
    <div class="table-header">
        <h3 data-type="group">Group License Keys</h3>
        <span class="results-info">Showing <?php echo count($groups); ?> group(s)</span>
    </div>

    <div class="table-wrapper">
        <table class="license-table">
            <thead>
                <tr>
                    <th>Group Name</th>
                    <th>Keys Count</th>
                    <th>Available/Used</th>
                    <th>Products</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $group): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($group->group_name); ?></strong>
                        <div class="group-id">ID: <?php echo esc_html($group->group_id); ?></div>
                    </td>
                    <td>
                        <span class="key-count-badge"><?php echo $group->key_count; ?> keys</span>
                    </td>
                    <td>
                        <div class="status-indicators">
                            <span class="status-available"><?php echo $group->unused_count; ?></span>
                            <span class="status-used"><?php echo $group->used_count; ?></span>
                        </div>
                    </td>
                    <td>
                        <?php 
                        if (empty($group->product_ids)) {
                            echo '<span class="product-all">All Products</span>';
                        } else {
                            $product_names = [];
                            $product_ids = explode(',', $group->product_ids);
                            foreach ($product_ids as $pid) {
                                $product = $this->product_manager->get_product_by_id($pid);
                                if ($product) $product_names[] = $product['name'];
                            }
                            echo esc_html(implode(', ', $product_names));
                        }
                        ?>
                    </td>
                    <td><?php echo date('M j, Y', strtotime($group->created_date)); ?></td>
                    <td>
                        <div class="action-buttons">
                            <a href="?page=top-up-agent-license-keys&edit_group=<?php echo $group->group_id; ?>"
                                class="btn btn-edit" title="Edit Group">Edit</a>
                            <form method="post" style="display: inline;"
                                onsubmit="return confirm('Are you sure you want to delete this entire group?');">
                                <?php wp_nonce_field('delete_group_keys'); ?>
                                <input type="hidden" name="group_id" value="<?php echo $group->group_id; ?>">
                                <button type="submit" name="delete_group_keys" class="btn btn-delete"
                                    title="Delete Group"></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Individual License Keys Table -->
<div class="license-table-container">
    <div class="table-header">
        <h3>Individual License Keys (<?php echo number_format($total_keys); ?> total)</h3>
    </div>

    <?php if (!empty($license_keys)): ?>
    <div class="responsive-table">
        <table class="license-table">
            <thead>
                <tr>
                    <th class="col-id">ID</th>
                    <th class="col-license">License Key</th>
                    <th class="col-products">Assigned Products</th>
                    <th class="col-type">Type</th>
                    <th class="col-status">Status</th>
                    <th class="col-dates">Created / Used</th>
                    <th class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($license_keys as $key): ?>
                <tr class="license-row">
                    <td class="col-id"><?php echo $key->id; ?></td>
                    <td class="col-license">
                        <div class="license-key-display">
                            <code><?php echo esc_html($key->license_key); ?></code>
                            <button class="copy-btn"
                                onclick="copyToClipboard('<?php echo esc_js($key->license_key); ?>')"
                                title="Copy to clipboard"></button>
                        </div>
                    </td>
                    <td class="col-products">
                        <?php 
                                    if ($key->product_ids) {
                                        $assigned_product_ids = explode(',', $key->product_ids);
                                        $assigned_products = $this->product_manager->get_products_by_ids($assigned_product_ids);
                                        $product_names = array_map(function($product) {
                                            return $product['name'];
                                        }, $assigned_products);
                                        echo '<span class="product-list">' . esc_html(implode(', ', $product_names)) . '</span>';
                                    } else {
                                        echo '<span class="all-products">All Products</span>';
                                    }
                                    ?>
                    </td>
                    <td class="col-type">
                        <?php 
                                    if (isset($key->is_group_product) && $key->is_group_product) {
                                        echo '<span class="type-badge type-group">GROUP<br><small>(' . (isset($key->group_license_count) ? $key->group_license_count : 3) . ' keys)</small></span>';
                                    } else {
                                        echo '<span class="type-badge type-single">Single</span>';
                                    }
                                    ?>
                    </td>
                    <td class="col-status">
                        <?php if ($key->status === 'unused'): ?>
                        <span class="status-badge status-available">Available</span>
                        <?php else: ?>
                        <span class="status-badge status-used">Used</span>
                        <?php endif; ?>
                    </td>
                    <td class="col-dates">
                        <div class="dates-info">
                            <div class="created-date"><?php echo date('M j, Y', strtotime($key->created_date)); ?>
                            </div>
                            <?php if (!empty($key->used_date)): ?>
                            <div class="used-date"><?php echo date('M j, Y', strtotime($key->used_date)); ?></div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="col-actions">
                        <div class="action-buttons">
                            <a href="<?php echo admin_url('admin.php?page=top-up-agent-license-keys&edit=' . $key->id); ?>"
                                class="btn btn-edit" title="Edit"></a>
                            <form method="post" style="display: inline;"
                                onsubmit="return confirm('Are you sure you want to delete this license key?')">
                                <?php wp_nonce_field('delete_license_key'); ?>
                                <input type="hidden" name="key_id" value="<?php echo $key->id; ?>">
                                <button type="submit" name="delete_license_key" class="btn btn-delete"
                                    title="Delete"></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination-wrapper">
        <div class="pagination-info">
            Showing
            <?php echo number_format((($page - 1) * $per_page) + 1); ?>-<?php echo number_format(min($page * $per_page, $total_keys)); ?>
            of <?php echo number_format($total_keys); ?> items
        </div>
        <div class="pagination-controls">
            <?php if ($page > 1): ?>
            <a class="btn btn-pagination" href="<?php echo add_query_arg('paged', $page - 1); ?>">‹ Previous</a>
            <?php endif; ?>

            <span class="pagination-current">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>

            <?php if ($page < $total_pages): ?>
            <a class="btn btn-pagination" href="<?php echo add_query_arg('paged', $page + 1); ?>">Next ›</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty-state">
        <div class="empty-icon">📄</div>
        <h3>No license keys found</h3>
        <p>No license keys match your current filters.</p>
        <?php if ($search || $status_filter || $product_filter): ?>
        <a href="<?php echo admin_url('admin.php?page=top-up-agent-license-keys'); ?>" class="btn btn-primary">Clear
            Filters</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php
    }

    public function render_edit_form($edit_key) {
        $edit_key_products = $edit_key && $edit_key->product_ids ? 
            array_map('intval', explode(',', $edit_key->product_ids)) : [];
        ?>
<div class="license-form-container">
    <h2>✏️ Edit License Key</h2>
    <form method="post" class="license-form">
        <?php wp_nonce_field('edit_license_key'); ?>
        <input type="hidden" name="key_id" value="<?php echo $edit_key->id; ?>">

        <div class="form-grid">
            <div class="form-group">
                <label for="license_key">License Key</label>
                <input type="text" name="license_key" id="license_key"
                    value="<?php echo esc_attr($edit_key->license_key); ?>" class="form-input" required>
            </div>

            <div class="form-group full-width">
                <label for="edit_selected_products">Assigned Products</label>
                <?php $this->product_manager->render_product_options($edit_key_products, 'edit_selected_products'); ?>
                <p class="form-help">Select products that this license key can be used for. Leave empty for all
                    products.</p>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" name="edit_license_key" class="btn btn-primary">Update License Key</button>
            <a href="<?php echo admin_url('admin.php?page=top-up-agent-license-keys'); ?>"
                class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php
    }

    public function render_add_form() {
        ?>
<div class="license-form-container">
    <h2>➕ Add New License Key</h2>
    <form method="post" class="license-form">
        <?php wp_nonce_field('add_license_key'); ?>

        <div class="form-grid">
            <div class="form-group" id="single_license_key_section">
                <label for="license_key">License Key</label>
                <input type="text" name="license_key" id="license_key" class="form-input" required>
                <p class="form-help">Enter a unique license key (alphanumeric characters, dashes, and spaces allowed)
                </p>
            </div>

            <div class="form-group">
                <label for="selected_products">Assign to Products</label>
                <?php $this->product_manager->render_product_options([], 'selected_products'); ?>
                <p class="form-help">Select products that this license key can be used for. Leave empty for all
                    products.</p>
            </div>

            <div class="form-group full-width">
                <label>
                    <div class="checkbox-wrapper-41">
                        <input type="checkbox" name="is_group_product" id="is_group_product" value="1">
                    </div>
                    This is a group product (allows multiple license keys for simultaneous automation)
                </label>
                <p class="form-help">Check this if this product should support group automation with multiple license
                    keys</p>
            </div>

            <div id="group_settings" class="form-group" style="display: none;">
                <div class="group-name-preview">
                    <label>Group Name (Auto-generated)</label>
                    <div class="group-name-display" id="group_name_preview">
                        Select products to see group name preview
                    </div>
                    <p class="form-help">Group name will be automatically generated based on selected products</p>
                </div>
            </div>

            <div id="group_license_count_section" class="form-group" style="display: none;">
                <label>Number of License Keys</label>
                <div class="modern-options">
                    <div class="modern-option">
                        <input type="radio" name="group_license_count" id="group_license_count_2" value="2">
                        <label for="group_license_count_2">2 Keys</label>
                    </div>
                    <div class="modern-option">
                        <input type="radio" name="group_license_count" id="group_license_count_3" value="3" checked>
                        <label for="group_license_count_3">3 Keys</label>
                    </div>
                    <div class="modern-option">
                        <input type="radio" name="group_license_count" id="group_license_count_4" value="4">
                        <label for="group_license_count_4">4 Keys</label>
                    </div>
                    <div class="modern-option">
                        <input type="radio" name="group_license_count" id="group_license_count_5" value="5">
                        <label for="group_license_count_5">5 Keys</label>
                    </div>
                </div>
                <p class="form-help">How many license keys should be used for this group product</p>
            </div>

            <div id="multiple_keys_section" class="form-group full-width" style="display: none;">
                <label for="multiple_license_keys">Multiple License Keys</label>
                <textarea name="multiple_license_keys" id="multiple_license_keys" rows="5" class="form-textarea"
                    placeholder="Enter multiple license keys (one per line)"></textarea>
                <p class="form-help">For group products, enter ALL license keys that belong to this set (one per line)
                </p>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" name="add_license_key" class="btn btn-primary">Add License Key</button>
        </div>
    </form>
</div>
<?php
    }

    public function render_edit_group_form($group_id) {
        $group_keys = $this->license_manager->get_group_license_keys($group_id);
        if (empty($group_keys)) {
            echo '<div class="error"><p>Group not found!</p></div>';
            return;
        }
        
        $first_key = $group_keys[0];
        $license_keys_text = implode("\n", array_column($group_keys, 'license_key'));
        $selected_products = $first_key->product_ids ? explode(',', $first_key->product_ids) : [];
        ?>
<div class="license-form-container">
    <h2>✏️ Edit Group License Keys</h2>
    <form method="post" class="license-form">
        <?php wp_nonce_field('edit_group_keys'); ?>
        <input type="hidden" name="group_id" value="<?php echo esc_attr($group_id); ?>">

        <div class="form-grid">
            <div class="form-group">
                <label>Group Name (Auto-generated)</label>
                <div class="group-name-display">
                    <?php echo esc_html($first_key->group_name); ?>
                </div>
                <p class="form-help">Group name will be automatically updated based on selected products</p>
            </div>

            <div class="form-group">
                <label for="selected_products">Assign to Products</label>
                <?php $this->product_manager->render_product_options($selected_products, 'selected_products'); ?>
                <p class="form-help">Select products that these license keys can be used for. Leave empty for all
                    products.</p>
            </div>

            <div class="form-group">
                <label>Number of License Keys</label>
                <div class="modern-options">
                    <div class="modern-option">
                        <input type="radio" name="group_license_count" id="edit_group_license_count_2" value="2"
                            <?php checked($first_key->group_license_count, 2); ?>>
                        <label for="edit_group_license_count_2">2 Keys</label>
                    </div>
                    <div class="modern-option">
                        <input type="radio" name="group_license_count" id="edit_group_license_count_3" value="3"
                            <?php checked($first_key->group_license_count, 3); ?>>
                        <label for="edit_group_license_count_3">3 Keys</label>
                    </div>
                    <div class="modern-option">
                        <input type="radio" name="group_license_count" id="edit_group_license_count_4" value="4"
                            <?php checked($first_key->group_license_count, 4); ?>>
                        <label for="edit_group_license_count_4">4 Keys</label>
                    </div>
                    <div class="modern-option">
                        <input type="radio" name="group_license_count" id="edit_group_license_count_5" value="5"
                            <?php checked($first_key->group_license_count, 5); ?>>
                        <label for="edit_group_license_count_5">5 Keys</label>
                    </div>
                </div>
                <p class="form-help">How many license keys should be used for this group product</p>
            </div>

            <div class="form-group full-width">
                <label for="multiple_license_keys">License Keys in this Group</label>
                <textarea name="multiple_license_keys" id="multiple_license_keys" rows="8" class="form-textarea"
                    required><?php echo esc_textarea($license_keys_text); ?></textarea>
                <p class="form-help">Edit the license keys in this group (one per line). You can add, remove, or modify
                    keys.</p>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" name="edit_group_keys" class="btn btn-primary">Update Group</button>
            <a href="?page=top-up-agent-license-keys" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php
    }

    public function render_bulk_import_form() {
        ?>
<div class="license-form-container">
    <h2>📁 Bulk Import License Keys</h2>
    <form method="post" class="license-form">
        <?php wp_nonce_field('bulk_import_license_keys'); ?>

        <div class="form-grid">
            <div class="form-group full-width">
                <label for="bulk_license_keys">License Keys</label>
                <textarea name="bulk_license_keys" id="bulk_license_keys" rows="10" class="form-textarea"
                    placeholder="Enter one license key per line"></textarea>
                <p class="form-help">Enter one license key per line. Invalid keys will be skipped with error reporting.
                </p>
            </div>

            <div class="form-group">
                <label for="bulk_selected_products">Assign to Products</label>
                <?php $this->product_manager->render_bulk_product_options(); ?>
                <p class="form-help">Select products that these license keys can be used for. Leave empty for all
                    products.</p>
            </div>

            <div class="form-group">
                <label>
                    <div class="checkbox-wrapper-41">
                        <input type="checkbox" name="bulk_is_group_product" id="bulk_is_group_product" value="1">
                    </div>
                    These are group products
                </label>
                <p class="form-help">Check if these products should support group automation with multiple license keys
                </p>
            </div>

            <div id="bulk_group_settings" class="form-group" style="display: none;">
                <div class="group-name-preview">
                    <label>Group Name (Auto-generated)</label>
                    <div class="group-name-display" id="bulk_group_name_preview">
                        Select products to see group name preview
                    </div>
                    <p class="form-help">Group names will be automatically generated based on selected products</p>
                </div>

                <label>Number of License Keys per Group</label>
                <div class="modern-options">
                    <div class="modern-option">
                        <input type="radio" name="bulk_group_license_count" id="bulk_group_license_count_2" value="2">
                        <label for="bulk_group_license_count_2">2 Keys</label>
                    </div>
                    <div class="modern-option">
                        <input type="radio" name="bulk_group_license_count" id="bulk_group_license_count_3" value="3"
                            checked>
                        <label for="bulk_group_license_count_3">3 Keys</label>
                    </div>
                    <div class="modern-option">
                        <input type="radio" name="bulk_group_license_count" id="bulk_group_license_count_4" value="4">
                        <label for="bulk_group_license_count_4">4 Keys</label>
                    </div>
                    <div class="modern-option">
                        <input type="radio" name="bulk_group_license_count" id="bulk_group_license_count_5" value="5">
                        <label for="bulk_group_license_count_5">5 Keys</label>
                    </div>
                </div>
                <p class="form-help">How many license keys should be used for these group products</p>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" name="bulk_import" class="btn btn-primary">Import License Keys</button>
        </div>
    </form>
</div>
<?php
    }
}