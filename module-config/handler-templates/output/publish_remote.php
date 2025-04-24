<?php
/**
 * Publish Remote Output Handler Template
 */

// Access the data passed from the AJAX handler via GLOBALS
$all_locations            = $GLOBALS['dm_template_all_locations'] ?? [];
// $site_info             = $GLOBALS['dm_template_site_info'] ?? null; // No longer needed directly
$saved_config             = $GLOBALS['dm_template_saved_config'] ?? [];
// $enabled_post_types    = $GLOBALS['dm_template_enabled_post_types'] ?? []; // No longer needed directly
$enabled_taxonomies       = $GLOBALS['dm_template_enabled_taxonomies'] ?? []; // Still needed for conditional rows

// Get the pre-filtered options arrays
$post_type_options = $GLOBALS['dm_template_filtered_post_type_options'] ?? [];
$category_options  = $GLOBALS['dm_template_filtered_category_options'] ?? [];
$tag_options       = $GLOBALS['dm_template_filtered_tag_options'] ?? [];
$custom_taxonomies = $GLOBALS['dm_template_filtered_custom_taxonomies'] ?? []; // This now holds [slug => [label=>..., terms=>...]]

// Helper function to generate select options (if not already defined by airdrop template)
if (!function_exists('dm_generate_options')) {
    function dm_generate_options($options, $selected_value = '', $use_key_as_value = false) {
        $html = '';
        // Add initial log for the whole options array
        // error_log('[dm_generate_options] Received options: ' . print_r($options, true)); 
        foreach ($options as $key => $option) {
            $value = null; 
            $label = null; 
            // Log the individual option being processed
            // error_log('[dm_generate_options] Processing option [' . $key . ']: ' . print_r($option, true));

            if ($use_key_as_value) {
                $value = $key;
                $label = $option; 
            } elseif (is_object($option)) { // Handle objects (e.g., terms)
                $value = $option->term_id ?? ($option->location_id ?? ($option->name ?? null)); // Use null default
                $label = $option->name ?? ($option->location_name ?? null); // Use null default
            } elseif (is_array($option)) { // Handle arrays 
                // *** ADJUSTED LOGIC FOR ARRAY ***
                // Prioritize term_id/name for taxonomy terms, then common patterns
                $value = $option['term_id'] ?? ($option['value'] ?? ($option['location_id'] ?? null)); 
                $label = $option['name'] ?? ($option['text'] ?? ($option['label'] ?? ($option['location_name'] ?? null))); 
            } elseif (is_string($option)) { // Handle simple string options if needed
                $value = $option;
                $label = $option;
            }

            // Ensure value and label are treated as strings for comparison and output
            // Check BEFORE casting if extraction failed (null)
             if ($value === null || $label === null) {
                 error_log('[dm_generate_options] Skipping option due to failed value/label extraction: ' . print_r($option, true)); // DEBUG
                 continue; // Skip this iteration
             }

            $value = (string)$value;
            $label = (string)$label;
            $selected_value = (string)$selected_value;
            
            // Check if empty AFTER casting, in case '0' is a valid value
            if ($label !== '') { // Only really need a label to display
                $html .= '<option value="' . esc_attr($value) . '" ' . selected($selected_value, $value, false) . '>' . esc_html($label) . '</option>';
            } else {
                 error_log('[dm_generate_options] Skipping option due to empty label after casting: ' . print_r($option, true)); // DEBUG
            }
        }
        return $html;
    }
}

// Determine selected values from saved config
$is_location_change = !empty($GLOBALS['dm_template_selected_location_id']);
$selected_location_id  = $GLOBALS['dm_template_selected_location_id'] ?? null; // Use the ID passed from AJAX

// If location changed, reset dependents to defaults, otherwise load from saved config
$selected_post_type    = $is_location_change ? null : ($saved_config['selected_remote_post_type'] ?? null);
$selected_category_id  = $is_location_change ? null : ($saved_config['selected_remote_category_id'] ?? null);
$selected_tag_id       = $is_location_change ? null : ($saved_config['selected_remote_tag_id'] ?? null);
$selected_post_status  = $is_location_change ? 'draft' : ($saved_config['remote_post_status'] ?? 'draft');
$selected_date_source  = $is_location_change ? 'current_date' : ($saved_config['post_date_source'] ?? 'current_date');
$selected_custom_tax   = $is_location_change ? [] : ($saved_config['selected_custom_taxonomy_values'] ?? []);

