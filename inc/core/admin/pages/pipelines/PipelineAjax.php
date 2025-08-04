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
            
            case 'get_template':
                $this->get_template();
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
            
            case 'delete_flow':
                $this->delete_flow_from_pipeline();
                break;
            
            case 'get_flow_config':
                $this->get_flow_config();
                break;
            
            case 'configure-step-action':
                $this->configure_step_action();
                break;
                
            case 'add-location-action':
                $this->add_location_action();
                break;
                
            case 'add-handler-action':
                $this->add_handler_action();
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

    /**
     * Get rendered template with provided data
     * Dedicated endpoint for template rendering to maintain architecture consistency
     */
    private function get_template()
    {
        // Remove fallbacks - require explicit data
        if (!isset($_POST['template'])) {
            wp_send_json_error(['message' => __('Template parameter is required', 'data-machine')]);
        }
        if (!isset($_POST['template_data'])) {
            wp_send_json_error(['message' => __('Template data parameter is required', 'data-machine')]);
        }
        
        $template = sanitize_text_field(wp_unslash($_POST['template']));
        $template_data = json_decode(wp_unslash($_POST['template_data']), true);
        
        if (!is_array($template_data)) {
            wp_send_json_error(['message' => __('Invalid template data format', 'data-machine')]);
        }

        if (empty($template)) {
            wp_send_json_error(['message' => __('Template name is required', 'data-machine')]);
        }
        
        // For step-card templates in AJAX context, add sensible defaults for UI rendering
        if ($template === 'page/step-card' && !isset($template_data['is_first_step'])) {
            // AJAX-rendered steps default to showing arrows (safer for dynamic UI)
            $template_data['is_first_step'] = false;
        }

        // Use universal template rendering system
        $content = apply_filters('dm_render_template', '', $template, $template_data);
        
        if ($content) {
            wp_send_json_success([
                'html' => $content,
                'template' => $template
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(__('Template "%s" not found', 'data-machine'), $template)
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

        wp_send_json_success([
            'message' => sprintf(__('Step "%s" added successfully', 'data-machine'), $step_config['label']),
            'step_type' => $step_type,
            'step_config' => $step_config,
            'pipeline_id' => $pipeline_id,
            'position' => $next_position,
            'step_data' => $new_step
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

        // Get the created flow data
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            wp_send_json_error(['message' => __('Failed to retrieve created flow', 'data-machine')]);
        }

        wp_send_json_success([
            'message' => sprintf(__('Flow "%s" created successfully', 'data-machine'), $flow_name),
            'flow_id' => $flow_id,
            'flow_name' => $flow_name,
            'pipeline_id' => $pipeline_id,
            'flow_data' => $flow
        ]);
    }

    /**
     * Get flow step card data for template rendering
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

        // Check if this is the first flow step by counting existing steps in flows
        $db_flows = apply_filters('dm_get_database_service', null, 'flows');
        $flows = $db_flows ? $db_flows->get_flows_by_pipeline($_POST['pipeline_id'] ?? 0) : [];
        $is_first_step = empty($flows) || empty($flows[0]['flow_config'] ?? []);

        // Prepare data for template
        $template_data = [
            'step' => [
                'step_type' => $step_type,
                'step_config' => []  // Empty config for new steps
            ],
            'flow_config' => [],  // Empty flow config for new steps
            'flow_id' => $flow_id,
            'is_first_step' => $is_first_step  // Template uses this to determine arrow
        ];

        wp_send_json_success([
            'template_data' => $template_data,
            'step_type' => $step_type,
            'flow_id' => $flow_id
        ]);
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
        
        if (!isset($_POST['step_position']) || $pipeline_id <= 0) {
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
            $logger->debug("Deleted step at position '{$step_position}' from pipeline '{$pipeline_name}' (ID: {$pipeline_id}). Affected {$flow_count} flows.");
        }

        wp_send_json_success([
            'message' => sprintf(
                __('Step at position %s deleted successfully from pipeline "%s". %d flows were affected.', 'data-machine'),
                $step_position,
                $pipeline_name,
                $flow_count
            ),
            'pipeline_id' => (int)$pipeline_id,
            'step_position' => $step_position,
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

        // Create default "Draft Flow" for the new pipeline
        $db_flows = apply_filters('dm_get_database_service', null, 'flows');
        if ($db_flows) {
            $flow_data = [
                'pipeline_id' => $pipeline_id,
                'flow_name' => __('Draft Flow', 'data-machine'),
                'flow_config' => json_encode([]),
                'scheduling_config' => json_encode([
                    'status' => 'inactive',
                    'interval' => 'manual'
                ])
            ];
            
            $flow_id = $db_flows->create_flow($flow_data);
            // Continue even if flow creation fails - pipeline is more important
        }

        // Get the created pipeline data
        $pipeline = $db_pipelines->get_pipeline($pipeline_id);
        if (!$pipeline) {
            wp_send_json_error(['message' => __('Failed to retrieve created pipeline', 'data-machine')]);
        }

        // Get existing flows (should include the newly created draft flow)
        $existing_flows = $db_flows ? $db_flows->get_flows_for_pipeline($pipeline_id) : [];

        wp_send_json_success([
            'message' => __('Draft pipeline created successfully', 'data-machine'),
            'pipeline_id' => $pipeline_id,
            'pipeline_name' => $pipeline_data['pipeline_name'],
            'pipeline_data' => $pipeline,
            'existing_flows' => $existing_flows
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
            $logger->debug("Deleted pipeline '{$pipeline_name}' (ID: {$pipeline_id}) with cascade deletion of {$flow_count} flows and {$job_count} jobs.");
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

    /**
     * Delete flow from pipeline
     * Deletes the flow instance and any associated jobs
     */
    private function delete_flow_from_pipeline()
    {
        $flow_id = (int)sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));
        
        if (empty($flow_id)) {
            wp_send_json_error(['message' => __('Flow ID is required', 'data-machine')]);
        }

        // Get database services using filter-based discovery
        $db_flows = apply_filters('dm_get_database_service', null, 'flows');
        $db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
        
        if (!$db_flows || !$db_jobs) {
            $logger = apply_filters('dm_get_logger', null);
            $logger && $logger->error('Database services unavailable in delete_flow_from_pipeline');
            wp_send_json_error(['message' => __('Database services unavailable', 'data-machine')]);
        }

        // Get flow data before deletion for logging and response
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            wp_send_json_error(['message' => __('Flow not found', 'data-machine')]);
        }
        
        $flow_name = is_object($flow) ? $flow->flow_name : $flow['flow_name'];
        $pipeline_id = is_object($flow) ? $flow->pipeline_id : $flow['pipeline_id'];
        
        // Get affected jobs for cascade deletion
        $affected_jobs = $db_jobs->get_jobs_for_flow($flow_id);
        $job_count = count($affected_jobs);

        // Cascade deletion: jobs → flow
        // Delete all jobs associated with this flow first
        if (!empty($affected_jobs)) {
            foreach ($affected_jobs as $job) {
                $job_id = is_object($job) ? $job->job_id : $job['job_id'];
                $success = $db_jobs->delete_job($job_id);
                if (!$success) {
                    wp_send_json_error(['message' => sprintf(__('Failed to delete job %d during flow deletion', 'data-machine'), $job_id)]);
                }
            }
        }

        // Delete the flow itself
        $success = $db_flows->delete_flow($flow_id);
        
        if (!$success) {
            wp_send_json_error(['message' => __('Failed to delete flow', 'data-machine')]);
        }

        // Log the deletion
        $logger = apply_filters('dm_get_logger', null);
        if ($logger) {
            $logger->debug("Deleted flow '{$flow_name}' (ID: {$flow_id}) with cascade deletion of {$job_count} jobs.");
        }

        wp_send_json_success([
            'message' => sprintf(
                __('Flow "%s" deleted successfully. %d associated jobs were also deleted.', 'data-machine'),
                $flow_name,
                $job_count
            ),
            'flow_id' => $flow_id,
            'flow_name' => $flow_name,
            'pipeline_id' => $pipeline_id,
            'deleted_jobs' => $job_count
        ]);
    }

    /**
     * Get flow configuration for step card updates
     */
    private function get_flow_config()
    {
        $flow_id = (int) sanitize_text_field(wp_unslash($_POST['flow_id'] ?? ''));

        if (empty($flow_id)) {
            wp_send_json_error(['message' => __('Flow ID is required.', 'data-machine')]);
            return;
        }

        // Get database service
        $db_flows = apply_filters('dm_get_database_service', null, 'flows');
        if (!$db_flows) {
            wp_send_json_error(['message' => __('Database service unavailable.', 'data-machine')]);
            return;
        }

        // Get flow data
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            wp_send_json_error(['message' => __('Flow not found.', 'data-machine')]);
            return;
        }

        // Parse flow configuration
        $flow_config = json_decode($flow['flow_config'] ?? '{}', true) ?: [];

        wp_send_json_success([
            'flow_id' => $flow_id,
            'flow_config' => $flow_config
        ]);
    }

    /**
     * Handle step configuration save action
     */
    private function configure_step_action()
    {
        // Get context data from AJAX request - no fallbacks
        if (!isset($_POST['context'])) {
            wp_send_json_error(['message' => __('Context data is required', 'data-machine')]);
        }
        
        $context_raw = wp_unslash($_POST['context']);
        $context = json_decode($context_raw, true);
        
        if (!is_array($context)) {
            wp_send_json_error(['message' => __('Invalid context data format', 'data-machine')]);
        }
        
        // Validate required context fields
        $required_fields = ['step_type', 'pipeline_id', 'current_step', 'step_key'];
        foreach ($required_fields as $field) {
            if (!isset($context[$field]) || empty($context[$field])) {
                wp_send_json_error([
                    'message' => sprintf(__('Required field missing: %s', 'data-machine'), $field),
                    'received_context' => array_keys($context)
                ]);
            }
        }
        
        $step_type = sanitize_text_field($context['step_type']);
        $pipeline_id = sanitize_text_field($context['pipeline_id']);
        $current_step = sanitize_text_field($context['current_step']);
        $step_key = sanitize_text_field($context['step_key']);
        
        if (empty($step_type)) {
            wp_send_json_error(['message' => __('Step type is required', 'data-machine')]);
        }
        
        // Handle AI step configuration
        if ($step_type === 'ai') {
            // FAIL FAST - require proper step_key (passed from modal template)
            if (empty($step_key) || empty($pipeline_id) || empty($current_step)) {
                wp_send_json_error([
                    'message' => __('Pipeline ID, step name, and step key are required for AI configuration', 'data-machine'),
                    'missing_data' => [
                        'step_key' => empty($step_key),
                        'pipeline_id' => empty($pipeline_id),
                        'current_step' => empty($current_step)
                    ]
                ]);
            }
            
            // Validate step_key format
            $expected_step_key = "pipeline_{$pipeline_id}_step_{$current_step}";
            if ($step_key !== $expected_step_key) {
                wp_send_json_error([
                    'message' => __('Step key format invalid', 'data-machine'),
                    'expected' => $expected_step_key,
                    'received' => $step_key
                ]);
            }
            
            // Get AI HTTP Client options manager for step-aware configuration
            if (class_exists('AI_HTTP_Options_Manager')) {
                try {
                    $options_manager = new \AI_HTTP_Options_Manager('data-machine', 'llm');
                    
                    // Get form data using step-aware field names (no fallbacks)
                    $form_data = [];
                    
                    // Field names are prefixed with step key for step-aware configuration
                    $provider_field = "ai_{$step_key}_provider";
                    $api_key_field = 'ai_api_key';  // API key uses generic name
                    $model_field = "ai_{$step_key}_model";
                    $temperature_field = "ai_{$step_key}_temperature";
                    $system_prompt_field = "ai_{$step_key}_system_prompt";
                    
                    // Log received field names for debugging
                    $logger = apply_filters('dm_get_logger', null);
                    $logger?->debug('AI step configuration field mapping', [
                        'step_key' => $step_key,
                        'expected_fields' => [
                            'provider' => $provider_field,
                            'api_key' => $api_key_field,
                            'model' => $model_field,
                            'temperature' => $temperature_field
                        ],
                        'received_post_keys' => array_keys($_POST)
                    ]);
                    
                    // Extract AI configuration with proper field names
                    if (isset($_POST[$provider_field])) {
                        $form_data['provider'] = sanitize_text_field(wp_unslash($_POST[$provider_field]));
                    }
                    if (isset($_POST[$api_key_field])) {
                        $form_data['api_key'] = sanitize_text_field(wp_unslash($_POST[$api_key_field]));
                    }
                    if (isset($_POST[$model_field])) {
                        $form_data['model'] = sanitize_text_field(wp_unslash($_POST[$model_field]));
                    }
                    if (isset($_POST[$temperature_field])) {
                        $form_data['temperature'] = floatval($_POST[$temperature_field]);
                    }
                    if (isset($_POST[$system_prompt_field])) {
                        $form_data['system_prompt'] = sanitize_textarea_field(wp_unslash($_POST[$system_prompt_field]));
                    }
                    if (isset($_POST['system_prompt'])) {
                        $form_data['system_prompt'] = sanitize_textarea_field(wp_unslash($_POST['system_prompt']));
                    }
                    
                    // Save step-specific configuration
                    $success = $options_manager->save_step_configuration($step_key, $form_data);
                    
                    if ($success) {
                        wp_send_json_success([
                            'message' => __('AI step configuration saved successfully', 'data-machine'),
                            'step_key' => $step_key
                        ]);
                    } else {
                        wp_send_json_error(['message' => __('Failed to save AI step configuration', 'data-machine')]);
                    }
                    
                } catch (Exception $e) {
                    $logger = apply_filters('dm_get_logger', null);
                    if ($logger) {
                        $logger->error('AI step configuration save error: ' . $e->getMessage());
                    }
                    wp_send_json_error(['message' => __('Error saving AI configuration', 'data-machine')]);
                }
            } else {
                wp_send_json_error(['message' => __('AI HTTP Client library not available', 'data-machine')]);
            }
        } else {
            // Handle other step types in the future
            wp_send_json_error(['message' => sprintf(__('Configuration for %s steps is not yet implemented', 'data-machine'), $step_type)]);
        }
    }

    /**
     * Handle add location action for remote locations manager
     */
    private function add_location_action()
    {
        // Get context data from AJAX request
        $context_raw = wp_unslash($_POST['context'] ?? '{}');
        $context = json_decode($context_raw, true) ?: [];
        
        $handler_slug = sanitize_text_field($context['handler_slug'] ?? '');
        
        if (empty($handler_slug)) {
            wp_send_json_error(['message' => __('Handler slug is required', 'data-machine')]);
        }
        
        // Collect form data for location configuration
        $location_data = [];
        
        // Get standard location fields
        if (isset($_POST['location_name'])) {
            $location_data['location_name'] = sanitize_text_field(wp_unslash($_POST['location_name']));
        }
        if (isset($_POST['location_url'])) {
            $location_data['location_url'] = esc_url_raw(wp_unslash($_POST['location_url']));
        }
        if (isset($_POST['location_username'])) {
            $location_data['location_username'] = sanitize_text_field(wp_unslash($_POST['location_username']));
        }
        if (isset($_POST['location_password'])) {
            $location_data['location_password'] = sanitize_text_field(wp_unslash($_POST['location_password']));
        }
        
        // Validate required fields
        if (empty($location_data['location_name']) || empty($location_data['location_url'])) {
            wp_send_json_error(['message' => __('Location name and URL are required', 'data-machine')]);
        }
        
        // Get remote locations database service
        $db_remote_locations = apply_filters('dm_get_database_service', null, 'remote_locations');
        if (!$db_remote_locations) {
            wp_send_json_error(['message' => __('Remote locations database service unavailable', 'data-machine')]);
        }
        
        // Save the remote location
        $location_id = $db_remote_locations->create_location([
            'handler_slug' => $handler_slug,
            'location_name' => $location_data['location_name'],
            'location_config' => wp_json_encode($location_data)
        ]);
        
        if (!$location_id) {
            wp_send_json_error(['message' => __('Failed to save remote location', 'data-machine')]);
        }
        
        // Log the creation
        $logger = apply_filters('dm_get_logger', null);
        if ($logger) {
            $logger->debug("Created remote location '{$location_data['location_name']}' for handler '{$handler_slug}' (ID: {$location_id})");
        }
        
        wp_send_json_success([
            'message' => sprintf(__('Remote location "%s" saved successfully', 'data-machine'), $location_data['location_name']),
            'location_id' => $location_id,
            'location_name' => $location_data['location_name'],
            'handler_slug' => $handler_slug
        ]);
    }

    /**
     * Handle add handler action with proper update vs replace logic
     */
    private function add_handler_action()
    {
        // Get context data from AJAX request
        $context_raw = wp_unslash($_POST['context'] ?? '{}');
        $context = json_decode($context_raw, true) ?: [];
        
        $handler_slug = sanitize_text_field($context['handler_slug'] ?? '');
        $step_type = sanitize_text_field($context['step_type'] ?? '');
        $flow_id = (int)sanitize_text_field($context['flow_id'] ?? '');
        $pipeline_id = (int)sanitize_text_field($context['pipeline_id'] ?? '');
        
        if (empty($handler_slug) || empty($step_type)) {
            wp_send_json_error(['message' => __('Handler slug and step type are required', 'data-machine')]);
        }
        
        // Get handler configuration via filter system
        $handlers = apply_filters('dm_get_handlers', null, $step_type);
        
        if (!isset($handlers[$handler_slug])) {
            wp_send_json_error(['message' => __('Invalid handler for this step type', 'data-machine')]);
        }
        
        $handler_config = $handlers[$handler_slug];
        
        // Get settings class to process form data
        $settings_instance = apply_filters('dm_get_handler_settings', null, $handler_slug);
        $handler_settings = [];
        
        // If handler has settings, sanitize the form data
        if ($settings_instance && method_exists($settings_instance, 'sanitize')) {
            $raw_settings = [];
            
            // Extract form fields (skip WordPress and system fields)
            foreach ($_POST as $key => $value) {
                if (!in_array($key, ['action', 'pipeline_action', 'context', 'nonce', '_wp_http_referer'])) {
                    $raw_settings[$key] = $value;
                }
            }
            
            $handler_settings = $settings_instance->sanitize($raw_settings);
        }
        
        // For flow context, update or add handler to flow configuration
        if ($flow_id > 0) {
            $db_flows = apply_filters('dm_get_database_service', null, 'flows');
            if (!$db_flows) {
                wp_send_json_error(['message' => __('Database service unavailable', 'data-machine')]);
            }
            
            // Get current flow
            $flow = $db_flows->get_flow($flow_id);
            if (!$flow) {
                wp_send_json_error(['message' => __('Flow not found', 'data-machine')]);
            }
            
            // Parse current flow configuration
            $flow_config_raw = $flow['flow_config'] ?? '{}';
            $flow_config = is_string($flow_config_raw) ? json_decode($flow_config_raw, true) : $flow_config_raw;
            $flow_config = $flow_config ?: [];
            
            // Initialize step configuration if it doesn't exist
            if (!isset($flow_config['steps'])) {
                $flow_config['steps'] = [];
            }
            
            // Find or create step configuration
            $step_key = $step_type;
            if (!isset($flow_config['steps'][$step_key])) {
                $flow_config['steps'][$step_key] = [
                    'step_type' => $step_type,
                    'handlers' => []
                ];
            }
            
            // Initialize handlers array if it doesn't exist
            if (!isset($flow_config['steps'][$step_key]['handlers'])) {
                $flow_config['steps'][$step_key]['handlers'] = [];
            }
            
            // Check if handler already exists
            $handler_exists = isset($flow_config['steps'][$step_key]['handlers'][$handler_slug]);
            
            // UPDATE existing handler settings OR ADD new handler
            $flow_config['steps'][$step_key]['handlers'][$handler_slug] = [
                'handler_slug' => $handler_slug,
                'settings' => $handler_settings,
                'enabled' => true
            ];
            
            // Update flow with new configuration
            $success = $db_flows->update_flow($flow_id, [
                'flow_config' => wp_json_encode($flow_config)
            ]);
            
            if (!$success) {
                wp_send_json_error(['message' => __('Failed to save handler settings', 'data-machine')]);
            }
            
            // Log the action
            $logger = apply_filters('dm_get_logger', null);
            if ($logger) {
                $action_type = $handler_exists ? 'updated' : 'added';
                $logger->debug("Handler '{$handler_slug}' {$action_type} for step '{$step_type}' in flow {$flow_id}");
            }
            
            $action_message = $handler_exists 
                ? sprintf(__('Handler "%s" settings updated successfully', 'data-machine'), $handler_config['label'] ?? $handler_slug)
                : sprintf(__('Handler "%s" added to flow successfully', 'data-machine'), $handler_config['label'] ?? $handler_slug);
            
            wp_send_json_success([
                'message' => $action_message,
                'handler_slug' => $handler_slug,
                'step_type' => $step_type,
                'flow_id' => $flow_id,
                'handler_config' => $handler_config,
                'handler_settings' => $handler_settings,
                'action_type' => $handler_exists ? 'updated' : 'added'
            ]);
            
        } else {
            // For pipeline context (template), just confirm the handler is valid
            wp_send_json_success([
                'message' => sprintf(__('Handler "%s" configuration saved', 'data-machine'), $handler_config['label'] ?? $handler_slug),
                'handler_slug' => $handler_slug,
                'step_type' => $step_type,
                'pipeline_id' => $pipeline_id,
                'handler_config' => $handler_config,
                'handler_settings' => $handler_settings
            ]);
        }
    }
}