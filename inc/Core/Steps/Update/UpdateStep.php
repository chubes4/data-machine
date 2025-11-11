<?php
/**
 * Update step with AI tool detection and direct handler execution.
 *
 * @package DataMachine\Core\Steps\Update
 */

namespace DataMachine\Core\Steps\Update;

if (!defined('ABSPATH')) {
    exit;
}
class UpdateStep {

    /**
     * Execute update handler with AI tool result detection.
     */
    public function execute(array $parameters): array {
        $job_id = $parameters['job_id'];
        $flow_step_id = $parameters['flow_step_id'];
        $data = $parameters['data'] ?? [];
        $flow_step_config = $parameters['flow_step_config'] ?? [];
        
        try {
            if (empty($flow_step_config)) {
                do_action('datamachine_log', 'error', 'Update Step: No step configuration provided', ['flow_step_id' => $flow_step_id]);
                return $data;
            }

            $handler_slug = $flow_step_config['handler_slug'] ?? '';

            if (empty($handler_slug)) {
                do_action('datamachine_log', 'error', 'Update Step: No handler configured', ['flow_step_id' => $flow_step_id]);
                return $data;
            }

            $handler_config = $flow_step_config['handler_config'] ?? [];

            if (empty($data)) {
                do_action('datamachine_log', 'error', 'Update Step: No data to process', ['flow_step_id' => $flow_step_id]);
                return $data;
            }

            do_action('datamachine_log', 'debug', 'Update Step: Starting update processing', [
                'flow_step_id' => $flow_step_id,
                'handler_slug' => $handler_slug,
                'data_entries' => count($data)
            ]);

            $tool_result_entry = $this->find_tool_result_for_handler($data, $handler_slug);
            if ($tool_result_entry) {
                do_action('datamachine_log', 'debug', 'Update Step: Tool already executed by AI step', [
                    'flow_step_id' => $flow_step_id,
                    'handler' => $handler_slug,
                    'tool_name' => $tool_result_entry['metadata']['tool_name'] ?? 'unknown'
                ]);
                
                return $this->create_update_entry_from_tool_result($tool_result_entry, $data, $handler_slug, $flow_step_id);
            }

            $handler_result = $this->execute_handler($handler_slug, $data, $handler_config, $flow_step_config, $parameters);
            
            if ($handler_result === null) {
                do_action('datamachine_log', 'error', 'Update Step: Handler execution failed', [
                    'handler_slug' => $handler_slug,
                    'flow_step_id' => $flow_step_id
                ]);
                return $data;
            }

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

            $data = apply_filters('datamachine_data_packet', $data, $update_entry, $flow_step_id, 'update');

            $handler_success = $handler_result['success'] ?? false;
            if (!$handler_success) {
                do_action('datamachine_fail_job', $job_id, 'update_handler_failed', [
                    'handler_slug' => $handler_slug,
                    'flow_step_id' => $flow_step_id,
                    'handler_error' => $handler_result['error'] ?? 'Unknown handler error',
                    'handler_result' => $handler_result
                ]);
            }

            return $data;

        } catch (\Exception $e) {
            do_action('datamachine_log', 'error', 'Update Step: Exception during processing', [
                'flow_step_id' => $flow_step_id,
                'exception' => $e->getMessage()
            ]);
            
            do_action('datamachine_fail_job', $job_id, 'update_step_exception', [
                'flow_step_id' => $flow_step_id,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString()
            ]);
            
            return $data;
        }
    }

