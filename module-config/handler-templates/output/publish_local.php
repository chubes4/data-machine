<?php
/**
 * Publish Local Output Handler Template
 */

// Get necessary data for dropdowns
$post_type_options = [];
$post_types = get_post_types(['public' => true], 'objects');
$common_types = ['post' => 'Post', 'page' => 'Page'];
foreach ($common_types as $slug => $label) {
    if (isset($post_types[$slug])) {
        $post_type_options[$slug] = $label;
        unset($post_types[$slug]);
    }
}
foreach ($post_types as $pt) {
    $post_type_options[$pt->name] = $pt->label;
}

$category_options = [
    'model_decides' => '-- Let Model Decide --',
    'instruct_model' => '-- Instruct Model --'
];
$local_categories = get_terms(['taxonomy' => 'category', 'hide_empty' => false]);
if (!is_wp_error($local_categories)) {
    foreach ($local_categories as $cat) {
        $category_options[$cat->term_id] = $cat->name;
    }
}

$tag_options = [
    'model_decides' => '-- Let Model Decide --',
    'instruct_model' => '-- Instruct Model --'
];
$local_tags = get_terms(['taxonomy' => 'post_tag', 'hide_empty' => false]);
if (!is_wp_error($local_tags)) {
    foreach ($local_tags as $tag) {
        $tag_options[$tag->term_id] = $tag->name;
    }
}
?>
<table class="form-table">
    <tr>
        <th scope="row"><label for="output_publish_local_post_type"><?php _e('Post Type', 'data-machine'); ?></label></th>
        <td>
            <select id="output_publish_local_post_type" name="output_config[publish_local][post_type]">
                <?php foreach ($post_type_options as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php _e('Select the post type for published content.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="output_publish_local_post_status"><?php _e('Post Status', 'data-machine'); ?></label></th>
        <td>
            <select id="output_publish_local_post_status" name="output_config[publish_local][post_status]">
                <option value="draft"><?php _e('Draft'); ?></option>
                <option value="publish"><?php _e('Publish'); ?></option>
                <option value="pending"><?php _e('Pending Review'); ?></option>
                <option value="private"><?php _e('Private'); ?></option>
            </select>
            <p class="description"><?php _e('Select the status for the newly created post.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="output_publish_local_post_date_source"><?php _e('Post Date Setting', 'data-machine'); ?></label></th>
        <td>
            <select id="output_publish_local_post_date_source" name="output_config[publish_local][post_date_source]">
                <option value="current_date"><?php _e('Use Current Date', 'data-machine'); ?></option>
                <option value="source_date"><?php _e('Use Source Date (if available)', 'data-machine'); ?></option>
            </select>
            <p class="description"><?php _e('Choose whether to use the original date from the source (if available) or the current date when publishing. UTC timestamps will be converted to site time.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="output_publish_local_selected_local_category_id"><?php _e('Category', 'data-machine'); ?></label></th>
        <td>
            <select id="output_publish_local_selected_local_category_id" name="output_config[publish_local][selected_local_category_id]">
                <?php foreach ($category_options as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php _e('Select a category, let the AI choose, or instruct the AI using your prompt.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="output_publish_local_selected_local_tag_id"><?php _e('Tag', 'data-machine'); ?></label></th>
        <td>
            <select id="output_publish_local_selected_local_tag_id" name="output_config[publish_local][selected_local_tag_id]">
                 <?php foreach ($tag_options as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php _e('Select a single tag, let the AI choose, or instruct the AI using your prompt.', 'data-machine'); ?></p>
        </td>
    </tr>
</table> 