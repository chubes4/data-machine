<?php
/**
 * Pipeline+Flow job creation class for execution orchestration.
 *
 * Creates and schedules jobs using the two-layer Pipeline+Flow architecture:
 * - Pipelines: Reusable workflow templates with step sequences
 * - Flows: Configured instances with handler settings and scheduling
 *
 * Validates pipeline steps against universal handler system and dm_get_steps
 * filter registry. Fully integrated with ProcessingOrchestrator for seamless execution.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/engine
 * @since      0.15.0
 */

namespace DataMachine\Engine;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class JobCreator {

    /**
     * Constructor - parameter-less for pure filter-based architecture.
     * Services accessed via ultra-direct filters.
     */
    public function __construct() {
        // All services accessed via filters - no constructor dependencies
    }

    /**
     * Create and schedule a job for the async pipeline+flow architecture.
     *
     * This is the single entry point for all job creation using pipeline+flow-based architecture.
     * Always schedules dm_execute_step with position 0 to start the async pipeline.
     *
     * @param int         $pipeline_id Pipeline ID defining the workflow steps.
     * @param int         $flow_id Flow ID with configured handlers and scheduling.
     * @param string      $context Context describing job source ('run_now', 'file_upload', 'single_module', 'scheduled').
     * @param array|null  $optional_data Input data for file-based jobs.
     * @return array Result array with 'success' boolean and 'message' string.
     */
    public function create_and_schedule_job( int $pipeline_id, int $flow_id, string $context, ?array $optional_data = null ): array {
        try {
            // Validate required parameters
            if ( empty( $pipeline_id ) || empty( $flow_id ) ) {
                return [
                    'success' => false,
                    'message' => 'Invalid pipeline ID or flow ID'
                ];
            }

            // Validate pipeline and flow configuration
            $validation_result = $this->validate_pipeline_and_flow( $pipeline_id, $flow_id );
            if ( is_wp_error( $validation_result ) ) {
                return [
                    'success' => false,
                    'message' => $validation_result->get_error_message()
                ];
            }

            // Action Scheduler handles global concurrency control (MAX_CONCURRENT_JOBS = 2)

            // Build job config for database storage
            $job_config = $this->build_pipeline_flow_job_config( $pipeline_id, $flow_id, $context, $optional_data );
            if ( is_wp_error( $job_config ) ) {
                return [
                    'success' => false,
                    'message' => $job_config->get_error_message()
                ];
            }

            // Create job record in database using pipeline+flow-based format
            $job_data = [
                'pipeline_id' => $pipeline_id,
                'flow_id' => $flow_id,
                'flow_config' => wp_json_encode( $job_config )
            ];
            $all_databases = apply_filters('dm_get_database_services', []);
            $db_jobs = $all_databases['jobs'] ?? null;
            $job_id = $db_jobs->create_job( $job_data );

            if ( ! $job_id ) {
                $logger = apply_filters('dm_get_logger', null);
                $logger->error( 'Failed to create job record', [
                    'pipeline_id' => $pipeline_id,
                    'flow_id' => $flow_id,
                    'context' => $context
                ] );
                return [
                    'success' => false,
                    'message' => 'Failed to create job record'
                ];
            }

            // Schedule Step 1: Pipeline Processing (starts async pipeline at position 0)
            $scheduler = apply_filters('dm_get_action_scheduler', null);
            if (!$scheduler) {
                $logger = apply_filters('dm_get_logger', null);
                $logger->error('ActionScheduler service not available');
                return [
                    'success' => false,
                    'message' => 'ActionScheduler service not available'
                ];
            }
            
            $action_id = $scheduler->schedule_single_action(
                time() + 1, // Start immediately
                'dm_execute_step',
                [
                    'job_id' => $job_id,
                    'step_position' => 0,
                    'pipeline_id' => $pipeline_id,
                    'flow_id' => $flow_id,
                    'pipeline_config' => $job_config,
                    'previous_data_packets' => [] // First step has no previous data
                ],
                \DataMachine\Engine\Constants::ACTION_GROUP
            );

            if ( $action_id === false || $action_id === 0 ) {
                $logger = apply_filters('dm_get_logger', null);
                $logger->error( 'Failed to schedule pipeline+flow job', [
                    'job_id' => $job_id,
                    'pipeline_id' => $pipeline_id,
                    'flow_id' => $flow_id,
                    'action_id' => $action_id
                ] );
                
                // Mark job as failed since scheduling failed
                $all_databases = apply_filters('dm_get_database_services', []);
                $db_jobs = $all_databases['jobs'] ?? null;
                $db_jobs->complete_job( $job_id, 'failed', wp_json_encode( [
                    'error' => 'Failed to schedule pipeline processing step'
                ] ) );
                
                return [
                    'success' => false,
                    'message' => 'Failed to schedule job processing'
                ];
            }

            $logger = apply_filters('dm_get_logger', null);
            $logger->debug( 'Job created and scheduled successfully', [
                'job_id' => $job_id,
                'pipeline_id' => $pipeline_id,
                'flow_id' => $flow_id,
                'context' => $context,
                'action_id' => $action_id
            ] );

            return [
                'success' => true,
                'message' => 'Job scheduled successfully',
                'job_id' => $job_id,
                'action_id' => $action_id
            ];

        } catch ( Exception $e ) {
            $logger = apply_filters('dm_get_logger', null);
            $logger->error( 'Exception in job creation', [
                'pipeline_id' => $pipeline_id,
                'flow_id' => $flow_id,
                'context' => $context,
                'error' => $e->getMessage()
            ] );
            
            return [
                'success' => false,
                'message' => 'Job creation failed due to exception'
            ];
        }
    }

