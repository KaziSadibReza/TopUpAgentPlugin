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
        <div class="stat-icon">📊</div>
        <div class="stat-content">
            <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
            <div class="stat-label">Total Keys</div>
        </div>
    </div>
    <div class="stat-card stat-available">
        <div class="stat-icon">✅</div>
        <div class="stat-content">
            <div class="stat-number"><?php echo number_format($stats['unused'] ?? 0); ?></div>
            <div class="stat-label">Available</div>
        </div>
    </div>
    <div class="stat-card stat-used">
        <div class="stat-icon">❌</div>
        <div class="stat-content">
            <div class="stat-number"><?php echo number_format($stats['used'] ?? 0); ?></div>
            <div class="stat-label">Used</div>
        </div>
    </div>
    <div class="stat-card stat-group">
        <div class="stat-icon">📦</div>
        <div class="stat-content">
            <div class="stat-number"><?php echo number_format($stats['group_products'] ?? 0); ?></div>
            <div class="stat-label">Group Keys</div>
        </div>
    </div>
    <div class="stat-card stat-recent">
        <div class="stat-icon">🕒</div>
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
                    placeholder="Search by license key..." class="filter-input">
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
        <h3>📦 Group License Keys</h3>
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
                            <span class="status-available">✅ <?php echo $group->unused_count; ?></span>
                            <span class="status-used">❌ <?php echo $group->used_count; ?></span>
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
                                class="btn btn-edit" title="Edit Group">✏️ Edit</a>
                            <form method="post" style="display: inline;"
                                onsubmit="return confirm('Are you sure you want to delete this entire group?');">
                                <?php wp_nonce_field('delete_group_keys'); ?>
                                <input type="hidden" name="group_id" value="<?php echo $group->group_id; ?>">
                                <button type="submit" name="delete_group_keys" class="btn btn-delete"
                                    title="Delete Group">🗑️</button>
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
        <h3>� Individual License Keys (<?php echo number_format($total_keys); ?> total)</h3>
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
                                title="Copy to clipboard">📋</button>
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
                                        echo '<span class="type-badge type-group">📦 GROUP<br><small>(' . (isset($key->group_license_count) ? $key->group_license_count : 3) . ' keys)</small></span>';
                                    } else {
                                        echo '<span class="type-badge type-single">📄 Single</span>';
                                    }
                                    ?>
                    </td>
                    <td class="col-status">
                        <?php if ($key->status === 'unused'): ?>
                        <span class="status-badge status-available">✅ Available</span>
                        <?php else: ?>
                        <span class="status-badge status-used">❌ Used</span>
                        <?php endif; ?>
                    </td>
                    <td class="col-dates">
                        <div class="dates-info">
                            <div class="created-date">📅 <?php echo date('M j, Y', strtotime($key->created_date)); ?>
                            </div>
                            <?php if ($key->used_date): ?>
                            <div class="used-date">🕒 <?php echo date('M j, Y', strtotime($key->used_date)); ?></div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="col-actions">
                        <div class="action-buttons">
                            <a href="<?php echo admin_url('admin.php?page=top-up-agent-license-keys&edit=' . $key->id); ?>"
                                class="btn btn-edit" title="Edit">✏️</a>
                            <form method="post" style="display: inline;"
                                onsubmit="return confirm('Are you sure you want to delete this license key?')">
                                <?php wp_nonce_field('delete_license_key'); ?>
                                <input type="hidden" name="key_id" value="<?php echo $key->id; ?>">
                                <button type="submit" name="delete_license_key" class="btn btn-delete"
                                    title="Delete">🗑️</button>
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

    public function render_assets() {
        ?>
<!-- Include Select2 CSS and JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<?php
    }

    public function render_styles() {
        ?>
<style>
/* Modern License Management Styles */
.license-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 16px;
    border-left: 4px solid;
}

.stat-total {
    border-left-color: #3b82f6;
}

.stat-available {
    border-left-color: #10b981;
}

.stat-used {
    border-left-color: #ef4444;
}

.stat-group {
    border-left-color: #8b5cf6;
}

.stat-recent {
    border-left-color: #f59e0b;
}

.stat-icon {
    font-size: 32px;
    opacity: 0.8;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    line-height: 1;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 14px;
    color: #6b7280;
    font-weight: 500;
}

