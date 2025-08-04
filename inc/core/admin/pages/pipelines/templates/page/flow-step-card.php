<?php
/**
 * Flow Step Card Template
 *
 * Pure rendering template for flow step cards with handler discovery.
 * Uses identical empty step pattern as pipeline steps.
 * Used in both AJAX responses and initial page loads.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Extract variables for template use - identical to pipeline step pattern
$is_empty = $step['is_empty'] ?? false;
$step_type = $step['step_type'] ?? '';
$step_position = $step['position'] ?? 'unknown';
$step_handlers = $flow_config['steps'][0] ?? []; // Simplified - could use step_type as key

// Dynamic handler discovery using parameter-based filter system (only for non-empty steps)
$available_handlers = $is_empty ? [] : apply_filters('dm_get_handlers', null, $step_type);
$has_handlers = !empty($available_handlers);

// AI steps don't use traditional handlers - they use internal multi-provider client
$step_uses_handlers = !$is_empty && ($step_type !== 'ai');

?>
<?php
// Arrow before every step except the very first step in the container
$is_first_step = $is_first_step ?? false;
if (!$is_first_step): ?>
    <div class="dm-flow-step-arrow">
        <span class="dashicons dashicons-arrow-right-alt"></span>
    </div>
<?php endif; ?>

<div class="dm-step-card dm-flow-step<?php echo $is_empty ? ' dm-step-card--empty' : ''; ?>" data-flow-id="<?php echo esc_attr($flow_id); ?>" data-step-type="<?php echo esc_attr($step_type); ?>" data-step-position="<?php echo esc_attr($step_position); ?>">
    <?php if ($is_empty): ?>
        <!-- Empty flow step - End of flow indicator (no button like pipeline) -->
        <div class="dm-step-empty-content">
            <div class="dm-flow-end-indicator">
                <span class="dm-placeholder-text"><?php esc_html_e('End of flow', 'data-machine'); ?></span>
            </div>
        </div>
    <?php else: ?>
        <!-- Populated flow step - normal step content -->
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
            <!-- Universal flow step configuration info -->
            <div class="dm-flow-step-info">
                <?php if ($step_uses_handlers): ?>
                    <!-- Handler-based steps configuration -->
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
                        <div class="dm-no-config">
                            <span><?php esc_html_e('No handlers configured', 'data-machine'); ?></span>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- AI steps configuration -->
                    <div class="dm-no-config">
                        <span><?php esc_html_e('No AI model configured', 'data-machine'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>