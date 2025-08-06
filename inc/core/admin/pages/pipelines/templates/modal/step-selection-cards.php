<?php
/**
 * Step Selection Cards Template
 *
 * Pure rendering template for step selection modal content.
 * Displays available step types for pipeline building.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

?>
<div class="dm-step-selection-container" data-pipeline-id="<?php echo esc_attr($pipeline_id ?? ''); ?>">
    <!-- Hidden input to store pipeline ID for JavaScript access -->
    <input type="hidden" name="pipeline_id" value="<?php echo esc_attr($pipeline_id ?? ''); ?>" />
    
    <div class="dm-step-selection-header">
        <p><?php esc_html_e('Select a step type to add to your pipeline', 'data-machine'); ?></p>
    </div>
    
    <div class="dm-step-cards">
        <?php foreach ($all_steps as $step_type => $step_config): ?>
            <?php
            $label = $step_config['label'] ?? ucfirst($step_type);
            $description = $step_config['description'] ?? '';
            
            // Get available handlers for this step type using pure discovery
            $handlers_list = '';
            $all_handlers = apply_filters('dm_get_handlers', []);
            $handlers = array_filter($all_handlers, function($handler) use ($step_type) {
                return ($handler['type'] ?? '') === $step_type;
            });
            
            if (!empty($handlers)) {
                $handler_labels = [];
                foreach ($handlers as $handler_slug => $handler_config) {
                    $handler_labels[] = $handler_config['label'] ?? ucfirst($handler_slug);
                }
                $handlers_list = implode(', ', $handler_labels);
            }
            ?>
            <div class="dm-step-selection-card dm-modal-close" 
                 data-template="add-step-action"
                 data-context='{"step_type":"<?php echo esc_attr($step_type); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id ?? ''); ?>"}'
                 data-step-type="<?php echo esc_attr($step_type); ?>"
                 role="button" 
                 tabindex="0"
                 style="cursor: pointer;">
                <div class="dm-step-card-header">
                    <h5 class="dm-step-card-title"><?php echo esc_html($label); ?></h5>
                </div>
                <?php if ($description): ?>
                    <div class="dm-step-card-body">
                        <p class="dm-step-card-description"><?php echo esc_html($description); ?></p>
                        <?php if (!empty($handlers_list)): ?>
                            <div class="dm-step-handlers">
                                <span class="dm-handlers-label"><?php esc_html_e('Available handlers:', 'data-machine'); ?></span>
                                <span class="dm-handlers-list"><?php echo esc_html($handlers_list); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>