/* Advanced Search and Filter Panel */
.search-filter-panel {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin: 20px 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    /* Clear any floats and reset positioning */
    clear: both;
    overflow: hidden;
}

.search-filter-panel::after {
    content: "";
    display: table;
    clear: both;
}

.search-filter-panel h3 {
    margin: 0 0 20px 0;
    font-size: 18px;
    color: #1f2937;
}

/* Specific targeting to override WordPress admin styles */
.license-manager-filters .filter-grid {
    display: grid !important;
    grid-template-columns: 2fr 1fr 1.5fr auto !important;
    gap: 20px !important;
    align-items: end !important;
    background: #f8fafc !important;
    padding: 20px !important;
    border-radius: 12px !important;
    border: 1px solid #e5e7eb !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
    /* Override any WordPress layout styles */
    float: none !important;
    width: 100% !important;
    margin: 0 !important;
}

.license-manager-filters .filter-group {
    position: relative !important;
    /* Override WordPress default float layout */
    float: none !important;
    width: auto !important;
    margin: 0 !important;
    padding: 0 !important;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    box-sizing: border-box !important;
    /* Ensure grid item behavior */
    grid-column: auto !important;
    grid-row: auto !important;
}

.export-panel,
.license-table-container {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin: 20px 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.export-panel h3,
.table-header h3 {
    margin: 0 0 20px 0;
    font-size: 18px;
    color: #1f2937;
}

.filter-form {
    margin-bottom: 20px;
}

.filter-grid {
    display: grid !important;
    grid-template-columns: 2fr 1fr 1.5fr auto !important;
    gap: 20px !important;
    align-items: end !important;
    background: #f8fafc !important;
    padding: 20px !important;
    border-radius: 12px !important;
    border: 1px solid #e5e7eb !important;
    /* Override WordPress default styles */
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
}

.filter-group {
    position: relative !important;
    /* Override WordPress default float layout */
    float: none !important;
    width: auto !important;
    margin: 0 !important;
    padding: 0 !important;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    box-sizing: border-box !important;
}

.filter-group label {
    display: block !important;
    font-weight: 600 !important;
    margin-bottom: 8px !important;
    color: #374151 !important;
    font-size: 13px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
}

.filter-input,
.filter-select {
    width: 100% !important;
    padding: 12px 16px !important;
    border: 2px solid #e5e7eb !important;
    border-radius: 8px !important;
    font-size: 14px !important;
    transition: all 0.2s ease !important;
    background: white !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
    margin: 0 !important;
}

.filter-input:focus,
.filter-select:focus {
    outline: none !important;
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
    transform: translateY(-1px) !important;
}

.filter-input::placeholder {
    color: #9ca3af !important;
    font-style: italic !important;
}

.filter-actions {
    display: flex !important;
    gap: 8px !important;
    flex-direction: column !important;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    transition: all 0.2s ease;
    font-size: 14px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.btn-primary {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
    border: 2px solid transparent;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: white;
    text-decoration: none;
}

.btn-secondary {
    background: #f8fafc;
    color: #374151;
    border: 2px solid #e5e7eb;
}

.btn-secondary:hover {
    background: #f1f5f9;
    border-color: #d1d5db;
    color: #1f2937;
    text-decoration: none;
}

/* Filter-specific button styling */
.filter-actions .btn {
    width: 100%;
    font-size: 13px;
    padding: 10px 16px;
}

.filter-actions .btn-primary {
    background: linear-gradient(135deg, #10b981, #059669);
    border-color: #10b981;
}

.filter-actions .btn-primary:hover {
    background: linear-gradient(135deg, #059669, #047857);
}

.btn-secondary:hover {
    background: #4b5563;
}

.btn-export {
    background: #10b981;
    color: white;
    margin-right: 10px;
}

.btn-export:hover {
    background: #059669;
}

.active-filters {
    padding: 16px;
    background: #f0f9ff;
    border-radius: 8px;
    margin-top: 16px;
}

.filter-tag {
    display: inline-block;
    background: white;
    padding: 4px 12px;
    border-radius: 20px;
    margin: 0 8px 8px 0;
    border: 1px solid #e5e7eb;
    font-size: 12px;
}

.results-info {
    color: #3b82f6;
    font-weight: 600;
    margin-left: 8px;
}

.export-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.responsive-table {
    overflow-x: auto;
}

.license-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 16px;
}

.license-table th {
    background: #f9fafb;
    padding: 16px 12px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 2px solid #e5e7eb;
}

.license-table td {
    padding: 16px 12px;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: top;
}

.license-row:hover {
    background: #f9fafb;
}

.license-key-display {
    display: flex;
    align-items: center;
    gap: 8px;
}

.license-key-display code {
    background: #f3f4f6;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-family: monospace;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
}

.copy-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    opacity: 0.7;
}

.copy-btn:hover {
    opacity: 1;
    background: #f3f4f6;
}

.product-list {
    font-size: 12px;
    color: #6b7280;
}

.all-products {
    font-style: italic;
    color: #9ca3af;
    font-size: 12px;
}

.type-badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    text-align: center;
    line-height: 1.2;
}

.type-group {
    background: #ede9fe;
    color: #7c3aed;
}

.type-single {
    background: #f3f4f6;
    color: #6b7280;
}

.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.status-available {
    background: #d1fae5;
    color: #065f46;
}

.status-used {
    background: #fee2e2;
    color: #991b1b;
}

.dates-info {
    font-size: 12px;
    line-height: 1.4;
}

.created-date {
    color: #6b7280;
}

.used-date {
    color: #ef4444;
    margin-top: 4px;
}

.action-buttons {
    display: flex;
    gap: 6px;
}

.btn-edit {
    background: #f59e0b;
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 12px;
}

.btn-edit:hover {
    background: #d97706;
}

.btn-delete {
    background: #ef4444;
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-size: 12px;
}

.btn-delete:hover {
    background: #dc2626;
}

.pagination-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.pagination-info {
    color: #6b7280;
    font-size: 14px;
}

.pagination-controls {
    display: flex;
    gap: 12px;
    align-items: center;
}

.btn-pagination {
    background: #f3f4f6;
    color: #374151;
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
}

.btn-pagination:hover {
    background: #e5e7eb;
}

.pagination-current {
    color: #6b7280;
    font-weight: 600;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6b7280;
}

.empty-icon {
    font-size: 64px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.empty-state h3 {
    margin: 0 0 8px 0;
    color: #374151;
}

.empty-state p {
    margin: 0 0 20px 0;
}

/* Responsive Design */
@media (max-width: 768px) {

    .filter-grid,
    .license-manager-filters .filter-grid {
        grid-template-columns: 1fr !important;
        gap: 16px !important;
        padding: 16px !important;
    }

    .filter-actions {
        flex-direction: row !important;
        gap: 12px !important;
    }

    .filter-actions .btn {
        width: auto !important;
        flex: 1 !important;
    }

    .license-stats-grid {
        grid-template-columns: 1fr 1fr;
    }

    .export-buttons {
        flex-direction: column;
    }

    .license-table th,
    .license-table td {
        padding: 12px 8px;
        font-size: 12px;
    }

    .pagination-wrapper {
        flex-direction: column;
        gap: 16px;
    }
}

@media (max-width: 1024px) {

    .filter-grid,
    .license-manager-filters .filter-grid {
        grid-template-columns: 1fr 1fr !important;
        gap: 16px !important;
    }

    .filter-actions {
        grid-column: 1 / -1 !important;
        flex-direction: row !important;
        justify-content: center !important;
    }
}

/* Additional modern touches */
.license-table-container {
    border: 1px solid #e5e7eb;
}

.table-header {
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 16px;
    margin-bottom: 0;
}

/* Smooth transitions for form sections */
#single_license_key_section,
#group_settings,
#multiple_keys_section {
    transition: opacity 0.3s ease, height 0.3s ease;
}

#single_license_key_section.hidden,
#group_settings.hidden,
#multiple_keys_section.hidden {
    opacity: 0;
    height: 0;
    overflow: hidden;
    margin: 0;
    padding: 0;
}

