<?php 
/**
 * Some of the code written, maintained by Darko Gjorgjijoski
 */
?>
<h3><?php esc_html_e( 'Database Migration', 'top-up-agent' ); ?></h3>
<p><?php esc_html_e( 'This is one-click migration tool that makes it possible to migrate from other plugins easily. Please take database backups before starting this operation.', 'top-up-agent' ); ?></p>
<form class="tua-tool-form" id="tua-migrate-tool" method="POST">
<table class="form-table">
    
    <tbody>
        <tr>
           
        <td>
           <div class="tua-tool-form-row">
        <label for="identifier"><?php esc_html_e( 'Select plugin', 'top-up-agent' ); ?></label>
        <select id="identifier" name="plugin_name">
            <option value="dlm">
                <?php esc_html_e('Digital License Manager', 'top-up-agent' ); ?>
                </option>
            </select>
        </div>
        <div class="tua-tool-form-row">
            <label>
                <input type="checkbox" name="preserve_ids" value="1">
                <small style="color:red;"><?php esc_html_e( 'Preserve old IDs. If checked, your existing database will be wiped to remove/free used IDs. Use this ONLY if you are absolutely sure what you are doing and if your app depend on the existing license IDs.', 'top-up-agent' ); ?></small>
            </label>
        </div>
        <div class="tua-tool-form-row tua-tool-form-row-progress" style="display: none;">
            <div class="tua-tool-progress-bar">
                <p class="tua-tool-progress-bar-inner">&nbsp;</p>
            </div>
            <div class="tua-tool-progress-info"><?php esc_html_e( 'Initializing...', 'top-up-agent' ); ?></div>
        </div>
        <div class="tua-tool-form-row">
            <input type="hidden" name="id" value="">
            <input type="hidden" name="identifier" value="migrate">
            <button type="submit" class="button button-small button-primary"><?php esc_html_e( 'Migrate', 'top-up-agent' ); ?></button>
        </div> 

        </td>
    </tr>
    </tbody>
</table>

    
    </form>
