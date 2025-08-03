<?php
/**
 * Flow Step Card Template
 *
 * Pure rendering template for flow step cards with handler discovery.
 * Used in both AJAX responses and initial page loads.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Extract variables for template use
$step_type = $step['step_type'] ?? 'unknown';
$step_position = $step['position'] ?? 'unknown'; // Flow steps mirror pipeline step positions
$step_handlers = $flow_config['steps'][0] ?? []; // Simplified - could use step_type as key

// Dynamic handler discovery using parameter-based filter system
$available_handlers = apply_filters('dm_get_handlers', null, $step_type);
$has_handlers = !empty($available_handlers);

// AI steps don't use traditional handlers - they use internal multi-provider client
$step_uses_handlers = ($step_type !== 'ai');

?>
<div class="dm-step-card dm-flow-step" data-flow-id="<?php echo esc_attr($flow_id); ?>" data-step-type="<?php echo esc_attr($step_type); ?>" data-step-position="<?php echo esc_attr($step_position); ?>">
    <div class="dm-step-header">
        <div class="dm-step-title"><?php echo esc_html(ucfirst(str_replace('_', ' ', $step_type))); ?></div>
        <div class="dm-step-actions">
            <?php if ($has_handlers && $step_uses_handlers): ?>
                <button type="button" class="button button-small dm-modal-open" 
                        data-template="handler-selection"
                        data-context='{"flow_id":"<?php echo esc_attr($flow_id); ?>","step_type":"<?php echo esc_attr($step_type); ?>"}'>
                    <?php esc_html_e('Add Handler', 'data-machine'); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="dm-step-body">
        <!-- Configured Handlers for this step (only for steps that use handlers) -->
        <?php if ($step_uses_handlers): ?>
            <div class="dm-step-handlers">
                <?php if (!empty($step_handlers)): ?>
                    <?php foreach ($step_handlers as $handler_key => $handler_config): ?>
                        <div class="dm-handler-tag" data-handler-key="<?php echo esc_attr($handler_key); ?>">
                            <span class="dm-handler-name"><?php echo esc_html($handler_config['name'] ?? $handler_key); ?></span>
                            <button type="button" class="dm-handler-remove" 
                                    data-handler-key="<?php echo esc_attr($handler_key); ?>" 
                                    data-flow-id="<?php echo esc_attr($flow_id); ?>">×</button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="dm-no-handlers">
                        <span><?php esc_html_e('No handlers configured', 'data-machine'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>