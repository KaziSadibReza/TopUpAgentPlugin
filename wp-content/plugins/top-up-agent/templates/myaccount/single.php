<?php
/**
 * The template for the overview of a single license inside "My Account"
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/lmfwc/myaccount/licenses/single.php
 *
 * HOWEVER, on occasion I will need to update template files and you
 * (the developer) will need to copy the new files to your theme to
 * maintain compatibility. I try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @version 1.0.0
 *
 * Default variables
 *
 * @var License $license
 * @var WC_Product $product
 * @var WC_Order $order
 * @var string $date_format
 * @var string $license_key
 * @var stdClass $message
 * 
 * Some of the code written, maintained by Darko Gjorgjijoski
 */

use TopUpAgent\Models\Resources\License;
use TopUpAgent\Enums\LicenseStatus;
use TopUpAgent\Settings;

defined( 'ABSPATH' ) || exit;
   
$licenseNonce     = wp_create_nonce( 'tua_nonce' );
$timesActivated   = $license->getTimesActivated() ? $license->getTimesActivated() : '0';
$activationsLimit = $license->getTimesActivatedMax() ? $license->getTimesActivatedMax() : '&infin;';
?>

<?php do_action( 'tua_myaccount_single_page_start', $license, $order, $product, $date_format, $license_key ); ?>

<h2><?php esc_html_e( 'License Details', 'top-up-agent' ); ?></h2>

<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
    <tbody>
    <tr>
        <th scope="row"><?php esc_html_e( 'Product', 'top-up-agent' ); ?></th>
        <td>
            <?php if ( $product ): ?>
                <a target="_blank" href="<?php echo esc_url( get_post_permalink( $product->get_id() ) ); ?>">
                    <span><?php echo esc_html( $product->get_name() ); ?></span>
                </a>
            <?php else: ?>
                <span>
                    <?php 
                    echo sprintf(
                        // translators: %s is the placeholder for the license ID.
                        esc_html__( 'License #%s', 'top-up-agent' ),
                        esc_html( $license->getId() )
                    );
                    ?>
                </span>
            <?php endif; ?>
        </td>
    </tr>
    <tr class="woocommerce-table__line-item license_keys">
        <th scope="row"><?php esc_html_e( 'License key', 'top-up-agent' ); ?></th>
        <td>
            <?php echo esc_html( $license_key ); ?>
        </td>
    </tr>
    <tr class="woocommerce-table__line-item activations_limit">
        <th scope="row"><?php esc_html_e( 'Activations', 'top-up-agent' ); ?></th>
        <td>
            <p>
                <span><?php echo esc_html( $timesActivated ); ?></span>
                <span>/</span>
                <span><?php echo esc_html( $activationsLimit ); ?></span>
            </p>
        </td>
    </tr>
    <tr class="woocommerce-table__line-item valid_until">
        <th scope="row"><?php esc_html_e( 'Expires', 'top-up-agent' ); ?></th>
        <td class="tua-inline-child tua-license-status">
            <?php
            if ( $license->getExpiresAt() ) {
                printf( '<b>%s</b>', esc_html( wp_date( tua_expiration_format(), strtotime( $license->getExpiresAt() ) ) ) );
            } elseif ( $license->getValidFor() ) {
                $validDate = date( 'Y-m-d', strtotime( $order->get_date_paid() . ' + ' . $license->getValidFor() . ' days' ) );
                printf( '<b>%s</b>', esc_html( wp_date( tua_expiration_format(), strtotime( $validDate ) ) ) );
            } else {
                esc_html_e( 'Never Expires', 'top-up-agent' );
            }
            ?>
        </td>
    </tr>

    <?php if ( Settings::get( 'tua_download_certificates', Settings::SECTION_WOOCOMMERCE ) ): ?> 
        <tr class="woocommerce-table__line-item valid_until">
            <th scope="row"><?php esc_html_e( 'Certificate', 'top-up-agent' ); ?></th>
            <td class="tua-inline-child tua-license-certificate">
                <form method="post" style="display: inline-block; margin: 0;">
                    <input type="hidden" name="license" value="<?php echo esc_attr( $license->getDecryptedLicenseKey() ); ?>"/>
                    <input type="hidden" name="action" value="tua_download_license_pdf">
                    <?php wp_nonce_field( 'tua_myaccount_download_certificates' ); ?>
                    <button class="button" type="submit"><?php esc_html_e( 'Download', 'top-up-agent' ); ?></button>
                </form>
            </td>
        </tr>
    <?php endif; ?>

    <?php do_action( 'tua_myaccount_licenses_single_page_table_details', $license, $order, $product, $date_format, $license_key ); ?>

    </tbody>

</table>

<p>
    <a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" class="woocommerce-button button tua-button"><?php esc_html_e( 'View Order', 'top-up-agent' ); ?></a>
</p>

<?php do_action( 'tua_myaccount_licenses_single_page_end', $license, $order, $product, $date_format, $license_key ); ?>
