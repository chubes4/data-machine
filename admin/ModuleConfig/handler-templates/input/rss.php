<?php
/**
 * RSS Input Handler Template
 * Contains only the HTML markup for the RSS input handler settings
 */
?>
<table class="form-table">
    <tr>
        <th scope="row"><label for="data_source_rss_feed_url"><?php _e('Feed URL', 'data-machine'); ?></label></th>
        <td>
            <input type="url" id="data_source_rss_feed_url" name="data_source_config[rss][feed_url]" value="" class="regular-text" placeholder="https://example.com/feed/">
            <p class="description"><?php _e('The URL of the RSS or Atom feed.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="data_source_rss_item_count"><?php _e('Items to Fetch', 'data-machine'); ?></label></th>
        <td>
            <input type="number" id="data_source_rss_item_count" name="data_source_config[rss][item_count]" value="10" class="small-text" min="1" max="100">
            <p class="description"><?php _e('Number of recent feed items to check per run. The system will process the first new item found.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="data_source_rss_timeframe_limit"><?php _e('Process Items Within', 'data-machine'); ?></label></th>
        <td>
            <select id="data_source_rss_timeframe_limit" name="data_source_config[rss][timeframe_limit]">
                <option value="all_time"><?php _e('All Time', 'data-machine'); ?></option>
                <option value="24_hours"><?php _e('Last 24 Hours', 'data-machine'); ?></option>
                <option value="72_hours"><?php _e('Last 72 Hours', 'data-machine'); ?></option>
                <option value="7_days"><?php _e('Last 7 Days', 'data-machine'); ?></option>
                <option value="30_days"><?php _e('Last 30 Days', 'data-machine'); ?></option>
            </select>
            <p class="description"><?php _e('Only consider items published within this timeframe. Helps ensure freshness and avoid processing very old items.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="data_source_rss_search"><?php _e('Search Term Filter', 'data-machine'); ?></label></th>
        <td>
            <input type="text" id="data_source_rss_search" name="data_source_config[rss][search]" value="" class="regular-text">
            <p class="description"><?php _e('Optional: Filter items locally by keywords (comma-separated). Only items containing at least one keyword in their title or content (text only) will be considered.', 'data-machine'); ?></p>
        </td>
    </tr>
</table> 