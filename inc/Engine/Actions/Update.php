<?php
namespace DataMachine\Engine\Actions;


/**
 * Centralized update operations for jobs, flows, and pipelines.
 *
 * Provides intelligent method selection, service discovery, and consistent
 * error handling via action hooks.
 *
 * @package DataMachine\Engine\Actions
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Centralized update operations via action hooks.
 */
class Update {

    /**
     * Register update action hooks with intelligent method selection.
     */
    public static function register() {
        $instance = new self();
        
        // Central job status update hook - eliminates confusion about which method to use
        add_action('datamachine_update_job_status', [$instance, 'handle_job_status_update'], 10, 4);
        
        
        // Flow handler management action hook - eliminates 50+ line handler update patterns
        add_action('datamachine_update_flow_handler', [$instance, 'handle_flow_handler_update'], 10, 3);
        
        // Flow step synchronization action hook - unifies single and bulk step sync operations
        add_action('datamachine_sync_steps_to_flow', [$instance, 'handle_flow_steps_sync'], 10, 3);
        
        // Flow user message management action hook - enables AI steps to run standalone
        add_action('datamachine_update_flow_user_message', [$instance, 'handle_flow_user_message_update'], 10, 2);

        // Pipeline system prompt management action hook - enables AI step template updates
        add_action('datamachine_update_system_prompt', [$instance, 'handle_system_prompt_update'], 10, 2);
    }

    /**
     * Handle job status updates with intelligent method selection.
     *
     * Eliminates confusion about which method to use (start_job vs complete_job vs update_job_status)
     * by automatically selecting the appropriate method based on context and status transitions.
     *
     * @param int $job_id Job ID to update
     * @param string $new_status New job status
     * @param string $context Update context ('start', 'complete', 'update')
     * @param string|null $old_status Previous job status for transition logic
     * @return bool Success status
     * @since 1.0.0
     */
    public function handle_job_status_update($job_id, $new_status, $context = 'update', $old_status = null) {
        $job_id = (int) $job_id; // Ensure job_id is int for database operations

        $db_jobs = new \DataMachine\Core\Database\Jobs\Jobs();

        $success = false;
        $method_used = '';
        
        if ($context === 'start' || ($new_status === 'processing' && $old_status === 'pending')) {
            // Job is starting - use start_job for timestamp
            $success = $db_jobs->start_job($job_id, $new_status);
            $method_used = 'start_job';
            
        } elseif ($context === 'complete' || in_array($new_status, ['completed', 'failed', 'completed_no_items'])) {
            // Job is ending - use complete_job for timestamp
            $success = $db_jobs->complete_job($job_id, $new_status);
            $method_used = 'complete_job';
            
        } else {
            $success = $db_jobs->update_job_status($job_id, $new_status);
            $method_used = 'update_job_status';
        }
        
        // Clean up processed items if job failed (allows retry without processed item conflicts)
        if ($new_status === 'failed' && $success) {
            do_action('datamachine_delete_processed_items', ['job_id' => (int)$job_id]);
        }
        
        
        return $success;
    }

    /**
     * Handle flow handler updates with centralized database operations.
     *
     * Eliminates repetitive handler update patterns by providing centralized
     * handler addition/update functionality with consistent error handling.
     *
     * @param string $flow_step_id Flow step ID (format: pipeline_step_id_flow_id)
     * @param string $handler_slug Handler slug to add/update
     * @param array $handler_settings Handler configuration settings
     * @return bool Success status
     * @since 1.0.0
     */
    public function handle_flow_handler_update($flow_step_id, $handler_slug, $handler_settings = []) {
        $parts = apply_filters('datamachine_split_flow_step_id', null, $flow_step_id);
        if (!$parts) {
            do_action('datamachine_log', 'error', 'Invalid flow_step_id format for handler update', ['flow_step_id' => $flow_step_id]);
            return false;
        }
        $flow_id = $parts['flow_id'];

        $db_flows = new \DataMachine\Core\Database\Flows\Flows();

        // Get current flow
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            do_action('datamachine_log', 'error', 'Flow handler update failed - flow not found', [
                'flow_id' => $flow_id,
                'flow_step_id' => $flow_step_id
            ]);
            return false;
        }
        $flow_config = $flow['flow_config'] ?? [];
        
