<?php
/**
 * Update Step - Updates existing content with processed data
 * 
 * "Plugins Within Plugins" Architecture Implementation
 * 
 * Processes cumulative data packet array and updates existing content.
 * Uses pure filter-based architecture following Data Machine patterns.
 * 
 * Update steps bridge AI processing with existing content modifications,
 * distinct from publish steps which create new content.
 * 
 * @package DataMachine
 * @subpackage Core\Steps\Update
 * @since 0.1.0
 */

namespace DataMachine\Core\Steps\Update;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Update Step - Updates existing content with processed data
 * 
 * Processes cumulative data packet array and updates existing content
 * based on AI analysis and handler configuration.
 */
class UpdateStep {

    /**
     * Execute update processing
     * 
     * Processes cumulative data packet array and updates existing content
     * based on AI analysis and handler configuration.
     * 
     * @param string $job_id The job ID for context tracking
     * @param string $flow_step_id The flow step ID to process
     * @param array $data The cumulative data packet array for this job
     * @param array $flow_step_config The merged step configuration
     * @return array Updated data packet array with update results added
     */
    public function execute($job_id, $flow_step_id, array $data = [], array $flow_step_config = []): array {
        try {
            if (empty($flow_step_config)) {
                do_action('dm_log', 'error', 'Update Step: No step configuration provided', ['flow_step_id' => $flow_step_id]);
                return [];
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

            do_action('dm_log', 'debug', 'Update Step: Starting update processing', [
                'flow_step_id' => $flow_step_id,
                'handler_slug' => $handler_slug,
                'data_entries' => count($data)
            ]);

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
            $handler_result = $this->execute_handler($handler_slug, $data, $handler, $flow_step_config);
            
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
            array_unshift($data, $update_entry);

            do_action('dm_log', 'info', 'Update Step: Processing completed', [
                'flow_step_id' => $flow_step_id,
                'handler_slug' => $handler_slug,
                'success' => $handler_result['success'] ?? false,
                'data_entries' => count($data)
            ]);

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
     * Execute update handler with data packet
     * 
     * @param string $handler_slug Handler slug to execute
     * @param array $data Complete data packet array
     * @param array $handler_config Handler configuration
     * @param array $flow_step_config Complete flow step configuration
     * @return array|null Handler result or null on failure
     */
    private function execute_handler($handler_slug, $data, $handler_config, $flow_step_config) {
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

            // Extract parameters using hybrid approach (original_id from any entry, content from latest entry)
            $parameters = $this->extract_hybrid_parameters_from_data($data, $handler_config);
            
            // Get handler tools for conditional execution
            $all_tools = apply_filters('ai_tools', [], $handler_slug, $handler_config);
            $handler_tools = array_filter($all_tools, function($tool) use ($handler_slug) {
                return isset($tool['handler']) && $tool['handler'] === $handler_slug;
            });

            $handler_instance = new $handler_class();

            // Execute via tool calling if tools are available (following established pattern)
            if (!empty($handler_tools)) {
                $tool_name = array_key_first($handler_tools);
                $tool_def = $handler_tools[$tool_name];
                $tool_def['handler_config'] = $handler_config;

                do_action('dm_log', 'debug', 'Update Step: Executing handler via tool calling', [
                    'handler' => $handler_slug,
                    'tool_name' => $tool_name,
                    'parameters_count' => count($parameters)
                ]);

                return $handler_instance->handle_tool_call($parameters, $tool_def);
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
     * Extract update parameters using hybrid approach for handler execution
     * 
     * Uses hybrid parameter extraction:
     * - Searches ALL entries for original_id (typically from fetch entry)
     * - Uses LATEST entry (data[0]) for content (typically AI-generated)
     * - Merges parameters from multiple sources as needed
     * 
     * @param array $data Complete data packet array
     * @param array $handler_settings Handler configuration settings
     * @return array Parameters for handler execution
     */
    private function extract_hybrid_parameters_from_data(array $data, array $handler_settings): array {
        $parameters = [];
        
        // Step 1: Find original_id from ANY entry (search all entries)
        $original_id_entry = null;
        foreach ($data as $data_entry) {
            $metadata = $data_entry['metadata'] ?? [];
            if (isset($metadata['original_id'])) {
                $original_id_entry = $data_entry;
                $parameters['original_id'] = $metadata['original_id'];
                
                do_action('dm_log', 'debug', 'Update Step: Found original_id in hybrid search', [
                    'original_id' => $metadata['original_id'],
                    'entry_type' => $data_entry['type'] ?? 'unknown',
                    'entry_source' => $metadata['source_type'] ?? 'unknown'
                ]);
                break;
            }
        }
        
        // Step 2: Get content from LATEST entry (data[0] - typically AI-generated)
        $latest_entry = !empty($data) ? $data[0] : [];
        $content_data = $latest_entry['content'] ?? [];
        
        if (isset($content_data['title'])) {
            $parameters['title'] = $content_data['title'];
        }
        if (isset($content_data['body'])) {
            $parameters['content'] = $content_data['body'];
        }
        
        do_action('dm_log', 'debug', 'Update Step: Extracted content from latest entry', [
            'entry_type' => $latest_entry['type'] ?? 'unknown',
            'entry_source' => $latest_entry['metadata']['source_type'] ?? 'unknown',
            'has_title' => isset($parameters['title']),
            'has_content' => isset($parameters['content']),
            'content_length' => isset($parameters['content']) ? strlen($parameters['content']) : 0
        ]);
        
        // Step 3: Extract additional metadata from original_id entry if found
        if ($original_id_entry) {
            $metadata = $original_id_entry['metadata'] ?? [];
            if (isset($metadata['source_url'])) {
                $parameters['source_url'] = $metadata['source_url'];
            }
        }
        
        // Step 4: Include AI analysis results from latest entry
        foreach ($content_data as $key => $value) {
            if (strpos($key, 'ai_') === 0 || in_array($key, [
                'content_type', 'audience_level', 'skill_prerequisites', 
                'content_characteristics', 'primary_intent', 'actionability',
                'complexity_score', 'estimated_completion_time'
            ])) {
                $parameters[$key] = $value;
            }
        }
        
        // Step 5: Merge handler settings
        if (!empty($handler_settings)) {
            $tool_relevant_settings = array_filter($handler_settings, function($key) {
                return !in_array($key, ['handler_slug', 'auth_config', 'internal_config']);
            }, ARRAY_FILTER_USE_KEY);
            $parameters = array_merge($parameters, $tool_relevant_settings);
        }
        
        do_action('dm_log', 'debug', 'Update Step: Hybrid parameter extraction completed', [
            'total_parameters' => count($parameters),
            'has_original_id' => isset($parameters['original_id']),
            'parameter_sources' => [
                'original_id_from' => $original_id_entry ? ($original_id_entry['type'] ?? 'unknown') : 'not_found',
                'content_from' => $latest_entry['type'] ?? 'unknown',
                'entries_searched' => count($data)
            ]
        ]);
        
        return $parameters;
    }

    /**
     * Extract update parameters from data entry for handler execution (Legacy method - kept for compatibility)
     * 
     * @param array $data_entry Data entry from data packet
     * @param array $handler_settings Handler configuration settings
     * @return array Parameters for handler execution
     */
    private function extract_update_parameters_from_data(array $data_entry, array $handler_settings): array {
        $parameters = [];
        
        // Extract content from data entry
        $content_data = $data_entry['content'] ?? [];
        if (isset($content_data['title'])) {
            $parameters['title'] = $content_data['title'];
        }
        if (isset($content_data['body'])) {
            $parameters['content'] = $content_data['body'];
        }
        
        // Extract metadata - CRITICAL: Include original_id for updates
        $metadata = $data_entry['metadata'] ?? [];
        if (isset($metadata['original_id'])) {
            $parameters['original_id'] = $metadata['original_id'];
        }
        if (isset($metadata['source_url'])) {
            $parameters['source_url'] = $metadata['source_url'];
        }
        
        // Include any AI analysis results from previous steps
        foreach ($content_data as $key => $value) {
            if (strpos($key, 'ai_') === 0 || in_array($key, [
                'content_type', 'audience_level', 'skill_prerequisites', 
                'content_characteristics', 'primary_intent', 'actionability',
                'complexity_score', 'estimated_completion_time'
            ])) {
                $parameters[$key] = $value;
            }
        }
        
        // Merge handler settings
        if (!empty($handler_settings)) {
            $tool_relevant_settings = array_filter($handler_settings, function($key) {
                return !in_array($key, ['handler_slug', 'auth_config', 'internal_config']);
            }, ARRAY_FILTER_USE_KEY);
            $parameters = array_merge($parameters, $tool_relevant_settings);
        }
        
        return $parameters;
    }

    /**
     * Find tool_result entry for the specified handler in the data packet.
     * 
     * @param array $data Data packet array
     * @param string $handler Handler slug to look for
     * @return array|null Tool result entry or null if not found
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
                // Fallback: check if tool name contains handler name pattern
                if (strpos($tool_name, $handler) !== false || strpos($handler, $tool_name) !== false) {
                    return $entry;
                }
            }
        }
        return null;
    }

    /**
     * Find data entry with original_id for update operations
     * 
     * Searches through all data entries to find one containing original_id
     * in metadata. This mirrors the logic from AIStepTools for consistency.
     * 
     * @param array $data Data packet array to search
     * @param array $handler_config Handler configuration for parameter extraction
     * @return array|null Data entry with original_id or null if not found
     */
    private function find_entry_with_original_id(array $data, array $handler_config = []): ?array {
        foreach ($data as $data_entry) {
            $entry_parameters = $this->extract_update_parameters_from_data($data_entry, $handler_config);
            if (isset($entry_parameters['original_id'])) {
                do_action('dm_log', 'debug', 'Update Step: Found original_id in data entry', [
                    'original_id' => $entry_parameters['original_id'],
                    'data_entry_type' => $data_entry['type'] ?? 'unknown',
                    'data_entry_source' => $data_entry['metadata']['source_type'] ?? 'unknown'
                ]);
                return $data_entry;
            }
        }
        
        do_action('dm_log', 'debug', 'Update Step: No original_id found in any data entry', [
            'data_entries_count' => count($data),
            'searched_entries' => array_map(function($entry) {
                return [
                    'type' => $entry['type'] ?? 'unknown',
                    'source' => $entry['metadata']['source_type'] ?? 'unknown',
                    'has_metadata' => !empty($entry['metadata'])
                ];
            }, $data)
        ]);
        
        return null;
    }

    /**
     * Create update entry from successful tool result.
     * 
     * @param array $tool_result_entry The tool result entry from AI step
     * @param array $data Current data packet
     * @param string $handler Handler slug
     * @param string $flow_step_id Flow step ID
     * @return array Updated data packet with update entry
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
        array_unshift($data, $update_entry);
        
        do_action('dm_log', 'info', 'Update Step: Completed successfully via AI tool call', [
            'flow_step_id' => $flow_step_id,
            'handler_slug' => $handler,
            'tool_result_keys' => array_keys($tool_result_data),
            'success' => $tool_result_data['success'] ?? false,
            'data_entries' => count($data)
        ]);

        return $data;
    }
}