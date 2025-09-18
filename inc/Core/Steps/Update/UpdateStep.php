<?php
/**
 * Update Step - Content Modification
 *
 * Updates existing content using processed data via handler tools.
 * Supports AI-generated updates and direct handler execution.
 *
 * @package DataMachine\Core\Steps\Update
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\Update;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Update Step - Modifies Existing Content
 *
 * Processes data packets and updates existing content via handler tools.
 * Detects AI tool execution or executes handlers directly.
 */
class UpdateStep {

    /**
     * Execute update processing
     * 
     * Updates existing content using processed data via handler tools.
     * Detects if AI has already executed the tool or executes handler directly.
     * 
     * @param array $parameters Flat parameter structure from dm_engine_parameters filter:
     *   - job_id: Job execution identifier
     *   - flow_step_id: Flow step identifier  
     *   - flow_step_config: Step configuration data
     *   - data: Data packet array for processing
     *   - source_url: Required for content identification (from metadata)
     *   - Additional parameters: image_url, file_path, mime_type (as available)
     * @return array Updated data packet array with update results
     */
    public function execute(array $parameters): array {
        // Extract from flat parameter structure
        $job_id = $parameters['job_id'];
        $flow_step_id = $parameters['flow_step_id'];
        $data = $parameters['data'] ?? [];
        $flow_step_config = $parameters['flow_step_config'] ?? [];
        
        try {
            if (empty($flow_step_config)) {
                do_action('dm_log', 'error', 'Update Step: No step configuration provided', ['flow_step_id' => $flow_step_id]);
                return $data;
            }

            // Get handler configuration from flow step
            $handler = $flow_step_config['handler'] ?? [];
            $handler_slug = $handler['handler_slug'] ?? '';
            
            if (empty($handler_slug)) {
                do_action('dm_log', 'error', 'Update Step: No handler configured', ['flow_step_id' => $flow_step_id]);
                return $data;
            }

            // Validate we have data to process
            if (empty($data)) {
                do_action('dm_log', 'error', 'Update Step: No data to process', ['flow_step_id' => $flow_step_id]);
                return $data;
            }

            // Check if AI already executed the tool for this handler
            $tool_result_entry = $this->find_tool_result_for_handler($data, $handler_slug);
            if ($tool_result_entry) {
                do_action('dm_log', 'debug', 'Update Step: Tool already executed by AI step', [
                    'flow_step_id' => $flow_step_id,
                    'handler' => $handler_slug,
                    'tool_name' => $tool_result_entry['metadata']['tool_name'] ?? 'unknown'
                ]);
                
                // Create success entry from tool result and return
                return $this->create_update_entry_from_tool_result($tool_result_entry, $data, $handler_slug, $flow_step_id);
            }

            // Get handler instance and execute update
            $handler_result = $this->execute_handler($handler_slug, $data, $handler, $flow_step_config, $parameters);
            
            if ($handler_result === null) {
                do_action('dm_log', 'error', 'Update Step: Handler execution failed', [
                    'handler_slug' => $handler_slug,
                    'flow_step_id' => $flow_step_id
                ]);
                return $data;
            }

            // Add update result to data packet
            $update_entry = [
                'content' => [
                    'update_result' => $handler_result,
                    'updated_at' => current_time('mysql')
                ],
                'metadata' => [
                    'step_type' => 'update',
                    'handler' => $handler_slug,
                    'flow_step_id' => $flow_step_id,
                    'success' => $handler_result['success'] ?? false
                ],
                'attachments' => []
            ];

            // Add to front of data array following established pattern
            $data = apply_filters('dm_data_packet', $data, $update_entry, $flow_step_id, 'update');

            // Check for handler failure and fail the job if update failed
            $handler_success = $handler_result['success'] ?? false;
            if (!$handler_success) {
                do_action('dm_fail_job', $job_id, 'update_handler_failed', [
                    'handler_slug' => $handler_slug,
                    'flow_step_id' => $flow_step_id,
                    'handler_error' => $handler_result['error'] ?? 'Unknown handler error',
                    'handler_result' => $handler_result
                ]);
            }

            return $data;

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Update Step: Exception during processing', [
                'flow_step_id' => $flow_step_id,
                'exception' => $e->getMessage()
            ]);
            
            // Fail the job on exception
            do_action('dm_fail_job', $job_id, 'update_step_exception', [
                'flow_step_id' => $flow_step_id,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString()
            ]);
            
