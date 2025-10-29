<?php
class Top_Up_Agent_License_Key_Manager {
    private $table_name;
    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'top_up_agent_license_keys';
        $this->init_table();
    }

    private function init_table() {
        $this->wpdb->query("CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            license_key varchar(255) NOT NULL,
            product_ids text DEFAULT NULL,
            status enum('unused','used') DEFAULT 'unused',
            used_date datetime DEFAULT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            order_id int(11) DEFAULT NULL,
            is_group_product tinyint(1) DEFAULT 0,
            group_license_count int(11) DEFAULT 3,
            group_id varchar(50) DEFAULT NULL,
            group_name varchar(255) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY license_key (license_key),
            KEY group_id (group_id)
        )");
        
        // Safely add missing columns for existing installations
        $this->add_column_if_not_exists('is_group_product', 'tinyint(1) DEFAULT 0');
        $this->add_column_if_not_exists('group_license_count', 'int(11) DEFAULT 3');
        $this->add_column_if_not_exists('group_id', 'varchar(50) DEFAULT NULL');
        $this->add_column_if_not_exists('group_name', 'varchar(255) DEFAULT NULL');
        $this->add_column_if_not_exists('order_id', 'int(11) DEFAULT NULL');
        $this->add_index_if_not_exists('group_id', 'group_id');
    }

    /**
     * Safely add column if it doesn't exist
     */
    private function add_column_if_not_exists($column_name, $column_definition) {
        $column_exists = $this->wpdb->get_results("SHOW COLUMNS FROM {$this->table_name} LIKE '{$column_name}'");
        
        if (empty($column_exists)) {
            $this->wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN {$column_name} {$column_definition}");
        }
    }

    /**
     * Safely add index if it doesn't exist
     */
    private function add_index_if_not_exists($index_name, $column_name) {
        $index_exists = $this->wpdb->get_results("SHOW INDEX FROM {$this->table_name} WHERE Key_name = '{$index_name}'");
        
        if (empty($index_exists)) {
            $this->wpdb->query("ALTER TABLE {$this->table_name} ADD KEY {$index_name} ({$column_name})");
        }
    }

    public function add_license_key($license_key, $product_ids = [], $is_group_product = 0, $group_license_count = 3, $group_id = null, $group_name = null) {
        // Handle product_ids assignment logic:
        // - null = available for ALL products  
        // - empty array = not assigned to any specific products (use empty string)
        // - array with values = available for specific products only
        if ($product_ids === null) {
            $product_ids_string = null; // Available for ALL products
        } elseif (is_array($product_ids) && empty($product_ids)) {
            $product_ids_string = ''; // Not assigned to any products
        } else {
            $product_ids_string = implode(',', array_map('intval', $product_ids));
        }
        
        $result = $this->wpdb->insert($this->table_name, [
            'license_key' => sanitize_text_field($license_key),
            'product_ids' => $product_ids_string,
            'is_group_product' => intval($is_group_product),
            'group_license_count' => intval($group_license_count),
            'group_id' => $group_id,
            'group_name' => $group_name
        ]);
        
        return $result !== false;
    }

    /**
     * Add a group of license keys as a set
     */
    public function add_group_license_keys($license_keys_array, $product_ids = [], $group_license_count = 3, $product_manager = null) {
        $group_id = 'grp_' . uniqid() . '_' . time();
        
        // Auto-generate group name based on products
        $group_name = $this->generate_group_name($product_ids, $product_manager);
        
        $success_count = 0;
        $errors = [];
        
        foreach ($license_keys_array as $license_key) {
            $license_key = trim($license_key);
            if (!empty($license_key)) {
                $result = $this->add_license_key($license_key, $product_ids, 1, $group_license_count, $group_id, $group_name);
                if ($result) {
                    $success_count++;
                } else {
                    $errors[] = $license_key;
                }
            }
        }
        
        return [
            'success_count' => $success_count,
            'errors' => $errors,
            'group_id' => $group_id,
            'group_name' => $group_name
        ];
    }
    
    /**
     * Generate group name based on products
     */
    private function generate_group_name($product_ids, $product_manager = null) {
        if (empty($product_ids)) {
            // For "All Products", find next set number
            $existing_count = (int) $this->wpdb->get_var(
                "SELECT COUNT(DISTINCT group_id) FROM {$this->table_name} 
                 WHERE group_name LIKE 'All Products Set %'"
            );
            return 'All Products Set ' . ($existing_count + 1);
        }
        
        if ($product_manager && count($product_ids) === 1) {
            // Single product - use cleaned product name
            $product = $product_manager->get_product_by_id($product_ids[0]);
            if ($product) {
                // Clean the product name - remove extra colons and trim
                $product_name = $product['name'];
                $product_name = str_replace('::', ':', $product_name);
                $product_name = trim($product_name, ' :');
                $product_name = preg_replace('/\s+/', ' ', $product_name);
                
                // Truncate if too long for readability
                if (strlen($product_name) > 50) {
                    $product_name = substr($product_name, 0, 47) . '...';
                }
                
                $existing_count = (int) $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(DISTINCT group_id) FROM {$this->table_name} 
                     WHERE group_name LIKE %s",
                    $product_name . ' Set %'
                ));
                return $product_name . ' Set ' . ($existing_count + 1);
            }
        }
        
        // Multiple products or unknown products
        $existing_count = (int) $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT group_id) FROM {$this->table_name} 
             WHERE group_name LIKE 'Mixed Products Set %'"
        );
        return 'Mixed Products Set ' . ($existing_count + 1);
    }

    /**
     * Get all license keys in a group
     */
    public function get_group_license_keys($group_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE group_id = %s ORDER BY created_date ASC", 
            $group_id
        ));
    }

    /**
     * Update an entire group of license keys
     */
    public function update_group_license_keys($group_id, $license_keys_array, $product_ids = [], $group_license_count = 3, $product_manager = null) {
        // First, delete all existing keys in this group
        $this->wpdb->delete($this->table_name, ['group_id' => $group_id]);
        
        // Generate new group name based on products
        $group_name = $this->generate_group_name($product_ids, $product_manager);
        
        // Then add the new keys
        return $this->add_group_license_keys_with_existing_id($group_id, $license_keys_array, $product_ids, $group_license_count, $group_name);
    }
    
    /**
     * Add group license keys with existing group ID (for updates)
     */
    private function add_group_license_keys_with_existing_id($group_id, $license_keys_array, $product_ids = [], $group_license_count = 3, $group_name = '') {
        $success_count = 0;
        $errors = [];
        
        foreach ($license_keys_array as $license_key) {
            $license_key = trim($license_key);
            if (!empty($license_key)) {
                $result = $this->add_license_key($license_key, $product_ids, 1, $group_license_count, $group_id, $group_name);
                if ($result) {
                    $success_count++;
                } else {
                    $errors[] = $license_key;
                }
            }
        }
        
        return [
            'success_count' => $success_count,
            'errors' => $errors,
            'group_id' => $group_id,
            'group_name' => $group_name
        ];
    }

    /**
     * Delete an entire group of license keys
     */
    public function delete_group_license_keys($group_id) {
        $result = $this->wpdb->delete($this->table_name, ['group_id' => $group_id]);
        return $result !== false;
    }

    /**
     * Get all groups with their basic info
     */
    public function get_all_groups() {
        return $this->wpdb->get_results(
            "SELECT group_id, group_name, product_ids, group_license_count, created_date, 
                    COUNT(*) as key_count,
                    SUM(status = 'unused') as unused_count,
                    SUM(status = 'used') as used_count
             FROM {$this->table_name} 
             WHERE is_group_product = 1 AND group_id IS NOT NULL 
             GROUP BY group_id, group_name, product_ids, group_license_count, created_date
             ORDER BY created_date DESC"
        );
    }

    public function update_license_key($key_id, $license_key, $product_ids = []) {
        $product_ids_string = empty($product_ids) ? null : implode(',', array_map('intval', $product_ids));
        
        $result = $this->wpdb->update($this->table_name, [
            'license_key' => sanitize_text_field($license_key),
            'product_ids' => $product_ids_string,
        ], ['id' => intval($key_id)]);
        
        return $result !== false;
    }

    public function delete_license_key($key_id) {
        $result = $this->wpdb->delete($this->table_name, ['id' => intval($key_id)]);
        return $result !== false;
    }

    public function get_license_key($key_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d", 
            $key_id
        ));
    }

    public function get_all_license_keys($search = '', $status = '', $product_id = '', $limit = 20, $offset = 0) {
        $where_conditions = ['(is_group_product = 0 OR is_group_product IS NULL OR group_id IS NULL)']; // Exclude group keys
        $where_values = [];
        
        if (!empty($search)) {
            $where_conditions[] = "license_key LIKE %s";
            $where_values[] = '%' . $search . '%';
        }
        
        if (!empty($status) && in_array($status, ['unused', 'used'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = $status;
        }
        
        if (!empty($product_id)) {
            $where_conditions[] = "(product_ids IS NULL OR (product_ids != '' AND FIND_IN_SET(%d, product_ids) > 0))";
            $where_values[] = intval($product_id);
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        $limit_clause = $limit > 0 ? "LIMIT $offset, $limit" : '';
        
        $query = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY created_date DESC {$limit_clause}";
        
        if (!empty($where_values)) {
            return $this->wpdb->get_results($this->wpdb->prepare($query, $where_values));
        } else {
            return $this->wpdb->get_results($query);
        }
    }
    
    public function get_all_license_keys_count($search = '', $status = '', $product_id = '') {
        $where_conditions = ['(is_group_product = 0 OR is_group_product IS NULL OR group_id IS NULL)']; // Exclude group keys
        $where_values = [];
        
        if (!empty($search)) {
            $where_conditions[] = "license_key LIKE %s";
            $where_values[] = '%' . $search . '%';
        }
        
        if (!empty($status) && in_array($status, ['unused', 'used'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = $status;
        }
        
        if (!empty($product_id)) {
            $where_conditions[] = "(product_ids IS NULL OR (product_ids != '' AND FIND_IN_SET(%d, product_ids) > 0))";
            $where_values[] = intval($product_id);
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        $query = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
        
        if (!empty($where_values)) {
            return $this->wpdb->get_var($this->wpdb->prepare($query, $where_values));
        } else {
            return $this->wpdb->get_var($query);
        }
    }
    
    // Alias for backward compatibility
    public function get_license_keys_count($search = '', $status = '', $product_id = '') {
        return $this->get_all_license_keys_count($search, $status, $product_id);
    }

    public function bulk_import($license_keys_text, $product_ids = [], $is_group_product = 0, $group_license_count = 3) {
        $keys_array = array_filter(array_map('trim', explode("\n", $license_keys_text)));
        $success_count = 0;
        $error_count = 0;
        
        foreach ($keys_array as $key) {
            if ($this->add_license_key($key, $product_ids, $is_group_product, $group_license_count)) {
                $success_count++;
            } else {
                $error_count++;
            }
        }
        
        return [
            'success' => $success_count,
            'errors' => $error_count
        ];
    }

    public function get_unused_license_key_count($product_id) {
        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'unused' AND (product_ids IS NULL OR (product_ids != '' AND FIND_IN_SET(%d, product_ids) > 0))",
            $product_id
        ));
    }
    
    public function get_statistics() {
        $stats = [];
        
        // Individual keys (not in groups)
        $individual_total = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE is_group_product = 0 OR is_group_product IS NULL OR group_id IS NULL");
        $individual_unused = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE (is_group_product = 0 OR is_group_product IS NULL OR group_id IS NULL) AND status = 'unused'");
        $individual_used = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE (is_group_product = 0 OR is_group_product IS NULL OR group_id IS NULL) AND status = 'used'");
        
        // Group counts (distinct groups)
        $group_total = (int) $this->wpdb->get_var("SELECT COUNT(DISTINCT group_id) FROM {$this->table_name} WHERE is_group_product = 1 AND group_id IS NOT NULL");
        
        // Group status counts (groups where all keys are unused/used)
        $group_unused = (int) $this->wpdb->get_var("
            SELECT COUNT(DISTINCT group_id) FROM {$this->table_name} 
            WHERE is_group_product = 1 AND group_id IS NOT NULL AND group_id NOT IN (
                SELECT DISTINCT group_id FROM {$this->table_name} 
                WHERE is_group_product = 1 AND group_id IS NOT NULL AND status = 'used'
            )
        ");
        $group_used = $group_total - $group_unused;
        
        // Combined totals (individual keys + groups as single units)
        $stats['total'] = $individual_total + $group_total;
        $stats['unused'] = $individual_unused + $group_unused;
        $stats['used'] = $individual_used + $group_used;
        
        // Separate counts for display
        $stats['group_products'] = $group_total;
        $stats['single_products'] = $individual_total;
        
        // Recent additions (last 7 days) - count groups as single units
        $recent_individual = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE (is_group_product = 0 OR is_group_product IS NULL OR group_id IS NULL) 
             AND created_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $recent_groups = (int) $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT group_id) FROM {$this->table_name} 
             WHERE is_group_product = 1 AND group_id IS NOT NULL 
             AND created_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $stats['recent_additions'] = $recent_individual + $recent_groups;
        
        // Recent usage (last 7 days) - count groups as single units when any key in group is used
        $recent_used_individual = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE (is_group_product = 0 OR is_group_product IS NULL OR group_id IS NULL) 
             AND used_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $recent_used_groups = (int) $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT group_id) FROM {$this->table_name} 
             WHERE is_group_product = 1 AND group_id IS NOT NULL 
             AND used_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $stats['recent_usage'] = $recent_used_individual + $recent_used_groups;
        
        return $stats;
    }
    
    public function mark_as_used($license_key, $order_id = null) {
        $update_data = [
            'status' => 'used',
            'used_date' => current_time('mysql')
        ];
        
        if ($order_id) {
            $update_data['order_id'] = intval($order_id);
        }
        
        return $this->wpdb->update(
            $this->table_name,
            $update_data,
            ['license_key' => $license_key]
        );
    }
    
    public function find_available_license_key($product_id = null) {
        $where_clause = "status = 'unused'";
        $params = [];
        
        if ($product_id) {
            $where_clause .= " AND (product_ids IS NULL OR (product_ids != '' AND FIND_IN_SET(%d, product_ids) > 0))";
            $params[] = intval($product_id);
        }
        
        $query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY created_date ASC LIMIT 1";
        
        if (!empty($params)) {
            return $this->wpdb->get_row($this->wpdb->prepare($query, $params));
        } else {
            return $this->wpdb->get_row($query);
        }
    }
    
    /**
     * Export license keys to CSV format
     */
    public function export_to_csv($status_filter = '', $product_filter = '') {
        $where_conditions = [];
        $params = [];
        
        if ($status_filter) {
            $where_conditions[] = "status = %s";
            $params[] = $status_filter;
        }
        
        if ($product_filter) {
            $where_conditions[] = "(product_ids IS NULL OR (product_ids != '' AND FIND_IN_SET(%d, product_ids) > 0))";
            $params[] = intval($product_filter);
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
        $query = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY created_date DESC";
        
        if (!empty($params)) {
            $license_keys = $this->wpdb->get_results($this->wpdb->prepare($query, $params));
        } else {
            $license_keys = $this->wpdb->get_results($query);
        }
        
        return $license_keys;
    }
    
    /**
     * Validate license key format
     */
    public function validate_license_key($license_key) {
        $errors = [];
        
        // Check length
        if (strlen($license_key) < 5) {
            $errors[] = "License key must be at least 5 characters long";
        }
        
        // Check for invalid characters (allow alphanumeric, dashes, spaces)
        if (!preg_match('/^[a-zA-Z0-9\-\s]+$/', $license_key)) {
            $errors[] = "License key contains invalid characters. Only letters, numbers, dashes and spaces are allowed";
        }
        
        // Check if already exists
        if ($this->license_key_exists($license_key)) {
            $errors[] = "This license key already exists";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Check if license key already exists
     */
    private function license_key_exists($license_key) {
        $result = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE license_key = %s",
            $license_key
        ));
        
        return $result > 0;
    }

    /**
     * Get unused group license by product
     *
     * @param int $product_id
     * @return array|null
     */
    public function get_unused_group_license_by_product($product_id) {
        error_log("Top Up Agent: Getting group license for product ID: $product_id");
        
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE status = 'unused' 
             AND is_group_product = 1
             AND (product_ids IS NULL OR (product_ids != '' AND FIND_IN_SET(%s, product_ids) > 0))
             ORDER BY created_date ASC
             LIMIT 1",
            $product_id
        ), ARRAY_A);

        if (!$result) {
            error_log("Top Up Agent: No group license found for product ID: $product_id");
            return null;
        }

        error_log("Top Up Agent: Found group license entry: " . print_r($result, true));

        // Get all license keys from the same group that are unused
        $group_licenses = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, license_key FROM {$this->table_name} 
             WHERE group_id = %s AND status = 'unused'
             ORDER BY created_date ASC",
            $result['group_id']
        ), ARRAY_A);

        if (empty($group_licenses)) {
            error_log("Top Up Agent: No unused licenses found in group: {$result['group_id']}");
            return null;
        }

        $license_keys = array_column($group_licenses, 'license_key');
        
        error_log("Top Up Agent: Group license keys retrieved: " . print_r($license_keys, true));

        return array(
            'license_keys' => $license_keys,
            'group_id' => $result['group_id'],
            'group_name' => $result['group_name'],
            'group_license_count' => intval($result['group_license_count']),
            'available_count' => count($license_keys)
        );
    }

    /**
     * Mark group license as used
     *
     * @param string $group_id
     * @param int $order_id
     * @return bool
     */
    public function mark_group_license_used($group_id, $order_id = null) {
        $update_data = [
            'status' => 'used',
            'used_date' => current_time('mysql')
        ];

        // Optionally store order ID if provided
        if ($order_id) {
            // Safely add order_id column if it doesn't exist
            $this->add_column_if_not_exists('order_id', 'int(11) DEFAULT NULL');
            $update_data['order_id'] = $order_id;
        }

        $result = $this->wpdb->update(
            $this->table_name,
            $update_data,
            ['group_id' => $group_id, 'status' => 'unused']
        );

        return $result !== false;
    }

    /**
     * Get license type (single or group)
     *
     * @param string $license_key
     * @return string|null
     */
    public function get_license_type($license_key) {
        $result = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT is_group_product FROM {$this->table_name} WHERE license_key = %s",
            $license_key
        ));

        if ($result === null) {
            return null;
        }

        return intval($result) === 1 ? 'group' : 'single';
    }

    /**
     * Get license data by license key
     *
     * @param string $license_key
     * @return array|null
     */
    public function get_license_data($license_key) {
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE license_key = %s",
            $license_key
        ), ARRAY_A);

        if (!$result) {
            return null;
        }

        $result['is_group'] = intval($result['is_group_product']) === 1;
        $result['type'] = $result['is_group'] ? 'group' : 'single';

        // If it's a group license, get all license keys from the group
        if ($result['is_group'] && $result['group_id']) {
            $group_licenses = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT license_key FROM {$this->table_name} 
                 WHERE group_id = %s
                 ORDER BY created_date ASC",
                $result['group_id']
            ), ARRAY_A);

            $result['group_license_keys'] = array_column($group_licenses, 'license_key');
        }

        return $result;
    }

    /**
     * Create a group license with multiple redimension codes
     *
     * @param array $license_keys Array of license keys/redimension codes
     * @param string $product_ids Comma-separated product IDs
     * @param string $group_name Human-readable group name
     * @return string|false Group ID on success, false on failure
     */
    public function create_group_license($license_keys, $product_ids, $group_name) {
        if (empty($license_keys) || !is_array($license_keys)) {
            error_log("Top Up Agent: Invalid license keys provided for group creation");
            return false;
        }

        // Generate unique group ID
        $group_id = 'GROUP_' . time() . '_' . substr(md5($group_name), 0, 8);
        
        error_log("Top Up Agent: Creating group license '$group_name' with ID: $group_id");
        error_log("Top Up Agent: License keys: " . print_r($license_keys, true));

        $success_count = 0;
        foreach ($license_keys as $license_key) {
            $result = $this->wpdb->insert(
                $this->table_name,
                [
                    'license_key' => $license_key,
                    'product_ids' => $product_ids,
                    'status' => 'unused',
                    'created_date' => current_time('mysql'),
                    'is_group_product' => 1,
                    'group_license_count' => count($license_keys),
                    'group_id' => $group_id,
                    'group_name' => $group_name
                ]
            );

            if ($result !== false) {
                $success_count++;
                error_log("Top Up Agent: Added license key to group: $license_key");
            } else {
                error_log("Top Up Agent: Failed to add license key to group: $license_key");
            }
        }

        if ($success_count > 0) {
            error_log("Top Up Agent: Group license created successfully. $success_count/$" . count($license_keys) . " keys added");
            return $group_id;
        } else {
            error_log("Top Up Agent: Failed to create group license");
            return false;
        }
    }

    /**
     * Get all group licenses
     *
     * @return array
     */
    public function get_all_group_licenses() {
        $results = $this->wpdb->get_results(
            "SELECT group_id, group_name, COUNT(*) as total_count, 
                    SUM(CASE WHEN status = 'unused' THEN 1 ELSE 0 END) as unused_count,
                    SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used_count,
                    product_ids, MIN(created_date) as created_date
             FROM {$this->table_name} 
             WHERE is_group_product = 1 AND group_id IS NOT NULL
             GROUP BY group_id, group_name, product_ids
             ORDER BY created_date DESC",
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get detailed group license information
     *
     * @param string $group_id
     * @return array|null
     */
    public function get_group_license_details($group_id) {
        $licenses = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE group_id = %s
             ORDER BY created_date ASC",
            $group_id
        ), ARRAY_A);

        if (empty($licenses)) {
            return null;
        }

        $first_license = $licenses[0];
        return [
            'group_id' => $group_id,
            'group_name' => $first_license['group_name'],
            'product_ids' => $first_license['product_ids'],
            'total_count' => count($licenses),
            'unused_count' => array_sum(array_map(function($l) { return $l['status'] === 'unused' ? 1 : 0; }, $licenses)),
            'used_count' => array_sum(array_map(function($l) { return $l['status'] === 'used' ? 1 : 0; }, $licenses)),
            'licenses' => $licenses
        ];
    }
}