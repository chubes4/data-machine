<?php
/**
 * Handler Selection Cards Template
 *
 * Pure rendering template for handler selection modal content.
 * Grid-based layout with simple button-style handler selection.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

?>
<div class="dm-handler-selection-container">
    <!-- Hidden inputs for context -->
    <input type="hidden" name="pipeline_id" value="<?php echo esc_attr($pipeline_id ?? ''); ?>" />
    <input type="hidden" name="step_type" value="<?php echo esc_attr($step_type); ?>" />
    
    <div class="dm-handler-selection-header">
        <p><?php echo esc_html(sprintf(__('Select a %s handler to configure', 'data-machine'), $step_type)); ?></p>
    </div>
    
    <div class="dm-handler-grid">
        <?php foreach ($handlers as $handler_slug => $handler_config): ?>
            <button type="button" 
                    class="dm-handler-button" 
                    data-handler-slug="<?php echo esc_attr($handler_slug); ?>" 
                    data-step-type="<?php echo esc_attr($step_type); ?>">
                <div class="dm-handler-button-content">
                    <h4 class="dm-handler-name"><?php echo esc_html($handler_config['label'] ?? ucfirst($handler_slug)); ?></h4>
                    <?php if (!empty($handler_config['description'])): ?>
                        <p class="dm-handler-description"><?php echo esc_html($handler_config['description']); ?></p>
                    <?php endif; ?>
                </div>
            </button>
        <?php endforeach; ?>
    </div>
</div>