<?php
/**
 * Universal Handler Settings Template
 *
 * Universal template for all handler configuration modal content.
 * Uses Settings classes to render appropriate fields for any handler type.
 * Eliminates code duplication across individual handler templates.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Extract context data consistently with template system
$handler_slug = $context['handler_slug'] ?? ($handler_slug ?? null);
$step_type = $context['step_type'] ?? ($step_type ?? null);
$flow_step_id = $context['flow_step_id'] ?? ($flow_step_id ?? null);
$pipeline_id = $context['pipeline_id'] ?? ($pipeline_id ?? null);

// Get individual IDs directly from context
$flow_id = $context['flow_id'] ?? null;
$pipeline_step_id = $context['pipeline_step_id'] ?? null;

// Template self-discovery - get handler configuration and settings
$handler_info = [];
$handler_settings = null;
$settings_fields = [];

if ($handler_slug) {
    // Get handler configuration via pure discovery
    $all_handlers = apply_filters('dm_handlers', []);
    $handler_info = $all_handlers[$handler_slug] ?? [];
    
    // Get handler settings instance via pure discovery 
    // Handle special cases like WordPress fetch/publish distinction
    $settings_key = $handler_slug;
    if ($handler_slug === 'wordpress' && $step_type) {
        $settings_key = ($step_type === 'fetch') ? 'wordpress_fetch' : 'wordpress_publish';
    }
    
    $all_settings = apply_filters('dm_handler_settings', []);
    $handler_settings = $all_settings[$settings_key] ?? null;
    
    // Get settings fields with current configuration
    if ($handler_settings && method_exists($handler_settings, 'get_fields')) {
        $current_settings = $context['current_settings'] ?? [];
        $settings_fields = $handler_settings::get_fields($current_settings);
    }
}

$handler_label = $handler_info['label'] ?? ucfirst(str_replace('_', ' ', $handler_slug));

// Authentication discovery via pure discovery mode
$all_auth = apply_filters('dm_auth_providers', []);
$has_auth_system = isset($all_auth[$handler_slug]) || isset($all_auth[$settings_key]);

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
            <?php 
            // Determine auth template based on handler type
            $auth_template = 'handler-auth';
            if ($handler_slug === 'wordpress' || $settings_key === 'wordpress_publish') {
                $auth_template = 'remote-locations-manager';
            }
            ?>
            <button type="button" class="button button-secondary dm-modal-content" 
                    data-template="<?php echo esc_attr($auth_template); ?>"
                    data-context='{"handler_slug":"<?php echo esc_attr($handler_slug); ?>","step_type":"<?php echo esc_attr($step_type ?? ''); ?>"}'>
                <?php 
                if ($auth_template === 'remote-locations-manager') {
                    esc_html_e('Manage Remote Locations', 'data-machine');
                } else {
                    esc_html_e('Manage Authentication', 'data-machine');
                }
                ?>
            </button>
        </div>
    <?php endif; ?>
    
    <div class="dm-handler-settings-form" data-handler-slug="<?php echo esc_attr($handler_slug); ?>" data-step-type="<?php echo esc_attr($step_type); ?>">
        
        <!-- Hidden fields for handler settings form -->
        <input type="hidden" name="handler_settings_nonce" value="<?php echo wp_create_nonce('dm_save_handler_settings'); ?>" />
        <input type="hidden" name="handler_slug" value="<?php echo esc_attr($handler_slug); ?>" />
        <input type="hidden" name="step_type" value="<?php echo esc_attr($step_type); ?>" />
        <input type="hidden" name="flow_step_id" value="<?php echo esc_attr($flow_step_id); ?>" />
        <input type="hidden" name="flow_id" value="<?php echo esc_attr($flow_id); ?>" />
        <input type="hidden" name="pipeline_step_id" value="<?php echo esc_attr($pipeline_step_id); ?>" />
        <input type="hidden" name="pipeline_id" value="<?php echo esc_attr($pipeline_id); ?>" />
        
        <div class="dm-settings-fields">
            <?php
            // Direct flow_config access for settings persistence
            $current_settings = [];
            
            // If we have a flow_step_id and flow_id, get the settings from flow_config
            if (!empty($flow_step_id) && !empty($flow_id)) {
                    // Get flow configuration directly
                    $all_databases = apply_filters('dm_db', []);
                    $db_flows = $all_databases['flows'] ?? null;
                    
                    if ($db_flows) {
                        $flow = $db_flows->get_flow($flow_id);
                        if ($flow && !empty($flow['flow_config'])) {
                            $flow_config = $flow['flow_config'] ?: [];
                            
                            // Direct access using flow_step_id as key
                            if (isset($flow_config[$flow_step_id])) {
                                $current_settings = $flow_config[$flow_step_id]['handler']['settings'] ?? [];
                            }
                        }
                    }
                }
            
            
            // Render settings fields using the Settings class and field renderer
            if (!empty($settings_fields)) {
                foreach ($settings_fields as $field_name => $field_config) {
                    $current_value = $current_settings[$field_name] ?? null;
                    
                    // Use the ModalAjax field renderer
                    echo \DataMachine\Core\Admin\Modal\ModalAjax::render_settings_field(
                        $field_name, 
                        $field_config, 
                        $current_value
                    );
                }
            } else {
                // Fallback message if Settings class not found
                echo '<p class="notice notice-warning inline">';
                esc_html_e('Settings fields could not be loaded. Please check handler configuration.', 'data-machine');
                echo '</p>';
            }
            ?>
        </div>
        
        <div class="dm-settings-actions">
            <button type="button" class="button button-secondary dm-cancel-settings">
                <?php esc_html_e('Cancel', 'data-machine'); ?>
            </button>
            <button type="button" class="button button-secondary dm-modal-content" 
                    data-template="handler-selection"
                    data-context='{"flow_step_id":"<?php echo esc_attr($flow_step_id); ?>","step_type":"<?php echo esc_attr($step_type); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>"}'>
                <?php esc_html_e('Change Handler Type', 'data-machine'); ?>
            </button>
            <button type="button" class="button button-primary dm-modal-close" 
                    data-template="add-handler-action"
                    data-context='{"handler_slug":"<?php echo esc_attr($handler_slug); ?>","step_type":"<?php echo esc_attr($step_type ?? ''); ?>","flow_step_id":"<?php echo esc_attr($flow_step_id ?? ''); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id ?? ''); ?>"}'>
                <?php esc_html_e('Save Handler Settings', 'data-machine'); ?>
            </button>
        </div>
    </div>
</div>