    /**
     * Validate pipeline and flow configuration against universal handler system.
     *
     * @param int $pipeline_id Pipeline ID to validate.
     * @param int $flow_id Flow ID to validate.
     * @return true|WP_Error True if valid, WP_Error if validation fails.
     */
    private function validate_pipeline_and_flow( int $pipeline_id, int $flow_id ) {
        // Get pipeline data
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        $pipeline = $db_pipelines->get_pipeline( $pipeline_id );
        if ( ! $pipeline ) {
            return new \WP_Error( 'pipeline_not_found', 'Pipeline not found in database' );
        }

        // Get flow data
        $db_flows = $all_databases['flows'] ?? null;
        $flow = $db_flows->get_flow( $flow_id );
        if ( ! $flow ) {
            return new \WP_Error( 'flow_not_found', 'Flow not found in database' );
        }

        // Validate flow belongs to pipeline
        if ( (int)$flow['pipeline_id'] !== (int)$pipeline_id ) {
            return new \WP_Error( 'flow_pipeline_mismatch', 'Flow does not belong to the specified pipeline' );
        }

        // Basic pipeline configuration validation
        $pipeline_config = json_decode( $pipeline->step_configuration ?? '[]', true );
        if ( ! is_array( $pipeline_config ) ) {
            return new \WP_Error( 'invalid_pipeline_config', 'Invalid pipeline configuration' );
        }

        if ( empty( $pipeline_config ) ) {
            return new \WP_Error( 'empty_pipeline', 'Pipeline has no steps defined' );
        }


        return true;
    }

    /**
     * Build pipeline+flow-based job configuration for execution.
     *
     * @param int    $pipeline_id Pipeline ID defining the workflow steps.
     * @param int    $flow_id Flow ID with configured handlers and settings.
     * @param string $context Job context.
     * @param array|null $optional_data Input data for file-based jobs.
     * @return array|WP_Error Job config array or error.
     */
    private function build_pipeline_flow_job_config( int $pipeline_id, int $flow_id, string $context, ?array $optional_data = null ) {
        // Get pipeline data
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        $pipeline = $db_pipelines->get_pipeline( $pipeline_id );
        if ( ! $pipeline ) {
            return new \WP_Error( 'pipeline_not_found', 'Pipeline not found in database' );
        }

        // Get flow data
        $db_flows = $all_databases['flows'] ?? null;
        $flow = $db_flows->get_flow( $flow_id );
        if ( ! $flow ) {
            return new \WP_Error( 'flow_not_found', 'Flow not found in database' );
        }

        // Parse pipeline step configuration
        $pipeline_step_config = json_decode( $pipeline->step_configuration ?? '[]', true );
        if ( ! is_array( $pipeline_step_config ) ) {
            $pipeline_step_config = [];
        }

        // Filter out empty/placeholder steps before execution
        $pipeline_step_config = array_filter($pipeline_step_config, function($step) {
            $step_type = $step['type'] ?? $step['step_type'] ?? '';
            return !empty(trim($step_type));
        });

        // Validate we have executable steps after filtering
        if ( empty( $pipeline_step_config ) ) {
            return new \WP_Error( 'no_executable_steps', 'Pipeline has no executable steps after filtering empty placeholders' );
        }

        // Sort pipeline steps by position for position-based execution
        usort( $pipeline_step_config, function( $a, $b ) {
            return ( $a['position'] ?? 0 ) <=> ( $b['position'] ?? 0 );
        });

        // Build pipeline+flow-based job configuration
        $job_config = [
            'pipeline_id' => $pipeline_id,
            'flow_id' => $flow_id,
            'context' => $context,
            'pipeline_name' => $pipeline->pipeline_name ?? 'Unknown Pipeline',
            'flow_name' => $flow['flow_name'] ?? 'Unknown Flow',
            'pipeline_step_config' => $pipeline_step_config,
            'flow_config' => $flow['flow_config'] ?? [],
            'created_at' => current_time( 'mysql' )
        ];

        // Add optional data if provided (for file uploads, etc.)
        if ( $optional_data ) {
            $job_config['optional_data'] = $optional_data;
        }

        // Log pipeline+flow configuration for debugging
        $logger = apply_filters('dm_get_logger', null);
        if ( $logger ) {
            $logger->debug( 'Built pipeline+flow job configuration', [
                'pipeline_id' => $pipeline_id,
                'flow_id' => $flow_id,
                'context' => $context,
                'pipeline_steps_count' => count( $pipeline_step_config ),
                'has_optional_data' => ! empty( $optional_data )
            ] );
        }

        return $job_config;
    }
}