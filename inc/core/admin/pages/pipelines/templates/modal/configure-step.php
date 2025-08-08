<?php
/**
 * Configure Step Modal Template
 *
 * Pure rendering template for step configuration modal content.
 * Handles AI step configuration with proper modal action buttons.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Templates
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Context auto-resolved by PipelineContextManager filter before template renders
// All required context variables are available: $step_type, $pipeline_id, $step_id

?>
<div class="dm-configure-step-container">
    <div class="dm-configure-step-header">
        <h3><?php echo esc_html(sprintf(__('Configure %s Step', 'data-machine'), ucfirst($step_type))); ?></h3>
        <p><?php echo esc_html(sprintf(__('Set up your %s step configuration below.', 'data-machine'), $step_type)); ?></p>
    </div>
    
    <?php if ($step_type === 'ai'): ?>
        <?php
        // FAIL FAST - require step_id for unique AI step configuration
        if (!$step_id) {
            echo '<div class="dm-error">
                <h4>' . __('Configuration Error', 'data-machine') . '</h4>
                <p>' . __('Step ID is required for AI step configuration.', 'data-machine') . '</p>
                <p><em>' . __('Missing: step_id', 'data-machine') . '</em></p>
            </div>';
            return;
        }
        
        // Render AI HTTP Client ProviderManagerComponent for complete AI configuration
        if (class_exists('AI_HTTP_ProviderManager_Component')) {
            echo \AI_HTTP_ProviderManager_Component::render([
                'plugin_context' => 'data-machine',
                'ai_type' => 'llm',
                'title' => '', // No title since we have our own header
                'components' => [
                    'core' => ['provider_selector', 'api_key_input', 'model_selector'],
                    'extended' => ['temperature_slider', 'system_prompt_field']
                ],
                'show_test_connection' => false,
                'show_save_button' => false, // Hide built-in save button - we provide our own
                'wrapper_class' => 'ai-http-provider-manager dm-ai-step-config',
                'step_id' => $step_id, // Unique step-aware configuration
                'component_configs' => [
                    'temperature_slider' => [
                        // KISS: Only customize what Data Machine specifically needs
                        'label' => __('AI Creativity Level', 'data-machine'),
                        'help_text' => __('Controls randomness in AI responses. Higher values = more creative, lower values = more focused.', 'data-machine')
                    ],
                    'system_prompt_field' => [
                        'label' => __('AI Processing Instructions', 'data-machine'),
                        'placeholder' => __('Define how the AI should process data from previous pipeline steps...', 'data-machine'),
                        'help_text' => __('Instructions that guide AI behavior for this pipeline step. The AI will receive data from all previous steps automatically.', 'data-machine'),
                        'rows' => 6,
                        'default_value' => ''
                    ]
                ]
            ]);
        } else {
            // Fallback if AI HTTP Client is not available
            echo '<div class="dm-ai-config-error">
                <h4>' . __('AI Configuration Unavailable', 'data-machine') . '</h4>
                <p>' . __('The AI HTTP Client library is required for AI step configuration. Please ensure the library is properly loaded.', 'data-machine') . '</p>
                <p><em>' . __('Expected class: AI_HTTP_ProviderManager_Component', 'data-machine') . '</em></p>
            </div>';
        }
        ?>
    <?php else: ?>
        <div class="dm-generic-step-config">
            <p><?php echo esc_html(sprintf(__('Configuration for %s steps is not yet implemented.', 'data-machine'), $step_type)); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="dm-step-config-actions">
        <button type="button" class="button button-secondary dm-cancel-settings">
            <?php esc_html_e('Cancel', 'data-machine'); ?>
        </button>
        <button type="button" class="button button-primary dm-modal-close" 
                data-template="configure-step-action"
                data-context='{"step_type":"<?php echo esc_attr($step_type ?? ''); ?>","pipeline_id":"<?php echo esc_attr($pipeline_id ?? ''); ?>","step_id":"<?php echo esc_attr($step_id ?? ''); ?>"}'>
            <?php esc_html_e('Save Step Configuration', 'data-machine'); ?>
        </button>
    </div>
</div>