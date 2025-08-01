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
            
            case 'get_handler_selection':
                $this->get_handler_selection_content();
                break;
            
            case 'add_flow':
                $this->add_flow_to_pipeline();
                break;
            
            case 'get_flow_step_card':
                $this->get_flow_step_card();
                break;
            
            case 'get_pipeline_step_card':
                $this->get_pipeline_step_card();
                break;
            
            case 'get_handler_settings':
                $this->get_handler_settings();
                break;
            
            case 'save_pipeline':
                $this->save_pipeline();
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

        // Render template
        $content = $this->render_template('step-selection-cards', ['all_steps' => $all_steps]);

        wp_send_json_success([
            'content' => $content,
            'title' => __('Choose Step Type', 'data-machine')
        ]);
    }


    /**
     * Generate handler selection modal content
     */
    private function get_handler_selection_content()
    {
        // Get step type from POST data
        $step_type = sanitize_text_field(wp_unslash($_POST['step_type'] ?? ''));
        
        if (empty($step_type)) {
            wp_send_json_error(['message' => __('Step type is required', 'data-machine')]);
        }

        // Get available handlers using parameter-based filter discovery
        $available_handlers = apply_filters('dm_get_handlers', null, $step_type);
        
        if (empty($available_handlers)) {
            wp_send_json_error(['message' => sprintf(__('No handlers available for %s steps', 'data-machine'), $step_type)]);
        }

        // Render template
        $content = $this->render_template('handler-selection-cards', [
            'handlers' => $available_handlers,
            'step_type' => $step_type
        ]);

        wp_send_json_success([
            'content' => $content,
            'title' => sprintf(__('Choose %s Handler', 'data-machine'), ucfirst($step_type)),
            'step_type' => $step_type
        ]);
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

    /**
     * Add flow to pipeline
     */
    private function add_flow_to_pipeline()
    {
        $pipeline_id = sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        
        if (empty($pipeline_id)) {
            wp_send_json_error(['message' => __('Pipeline ID is required', 'data-machine')]);
        }

        // Get database service
        $db_flows = apply_filters('dm_get_database_service', null, 'flows');
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        
        if (!$db_flows || !$db_pipelines) {
            wp_send_json_error(['message' => __('Database services unavailable', 'data-machine')]);
        }

        // Verify pipeline exists
        $pipeline = $db_pipelines->get_pipeline_by_id($pipeline_id);
        if (!$pipeline) {
            wp_send_json_error(['message' => __('Pipeline not found', 'data-machine')]);
        }

        // Get pipeline name for generating flow name
        $pipeline_name = is_object($pipeline) ? $pipeline->pipeline_name : $pipeline['pipeline_name'];
        
        // Count existing flows for this pipeline to generate unique name
        $existing_flows = $db_flows->get_flows_for_pipeline($pipeline_id);
        $flow_number = count($existing_flows) + 1;
        $flow_name = sprintf(__('%s Flow %d', 'data-machine'), $pipeline_name, $flow_number);

        // Create new flow
        $flow_data = [
            'pipeline_id' => $pipeline_id,
            'flow_name' => $flow_name,
            'flow_config' => json_encode([]), // Empty config initially
            'scheduling_config' => json_encode([
                'status' => 'inactive',
                'interval' => 'manual'
            ])
        ];

        $flow_id = $db_flows->create_flow($flow_data);
        
        if (!$flow_id) {
            wp_send_json_error(['message' => __('Failed to create flow', 'data-machine')]);
        }

        wp_send_json_success([
            'message' => sprintf(__('Flow "%s" created successfully', 'data-machine'), $flow_name),
            'flow_id' => $flow_id,
            'flow_name' => $flow_name,
            'pipeline_id' => $pipeline_id
        ]);
    }

    /**
     * Generate flow step card HTML using template system
     */
    private function get_flow_step_card()
    {
        $step_type = sanitize_text_field(wp_unslash($_POST['step_type'] ?? ''));
        $flow_id = sanitize_text_field(wp_unslash($_POST['flow_id'] ?? 'new'));
        
        if (empty($step_type)) {
            wp_send_json_error(['message' => __('Step type is required', 'data-machine')]);
        }

        // Validate step type exists using filter system
        $step_config = apply_filters('dm_get_steps', null, $step_type);
        if (!$step_config) {
            wp_send_json_error(['message' => __('Invalid step type', 'data-machine')]);
        }

        // Prepare data for template
        $template_data = [
            'step' => [
                'step_type' => $step_type,
                'step_config' => []  // Empty config for new steps
            ],
            'flow_config' => [],  // Empty flow config for new steps
            'flow_id' => $flow_id
        ];

        // Render template
        $html = $this->render_template('flow-step-card', $template_data);

        wp_send_json_success([
            'html' => $html,
            'step_type' => $step_type,
            'flow_id' => $flow_id
        ]);
    }

    /**
     * Render template with data
     */
    private function render_template($template_name, $data = [])
    {
        $template_path = __DIR__ . '/templates/' . $template_name . '.php';
        
        if (!file_exists($template_path)) {
            return '<div class="dm-error">Template not found: ' . esc_html($template_name) . '</div>';
        }

        // Extract data variables for template use
        extract($data);

        // Capture template output
        ob_start();
        include $template_path;
        return ob_get_clean();
    }

    /**
     * Generate pipeline step card HTML using template system
     */
    private function get_pipeline_step_card()
    {
        $step_type = sanitize_text_field(wp_unslash($_POST['step_type'] ?? ''));
        
        if (empty($step_type)) {
            wp_send_json_error(['message' => __('Step type is required', 'data-machine')]);
        }

        // Validate step type exists using filter system
        $step_config = apply_filters('dm_get_steps', null, $step_type);
        if (!$step_config) {
            wp_send_json_error(['message' => __('Invalid step type', 'data-machine')]);
        }

        // Prepare data for template
        $template_data = [
            'step' => [
                'step_type' => $step_type,
                'step_config' => $step_config  // Include step config for pipeline level
            ]
        ];

        // Render template
        $html = $this->render_template('pipeline-step-card', $template_data);

        wp_send_json_success([
            'html' => $html,
            'step_type' => $step_type,
            'step_config' => $step_config
        ]);
    }

    /**
     * Get handler settings form HTML using template system
     */
    private function get_handler_settings()
    {
        $handler_slug = sanitize_text_field(wp_unslash($_POST['handler_slug'] ?? ''));
        $step_type = sanitize_text_field(wp_unslash($_POST['step_type'] ?? ''));
        
        if (empty($handler_slug) || empty($step_type)) {
            wp_send_json_error(['message' => __('Handler slug and step type are required', 'data-machine')]);
        }

        // Validate handler exists using filter system
        $handlers = apply_filters('dm_get_handlers', null, $step_type);
        if (empty($handlers[$handler_slug])) {
            wp_send_json_error(['message' => __('Handler not found', 'data-machine')]);
        }

        // Get handler settings using existing filter system
        $handler_settings = apply_filters('dm_get_handler_settings', null, $handler_slug);
        
        // Prepare data for template
        $template_data = [
            'handler_slug' => $handler_slug,
            'step_type' => $step_type,
            'handler_config' => $handlers[$handler_slug],
            'handler_settings' => $handler_settings,
            'settings_available' => !empty($handler_settings)
        ];

        // Render template
        $html = $this->render_template('handler-settings-form', $template_data);

        wp_send_json_success([
            'html' => $html,
            'handler_slug' => $handler_slug,
            'step_type' => $step_type,
            'title' => sprintf(__('Configure %s Handler', 'data-machine'), $handlers[$handler_slug]['label'] ?? ucfirst($handler_slug))
        ]);
    }

    /**
     * Save pipeline (create new or update existing)
     */
    private function save_pipeline()
    {
        $pipeline_id = sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        $pipeline_name = sanitize_text_field(wp_unslash($_POST['pipeline_name'] ?? ''));
        $step_configuration_raw = wp_unslash($_POST['step_configuration'] ?? '[]');
        
        // Validate required fields
        if (empty($pipeline_name)) {
            wp_send_json_error(['message' => __('Pipeline name is required', 'data-machine')]);
        }
        
        // Parse and validate step configuration
        $step_configuration = json_decode($step_configuration_raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => __('Invalid step configuration format', 'data-machine')]);
        }
        
        // Validate steps if provided
        if (!empty($step_configuration)) {
            foreach ($step_configuration as $step) {
                if (empty($step['step_type'])) {
                    wp_send_json_error(['message' => __('All steps must have a step type', 'data-machine')]);
                }
                
                // Validate step type exists using filter system
                $step_config = apply_filters('dm_get_steps', null, $step['step_type']);
                if (!$step_config) {
                    wp_send_json_error(['message' => sprintf(__('Invalid step type: %s', 'data-machine'), $step['step_type'])]);
                }
            }
        }
        
        // Get database service
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        if (!$db_pipelines) {
            $logger = apply_filters('dm_get_logger', null);
            $logger && $logger->error('Database service unavailable in save_pipeline');
            wp_send_json_error(['message' => __('Database service unavailable', 'data-machine')]);
        }
        
        // Prepare pipeline data
        $pipeline_data = [
            'pipeline_name' => $pipeline_name,
            'step_configuration' => $step_configuration
        ];
        
        $is_new_pipeline = ($pipeline_id === 'new' || empty($pipeline_id));
        
        if ($is_new_pipeline) {
            // Create new pipeline
            $new_pipeline_id = $db_pipelines->create_pipeline($pipeline_data);
            
            if (!$new_pipeline_id) {
                wp_send_json_error(['message' => __('Failed to create pipeline', 'data-machine')]);
            }
            
            wp_send_json_success([
                'message' => sprintf(__('Pipeline "%s" created successfully', 'data-machine'), $pipeline_name),
                'pipeline_id' => $new_pipeline_id,
                'pipeline_name' => $pipeline_name,
                'is_new' => true,
                'step_count' => count($step_configuration)
            ]);
            
        } else {
            // Update existing pipeline
            $success = $db_pipelines->update_pipeline((int)$pipeline_id, $pipeline_data);
            
            if (!$success) {
                wp_send_json_error(['message' => __('Failed to update pipeline', 'data-machine')]);
            }
            
            wp_send_json_success([
                'message' => sprintf(__('Pipeline "%s" updated successfully', 'data-machine'), $pipeline_name),
                'pipeline_id' => (int)$pipeline_id,
                'pipeline_name' => $pipeline_name,
                'is_new' => false,
                'step_count' => count($step_configuration)
            ]);
        }
    }
}