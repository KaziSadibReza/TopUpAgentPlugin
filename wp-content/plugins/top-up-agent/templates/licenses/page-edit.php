<?php
use TopUpAgent\Models\Resources\License as LicenseResourceModel;

defined('ABSPATH') || exit;

/** @var LicenseResourceModel $license */
?>

<h1 class="wp-heading-inline"><?php esc_html_e('Edit license key', 'top-up-agent'); ?></h1>
<hr class="wp-header-end">

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <input type="hidden" name="source" value="<?php echo esc_attr($license->getSource()); ?>">
    <input type="hidden" name="action" value="tua_update_license_key">
    <?php wp_nonce_field('tua_update_license_key'); ?>

    <table class="form-table">
        <tbody>

        <!-- LICENSE ID -->
        <tr scope="row">
            <th scope="row"><label for="edit__license_id"><?php esc_html_e('ID', 'top-up-agent');?></label></th>
            <td>
                <input name="license_id" id="edit__license_id" class="regular-text" type="text" value="<?php echo esc_attr($license->getId()); ?>" readonly>
            </td>
        </tr>

        <!-- LICENSE KEY -->
        <tr scope="row">
            <th scope="row"><label for="edit__license_key"><?php esc_html_e('License key', 'top-up-agent');?></label></th>
            <td>
                <input name="license_key" id="edit__license_key"  required class="regular-text" type="text" value="<?php echo esc_attr($licenseKey); ?>">
                <p class="description"><?php esc_html_e('The license key will be encrypted before it is stored inside the database.', 'top-up-agent');?></p>
            </td>
        </tr>

        <!-- VALID FOR -->
        <tr scope="row">
            <th scope="row"><label for="edit__valid_for"><?php esc_html_e('Valid for (days)', 'top-up-agent');?></label></th>
            <td>
                <input name="valid_for" type="number" id="edit__valid_for" class="regular-text" value="<?php echo esc_attr($license->getValidFor()); ?>">
                <p class="description"><?php esc_html_e('Number of days for which the license key is valid after purchase. Leave blank if the license key does not expire. Cannot be used at the same time as the "Expires at" field.', 'top-up-agent');?></p>
            </td>
        </tr>

        <!-- EXPIRES AT -->
        <tr scope="row">
            <th scope="row"><label for="edit__expires_at"><?php esc_html_e('Expires at', 'top-up-agent');?></label></th>
            <td>
                <input name="expires_at" id="edit__expires_at" class="regular-text" type="text" value="<?php echo esc_attr($expiresAt); ?>">
                <p class="description"><?php esc_html_e('The exact date this license key expires on. Leave blank if the license key does not expire. Cannot be used at the same time as the "Valid for (days)" field.', 'top-up-agent');?></p>
            </td>
        </tr>

        <!-- TIMES ACTIVATED MAX -->
        <tr scope="row">
            <th scope="row"><label for="edit__times_activated_max"><?php esc_html_e('Maximum activation count', 'top-up-agent');?></label></th>
            <td>
                <input name="times_activated_max" id="edit__times_activated_max" class="regular-text" type="number" value="<?php echo esc_attr($license->getTimesActivatedMax()); ?>">
                <p class="description"><?php esc_html_e('Define how many times the license key can be marked as "activated" by using the REST API. Leave blank if you do not use the API.', 'top-up-agent');?></p>
            </td>
        </tr>

        <!-- STATUS -->
        <tr scope="row">
            <th scope="row"><label for="edit__status"><?php esc_html_e('Status', 'top-up-agent');?></label></th>
            <td>
                <select id="edit__status" name="status" class="regular-text">
                    <?php foreach($statusOptions as $option): ?>
                        <option value="<?php echo esc_attr($option['value']); ?>" <?php selected($option['value'], $license->getStatus(), true); ?>>
                            <span><?php echo esc_html($option['name']); ?></span>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>

        <!-- ORDER -->
        <tr scope="row">
            <th scope="row"><label for="edit__order"><?php esc_html_e('Order', 'top-up-agent');?></label></th>
            <td>
                <select name="order_id" id="edit__order" class="regular-text">
                    <?php
                    if ($license->getOrderId()) {
                        /** @var WC_Order $order */
                        $order = wc_get_order($license->getOrderId());
                        if ($order) {
                            echo sprintf(
                                '<option value="%d" selected="selected">#%d %s <%s></option>',
                                esc_attr($order->get_id()),
                                esc_html($order->get_id()),
                                esc_html($order->get_formatted_billing_full_name()),
                                esc_html($order->get_billing_email())
                            );
                        }
                    }
                    ?>
                </select>
                <p class="description"><?php esc_html_e('The product to which the license keys will be assigned.', 'top-up-agent');?></p>
            </td>
        </tr>

        <!-- PRODUCT -->
        <tr scope="row">
            <th scope="row"><label for="edit__product"><?php esc_html_e('Product', 'top-up-agent');?></label></th>
            <td>
                <select name="product_id" id="edit__product" class="regular-text">
                    <?php
                    if ($license->getProductId()) {
                        /** @var WC_Order $order */
                        $product = wc_get_product($license->getProductId());
                        if ($product) {
                            echo sprintf(
                                '<option value="%d" selected="selected">(#%d) %s</option>',
                                esc_attr($product->get_id()),
                                esc_html($product->get_id()),
                                esc_html($product->get_formatted_name())
                            );
                        }
                    }
                    ?>
                </select>
                <p class="description"><?php esc_html_e('The product to which the license keys will be assigned.', 'top-up-agent');?></p>
            </td>
        </tr>

        <!-- CUSTOMER -->
        <tr scope="row">
            <th scope="row"><label for="single__user"><?php esc_html_e('Customer', 'top-up-agent');?></label></th>
            <td>
                <select name="user_id" id="single__user" class="regular-text">
                    <?php
                    if ($license->getUserId()) {
                        /** @var WP_User $user */
                        $user = get_userdata($license->getUserId());
                        if ($user) {
                            echo sprintf(
                                '<option value="%d" selected="selected">%s (#%d - %s)</option>',
                                esc_attr($user->ID),
                                esc_html($user->user_nicename),
                                esc_html($user->ID),
                                esc_html($user->user_email)
                            );
                        }
                    }
                    ?>
                </select>
                <p class="description"><?php esc_html_e('The user to which the license keys will be assigned.', 'top-up-agent');?></p>
            </td>
        </tr>

        </tbody>
    </table>

    <p class="submit">
        <input name="submit" id="edit__submit" class="button button-primary" value="<?php esc_html_e('Save' ,'top-up-agent');?>" type="submit">
    </p>
</form>
