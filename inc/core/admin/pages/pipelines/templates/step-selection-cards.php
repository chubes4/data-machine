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
<div class="dm-step-selection-container">
    <div class="dm-step-selection-header">
        <p><?php esc_html_e('Select a step type to add to your pipeline', 'data-machine'); ?></p>
    </div>
    
    <div class="dm-step-cards">
        <?php foreach ($all_steps as $step_type => $step_config): ?>
            <?php
            $label = $step_config['label'] ?? ucfirst($step_type);
            $description = $step_config['description'] ?? '';
            
            // Get available handlers for this step type using filter-based discovery
            $handlers_list = '';
            if ($step_type === 'ai') {
                // AI steps use multi-provider AI client
                $handlers_list = __('Multi-provider AI client', 'data-machine');
            } elseif (in_array($step_type, ['input', 'output'])) {
                // Use filter system to discover available handlers
                $handlers = apply_filters('dm_get_handlers', null, $step_type);
                
                if (!empty($handlers)) {
                    $handler_labels = [];
                    foreach ($handlers as $handler_slug => $handler_config) {
                        $handler_labels[] = $handler_config['label'] ?? ucfirst($handler_slug);
                    }
                    $handlers_list = implode(', ', $handler_labels);
                }
            }
            ?>
            <div class="dm-step-selection-card" data-step-type="<?php echo esc_attr($step_type); ?>">
                <div class="dm-step-card-header">
                    <div class="dm-step-icon dm-step-icon-<?php echo esc_attr($step_type); ?>">
                        <?php echo esc_html(strtoupper(substr($step_type, 0, 2))); ?>
                    </div>
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