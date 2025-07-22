<?php
/**
 * Public REST API Input Handler Template
 */
?>
<table class="form-table">
    <tr>
        <th scope="row"><label for="data_source_public_rest_api_api_endpoint_url"><?php _e('API Endpoint URL', 'data-machine'); ?></label></th>
        <td>
            <input type="url" id="data_source_public_rest_api_api_endpoint_url" name="data_source_config[public_rest_api][api_endpoint_url]" value="" class="regular-text" required>
            <p class="description"><?php _e('Enter the full URL of the public REST API endpoint (e.g., https://example.com/wp-json/wp/v2/posts). Standard WP REST API query parameters like ?per_page=X&orderby=date&order=desc are usually supported and added automatically, but you can add custom ones here if needed.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="data_source_public_rest_api_data_path"><?php _e('Data Path (Optional)', 'data-machine'); ?></label></th>
        <td>
            <input type="text" id="data_source_public_rest_api_data_path" name="data_source_config[public_rest_api][data_path]" value="" class="regular-text">
            <p class="description"><?php _e('If the items are nested within the JSON response, specify the path using dot notation (e.g., `data.items`). Leave empty to auto-detect the first array of objects.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="data_source_public_rest_api_search"><?php _e('Search Term', 'data-machine'); ?></label></th>
        <td>
            <input type="text" id="data_source_public_rest_api_search" name="data_source_config[public_rest_api][search]" value="" class="regular-text">
            <p class="description"><?php _e('Optionally filter results locally by keywords (comma-separated). Only items containing at least one keyword in their title or content will be processed.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="data_source_public_rest_api_item_count"><?php _e('Items to Process', 'data-machine'); ?></label></th>
        <td>
            <input type="number" id="data_source_public_rest_api_item_count" name="data_source_config[public_rest_api][item_count]" value="1" class="small-text" min="1" max="100">
            <p class="description"><?php _e('Maximum number of *new* items to process per run.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="data_source_public_rest_api_timeframe_limit"><?php _e('Process Items Within', 'data-machine'); ?></label></th>
        <td>
            <select id="data_source_public_rest_api_timeframe_limit" name="data_source_config[public_rest_api][timeframe_limit]">
                <option value="all_time"><?php _e('All Time', 'data-machine'); ?></option>
                <option value="24_hours"><?php _e('Last 24 Hours', 'data-machine'); ?></option>
                <option value="72_hours"><?php _e('Last 72 Hours', 'data-machine'); ?></option>
                <option value="7_days"><?php _e('Last 7 Days', 'data-machine'); ?></option>
                <option value="30_days"><?php _e('Last 30 Days', 'data-machine'); ?></option>
            </select>
            <p class="description"><?php _e('Only consider items published within this timeframe. Requires a parsable date field in the API response.', 'data-machine'); ?></p>
        </td>
    </tr>
</table> 