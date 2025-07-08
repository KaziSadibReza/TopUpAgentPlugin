<?php

defined('ABSPATH') || exit;

/**
 * Available variables
 *
 * @var string $section
 * @var string $urlGeneral
 * @var string $urlRestApi
 * @var string $urlTools
 */

?>

<div class="wrap lmfwc">

    <?php settings_errors(); ?>
    <ul class="subsubsub"><li><a href="<?php echo esc_url($urlGeneral); ?>" class="<?=$section === 'general' ? 'current' : '';?>">
        <span><?php esc_html_e('General', 'top-up-agent');?></span>
    </a> | </li><li><a href="<?php echo esc_url($urlRestApi); ?>" class="<?=$section === 'rest_api' ? 'current' : '';?>">
        <span><?php esc_html_e('REST API', 'top-up-agent');?></span>
    </a> | </li><li><a href="<?php echo esc_url($urlTools); ?>" class="<?=$section === 'tools' ? 'current' : '';?>">
        <span><?php esc_html_e('Tools', 'top-up-agent');?></span>
    </a>  </li></ul>
    <br class="clear">

    <?php if ($section == 'general'): ?>

        <form action="<?php echo esc_url(admin_url('options.php')); ?>" method="POST">
            <?php settings_fields('tua_settings_group_general'); ?>
            <?php do_settings_sections('tua_license_keys'); ?>
            <?php do_settings_sections('tua_rest_api'); ?>
            <?php submit_button(); ?>
        </form>

    <?php elseif ($section === 'rest_api'): ?>

        <?php if ($action === 'list'): ?>

            <?php include_once 'settings/rest-api-list.php'; ?>

        <?php elseif ($action === 'show'): ?>

            <?php include_once 'settings/rest-api-show.php'; ?>

        <?php else: ?>

            <?php include_once 'settings/rest-api-key.php'; ?>

        <?php endif; ?>

    <?php elseif ($section === 'tools'): ?>

        <form action="<?php echo esc_url(admin_url('options.php')); ?>" method="POST">
            <?php settings_fields('tua_settings_group_tools'); ?>
            <?php do_settings_sections('tua_export'); ?>
            <?php submit_button(); ?>
        </form>

         <?php include_once 'settings/data-tools.php'; ?>


    <?php endif; ?>

</div>
