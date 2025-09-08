<?php
/**
 * WordPress Tab Template
 * 
 * WordPress-specific settings for post types, taxonomies, and defaults.
 */

$settings = dm_get_data_machine_settings();
$wp_settings = $settings['wordpress_settings'] ?? [];
$engine_mode = $settings['engine_mode'];

$enabled_post_types = $wp_settings['enabled_post_types'] ?? [];
$enabled_taxonomies = $wp_settings['enabled_taxonomies'] ?? [];
$default_author_id = $wp_settings['default_author_id'] ?? 0;
$default_post_status = $wp_settings['default_post_status'] ?? '';

$disabled_attr = $engine_mode ? 'disabled' : '';

$post_types = get_post_types(['public' => true], 'objects');
$taxonomies = get_taxonomies(['public' => true], 'objects');
$filtered_taxonomies = [];
foreach ($taxonomies as $taxonomy) {
    if (!in_array($taxonomy->name, ['post_format', 'nav_menu', 'link_category'])) {
        $filtered_taxonomies[] = $taxonomy;
    }
}

$users = get_users(['capability' => 'publish_posts', 'number' => 100]);
$post_statuses = get_post_stati(['public' => true, 'private' => true]);
?>

<table class="form-table">
    <tr>
        <th scope="row"><?php esc_html_e('Enabled Post Types', 'data-machine'); ?></th>
        <td>
            <?php if ($post_types): ?>
                <fieldset <?php echo $disabled_attr; ?>>
                    <?php foreach ($post_types as $post_type): ?>
                        <?php $is_enabled = !$enabled_post_types || ($enabled_post_types[$post_type->name] ?? false); ?>
                        <label class="dm-settings-page-item">
                            <input type="checkbox" 
                                   name="data_machine_settings[wordpress_settings][enabled_post_types][<?php echo esc_attr($post_type->name); ?>]" 
                                   value="1" 
                                   <?php checked($is_enabled, true); ?>
                                   <?php echo $disabled_attr; ?>>
                            <?php echo esc_html($post_type->labels->name ?? $post_type->label); ?>
                            <span class="description">(<?php echo esc_html($post_type->name); ?>)</span>
                        </label>
                    <?php endforeach; ?>
                    <p class="description">
                        <?php esc_html_e('Unchecked post types will not appear in any WordPress handler configuration modals.', 'data-machine'); ?>
                    </p>
                    <?php if ($engine_mode): ?>
                        <p class="description">
                            <?php esc_html_e('Post type controls are disabled when Engine Mode is active.', 'data-machine'); ?>
                        </p>
                    <?php endif; ?>
                </fieldset>
            <?php else: ?>
                <p><?php esc_html_e('No public post types are currently available.', 'data-machine'); ?></p>
            <?php endif; ?>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><?php esc_html_e('Enabled Taxonomies', 'data-machine'); ?></th>
        <td>
            <?php if ($filtered_taxonomies): ?>
                <fieldset <?php echo $disabled_attr; ?>>
                    <?php foreach ($filtered_taxonomies as $taxonomy): ?>
                        <?php 
                        $taxonomy_label = $taxonomy->labels->name ?? $taxonomy->label;
                        $is_enabled = !$enabled_taxonomies || ($enabled_taxonomies[$taxonomy->name] ?? false);
                        ?>
                        <label class="dm-settings-page-item">
                            <input type="checkbox" 
                                   name="data_machine_settings[wordpress_settings][enabled_taxonomies][<?php echo esc_attr($taxonomy->name); ?>]" 
                                   value="1" 
                                   <?php checked($is_enabled, true); ?>
                                   <?php echo $disabled_attr; ?>>
                            <?php echo esc_html($taxonomy_label); ?>
                            <span class="description">(<?php echo esc_html($taxonomy->name); ?>)</span>
                        </label>
                    <?php endforeach; ?>
                    <p class="description">
                        <?php esc_html_e('Unchecked taxonomies will not appear in any WordPress handler configuration modals.', 'data-machine'); ?>
                    </p>
                    <?php if ($engine_mode): ?>
                        <p class="description">
                            <?php esc_html_e('Taxonomy controls are disabled when Engine Mode is active.', 'data-machine'); ?>
                        </p>
                    <?php endif; ?>
                </fieldset>
            <?php else: ?>
                <p><?php esc_html_e('No content taxonomies are currently available.', 'data-machine'); ?></p>
            <?php endif; ?>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><?php esc_html_e('Default Author', 'data-machine'); ?></th>
        <td>
            <select name="data_machine_settings[wordpress_settings][default_author_id]" <?php echo $disabled_attr; ?>>
                <option value="0"><?php esc_html_e('-- No Default --', 'data-machine'); ?></option>
                <?php foreach ($users as $user): ?>
                    <?php $display_name = $user->display_name ?: $user->user_login; ?>
                    <option value="<?php echo esc_attr($user->ID); ?>" 
                            <?php selected($default_author_id, $user->ID); ?>>
                        <?php echo esc_html($display_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">
                <?php esc_html_e('When an author is selected, the author field will be hidden from WordPress publish handler modals and this author will be used as the default.', 'data-machine'); ?>
            </p>
            <?php if ($engine_mode): ?>
                <p class="description">
                    <?php esc_html_e('Author controls are disabled when Engine Mode is active.', 'data-machine'); ?>
                </p>
            <?php endif; ?>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><?php esc_html_e('Default Post Status', 'data-machine'); ?></th>
        <td>
            <select name="data_machine_settings[wordpress_settings][default_post_status]" <?php echo $disabled_attr; ?>>
                <option value=""><?php esc_html_e('-- No Default --', 'data-machine'); ?></option>
                <?php foreach ($post_statuses as $status => $label): ?>
                    <option value="<?php echo esc_attr($status); ?>" 
                            <?php selected($default_post_status, $status); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">
                <?php esc_html_e('When a status is selected, the post status field will be hidden from WordPress publish handler modals and this status will be used as the default.', 'data-machine'); ?>
            </p>
            <?php if ($engine_mode): ?>
                <p class="description">
                    <?php esc_html_e('Post status controls are disabled when Engine Mode is active.', 'data-machine'); ?>
                </p>
            <?php endif; ?>
        </td>
    </tr>
</table>