// Determine if dependent fields should be disabled
// Dependents are disabled if *no* location is selected OR if site_info hasn't loaded for the selected one.
$dependents_disabled = empty($selected_location_id);

// Default options (still needed)
$category_default_opts = [
    ['value' => '', 'text' => '-- Select Category --'],
    ['value' => 'model_decides', 'text' => '-- Let Model Decide --'],
    ['value' => 'instruct_model', 'text' => '-- Instruct Model --']
];
$tag_default_opts = [
     ['value' => '', 'text' => '-- Select Tag --'],
    ['value' => 'model_decides', 'text' => '-- Let Model Decide --'],
    ['value' => 'instruct_model', 'text' => '-- Instruct Model --']
];

?>
<table class="form-table">
    <tr>
        <th scope="row"><label for="output_publish_remote_location_id"><?php _e('Remote Location', 'data-machine'); ?></label></th>
        <td>
            <select id="output_publish_remote_location_id" name="output_config[publish_remote][location_id]">
                <option value=""><?php _e('-- Select Location --', 'data-machine'); ?></option>
                 <?php echo dm_generate_options($all_locations, $selected_location_id); ?>
            </select>
            <p class="description">
                 <?php printf(
                    __('Select a pre-configured remote publishing location. Manage locations <a href="%s" target="_blank">here</a>.', 'data-machine'),
                    esc_url(admin_url('admin.php?page=dm-remote-locations'))
                ); ?>
            </p>
            <?php /* <div id="dm-sync-publish-remote-feedback" class="dm-sync-feedback" style="margin-top: 5px;"></div> Remove old sync button area */ ?>
        </td>
    </tr>
    <tr id="dm-remote-post-type-wrapper" class="dm-remote-field-row">
        <th scope="row"><label for="output_publish_remote_selected_remote_post_type"><?php _e('Remote Post Type', 'data-machine'); ?></label></th>
        <td>
            <select id="output_publish_remote_selected_remote_post_type" name="output_config[publish_remote][selected_remote_post_type]" <?php disabled($dependents_disabled || empty($post_type_options)); ?>>
                 <option value=""><?php _e('-- Select Post Type --', 'data-machine'); ?></option>
                 <?php echo dm_generate_options($post_type_options, $selected_post_type); ?>
            </select>
            <p class="description"><?php _e('Select the post type on the target site (Only enabled types shown).', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="output_publish_remote_remote_post_status"><?php _e('Remote Post Status', 'data-machine'); ?></label></th>
        <td>
            <select id="output_publish_remote_remote_post_status" name="output_config[publish_remote][remote_post_status]" <?php disabled($dependents_disabled); ?>>
                <option value="draft" <?php selected($selected_post_status, 'draft'); ?>><?php _e('Draft', 'data-machine'); ?></option>
                <option value="publish" <?php selected($selected_post_status, 'publish'); ?>><?php _e('Publish', 'data-machine'); ?></option>
                <option value="pending" <?php selected($selected_post_status, 'pending'); ?>><?php _e('Pending Review', 'data-machine'); ?></option>
                <option value="private" <?php selected($selected_post_status, 'private'); ?>><?php _e('Private', 'data-machine'); ?></option>
            </select>
            <p class="description"><?php _e('Select the desired status for the post created on the target site.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="output_publish_remote_post_date_source"><?php _e('Post Date Setting', 'data-machine'); ?></label></th>
        <td>
            <select id="output_publish_remote_post_date_source" name="output_config[publish_remote][post_date_source]" <?php disabled($dependents_disabled); ?>>
                 <option value="current_date" <?php selected($selected_date_source, 'current_date'); ?>><?php _e('Use Current Date', 'data-machine'); ?></option>
                 <option value="source_date" <?php selected($selected_date_source, 'source_date'); ?>><?php _e('Use Source Date (if available)', 'data-machine'); ?></option>
            </select>
             <p class="description"><?php _e('Choose whether to use the original date from the source (if available) or the current date when publishing remotely. UTC timestamps will be used.', 'data-machine'); ?></p>
        </td>
    </tr>
    <?php if (in_array('category', $enabled_taxonomies)): // Only show row if category is enabled ?>
    <tr id="dm-remote-category-wrapper" class="dm-remote-field-row dm-taxonomy-row" data-taxonomy="category">
        <th scope="row"><label for="output_publish_remote_selected_remote_category_id"><?php _e('Remote Category', 'data-machine'); ?></label></th>
        <td>
            <select id="output_publish_remote_selected_remote_category_id" name="output_config[publish_remote][selected_remote_category_id]" <?php disabled($dependents_disabled || empty($category_options)); ?>>
                 <?php echo dm_generate_options($category_default_opts, $selected_category_id); ?>
                 <?php echo dm_generate_options($category_options, $selected_category_id); ?>
           </select>
            <p class="description"><?php _e('Select a category, let the AI choose, or instruct the AI using your prompt.', 'data-machine'); ?></p>
        </td>
    </tr>
    <?php endif; ?>
    <?php if (in_array('post_tag', $enabled_taxonomies)): // Only show row if post_tag is enabled ?>
    <tr id="dm-remote-tag-wrapper" class="dm-remote-field-row dm-taxonomy-row" data-taxonomy="post_tag">
        <th scope="row"><label for="output_publish_remote_selected_remote_tag_id"><?php _e('Remote Tag', 'data-machine'); ?></label></th>
        <td>
            <select id="output_publish_remote_selected_remote_tag_id" name="output_config[publish_remote][selected_remote_tag_id]" <?php disabled($dependents_disabled || empty($tag_options)); ?>>
                 <?php echo dm_generate_options($tag_default_opts, $selected_tag_id); ?>
                 <?php echo dm_generate_options($tag_options, $selected_tag_id); ?>
           </select>
            <p class="description"><?php _e('Select a single tag, let the AI choose, or instruct the AI using your prompt.', 'data-machine'); ?></p>
        </td>
    </tr>
    <?php endif; ?>
    <?php 
    // Dynamic Custom Taxonomy Rows
    if (!empty($custom_taxonomies)):
        foreach ($custom_taxonomies as $slug => $tax_data):
            // Check if slug exists in enabled_taxonomies again (belt-and-suspenders, already filtered in AJAX)
            if (!in_array($slug, $enabled_taxonomies)) continue; 
            
            $tax_label = $tax_data['label'] ?? ucfirst(str_replace('_', ' ', $slug));
            $tax_terms = $tax_data['terms'] ?? []; // Terms are already sorted from AJAX handler
            $current_custom_tax_value = $selected_custom_tax[$slug] ?? null;
            $tax_default_opts = [
                ['value' => '', 'text' => '-- Select ' . $tax_label . ' --'],
                ['value' => 'model_decides', 'text' => '-- Let Model Decide --'],
                ['value' => 'instruct_model', 'text' => '-- Instruct Model --']
            ];
    ?>
    <tr class="dm-remote-field-row dm-taxonomy-row" data-taxonomy="<?php echo esc_attr($slug); ?>" >
        <th scope="row"><label for="output_publish_remote_rest_<?php echo esc_attr($slug); ?>"><?php echo esc_html($tax_label); ?></label></th>
        <td>
            <select 
                id="output_publish_remote_rest_<?php echo esc_attr($slug); ?>" 
                name="output_config[publish_remote][selected_custom_taxonomy_values][<?php echo esc_attr($slug); ?>]" 
                <?php disabled($dependents_disabled || empty($tax_terms)); ?>
            >
                <?php echo dm_generate_options($tax_default_opts, $current_custom_tax_value); ?>
                <?php echo dm_generate_options($tax_terms, $current_custom_tax_value); ?>
            </select>
             <p class="description"><?php printf(__('Select a %s, let the AI choose, or instruct the AI using your prompt.', 'data-machine'), esc_html(strtolower($tax_label))); ?></p>
        </td>
    </tr>
    <?php 
        endforeach; 
    endif; 
    ?>
</table> 