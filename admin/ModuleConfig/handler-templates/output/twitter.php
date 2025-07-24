<?php
/**
 * Twitter Output Handler Template
 */
?>
<table class="form-table">
    <tr>
        <td colspan="2">
            <p><?php printf(
                /* translators: %s: URL to the API Keys & Auth page */
                __('Twitter authentication is managed globally on the <a href="%s">API Keys & Auth</a> page.', 'data-machine'),
                esc_url(admin_url('admin.php?page=data-machine-api-keys')) // Adjust page slug if needed
            ); ?></p>
            <p><?php _e('You can adjust tweet behavior below.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="output_twitter_twitter_char_limit"><?php _e('Character Limit Override', 'data-machine'); ?></label></th>
        <td>
            <input type="number" id="output_twitter_twitter_char_limit" name="output_config[twitter][twitter_char_limit]" value="280" class="small-text" min="50" max="280">
            <p class="description"><?php _e('Set a custom character limit for tweets (default: 280). Text will be truncated if necessary.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><?php _e('Include Source Link', 'data-machine'); ?></th>
        <td>
            <label>
                <input type="checkbox" id="output_twitter_twitter_include_source" name="output_config[twitter][twitter_include_source]" value="1" checked>
                <?php _e('Append the original source URL to the tweet (if available and fits within character limits).', 'data-machine'); ?>
            </label>
        </td>
    </tr>
    <tr>
        <th scope="row"><?php _e('Enable Image Posting', 'data-machine'); ?></th>
        <td>
            <label>
                <input type="checkbox" id="output_twitter_twitter_enable_images" name="output_config[twitter][twitter_enable_images]" value="1" checked>
                 <?php _e('Attempt to find and upload an image from the source data (if available).', 'data-machine'); ?>
           </label>
        </td>
    </tr>
</table> 