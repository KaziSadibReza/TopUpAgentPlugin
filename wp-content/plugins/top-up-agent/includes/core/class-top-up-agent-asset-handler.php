<?php
/**
 * Top Up Agent Asset Handler
 * Handles proper enqueueing of CSS and JavaScript files for specific admin pages
 */

class Top_Up_Agent_Asset_Handler {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Enqueue admin assets only on our plugin pages
     */
    public function enqueue_admin_assets($hook) {
        // Check if we're on one of our plugin pages
        if (!$this->is_our_admin_page($hook)) {
            return;
        }
        
        // Get current page
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        
        switch ($page) {
            case 'top-up-agent-license-keys':
                $this->enqueue_license_keys_assets();
                break;
            case 'top-up-agent-dashboard':
                $this->enqueue_dashboard_assets();
                break;
            case 'top-up-agent-woocommerce':
                $this->enqueue_woocommerce_assets();
                break;
            case 'top-up-agent-settings':
                $this->enqueue_settings_assets();
                break;
        }
    }
    
    /**
     * Check if we're on one of our admin pages
     */
    private function is_our_admin_page($hook) {
        // Check if we're on an admin page that belongs to our plugin
        $our_pages = array(
            'toplevel_page_top-up-agent-dashboard',
            'top-up-agent_page_top-up-agent-license-keys',
            'top-up-agent_page_top-up-agent-woocommerce',
            'top-up-agent_page_top-up-agent-settings'
        );
        
        return in_array($hook, $our_pages);
    }
    
    /**
     * Enqueue assets for License Keys page
     */
    private function enqueue_license_keys_assets() {
        $plugin_url = plugin_dir_url(__FILE__) . '../../';
        $plugin_path = plugin_dir_path(__FILE__) . '../../';
        
        // Enqueue jQuery (WordPress built-in)
        wp_enqueue_script('jquery');
        
        // Ensure Select2 assets are available
        require_once plugin_dir_path(__FILE__) . 'class-asset-downloader.php';
        \TopUpAgent\Core\AssetDownloader::ensureAssetsExist();
        
        // Enqueue Select2 CSS (local if available, fallback to CDN)
        if (\TopUpAgent\Core\AssetDownloader::assetExists('select2', 'css')) {
            $select2_css_url = \TopUpAgent\Core\AssetDownloader::getAssetUrl('select2', 'css');
            $select2_css_path = \TopUpAgent\Core\AssetDownloader::getAssetPath('select2', 'css');
            $select2_css_version = file_exists($select2_css_path) ? filemtime($select2_css_path) : '4.1.0-rc.0';
            
            wp_enqueue_style(
                'top-up-agent-select2',
                $select2_css_url,
                array(),
                $select2_css_version
            );
        } else {
            // Fallback to CDN
            wp_enqueue_style(
                'top-up-agent-select2',
                'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
                array(),
                '4.1.0-rc.0'
            );
        }
        
        // Enqueue Select2 JS (local if available, fallback to CDN)
        if (\TopUpAgent\Core\AssetDownloader::assetExists('select2', 'js')) {
            $select2_js_url = \TopUpAgent\Core\AssetDownloader::getAssetUrl('select2', 'js');
            $select2_js_path = \TopUpAgent\Core\AssetDownloader::getAssetPath('select2', 'js');
            $select2_js_version = file_exists($select2_js_path) ? filemtime($select2_js_path) : '4.1.0-rc.0';
            
            wp_enqueue_script(
                'top-up-agent-select2',
                $select2_js_url,
                array('jquery'),
                $select2_js_version,
                false // Load in header
            );
        } else {
            // Fallback to CDN
            wp_enqueue_script(
                'top-up-agent-select2',
                'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
                array('jquery'),
                '4.1.0-rc.0',
                false // Load in header
            );
        }
        
        // Enqueue our License Management CSS
        $css_file = $plugin_path . 'assets/css/license-management.css';
        $css_version = file_exists($css_file) ? filemtime($css_file) : '1.0.0';
        
        wp_enqueue_style(
            'top-up-agent-license-management',
            $plugin_url . 'assets/css/license-management.css',
            array(),
            $css_version
        );
        
        // Enqueue our License Management JS with its own version
        $js_file = $plugin_path . 'assets/js/license-management.js';
        $js_version = file_exists($js_file) ? filemtime($js_file) : '1.0.0';
        
        wp_enqueue_script(
            'top-up-agent-license-management',
            $plugin_url . 'assets/js/license-management.js',
            array('jquery', 'top-up-agent-select2'),
            $js_version, // Use JS file's own modification time
            false // Load in header for better performance
        );
        
        // Localize script for AJAX and other data
        wp_localize_script(
            'top-up-agent-license-management',
            'topUpAgentLicense',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('top_up_agent_license_nonce'),
                'strings' => array(
                    'copySuccess' => __('License key copied to clipboard!', 'top-up-agent'),
                    'copyError' => __('Failed to copy license key', 'top-up-agent'),
                    'copyNotSupported' => __('Copy not supported in this browser', 'top-up-agent'),
                    'confirmDelete' => __('Are you sure you want to delete this license key?', 'top-up-agent'),
                    'confirmDeleteGroup' => __('Are you sure you want to delete this entire group?', 'top-up-agent'),
                    'selectProducts' => __('Select products to see group name preview', 'top-up-agent'),
                    'allProductsSet' => __('All Products Set 1', 'top-up-agent'),
                    'mixedProductsSet' => __('Mixed Products Set 1', 'top-up-agent'),
                    'bulkAllProductsSet' => __('All Products Set 1, Set 2, Set 3...', 'top-up-agent'),
                    'bulkMixedProductsSet' => __('Mixed Products Set 1, Set 2, Set 3...', 'top-up-agent')
                )
            )
        );
    }
    
    /**
     * Enqueue assets for Dashboard page
     */
    private function enqueue_dashboard_assets() {
        // Enqueue jQuery
        wp_enqueue_script('jquery');
        
        // Dashboard-specific assets will be added when available
    }
    
    /**
     * Enqueue assets for WooCommerce page
     */
    private function enqueue_woocommerce_assets() {
        // Enqueue jQuery
        wp_enqueue_script('jquery');
        
        // WooCommerce-specific assets will be added when available
    }
    
    /**
     * Enqueue assets for Settings page
     */
    private function enqueue_settings_assets() {
        // Enqueue jQuery
        wp_enqueue_script('jquery');
        
        // Settings-specific assets will be added when available
    }
    
    /**
     * Get plugin version for cache busting
     */
    private function get_plugin_version() {
        $plugin_data = get_file_data(plugin_dir_path(__FILE__) . '../../top-up-agent.php', array('Version' => 'Version'));
        return $plugin_data['Version'] ?: '1.0.0';
    }
}

// Initialize the asset handler
Top_Up_Agent_Asset_Handler::get_instance();