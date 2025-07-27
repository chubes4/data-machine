<?php
/**
 * Unified job creation class that handles all job creation entry points.
 *
 * Consolidates job creation logic from multiple sources into a single,
 * consistent implementation that always uses the async pipeline.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/engine
 * @since      0.6.0
 */

namespace DataMachine\Engine;

use DataMachine\Database\{Jobs, Modules, Projects};
use DataMachine\Helpers\Logger;

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
     * Create and schedule a job for the async pipeline.
     *
     * This is the single entry point for all job creation, regardless of source.
     * Always schedules dm_step_position_0_job_event to start the async pipeline.
     *
     * @param array       $module Module configuration array.
     * @param int         $user_id User ID creating the job.
     * @param string      $context Context describing job source ('run_now', 'file_upload', 'single_module', 'scheduled').
     * @param array|null  $optional_data Optional input data for file-based jobs.
     * @return array Result array with 'success' boolean and 'message' string.
     */
    public function create_and_schedule_job( array $module, int $user_id, string $context, ?array $optional_data = null ): array {
        try {
            // Validate required parameters
            if ( empty( $module ) || empty( $user_id ) ) {
                return [
                    'success' => false,
                    'message' => 'Invalid module or user ID'
                ];
            }

            $module_id = absint( $module['module_id'] ?? 0 );
            if ( empty( $module_id ) ) {
                return [
                    'success' => false,
                    'message' => 'Invalid module ID'
                ];
            }

            // Action Scheduler handles global concurrency control (MAX_CONCURRENT_JOBS = 2)

            // Build job config for database storage
            $job_config = $this->build_job_config( $module, $user_id, $context );
            if ( is_wp_error( $job_config ) ) {
                return [
                    'success' => false,
                    'message' => $job_config->get_error_message()
                ];
            }

            // Create job record in database using standardized format
            $job_data = [
                'module_id' => $module_id,
                'user_id' => $user_id,
                'module_config' => wp_json_encode( $job_config ),
                'input_data' => $optional_data ? wp_json_encode( $optional_data ) : null
            ];
            $db_jobs = apply_filters('dm_get_db_jobs', null);
            $job_id = $db_jobs->create_job( $job_data );

            if ( ! $job_id ) {
                $logger = apply_filters('dm_get_logger', null);
                $logger->error( 'Failed to create job record', [
                    'module_id' => $module_id,
                    'user_id' => $user_id,
                    'context' => $context
                ] );
                return [
                    'success' => false,
                    'message' => 'Failed to create job record'
                ];
            }

            // Schedule Step 1: Input Processing (starts async pipeline at position 0)
            $action_id = as_schedule_single_action(
                time() + 1, // Start immediately
                'dm_step_position_0_job_event',
                ['job_id' => $job_id],
                \DataMachine\Core\Constants::ACTION_GROUP
            );

            if ( $action_id === false || $action_id === 0 ) {
                $logger = apply_filters('dm_get_logger', null);
                $logger->error( 'Failed to schedule input job', [
                    'job_id' => $job_id,
                    'module_id' => $module_id,
                    'action_id' => $action_id
                ] );
                
                // Mark job as failed since scheduling failed
                $db_jobs = apply_filters('dm_get_db_jobs', null);
                $db_jobs->complete_job( $job_id, 'failed', wp_json_encode( [
                    'error' => 'Failed to schedule input processing step'
                ] ) );
                
                return [
                    'success' => false,
                    'message' => 'Failed to schedule job processing'
                ];
            }

            $logger = apply_filters('dm_get_logger', null);
            $logger->info( 'Job created and scheduled successfully', [
                'job_id' => $job_id,
                'module_id' => $module_id,
                'user_id' => $user_id,
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
                'module_id' => $module['module_id'] ?? 'unknown',
                'user_id' => $user_id,
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
     * Build job configuration from module data.
     *
     * @param array  $module Module configuration.
     * @param int    $user_id User ID.
     * @param string $context Job context.
     * @return array|WP_Error Job config array or error.
     */
    private function build_job_config( array $module, int $user_id, string $context ) {
        $module_id = absint( $module['module_id'] ?? 0 );
        
        // Get full module data from database
        $db_modules = apply_filters('dm_get_db_modules', null);
        $full_module = $db_modules->get_module( $module_id );
        if ( ! $full_module ) {
            return new WP_Error( 'module_not_found', 'Module not found in database' );
        }

        // Get project data
        $db_projects = apply_filters('dm_get_db_projects', null);
        $project = $db_projects->get_project( $full_module->project_id );
        if ( ! $project ) {
            return new WP_Error( 'project_not_found', 'Project not found for module' );
        }

        // Build module configuration from database fields
        $module_config = [
            'data_source_type' => $full_module->data_source_type ?? '',
            'data_source_config' => json_decode( $full_module->data_source_config ?? '{}', true ),
            'output_type' => $full_module->output_type ?? '',
            'output_config' => json_decode( $full_module->output_config ?? '{}', true ),
            'process_data_prompt' => $full_module->process_data_prompt ?? '',
            'fact_check_prompt' => $full_module->fact_check_prompt ?? '',
            'finalize_response_prompt' => $full_module->finalize_response_prompt ?? '',
            'skip_fact_check' => (bool)( $full_module->skip_fact_check ?? false )
        ];
        
        if ( ! is_array( $module_config['data_source_config'] ) ) {
            $module_config['data_source_config'] = [];
        }
        if ( ! is_array( $module_config['output_config'] ) ) {
            $module_config['output_config'] = [];
        }

        // Build simplified job configuration
        $job_config = [
            'module_id' => $module_id,
            'project_id' => $full_module->project_id,
            'user_id' => $user_id,
            'context' => $context,
            'data_source_type' => $module_config['data_source_type'] ?? '',
            'output_type' => $module_config['output_type'] ?? '',
            'output_config' => $module_config['output_config'] ?? [],
            'process_data_prompt' => $module_config['process_data_prompt'] ?? '',
            'fact_check_prompt' => $module_config['fact_check_prompt'] ?? '',
            'finalize_response_prompt' => $module_config['finalize_response_prompt'] ?? '',
            'skip_fact_check' => $module_config['skip_fact_check'] ?? false,
            'module_name' => $full_module->module_name ?? 'Unknown Module',
            'project_name' => $project->project_name ?? 'Unknown Project'
        ];

        // Add input-specific configuration
        $input_config_keys = [
            'rss_url', 'reddit_subreddit', 'reddit_sort', 'reddit_time_filter',
            'file_upload_path', 'api_endpoint', 'remote_location_id'
        ];
        
        foreach ( $input_config_keys as $key ) {
            if ( isset( $module_config[$key] ) ) {
                $job_config[$key] = $module_config[$key];
            }
        }

        // Output configuration is already included in output_config field above

        return $job_config;
    }
}