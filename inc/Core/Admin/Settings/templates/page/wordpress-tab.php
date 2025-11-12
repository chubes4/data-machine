<?php
/**
 * WordPress Tab Template
 *
 * WordPress-specific settings for post types, taxonomies, and defaults.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$settings = datamachine_get_datamachine_settings();
$wp_settings = $settings['wordpress_settings'] ?? [];
$engine_mode = $settings['engine_mode'];

$enabled_post_types = $wp_settings['enabled_post_types'] ?? [];
$enabled_taxonomies = $wp_settings['enabled_taxonomies'] ?? [];
$default_author_id = $wp_settings['default_author_id'] ?? 0;
$default_post_status = $wp_settings['default_post_status'] ?? '';
$default_include_source = $wp_settings['default_include_source'] ?? false;
$default_enable_images = $wp_settings['default_enable_images'] ?? false;

$disabled_attr = $engine_mode ? 'disabled' : '';

$post_types = get_post_types(['public' => true], 'objects');
$taxonomies = get_taxonomies(['public' => true], 'objects');
$excluded = apply_filters('datamachine_wordpress_system_taxonomies', []);
$filtered_taxonomies = [];
foreach ($taxonomies as $taxonomy) {
    if (!in_array($taxonomy->name, $excluded)) {
        $filtered_taxonomies[] = $taxonomy;
    }
}

$users = get_users(['capability' => 'publish_posts', 'number' => 100]);
$post_statuses = get_post_stati(['public' => true, 'private' => true]);
?>

<div class="datamachine-inline-note" style="margin: 8px 0 16px; padding: 8px 10px; border: 1px solid #e2e8f0; background: #f8fafc;">
    <strong><?php esc_html_e('Note', 'datamachine'); ?>:</strong>
    <?php esc_html_e('These are global overrides. If set here, they take precedence over pipeline/handler settings; if not set, pipelines remain in control.', 'datamachine'); ?>
 </div>

<table class="form-table">
    <tr>
        <th scope="row"><?php esc_html_e('Enabled Post Types', 'datamachine'); ?></th>
        <td>
            <?php if ($post_types): ?>
                <fieldset <?php echo esc_attr($disabled_attr); ?>>
                    <?php foreach ($post_types as $post_type): ?>
                        <?php $is_enabled = !$enabled_post_types || ($enabled_post_types[$post_type->name] ?? false); ?>
                        <label class="datamachine-settings-page-item">
                            <input type="checkbox" 
                                   name="datamachine_settings[wordpress_settings][enabled_post_types][<?php echo esc_attr($post_type->name); ?>]" 
                                   value="1" 
                                   <?php checked($is_enabled, true); ?>
                                   <?php echo esc_attr($disabled_attr); ?>>
                            <?php echo esc_html($post_type->labels->name ?? $post_type->label); ?>
                            <span class="description">(<?php echo esc_html($post_type->name); ?>)</span>
                        </label>
                    <?php endforeach; ?>
                    <p class="description">
                        <?php esc_html_e('Unchecked post types will not appear in any WordPress handler configuration modals.', 'datamachine'); ?>
                    </p>
                    <?php if ($engine_mode): ?>
                        <p class="description">
                            <?php esc_html_e('Post type controls are disabled when Engine Mode is active.', 'datamachine'); ?>
                        </p>
                    <?php endif; ?>
                </fieldset>
            <?php else: ?>
                <p><?php esc_html_e('No public post types are currently available.', 'datamachine'); ?></p>
            <?php endif; ?>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><?php esc_html_e('Enabled Taxonomies', 'datamachine'); ?></th>
        <td>
            <?php if ($filtered_taxonomies): ?>
                <fieldset <?php echo esc_attr($disabled_attr); ?>>
                    <?php foreach ($filtered_taxonomies as $taxonomy): ?>
                        <?php 
                        $taxonomy_label = $taxonomy->labels->name ?? $taxonomy->label;
                        $is_enabled = !$enabled_taxonomies || ($enabled_taxonomies[$taxonomy->name] ?? false);
                        ?>
                        <label class="datamachine-settings-page-item">
                            <input type="checkbox" 
                                   name="datamachine_settings[wordpress_settings][enabled_taxonomies][<?php echo esc_attr($taxonomy->name); ?>]" 
                                   value="1" 
                                   <?php checked($is_enabled, true); ?>
                                   <?php echo esc_attr($disabled_attr); ?>>
                            <?php echo esc_html($taxonomy_label); ?>
                            <span class="description">(<?php echo esc_html($taxonomy->name); ?>)</span>
                        </label>
                    <?php endforeach; ?>
                    <p class="description">
                        <?php esc_html_e('Unchecked taxonomies will not appear in any WordPress handler configuration modals.', 'datamachine'); ?>
                    </p>
                    <?php if ($engine_mode): ?>
                        <p class="description">
                            <?php esc_html_e('Taxonomy controls are disabled when Engine Mode is active.', 'datamachine'); ?>
                        </p>
                    <?php endif; ?>
                </fieldset>
            <?php else: ?>
                <p><?php esc_html_e('No content taxonomies are currently available.', 'datamachine'); ?></p>
            <?php endif; ?>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><?php esc_html_e('Default Author', 'datamachine'); ?></th>
        <td>
            <select name="datamachine_settings[wordpress_settings][default_author_id]" <?php echo esc_attr($disabled_attr); ?>>
                <option value="0"><?php esc_html_e('-- No Default --', 'datamachine'); ?></option>
                <?php foreach ($users as $user): ?>
                    <?php $display_name = $user->display_name ?: $user->user_login; ?>
                    <option value="<?php echo esc_attr($user->ID); ?>" 
                            <?php selected($default_author_id, $user->ID); ?>>
                        <?php echo esc_html($display_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">
                <?php esc_html_e('When an author is selected, the author field will be hidden from WordPress publish handler modals and this author will be used as the default.', 'datamachine'); ?>
            </p>
            <?php if ($engine_mode): ?>
                <p class="description">
                    <?php esc_html_e('Author controls are disabled when Engine Mode is active.', 'datamachine'); ?>
                </p>
            <?php endif; ?>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><?php esc_html_e('Default Post Status', 'datamachine'); ?></th>
        <td>
            <select name="datamachine_settings[wordpress_settings][default_post_status]" <?php echo esc_attr($disabled_attr); ?>>
                <option value=""><?php esc_html_e('-- No Default --', 'datamachine'); ?></option>
                <?php foreach ($post_statuses as $status => $label): ?>
                    <option value="<?php echo esc_attr($status); ?>" 
                            <?php selected($default_post_status, $status); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">
                <?php esc_html_e('When a status is selected, the post status field will be hidden from WordPress publish handler modals and this status will be used as the default.', 'datamachine'); ?>
            </p>
            <?php if ($engine_mode): ?>
                <p class="description">
                    <?php esc_html_e('Post status controls are disabled when Engine Mode is active.', 'datamachine'); ?>
                </p>
            <?php endif; ?>
        </td>
    </tr>

    <tr>
        <th scope="row"><?php esc_html_e('Default Include Source URL', 'datamachine'); ?></th>
        <td>
            <input type="checkbox"
                   name="datamachine_settings[wordpress_settings][default_include_source]"
                   value="1"
                   <?php checked($default_include_source, true); ?>
                   <?php echo esc_attr($disabled_attr); ?>>
            <?php esc_html_e('Always include source URL in posts', 'datamachine'); ?>
            <p class="description">
                <?php esc_html_e('When enabled, the source URL field will be hidden from WordPress publish handler modals and source URLs will always be included.', 'datamachine'); ?>
            </p>
            <?php if ($engine_mode): ?>
                <p class="description">
                    <?php esc_html_e('Source URL controls are disabled when Engine Mode is active.', 'datamachine'); ?>
                </p>
            <?php endif; ?>
        </td>
    </tr>

    <tr>
        <th scope="row"><?php esc_html_e('Default Enable Featured Images', 'datamachine'); ?></th>
        <td>
            <input type="checkbox"
                   name="datamachine_settings[wordpress_settings][default_enable_images]"
                   value="1"
                   <?php checked($default_enable_images, true); ?>
                   <?php echo esc_attr($disabled_attr); ?>>
            <?php esc_html_e('Always enable featured images', 'datamachine'); ?>
            <p class="description">
                <?php esc_html_e('When enabled, the featured images field will be hidden from WordPress publish handler modals and featured images will always be enabled.', 'datamachine'); ?>
            </p>
            <?php if ($engine_mode): ?>
                <p class="description">
                    <?php esc_html_e('Featured image controls are disabled when Engine Mode is active.', 'datamachine'); ?>
                </p>
            <?php endif; ?>
        </td>
    </tr>
</table>