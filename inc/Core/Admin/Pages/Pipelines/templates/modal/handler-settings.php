<?php
/**
 * Universal Handler Settings Template
 *
 * Unified configuration template supporting all handler types through Settings classes.
 * Features dynamic field rendering, authentication integration, and context-aware configuration.
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
    
    $all_settings = apply_filters('dm_handler_settings', []);
    $handler_settings = $all_settings[$settings_key] ?? null;
    
    if ($handler_settings && method_exists($handler_settings, 'get_fields')) {
        $current_settings_for_fields = [];
        if (!empty($flow_step_id) && !empty($handler_slug)) {
            $step_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);
            if (!empty($step_config)) {
                $current_settings_for_fields = $step_config['handler']['settings'][$handler_slug] ?? [];
            }
        }
        $all_fields = $handler_settings::get_fields($current_settings_for_fields);
        $settings_fields = apply_filters('dm_enabled_settings', $all_fields, $handler_slug, $step_type, [
            'flow_step_id' => $flow_step_id,
            'pipeline_id' => $pipeline_id,
            'current_settings' => $current_settings_for_fields
        ]);
    }
}

$handler_label = $handler_info['label'] ?? ucfirst(str_replace('_', ' ', $handler_slug));

$all_auth = apply_filters('dm_auth_providers', []);
$has_auth_system = isset($all_auth[$handler_slug]) || isset($all_auth[$settings_key]);

if ($settings_key === 'wordpress_publish' || $settings_key === 'wordpress_posts') {
    $has_auth_system = false;
}

?>
<div class="dm-handler-settings-container">
    <div class="dm-handler-settings-header">
        <?php /* translators: %s: Handler name/label */ ?>
        <h3><?php echo esc_html(sprintf(__('Configure %s Handler', 'data-machine'), $handler_label)); ?></h3>
        <?php /* translators: %s: Handler name/label */ ?>
        <p><?php echo esc_html(sprintf(__('Set up your %s integration settings below.', 'data-machine'), $handler_label)); ?></p>
    </div>
    
    <?php if ($has_auth_system): ?>
        <div class="dm-auth-link-section">
            <div class="dm-auth-link-info">
                <span class="dashicons dashicons-admin-network"></span>
                <?php /* translators: %s: Handler name/label */ ?>
                <span><?php echo esc_html(sprintf(__('%s requires authentication to function properly.', 'data-machine'), $handler_label)); ?></span>
            </div>
            <button type="button" class="button button-secondary dm-modal-content" 
                    data-template="modal/handler-auth-form"
                    data-context='<?php echo esc_attr(wp_json_encode(['handler_slug' => $handler_slug, 'step_type' => $step_type ?? '', 'flow_step_id' => $flow_step_id ?? '', 'pipeline_id' => $pipeline_id ?? '', 'flow_id' => $flow_id ?? ''])); ?>'>
                <?php esc_html_e('Manage Authentication', 'data-machine'); ?>
            </button>
        </div>
    <?php endif; ?>
    
    <div class="dm-handler-settings-form" data-handler-slug="<?php echo esc_attr($handler_slug); ?>" data-step-type="<?php echo esc_attr($step_type); ?>">
        
        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('dm_ajax_actions')); ?>" />
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
            
            $current_settings = apply_filters('dm_apply_global_defaults', $current_settings, $handler_slug, $step_type);
            
            
            if (!empty($settings_fields)) {
                foreach ($settings_fields as $field_name => $field_config) {
                    $current_value = $current_settings[$field_name] ?? null;
                    
                    echo wp_kses(
                        \DataMachine\Core\Admin\Modal\ModalAjax::render_settings_field(
                            $field_name,
                            $field_config,
                            $current_value
                        ),
                        dm_allowed_html()
                    );
                }
            } else {
                echo '<p class="notice notice-warning inline">';
                esc_html_e('Settings fields could not be loaded. Please check handler configuration.', 'data-machine');
                echo '</p>';
            }
            ?>
        </div>

        <?php
        // Global settings notification for WordPress handlers
        if (in_array($handler_slug, ['wordpress_publish', 'wordpress_posts'])) {
            $all_settings = get_option('data_machine_settings', []);
            $wp_settings = $all_settings['wordpress_settings'] ?? [];
            $global_settings = [];

            if (!empty($wp_settings['default_author_id'])) {
                $user = get_userdata($wp_settings['default_author_id']);
                $author_name = $user ? $user->display_name : 'Unknown';
                /* translators: %s: Author display name */
                $global_settings[] = sprintf(__('Author: %s', 'data-machine'), $author_name);
            }

            if (!empty($wp_settings['default_post_status'])) {
                $status_labels = [
                    'publish' => __('Published', 'data-machine'),
                    'draft' => __('Draft', 'data-machine'),
                    'private' => __('Private', 'data-machine')
                ];
                $status_label = $status_labels[$wp_settings['default_post_status']] ?? ucfirst($wp_settings['default_post_status']);
                /* translators: %s: Post status label */
                $global_settings[] = sprintf(__('Post Status: %s', 'data-machine'), $status_label);
            }

            if (isset($wp_settings['default_include_source'])) {
                $setting_label = $wp_settings['default_include_source'] ? __('Enabled', 'data-machine') : __('Disabled', 'data-machine');
                /* translators: %s: Setting status (Enabled/Disabled) */
                $global_settings[] = sprintf(__('Include Source: %s', 'data-machine'), $setting_label);
            }

            if (isset($wp_settings['default_enable_images'])) {
                $setting_label = $wp_settings['default_enable_images'] ? __('Enabled', 'data-machine') : __('Disabled', 'data-machine');
                /* translators: %s: Setting status (Enabled/Disabled) */
                $global_settings[] = sprintf(__('Featured Images: %s', 'data-machine'), $setting_label);
            }

            if (!empty($global_settings)) {
                ?>
                <div class="dm-global-settings-notice" style="background: #f0f6fc; border: 1px solid #c3d8e8; padding: 12px; margin: 16px 0; border-radius: 4px;">
                    <p style="margin: 0; color: #2c3e50;">
                        <strong><?php esc_html_e('Global Settings Active:', 'data-machine'); ?></strong>
                        <?php echo esc_html(implode(', ', $global_settings)); ?>.
                        <a href="<?php echo esc_url(admin_url('options-general.php?page=data-machine-settings&tab=wordpress')); ?>" target="_blank">
                            <?php esc_html_e('Change global settings', 'data-machine'); ?>
                        </a>
                    </p>
                </div>
                <?php
            }
        }
        ?>

        <div class="dm-settings-actions">
            <button type="button" class="button button-secondary dm-cancel-settings">
                <?php esc_html_e('Cancel', 'data-machine'); ?>
            </button>
            <button type="button" class="button button-secondary dm-modal-content" 
                    data-template="handler-selection"
                    data-context='<?php echo esc_attr(wp_json_encode(['flow_step_id' => $flow_step_id, 'step_type' => $step_type, 'pipeline_id' => $pipeline_id])); ?>'>
                <?php esc_html_e('Change Handler Type', 'data-machine'); ?>
            </button>
            <button type="button" class="button button-primary dm-modal-close" 
                    data-template="add-handler-action"
                    data-context='<?php echo esc_attr(wp_json_encode(['handler_slug' => $handler_slug, 'step_type' => $step_type ?? '', 'flow_step_id' => $flow_step_id ?? '', 'pipeline_id' => $pipeline_id ?? ''])); ?>'>
                <?php esc_html_e('Save Handler Settings', 'data-machine'); ?>
            </button>
        </div>
    </div>
</div>