<?php
/**
 * Pipeline AJAX Handler
 *
 * Handles all AJAX operations for the pipeline admin page.
 * Maintains clean separation where modal is pure UI and this component 
 * provides the business logic and content generation.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Pipelines;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class PipelineAjax
{
    /**
     * Handle pipeline AJAX requests
     */
    public function handle_pipeline_ajax()
    {
        // Verify nonce
        if (!check_ajax_referer('dm_pipeline_ajax', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed', 'data-machine')]);
        }

        // Verify user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        // Get action from POST data
        $action = sanitize_text_field(wp_unslash($_POST['pipeline_action'] ?? ''));

        switch ($action) {
            case 'get_step_selection':
                $this->get_step_selection_content();
                break;
            
            case 'add_step':
                $this->add_step_to_pipeline();
                break;
            
            default:
                wp_send_json_error(['message' => __('Invalid action', 'data-machine')]);
        }
    }

    /**
     * Generate step selection modal content
     */
    private function get_step_selection_content()
    {
        // Get all registered steps using proper filter-based discovery
        $all_steps = [];
        
        // Let steps self-register through the filter system
        // Try common step types but don't hardcode - let filter system decide
        $possible_types = ['input', 'ai', 'output']; // Just for discovery, not hardcoded requirement
        
        foreach ($possible_types as $type) {
            $step_config = apply_filters('dm_get_steps', null, $type);
            if ($step_config) {
                $all_steps[$type] = $step_config;
            }
        }

        if (empty($all_steps)) {
            wp_send_json_error(['message' => __('No steps available', 'data-machine')]);
        }

        // Generate HTML content for modal
        ob_start();
        $this->render_step_selection_cards($all_steps);
        $content = ob_get_clean();

        wp_send_json_success([
            'content' => $content,
            'title' => __('Choose Step Type', 'data-machine')
        ]);
    }

    /**
     * Render step selection cards
     */
    private function render_step_selection_cards($all_steps)
    {
        ?>
        <div class="dm-step-selection-container">
            <div class="dm-step-selection-header">
                <p><?php esc_html_e('Select a step type to add to your pipeline', 'data-machine'); ?></p>
            </div>
            
            <div class="dm-step-cards">
                <?php foreach ($all_steps as $step_type => $step_config): ?>
                    <?php $this->render_step_card($step_type, $step_config); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render individual step card
     */
    private function render_step_card($step_type, $step_config)
    {
        $label = $step_config['label'] ?? ucfirst($step_type);
        $description = $step_config['description'] ?? '';
        
        // Get available handlers for this step type using filter-based discovery
        $handlers_list = $this->get_handlers_for_step_type($step_type);
        
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
        <?php
    }

    /**
     * Get available handlers for a specific step type using filter-based discovery
     * 
     * @param string $step_type The step type ('input', 'ai', 'output')
     * @return string Comma-separated list of handler names
     */
    private function get_handlers_for_step_type($step_type)
    {
        $handlers_list = '';
        
        if ($step_type === 'ai') {
            // AI steps use multi-provider AI client
            $handlers_list = __('Multi-provider AI client', 'data-machine');
        } elseif (in_array($step_type, ['input', 'output'])) {
            // DEBUG: Log before calling the filter
            error_log("DEBUG: PipelineAjax calling dm_get_handlers filter for step_type: {$step_type}");
            
            // Use filter system to discover available handlers
            $handlers = apply_filters('dm_get_handlers', null, $step_type);
            
            // DEBUG: Log what we got back from the filter
            error_log("DEBUG: PipelineAjax got handlers: " . print_r($handlers, true));
            
            if (!empty($handlers)) {
                $handler_labels = [];
                foreach ($handlers as $handler_slug => $handler_config) {
                    $handler_labels[] = $handler_config['label'] ?? ucfirst($handler_slug);
                }
                $handlers_list = implode(', ', $handler_labels);
                error_log("DEBUG: PipelineAjax final handlers_list: {$handlers_list}");
            } else {
                error_log("DEBUG: PipelineAjax - no handlers found for {$step_type}");
            }
        }
        
        return $handlers_list;
    }

    /**
     * Add step to pipeline (placeholder for now)
     */
    private function add_step_to_pipeline()
    {
        $step_type = sanitize_text_field(wp_unslash($_POST['step_type'] ?? ''));
        
        if (empty($step_type)) {
            wp_send_json_error(['message' => __('Step type is required', 'data-machine')]);
        }

        // Validate step type exists
        $step_config = apply_filters('dm_get_steps', null, $step_type);
        if (!$step_config) {
            wp_send_json_error(['message' => __('Invalid step type', 'data-machine')]);
        }

        // For now, just return success - database integration will come later
        wp_send_json_success([
            'message' => sprintf(__('Step "%s" added successfully', 'data-machine'), $step_config['label']),
            'step_type' => $step_type,
            'step_config' => $step_config
        ]);
    }
}