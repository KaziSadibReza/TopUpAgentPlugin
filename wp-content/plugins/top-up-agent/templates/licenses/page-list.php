<?php defined('ABSPATH') || exit; ?>

<h1 class="wp-heading-inline"><?php esc_html_e('License keys', 'top-up-agent'); ?></h1>
<a class="page-title-action" href="<?php echo esc_url($addLicenseUrl); ?>">
    <span><?php esc_html_e('Add new', 'top-up-agent');?></span>
</a>
<a class="page-title-action" href="<?php echo esc_url($importLicenseUrl); ?>">
    <span><?php esc_html_e('Import', 'top-up-agent');?></span>
</a>
<hr class="wp-header-end">

<?php  ?>

<form method="post" id="tua-license-table">
    <?php
        $licenses->prepare_items();
        $licenses->views();
        $licenses->search_box(__( 'Search license key', 'top-up-agent' ), 'license_key');
        $licenses->display();
    ?>
</form>

<span class="tua-txt-copied-to-clipboard" style="display: none"><?php esc_html_e('Copied to clipboard', 'top-up-agent'); ?></span>
