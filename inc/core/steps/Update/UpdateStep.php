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

            // Get the latest data entry for processing
            $latest_entry = !empty($data) ? $data[0] : [];
            if (empty($latest_entry)) {
                do_action('dm_log', 'error', 'Update Step: No data to process', ['flow_step_id' => $flow_step_id]);
                return $data;
            }

            do_action('dm_log', 'debug', 'Update Step: Starting update processing', [
                'flow_step_id' => $flow_step_id,
                'handler_slug' => $handler_slug,
                'data_entries' => count($data)
            ]);

            // Get handler instance and execute update
            $handler_result = $this->execute_handler($handler_slug, $latest_entry, $handler, $flow_step_config);
            
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

            return $data;

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Update Step: Exception during processing', [
                'flow_step_id' => $flow_step_id,
                'exception' => $e->getMessage()
            ]);
            return $data;
        }
    }

    /**
     * Execute update handler with data packet
     * 
     * @param string $handler_slug Handler slug to execute
     * @param array $data_entry Latest data entry from packet
     * @param array $handler_config Handler configuration
     * @param array $flow_step_config Complete flow step configuration
     * @return array|null Handler result or null on failure
     */
    private function execute_handler($handler_slug, $data_entry, $handler_config, $flow_step_config) {
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

            // Extract parameters from data entry following established pattern
            $parameters = $this->extract_update_parameters_from_data($data_entry, $handler_config);
            
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
     * Extract update parameters from data entry for handler execution
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
}