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

// Extract required data  
// $step_type is already extracted by template context resolution via 'extract_from_step'
$step_type = $step_type ?? '';
$step_execution_order = $step['execution_order'] ?? 0;
$flow_id = $flow_id ?? 0;
$pipeline_id = $pipeline_id ?? 0;
$is_first_step = $is_first_step ?? false;
$is_empty = $step['is_empty'] ?? false;

// Handle empty steps gracefully (matching pipeline-step-card pattern)
$pipeline_step_id = $step['pipeline_step_id'] ?? null;
$flow_step_id = null;

if (!$is_empty) {
    // Only process flow_step_id lookup for populated steps
    if (!$pipeline_step_id) {
        throw new \InvalidArgumentException('flow-step-card template requires pipeline_step_id for populated steps.');
    }

    // Find flow_step_id from existing flow_config (stored data)
    foreach ($flow_config as $existing_flow_step_id => $step_data) {
        if (isset($step_data['pipeline_step_id']) && $step_data['pipeline_step_id'] === $pipeline_step_id) {
            $flow_step_id = $existing_flow_step_id;
            break;
        }
    }

    if (!$flow_step_id) {
        throw new \InvalidArgumentException('Template bug: flow_step_id not found in flow_config for populated step. Calling code must provide proper data.');
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

$step_title = $is_empty ? '' : ucfirst(str_replace('_', ' ', $step_type));
$has_handlers = !$is_empty && !empty($available_handlers);
$step_uses_handlers = !$is_empty && ($step_type !== 'ai'); // AI steps don't use traditional handlers
$handler_configured = !$is_empty && !empty($current_handler);

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

    <div class="dm-step-card<?php echo $is_empty ? ' dm-step-card--empty' : ''; ?>">
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
                <div class="dm-step-title"><?php echo esc_html($step_title); ?></div>
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
                                    data-context='{"flow_step_id":"<?php echo esc_attr($flow_step_id); ?>","step_type":"<?php echo esc_attr($step_type); ?>","handler_slug":"<?php echo esc_attr($handler_slug); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>"}'>
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
                    <?php elseif ($step_uses_handlers): ?>
                        <!-- No handlers configured -->
                        <div class="dm-placeholder-text"><?php esc_html_e('No handlers configured', 'data-machine'); ?></div>
                    <?php elseif ($step_type === 'ai'): ?>
                        <!-- AI step status - show model name -->
                        <?php
                        $ai_config = apply_filters('ai_config', $pipeline_step_id);
                        
                        // Access provider-keyed configuration structure
                        $selected_provider = $ai_config['selected_provider'] ?? 'openai';
                        $provider_config = $ai_config[$selected_provider] ?? [];
                        $model_name = !empty($provider_config['model']) ? $provider_config['model'] : 'AI processing step configured';
                        ?>
                        <div class="dm-placeholder-text"><?php echo esc_html($model_name); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>