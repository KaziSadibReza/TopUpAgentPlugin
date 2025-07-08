<?php

use TopUpAgent\Models\Resources\ApiKey as ApiKeyResourceModel;

defined('ABSPATH') || exit;

/** @var ApiKeyResourceModel $keyData */

?>

<h2><?php esc_html_e('Key details', 'top-up-agent');?></h2>
<hr class="wp-header-end">

<form method="post" action="<?=esc_url(admin_url('admin-post.php'));?>">
    <input type="hidden" name="id" value="<?php echo esc_attr($keyId); ?>">
    <?php wp_nonce_field('tua-api-key-update'); ?>
    <input type="hidden" name="action" value="<?='tua_api_key_update';?>">
    <input type="hidden" name="tua_action" value="<?php echo esc_attr($action); ?>">

    <table class="form-table">
        <tbody>
            <tr scope="row">
                <th scope="row">
                    <label for="description"><?php esc_html_e('Description', 'top-up-agent');?></label>
                    <span class="text-danger">*</span>
                </th>
                <td>
                    <input
                        id="description"
                        class="regular-text"
                        name="description"
                        type="text"
                        value="<?php echo esc_attr($keyData->getDescription()); ?>"
                    >
                    <p class="description">
                        <b><?php esc_html_e('Required.', 'top-up-agent');?></b>
                        <span><?php esc_html_e('Friendly name for identifying this key.', 'top-up-agent');?></span>
                    </p>
                </td>
            </tr>
            <tr scope="row">
                <th scope="row">
                    <label for="user"><?php esc_html_e('User', 'top-up-agent');?></label>
                    <span class="text-danger">*</span></label>
                </th>
                <td>
                    <select id="user" class="regular-text" name="user">
                        <option value=""><?php esc_html_e('Please select a user...', 'top-up-agent'); ?></option>
                        <?php
                            foreach ($users as $user):
                                $selected = ($userId == $user->ID) ? 'selected="selected"' : '';

                                echo sprintf(
                                    '<option value="%s" %s>%s (#%d - %s)</option>',
                                    esc_attr( $user->ID ),
                                    selected( $user->ID, $selected, false ),
                                    esc_html( $user->user_login ),
                                    esc_attr( $user->ID ),
                                    esc_html( $user->user_email )
                                );
                            endforeach;
                        ?>
                    </select>
                    <p class="description">
                        <b><?php esc_html_e('Required.', 'top-up-agent');?></b>
                        <span><?php esc_html_e('Owner of these keys.', 'top-up-agent');?></span>
                    </p>
                </td>
            </tr>
            <tr scope="row">
                <th scope="row">
                    <label for="permissions"><?php esc_html_e('Permissions', 'top-up-agent');?></label>
                    <span class="text-danger">*</span></label>
                </th>
                <td>
                    <select id="permissions" class="regular-text" name="permissions">
                        <?php foreach ($permissions as $permissionId => $permissionName) : ?>
                            <option
                                value="<?php echo esc_attr($permissionId); ?>"
                                <?php selected($keyData->getPermissions(), $permissionId, true); ?>
                            >
                                <span><?php echo esc_html($permissionName); ?></span>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <b><?php esc_html_e('Required.', 'top-up-agent');?></b>
                        <span><?php esc_html_e('Select the access type of these keys.', 'top-up-agent');?></span>
                    </p>
                </td>
            </tr>
            <?php if ($action === 'edit'): ?>
                <tr scope="row">
                    <th scope="row">
                        <label><?php esc_html_e('Consumer key ending in', 'top-up-agent');?></label>
                    </th>
                    <td>
                        <code>&hellip;<?php echo esc_html($keyData->getTruncatedKey()); ?></code>
                    </td>
                </tr>
                <tr scope="row">
                    <th scope="row">
                        <label><?php esc_html_e('Last access', 'top-up-agent');?></label>
                    </th>
                    <td>
                        <?php 
                            if (!empty($keyData->getLastAccess())) {
                                echo esc_html(apply_filters('woocommerce_api_key_last_access_datetime', $date, $keyData->getLastAccess()));
                            } else {
                                esc_html_e('Unknown', 'top-up-agent');
                            }
                        ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if (0 === intval($keyId)): ?>
        <?php submit_button(__('Generate API key', 'top-up-agent'), 'primary', 'update_api_key'); ?>
    <?php else: ?>
        <p class="submit">
            <?php submit_button(__('Save changes', 'top-up-agent'), 'primary', 'update_api_key', false); ?>
            <a
                style="color: #a00; text-decoration: none; margin-left: 10px;"
                href="<?php echo esc_url(
                    wp_nonce_url(
                        add_query_arg(
                            array(
                                'action' => 'revoke',
                                'key' => $keyId
                            ),
                            sprintf(
                                esc_url(admin_url('admin.php?page=%s&section=rest_api')),
                                \TopUpAgent\AdminMenus::SETTINGS_PAGE
                            )
                        ),
                        'revoke'
                    )
                );?>"
            >
                <span><?php esc_html_e('Revoke key', 'top-up-agent'); ?></span>
            </a>
        </p>
    <?php endif; ?>
</form>
