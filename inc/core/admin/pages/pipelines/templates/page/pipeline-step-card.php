<?php
/**
 * Pipeline Step Card Template
 *
 * Pure rendering template for pipeline step cards (template level).
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
$is_empty = $step['is_empty'] ?? false;
$step_type = $step['step_type'] ?? '';
$step_position = $step['position'] ?? 'unknown';
$step_config = $step['step_config'] ?? [];
$label = $is_empty ? '' : ($step_config['label'] ?? ucfirst(str_replace('_', ' ', $step_type)));

// Step configuration discovery (parallel to handler discovery pattern)
$step_config_info = $is_empty ? null : apply_filters('dm_get_step_config', null, $step_type, ['context' => 'pipeline']);
$has_step_config = !$is_empty && !empty($step_config_info);

?>
<?php
// Arrow before every step except the very first step in the container
$is_first_step = $is_first_step ?? false;
if (!$is_first_step): ?>
    <div class="dm-step-arrow">
        <span class="dashicons dashicons-arrow-right-alt"></span>
    </div>
<?php endif; ?>

<div class="dm-step-card dm-pipeline-step<?php echo $is_empty ? ' dm-step-card--empty' : ''; ?>" data-step-type="<?php echo esc_attr($step_type); ?>" data-step-position="<?php echo esc_attr($step_position); ?>">
    <?php if ($is_empty): ?>
        <!-- Empty step - Add Step functionality -->
        <div class="dm-step-empty-content">
            <button type="button" class="button button-secondary dm-modal-open dm-step-add-button"
                    data-template="step-selection"
                    data-context='{"context":"pipeline_builder","pipeline_id":"<?php echo esc_attr($pipeline_id ?? ''); ?>"}'>
                <?php esc_html_e('Add Step', 'data-machine'); ?>
            </button>
        </div>
    <?php else: ?>
        <!-- Populated step - normal step content -->
        <div class="dm-step-header">
            <div class="dm-step-title"><?php echo esc_html($label); ?></div>
            <div class="dm-step-actions">
                <button type="button" class="button button-small button-link-delete dm-modal-open" 
                        data-template="confirm-delete"
                        data-context='{"delete_type":"step","step_type":"<?php echo esc_attr($step_type); ?>","step_position":"<?php echo esc_attr($step_position); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id ?? ''); ?>"}'>
                    <?php esc_html_e('Delete', 'data-machine'); ?>
                </button>
                <?php if ($has_step_config): ?>
                    <button type="button" class="button button-small button-link-configure dm-modal-open" 
                            data-template="configure-step"
                            data-context='{"step_type":"<?php echo esc_attr($step_type); ?>","modal_type":"<?php echo esc_attr($step_config_info['modal_type'] ?? ''); ?>","config_type":"<?php echo esc_attr($step_config_info['config_type'] ?? ''); ?>"}'>
                        <?php echo esc_html($step_config_info['button_text'] ?? __('Configure', 'data-machine')); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="dm-step-body">
            <!-- Step body content - no colored badges for clean mechanical interface -->
        </div>
    <?php endif; ?>
</div>