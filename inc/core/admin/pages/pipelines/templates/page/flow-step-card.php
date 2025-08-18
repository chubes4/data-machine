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

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Extract required data from passed variables and step array
$step_type = $step['step_type'] ?? '';
$step_execution_order = $step['execution_order'] ?? 0;
$pipeline_step_id = $step['pipeline_step_id'] ?? null;
$is_empty = $step['is_empty'] ?? false;

// Handle flow step ID generation
$flow_step_id = null;

if (!$is_empty) {
    // Only process flow_step_id lookup for populated steps
    if (!$pipeline_step_id) {
        // Skip processing if no pipeline_step_id - template will show as empty
        return;
    }

    // Find flow_step_id from existing flow_config (stored data)
    foreach ($flow_config as $existing_flow_step_id => $step_data) {
        if (isset($step_data['pipeline_step_id']) && $step_data['pipeline_step_id'] === $pipeline_step_id) {
            $flow_step_id = $existing_flow_step_id;
            break;
        }
    }

    if (!$flow_step_id) {
        // No flow_step_id found - treat as unconfigured step
        $is_empty = true;
    }
}

// Get handler configuration from flow config using direct flow_step_id lookup
$current_handler = null;
if (!$is_empty) {
    $step_config = apply_filters('dm_get_flow_step_config', [], $flow_step_id);
    if (!empty($step_config['handler'])) {
        $current_handler = $step_config['handler'];
    }
}

// Get available handlers for this step type
$all_handlers = apply_filters('dm_handlers', []);
$available_handlers = array_filter($all_handlers, function($handler) use ($step_type) {
    return ($handler['type'] ?? '') === $step_type;
});

// Get step title from registered step configuration
$step_title = '';
if (!$is_empty) {
    $all_steps = apply_filters('dm_steps', []);
    $step_config = $all_steps[$step_type] ?? null;
    $step_title = $step_config['label'] ?? ucfirst(str_replace('_', ' ', $step_type));
}

$has_handlers = !$is_empty && !empty($available_handlers);
$step_uses_handlers = !$is_empty && ($step_type !== 'ai'); // AI steps don't use traditional handlers
$handler_configured = !$is_empty && !empty($current_handler);

// Status detection
$status_class = '';
if (!$is_empty) {
    $status = 'green'; // Default to green
    
    // AI step status
    if ($step_type === 'ai' && $pipeline_step_id) {
        $status = apply_filters('dm_detect_status', 'green', 'ai_step', [
            'pipeline_step_id' => $pipeline_step_id
        ]);
    }
    // Handler-based step status
    elseif ($step_uses_handlers) {
        if (!$handler_configured) {
            $status = 'red'; // No handler configured
        } else {
            // Check if configured handler requires authentication
            $handler_slug = $current_handler['handler_slug'] ?? '';
            $all_auth = apply_filters('dm_auth_providers', []);
            $requires_auth = isset($all_auth[$handler_slug]);
            
            if ($requires_auth) {
                $auth_status = apply_filters('dm_detect_status', 'green', 'handler_auth', [
                    'handler_slug' => $handler_slug
                ]);
                if ($auth_status === 'red') {
                    $status = 'red'; // Authentication required but missing
                }
            }
            
            // Check for handler customizations (only if still green)
            if ($status === 'green' && $flow_step_id) {
                $customizations = apply_filters('dm_get_handler_customizations', [], $flow_step_id);
                if (empty($customizations)) {
                    $status = 'yellow'; // Handler configured but no custom settings
                }
            }
            
            // Check for WordPress draft mode (only if still green)
            if ($status === 'green' && $handler_slug === 'wordpress_publish') {
                $draft_status = apply_filters('dm_detect_status', 'green', 'wordpress_draft', [
                    'flow_step_id' => $flow_step_id
                ]);
                if ($draft_status === 'yellow') {
                    $status = 'yellow'; // Warning: set to draft mode
                }
            }
            
            // Check for files handler status (only if still green)
            if ($status === 'green' && $handler_slug === 'files') {
                $files_status = apply_filters('dm_detect_status', 'green', 'files_status', [
                    'flow_step_id' => $flow_step_id
                ]);
                if ($files_status === 'red') {
                    $status = 'red'; // No files or all processed
                }
            }
        }
    }
    
    // Check for subsequent publish step (only if not already red)
    if ($status !== 'red' && $pipeline_step_id) {
        $subsequent_status = apply_filters('dm_detect_status', 'green', 'subsequent_publish_step', [
            'pipeline_step_id' => $pipeline_step_id
        ]);
        if ($subsequent_status === 'yellow') {
            $status = 'yellow'; // Override with warning status
        }
    }
    
    // Apply status class for all statuses (including green)
    $status_class = ' dm-step-card--status-' . $status;
}

