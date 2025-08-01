<?php
/**
 * Handler Settings Form Template
 *
 * Pure rendering template for handler configuration modal content.
 * Uses filter-based settings discovery for dynamic form generation.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

$handler_label = $handler_config['label'] ?? ucfirst($handler_slug);

?>
<div class="dm-handler-settings-container">
    <div class="dm-handler-settings-header">
        <h3><?php echo esc_html(sprintf(__('Configure %s Handler', 'data-machine'), $handler_label)); ?></h3>
        <p><?php echo esc_html(sprintf(__('Set up your %s integration settings below.', 'data-machine'), $handler_label)); ?></p>
    </div>
    
    <form class="dm-handler-settings-form" data-handler-slug="<?php echo esc_attr($handler_slug); ?>" data-step-type="<?php echo esc_attr($step_type); ?>">
        <?php if ($settings_available && $handler_settings): ?>
            <div class="dm-settings-fields">
                <?php
                // If handler has settings class, let it render its form
                if (method_exists($handler_settings, 'render_settings_form')) {
                    $handler_settings->render_settings_form();
                } else {
                    // Fallback: basic settings form
                    $this->render_basic_settings_form($handler_slug, $handler_config);
                }
                ?>
            </div>
        <?php else: ?>
            <div class="dm-no-settings">
                <p><?php echo esc_html(sprintf(__('The %s handler doesn\'t require additional configuration.', 'data-machine'), $handler_label)); ?></p>
                <p><?php esc_html_e('You can add this handler directly to your flow.', 'data-machine'); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="dm-settings-actions">
            <button type="button" class="button button-secondary dm-cancel-settings">
                <?php esc_html_e('Cancel', 'data-machine'); ?>
            </button>
            <button type="submit" class="button button-primary dm-save-handler-settings">
                <?php esc_html_e('Add Handler to Flow', 'data-machine'); ?>
            </button>
        </div>
        
        <?php wp_nonce_field('dm_save_handler_settings', 'handler_settings_nonce'); ?>
    </form>
</div>

<?php
/**
 * Render basic settings form for handlers without custom settings class
 */
function render_basic_settings_form($handler_slug, $handler_config) {
    ?>
    <div class="dm-basic-settings">
        <div class="dm-form-field">
            <label for="handler_name"><?php esc_html_e('Handler Name', 'data-machine'); ?></label>
            <input type="text" 
                   id="handler_name" 
                   name="handler_name" 
                   value="<?php echo esc_attr($handler_config['label'] ?? ucfirst($handler_slug)); ?>"
                   class="regular-text" />
            <p class="description"><?php esc_html_e('Custom name for this handler instance.', 'data-machine'); ?></p>
        </div>
        
        <?php if (!empty($handler_config['description'])): ?>
            <div class="dm-form-field">
                <label><?php esc_html_e('Description', 'data-machine'); ?></label>
                <p><?php echo esc_html($handler_config['description']); ?></p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
?>