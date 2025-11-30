<?php
/**
 * Unified execution endpoint for database flows and ephemeral workflows.
 *
 * @package DataMachine\Api
 */

namespace DataMachine\Api;

if (!defined('ABSPATH')) {
    exit;
}

class Execute {

    /**
     * Initialize REST API hooks
     */
    public static function register() {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    /**
     * Register execute REST route
     */
    public static function register_routes() {
        register_rest_route('datamachine/v1', '/execute', [
            'methods' => 'POST',
            'callback' => [self::class, 'handle_execute'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
            'args' => [
                'flow_id' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => 'Database flow ID to execute'
                ],
                'workflow' => [
                    'type' => 'object',
                    'required' => false,
                    'description' => 'Ephemeral workflow structure'
                ],
                'timestamp' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => 'Unix timestamp for delayed execution'
                ]
            ]
        ]);
    }

    /**
     * Handle execute endpoint requests
     *
     * Pure execution endpoint - handles immediate and delayed execution only.
     * For scheduling/recurring execution, use the /schedule endpoint.
     */
    public static function handle_execute($request) {
        $flow_id = $request->get_param('flow_id');
        $workflow = $request->get_param('workflow');
        $timestamp = $request->get_param('timestamp');

        // Validate: must have flow_id OR workflow
        if (!$flow_id && !$workflow) {
            return new \WP_Error(
                'missing_params',
                'Must provide either flow_id or workflow',
                ['status' => 400]
            );
        }

        if ($flow_id && $workflow) {
            return new \WP_Error(
                'conflicting_params',
                'Cannot provide both flow_id and workflow',
                ['status' => 400]
            );
        }

        // Database flow execution
        if ($flow_id) {
            return self::execute_database_flow($flow_id, $timestamp);
        }

        // Ephemeral workflow execution
        return self::execute_ephemeral_workflow($workflow, $timestamp);
    }

    /**
     * Execute database flow immediately or with delay
     */
    private static function execute_database_flow($flow_id, $timestamp) {
        // Validate flow exists
        $db_flows = new \DataMachine\Core\Database\Flows\Flows();
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            return new \WP_Error(
                'flow_not_found',
                "Flow {$flow_id} not found",
                ['status' => 404]
            );
        }

        // Create job upfront for immediate visibility
        $job_manager = new \DataMachine\Services\JobManager();
        $pipeline_id = (int) $flow['pipeline_id'];
        $job_id = $job_manager->create($flow_id, $pipeline_id);

        if (!$job_id) {
            return new \WP_Error(
                'job_creation_failed',
                'Failed to create job record',
                ['status' => 500]
            );
        }

        // Immediate execution via Action Scheduler
        if (!$timestamp) {
            $action_id = as_schedule_single_action(
                time(),
                'datamachine_run_flow_now',
                [$flow_id, $job_id],
                'datamachine'
            );

            return rest_ensure_response([
                'success' => true,
                'data' => [
                    'execution_type' => 'immediate',
                    'flow_id' => $flow_id,
                    'flow_name' => $flow['flow_name'] ?? "Flow {$flow_id}",
                    'job_id' => $job_id,
                    'action_id' => $action_id
                ],
                'message' => 'Flow execution queued'
            ]);
        }

        // Delayed execution (one-time)
        if (!function_exists('as_schedule_single_action')) {
            return new \WP_Error(
                'scheduler_unavailable',
                'Action Scheduler not available for delayed execution',
                ['status' => 500]
            );
        }

        $action_id = as_schedule_single_action(
            $timestamp,
            'datamachine_run_flow_now',
            [$flow_id, $job_id],
            'datamachine'
        );

