<form method="post" id="export_by_date_form" action="<?php echo esc_url( admin_url( 'admin.php?page=tua_licenses&action=process_date_export&_wpnonce=fc1129165a' ) ); ?>" id="tua-license-table">
<h2> Export License Keys by Date (CSV) </h2> 
<p> <i> - Select the date range to export the License Keys created between </i> </p>
<hr/>
<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_html( wp_create_nonce('export_date') ); ?>">
   <table>
        <tr>
            <td> <strong> Start Date: </strong> </td>
        <td><input type="date" value="export_csv_date" name="start_date" required/> </td>
        </tr>
        
        <tr>
        <td> <strong> End Date: </strong> </td>
        <td><input type="date" value="export_csv_date" name="end_date" required/> </td>
        </tr>

        <tr>
            <td> </td>
        <td> <input type="checkbox" name="unassigned"/> <strong> Unassigned Only </strong> </td>
        </tr>

        <tr>
            <td> <input type="submit" id="search-submit" class="button" value="Export">	 </td>

            <?php if ( isset( $_GET['error'] ) ) { ?>
            <td id="export_date_error"> <p style="color: red;"> No License Keys Found </p> </td>
            <?php } ?>
        </tr>
    </table>

    
    
    
</form>
