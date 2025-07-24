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
use DataMachine\Helpers\{Logger, ActionScheduler};

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class JobCreator {

    /** @var Jobs */
    private $db_jobs;

    /** @var Modules */
    private $db_modules;

    /** @var Projects */
    private $db_projects;


    /** @var ActionScheduler */
    private $action_scheduler;

    /** @var Logger */
    private $logger;

    /**
     * Constructor.
     *
     * @param Jobs     $db_jobs Database jobs service.
     * @param Modules  $db_modules Database modules service.
     * @param Projects $db_projects Database projects service.
     * @param ActionScheduler  $action_scheduler Action scheduler service.
     * @param Logger            $logger Logger service.
     */
    public function __construct(
        Jobs $db_jobs,
        Modules $db_modules,
        Projects $db_projects,
        ActionScheduler $action_scheduler,
        Logger $logger
    ) {
        $this->db_jobs = $db_jobs;
        $this->db_modules = $db_modules;
        $this->db_projects = $db_projects;
        $this->action_scheduler = $action_scheduler;
        $this->logger = $logger;
    }

    /**
     * Create and schedule a job for the async pipeline.
     *
     * This is the single entry point for all job creation, regardless of source.
     * Always schedules dm_input_job_event to start the async pipeline.
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

            // Create job record in database
            $job_id = $this->db_jobs->create_job(
                $module_id,
                $user_id,
                wp_json_encode( $job_config ),
                $optional_data ? wp_json_encode( $optional_data ) : null
            );

            if ( ! $job_id ) {
                $this->logger->error( 'Failed to create job record', [
                    'module_id' => $module_id,
                    'user_id' => $user_id,
                    'context' => $context
                ] );
                return [
                    'success' => false,
                    'message' => 'Failed to create job record'
                ];
            }

            // Schedule Step 1: Input Processing (starts async pipeline)
            $action_id = $this->action_scheduler->schedule_single_job(
                'dm_input_job_event',
                ['job_id' => $job_id],
                time() + 1 // Start immediately
            );

            if ( $action_id === false || $action_id === 0 ) {
                $this->logger->error( 'Failed to schedule input job', [
                    'job_id' => $job_id,
                    'module_id' => $module_id,
                    'action_id' => $action_id
                ] );
                
                // Mark job as failed since scheduling failed
                $this->db_jobs->complete_job( $job_id, 'failed', wp_json_encode( [
                    'error' => 'Failed to schedule input processing step'
                ] ) );
                
                return [
                    'success' => false,
                    'message' => 'Failed to schedule job processing'
                ];
            }

            $this->logger->info( 'Job created and scheduled successfully', [
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
            $this->logger->error( 'Exception in job creation', [
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
        $full_module = $this->db_modules->get_module( $module_id );
        if ( ! $full_module ) {
            return new WP_Error( 'module_not_found', 'Module not found in database' );
        }

        // Get project data
        $project = $this->db_projects->get_project( $full_module->project_id );
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