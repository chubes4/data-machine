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

$handler_label = $handler_config['label'] ?? ucfirst($handler_slug);

// Authentication discovery via filter
$auth_instance = apply_filters('dm_get_auth', null, $handler_slug);
$has_auth = ($auth_instance !== null);

?>
<div class="dm-handler-settings-container">
    <div class="dm-handler-settings-header">
        <h3><?php echo esc_html(sprintf(__('Configure %s Handler', 'data-machine'), $handler_label)); ?></h3>
        <p><?php echo esc_html(sprintf(__('Set up your %s integration settings below.', 'data-machine'), $handler_label)); ?></p>
    </div>
    
    <!-- Authentication Link Section -->
    <?php if ($has_auth): ?>
        <div class="dm-auth-link-section">
            <div class="dm-auth-link-info">
                <span class="dashicons dashicons-admin-network"></span>
                <span><?php echo esc_html(sprintf(__('%s requires authentication to function properly.', 'data-machine'), $handler_label)); ?></span>
            </div>
            <button type="button" class="button button-secondary dm-modal-content" 
                    data-template="handler-auth"
                    data-context='{"handler_slug":"<?php echo esc_attr($handler_slug); ?>","step_type":"<?php echo esc_attr($step_type ?? ''); ?>"}'>
                <?php esc_html_e('Manage Authentication', 'data-machine'); ?>
            </button>
        </div>
    <?php endif; ?>
    
    <form class="dm-handler-settings-form" data-handler-slug="<?php echo esc_attr($handler_slug); ?>" data-step-type="<?php echo esc_attr($step_type); ?>">
        <?php if ($settings_available && $handler_settings): ?>
            <div class="dm-settings-fields">
                <?php
                // Use get_fields() method to get field definitions and render dynamically
                if (method_exists($handler_settings, 'get_fields')) {
                    $fields = $handler_settings->get_fields();
                    
                    foreach ($fields as $field_key => $field_config) {
                        $field_type = $field_config['type'] ?? 'text';
                        $field_label = $field_config['label'] ?? ucfirst(str_replace('_', ' ', $field_key));
                        $field_description = $field_config['description'] ?? '';
                        $current_value = ''; // TODO: Load from saved config
                        
                        ?>
                        <div class="dm-form-field">
                            <label for="<?php echo esc_attr($field_key); ?>">
                                <?php echo esc_html($field_label); ?>
                            </label>
                            
                            <?php if ($field_type === 'number'): ?>
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
            <button type="submit" class="button button-primary dm-save-handler-settings">
                <?php esc_html_e('Add Handler to Flow', 'data-machine'); ?>
            </button>
        </div>
        
        <?php wp_nonce_field('dm_save_handler_settings', 'handler_settings_nonce'); ?>
    </form>
</div>

