<?php

defined('ABSPATH') || exit;

?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
            style="margin-right: 8px; vertical-align: text-bottom;">
            <path
                d="M12 1.25H11.9426C9.63423 1.24999 7.82519 1.24998 6.41371 1.43975C4.96897 1.63399 3.82895 2.03933 2.93414 2.93414C2.03933 3.82895 1.63399 4.96897 1.43975 6.41371C1.24998 7.82519 1.24999 9.63423 1.25 11.9426V12.0574C1.24999 14.3658 1.24998 16.1748 1.43975 17.5863C1.63399 19.031 2.03933 20.1711 2.93414 21.0659C3.82895 21.9607 4.96897 22.366 6.41371 22.5603C7.82519 22.75 9.63423 22.75 11.9426 22.75H12.0574C14.3658 22.75 16.1748 22.75 17.5863 22.5603C19.031 22.366 20.1711 21.9607 21.0659 21.0659C21.9607 20.1711 22.366 19.031 22.5603 17.5863C22.75 16.1748 22.75 14.3658 22.75 12.0574V12C22.75 11.5858 22.4142 11.25 22 11.25C21.5858 11.25 21.25 11.5858 21.25 12C21.25 14.3782 21.2484 16.0864 21.0736 17.3864C20.9018 18.6648 20.5749 19.4355 20.0052 20.0052C19.4355 20.5749 18.6648 20.9018 17.3864 21.0736C16.0864 21.2484 14.3782 21.25 12 21.25C9.62178 21.25 7.91356 21.2484 6.61358 21.0736C5.33517 20.9018 4.56445 20.5749 3.9948 20.0052C3.42514 19.4355 3.09825 18.6648 2.92637 17.3864C2.75159 16.0864 2.75 14.3782 2.75 12C2.75 9.62178 2.75159 7.91356 2.92637 6.61358C3.09825 5.33517 3.42514 4.56445 3.9948 3.9948C4.56445 3.42514 5.33517 3.09825 6.61358 2.92637C7.91356 2.75159 9.62178 2.75 12 2.75C12.4142 2.75 12.75 2.41421 12.75 2C12.75 1.58579 12.4142 1.25 12 1.25Z"
                fill="#0073aa" />
            <path
                d="M21.5303 3.53033C21.8232 3.23744 21.8232 2.76256 21.5303 2.46967C21.2374 2.17678 20.7626 2.17678 20.4697 2.46967L12.75 10.1893V6.65625C12.75 6.24204 12.4142 5.90625 12 5.90625C11.5858 5.90625 11.25 6.24204 11.25 6.65625V12C11.25 12.4142 11.5858 12.75 12 12.75H17.3438C17.758 12.75 18.0938 12.4142 18.0938 12C18.0938 11.5858 17.758 11.25 17.3438 11.25H13.8107L21.5303 3.53033Z"
                fill="#0073aa" />
        </svg>
        <?php esc_html_e('Top Up Agent Dashboard', 'top-up-agent'); ?>
    </h1>

    <div style="margin-top: 20px;">
        <!-- License Statistics Cards -->
        <div
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">

            <!-- Total Licenses Card -->
            <div class="postbox"
                style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: white;">
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
                            <span class="dashicons dashicons-admin-network"
                                style="width: 50px;; font-size: 48px;"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Licenses Card -->
            <div class="postbox"
                style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border: none; color: white;">
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
                                <?php echo esc_html($activePercentage); ?>%
                                <?php esc_html_e('of total', 'top-up-agent'); ?>
                            </small>
                        </div>
                        <div style="opacity: 0.7;">
                            <span class="dashicons dashicons-yes-alt" style="width: 50px; font-size: 48px;"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inactive Licenses Card -->
            <div class="postbox"
                style="background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%); border: none; color: white;">
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
                                <?php echo esc_html($inactivePercentage); ?>%
                                <?php esc_html_e('of total', 'top-up-agent'); ?>
                            </small>
                        </div>
                        <div style="opacity: 0.7;">
                            <span class="dashicons dashicons-dismiss" style="width: 50px; font-size: 48px;"></span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Quick Actions -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;" class="tua-dashboard-grid">

            <!-- Recent Licenses -->
            <div class="postbox">
                <h2 class="hndle">
                    <span class="dashicons dashicons-clock" style="margin-right: 8px;"></span>
                    <?php esc_html_e('Recent Licenses', 'top-up-agent'); ?>
                </h2>
                <div class="inside">
                    <?php if (!empty($recentLicenses)) : ?>
                    <div class="table-responsive">
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('License Key', 'top-up-agent'); ?></th>
                                    <th><?php esc_html_e('Status', 'top-up-agent'); ?></th>
                                    <th class="hide-mobile"><?php esc_html_e('Product', 'top-up-agent'); ?></th>
                                    <th class="hide-mobile"><?php esc_html_e('Created', 'top-up-agent'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentLicenses as $license) : ?>
                                <tr>
                                    <td>
                                        <code
                                            style="background: #f1f1f1; padding: 2px 6px; border-radius: 3px; font-size: 12px;">
                                                    <?php echo esc_html(substr($license->getDecryptedLicenseKey(), 0, 20) . '...'); ?>
                                                </code>
                                    </td>
                                    <td>
                                        <?php
                                                $statusClass = $license->getStatus() == \TopUpAgent\Enums\LicenseStatus::ACTIVE ? 'active' : 'inactive';
                                                $statusColor = $license->getStatus() == \TopUpAgent\Enums\LicenseStatus::ACTIVE ? '#46b450' : '#dc3232';
                                                ?>
                                        <span
                                            style="display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; color: white; background: <?php echo esc_attr($statusColor); ?>;">
                                            <?php echo esc_html($license->getStatus() == \TopUpAgent\Enums\LicenseStatus::ACTIVE ? __('Active', 'top-up-agent') : __('Inactive', 'top-up-agent')); ?>
                                        </span>
                                    </td>
                                    <td class="hide-mobile">
                                        <?php 
                                                if ($license->getProductId()) {
                                                    $product = wc_get_product($license->getProductId());
                                                    echo esc_html($product ? $product->get_name() : '#' . $license->getProductId());
                                                } else {
                                                    echo '<em>' . esc_html__('No product', 'top-up-agent') . '</em>';
                                                }
                                                ?>
                                    </td>
                                    <td class="hide-mobile">
                                        <?php echo esc_html(wp_date(get_option('date_format'), strtotime($license->getCreatedAt()))); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else : ?>
                    <p style="text-align: center; padding: 40px 20px; color: #666;">
                        <span class="dashicons dashicons-admin-network"
                            style=" font-size: 48px; opacity: 0.3; display: block; margin-bottom: 10px;"></span>
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
                            class="button button-primary"
                            style="text-align: center; padding:12px; display: flex; flex-direction: row; align-items: center; justify-content: center;">
                            <span class="dashicons dashicons-plus-alt" style="margin-right: 5px;"></span>
                            <?php esc_html_e('Add New License', 'top-up-agent'); ?>
                        </a>

                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . \TopUpAgent\AdminMenus::LICENSES_PAGE)); ?>"
                            class="button"
                            style="text-align: center; padding:12px; display: flex; flex-direction: row; align-items: center; justify-content: center;">
                            <span class="dashicons dashicons-list-view" style="margin-right: 5px;"></span>
                            <?php esc_html_e('View All Licenses', 'top-up-agent'); ?>
                        </a>

                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . \TopUpAgent\AdminMenus::LICENSES_PAGE . '&action=import&_wpnonce=' . wp_create_nonce('import'))); ?>"
                            class="button"
                            style="text-align: center; padding:12px; display: flex; flex-direction: row; align-items: center; justify-content: center;">
                            <span class="dashicons dashicons-upload" style="margin-right: 5px;"></span>
                            <?php esc_html_e('Import Licenses', 'top-up-agent'); ?>
                        </a>

                        <hr style="margin: 10px 0;">

                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . \TopUpAgent\AdminMenus::SETTINGS_PAGE)); ?>"
                            class="button"
                            style="text-align: center; padding:12px; display: flex; flex-direction: row; align-items: center; justify-content: center;">
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
    padding: 12px;
}

