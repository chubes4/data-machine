<?php
/**
 * Instagram Input Handler Template
 */
?>
<table class="form-table">
    <tr>
        <th scope="row"><label for="data_source_instagram_target_profiles"><?php _e('Target Instagram Profiles', 'data-machine'); ?></label></th>
        <td>
            <textarea id="data_source_instagram_target_profiles" name="data_source_config[instagram][target_profiles]" rows="5" class="large-text" required></textarea>
            <p class="description"><?php _e('Enter one Instagram profile handle per line to monitor (without @ symbol)', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="data_source_instagram_post_limit"><?php _e('Post Limit Per Profile', 'data-machine'); ?></label></th>
        <td>
            <input type="number" id="data_source_instagram_post_limit" name="data_source_config[instagram][post_limit]" value="5" class="small-text" min="1" max="20">
            <p class="description"><?php _e('Maximum number of recent posts to check per profile per run.', 'data-machine'); ?></p>
        </td>
    </tr>
</table> 