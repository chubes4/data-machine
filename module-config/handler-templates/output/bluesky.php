<?php
/**
 * Bluesky Output Handler Template
 */
?>
<table class="form-table">
    <tr>
        <td colspan="2">
            <p><?php printf(
                __('Bluesky configuration (handle and app password) is managed globally on the <a href="%s">API Keys & Auth</a> page.', 'data-machine'),
                esc_url(admin_url('admin.php?page=data-machine-api-keys')) // Adjust page slug if needed
            ); ?></p>
            <p><?php _e('You can choose whether to include the source link and image (if available) below.', 'data-machine'); ?></p>
        </td>
    </tr>
     <tr>
        <th scope="row"><?php _e('Include Source Link', 'data-machine'); ?></th>
        <td>
            <label>
                <input type="checkbox" name="output_config[bluesky][bluesky_include_source]" value="1" checked>
                <?php _e('Append source URL to the post (if available and fits within character limit)', 'data-machine'); ?>
            </label>
        </td>
    </tr>
     <tr>
        <th scope="row"><?php _e('Enable Image Upload', 'data-machine'); ?></th>
        <td>
            <label>
                <input type="checkbox" name="output_config[bluesky][bluesky_enable_images]" value="1" checked>
                <?php _e('Attempt to upload an image to the post (if available from source)', 'data-machine'); ?>
            </label>
        </td>
    </tr>
</table> 