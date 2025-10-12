# Top Up Agent Plugin - Asset Management

This plugin now includes an automated asset management system that downloads and manages frontend dependencies locally for better performance and reliability.

## Features

- **Automated Asset Downloads**: Select2 and other dependencies are automatically downloaded during plugin activation
- **Local Asset Storage**: Assets are stored locally in the plugin for faster loading
- **CDN Fallback**: If local assets fail to load, the system falls back to CDN versions
- **Composer Integration**: Uses Composer for dependency management and autoloading
- **Version Control**: Tracks asset versions and updates when necessary

## Asset Management

### Automatic Download
Assets are automatically downloaded in these scenarios:
1. **Plugin Activation**: When the plugin is activated in WordPress
2. **Composer Install**: When running `composer install` or `composer update`
3. **Missing Assets**: When assets are missing and requested

### Supported Assets
- **Select2 v4.1.0-rc.0**: Enhanced select dropdowns
  - CSS: `assets/vendor/select2/select2.min.css`
  - JavaScript: `assets/vendor/select2/select2.min.js`

### Manual Setup
If you need to manually set up assets, run:

```bash
# Navigate to plugin directory
cd wp-content/plugins/top-up-agent

# Install Composer dependencies
composer install --no-dev --optimize-autoloader

# Or run the setup script
php setup-assets.php
```

## Development

### Adding New Assets
To add new frontend assets:

1. Update the `$assets` array in `includes/core/class-asset-downloader.php`
2. Add the asset configuration with URLs and local paths
3. Run `composer install` or activate the plugin to download

### Asset Structure
```
assets/
├── vendor/
│   └── select2/
│       ├── select2.min.css
│       └── select2.min.js
├── css/
│   └── license-management.css
└── js/
    └── license-management.js
```

## WordPress Integration

### Asset Enqueuing
The `Top_Up_Agent_Asset_Handler` class automatically:
- Detects if local assets exist
- Uses local assets when available
- Falls back to CDN if local assets are missing
- Manages proper WordPress enqueuing with dependencies

### Performance Benefits
- **Faster Loading**: Local assets load faster than CDN
- **Offline Support**: Works without internet connectivity
- **Cache Control**: Better control over asset caching
- **Reduced External Dependencies**: Less reliance on external CDNs

## Troubleshooting

### Assets Not Loading
1. Check if assets exist in `assets/vendor/` directory
2. Deactivate and reactivate the plugin
3. Run `composer install` manually
4. Check WordPress error logs

### Permission Issues
Ensure the plugin directory is writable:
```bash
chmod -R 755 wp-content/plugins/top-up-agent/assets
```

### Force Asset Re-download
To force re-download of assets:
1. Delete the `assets/vendor/` directory
2. Deactivate and reactivate the plugin
3. Or run `php setup-assets.php`

## File Locations

- **Asset Downloader**: `includes/core/class-asset-downloader.php`
- **Asset Handler**: `includes/core/class-top-up-agent-asset-handler.php`
- **Composer Config**: `composer.json`
- **Setup Script**: `setup-assets.php`
- **Local Assets**: `assets/vendor/`

## Requirements

- PHP 7.4+
- WordPress 5.0+
- Write permissions on plugin directory
- Internet connection for initial asset download

## License

This asset management system is part of the Top Up Agent plugin and follows the same licensing terms.