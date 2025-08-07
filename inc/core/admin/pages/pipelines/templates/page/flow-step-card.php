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

// Use pipeline-level step_id (NEVER generate step_ids at flow level)
$step_id = $step['step_id'] ?? null;
if (!$step_id) {
    // FAIL FAST: Flow templates should never generate step_ids
    throw new \InvalidArgumentException('flow-step-card template requires step_id from pipeline level. Flow templates must not generate step_ids.');
}

// Get handler configuration from flow config using step_id
$step_handlers = [];
if (!empty($flow_config['steps'][$step_id]['handlers'])) {
    $step_handlers = $flow_config['steps'][$step_id]['handlers'];
}

// Get available handlers for this step type
$all_handlers = apply_filters('dm_get_handlers', []);
$available_handlers = array_filter($all_handlers, function($handler) use ($step_type) {
    return ($handler['type'] ?? '') === $step_type;
});

$step_title = ucfirst(str_replace('_', ' ', $step_type));
$has_handlers = !empty($available_handlers);
$step_uses_handlers = ($step_type !== 'ai'); // AI steps don't use traditional handlers

?>
<div class="dm-step-container" 
     data-step-position="<?php echo esc_attr($step_position); ?>"
     data-step-type="<?php echo esc_attr($step_type); ?>"
     data-flow-id="<?php echo esc_attr($flow_id); ?>"
     <?php if ($step_id): ?>data-step-id="<?php echo esc_attr($step_id); ?>"<?php endif; ?>>

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
                    <?php if (empty($step_handlers)): ?>
                        <!-- No handlers configured - show Add Handler button -->
                        <button type="button" class="button button-small dm-modal-open" 
                                data-template="handler-selection"
                                data-context='{"flow_id":"<?php echo esc_attr($flow_id); ?>","step_type":"<?php echo esc_attr($step_type); ?>"}'>
                            <?php esc_html_e('Add Handler', 'data-machine'); ?>
                        </button>
                    <?php else: ?>
                        <!-- Handler configured - show Edit Handler button -->
                        <?php
                        $current_handler = array_keys($step_handlers)[0]; // Get first handler
                        ?>
                        <button type="button" class="button button-small dm-modal-open" 
                                data-template="handler-settings"
                                data-context='{"flow_id":"<?php echo esc_attr($flow_id); ?>","step_type":"<?php echo esc_attr($step_type); ?>","step_id":"<?php echo esc_attr($step_id); ?>","handler_slug":"<?php echo esc_attr($current_handler); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>"}'>
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
                <?php if ($step_uses_handlers && !empty($step_handlers)): ?>
                    <!-- Show configured handlers -->
                    <?php foreach ($step_handlers as $handler_key => $handler_config): ?>
                        <div class="dm-handler-tag" data-handler-key="<?php echo esc_attr($handler_key); ?>">
                            <span class="dm-handler-name"><?php echo esc_html($handler_config['handler_slug'] ?? $handler_key); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php elseif ($step_uses_handlers): ?>
                    <!-- No handlers configured -->
                    <div class="dm-placeholder-text"><?php esc_html_e('No handlers configured', 'data-machine'); ?></div>
                <?php elseif ($step_type === 'ai'): ?>
                    <!-- AI step status -->
                    <div class="dm-placeholder-text"><?php esc_html_e('Configure step to see AI status', 'data-machine'); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>