.button:hover {
    transform: translateY(-1px);
    transition: transform 0.2s ease;
}

/* Mobile Responsive Styles */
@media screen and (max-width: 768px) {

    /* Stack dashboard grid vertically on mobile */
    .tua-dashboard-grid {
        display: grid !important;
        grid-template-columns: 1fr !important;
        gap: 15px !important;
    }

    /* Hide specific columns on mobile */
    .hide-mobile {
        display: none !important;
    }

    /* Adjust card statistics for mobile */
    .postbox .inside {
        padding: 15px !important;
    }

    /* Make statistics cards smaller on mobile */
    .postbox[style*="linear-gradient"] .inside {
        padding: 15px !important;
    }

    .postbox[style*="linear-gradient"] h3 {
        font-size: 12px !important;
    }

    .postbox[style*="linear-gradient"] p {
        font-size: 24px !important;
    }

    .postbox[style*="linear-gradient"] .dashicons {
        font-size: 36px !important;
    }

    /* Responsive table */
    .table-responsive {
        overflow-x: auto;
    }

    .table-responsive table {
        min-width: 300px;
    }

    /* Adjust button text on mobile */
    .button {
        font-size: 14px !important;
        padding: 8px 12px !important;
    }

    /* Adjust main heading */
    .wp-heading-inline svg {
        width: 20px !important;
        height: 20px !important;
        margin-right: 5px !important;
    }

    /* Stack quick action buttons better on mobile */
    .postbox .inside>div {
        gap: 12px !important;
    }
}

@media screen and (max-width: 480px) {

    /* Extra small screens */
    .postbox h2.hndle {
        padding: 12px 15px !important;
        font-size: 14px !important;
    }

    .postbox .inside {
        padding: 10px !important;
    }

    /* Smaller statistics text */
    .postbox[style*="linear-gradient"] p {
        font-size: 20px !important;
    }

    .postbox[style*="linear-gradient"] .dashicons {
        font-size: 30px !important;
    }

    /* Adjust license key display */
    code {
        font-size: 10px !important;
        padding: 1px 4px !important;
    }

    /* Smaller status badges */
    span[style*="border-radius: 12px"] {
        font-size: 9px !important;
        padding: 1px 6px !important;
    }
}

/* Tablet styles */
@media screen and (min-width: 769px) and (max-width: 1024px) {

    /* Adjust grid for tablets */
    .tua-dashboard-grid {
        grid-template-columns: 1.5fr 1fr !important;
        gap: 15px !important;
    }

    /* Show all table columns on tablets */
    .hide-mobile {
        display: table-cell !important;
    }
}

/* Large screen optimizations */
@media screen and (min-width: 1200px) {
    .postbox .inside {
        padding: 20px !important;
    }

    .tua-dashboard-grid {
        gap: 25px !important;
    }
}
</style>