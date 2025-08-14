<?php

namespace DataMachine\Core\Steps\Publish;


if (!defined('ABSPATH')) {
    exit;
}

// Pure array-based data packet system - no object dependencies

/**
 * Universal Publish Step - Executes any publish handler
 * 
 * This step can publish data to any configured destination using the filter-based
 * handler discovery system. Handler configuration is managed through the modal system
 * and flow-level settings, maintaining complete separation from step-level logic.
 * 
 * PURE CAPABILITY-BASED: External publish step classes only need:
 * - execute(int $job_id, array $data, array $step_config): array method
 * - Parameter-less constructor
 * - No interface implementation required
 * 
 * Handler selection is determined by flow configuration, enabling
 * complete flexibility in publishing workflows.
 */
class PublishStep {

    /**
     * Execute publish publishing with pure array data packet system
     * 
     * PURE ARRAY SYSTEM:
     * - Receives the cumulative data packet array (newest items first)
     * - Uses latest entry for publishing (data[0])
     * - Adds publish result to the array and returns updated array
     * 
     * @param string $flow_step_id The flow step ID to process
     * @param array $data The cumulative data packet array for this job
     * @param array $flow_step_config Flow step configuration including handler settings
     * @return array Updated data packet array with publish result added
     */
    public function execute($flow_step_id, array $data = [], array $flow_step_config = []): array {
        $all_databases = apply_filters('dm_db', []);
        $db_jobs = $all_databases['jobs'] ?? null;

        try {
            do_action('dm_log', 'debug', 'Publish Step: Starting data publishing', ['flow_step_id' => $flow_step_id]);

            // Use step configuration directly - no job config introspection needed
            if (empty($flow_step_config)) {
                do_action('dm_log', 'error', 'Publish Step: No step configuration provided', ['flow_step_id' => $flow_step_id]);
                return [];
            }

            $handler_data = $flow_step_config['handler'] ?? null;
            
            if (!$handler_data || empty($handler_data['handler_slug'])) {
                do_action('dm_log', 'error', 'Publish Step: Publish step requires handler configuration', [
                    'flow_step_id' => $flow_step_id,
                    'available_flow_step_config' => array_keys($flow_step_config),
                    'handler_data' => $handler_data
                ]);
                return [];
            }
            
            $handler = $handler_data['handler_slug'];
            $handler_settings = $handler_data['settings'] ?? [];

            // Publish steps use latest data entry (first in array)
            $latest_data = $data[0] ?? null;
            if (!$latest_data) {
                do_action('dm_log', 'error', 'Publish Step: No data available from previous step', ['flow_step_id' => $flow_step_id]);
                return $data; // Return unchanged array
            }

            // Execute single publish handler - one step, one handler, per flow
            $handler_result = $this->execute_publish_handler_direct($handler, $latest_data, $flow_step_config, $handler_settings);

            if (!$handler_result || !is_array($handler_result)) {
                do_action('dm_log', 'error', 'Publish Step: Handler execution failed - null or invalid result', [
                    'flow_step_id' => $flow_step_id,
                    'handler' => $handler,
                    'result_type' => gettype($handler_result)
                ]);
                return []; // Return empty array to signal step failure
            }

            // Check if handler reported failure
            if (isset($handler_result['success']) && $handler_result['success'] === false) {
                do_action('dm_log', 'error', 'Publish Step: Handler reported failure', [
                    'flow_step_id' => $flow_step_id,
                    'handler' => $handler,
                    'error' => $handler_result['error'] ?? 'Unknown error'
                ]);
                return []; // Return empty array to signal step failure
            }

            // Create publish data entry for the data packet array
            $publish_entry = [
                'type' => 'publish',
                'handler' => $handler,
                'content' => [
                    'title' => 'Publish Complete',
                    'body' => json_encode($handler_result, JSON_PRETTY_PRINT)
                ],
                'metadata' => [
                    'handler_used' => $handler,
                    'publish_success' => true,
                    'flow_step_id' => $flow_step_id,
                    'source_type' => $latest_data['metadata']['source_type'] ?? 'unknown'
                ],
                'result' => $handler_result,
                'timestamp' => time()
            ];
            
            // Add publish entry to front of data packet array (newest first)
            array_unshift($data, $publish_entry);
            
            do_action('dm_log', 'debug', 'Publish Step: Publishing completed successfully', [
                'flow_step_id' => $flow_step_id,
                'handler' => $handler,
                'items_processed' => 1, // Publish step always processes exactly 1 item (latest)
                'total_items_in_packet' => count($data) // Total accumulated items in data packet
            ]);

            return $data;

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Publish Step: Exception during publishing', [
                'flow_step_id' => $flow_step_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Return empty array on failure (engine interprets as step failure)
            return $data;
        }
    }



    /**
     * Execute publish handler directly using pure auto-discovery
     * 
     * @param string $handler_name Publish handler name
     * @param array $data_entry Latest data entry from data packet array
     * @param array $flow_step_config Flow step configuration
     * @param array $handler_settings Handler settings
     * @return array|null Publish result or null on failure
     */
    private function execute_publish_handler_direct(string $handler_name, array $data_entry, array $flow_step_config, array $handler_settings): ?array {
        // Get handler object directly from handler system
        $handler = $this->get_handler_object($handler_name, 'publish');
        if (!$handler) {
            do_action('dm_log', 'error', 'Publish Step: Handler not found or invalid', [
                'handler' => $handler_name,
                'flow_step_config' => array_keys($flow_step_config)
            ]);
            return null;
        }

        try {
            // Handler is already instantiated from the registry

            // Get pipeline and flow IDs from flow_step_config
            $pipeline_id = $flow_step_config['pipeline_id'] ?? null;
            $flow_id = $flow_step_config['flow_id'] ?? null;
            
            if (!$pipeline_id) {
                do_action('dm_log', 'error', 'Publish Step: Pipeline ID not found in flow step config', [
                    'flow_step_config_keys' => array_keys($flow_step_config)
                ]);
                return null;
            }

            // Universal JSON data entry interface - simple and direct
            
            // Convert data entry to pure JSON object  
            $json_data_entry = json_decode(json_encode($data_entry));
            $json_data_entry->publish_config = $handler_settings;
            
            // Execute handler with pure JSON object - beautiful simplicity
            $publish_result = $handler->handle_publish($json_data_entry);

            return $publish_result;

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Publish Step: Handler execution failed', [
                'handler' => $handler_name,
                'pipeline_id' => $pipeline_id ?? 'unknown',
                'flow_id' => $flow_id ?? 'unknown',
                'exception' => $e->getMessage()
            ]);
            return null;
        }
    }



    /**
     * Get handler object directly from the handler system.
     * 
     * Uses the object-based handler registration to get
     * instantiated handler objects directly, eliminating class discovery.
     * 
     * @param string $handler_name Handler name/key
     * @param string $handler_type Handler type (fetch/publish)
     * @return object|null Handler object or null if not found
     */
    private function get_handler_object(string $handler_name, string $handler_type): ?object {
        // Direct handler discovery - no redundant filtering needed
        $all_handlers = apply_filters('dm_handlers', []);
        $handler_info = $all_handlers[$handler_name] ?? null;
        
        if (!$handler_info || !isset($handler_info['class'])) {
            return null;
        }
        
        // Verify handler type matches
        if (($handler_info['type'] ?? '') !== $handler_type) {
            return null;
        }
        
        $class_name = $handler_info['class'];
        return class_exists($class_name) ? new $class_name() : null;
    }

    // PublishStep receives cumulative data packet array from engine via $data parameter


}


