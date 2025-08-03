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

        // Get action from POST data - support both 'pipeline_action' and 'operation' for modal system
        $action = sanitize_text_field(wp_unslash($_POST['pipeline_action'] ?? $_POST['operation'] ?? ''));

        switch ($action) {
            case 'get_modal':
                $this->get_modal();
                break;
            // get_step_selection removed - now handled by universal modal system
            
            case 'add_step':
                $this->add_step_to_pipeline();
                break;
            
            // get_handler_selection removed - now handled by universal modal system
            
            case 'add_flow':
                $this->add_flow_to_pipeline();
                break;
            
            case 'get_flow_step_card':
                $this->get_flow_step_card();
                break;
            
            // get_handler_settings removed - now handled by universal modal system
            
            case 'save_pipeline':
                $this->save_pipeline();
                break;
            
            case 'delete_step':
                $this->delete_step_from_pipeline();
                break;
            
            case 'delete_pipeline':
                $this->delete_pipeline();
                break;
            
            case 'create_draft_pipeline':
                $this->create_draft_pipeline();
                break;
            
            case 'save_flow_schedule':
                $this->save_flow_schedule();
                break;
            
            case 'run_flow_now':
                $this->run_flow_now();
                break;
            
            default:
                wp_send_json_error(['message' => __('Invalid action', 'data-machine')]);
        }
    }

    /**
     * Get modal based on template and context
     * Routes to appropriate content generation method
     */
    private function get_modal()
    {
        $template = sanitize_text_field(wp_unslash($_POST['template'] ?? ''));
        $context = json_decode(wp_unslash($_POST['context'] ?? '{}'), true) ?: [];

        // Pure filter-based routing - zero hardcoded templates
        $content = apply_filters('dm_get_modal', null, $template);
        
        if ($content) {
            wp_send_json_success([
                'content' => $content,
                'title' => ucfirst(str_replace('-', ' ', $template))
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(__('Modal template "%s" not found', 'data-machine'), $template)
            ]);
        }
    }

    // delete-step content removed - now handled by universal modal system via template in PipelinesFilters.php

    // step-selection content removed - now handled by universal modal system via PipelinesFilters.php


    // handler-selection content removed - now handled by universal modal system via PipelinesFilters.php


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
     * Add step to pipeline
     */
    private function add_step_to_pipeline()
    {
        $step_type = sanitize_text_field(wp_unslash($_POST['step_type'] ?? ''));
        $pipeline_id = (int)sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        
        if (empty($step_type)) {
            wp_send_json_error(['message' => __('Step type is required', 'data-machine')]);
        }

        if (empty($pipeline_id)) {
            wp_send_json_error(['message' => __('Pipeline ID is required', 'data-machine')]);
        }

        // Validate step type exists
        $step_config = apply_filters('dm_get_steps', null, $step_type);
        if (!$step_config) {
            wp_send_json_error(['message' => __('Invalid step type', 'data-machine')]);
        }

        // Get database service
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        if (!$db_pipelines) {
            wp_send_json_error(['message' => __('Database service unavailable', 'data-machine')]);
        }

        // Verify pipeline exists
        $pipeline = $db_pipelines->get_pipeline($pipeline_id);
        if (!$pipeline) {
            wp_send_json_error(['message' => __('Pipeline not found', 'data-machine')]);
        }

        // Get current step configuration
        $current_steps = $db_pipelines->get_pipeline_step_configuration($pipeline_id);
        
        // Find next available position
        $next_position = 0;
        if (!empty($current_steps)) {
            $positions = array_column($current_steps, 'position');
            $next_position = empty($positions) ? 0 : max($positions) + 10;
        }

        // Add the new step
        $new_step = [
            'step_type' => $step_type,
            'position' => $next_position,
            'label' => $step_config['label'] ?? ucfirst(str_replace('_', ' ', $step_type))
        ];

        $current_steps[] = $new_step;

        // Update pipeline with new step configuration (auto-save)
        $success = $db_pipelines->update_pipeline($pipeline_id, [
            'step_configuration' => $current_steps
        ]);

        if (!$success) {
            wp_send_json_error(['message' => __('Failed to add step to pipeline', 'data-machine')]);
        }

        // Generate empty step HTML for adding at the end (replaces JavaScript HTML generation)
        $empty_step_html = $this->render_template('page/pipeline-step-card', [
            'step' => [
                'is_empty' => true,
                'step_type' => '',
                'position' => ''
            ],
            'pipeline_id' => $pipeline_id
        ]);

        wp_send_json_success([
            'message' => sprintf(__('Step "%s" added successfully', 'data-machine'), $step_config['label']),
            'step_type' => $step_type,
            'step_config' => $step_config,
            'pipeline_id' => $pipeline_id,
            'position' => $next_position,
            'step_html' => $this->render_template('page/pipeline-step-card', [
                'step' => $new_step,
                'pipeline_id' => $pipeline_id
            ]),
            'empty_step_html' => $empty_step_html
        ]);
    }

    /**
     * Add flow to pipeline
     */
    private function add_flow_to_pipeline()
    {
        $pipeline_id = (int)sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        
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
        $pipeline = $db_pipelines->get_pipeline($pipeline_id);
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

        // Get the created flow for template rendering
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            wp_send_json_error(['message' => __('Failed to retrieve created flow', 'data-machine')]);
        }

        // Render the flow instance card template
        $flow_card_html = $this->render_template('page/flow-instance-card', ['flow' => $flow]);

        wp_send_json_success([
            'message' => sprintf(__('Flow "%s" created successfully', 'data-machine'), $flow_name),
            'flow_id' => $flow_id,
            'flow_name' => $flow_name,
            'pipeline_id' => $pipeline_id,
            'flow_card_html' => $flow_card_html
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
        $html = $this->render_template('page/flow-step-card', $template_data);

        wp_send_json_success([
            'html' => $html,
            'step_type' => $step_type,
            'flow_id' => $flow_id
        ]);
    }

    /**
     * Render template with data (strict subdirectory structure only)
     */
    private function render_template($template_name, $data = [])
    {
        // Enforce strict organized subdirectory structure: 'modal/template-name' or 'page/template-name'
        $template_path = __DIR__ . '/templates/' . $template_name . '.php';
        
        // No fallbacks - template must exist in organized structure
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


    // handler-settings content removed - now handled by universal modal system via PipelinesFilters.php

    /**
     * Save pipeline (create new or update existing)
     */
    private function save_pipeline()
    {
        $pipeline_id = (int)sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
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
        
        // Handle both create and update cases based on pipeline_id
        if (empty($pipeline_id)) {
            // Create new pipeline (when Add New Pipeline form is saved)
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
            $success = $db_pipelines->update_pipeline($pipeline_id, $pipeline_data);
            
            if (!$success) {
                wp_send_json_error(['message' => __('Failed to update pipeline', 'data-machine')]);
            }
            
            wp_send_json_success([
                'message' => sprintf(__('Pipeline "%s" updated successfully', 'data-machine'), $pipeline_name),
                'pipeline_id' => $pipeline_id,
                'pipeline_name' => $pipeline_name,
                'is_new' => false,
                'step_count' => count($step_configuration)
            ]);
        }
    }

    /**
     * Delete step from pipeline with cascade handling
     */
    private function delete_step_from_pipeline()
    {
        $step_position = sanitize_text_field(wp_unslash($_POST['step_position'] ?? ''));
        $pipeline_id = (int)sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        
        if (empty($step_position) || empty($pipeline_id)) {
            wp_send_json_error(['message' => __('Step position and pipeline ID are required', 'data-machine')]);
        }

        // Get database services using filter-based discovery
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        $db_flows = apply_filters('dm_get_database_service', null, 'flows');
        
        if (!$db_pipelines || !$db_flows) {
            $logger = apply_filters('dm_get_logger', null);
            $logger && $logger->error('Database services unavailable in delete_step_from_pipeline');
            wp_send_json_error(['message' => __('Database services unavailable', 'data-machine')]);
        }

        // Get current pipeline configuration
        $pipeline = $db_pipelines->get_pipeline($pipeline_id);
        if (!$pipeline) {
            wp_send_json_error(['message' => __('Pipeline not found', 'data-machine')]);
        }

        $pipeline_name = is_object($pipeline) ? $pipeline->pipeline_name : $pipeline['pipeline_name'];
        $current_steps = $db_pipelines->get_pipeline_step_configuration($pipeline_id);
        
        if (empty($current_steps)) {
            wp_send_json_error(['message' => __('No steps found in pipeline', 'data-machine')]);
        }

        // Find and remove the step by position
        $updated_steps = [];
        $step_found = false;
        
        foreach ($current_steps as $step) {
            if (($step['position'] ?? '') != $step_position) {
                $updated_steps[] = $step;
            } else {
                $step_found = true;
            }
        }
        
        if (!$step_found) {
            wp_send_json_error(['message' => sprintf(__('Step at position %s not found in pipeline', 'data-machine'), $step_position)]);
        }

        // Update pipeline with remaining steps
        $pipeline_data = [
            'pipeline_name' => $pipeline_name,
            'step_configuration' => $updated_steps
        ];
        
        $success = $db_pipelines->update_pipeline((int)$pipeline_id, $pipeline_data);
        
        if (!$success) {
            wp_send_json_error(['message' => __('Failed to update pipeline', 'data-machine')]);
        }

        // Get affected flows for reporting
        $affected_flows = $db_flows->get_flows_for_pipeline($pipeline_id);
        $flow_count = count($affected_flows);
        
        // Log the deletion
        $logger = apply_filters('dm_get_logger', null);
        if ($logger) {
            $logger->info("Deleted step '{$step_type}' from pipeline '{$pipeline_name}' (ID: {$pipeline_id}). Affected {$flow_count} flows.");
        }

        wp_send_json_success([
            'message' => sprintf(
                __('Step "%s" deleted successfully from pipeline "%s". %d flows were affected.', 'data-machine'),
                ucfirst(str_replace('_', ' ', $step_type)),
                $pipeline_name,
                $flow_count
            ),
            'pipeline_id' => (int)$pipeline_id,
            'step_type' => $step_type,
            'affected_flows' => $flow_count,
            'remaining_steps' => count($updated_steps)
        ]);
    }

    /**
     * Create a new draft pipeline in the database
     */
    private function create_draft_pipeline()
    {
        // Get database service
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        if (!$db_pipelines) {
            wp_send_json_error(['message' => __('Database service unavailable', 'data-machine')]);
        }

        // Create draft pipeline with default name
        $pipeline_data = [
            'pipeline_name' => __('Draft Pipeline', 'data-machine'),
            'step_configuration' => []
        ];

        $pipeline_id = $db_pipelines->create_pipeline($pipeline_data);
        
        if (!$pipeline_id) {
            wp_send_json_error(['message' => __('Failed to create draft pipeline', 'data-machine')]);
        }

        // Get the created pipeline for template rendering
        $pipeline = $db_pipelines->get_pipeline($pipeline_id);
        if (!$pipeline) {
            wp_send_json_error(['message' => __('Failed to retrieve created pipeline', 'data-machine')]);
        }

        // Render the pipeline card template
        $pipeline_card_html = $this->render_template('page/pipeline-card', ['pipeline' => $pipeline]);

        wp_send_json_success([
            'message' => __('Draft pipeline created successfully', 'data-machine'),
            'pipeline_id' => $pipeline_id,
            'pipeline_name' => $pipeline_data['pipeline_name'],
            'pipeline_card_html' => $pipeline_card_html
        ]);
    }

    /**
     * Save flow schedule configuration
     */
    private function save_flow_schedule()
    {
        $flow_id = (int)sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));
        $schedule_status = sanitize_text_field(wp_unslash($_POST['schedule_status'] ?? 'inactive'));
        $schedule_interval = sanitize_text_field(wp_unslash($_POST['schedule_interval'] ?? 'manual'));

        if (empty($flow_id)) {
            wp_send_json_error(['message' => __('Flow ID is required', 'data-machine')]);
        }

        // Get database service
        $db_flows = apply_filters('dm_get_database_service', null, 'flows');
        if (!$db_flows) {
            wp_send_json_error(['message' => __('Database service unavailable', 'data-machine')]);
        }

        // Get existing flow
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            wp_send_json_error(['message' => __('Flow not found', 'data-machine')]);
        }

        // Parse existing scheduling config
        $scheduling_config = json_decode($flow['scheduling_config'] ?? '{}', true);
        $old_status = $scheduling_config['status'] ?? 'inactive';

        // Update scheduling config
        $scheduling_config['status'] = $schedule_status;
        $scheduling_config['interval'] = $schedule_interval;

        // Update database
        $result = $db_flows->update_flow($flow_id, [
            'scheduling_config' => wp_json_encode($scheduling_config)
        ]);

        if (!$result) {
            wp_send_json_error(['message' => __('Failed to save schedule configuration', 'data-machine')]);
        }

        // Handle Action Scheduler scheduling
        $scheduler = apply_filters('dm_get_scheduler', null);
        if ($scheduler) {
            if ($schedule_status === 'active' && $schedule_interval !== 'manual') {
                // Activate scheduling
                $scheduler->activate_flow($flow_id);
            } elseif ($old_status === 'active') {
                // Deactivate scheduling if it was previously active
                $scheduler->deactivate_flow($flow_id);
            }
        }

        wp_send_json_success([
            'message' => sprintf(__('Schedule saved successfully. Flow is now %s.', 'data-machine'), $schedule_status),
            'flow_id' => $flow_id,
            'schedule_status' => $schedule_status,
            'schedule_interval' => $schedule_interval
        ]);
    }

    /**
     * Run flow immediately
     */
    private function run_flow_now()
    {
        $flow_id = (int)sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));

        if (empty($flow_id)) {
            wp_send_json_error(['message' => __('Flow ID is required', 'data-machine')]);
        }

        // Get database service
        $db_flows = apply_filters('dm_get_database_service', null, 'flows');
        if (!$db_flows) {
            wp_send_json_error(['message' => __('Database service unavailable', 'data-machine')]);
        }

        // Get flow data
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            wp_send_json_error(['message' => __('Flow not found', 'data-machine')]);
        }

        // Use existing JobCreator to create and schedule job
        $job_creator = apply_filters('dm_get_job_creator', null);
        if (!$job_creator) {
            wp_send_json_error(['message' => __('Job creator service unavailable', 'data-machine')]);
        }

        $result = $job_creator->create_and_schedule_job(
            (int)$flow['pipeline_id'],
            $flow_id,
            (int)$flow['user_id'],
            'run_now'
        );

        if ($result['success'] ?? false) {
            wp_send_json_success([
                'message' => sprintf(__('Flow "%s" started successfully', 'data-machine'), $flow['flow_name']),
                'job_id' => $result['job_id'] ?? null
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message'] ?? __('Failed to start flow', 'data-machine')
            ]);
        }
    }

    /**
     * Delete pipeline with cascade deletion logic
     * Deletes flows first, then jobs, then the pipeline itself
     */
    private function delete_pipeline()
    {
        $pipeline_id = (int)sanitize_text_field(wp_unslash($_POST['pipeline_id'] ?? ''));
        
        if (empty($pipeline_id)) {
            wp_send_json_error(['message' => __('Pipeline ID is required', 'data-machine')]);
        }

        // Get database services using filter-based discovery
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        $db_flows = apply_filters('dm_get_database_service', null, 'flows');
        $db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
        
        if (!$db_pipelines || !$db_flows || !$db_jobs) {
            $logger = apply_filters('dm_get_logger', null);
            $logger && $logger->error('Database services unavailable in delete_pipeline');
            wp_send_json_error(['message' => __('Database services unavailable', 'data-machine')]);
        }

        // Get pipeline data before deletion for logging
        $pipeline = $db_pipelines->get_pipeline($pipeline_id);
        if (!$pipeline) {
            wp_send_json_error(['message' => __('Pipeline not found', 'data-machine')]);
        }
        
        $pipeline_name = is_object($pipeline) ? $pipeline->pipeline_name : $pipeline['pipeline_name'];
        
        // Get affected flows and jobs for cascade deletion and reporting
        $affected_flows = $db_flows->get_flows_for_pipeline($pipeline_id);
        $affected_jobs = $db_jobs->get_jobs_for_pipeline($pipeline_id);
        
        $flow_count = count($affected_flows);
        $job_count = count($affected_jobs);

        // Cascade deletion: jobs → flows → pipeline
        // Delete all jobs associated with this pipeline first
        if (!empty($affected_jobs)) {
            foreach ($affected_jobs as $job) {
                $job_id = is_object($job) ? $job->job_id : $job['job_id'];
                $success = $db_jobs->delete_job($job_id);
                if (!$success) {
                    wp_send_json_error(['message' => sprintf(__('Failed to delete job %d during cascade deletion', 'data-machine'), $job_id)]);
                }
            }
        }

        // Delete all flows associated with this pipeline
        if (!empty($affected_flows)) {
            foreach ($affected_flows as $flow) {
                $flow_id = is_object($flow) ? $flow->flow_id : $flow['flow_id'];
                $success = $db_flows->delete_flow($flow_id);
                if (!$success) {
                    wp_send_json_error(['message' => sprintf(__('Failed to delete flow %d during cascade deletion', 'data-machine'), $flow_id)]);
                }
            }
        }

        // Finally, delete the pipeline itself
        $success = $db_pipelines->delete_pipeline($pipeline_id);
        
        if (!$success) {
            wp_send_json_error(['message' => __('Failed to delete pipeline', 'data-machine')]);
        }

        // Log the deletion
        $logger = apply_filters('dm_get_logger', null);
        if ($logger) {
            $logger->info("Deleted pipeline '{$pipeline_name}' (ID: {$pipeline_id}) with cascade deletion of {$flow_count} flows and {$job_count} jobs.");
        }

        wp_send_json_success([
            'message' => sprintf(
                __('Pipeline "%s" deleted successfully. %d flows and %d jobs were also deleted.', 'data-machine'),
                $pipeline_name,
                $flow_count,
                $job_count
            ),
            'pipeline_id' => $pipeline_id,
            'pipeline_name' => $pipeline_name,
            'deleted_flows' => $flow_count,
            'deleted_jobs' => $job_count
        ]);
    }
}