        return rest_ensure_response([
            'success' => true,
            'data' => [
                'execution_type' => 'delayed',
                'flow_id' => $flow_id,
                'flow_name' => $flow['flow_name'] ?? "Flow {$flow_id}",
                'job_id' => $job_id,
                'timestamp' => $timestamp,
                'scheduled_time' => wp_date('c', $timestamp)
            ],
            'message' => 'Flow scheduled for one-time execution at ' . wp_date('M j, Y g:i A', $timestamp)
        ]);
    }

    /**
     * Execute ephemeral workflow with optional delayed execution
     */
    private static function execute_ephemeral_workflow($workflow, $timestamp) {

        // Validate workflow structure
        $validation = self::validate_workflow($workflow);
        if (!$validation['valid']) {
            return new \WP_Error(
                'invalid_workflow',
                $validation['error'],
                ['status' => 400]
            );
        }

        // Build configs from workflow
        $configs = self::build_configs_from_workflow($workflow);

        // Get database service
        $db_jobs = new \DataMachine\Core\Database\Jobs\Jobs();

        // Create job record
        $job_id = $db_jobs->create_job([
            'pipeline_id' => null,
            'flow_id' => null
        ]);

        if (!$job_id) {
            return new \WP_Error(
                'job_creation_failed',
                'Failed to create job record',
                ['status' => 500]
            );
        }

        // Store configs in engine_data
        $db_jobs->store_engine_data($job_id, [
            'flow_config' => $configs['flow_config'],
            'pipeline_config' => $configs['pipeline_config']
        ]);

        // Find first step
        $first_step_id = self::get_first_step_id($configs['flow_config']);

        if (!$first_step_id) {
            return new \WP_Error(
                'workflow_error',
                'Could not determine first step in workflow',
                ['status' => 500]
            );
        }

        // Immediate execution
        if (!$timestamp) {
            do_action('datamachine_schedule_next_step', $job_id, $first_step_id, []);

            return rest_ensure_response([
                'success' => true,
                'data' => [
                    'execution_type' => 'immediate',
                    'job_id' => $job_id,
                    'step_count' => count($workflow['steps'] ?? [])
                ],
                'message' => 'Ephemeral workflow execution started'
            ]);
        }

        // Delayed execution
        if (function_exists('as_schedule_single_action')) {
            $action_id = as_schedule_single_action(
                $timestamp,
                'datamachine_schedule_next_step',
                [$job_id, $first_step_id, []],
                'datamachine'
            );

            if ($action_id === false) {
                return new \WP_Error(
                    'scheduling_failed',
                    'Failed to schedule workflow execution',
                    ['status' => 500]
                );
            }

            return rest_ensure_response([
                'success' => true,
                'data' => [
                    'execution_type' => 'delayed',
                    'job_id' => $job_id,
                    'step_count' => count($workflow['steps'] ?? []),
                    'timestamp' => $timestamp,
                    'scheduled_time' => wp_date('c', $timestamp)
                ],
                'message' => 'Ephemeral workflow scheduled for one-time execution at ' . wp_date('M j, Y g:i A', $timestamp)
            ]);
        }

        return new \WP_Error(
            'scheduler_unavailable',
            'Action Scheduler not available for delayed execution',
            ['status' => 500]
        );
    }

    /**
     * Validate workflow structure
     */
    private static function validate_workflow($workflow) {
        if (!isset($workflow['steps']) || !is_array($workflow['steps'])) {
            return ['valid' => false, 'error' => 'Workflow must contain steps array'];
        }

        if (empty($workflow['steps'])) {
            return ['valid' => false, 'error' => 'Workflow must have at least one step'];
        }

        $step_types = apply_filters('datamachine_step_types', []);
        $valid_types = array_keys($step_types);

        foreach ($workflow['steps'] as $index => $step) {
            if (!isset($step['type'])) {
                return ['valid' => false, 'error' => "Step {$index} missing type"];
            }

            if (!in_array($step['type'], $valid_types, true)) {
                return ['valid' => false, 'error' => "Step {$index} has invalid type: {$step['type']}. Valid types: " . implode(', ', $valid_types)];
            }

            if ($step['type'] !== 'ai' && !isset($step['handler_slug'])) {
                return ['valid' => false, 'error' => "Step {$index} missing handler_slug (required for non-AI steps)"];
            }
        }

        return ['valid' => true];
    }

    /**
     * Build flow_config and pipeline_config from workflow structure
     */
    private static function build_configs_from_workflow($workflow) {
        $flow_config = [];
        $pipeline_config = [];

        foreach ($workflow['steps'] as $index => $step) {
            $step_id = "ephemeral_step_{$index}";
            $pipeline_step_id = "ephemeral_pipeline_{$index}";

            // Flow config (instance-specific)
            $flow_config[$step_id] = [
                'flow_step_id' => $step_id,
                'pipeline_step_id' => $pipeline_step_id,
                'step_type' => $step['type'],
                'execution_order' => $index,
                'handler_slug' => $step['handler_slug'] ?? '',
                'handler_config' => $step['config'] ?? [],
                'user_message' => $step['user_message'] ?? '',
                'enabled_tools' => $step['enabled_tools'] ?? [],
                'pipeline_id' => 0,  // Sentinel value for ephemeral workflows
                'flow_id' => 0       // Sentinel value for ephemeral workflows
            ];

            // Pipeline config (AI settings only)
            if ($step['type'] === 'ai') {
                $pipeline_config[$pipeline_step_id] = [
                    'provider' => $step['provider'] ?? '',
                    'model' => $step['model'] ?? '',
                    'system_prompt' => $step['system_prompt'] ?? '',
                    'enabled_tools' => $step['enabled_tools'] ?? []
                ];
            }
        }

        return [
            'flow_config' => $flow_config,
            'pipeline_config' => $pipeline_config
        ];
    }

    /**
     * Get first step ID from flow_config
     */
    private static function get_first_step_id($flow_config) {
        foreach ($flow_config as $step_id => $config) {
            if (($config['execution_order'] ?? -1) === 0) {
                return $step_id;
            }
        }
        return null;
    }
}