        // Initialize step configuration if it doesn't exist
        if (!isset($flow_config[$flow_step_id])) {
            if (!isset($parts['pipeline_step_id']) || empty($parts['pipeline_step_id'])) {
                do_action('datamachine_log', 'error', 'Pipeline step ID is required for flow handler update', [
                    'flow_step_id' => $flow_step_id,
                    'parts' => $parts
                ]);
                return false;
            }
            $pipeline_step_id = $parts['pipeline_step_id'];
            $flow_config[$flow_step_id] = [
                'flow_step_id' => $flow_step_id,
                'pipeline_step_id' => $pipeline_step_id,
                'pipeline_id' => $flow['pipeline_id'],
                'flow_id' => $flow_id,
                'handler' => null
            ];
        }
        
        // Check if handler already exists
        $handler_exists = isset($flow_config[$flow_step_id]['handler_slug']) &&
                         $flow_config[$flow_step_id]['handler_slug'] === $handler_slug;

        // UPDATE existing handler settings OR ADD new handler (single handler per step)
        $flow_config[$flow_step_id]['handler_slug'] = $handler_slug;
        $flow_config[$flow_step_id]['handler_config'] = $handler_settings;
        $flow_config[$flow_step_id]['enabled'] = true;

         // Update flow with new configuration
         $success = $db_flows->update_flow($flow_id, [
             'flow_config' => wp_json_encode($flow_config)
         ]);
         
         if (!$success) {
             do_action('datamachine_log', 'error', 'Flow handler update failed - database update failed', [
                 'flow_id' => $flow_id,
                 'flow_step_id' => $flow_step_id,
                 'handler_slug' => $handler_slug
             ]);
             return false;
         }

         // Clear flow cache to ensure fresh data on next fetch
         do_action('datamachine_clear_flow_cache', $flow_id);