/* Enhanced Select2 Styling for Better Product Selection UI */
.select2-container {
    width: 100% !important;
}

.select2-container--default .select2-selection--multiple {
    border: 2px solid #e5e7eb !important;
    border-radius: 8px !important;
    min-height: 44px !important;
    padding: 6px 8px !important;
    background: white !important;
}

.select2-container--default .select2-selection--single {
    border: 2px solid #e5e7eb !important;
    border-radius: 8px !important;
    height: 44px !important;
    background: white !important;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 40px !important;
    padding-left: 12px !important;
    color: #374151 !important;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 40px !important;
    right: 8px !important;
}

.select2-container--default.select2-container--focus .select2-selection--multiple,
.select2-container--default.select2-container--focus .select2-selection--single {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 1px #3b82f6 !important;
}

.select2-container--default .select2-selection--multiple .select2-selection__rendered {
    padding: 0 !important;
    margin: 0 !important;
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 6px !important;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background: #f3f4f6 !important;
    border: 1px solid #d1d5db !important;
    border-radius: 6px !important;
    padding: 4px 8px !important;
    margin: 0 !important;
    font-size: 12px !important;
    max-width: 200px !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    display: flex !important;
    align-items: center !important;
    gap: 4px !important;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice__display {
    max-width: 160px !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    color: #374151 !important;
    font-weight: 500 !important;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    background: none !important;
    border: none !important;
    color: #6b7280 !important;
    font-size: 14px !important;
    padding: 0 !important;
    margin: 0 !important;
    width: 16px !important;
    height: 16px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: 50% !important;
    cursor: pointer !important;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
    background: #fee2e2 !important;
    color: #dc2626 !important;
}

.select2-container--default .select2-selection--multiple .select2-search--inline .select2-search__field {
    border: none !important;
    outline: none !important;
    font-size: 14px !important;
    padding: 4px !important;
    margin: 0 !important;
    min-width: 100px !important;
}

.select2-container--default .select2-selection--multiple .select2-selection__clear {
    background: #ef4444 !important;
    color: white !important;
    border: none !important;
    border-radius: 4px !important;
    padding: 2px 6px !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    position: absolute !important;
    right: 8px !important;
    top: 8px !important;
}

.select2-container--default .select2-selection--multiple .select2-selection__clear:hover {
    background: #dc2626 !important;
}

/* Special styling for filter dropdowns */
.filter-group .select2-container--default .select2-selection--single {
    height: 44px !important;
    background: #f9fafb !important;
    border: 2px solid #e5e7eb !important;
}

.filter-group .select2-container--default .select2-selection--single:hover {
    border-color: #d1d5db !important;
}

.filter-group .select2-container--default.select2-container--focus .select2-selection--single {
    background: white !important;
    border-color: #3b82f6 !important;
}

.filter-group .select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #374151 !important;
    font-weight: 500 !important;
}

