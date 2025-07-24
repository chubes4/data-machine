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

// Get all public taxonomies available for content publishing
$taxonomies = get_taxonomies(['public' => true], 'objects');
$taxonomy_options = [];

foreach ($taxonomies as $tax_slug => $tax_obj) {
    // Skip built-in taxonomies that aren't typically used for content publishing
    if (in_array($tax_slug, ['nav_menu', 'link_category', 'post_format'])) {
        continue;
    }
    
    $terms = get_terms(['taxonomy' => $tax_slug, 'hide_empty' => false]);
    if (!is_wp_error($terms) && !empty($terms)) {
        $taxonomy_options[$tax_slug] = [
            'label' => $tax_obj->label,
            'terms' => []
        ];
        
        // Add default options
        $taxonomy_options[$tax_slug]['terms'][''] = '-- Select ' . $tax_obj->label . ' --';
        $taxonomy_options[$tax_slug]['terms']['instruct_model'] = '-- Instruct Model --';
        
        // Add actual terms
        foreach ($terms as $term) {
            $taxonomy_options[$tax_slug]['terms'][$term->term_id] = $term->name;
        }
    }
}

// Legacy support: ensure category and post_tag have specific variables for backward compatibility
$category_options = $taxonomy_options['category']['terms'] ?? ['instruct_model' => '-- Instruct Model --'];
$tag_options = $taxonomy_options['post_tag']['terms'] ?? ['instruct_model' => '-- Instruct Model --'];
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
                <option value="draft"><?php _e('Draft', 'data-machine'); ?></option>
                <option value="publish"><?php _e('Publish', 'data-machine'); ?></option>
                <option value="pending"><?php _e('Pending Review', 'data-machine'); ?></option>
                <option value="private"><?php _e('Private', 'data-machine'); ?></option>
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
    <?php if (isset($taxonomy_options['category'])): // Only show if category taxonomy has terms ?>
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
    <?php endif; ?>
    <?php if (isset($taxonomy_options['post_tag'])): // Only show if post_tag taxonomy has terms ?>
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
    <?php endif; ?>
    <?php 
    // Dynamic Custom Taxonomy Rows - show all other public taxonomies
    foreach ($taxonomy_options as $tax_slug => $tax_data):
        // Skip category and post_tag as they already have dedicated rows above
        if (in_array($tax_slug, ['category', 'post_tag'])) {
            continue;
        }
        
        $tax_label = $tax_data['label'];
        $tax_terms = $tax_data['terms'];
    ?>
    <tr>
        <th scope="row"><label for="output_publish_local_<?php echo esc_attr($tax_slug); ?>"><?php echo esc_html($tax_label); ?></label></th>
        <td>
            <select id="output_publish_local_<?php echo esc_attr($tax_slug); ?>" name="output_config[publish_local][rest_<?php echo esc_attr($tax_slug); ?>]">
                <?php foreach ($tax_terms as $term_id => $term_name): ?>
                    <option value="<?php echo esc_attr($term_id); ?>"><?php echo esc_html($term_name); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php 
                /* translators: %s: taxonomy label */
                printf(__('Select a %s, let the AI choose, or instruct the AI using your prompt.', 'data-machine'), esc_html(strtolower($tax_label))); ?></p>
        </td>
    </tr>
    <?php endforeach; ?>
</table> 