<?php
/**
 * Asset Downloader for Top Up Agent Plugin
 * Downloads and manages frontend assets like Select2
 */

namespace TopUpAgent\Core;

class AssetDownloader {
    
    private static $assets = [
        'select2' => [
            'version' => '4.1.0-rc.0',
            'files' => [
                'css' => [
                    'url' => 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
                    'path' => 'assets/vendor/select2/select2.min.css'
                ],
                'js' => [
                    'url' => 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
                    'path' => 'assets/vendor/select2/select2.min.js'
                ]
            ]
        ]
    ];
    
    /**
     * Download all assets (called by Composer script)
     */
    public static function downloadAssets() {
        $plugin_dir = dirname(dirname(__DIR__));
        
        foreach (self::$assets as $asset_name => $asset_config) {
            self::downloadAsset($asset_name, $asset_config, $plugin_dir);
        }
    }
    
    /**
     * Download individual asset
     */
    private static function downloadAsset($name, $config, $plugin_dir) {
        echo "Downloading {$name} assets...\n";
        
        foreach ($config['files'] as $type => $file_config) {
            $target_path = $plugin_dir . '/' . $file_config['path'];
            $target_dir = dirname($target_path);
            
            // Create directory if it doesn't exist
            if (!is_dir($target_dir)) {
                wp_mkdir_p($target_dir);
                echo "Created directory: {$target_dir}\n";
            }
            
            // Download file if it doesn't exist or is outdated
            if (!file_exists($target_path) || self::shouldUpdate($target_path, $config['version'])) {
                $content = self::downloadFile($file_config['url']);
                
                if ($content !== false) {
                    file_put_contents($target_path, $content);
                    // Add version comment to track updates
                    self::addVersionComment($target_path, $config['version'], $type);
                    echo "Downloaded: {$file_config['path']}\n";
                } else {
                    echo "Failed to download: {$file_config['url']}\n";
                }
            } else {
                echo "Asset up to date: {$file_config['path']}\n";
            }
        }
    }
    
    /**
     * Download file content
     */
    private static function downloadFile($url) {
        // Use WordPress HTTP API if available, otherwise use file_get_contents
        if (function_exists('wp_remote_get')) {
            $response = wp_remote_get($url, [
                'timeout' => 30,
                'user-agent' => 'Top Up Agent Plugin Asset Downloader'
            ]);
            
            if (is_wp_error($response)) {
                return false;
            }
            
            return wp_remote_retrieve_body($response);
        } else {
            // Fallback for command line execution
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'Top Up Agent Plugin Asset Downloader'
                ]
            ]);
            
            return file_get_contents($url, false, $context);
        }
    }
    
    /**
     * Check if asset should be updated
     */
    private static function shouldUpdate($file_path, $version) {
        if (!file_exists($file_path)) {
            return true;
        }
        
        // Read first few lines to check version
        $handle = fopen($file_path, 'r');
        $first_lines = '';
        for ($i = 0; $i < 5; $i++) {
            $line = fgets($handle);
            if ($line === false) break;
            $first_lines .= $line;
        }
        fclose($handle);
        
        // Check if version matches
        return strpos($first_lines, "Version: {$version}") === false;
    }
    
    /**
     * Add version comment to file
     */
    private static function addVersionComment($file_path, $version, $type) {
        $content = file_get_contents($file_path);
        
        if ($type === 'css') {
            $comment = "/* Top Up Agent Plugin - Select2 v{$version} - Downloaded: " . date('Y-m-d H:i:s') . " */\n";
        } else {
            $comment = "/* Top Up Agent Plugin - Select2 v{$version} - Downloaded: " . date('Y-m-d H:i:s') . " */\n";
        }
        
        file_put_contents($file_path, $comment . $content);
    }
    
    /**
     * Download assets during plugin activation
     */
    public static function downloadOnActivation() {
        $plugin_dir = plugin_dir_path(__FILE__) . '../..';
        
        foreach (self::$assets as $asset_name => $asset_config) {
            self::downloadAsset($asset_name, $asset_config, $plugin_dir);
        }
    }
    
    /**
     * Check and download missing assets
     */
    public static function ensureAssetsExist() {
        $plugin_dir = plugin_dir_path(__FILE__) . '../..';
        $missing_assets = false;
        
        foreach (self::$assets as $asset_name => $asset_config) {
            foreach ($asset_config['files'] as $type => $file_config) {
                $target_path = $plugin_dir . '/' . $file_config['path'];
                if (!file_exists($target_path)) {
                    $missing_assets = true;
                    break 2;
                }
            }
        }
        
        if ($missing_assets) {
            self::downloadOnActivation();
        }
    }
    
    /**
     * Get local asset URL
     */
    public static function getAssetUrl($asset_name, $type) {
        if (!isset(self::$assets[$asset_name]['files'][$type])) {
            return false;
        }
        
        $file_config = self::$assets[$asset_name]['files'][$type];
        $plugin_url = plugin_dir_url(__FILE__) . '../..';
        
        return $plugin_url . $file_config['path'];
    }
    
    /**
     * Get local asset path
     */
    public static function getAssetPath($asset_name, $type) {
        if (!isset(self::$assets[$asset_name]['files'][$type])) {
            return false;
        }
        
        $file_config = self::$assets[$asset_name]['files'][$type];
        $plugin_path = plugin_dir_path(__FILE__) . '../..';
        
        return $plugin_path . $file_config['path'];
    }
    
    /**
     * Check if asset exists locally
     */
    public static function assetExists($asset_name, $type) {
        $path = self::getAssetPath($asset_name, $type);
        return $path && file_exists($path);
    }
}