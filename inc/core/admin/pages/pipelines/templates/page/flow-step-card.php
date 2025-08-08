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
$step_type = $step['step_type'] ?? '';
$step_position = $step['position'] ?? 0;
$flow_id = $flow_id ?? 0;
$pipeline_id = $pipeline_id ?? 0;
$is_first_step = $is_first_step ?? false;

// Generate flow-specific step_id using step_id_flow_id pattern
$pipeline_step_id = $step['step_id'] ?? null;
if (!$pipeline_step_id) {
    throw new \InvalidArgumentException('flow-step-card template requires step_id from pipeline level to generate flow step ID.');
}

// Create flow step ID: step_id_flow_id
$flow_step_id = $pipeline_step_id . '_' . $flow_id;

// Get handler configuration from flow config using position-based access
// with fallback to flow_step_id search for robustness
$current_handler = null;
if (!empty($flow_config[$step_position]['handler'])) {
    $current_handler = $flow_config[$step_position]['handler'];
} else {
    // Fallback: search flow_config for matching flow_step_id
    foreach ($flow_config as $flow_step) {
        if (($flow_step['flow_step_id'] ?? '') === $flow_step_id && !empty($flow_step['handler'])) {
            $current_handler = $flow_step['handler'];
            break;
        }
    }
}

// Get available handlers for this step type
$all_handlers = apply_filters('dm_get_handlers', []);
$available_handlers = array_filter($all_handlers, function($handler) use ($step_type) {
    return ($handler['type'] ?? '') === $step_type;
});

$step_title = ucfirst(str_replace('_', ' ', $step_type));
$has_handlers = !empty($available_handlers);
$step_uses_handlers = ($step_type !== 'ai'); // AI steps don't use traditional handlers
$handler_configured = !empty($current_handler);

?>
<div class="dm-step-container" 
     data-step-position="<?php echo esc_attr($step_position); ?>"
     data-step-type="<?php echo esc_attr($step_type); ?>"
     data-flow-id="<?php echo esc_attr($flow_id); ?>"
     data-pipeline-step-id="<?php echo esc_attr($pipeline_step_id); ?>"
     data-flow-step-id="<?php echo esc_attr($flow_step_id); ?>">

    <?php if (!$is_first_step): ?>
        <div class="dm-step-arrow">
            <span class="dashicons dashicons-arrow-right-alt"></span>
        </div>
    <?php endif; ?>

    <div class="dm-step-card">
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
                        
                        // Determine correct handler settings template - WordPress needs input/output distinction
                        $template_slug = $handler_slug;
                        if ($handler_slug === 'wordpress') {
                            $template_slug = ($step_type === 'input') ? 'wordpress_input' : 'wordpress_output';
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
                    <!-- AI step status -->
                    <?php 
                    // Get AI configuration for this step using filter-based discovery
                    $ai_config = [];
                    if ($pipeline_step_id) {
                        $ai_client = apply_filters('dm_get_ai_http_client', null);
                        if ($ai_client) {
                            $ai_config = $ai_client->get_step_configuration($pipeline_step_id);
                        }
                    }
                    
                    if (!empty($ai_config['provider']) && !empty($ai_config['model'])): ?>
                        <div class="dm-ai-config-status">
                            <span class="dm-ai-provider"><?php echo esc_html(ucfirst($ai_config['provider'])); ?></span>
                            <span class="dm-ai-model"><?php echo esc_html($ai_config['model']); ?></span>
                            <?php if (!empty($ai_config['temperature'])): ?>
                                <span class="dm-ai-temperature">T: <?php echo esc_html($ai_config['temperature']); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="dm-placeholder-text"><?php esc_html_e('Configure step to see AI status', 'data-machine'); ?></div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>