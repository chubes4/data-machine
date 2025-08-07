<?php
/**
 * Handler Settings Form Template
 *
 * Pure rendering template for handler configuration modal content.
 * Uses filter-based settings discovery for dynamic form generation.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Extract context data passed through modal system
$handler_slug = $context['handler_slug'] ?? ($handler_slug ?? null);
$step_type = $context['step_type'] ?? ($step_type ?? null);
$step_id = $context['step_id'] ?? ($step_id ?? null);
$flow_id = $context['flow_id'] ?? ($flow_id ?? null);
$pipeline_id = $context['pipeline_id'] ?? ($pipeline_id ?? null);

// Template self-discovery - get handler configuration and settings
$handler_config = [];
$settings_instance = null;

if ($handler_slug) {
    // Get handler configuration via pure discovery
    $all_handlers = apply_filters('dm_get_handlers', []);
    $handler_config = $all_handlers[$handler_slug] ?? [];
    
    // Get handler settings instance via pure discovery
    $all_settings = apply_filters('dm_get_handler_settings', []);
    $settings_instance = $all_settings[$handler_slug] ?? null;
}

$handler_label = $handler_config['label'] ?? ucfirst($handler_slug);

// Authentication discovery via pure discovery mode
$all_auth = apply_filters('dm_get_auth_providers', []);
$has_auth_system = isset($all_auth[$handler_slug]);

?>
<div class="dm-handler-settings-container">
    <div class="dm-handler-settings-header">
        <h3><?php echo esc_html(sprintf(__('Configure %s Handler', 'data-machine'), $handler_label)); ?></h3>
        <p><?php echo esc_html(sprintf(__('Set up your %s integration settings below.', 'data-machine'), $handler_label)); ?></p>
    </div>
    
    <!-- Authentication Link Section -->
    <?php if ($has_auth_system): ?>
        <div class="dm-auth-link-section">
            <div class="dm-auth-link-info">
                <span class="dashicons dashicons-admin-network"></span>
                <span><?php echo esc_html(sprintf(__('%s requires authentication to function properly.', 'data-machine'), $handler_label)); ?></span>
            </div>
            <button type="button" class="button button-secondary dm-modal-content" 
                    data-template="<?php echo ($handler_slug === 'wordpress') ? 'remote-locations-manager' : 'handler-auth'; ?>"
                    data-context='{"handler_slug":"<?php echo esc_attr($handler_slug); ?>","step_type":"<?php echo esc_attr($step_type ?? ''); ?>"}'>
                <?php 
                if ($handler_slug === 'wordpress') {
                    esc_html_e('Manage Remote Locations', 'data-machine');
                } else {
                    esc_html_e('Manage Authentication', 'data-machine');
                }
                ?>
            </button>
        </div>
    <?php endif; ?>
    
    <div class="dm-handler-settings-form" data-handler-slug="<?php echo esc_attr($handler_slug); ?>" data-step-type="<?php echo esc_attr($step_type); ?>">
        
        <!-- Hidden fields for handler settings form - populated from context -->
        <input type="hidden" name="handler_settings_nonce" value="<?php echo wp_create_nonce('dm_save_handler_settings'); ?>" />
        <input type="hidden" name="handler_slug" value="<?php echo esc_attr($handler_slug); ?>" />
        <input type="hidden" name="step_type" value="<?php echo esc_attr($step_type); ?>" />
        <input type="hidden" name="step_id" value="<?php echo esc_attr($step_id); ?>" />
        <input type="hidden" name="flow_id" value="<?php echo esc_attr($flow_id); ?>" />
        <input type="hidden" name="pipeline_id" value="<?php echo esc_attr($pipeline_id); ?>" />
        
        <?php if ($settings_instance): ?>
            <div class="dm-settings-fields">
                <?php
                // Use get_fields() method to get field definitions and render dynamically
                if (method_exists($settings_instance, 'get_fields')) {
                    $fields = $settings_instance->get_fields();
                    
                    foreach ($fields as $field_key => $field_config) {
                        $field_type = $field_config['type'] ?? 'text';
                        $field_label = $field_config['label'] ?? ucfirst(str_replace('_', ' ', $field_key));
                        $field_description = $field_config['description'] ?? '';
                        $current_value = '';
                        
                        ?>
                        <div class="dm-form-field">
                            <label for="<?php echo esc_attr($field_key); ?>">
                                <?php echo esc_html($field_label); ?>
                            </label>
                            
                            <?php if ($field_type === 'section'): ?>
                                <!-- Section header -->
                                <div class="dm-settings-section">
                                    <h4><?php echo esc_html($field_label); ?></h4>
                                    <?php if (!empty($field_description)): ?>
                                        <p class="description"><?php echo esc_html($field_description); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php
                                    // Section content discovery - allows handlers to provide custom section content
                                    $section_content = apply_filters('dm_render_handler_section_content', '', $handler_slug, $field_key, $field_config);
                                    if (!empty($section_content)) {
                                        echo $section_content;
                                    } elseif ($field_key === 'file_upload_section') {
                                        // Default file upload interface for any handler using file_upload_section
                                        ?>
                                        <div class="dm-file-upload-container">
                                            <input type="file" id="dm-file-upload" multiple />
                                            <button type="button" class="button" id="dm-upload-files">
                                                <?php esc_html_e('Upload Files', 'data-machine'); ?>
                                            </button>
                                            <div id="dm-uploaded-files-list" class="dm-file-list">
                                                <!-- Uploaded files will appear here -->
                                            </div>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </div>
                            <?php elseif ($field_type === 'number'): ?>
                                <input type="number" 
                                       id="<?php echo esc_attr($field_key); ?>" 
                                       name="<?php echo esc_attr($field_key); ?>" 
                                       value="<?php echo esc_attr($current_value); ?>"
                                       min="<?php echo esc_attr($field_config['min'] ?? ''); ?>"
                                       max="<?php echo esc_attr($field_config['max'] ?? ''); ?>"
                                       class="regular-text" />
                            <?php elseif ($field_type === 'checkbox'): ?>
                                <input type="checkbox" 
                                       id="<?php echo esc_attr($field_key); ?>" 
                                       name="<?php echo esc_attr($field_key); ?>" 
                                       value="1"
                                       <?php checked(!empty($current_value)); ?> />
                            <?php elseif ($field_type === 'textarea'): ?>
                                <textarea id="<?php echo esc_attr($field_key); ?>" 
                                          name="<?php echo esc_attr($field_key); ?>" 
                                          rows="4" 
                                          class="large-text"><?php echo esc_textarea($current_value); ?></textarea>
                            <?php elseif ($field_type === 'select'): ?>
                                <select id="<?php echo esc_attr($field_key); ?>" 
                                        name="<?php echo esc_attr($field_key); ?>" 
                                        class="regular-text">
                                    <?php foreach ($field_config['options'] ?? [] as $option_value => $option_label): ?>
                                        <option value="<?php echo esc_attr($option_value); ?>" 
                                                <?php selected($current_value, $option_value); ?>>
                                            <?php echo esc_html($option_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($field_type === 'url'): ?>
                                <input type="url" 
                                       id="<?php echo esc_attr($field_key); ?>" 
                                       name="<?php echo esc_attr($field_key); ?>" 
                                       value="<?php echo esc_attr($current_value); ?>"
                                       class="regular-text" />
                            <?php else: ?>
                                <input type="text" 
                                       id="<?php echo esc_attr($field_key); ?>" 
                                       name="<?php echo esc_attr($field_key); ?>" 
                                       value="<?php echo esc_attr($current_value); ?>"
                                       class="regular-text" />
                            <?php endif; ?>
                            
                            <?php if (!empty($field_description)): ?>
                                <p class="description"><?php echo esc_html($field_description); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
        <?php else: ?>
            <div class="dm-no-settings">
                <p><?php echo esc_html(sprintf(__('The %s handler doesn\'t require additional configuration.', 'data-machine'), $handler_label)); ?></p>
                <p><?php esc_html_e('You can add this handler directly to your flow.', 'data-machine'); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="dm-settings-actions">
            <button type="button" class="button button-secondary dm-cancel-settings">
                <?php esc_html_e('Cancel', 'data-machine'); ?>
            </button>
            <button type="button" class="button button-secondary dm-modal-content" 
                    data-template="handler-selection"
                    data-context='{"flow_id":"<?php echo esc_attr($flow_id); ?>","step_type":"<?php echo esc_attr($step_type); ?>"}'>
                <?php esc_html_e('Change Handler Type', 'data-machine'); ?>
            </button>
            <button type="button" class="button button-primary dm-modal-close" 
                    data-template="add-handler-action"
                    data-context='{"handler_slug":"<?php echo esc_attr($handler_slug); ?>","step_type":"<?php echo esc_attr($step_type ?? ''); ?>","step_id":"<?php echo esc_attr($step_id ?? ''); ?>","flow_id":"<?php echo esc_attr($flow_id ?? ''); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id ?? ''); ?>"}'>
                <?php esc_html_e('Save Handler Settings', 'data-machine'); ?>
            </button>
        </div>
    </div>
</div>


