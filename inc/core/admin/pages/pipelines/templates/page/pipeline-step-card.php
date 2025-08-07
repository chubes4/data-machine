<?php
/**
 * Pipeline Step Card Template
 *
 * Dedicated template for pipeline-level step cards only.
 * Simplified from universal template - no context switching complexity.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Extract and prepare template variables - no fallbacks
if (!isset($step) || !is_array($step)) {
    throw new \InvalidArgumentException('pipeline-step-card template requires step data array');
}
if (!isset($pipeline_id)) {
    throw new \InvalidArgumentException('pipeline-step-card template requires pipeline_id parameter');
}

$is_empty = $step['is_empty'];
$step_type = $step['step_type'];
$step_position = $step['position'];
$step_id = $step['step_id'] ?? null;
$step_data = $step; // Database format: step IS the step data

// Pipeline-specific variables (from original pipeline context branch)
$step_title = $is_empty ? '' : ($step_data['label'] ?? ucfirst(str_replace('_', ' ', $step_type)));

// Step configurations discovery (pure discovery pattern)
if ($is_empty) {
    $step_config_info = null;
} else {
    $all_step_configs = apply_filters('dm_get_step_configs', []);
    $step_config_info = $all_step_configs[$step_type] ?? null;
}
$has_step_config = !$is_empty && !empty($step_config_info);

?>
<div class="dm-step-container" 
     data-step-position="<?php echo esc_attr($step_position); ?>"
     data-step-type="<?php echo esc_attr($step_type); ?>"
     <?php if (!empty($step_id)): ?>data-step-id="<?php echo esc_attr($step_id); ?>"<?php endif; ?>
     data-pipeline-id="<?php echo esc_attr($pipeline_id); ?>">

    <?php
    // Universal arrow logic - contextual validation
    // For empty steps or when arrows aren't critical, default to showing arrow (safer for UI)
    if (!isset($is_first_step)) {
        if ($is_empty) {
            // Empty steps don't need strict arrow validation - default to showing arrow
            $is_first_step = false;
        } else {
            // Populated steps should have proper arrow logic
            throw new \InvalidArgumentException('pipeline-step-card template requires is_first_step parameter for populated steps');
        }
    }
    if (!$is_first_step): ?>
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
                        data-context='{"context":"pipeline_builder","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>"}'>
                    <?php esc_html_e('Add Step', 'data-machine'); ?>
                </button>
            </div>
        <?php else: ?>
            <!-- Populated step -->
            <div class="dm-step-header">
                <div class="dm-step-title"><?php echo esc_html($step_title); ?></div>
                <div class="dm-step-actions">
                    <!-- Pipeline actions: Delete + Configure -->
                    <button type="button" class="button button-small button-link-delete dm-modal-open" 
                            data-template="confirm-delete"
                            data-context='{"delete_type":"step","step_type":"<?php echo esc_attr($step_type); ?>","step_id":"<?php echo esc_attr($step_id); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>"}'>
                        <?php esc_html_e('Delete', 'data-machine'); ?>
                    </button>
                    <?php if ($has_step_config): ?>
                        <button type="button" class="button button-small button-link-configure dm-modal-open" 
                                data-template="configure-step"
                                data-context='{"step_type":"<?php echo esc_attr($step_type); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id); ?>","step_id":"<?php echo esc_attr($step_id); ?>","modal_type":"<?php echo esc_attr($step_config_info['modal_type'] ?? ''); ?>","config_type":"<?php echo esc_attr($step_config_info['config_type'] ?? ''); ?>"}'>
                            <?php echo esc_html($step_config_info['button_text'] ?? __('Configure', 'data-machine')); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="dm-step-body">
                <!-- Pipeline context: empty body (structural only) -->
            </div>
        <?php endif; ?>
    </div>
</div>