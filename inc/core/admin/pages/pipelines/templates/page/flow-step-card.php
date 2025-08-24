<?php
/**
 * Flow Step Card Template
 *
 * Simple template for displaying handler configurations within flows.
 * Uses same CSS structure as step-card for consistent styling.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

$step_type = $step['step_type'] ?? '';
$step_execution_order = $step['execution_order'] ?? 0;
$pipeline_step_id = $step['pipeline_step_id'] ?? null;
$is_empty = $step['is_empty'] ?? false;

$flow_step_id = null;

if (!$is_empty) {
    if (!$pipeline_step_id) {
        return;
    }

    foreach ($flow_config as $existing_flow_step_id => $step_data) {
        if (isset($step_data['pipeline_step_id']) && $step_data['pipeline_step_id'] === $pipeline_step_id) {
            $flow_step_id = $existing_flow_step_id;
            break;
        }
    }

    if (!$flow_step_id) {
        $is_empty = true;
    }
}

$current_handler = null;
if (!$is_empty) {
    $step_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);
    if (!empty($step_config['handler'])) {
        $current_handler = $step_config['handler'];
    }
}

$all_handlers = apply_filters('dm_handlers', []);
$available_handlers = array_filter($all_handlers, function($handler) use ($step_type) {
    return ($handler['type'] ?? '') === $step_type;
});

$step_title = '';
if (!$is_empty) {
    $all_steps = apply_filters('dm_steps', []);
    $step_config = $all_steps[$step_type] ?? null;
    $step_title = $step_config['label'] ?? ucfirst(str_replace('_', ' ', $step_type));
}

$has_handlers = !$is_empty && !empty($available_handlers);
$step_uses_handlers = !$is_empty && ($step_type !== 'ai');
$handler_configured = !$is_empty && !empty($current_handler);

$status_class = '';
if (!$is_empty) {
    $status = 'green';
    
    if ($step_type === 'ai' && $pipeline_step_id) {
        $status = apply_filters('dm_detect_status', 'green', 'ai_step', [
            'pipeline_step_id' => $pipeline_step_id
        ]);
    }
    elseif ($step_uses_handlers) {
        if (!$handler_configured) {
            $status = 'red';
        } else {
            $handler_slug = $current_handler['handler_slug'] ?? '';
            $all_auth = apply_filters('dm_auth_providers', []);
            $requires_auth = isset($all_auth[$handler_slug]);
            
            if ($requires_auth) {
                $auth_status = apply_filters('dm_detect_status', 'green', 'handler_auth', [
                    'handler_slug' => $handler_slug
                ]);
                if ($auth_status === 'red') {
                    $status = 'red';
                }
            }
            
            if ($status === 'green' && $flow_step_id) {
                $customizations = apply_filters('dm_get_handler_customizations', [], $flow_step_id);
                if (empty($customizations)) {
                    $status = 'yellow';
                }
            }
            
            if ($status === 'green' && $handler_slug === 'wordpress_publish') {
                $draft_status = apply_filters('dm_detect_status', 'green', 'wordpress_draft', [
                    'flow_step_id' => $flow_step_id
                ]);
                if ($draft_status === 'yellow') {
                    $status = 'yellow';
                }
            }
            
            if ($status === 'green' && $handler_slug === 'files') {
                $files_status = apply_filters('dm_detect_status', 'green', 'files_status', [
                    'flow_step_id' => $flow_step_id
                ]);
                if ($files_status === 'red') {
                    $status = 'red';
                }
            }
        }
    }
    
    if ($status !== 'red' && $pipeline_step_id) {
        $subsequent_status = apply_filters('dm_detect_status', 'green', 'subsequent_publish_step', [
            'pipeline_step_id' => $pipeline_step_id
        ]);
        if ($subsequent_status === 'yellow') {
            $status = 'yellow';
        }
    }
    
    $status_class = ' dm-step-card--status-' . $status;
}

?>
<div class="dm-step-container" 
     data-step-execution-order="<?php echo esc_attr($step_execution_order); ?>"
     data-step-type="<?php echo esc_attr($step_type); ?>"
     data-flow-id="<?php echo esc_attr($flow_id); ?>"
     data-pipeline-step-id="<?php echo esc_attr($pipeline_step_id); ?>"
     data-flow-step-id="<?php echo esc_attr($flow_step_id); ?>">

    <?php if (($step['execution_order'] ?? 0) > 0): ?>
        <div class="dm-data-flow-arrow">
            <span class="dashicons dashicons-arrow-right-alt"></span>
        </div>
    <?php endif; ?>

    <div class="dm-step-card<?php echo $is_empty ? ' dm-step-card--empty' : ''; ?><?php echo esc_attr($status_class); ?>">
        <?php if ($is_empty): ?>
            <div class="dm-step-empty-content">
                <button type="button" class="button button-secondary dm-modal-open dm-step-add-button"
                        data-template="step-selection"
                        data-context='{"context":"flow_builder","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>","flow_id":"<?php echo esc_attr($flow_id); ?>"}'>
                    <?php esc_html_e('Add Step', 'data-machine'); ?>
                </button>
            </div>
        <?php else: ?>
            <div class="dm-step-header">
                <div class="dm-step-title">
                    <?php echo esc_html($step_title); ?>
                </div>
                <div class="dm-step-actions">
                    <?php if ($step_uses_handlers && $has_handlers): ?>
                        <?php if (!$handler_configured): ?>
                            <button type="button" class="button button-small dm-modal-open" 
                                    data-template="handler-selection"
                                    data-context='{"flow_step_id":"<?php echo esc_attr($flow_step_id); ?>","step_type":"<?php echo esc_attr($step_type); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>"}'>
                                <?php esc_html_e('Add Handler', 'data-machine'); ?>
                            </button>
                        <?php else: ?>
                            <?php
                            $handler_slug = $current_handler['handler_slug'] ?? '';
                            
                            $template_slug = $handler_slug;
                            ?>
                            <button type="button" class="button button-small dm-modal-open" 
                                    data-template="handler-settings/<?php echo esc_attr($template_slug); ?>"
                                    data-context='{"flow_step_id":"<?php echo esc_attr($flow_step_id); ?>","step_type":"<?php echo esc_attr($step_type); ?>","handler_slug":"<?php echo esc_attr($handler_slug); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>","flow_id":"<?php echo esc_attr($flow_id); ?>"}'>
                                <?php esc_html_e('Edit Handler', 'data-machine'); ?>
                            </button>
                        <?php endif; ?>
                    <?php elseif ($step_type === 'ai'): ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="dm-step-body">
                <div class="dm-flow-step-info">
                    <?php if ($step_uses_handlers && $handler_configured): ?>
                        <div class="dm-handler-tag" data-handler-key="<?php echo esc_attr($current_handler['handler_slug'] ?? ''); ?>">
                            <span class="dm-handler-name"><?php echo esc_html($current_handler['handler_slug'] ?? 'Unknown'); ?></span>
                        </div>
                        
                        <?php 
                        $customizations = apply_filters('dm_get_handler_customizations', [], $flow_step_id);
                        ?>
                        <div class="dm-handler-customizations">
                            <?php if (!empty($customizations)): ?>
                                <?php foreach ($customizations as $customization): ?>
                                    <div>
                                        <?php echo esc_html(empty($customization['label']) ? $customization['display_value'] : $customization['label'] . ': ' . $customization['display_value']); ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="dm-not-configured">
                                    <?php esc_html_e('Not configured', 'data-machine'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($step_uses_handlers): ?>
                        <div class="dm-placeholder-text"><?php esc_html_e('No handlers configured', 'data-machine'); ?></div>
                    <?php elseif ($step_type === 'ai'): ?>
                        <?php
                        $ai_config = apply_filters('dm_ai_config', [], $pipeline_step_id);
                        $show_config = false;
                        if (!empty($ai_config) && isset($ai_config['selected_provider'])) {
                            $selected_provider = $ai_config['selected_provider'];
                            $model_name = $ai_config['model'] ?? '';
                            if ($selected_provider && $model_name) {
                                $show_config = true;
                            }
                        }
                        
                        if ($show_config): ?>
                            <div class="dm-ai-step-info dm-ai-configured">
                                <div class="dm-model-name">
                                    <strong><?php echo esc_html(ucfirst($selected_provider)); ?>: <?php echo esc_html($model_name); ?></strong>
                                </div>
                                <div class="dm-user-message-display">
                                    <?php
                                    // Get current flow step configuration to check for user message
                                    $step_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);
                                    $user_message = $step_config['user_message'] ?? '';
                                    
                                    // Calculate dynamic rows based on content length
                                    $estimated_rows = max(4, min(15, ceil(strlen($user_message) / 60) + 1));
                                    ?>
                                    <textarea class="dm-user-message-input dm-flow-user-message-input" 
                                              data-flow-step-id="<?php echo esc_attr($flow_step_id); ?>"
                                              placeholder="<?php esc_attr_e('Enter user message for this AI step (e.g., topic, question, or content to process)...', 'data-machine'); ?>"
                                              rows="<?php echo esc_attr($estimated_rows); ?>"><?php echo esc_textarea($user_message); ?></textarea>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="dm-placeholder-text"><?php esc_html_e('AI step not configured', 'data-machine'); ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>