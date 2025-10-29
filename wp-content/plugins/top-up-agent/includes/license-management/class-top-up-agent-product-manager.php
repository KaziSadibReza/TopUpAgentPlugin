<?php
class Top_Up_Agent_Product_Manager {
    private $products = [];
    
    public function __construct() {
        $this->load_products();
    }

    private function load_products() {
        $this->products = [];
        
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Get all products (simple and variable)
        $all_products = wc_get_products(['limit' => -1, 'status' => 'publish']);
        
        foreach ($all_products as $product) {
            $this->products[] = [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'type' => $product->get_type(),
                'parent_id' => 0
            ];
            
            // If it's a variable product, get variations
            if ($product->get_type() === 'variable') {
                $children = $product->get_children();
                foreach ($children as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if ($variation) {
                        $variation_attributes = wc_get_formatted_variation($variation, true);
                        $this->products[] = [
                            'id' => $variation->get_id(),
                            'name' => $product->get_name() . ' - ' . $variation_attributes,
                            'type' => 'variation',
                            'parent_id' => $product->get_id()
                        ];
                    }
                }
            }
        }
    }

    public function get_all_products() {
        return $this->products;
    }

    public function get_product_by_id($product_id) {
        foreach ($this->products as $product) {
            if ($product['id'] == $product_id) {
                return $product;
            }
        }
        return null;
    }

    public function get_products_by_ids($product_ids) {
        $result = [];
        foreach ($product_ids as $product_id) {
            $product = $this->get_product_by_id($product_id);
            if ($product) {
                $result[] = $product;
            }
        }
        return $result;
    }

    /**
     * Clean up product name for better display
     */
    private function clean_product_name($name) {
        // Remove extra colons and clean up formatting
        $cleaned = str_replace('::', ':', $name);
        $cleaned = trim($cleaned, ' :');
        
        // Remove excessive spacing
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        
        return $cleaned;
    }

    public function render_product_options($selected_products = [], $element_id = 'selected_products') {
        ?>
        <select name="selected_products" id="<?php echo $element_id; ?>" class="modern-select">
            <option value="">Select a product (or leave empty for all products)</option>
            <?php foreach ($this->products as $product): ?>
                <?php $display_name = $this->clean_product_name($product['name']); ?>
                <option value="<?php echo $product['id']; ?>" <?php echo in_array($product['id'], $selected_products) ? 'selected' : ''; ?>>
                    <?php echo esc_html($display_name . ' (ID: ' . $product['id'] . ')'); ?>
                    <?php if ($product['type'] === 'variation'): ?>
                        - Variation
                    <?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function render_bulk_product_options($selected_products = []) {
        ?>
        <select name="bulk_selected_products" id="bulk_selected_products" class="modern-select">
            <option value="">Select a product (or leave empty for all products)</option>
            <?php foreach ($this->products as $product): ?>
                <?php $display_name = $this->clean_product_name($product['name']); ?>
                <option value="<?php echo $product['id']; ?>" <?php echo in_array($product['id'], $selected_products) ? 'selected' : ''; ?>>
                    <?php echo esc_html($display_name . ' (ID: ' . $product['id'] . ')'); ?>
                    <?php if ($product['type'] === 'variation'): ?>
                        - Variation
                    <?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
}
