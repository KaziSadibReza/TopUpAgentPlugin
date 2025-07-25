<?php
/**
 * The template which contains the license key delivery notice, instead of the license keys.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/tua-email-order-license-notice.php.
 *
 * HOWEVER, on occasion I will need to update template files and you
 * (the developer) will need to copy the new files to your theme to
 * maintain compatibility. I try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @version 2.0.0
 */
defined('ABSPATH') || exit; ?>

<?php
$text = apply_filters('tua_text_license_table_header', null);
if ($text) {
    echo '<h2>' . esc_html($text) . '</h2>';
}
?>
<p><?php esc_html_e('Your license keys will shortly be delivered. It can take up to 24 hours, but usually doesn\'t take longer than a few minutes. Thank you for your patience.', 'top-up-agent'); ?></p>