.filter-group .select2-container--default .select2-selection--single .select2-selection__placeholder {
    color: #9ca3af !important;
}

.select2-dropdown {
    border: 2px solid #e5e7eb !important;
    border-radius: 8px !important;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1) !important;
}

.select2-container--default .select2-results__option {
    padding: 8px 12px !important;
    font-size: 13px !important;
    border-bottom: 1px solid #f3f4f6 !important;
}

.select2-container--default .select2-results__option--highlighted {
    background: #eff6ff !important;
    color: #1e40af !important;
}

.select2-container--default .select2-results__option[aria-selected=true] {
    background: #dbeafe !important;
    color: #1e40af !important;
    font-weight: 600 !important;
}

.select2-container--default .select2-search--dropdown .select2-search__field {
    border: 1px solid #d1d5db !important;
    border-radius: 6px !important;
    padding: 8px 12px !important;
    font-size: 14px !important;
}

.select2-container--default .select2-search--dropdown .select2-search__field:focus {
    border-color: #3b82f6 !important;
    outline: none !important;
    box-shadow: 0 0 0 1px #3b82f6 !important;
}

/* Custom styles for empty state */
.select2-container--default .select2-results__message {
    padding: 16px !important;
    text-align: center !important;
    color: #6b7280 !important;
    font-style: italic !important;
}