            return $data;
        }
    }

    /**
     * Execute update handler via tool calling
     * 
     * @param string $handler_slug Handler slug (wordpress, etc.)
     * @param array $data Data packet array for content extraction
     * @param array $handler_config Handler-specific configuration
     * @param array $flow_step_config Complete step configuration
     * @param array $parameters Flat parameter structure from engine
     * @return array|null Handler result with success/error data or null on failure
     */
    private function execute_handler($handler_slug, $data, $handler_config, $flow_step_config, $parameters) {
        try {
            // Get all registered update handlers
            $all_handlers = apply_filters('dm_handlers', []);
            $update_handlers = array_filter($all_handlers, function($handler) {
                return isset($handler['type']) && $handler['type'] === 'update';
            });

            if (!isset($update_handlers[$handler_slug])) {
                do_action('dm_log', 'error', 'Update Step: Handler not found', [
                    'handler_slug' => $handler_slug,
                    'available_handlers' => array_keys($update_handlers)
                ]);
                return null;
            }

            $handler_def = $update_handlers[$handler_slug];
            $handler_class = $handler_def['class'] ?? '';

            if (empty($handler_class) || !class_exists($handler_class)) {
                do_action('dm_log', 'error', 'Update Step: Handler class not found', [
                    'handler_slug' => $handler_slug,
                    'handler_class' => $handler_class
                ]);
                return null;
            }

            // Get handler tools for conditional execution
            $all_tools = apply_filters('ai_tools', [], $handler_slug, $handler_config);
            $handler_tools = array_filter($all_tools, function($tool) use ($handler_slug) {
                return isset($tool['handler']) && $tool['handler'] === $handler_slug;
            });
            
            // Extract variables from flat parameter structure for engine context
            $source_url = $parameters['source_url'] ?? null;
            $image_url = $parameters['image_url'] ?? null;
            $file_path = $parameters['file_path'] ?? null;
            $mime_type = $parameters['mime_type'] ?? null;
            
            // Build parameters using AIStepToolParameters with engine context
            $engine_parameters = compact('source_url', 'image_url', 'file_path', 'mime_type');
            
            if (!empty($handler_tools)) {
                $tool_name = array_key_first($handler_tools);
                $tool_def = $handler_tools[$tool_name];
                
                // Build flat parameters with engine context (includes source_url for content identification)
                $handler_parameters = \DataMachine\Core\Steps\AI\AIStepToolParameters::buildForHandlerTool(
                    [], // No AI parameters - direct handler execution
                    $data,
                    $tool_def,
                    $engine_parameters,
                    $handler_config
                );
            } else {
                // Fallback for handlers without tools
                $handler_parameters = $engine_parameters;
            }

            $handler_instance = new $handler_class();

            // Execute via tool calling interface (standardized for all handler types)
            if (!empty($handler_tools)) {
                $tool_name = array_key_first($handler_tools);
                $tool_def = $handler_tools[$tool_name];
                $tool_def['handler_config'] = $handler_config;

                do_action('dm_log', 'debug', 'Update Step: Executing handler via tool calling', [
                    'handler' => $handler_slug,
                    'tool_name' => $tool_name,
                    'parameters_count' => count($handler_parameters)
                ]);

                return $handler_instance->handle_tool_call($handler_parameters, $tool_def);
            }

            // No tool calling available - direct execution would go here if needed
            do_action('dm_log', 'error', 'Update Step: Handler has no execution method available', [
                'handler' => $handler_slug,
                'has_tools' => !empty($handler_tools)
            ]);
            return null;

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Update Step: Handler execution failed', [
                'handler' => $handler_slug,
                'exception' => $e->getMessage()
            ]);
            return null;
        }
    }



    /**
     * Find tool result entry for handler
     * 
     * @param array $data Data packet array
     * @param string $handler Handler slug
     * @return array|null Tool result entry or null
     */
    private function find_tool_result_for_handler(array $data, string $handler): ?array {
        foreach ($data as $entry) {
            if (($entry['type'] ?? '') === 'tool_result') {
                $tool_name = $entry['metadata']['tool_name'] ?? '';
                // For update handlers, look for tools that match handler pattern (e.g., save_semantic_analysis for structured_data)
                $tool_handler = $entry['metadata']['tool_handler'] ?? '';
                if ($tool_handler === $handler) {
                    return $entry;
                }
                // Check if tool name contains handler name pattern
                if (strpos($tool_name, $handler) !== false || strpos($handler, $tool_name) !== false) {
                    return $entry;
                }
            }
        }
        return null;
    }


    /**
     * Create update entry from tool result
     * 
     * @param array $tool_result_entry Tool result from AI step
     * @param array $data Current data packet
     * @param string $handler Handler slug
     * @param string $flow_step_id Flow step ID
     * @return array Updated data packet
     */
    private function create_update_entry_from_tool_result(array $tool_result_entry, array $data, string $handler, string $flow_step_id): array {
        $tool_result_data = $tool_result_entry['metadata']['tool_result'] ?? [];
        
        // Create update data entry for the data packet array
        $update_entry = [
            'content' => [
                'update_result' => $tool_result_data,
                'updated_at' => current_time('mysql')
            ],
            'metadata' => [
                'step_type' => 'update',
                'handler' => $handler,
                'flow_step_id' => $flow_step_id,
                'success' => $tool_result_data['success'] ?? false,
                'executed_via' => 'ai_tool_call',
                'tool_execution_data' => $tool_result_data
            ],
            'attachments' => []
        ];
        
        // Add update entry to front of data packet array (newest first)
        $data = apply_filters('dm_data_packet', $data, $update_entry, $flow_step_id, 'update');

        return $data;
    }
}