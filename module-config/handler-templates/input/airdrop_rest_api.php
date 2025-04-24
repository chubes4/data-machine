<?php
/**
 * Airdrop REST API Input Handler Template
 */

// Access the data passed from the AJAX handler via GLOBALS
$all_locations = $GLOBALS['dm_template_all_locations'] ?? [];
// $site_info     = $GLOBALS['dm_template_site_info'] ?? null; // No longer needed directly
$saved_config  = $GLOBALS['dm_template_saved_config'] ?? [];
// Get the pre-filtered options arrays
$post_type_options = $GLOBALS['dm_template_filtered_post_type_options'] ?? [];
$category_options  = $GLOBALS['dm_template_filtered_category_options'] ?? [];
$tag_options       = $GLOBALS['dm_template_filtered_tag_options'] ?? [];
$custom_taxonomies = $GLOBALS['dm_template_filtered_custom_taxonomies'] ?? []; // Get custom taxonomies
$enabled_taxonomies= $GLOBALS['dm_template_enabled_taxonomies'] ?? []; // Needed for conditional rows

// Helper function to generate select options (if not already defined)
if (!function_exists('dm_generate_options')) {
    function dm_generate_options($options, $selected_value = '', $use_key_as_value = false) {
       // ... function code ...
       $html = '';
       foreach ($options as $key => $option) {
           $value = null; $label = null; 
           if ($use_key_as_value) {
                $value = $key; $label = $option;
           } elseif (is_object($option)) { 
               $value = $option->term_id ?? null;
               $label = $option->name ?? null;
           } elseif (is_array($option)) { 
               $value = $option['term_id'] ?? ($option['value'] ?? ($option['location_id'] ?? null)); 
               $label = $option['name'] ?? ($option['text'] ?? ($option['label'] ?? ($option['location_name'] ?? null))); 
           } elseif (is_string($option)) { 
               $value = $option; $label = $option;
           }
           if ($value === null || $label === null) { continue; }
           $value = (string)$value; $label = (string)$label; $selected_value = (string)$selected_value;
           if ($label !== '') {
               $html .= '<option value="' . esc_attr($value) . '" ' . selected($selected_value, $value, false) . '>' . esc_html($label) . '</option>';
           }
       }
       return $html;
    }
}

// Determine selected values from saved config
$is_location_change = !empty($GLOBALS['dm_template_selected_location_id']);
$selected_location_id = $GLOBALS['dm_template_selected_location_id'] ?? null; // Use the ID passed from AJAX

// If location changed, reset dependents to defaults, otherwise load from saved config
$selected_post_type   = $is_location_change ? null : ($saved_config['rest_post_type'] ?? null);
$selected_category    = $is_location_change ? '0' : ($saved_config['rest_category'] ?? '0'); // Default for airdrop
$selected_tag         = $is_location_change ? '0' : ($saved_config['rest_tag'] ?? '0');      // Default for airdrop
$selected_status      = $is_location_change ? 'publish' : ($saved_config['rest_post_status'] ?? 'publish');
$selected_orderby     = $is_location_change ? 'date' : ($saved_config['rest_orderby'] ?? 'date');
$selected_order       = $is_location_change ? 'DESC' : ($saved_config['rest_order'] ?? 'DESC');
$item_count           = $is_location_change ? 1 : ($saved_config['item_count'] ?? 1);
$timeframe_limit      = $is_location_change ? 'all_time' : ($saved_config['timeframe_limit'] ?? 'all_time');
$search_term          = $is_location_change ? '' : ($saved_config['search'] ?? '');

// Retrieve saved custom taxonomy selections
$selected_custom_tax  = $is_location_change ? [] : ($saved_config['custom_taxonomies'] ?? []); 

// Determine if dependent fields should be disabled
// Dependents are disabled if *no* location is selected OR if site_info hasn't loaded for the selected one.
$dependents_disabled = empty($selected_location_id);

// NOTE: No need to prepare options here, they come pre-filtered from AJAX handler