/* Responsive improvements for Select2 */
@media (max-width: 768px) {
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        max-width: 150px !important;
        font-size: 11px !important;
        padding: 3px 6px !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__display {
        max-width: 120px !important;
    }
}
</style>
<?php
    }

    public function render_scripts() {
        ?>
<script>
jQuery(document).ready(function($) {
    // Initialize Select2 for product selectors with enhanced formatting
    $('#selected_products, #edit_selected_products, #bulk_selected_products, .modern-select').select2({
        placeholder: 'Select products...',
        allowClear: true,
        width: '100%',
        templateResult: function(data) {
            if (!data.id) return data.text;

            // Create a shortened version for display
            var text = data.text.trim();
            var shortText = text.length > 60 ? text.substring(0, 60) + '...' : text;

            var $result = $('<span></span>');
            $result.text(shortText);
            $result.attr('title', text); // Full text on hover

            return $result;
        },
        templateSelection: function(data) {
            if (!data.id) return data.text;

            // Even shorter version for selected items
            var text = data.text.trim();
            var shortText = text.length > 40 ? text.substring(0, 40) + '...' : text;

            var $selection = $('<span></span>');
            $selection.text(shortText);
            $selection.attr('title', text); // Full text on hover

            return $selection;
        },
        escapeMarkup: function(markup) {
            return markup;
        }
    });

    // Initialize Select2 for filter dropdowns with simpler styling
    $('#product, #status').select2({
        placeholder: function() {
            return $(this).attr('id') === 'product' ? 'All Products' : 'All Statuses';
        },
        allowClear: true,
        width: '100%',
        minimumResultsForSearch: 5, // Show search only if more than 5 options
        templateResult: function(data) {
            if (!data.id) return data.text;

            // Create a shortened version for display in dropdown
            var text = data.text.trim();
            var shortText = text.length > 50 ? text.substring(0, 50) + '...' : text;

            var $result = $('<span></span>');
            $result.text(shortText);
            $result.attr('title', text); // Full text on hover

            return $result;
        },
        templateSelection: function(data) {
            if (!data.id) return data.text;

            // Shorter version for selected item in filter
            var text = data.text.trim();
            var shortText = text.length > 35 ? text.substring(0, 35) + '...' : text;

            var $selection = $('<span></span>');
            $selection.text(shortText);
            $selection.attr('title', text); // Full text on hover

            return $selection;
        },
        escapeMarkup: function(markup) {
            return markup;
        }
    });

    // Update group name preview when products are selected
    function updateGroupNamePreview() {
        var selectedProducts = $('#selected_products');
        var groupNamePreview = $('#group_name_preview');

        // Check if elements exist and Select2 is initialized
        if (selectedProducts.length === 0 || groupNamePreview.length === 0) {
            return;
        }

        try {
            var selectedData = selectedProducts.select2('data');
            if (selectedData && selectedData.length === 0) {
                groupNamePreview.text('All Products Set 1');
            } else if (selectedData && selectedData.length === 1) {
                groupNamePreview.text(selectedData[0].text + ' Set 1');
            } else if (selectedData && selectedData.length > 1) {
                groupNamePreview.text('Mixed Products Set 1');
            }
        } catch (e) {
            // Select2 not initialized yet, ignore
            console.log('Select2 not ready for group name preview');
        }
    }

    function updateBulkGroupNamePreview() {
        var selectedProducts = $('#bulk_selected_products');
        var groupNamePreview = $('#bulk_group_name_preview');

        // Check if elements exist and Select2 is initialized
        if (selectedProducts.length === 0 || groupNamePreview.length === 0) {
            return;
        }

        try {
            var selectedData = selectedProducts.select2('data');
            if (selectedData && selectedData.length === 0) {
                groupNamePreview.text('All Products Set 1, Set 2, Set 3...');
            } else if (selectedData && selectedData.length === 1) {
                groupNamePreview.text(selectedData[0].text + ' Set 1, Set 2, Set 3...');
            } else if (selectedData && selectedData.length > 1) {
                groupNamePreview.text('Mixed Products Set 1, Set 2, Set 3...');
            }
        } catch (e) {
            // Select2 not initialized yet, ignore
            console.log('Select2 not ready for bulk group name preview');
        }
    }

    // Listen for product selection changes (only if elements exist)
    if ($('#selected_products').length > 0) {
        $('#selected_products').on('change', updateGroupNamePreview);
    }
    if ($('#bulk_selected_products').length > 0) {
        $('#bulk_selected_products').on('change', updateBulkGroupNamePreview);
    }

    // Initial preview update (with delay to ensure Select2 is initialized)
    setTimeout(function() {
        updateGroupNamePreview();
        updateBulkGroupNamePreview();
    }, 100);

    // Group product checkbox functionality with multiple key input
    $('#is_group_product').change(function() {
        if ($(this).is(':checked')) {
            $('#group_settings').show();
            $('#group_license_count_section').show();
            $('#multiple_keys_section').show();
            $('#single_license_key_section').hide();
            // Remove required attribute from single license key when hidden
            $('#license_key').removeAttr('required');
        } else {
            $('#group_settings').hide();
            $('#group_license_count_section').hide();
            $('#multiple_keys_section').hide();
            $('#single_license_key_section').show();
            // Add required attribute back to single license key when shown
            $('#license_key').attr('required', 'required');
        }
    });

    $('#bulk_is_group_product').change(function() {
        if ($(this).is(':checked')) {
            $('#bulk_group_settings').show();
        } else {
            $('#bulk_group_settings').hide();
        }
    });
});

// Copy to clipboard function
function copyToClipboard(text) {
    // Check if clipboard API is available (HTTPS required)
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function() {
            showCopyNotification('License key copied to clipboard!');
        }).catch(function(err) {
            console.error('Could not copy text: ', err);
            fallbackCopyToClipboard(text);
        });
    } else {
        // Fallback for non-HTTPS environments
        fallbackCopyToClipboard(text);
    }
}

