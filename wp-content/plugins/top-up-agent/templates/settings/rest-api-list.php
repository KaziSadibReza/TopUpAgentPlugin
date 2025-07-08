<?php defined('ABSPATH') || exit; ?>

<h1>
    <span><?php esc_html_e('REST API', 'top-up-agent'); ?></span>
    <a class="add-new-h2" href="<?php echo esc_url( admin_url( sprintf( 'admin.php?page=%s&section=rest_api&create_key=1', \TopUpAgent\AdminMenus::SETTINGS_PAGE ) ) ); ?>">
        <span><?php esc_html_e( 'Add key', 'top-up-agent' ); ?></span>
    </a>

</h1>
<hr class="wp-header-end">

<form method="post">
    <?php
        $keys->prepare_items();
        $keys->views();
        $keys->search_box(__('Search key', 'top-up-agent'), 'key');
        $keys->display();
    ?>
</form>
