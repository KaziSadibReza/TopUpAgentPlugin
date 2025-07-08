<?php

defined('ABSPATH') || exit;

?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-admin-network" style="font-size: 24px; margin-right: 8px; color: #0073aa;"></span>
        <?php esc_html_e('Top Up Agent Dashboard', 'top-up-agent'); ?>
    </h1>
    
    <div style="margin-top: 20px;">
        <!-- License Statistics Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            
            <!-- Total Licenses Card -->
            <div class="postbox" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: white;">
                <div class="inside" style="padding: 20px;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="margin: 0; color: white; font-size: 14px; opacity: 0.9;">
                                <?php esc_html_e('Total Licenses', 'top-up-agent'); ?>
                            </h3>
                            <p style="margin: 5px 0 0 0; font-size: 32px; font-weight: 600; color: white;">
                                <?php echo esc_html($totalLicenses); ?>
                            </p>
                        </div>
                        <div style="opacity: 0.7;">
                            <span class="dashicons dashicons-admin-network" style="font-size: 48px;"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Licenses Card -->
            <div class="postbox" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border: none; color: white;">
                <div class="inside" style="padding: 20px;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="margin: 0; color: white; font-size: 14px; opacity: 0.9;">
                                <?php esc_html_e('Active Licenses', 'top-up-agent'); ?>
                            </h3>
                            <p style="margin: 5px 0 0 0; font-size: 32px; font-weight: 600; color: white;">
                                <?php echo esc_html($activeLicenses); ?>
                            </p>
                            <small style="opacity: 0.8;">
                                <?php echo esc_html($activePercentage); ?>% <?php esc_html_e('of total', 'top-up-agent'); ?>
                            </small>
                        </div>
                        <div style="opacity: 0.7;">
                            <span class="dashicons dashicons-yes-alt" style="font-size: 48px;"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inactive Licenses Card -->
            <div class="postbox" style="background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%); border: none; color: white;">
                <div class="inside" style="padding: 20px;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h3 style="margin: 0; color: white; font-size: 14px; opacity: 0.9;">
                                <?php esc_html_e('Inactive Licenses', 'top-up-agent'); ?>
                            </h3>
                            <p style="margin: 5px 0 0 0; font-size: 32px; font-weight: 600; color: white;">
                                <?php echo esc_html($inactiveLicenses); ?>
                            </p>
                            <small style="opacity: 0.8;">
                                <?php echo esc_html($inactivePercentage); ?>% <?php esc_html_e('of total', 'top-up-agent'); ?>
                            </small>
                        </div>
                        <div style="opacity: 0.7;">
                            <span class="dashicons dashicons-dismiss" style="font-size: 48px;"></span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Quick Actions -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
            
            <!-- Recent Licenses -->
            <div class="postbox">
                <h2 class="hndle">
                    <span class="dashicons dashicons-clock" style="margin-right: 8px;"></span>
                    <?php esc_html_e('Recent Licenses', 'top-up-agent'); ?>
                </h2>
                <div class="inside">
                    <?php if (!empty($recentLicenses)) : ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('License Key', 'top-up-agent'); ?></th>
                                    <th><?php esc_html_e('Status', 'top-up-agent'); ?></th>
                                    <th><?php esc_html_e('Product', 'top-up-agent'); ?></th>
                                    <th><?php esc_html_e('Created', 'top-up-agent'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentLicenses as $license) : ?>
                                    <tr>
                                        <td>
                                            <code style="background: #f1f1f1; padding: 2px 6px; border-radius: 3px; font-size: 12px;">
                                                <?php echo esc_html(substr($license->getDecryptedLicenseKey(), 0, 20) . '...'); ?>
                                            </code>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = $license->getStatus() == \TopUpAgent\Enums\LicenseStatus::ACTIVE ? 'active' : 'inactive';
                                            $statusColor = $license->getStatus() == \TopUpAgent\Enums\LicenseStatus::ACTIVE ? '#46b450' : '#dc3232';
                                            ?>
                                            <span style="display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; color: white; background: <?php echo esc_attr($statusColor); ?>;">
                                                <?php echo esc_html($license->getStatus() == \TopUpAgent\Enums\LicenseStatus::ACTIVE ? __('Active', 'top-up-agent') : __('Inactive', 'top-up-agent')); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($license->getProductId()) {
                                                $product = wc_get_product($license->getProductId());
                                                echo esc_html($product ? $product->get_name() : '#' . $license->getProductId());
                                            } else {
                                                echo '<em>' . esc_html__('No product', 'top-up-agent') . '</em>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php echo esc_html(wp_date(get_option('date_format'), strtotime($license->getCreatedAt()))); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p style="text-align: center; padding: 40px 20px; color: #666;">
                            <span class="dashicons dashicons-admin-network" style="font-size: 48px; opacity: 0.3; display: block; margin-bottom: 10px;"></span>
                            <?php esc_html_e('No licenses found yet.', 'top-up-agent'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="postbox">
                <h2 class="hndle">
                    <span class="dashicons dashicons-admin-tools" style="margin-right: 8px;"></span>
                    <?php esc_html_e('Quick Actions', 'top-up-agent'); ?>
                </h2>
                <div class="inside">
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . \TopUpAgent\AdminMenus::LICENSES_PAGE . '&action=add&_wpnonce=' . wp_create_nonce('add'))); ?>" 
                           class="button button-primary" style="text-align: center; padding: 12px;">
                            <span class="dashicons dashicons-plus-alt" style="margin-right: 5px;"></span>
                            <?php esc_html_e('Add New License', 'top-up-agent'); ?>
                        </a>

                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . \TopUpAgent\AdminMenus::LICENSES_PAGE)); ?>" 
                           class="button" style="text-align: center; padding: 12px;">
                            <span class="dashicons dashicons-list-view" style="margin-right: 5px;"></span>
                            <?php esc_html_e('View All Licenses', 'top-up-agent'); ?>
                        </a>

                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . \TopUpAgent\AdminMenus::LICENSES_PAGE . '&action=import&_wpnonce=' . wp_create_nonce('import'))); ?>" 
                           class="button" style="text-align: center; padding: 12px;">
                            <span class="dashicons dashicons-upload" style="margin-right: 5px;"></span>
                            <?php esc_html_e('Import Licenses', 'top-up-agent'); ?>
                        </a>

                        <hr style="margin: 10px 0;">

                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . \TopUpAgent\AdminMenus::SETTINGS_PAGE)); ?>" 
                           class="button" style="text-align: center; padding: 12px;">
                            <span class="dashicons dashicons-admin-settings" style="margin-right: 5px;"></span>
                            <?php esc_html_e('Settings', 'top-up-agent'); ?>
                        </a>

                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
.postbox h2.hndle {
    padding: 15px 20px;
    border-bottom: 1px solid #e1e1e1;
    margin: 0;
    font-weight: 600;
}

.postbox .inside {
    margin: 0;
}

.button:hover {
    transform: translateY(-1px);
    transition: transform 0.2s ease;
}
</style>
