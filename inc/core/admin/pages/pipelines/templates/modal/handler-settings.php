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

if (!defined('WPINC')) {
    die;
}

$handler_slug = $context['handler_slug'] ?? ($handler_slug ?? null);
$step_type = $context['step_type'] ?? ($step_type ?? null);
$flow_step_id = $context['flow_step_id'] ?? ($flow_step_id ?? null);
$pipeline_id = $context['pipeline_id'] ?? ($pipeline_id ?? null);


$handler_info = [];
$handler_settings = null;
$settings_fields = [];

if ($handler_slug) {
    $all_handlers = apply_filters('dm_handlers', []);
    $handler_info = $all_handlers[$handler_slug] ?? [];
    
    $settings_key = $handler_slug;
    if ($handler_slug === 'wordpress' && $step_type) {
        $settings_key = ($step_type === 'fetch') ? 'wordpress_fetch' : 'wordpress_publish';
    }
    
    $all_settings = apply_filters('dm_handler_settings', []);
    $handler_settings = $all_settings[$settings_key] ?? null;
    
    if ($handler_settings && method_exists($handler_settings, 'get_fields')) {
        // Get current settings from flow step config for field generation
        $current_settings_for_fields = [];
        if (!empty($flow_step_id) && !empty($handler_slug)) {
            $step_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);
            if (!empty($step_config)) {
                $current_settings_for_fields = $step_config['handler']['settings'][$handler_slug] ?? [];
            }
        }
        $settings_fields = $handler_settings::get_fields($current_settings_for_fields);
    }
}

$handler_label = $handler_info['label'] ?? ucfirst(str_replace('_', ' ', $handler_slug));

$all_auth = apply_filters('dm_auth_providers', []);
$has_auth_system = isset($all_auth[$handler_slug]) || isset($all_auth[$settings_key]);

if ($handler_slug === 'wordpress' || $settings_key === 'wordpress_publish') {
    $has_auth_system = false;
}

?>
<div class="dm-handler-settings-container">
    <div class="dm-handler-settings-header">
        <h3><?php echo esc_html(sprintf(__('Configure %s Handler', 'data-machine'), $handler_label)); ?></h3>
        <p><?php echo esc_html(sprintf(__('Set up your %s integration settings below.', 'data-machine'), $handler_label)); ?></p>
    </div>
    
    <?php if ($has_auth_system): ?>
        <div class="dm-auth-link-section">
            <div class="dm-auth-link-info">
                <span class="dashicons dashicons-admin-network"></span>
                <span><?php echo esc_html(sprintf(__('%s requires authentication to function properly.', 'data-machine'), $handler_label)); ?></span>
            </div>
            <button type="button" class="button button-secondary dm-modal-content" 
                    data-template="modal/handler-auth-form"
                    data-context='{"handler_slug":"<?php echo esc_attr($handler_slug); ?>","step_type":"<?php echo esc_attr($step_type ?? ''); ?>"}'>
                <?php esc_html_e('Manage Authentication', 'data-machine'); ?>
            </button>
        </div>
    <?php endif; ?>
    
    <div class="dm-handler-settings-form" data-handler-slug="<?php echo esc_attr($handler_slug); ?>" data-step-type="<?php echo esc_attr($step_type); ?>">
        
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('dm_ajax_actions'); ?>" />
        <input type="hidden" name="handler_slug" value="<?php echo esc_attr($handler_slug); ?>" />
        <input type="hidden" name="step_type" value="<?php echo esc_attr($step_type); ?>" />
        <input type="hidden" name="flow_step_id" value="<?php echo esc_attr($flow_step_id); ?>" />
        <input type="hidden" name="pipeline_id" value="<?php echo esc_attr($pipeline_id); ?>" />
        
        <div class="dm-settings-fields">
            <?php
            $current_settings = [];
            
            if (!empty($flow_step_id) && !empty($handler_slug)) {
                $step_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);
                if (!empty($step_config)) {
                    $current_settings = $step_config['handler']['settings'][$handler_slug] ?? [];
                }
            }
            
            
            if (!empty($settings_fields)) {
                foreach ($settings_fields as $field_name => $field_config) {
                    $current_value = $current_settings[$field_name] ?? null;
                    
                    echo \DataMachine\Core\Admin\Modal\ModalAjax::render_settings_field(
                        $field_name, 
                        $field_config, 
                        $current_value
                    );
                }
            } else {
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