         return true;
    }

    /**
     * Handle flow step synchronization for single or multiple steps.
     *
     * Unified logic for creating flow step configurations from pipeline steps.
     * Eliminates code duplication between single step sync and bulk step sync operations.
     *
     * @param int $flow_id Flow ID to sync steps to
     * @param array $steps Array of pipeline step data (single step = array with one element)
     * @param array $context Context information for logging and debugging
     * @return bool Success status
     * @since 1.0.0
     */
    public function handle_flow_steps_sync($flow_id, $steps, $context = []) {
        $db_flows = new \DataMachine\Core\Database\Flows\Flows();

        // Validate flow exists
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            do_action('datamachine_log', 'error', 'Flow steps sync failed - flow not found', [
                'flow_id' => $flow_id,
                'steps_count' => count($steps),
                'context' => $context
            ]);
            return false;
        }
        $flow_config = $flow['flow_config'] ?? [];
        
        // Process each step
        foreach ($steps as $step) {
            if (!isset($step['pipeline_step_id']) || empty($step['pipeline_step_id'])) {
                do_action('datamachine_log', 'error', 'Pipeline step ID is required for flow steps sync', [
                    'flow_id' => $flow_id,
                    'step' => $step
                ]);
                return false;
            }

            $pipeline_step_id = $step['pipeline_step_id'];

            if (!$pipeline_step_id) {
                do_action('datamachine_log', 'warning', 'Skipping step sync - missing pipeline_step_id', [
                    'flow_id' => $flow_id,
                    'step_data' => $step,
                    'context' => $context
                ]);
                continue;
            }
            
            // Generate flow step ID using existing filter pattern
            $flow_step_id = apply_filters('datamachine_generate_flow_step_id', '', $pipeline_step_id, $flow_id);
            
            // Get enabled_tools from pipeline step to inherit in flow step
            $tool_manager = new \DataMachine\Engine\AI\Tools\ToolManager();
            $pipeline_enabled_tools = $tool_manager->get_step_enabled_tools($pipeline_step_id);

            // Create flow step configuration
            $flow_config[$flow_step_id] = [
                'flow_step_id' => $flow_step_id,
                'step_type' => $step['step_type'] ?? '',
                'pipeline_step_id' => $pipeline_step_id,
                'pipeline_id' => $flow['pipeline_id'],
                'flow_id' => $flow_id,
                'execution_order' => $step['execution_order'] ?? 0,
                'enabled_tools' => $pipeline_enabled_tools,
                'handler' => null
            ];
        }

        // Update flow configuration
        $success = $db_flows->update_flow($flow_id, [
            'flow_config' => wp_json_encode($flow_config)
        ]);
        
        if (!$success) {
            do_action('datamachine_log', 'error', 'Flow steps sync failed - database update failed', [
                'flow_id' => $flow_id,
                'steps_count' => count($steps),
                'context' => $context
            ]);
            return false;
        }

        do_action('datamachine_log', 'debug', 'Flow steps sync completed successfully', [
            'flow_id' => $flow_id,
            'pipeline_id' => $flow['pipeline_id'],
            'steps_count' => count($steps),
            'context' => $context
        ]);

        return true;
    }

    /**
     * Handle flow user message updates for AI steps.
     *
     * Enables AI steps to run standalone by providing user message content
     * that gets converted to data packets when no fetch step precedes them.
     *
     * Flow-scoped user messages allow different content per flow instance
     * while maintaining pipeline-level system prompt templates.
     *
     * @param string $flow_step_id Flow step ID (format: pipeline_step_id_flow_id)
     * @param string $user_message User message content
     * @return bool Success status
     * @since 1.0.0
     */
    public function handle_flow_user_message_update($flow_step_id, $user_message) {
        $parts = apply_filters('datamachine_split_flow_step_id', null, $flow_step_id);
        if (!$parts) {
            do_action('datamachine_log', 'error', 'Invalid flow_step_id format for user message update', ['flow_step_id' => $flow_step_id]);
            return false;
        }
        $flow_id = $parts['flow_id'];

        $db_flows = new \DataMachine\Core\Database\Flows\Flows();

        // Get current flow
        $flow = $db_flows->get_flow($flow_id);
        if (!$flow) {
            do_action('datamachine_log', 'error', 'Flow user message update failed - flow not found', [
                'flow_id' => $flow_id,
                'flow_step_id' => $flow_step_id
            ]);
            return false;
        }

        // Get flow configuration
        $flow_config = $flow['flow_config'] ?? [];

        // Update user message in the specific flow step
        if (!isset($flow_config[$flow_step_id])) {
            do_action('datamachine_log', 'error', 'Flow user message update failed - flow step not found', [
                'flow_id' => $flow_id,
                'flow_step_id' => $flow_step_id
            ]);
            return false;
        }

        // Update user message field
        $flow_config[$flow_step_id]['user_message'] = wp_unslash(sanitize_textarea_field($user_message));

        // Update flow with new configuration
        $success = $db_flows->update_flow($flow_id, [
            'flow_config' => wp_json_encode($flow_config)
        ]);

        if (!$success) {
            do_action('datamachine_log', 'error', 'Flow user message update failed - database update error', [
                'flow_id' => $flow_id,
                'flow_step_id' => $flow_step_id
            ]);
            return false;
        }

        return true;
    }

    /**
     * Handle system prompt updates for AI pipeline steps.
     *
     * Updates the system_prompt field in pipeline step configuration
     * while preserving all other step configuration data.
     *
     * Pipeline-scoped system prompts serve as templates that can be
     * inherited by flow instances while maintaining flow-specific customization.
     *
     * @param string $pipeline_step_id Pipeline step ID (UUID4)
     * @param string $system_prompt System prompt content
     * @return bool Success status
     * @since 1.0.0
     */
    public function handle_system_prompt_update($pipeline_step_id, $system_prompt) {
        // Get database services
        $db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();

        // Get step configuration
        $step_config = $db_pipelines->get_pipeline_step_config( $pipeline_step_id );

        if (empty($step_config) || empty($step_config['pipeline_id'])) {
            do_action('datamachine_log', 'error', 'System prompt update failed - pipeline step not found', [
                'pipeline_step_id' => $pipeline_step_id
            ]);
            return false;
        }

        $pipeline_id = $step_config['pipeline_id'];

        // Get the complete pipeline data
        $db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
        $target_pipeline = $db_pipelines->get_pipeline($pipeline_id);
        if (!$target_pipeline) {
            do_action('datamachine_log', 'error', 'System prompt update failed - pipeline not found', [
                'pipeline_id' => $pipeline_id,
                'pipeline_step_id' => $pipeline_step_id
            ]);
            return false;
        }

        // Update step configuration
        $pipeline_config = $target_pipeline['pipeline_config'] ?? [];

        // Update system_prompt field
        if (!isset($pipeline_config[$pipeline_step_id])) {
            $pipeline_config[$pipeline_step_id] = [];
        }
        $pipeline_config[$pipeline_step_id]['system_prompt'] = wp_unslash($system_prompt);

        // Save updated pipeline configuration
        $success = $db_pipelines->update_pipeline($target_pipeline['pipeline_id'], [
            'pipeline_config' => json_encode($pipeline_config)
        ]);

        if (!$success) {
            do_action('datamachine_log', 'error', 'System prompt update failed - database update error', [
                'pipeline_id' => $target_pipeline['pipeline_id'],
                'pipeline_step_id' => $pipeline_step_id
            ]);
            return false;
        }

        return true;
    }


}