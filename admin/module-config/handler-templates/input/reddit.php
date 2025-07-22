<?php
/**
 * Reddit Input Handler Template
 */
?>
<table class="form-table">
    <tr>
        <th scope="row"><label for="data_source_reddit_subreddit"><?php _e('Subreddit Name', 'data-machine'); ?></label></th>
        <td>
            <input type="text" id="data_source_reddit_subreddit" name="data_source_config[reddit][subreddit]" value="" class="regular-text" placeholder="news">
            <p class="description"><?php _e('Enter the name of the subreddit (e.g., news, programming) without "r/".', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="data_source_reddit_sort_by"><?php _e('Sort By', 'data-machine'); ?></label></th>
        <td>
            <select id="data_source_reddit_sort_by" name="data_source_config[reddit][sort_by]">
                <option value="hot">Hot</option>
                <option value="new">New</option>
                <option value="top">Top (All Time)</option>
                <option value="rising">Rising</option>
            </select>
            <p class="description"><?php _e('Select how to sort the subreddit posts.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="data_source_reddit_item_count"><?php _e('Posts to Fetch', 'data-machine'); ?></label></th>
        <td>
            <input type="number" id="data_source_reddit_item_count" name="data_source_config[reddit][item_count]" value="1" class="small-text" min="1" max="100">
            <p class="description"><?php _e('Number of recent posts to check per run. The system will process the first new post found. Max 100.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="data_source_reddit_timeframe_limit"><?php _e('Process Posts Within', 'data-machine'); ?></label></th>
        <td>
            <select id="data_source_reddit_timeframe_limit" name="data_source_config[reddit][timeframe_limit]">
                <option value="all_time"><?php _e('All Time', 'data-machine'); ?></option>
                <option value="24_hours"><?php _e('Last 24 Hours', 'data-machine'); ?></option>
                <option value="72_hours"><?php _e('Last 72 Hours', 'data-machine'); ?></option>
                <option value="7_days"><?php _e('Last 7 Days', 'data-machine'); ?></option>
                <option value="30_days"><?php _e('Last 30 Days', 'data-machine'); ?></option>
            </select>
            <p class="description"><?php _e('Only consider posts created within this timeframe.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="data_source_reddit_min_upvotes"><?php _e('Minimum Upvotes', 'data-machine'); ?></label></th>
        <td>
            <input type="number" id="data_source_reddit_min_upvotes" name="data_source_config[reddit][min_upvotes]" value="0" class="small-text" min="0" max="100000">
            <p class="description"><?php _e('Only process posts with at least this many upvotes (score). Set to 0 to disable filtering.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="data_source_reddit_comment_count"><?php _e('Number of Top Comments', 'data-machine'); ?></label></th>
        <td>
            <input type="number" id="data_source_reddit_comment_count" name="data_source_config[reddit][comment_count]" value="10" class="small-text" min="0" max="20">
            <p class="description"><?php _e('Number of top-level comments to fetch for each post (0-20). Set to 0 to disable.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="data_source_reddit_min_comment_count"><?php _e('Minimum Comment Count', 'data-machine'); ?></label></th>
        <td>
            <input type="number" id="data_source_reddit_min_comment_count" name="data_source_config[reddit][min_comment_count]" value="0" class="small-text" min="0" max="100000">
            <p class="description"><?php _e('Only process posts with at least this many comments. Set to 0 to disable filtering.', 'data-machine'); ?></p>
           </td>
          </tr>
          <tr>
           <th scope="row"><label for="data_source_reddit_search"><?php _e('Search Term Filter', 'data-machine'); ?></label></th>
           <td>
            <input type="text" id="data_source_reddit_search" name="data_source_config[reddit][search]" value="" class="regular-text" placeholder="keyword1, keyword2">
            <p class="description"><?php _e('Optional: Filter posts locally by keywords (comma-separated). Only posts containing at least one keyword in their title or content (selftext) will be considered.', 'data-machine'); ?></p>
           </td>
          </tr>
         </table>