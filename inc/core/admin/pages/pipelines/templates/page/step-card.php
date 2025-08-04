<?php
/**
 * Universal Step Card Template
 *
 * Consolidated template for both pipeline and flow step cards.
 * Uses context-aware rendering to handle different UI requirements.
 * Container-based architecture eliminates arrow positioning issues.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Extract and prepare template variables - no fallbacks
if (!isset($context)) {
    throw new \InvalidArgumentException('step-card template requires context parameter');
}
if (!isset($step) || !is_array($step)) {
    throw new \InvalidArgumentException('step-card template requires step data array');
}
$is_empty = $step['is_empty'];
$step_type = $step['step_type'];
$step_position = $step['position'];
$step_config = $step['step_config'];

// Context-specific variables
if ($context === 'pipeline') {
    if (!isset($pipeline_id)) {
        throw new \InvalidArgumentException('pipeline context requires pipeline_id parameter');
    }
    $step_title = $is_empty ? '' : ($step_config['label'] ?? ucfirst(str_replace('_', ' ', $step_type)));
    
    // Step configuration discovery (parallel to handler discovery pattern)
    $step_config_info = $is_empty ? null : apply_filters('dm_get_step_config', null, $step_type, ['context' => 'pipeline']);
    $has_step_config = !$is_empty && !empty($step_config_info);
} else {
    // Flow context
    if (!isset($flow_id)) {
        throw new \InvalidArgumentException('flow context requires flow_id parameter');
    }
    if (!isset($flow_config)) {
        throw new \InvalidArgumentException('flow context requires flow_config parameter');
    }
    $step_title = $is_empty ? '' : ucfirst(str_replace('_', ' ', $step_type));
    $step_handlers = $flow_config['steps'][$step_type]['handlers'] ?? [];
    
    // Dynamic handler discovery using parameter-based filter system (only for non-empty steps)
    $available_handlers = $is_empty ? [] : apply_filters('dm_get_handlers', null, $step_type);
    $has_handlers = !empty($available_handlers);
    
    // AI steps don't use traditional handlers - they use internal multi-provider client
    $step_uses_handlers = !$is_empty && ($step_type !== 'ai');
}

?>
<div class="dm-step-container" 
     data-step-position="<?php echo esc_attr($step_position); ?>"
     data-step-type="<?php echo esc_attr($step_type); ?>"
     <?php if ($context === 'pipeline'): ?>data-pipeline-id="<?php echo esc_attr($pipeline_id); ?>"<?php endif; ?>
     <?php if ($context === 'flow'): ?>data-flow-id="<?php echo esc_attr($flow_id); ?>"<?php endif; ?>>

    <?php
    // Universal arrow logic - contextual validation
    // For empty steps or when arrows aren't critical, default to showing arrow (safer for UI)
    if (!isset($is_first_step)) {
        if ($is_empty) {
            // Empty steps don't need strict arrow validation - default to showing arrow
            $is_first_step = false;
        } else {
            // Populated steps should have proper arrow logic
            throw new \InvalidArgumentException('step-card template requires is_first_step parameter for populated steps');
        }
    }
    if (!$is_first_step): ?>
        <div class="dm-step-arrow">
            <span class="dashicons dashicons-arrow-right-alt"></span>
        </div>
    <?php endif; ?>

    <div class="dm-step-card<?php echo $is_empty ? ' dm-step-card--empty' : ''; ?>">
        <?php if ($is_empty): ?>
            <!-- Empty step - context determines content -->
            <div class="dm-step-empty-content">
                <?php if ($context === 'pipeline'): ?>
                    <!-- Pipeline: Add Step button -->
                    <button type="button" class="button button-secondary dm-modal-open dm-step-add-button"
                            data-template="step-selection"
                            data-context='{"context":"pipeline_builder","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>"}'>
                        <?php esc_html_e('Add Step', 'data-machine'); ?>
                    </button>
                <?php else: ?>
                    <!-- Flow: End of flow indicator -->
                    <div class="dm-flow-end-indicator">
                        <span class="dm-placeholder-text"><?php esc_html_e('End of flow', 'data-machine'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Populated step - context-aware content -->
            <div class="dm-step-header">
                <div class="dm-step-title"><?php echo esc_html($step_title); ?></div>
                <div class="dm-step-actions">
                    <?php if ($context === 'pipeline'): ?>
                        <!-- Pipeline actions: Delete + Configure -->
                        <button type="button" class="button button-small button-link-delete dm-modal-open" 
                                data-template="confirm-delete"
                                data-context='{"delete_type":"step","step_type":"<?php echo esc_attr($step_type); ?>","step_position":"<?php echo esc_attr($step_position); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>"}'>
                            <?php esc_html_e('Delete', 'data-machine'); ?>
                        </button>
                        <?php if ($has_step_config): ?>
                            <button type="button" class="button button-small button-link-configure dm-modal-open" 
                                    data-template="configure-step"
                                    data-context='{"step_type":"<?php echo esc_attr($step_type); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>","current_step":"<?php echo esc_attr($step_type); ?>","modal_type":"<?php echo esc_attr($step_config_info['modal_type'] ?? ''); ?>","config_type":"<?php echo esc_attr($step_config_info['config_type'] ?? ''); ?>"}'>
                                <?php echo esc_html($step_config_info['button_text'] ?? __('Configure', 'data-machine')); ?>
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Flow actions: Add Handler -->
                        <?php if ($has_handlers && $step_uses_handlers): ?>
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
                                $current_handler = array_keys($step_handlers)[0]; // Get first (and only) handler
                                ?>
                                <button type="button" class="button button-small dm-modal-open" 
                                        data-template="handler-settings"
                                        data-context='{"flow_id":"<?php echo esc_attr($flow_id); ?>","step_type":"<?php echo esc_attr($step_type); ?>","handler_slug":"<?php echo esc_attr($current_handler); ?>"}'>
                                    <?php esc_html_e('Edit Handler', 'data-machine'); ?>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="dm-step-body">
                <?php if ($context === 'flow'): ?>
                    <!-- Flow-specific: Handler management UI -->
                    <div class="dm-flow-step-info">
                        <?php if ($step_uses_handlers): ?>
                            <!-- Handler-based steps configuration -->
                            <?php if (!empty($step_handlers)): ?>
                                <?php foreach ($step_handlers as $handler_key => $handler_config): ?>
                                    <div class="dm-handler-tag" data-handler-key="<?php echo esc_attr($handler_key); ?>">
                                        <span class="dm-handler-name"><?php echo esc_html($handler_config['handler_slug'] ?? $handler_key); ?></span>
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
                <?php endif; ?>
                <!-- Pipeline context: empty body (structural only) -->
            </div>
        <?php endif; ?>
    </div>
</div>