    /**
     * Execute update handler via tool calling interface.
     *
     * @param string $handler_slug Handler identifier
     * @param array $data Current data packet
     * @param array $handler_config Handler settings
     * @param array $flow_step_config Complete step configuration
     * @param array $parameters Engine parameters
     * @return array|null Handler result or null on failure
     */
    private function execute_handler($handler_slug, $data, $handler_config, $flow_step_config, $parameters) {
        try {
            $update_handlers = apply_filters('datamachine_handlers', [], 'update');

            if (!isset($update_handlers[$handler_slug])) {
                do_action('datamachine_log', 'error', 'Update Step: Handler not found', [
                    'handler_slug' => $handler_slug,
                    'available_handlers' => array_keys($update_handlers)
                ]);
                return null;
            }

            $handler_def = $update_handlers[$handler_slug];
            $handler_class = $handler_def['class'] ?? '';

            if (empty($handler_class) || !class_exists($handler_class)) {
                do_action('datamachine_log', 'error', 'Update Step: Handler class not found', [
                    'handler_slug' => $handler_slug,
                    'handler_class' => $handler_class
                ]);
                return null;
            }

            $all_tools = apply_filters('ai_tools', [], $handler_slug, $handler_config);
            $handler_tools = array_filter($all_tools, function($tool) use ($handler_slug) {
                return isset($tool['handler']) && $tool['handler'] === $handler_slug;
            });
            
            $job_id = $parameters['job_id'];

            // Access engine data via centralized filter pattern (source_url, image_url from fetch handlers)
            $engine_data = apply_filters('datamachine_engine_data', [], $job_id);

            $source_url = $engine_data['source_url'] ?? null;
            $image_url = $engine_data['image_url'] ?? null;

            // Extract file metadata from data array (same pattern as AIStep)
            $file_path = null;
            $mime_type = null;
            if (!empty($data)) {
                $first_item = $data[0] ?? [];
                $metadata = $first_item['metadata'] ?? [];
                if (isset($metadata['file_path']) && file_exists($metadata['file_path'])) {
                    $file_path = $metadata['file_path'];
                    $mime_type = $metadata['mime_type'] ?? '';
                }
            }

            $engine_parameters = compact('source_url', 'image_url', 'file_path', 'mime_type');
            
            if (!empty($handler_tools)) {
                $tool_name = array_key_first($handler_tools);
                $tool_def = $handler_tools[$tool_name];
                
                $handler_parameters = \DataMachine\Core\Steps\AI\AIStepToolParameters::buildForHandlerTool(
                    [], // No AI parameters - direct handler execution
                    $data,
                    $tool_def,
                    $engine_parameters,
                    $handler_config
                );
            } else {
                $handler_parameters = $engine_parameters;
            }

            $handler_instance = new $handler_class();

            if (!empty($handler_tools)) {
                $tool_name = array_key_first($handler_tools);
                $tool_def = $handler_tools[$tool_name];
                $tool_def['handler_config'] = $handler_config;

                do_action('datamachine_log', 'debug', 'Update Step: Executing handler via tool calling', [
                    'handler' => $handler_slug,
                    'tool_name' => $tool_name,
                    'parameters_count' => count($handler_parameters)
                ]);

                return $handler_instance->handle_tool_call($handler_parameters, $tool_def);
            }

            do_action('datamachine_log', 'error', 'Update Step: Handler has no execution method available', [
                'handler' => $handler_slug,
                'has_tools' => !empty($handler_tools)
            ]);
            return null;

        } catch (\Exception $e) {
            do_action('datamachine_log', 'error', 'Update Step: Handler execution failed', [
                'handler' => $handler_slug,
                'exception' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Find AI tool execution result using intelligent handler matching.
     *
     * Implements enhanced tool result detection with flexible matching:
     * 1. Exact handler match via tool_handler metadata
     * 2. Partial name matching for tool discovery compatibility
     *
     * @param array $data Data packet array from AI step execution
     * @param string $handler Target handler slug for matching
     * @return array|null Tool result entry or null if no match found
     */
    private function find_tool_result_for_handler(array $data, string $handler): ?array {
        foreach ($data as $entry) {
            if (($entry['type'] ?? '') === 'tool_result') {
                $tool_name = $entry['metadata']['tool_name'] ?? '';
                $tool_handler = $entry['metadata']['tool_handler'] ?? '';

                // Exact handler match - primary discovery method
                if ($tool_handler === $handler) {
                    return $entry;
                }

                // Partial name matching for tool discovery - secondary method
                if (strpos($tool_name, $handler) !== false || strpos($handler, $tool_name) !== false) {
                    return $entry;
                }
            }
        }
        return null;
    }

    /**
     * Create update entry from AI tool result.
     *
     * @param array $tool_result_entry Tool result from AI step
     * @param array $data Current data packet
     * @param string $handler Handler slug
     * @param string $flow_step_id Flow step ID
     * @return array Updated data packet
     */
    private function create_update_entry_from_tool_result(array $tool_result_entry, array $data, string $handler, string $flow_step_id): array {
        $tool_result_data = $tool_result_entry['metadata']['tool_result'] ?? [];
        
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
        
        $data = apply_filters('datamachine_data_packet', $data, $update_entry, $flow_step_id, 'update');

        return $data;
    }
}