?>
<div class="dm-step-container" 
     data-step-execution-order="<?php echo esc_attr($step_execution_order); ?>"
     data-step-type="<?php echo esc_attr($step_type); ?>"
     data-flow-id="<?php echo esc_attr($flow_id); ?>"
     data-pipeline-step-id="<?php echo esc_attr($pipeline_step_id); ?>"
     data-flow-step-id="<?php echo esc_attr($flow_step_id); ?>">

    <?php if (!$is_first_step): ?>
        <div class="dm-step-arrow">
            <span class="dashicons dashicons-arrow-right-alt"></span>
        </div>
    <?php endif; ?>

    <div class="dm-step-card<?php echo $is_empty ? ' dm-step-card--empty' : ''; ?><?php echo esc_attr($status_class); ?>">
        <?php if ($is_empty): ?>
            <!-- Empty step - Add Step button -->
            <div class="dm-step-empty-content">
                <button type="button" class="button button-secondary dm-modal-open dm-step-add-button"
                        data-template="step-selection"
                        data-context='{"context":"flow_builder","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>","flow_id":"<?php echo esc_attr($flow_id); ?>"}'>
                    <?php esc_html_e('Add Step', 'data-machine'); ?>
                </button>
            </div>
        <?php else: ?>
            <!-- Populated step -->
            <div class="dm-step-header">
                <div class="dm-step-title">
                    <?php echo esc_html($step_title); ?>
                    <?php if ($status === 'yellow' && $step_type === 'publish' && $pipeline_step_id): ?>
                        <?php 
                        // Check if this is specifically the subsequent publish warning
                        $subsequent_check = apply_filters('dm_detect_status', 'green', 'subsequent_publish_step', [
                            'pipeline_step_id' => $pipeline_step_id
                        ]);
                        if ($subsequent_check === 'yellow'): 
                        ?>
                            <span class="dashicons dashicons-warning" 
                                  title="<?php esc_attr_e('Warning: This publish step follows another publish step. AI cannot guide content to multiple destinations simultaneously. Consider using separate AI steps or separate flows for each destination.', 'data-machine'); ?>" 
                                  style="color: #f0b849; font-size: 14px; margin-left: 4px;"></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="dm-step-actions">
                    <?php if ($step_uses_handlers && $has_handlers): ?>
                        <?php if (!$handler_configured): ?>
                            <!-- No handlers configured - show Add Handler button -->
                            <button type="button" class="button button-small dm-modal-open" 
                                    data-template="handler-selection"
                                    data-context='{"flow_step_id":"<?php echo esc_attr($flow_step_id); ?>","step_type":"<?php echo esc_attr($step_type); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>"}'>
                                <?php esc_html_e('Add Handler', 'data-machine'); ?>
                            </button>
                        <?php else: ?>
                            <!-- Handler configured - show Edit Handler button -->
                            <?php
                            $handler_slug = $current_handler['handler_slug'] ?? '';
                            
                            // Determine correct handler settings template - WordPress needs fetch/publish distinction
                            $template_slug = $handler_slug;
                            if ($handler_slug === 'wordpress') {
                                $template_slug = ($step_type === 'fetch') ? 'wordpress_fetch' : 'wordpress_publish';
                            }
                            ?>
                            <button type="button" class="button button-small dm-modal-open" 
                                    data-template="handler-settings/<?php echo esc_attr($template_slug); ?>"
                                    data-context='{"flow_step_id":"<?php echo esc_attr($flow_step_id); ?>","step_type":"<?php echo esc_attr($step_type); ?>","handler_slug":"<?php echo esc_attr($handler_slug); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>","flow_id":"<?php echo esc_attr($flow_id); ?>"}'>
                                <?php esc_html_e('Edit Handler', 'data-machine'); ?>
                            </button>
                        <?php endif; ?>
                    <?php elseif ($step_type === 'ai'): ?>
                        <!-- AI step - configuration handled at pipeline level, only display info in flows -->
                    <?php endif; ?>
                </div>
            </div>
            <div class="dm-step-body">
                <div class="dm-flow-step-info">
                    <?php if ($step_uses_handlers && $handler_configured): ?>
                        <!-- Show configured handler -->
                        <div class="dm-handler-tag" data-handler-key="<?php echo esc_attr($current_handler['handler_slug'] ?? ''); ?>">
                            <span class="dm-handler-name"><?php echo esc_html($current_handler['handler_slug'] ?? 'Unknown'); ?></span>
                        </div>
                        
                        <?php 
                        // Show customized settings or "Not configured" badge
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
                        <!-- No handlers configured -->
                        <div class="dm-placeholder-text"><?php esc_html_e('No handlers configured', 'data-machine'); ?></div>
                    <?php elseif ($step_type === 'ai'): ?>
                        <!-- AI step status - show configuration from pipeline level -->
                        <?php
                        $ai_config = apply_filters('dm_ai_config', [], $pipeline_step_id);
                        $show_config = false;
                        if (!empty($ai_config) && isset($ai_config['selected_provider'])) {
                            $selected_provider = $ai_config['selected_provider'];
                            $model_name = $ai_config['model'] ?? '';
                            // Only check if provider and model are present
                            // Library will handle API key validation during actual requests
                            if ($selected_provider && $model_name) {
                                $show_config = true;
                            }
                        }
                        if ($show_config) {
                            $display_text = ucfirst($selected_provider) . ': ' . $model_name;
                        } else {
                            $display_text = 'AI step not configured';
                        }
                        ?>
                        <div class="dm-placeholder-text"><?php echo esc_html($display_text); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>