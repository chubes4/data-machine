<?php
/**
 * Data Export Output Handler Template
 * Contains only the HTML markup for the data export output handler settings
 */
?>
<table class="form-table">
    <tr>
        <th scope="row"><label for="data_export_format">Export Format</label></th>
        <td>
            <select id="data_export_format" name="output_config[data_export][format]">
                <option value="json"><?php _e('JSON', 'data-machine'); ?></option>
                <option value="csv"><?php _e('CSV', 'data-machine'); ?></option>
                <option value="xml"><?php _e('XML', 'data-machine'); ?></option>
            </select>
            <p class="description"><?php _e('Select the format for data export', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="data_export_destination">Destination</label></th>
        <td>
            <select id="data_export_destination" name="output_config[data_export][destination]">
                <option value="browser"><?php _e('Browser Download', 'data-machine'); ?></option>
                <option value="file"><?php _e('Server File', 'data-machine'); ?></option>
            </select>
            <p class="description"><?php _e('Where to save the exported data', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="data_export_path">Export Path</label></th>
        <td>
            <input type="text" id="data_export_path" name="output_config[data_export][path]" class="regular-text" value="">
            <p class="description"><?php _e('Path where exports will be saved (when destination is "Server File")', 'data-machine'); ?></p>
        </td>
    </tr>
</table> 