// Fallback copy method using textarea
function fallbackCopyToClipboard(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    textarea.style.top = '0';
    textarea.style.left = '0';
    document.body.appendChild(textarea);
    textarea.select();
    textarea.setSelectionRange(0, 99999); // For mobile devices

    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showCopyNotification('License key copied to clipboard!');
        } else {
            showCopyNotification('Failed to copy license key', 'error');
        }
    } catch (err) {
        console.error('Fallback copy failed: ', err);
        showCopyNotification('Copy not supported in this browser', 'error');
    }

    document.body.removeChild(textarea);
}

// Show copy notification
function showCopyNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.textContent = message;
    const bgColor = type === 'error' ? '#ef4444' : '#10b981';
    notification.style.cssText =
        `position:fixed;top:20px;right:20px;background:${bgColor};color:white;padding:12px 20px;border-radius:8px;z-index:9999;font-size:14px;font-weight:500;box-shadow:0 4px 12px rgba(0,0,0,0.15);`;
    document.body.appendChild(notification);
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 3000);
}
</script>
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
                    <input type="checkbox" name="is_group_product" id="is_group_product" value="1">
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
                <label for="group_license_count">Number of License Keys</label>
                <select name="group_license_count" id="group_license_count" class="form-select">
                    <option value="2">2 Keys</option>
                    <option value="3" selected>3 Keys</option>
                    <option value="4">4 Keys</option>
                    <option value="5">5 Keys</option>
                </select>
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