?>
<table class="form-table">
    <tr>
        <th scope="row"><label for="data_source_airdrop_rest_api_location_id"><?php _e('Remote Location', 'data-machine'); ?></label></th>
        <td>
            <select id="data_source_airdrop_rest_api_location_id" name="data_source_config[airdrop_rest_api][location_id]">
                <option value=""><?php _e('-- Select Location --', 'data-machine'); ?></option>
                <?php echo dm_generate_options($all_locations, $selected_location_id); ?>
            </select>
            <p class="description"><?php _e('Select the pre-configured remote WordPress site (using the Data Machine Airdrop helper plugin) to fetch data from.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr id="dm-airdrop-post-type-wrapper" class="dm-remote-field-row">
        <th scope="row"><label for="data_source_airdrop_rest_api_rest_post_type"><?php _e('Post Type', 'data-machine'); ?></label></th>
        <td>
            <select id="data_source_airdrop_rest_api_rest_post_type" name="data_source_config[airdrop_rest_api][rest_post_type]" <?php disabled($dependents_disabled || empty($post_type_options)); ?>>
                 <option value=""><?php _e('-- Select Post Type --', 'data-machine'); ?></option>
                 <?php echo dm_generate_options($post_type_options, $selected_post_type); // Use pre-filtered array ?>
            </select>
            <p class="description"><?php _e('Select the post type to fetch from the remote site (Only enabled types shown).', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="data_source_airdrop_rest_api_rest_post_status"><?php _e('Post Status', 'data-machine'); ?></label></th>
        <td>
            <select id="data_source_airdrop_rest_api_rest_post_status" name="data_source_config[airdrop_rest_api][rest_post_status]" <?php disabled($dependents_disabled); ?>>
                <option value="publish" <?php selected($selected_status, 'publish'); ?>><?php _e('Published', 'data-machine'); ?></option>
                <option value="draft" <?php selected($selected_status, 'draft'); ?>><?php _e('Draft', 'data-machine'); ?></option>
                <option value="pending" <?php selected($selected_status, 'pending'); ?>><?php _e('Pending', 'data-machine'); ?></option>
                <option value="private" <?php selected($selected_status, 'private'); ?>><?php _e('Private', 'data-machine'); ?></option>
                <option value="any" <?php selected($selected_status, 'any'); ?>><?php _e('Any', 'data-machine'); ?></option>
            </select>
            <p class="description"><?php _e('Select the post status to fetch.', 'data-machine'); ?></p>
        </td>
    </tr>
    <!-- Category row -->
    <?php if (in_array('category', $enabled_taxonomies)): ?>
    <tr id="dm-airdrop-category-wrapper" class="dm-remote-field-row">
        <th scope="row"><label for="data_source_airdrop_rest_api_rest_category"><?php _e('Category', 'data-machine'); ?></label></th>
        <td>
            <select id="data_source_airdrop_rest_api_rest_category" name="data_source_config[airdrop_rest_api][rest_category]" <?php disabled($dependents_disabled || empty($category_options)); ?>>
                 <option value="0"><?php _e('-- All Categories --', 'data-machine'); ?></option>
                 <?php echo dm_generate_options($category_options, $selected_category); ?>
            </select>
            <p class="description"><?php _e('Optional: Filter by a specific enabled category from the remote site.', 'data-machine'); ?></p>
        </td>
    </tr>
    <?php endif; ?>
    <!-- Tag row -->
    <?php if (in_array('post_tag', $enabled_taxonomies)): ?>
     <tr id="dm-airdrop-tag-wrapper" class="dm-remote-field-row">
        <th scope="row"><label for="data_source_airdrop_rest_api_rest_tag"><?php _e('Tag', 'data-machine'); ?></label></th>
        <td>
            <select id="data_source_airdrop_rest_api_rest_tag" name="data_source_config[airdrop_rest_api][rest_tag]" <?php disabled($dependents_disabled || empty($tag_options)); ?>>
                 <option value="0"><?php _e('-- All Tags --', 'data-machine'); ?></option>
                 <?php echo dm_generate_options($tag_options, $selected_tag); ?>
            </select>
            <p class="description"><?php _e('Optional: Filter by a specific enabled tag from the remote site.', 'data-machine'); ?></p>
        </td>
    </tr>
    <?php endif; ?>
    <!-- *** NEW: Dynamic Custom Taxonomy Rows *** -->
    <?php 
    if (!empty($custom_taxonomies)):
        // $custom_taxonomies is the pre-filtered array [slug => [label=>..., terms=>...]]
        foreach ($custom_taxonomies as $slug => $tax_data):
            // Check if slug exists in enabled_taxonomies again (belt-and-suspenders)
            if (!in_array($slug, $enabled_taxonomies)) continue; 
            
            $tax_label = $tax_data['label'] ?? ucfirst(str_replace('_', ' ', $slug));
            $tax_terms = $tax_data['terms'] ?? [];
            $current_custom_tax_value = $selected_custom_tax[$slug] ?? '0'; // Default to '0' for Airdrop?
            // Default options for custom tax (Airdrop uses '0' for All)
            $tax_default_opts = [
                ['value' => '0', 'text' => '-- All ' . esc_html($tax_label) . ' --']
            ];
    ?>
    <tr class="dm-remote-field-row dm-taxonomy-row" data-taxonomy="<?php echo esc_attr($slug); ?>" >
        <th scope="row"><label for="data_source_airdrop_rest_api_custom_tax_<?php echo esc_attr($slug); ?>"><?php echo esc_html($tax_label); ?></label></th>
        <td>
            <select 
                id="data_source_airdrop_rest_api_custom_tax_<?php echo esc_attr($slug); ?>" 
                name="data_source_config[airdrop_rest_api][custom_taxonomies][<?php echo esc_attr($slug); ?>]" 
                <?php disabled($dependents_disabled || empty($tax_terms)); ?>
            >
                <?php echo dm_generate_options($tax_default_opts, $current_custom_tax_value); ?>
                <?php echo dm_generate_options($tax_terms, $current_custom_tax_value); // Use pre-filtered terms ?>
            </select>
             <p class="description"><?php printf(__('Optional: Filter by a specific enabled %s from the remote site.', 'data-machine'), esc_html(strtolower($tax_label))); ?></p>
        </td>
    </tr>
    <?php 
        endforeach; 
    endif; 
    ?>
    <!-- *** END NEW: Dynamic Custom Taxonomy Rows *** -->
    <tr>
        <th scope="row"><label for="data_source_airdrop_rest_api_rest_orderby"><?php _e('Order By', 'data-machine'); ?></label></th>
        <td>
            <select id="data_source_airdrop_rest_api_rest_orderby" name="data_source_config[airdrop_rest_api][rest_orderby]" <?php disabled($dependents_disabled); ?>>
                <option value="date" <?php selected($selected_orderby, 'date'); ?>><?php _e('Date', 'data-machine'); ?></option>
                <option value="modified" <?php selected($selected_orderby, 'modified'); ?>><?php _e('Modified Date', 'data-machine'); ?></option>
                <option value="title" <?php selected($selected_orderby, 'title'); ?>><?php _e('Title', 'data-machine'); ?></option>
                <option value="ID" <?php selected($selected_orderby, 'ID'); ?>><?php _e('ID', 'data-machine'); ?></option>
            </select>
            <p class="description"><?php _e('Select the field to order results by.', 'data-machine'); ?></p>
        </td>
    </tr>
     <tr>
        <th scope="row"><label for="data_source_airdrop_rest_api_rest_order"><?php _e('Order', 'data-machine'); ?></label></th>
        <td>
            <select id="data_source_airdrop_rest_api_rest_order" name="data_source_config[airdrop_rest_api][rest_order]" <?php disabled($dependents_disabled); ?>>
                <option value="DESC" <?php selected($selected_order, 'DESC'); ?>><?php _e('Descending', 'data-machine'); ?></option>
                <option value="ASC" <?php selected($selected_order, 'ASC'); ?>><?php _e('Ascending', 'data-machine'); ?></option>
            </select>
            <p class="description"><?php _e('Select the order direction.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="data_source_airdrop_rest_api_item_count"><?php _e('Items to Process', 'data-machine'); ?></label></th>
        <td>
            <input type="number" id="data_source_airdrop_rest_api_item_count" name="data_source_config[airdrop_rest_api][item_count]" value="<?php echo esc_attr($item_count); ?>" class="small-text" min="1" max="100" <?php disabled($dependents_disabled); ?>>
            <p class="description"><?php _e('Maximum number of *new* items to process per run.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="data_source_airdrop_rest_api_timeframe_limit"><?php _e('Process Items Within', 'data-machine'); ?></label></th>
        <td>
             <select id="data_source_airdrop_rest_api_timeframe_limit" name="data_source_config[airdrop_rest_api][timeframe_limit]" <?php disabled($dependents_disabled); ?>>
                <option value="all_time" <?php selected($timeframe_limit, 'all_time'); ?>><?php _e('All Time', 'data-machine'); ?></option>
                <option value="24_hours" <?php selected($timeframe_limit, '24_hours'); ?>><?php _e('Last 24 Hours', 'data-machine'); ?></option>
                <option value="72_hours" <?php selected($timeframe_limit, '72_hours'); ?>><?php _e('Last 72 Hours', 'data-machine'); ?></option>
                <option value="7_days" <?php selected($timeframe_limit, '7_days'); ?>><?php _e('Last 7 Days', 'data-machine'); ?></option>
                <option value="30_days" <?php selected($timeframe_limit, '30_days'); ?>><?php _e('Last 30 Days', 'data-machine'); ?></option>
            </select>
            <p class="description"><?php _e('Only consider items published within this timeframe.', 'data-machine'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="data_source_airdrop_rest_api_search"><?php _e('Search Term Filter', 'data-machine'); ?></label></th>
        <td>
            <input type="text" id="data_source_airdrop_rest_api_search" name="data_source_config[airdrop_rest_api][search]" value="<?php echo esc_attr($search_term); ?>" class="regular-text" <?php disabled($dependents_disabled); ?>>
            <p class="description"><?php _e('Optional: Filter items on the remote site using a search term.', 'data-machine'); ?></p>
        </td>
    </tr>
</table> 