<style>
.license-form-container {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin: 20px 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.license-form-container h2 {
    margin: 0 0 24px 0;
    color: #1f2937;
    font-size: 20px;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #374151;
}

.form-input,
.form-select {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.2s;
}

.form-textarea {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.2s;
    font-family: 'Courier New', monospace;
    line-height: 1.5;
    resize: vertical;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-help {
    margin: 6px 0 0 0;
    font-size: 12px;
    color: #6b7280;
}

.group-name-display {
    background: #f8fafc;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px 16px;
    font-size: 14px;
    color: #374151;
    font-weight: 500;
    min-height: 20px;
    line-height: 1.4;
    word-break: break-word;
}

.group-name-display:empty:before {
    content: "Group name will be generated automatically";
    color: #9ca3af;
    font-style: italic;
}

/* Select2 Improvements */
.select2-container {
    width: 100% !important;
}

.select2-selection--multiple {
    border: 2px solid #e5e7eb !important;
    border-radius: 8px !important;
    min-height: 42px !important;
    padding: 4px 8px !important;
}

.select2-selection--multiple:focus-within {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
}

.select2-selection__choice {
    background-color: #3b82f6 !important;
    border: none !important;
    border-radius: 6px !important;
    color: white !important;
    padding: 4px 8px !important;
    margin: 2px !important;
    max-width: 250px;
}

.select2-selection__choice__display {
    color: white !important;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 200px;
    display: inline-block;
}

.select2-selection__choice__remove {
    color: rgba(255, 255, 255, 0.8) !important;
    margin-right: 6px !important;
}

.select2-selection__choice__remove:hover {
    color: white !important;
    background: rgba(255, 255, 255, 0.2) !important;
    border-radius: 3px;
}

.select2-search__field {
    margin: 2px !important;
    padding: 4px !important;
}

/* Dropdown styling */
.select2-dropdown {
    border: 2px solid #e5e7eb !important;
    border-radius: 8px !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
}

.select2-results__option {
    padding: 8px 12px !important;
    font-size: 14px !important;
}

.select2-results__option--highlighted {
    background-color: #3b82f6 !important;
}

.form-actions {
    display: flex;
    gap: 12px;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}
</style>
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
                <label for="group_license_count">Number of License Keys</label>
                <select name="group_license_count" id="group_license_count" class="form-select">
                    <option value="2" <?php selected($first_key->group_license_count, 2); ?>>2 Keys</option>
                    <option value="3" <?php selected($first_key->group_license_count, 3); ?>>3 Keys</option>
                    <option value="4" <?php selected($first_key->group_license_count, 4); ?>>4 Keys</option>
                    <option value="5" <?php selected($first_key->group_license_count, 5); ?>>5 Keys</option>
                </select>
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

<style>
.license-form-container {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin: 20px 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.license-form-container h2 {
    margin: 0 0 24px 0;
    color: #1f2937;
    font-size: 20px;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #374151;
}

.form-input,
.form-select {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.2s;
}

.form-textarea {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.2s;
    font-family: 'Courier New', monospace;
    line-height: 1.5;
    resize: vertical;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-help {
    margin: 6px 0 0 0;
    font-size: 12px;
    color: #6b7280;
}

.group-name-display {
    background: #f8fafc;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px 16px;
    font-size: 14px;
    color: #374151;
    font-weight: 500;
    min-height: 20px;
    line-height: 1.4;
    word-break: break-word;
}

.group-name-display:empty:before {
    content: "Group name will be generated automatically";
    color: #9ca3af;
    font-style: italic;
}

/* Select2 Improvements */
.select2-container {
    width: 100% !important;
}

.select2-selection--multiple {
    border: 2px solid #e5e7eb !important;
    border-radius: 8px !important;
    min-height: 42px !important;
    padding: 4px 8px !important;
}

.select2-selection--multiple:focus-within {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
}

.select2-selection__choice {
    background-color: #3b82f6 !important;
    border: none !important;
    border-radius: 6px !important;
    color: white !important;
    padding: 4px 8px !important;
    margin: 2px !important;
    max-width: 250px;
}

.select2-selection__choice__display {
    color: white !important;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 200px;
    display: inline-block;
}

.select2-selection__choice__remove {
    color: rgba(255, 255, 255, 0.8) !important;
    margin-right: 6px !important;
}

.select2-selection__choice__remove:hover {
    color: white !important;
    background: rgba(255, 255, 255, 0.2) !important;
    border-radius: 3px;
}

.select2-search__field {
    margin: 2px !important;
    padding: 4px !important;
}

/* Dropdown styling */
.select2-dropdown {
    border: 2px solid #e5e7eb !important;
    border-radius: 8px !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
}

.select2-results__option {
    padding: 8px 12px !important;
    font-size: 14px !important;
}

.select2-results__option--highlighted {
    background-color: #3b82f6 !important;
}

.form-actions {
    display: flex;
    gap: 12px;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}
</style>
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
                    <input type="checkbox" name="bulk_is_group_product" id="bulk_is_group_product" value="1">
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

                <label for="bulk_group_license_count">Number of License Keys per Group</label>
                <select name="bulk_group_license_count" id="bulk_group_license_count" class="form-select">
                    <option value="2">2 Keys</option>
                    <option value="3" selected>3 Keys</option>
                    <option value="4">4 Keys</option>
                    <option value="5">5 Keys</option>
                </select>
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