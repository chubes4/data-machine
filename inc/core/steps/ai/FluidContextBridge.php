<?php

namespace DataMachine\Core\Steps\AI;

if (!defined('ABSPATH')) {
    exit;
}

// DataPacket is engine-only - core components work with simple arrays

/**
 * Fluid Context Bridge - Convert Data Machine DataPackets to ai-http-client context format
 * 
 * Single Responsibility: Bridge between Data Machine pipeline data and ai-http-client context management
 */
class FluidContextBridge {

    /**
     * Parameter-less constructor - pure filter-based architecture
     */
    public function __construct() {
        // No parameters needed - all services accessed via filters
    }

    /**
     * Aggregate multiple DataPackets into ai-http-client context format
     * 
     * Converts array of DataPackets from pipeline steps into structured context
     * that ai-http-client can use for enhanced AI interactions.
     * 
     * @param array $data_packets Array of DataPacket objects from pipeline
     * @return array Aggregated context for ai-http-client consumption
     */
    public function aggregate_pipeline_context(array $data_packets): array {
        $logger = apply_filters('dm_get_logger', null);
        
        if (empty($data_packets)) {
            $logger->debug('FluidContextBridge: No data packets provided for aggregation');
            return [];
        }

        $aggregated_context = [
            'pipeline_data' => [],
            'content_sources' => [],
            'metadata_summary' => [],
            'processing_history' => [],
            'total_content_length' => 0,
            'source_types' => [],
            'attachments_summary' => []
        ];

        foreach ($data_packets as $index => $packet) {
            // Duck typing: Check for required DataPacket properties instead of class instance
            if (!is_object($packet) || !isset($packet->content) || !isset($packet->metadata)) {
                $logger->warning('FluidContextBridge: Invalid data packet structure found in aggregation', [
                    'index' => $index,
                    'type' => gettype($packet),
                    'has_content' => is_object($packet) && isset($packet->content),
                    'has_metadata' => is_object($packet) && isset($packet->metadata)
                ]);
                continue;
            }

            $packet_context = $this->format_datapacket_content($packet);
            $aggregated_context['pipeline_data'][] = $packet_context;

            // Aggregate metadata
            $this->aggregate_packet_metadata($packet, $aggregated_context);
        }

        // Calculate summary statistics
        $aggregated_context['packet_count'] = count($aggregated_context['pipeline_data']);
        $aggregated_context['unique_sources'] = array_unique($aggregated_context['source_types']);

        $logger->info('FluidContextBridge: Successfully aggregated pipeline context', [
            'packet_count' => $aggregated_context['packet_count'],
            'total_content_length' => $aggregated_context['total_content_length'],
            'unique_sources' => count($aggregated_context['unique_sources'])
        ]);

        return $aggregated_context;
    }

    /**
     * Build AI request using aggregated context and ai-http-client capabilities
     * 
     * Prepares enhanced request that leverages ai-http-client's prompt building
     * and context injection features for superior AI understanding.
     * 
     * @param array $aggregated_context Context from aggregate_pipeline_context()
     * @param array $ai_step_config AI step configuration from Data Machine
     * @param int|null $pipeline_id Pipeline ID for including pipeline-level prompts
     * @return array Enhanced request ready for ai-http-client->send_request()
     */
    public function build_ai_request(array $aggregated_context, array $ai_step_config, ?int $pipeline_id = null): array {
        $logger = apply_filters('dm_get_logger', null);

        // Extract base configuration
        $base_prompt = $ai_step_config['prompt'] ?? '';
        $model = $ai_step_config['model'] ?? null;
        $temperature = $ai_step_config['temperature'] ?? null;

        if (empty($base_prompt)) {
            $logger->error('FluidContextBridge: No prompt provided in AI step configuration');
            return [];
        }

        // Prepare variables for ai-http-client templating
        $prompt_variables = $this->prepare_prompt_variables($aggregated_context);

        // Build enhanced context for ai-http-client
        $context_data = $this->prepare_context_data($aggregated_context);
        
        // Get step-level prompt configuration from AI step
        $step_prompt_config = null;
        if (class_exists('DataMachine\\Core\\Steps\\AI\\AIStep')) {
            $ai_step = new \DataMachine\Core\Steps\AI\AIStep();
            $step_prompt_config = $ai_step->get_step_configuration($job_id, 'ai');
        }
        
        // Use step-level system prompt if configured, otherwise use base prompt
        $system_prompt = '';
        if ($step_prompt_config && !empty($step_prompt_config['system_prompt'])) {
            $system_prompt = $step_prompt_config['system_prompt'];
        } else {
            $system_prompt = $base_prompt; // Fallback to base prompt
        }

        // Use ai-http-client's PromptManager for enhanced prompt building if available
        if (class_exists('AI_HTTP_Prompt_Manager')) {
            
            // Apply variable replacement to step-level system prompt
            $enhanced_prompt = str_replace(array_keys($prompt_variables), array_values($prompt_variables), $system_prompt);
            
            // Build modular system prompt with critical directives
            $system_sections = ['datetime'];
            
            // Add output formatting directives if output configuration is present
            if (!empty($ai_step_config['output_type']) || !empty($ai_step_config['output_config'])) {
                $system_sections[] = 'output_directives';
            }
            
            $system_context = array_merge($context_data, [
                'output_type' => $ai_step_config['output_type'] ?? '',
                'output_config' => $ai_step_config['output_config'] ?? [],
                'fluid_variables' => $prompt_variables
            ]);

            $enhanced_system = \AI_HTTP_Prompt_Manager::build_modular_system_prompt([
                'sections' => $system_sections,
                'context' => $system_context,
                'plugin_context' => 'data-machine'
            ]);

            // Build user message with enhanced prompt
            $enhanced_user = \AI_HTTP_Prompt_Manager::build_user_prompt($enhanced_prompt, $context_data);

            $messages = \AI_HTTP_Prompt_Manager::build_messages($enhanced_system, $enhanced_user);

        } else {
            // Fallback if ai-http-client PromptManager not available
            $logger->warning('FluidContextBridge: AI_HTTP_Prompt_Manager not available, using fallback');
            
            $enhanced_user_prompt = $this->build_fallback_prompt($base_prompt, $aggregated_context, $prompt_variables);
            
            // Add basic datetime and output directives to fallback system prompt
            $system_prompt = $this->build_fallback_system_prompt($ai_step_config);
            
            $messages = [
                [
                    'role' => 'system',
                    'content' => $system_prompt
                ],
                [
                    'role' => 'user', 
                    'content' => $enhanced_user_prompt
                ]
            ];
        }

        // Build final request
        $request = ['messages' => $messages];

        // Add model and temperature if provided
        if ($model) {
            $request['model'] = $model;
        }
        if ($temperature !== null) {
            $request['temperature'] = $temperature;
        }

        $logger->info('FluidContextBridge: Built enhanced AI request with critical directives', [
            'message_count' => count($messages),
            'context_packets' => count($aggregated_context['pipeline_data'] ?? []),
            'variables_count' => count($prompt_variables),
            'system_sections' => $system_sections ?? ['fallback'],
            'model' => $model
        ]);

        return $request;
    }

    /**
     * Format single DataPacket content for ai-http-client context
     * 
     * Transforms DataPacket structure into format optimized for AI understanding
     * while preserving all relevant information and relationships.
     * 
     * @param object $data_packet DataPacket object to format
     * @return array Formatted context data for single packet
     */
    public function format_datapacket_content(object $data_packet): array {
        $formatted = [
            'content' => [
                'title' => $data_packet->content['title'] ?? '',
                'body' => $data_packet->content['body'] ?? '',
                'summary' => $data_packet->content['summary'] ?? '',
                'tags' => $data_packet->content['tags'] ?? []
            ],
            'metadata' => [
                'source_type' => $data_packet->metadata['source_type'] ?? 'unknown',
                'source_url' => $data_packet->metadata['source_url'] ?? null,
                'date_created' => $data_packet->metadata['date_created'] ?? null,
                'language' => $data_packet->metadata['language'] ?? 'en',
                'format' => $data_packet->metadata['format'] ?? 'text'
            ],
            'processing' => [
                'steps_completed' => $data_packet->processing['steps_completed'] ?? [],
                'ai_model_used' => $data_packet->processing['ai_model_used'] ?? null,
                'tokens_used' => $data_packet->processing['tokens_used'] ?? null
            ],
            'attachments' => [
                'images_count' => count($data_packet->attachments['images'] ?? []),
                'files_count' => count($data_packet->attachments['files'] ?? []),
                'links_count' => count($data_packet->attachments['links'] ?? []),
                'has_media' => !empty($data_packet->attachments['images']) || !empty($data_packet->attachments['files'])
            ],
            'content_length' => $data_packet->getContentLength(),
            'has_content' => $data_packet->hasContent()
        ];

        // Add attachment details if they exist
        if (!empty($data_packet->attachments['images'])) {
            $formatted['attachments']['images'] = $data_packet->attachments['images'];
        }
        if (!empty($data_packet->attachments['links'])) {
            $formatted['attachments']['links'] = array_slice($data_packet->attachments['links'], 0, 5); // Limit to prevent bloat
        }

        return $formatted;
    }

    /**
     * Prepare prompt variables for ai-http-client templating system
     * 
     * Extracts key information from aggregated context and formats it 
     * for use with ai-http-client's {{variable}} replacement system.
     * 
     * @param array $context_data Aggregated context data
     * @return array Variables array for ai-http-client templating
     */
    public function prepare_prompt_variables(array $context_data): array {
        $variables = [];

        // Basic statistics
        $variables['{{packet_count}}'] = $context_data['packet_count'] ?? 0;
        $variables['{{total_content_length}}'] = $context_data['total_content_length'] ?? 0;
        $variables['{{source_count}}'] = count($context_data['unique_sources'] ?? []);

        // Source types summary
        $variables['{{source_types}}'] = !empty($context_data['unique_sources']) 
            ? implode(', ', $context_data['unique_sources']) 
            : 'unknown';

        // Content aggregation
        $titles = [];
        $bodies = [];
        $sources = [];

        foreach ($context_data['pipeline_data'] ?? [] as $packet_data) {
            if (!empty($packet_data['content']['title'])) {
                $titles[] = $packet_data['content']['title'];
            }
            if (!empty($packet_data['content']['body'])) {
                $bodies[] = substr($packet_data['content']['body'], 0, 200); // Preview only
            }
            if (!empty($packet_data['metadata']['source_url'])) {
                $sources[] = $packet_data['metadata']['source_url'];
            }
        }

        $variables['{{all_titles}}'] = implode(' | ', $titles);
        $variables['{{content_previews}}'] = implode(' ... ', $bodies);
        $variables['{{source_urls}}'] = implode(', ', array_unique($sources));

        // Processing summary
        $all_steps = [];
        foreach ($context_data['pipeline_data'] ?? [] as $packet_data) {
            $steps = $packet_data['processing']['steps_completed'] ?? [];
            $all_steps = array_merge($all_steps, $steps);
        }
        $variables['{{processing_steps}}'] = implode(', ', array_unique($all_steps));

        // Attachments summary
        $total_images = array_sum(array_column($context_data['pipeline_data'] ?? [], 'attachments.images_count'));
        $total_files = array_sum(array_column($context_data['pipeline_data'] ?? [], 'attachments.files_count'));
        $variables['{{total_images}}'] = $total_images;
        $variables['{{total_files}}'] = $total_files;
        $variables['{{has_media}}'] = ($total_images > 0 || $total_files > 0) ? 'yes' : 'no';

        return $variables;
    }

    /**
     * Aggregate metadata from individual packet into summary context
     * 
     * @param object $packet Source DataPacket object
     * @param array &$aggregated_context Context array to update (by reference)
     */
    private function aggregate_packet_metadata(object $packet, array &$aggregated_context): void {
        // Track content sources
        if (!empty($packet->metadata['source_url'])) {
            $aggregated_context['content_sources'][] = $packet->metadata['source_url'];
        }

        // Aggregate content length
        $aggregated_context['total_content_length'] += $packet->getContentLength();

        // Track source types
        $aggregated_context['source_types'][] = $packet->metadata['source_type'] ?? 'unknown';

        // Aggregate processing history
        foreach ($packet->processing['steps_completed'] ?? [] as $step) {
            if (!in_array($step, $aggregated_context['processing_history'])) {
                $aggregated_context['processing_history'][] = $step;
            }
        }

        // Summarize attachments
        $image_count = count($packet->attachments['images'] ?? []);
        $file_count = count($packet->attachments['files'] ?? []);
        $link_count = count($packet->attachments['links'] ?? []);

        if (!isset($aggregated_context['attachments_summary']['total_images'])) {
            $aggregated_context['attachments_summary']['total_images'] = 0;
            $aggregated_context['attachments_summary']['total_files'] = 0;
            $aggregated_context['attachments_summary']['total_links'] = 0;
        }

        $aggregated_context['attachments_summary']['total_images'] += $image_count;
        $aggregated_context['attachments_summary']['total_files'] += $file_count;
        $aggregated_context['attachments_summary']['total_links'] += $link_count;
    }

    /**
     * Prepare context data for ai-http-client context injection
     * 
     * @param array $aggregated_context Aggregated pipeline context
     * @return array Context formatted for ai-http-client
     */
    private function prepare_context_data(array $aggregated_context): array {
        return [
            'pipeline_summary' => [
                'total_packets' => $aggregated_context['packet_count'] ?? 0,
                'content_length' => $aggregated_context['total_content_length'] ?? 0,
                'source_types' => implode(', ', $aggregated_context['unique_sources'] ?? []),
                'processing_steps' => implode(', ', $aggregated_context['processing_history'] ?? [])
            ],
            'content_aggregate' => $aggregated_context['pipeline_data'] ?? [],
            'attachments' => $aggregated_context['attachments_summary'] ?? []
        ];
    }

    /**
     * Build fallback prompt when ai-http-client PromptManager is not available
     * 
     * @param string $base_prompt Base prompt from configuration
     * @param array $aggregated_context Aggregated context data
     * @param array $variables Variables for replacement
     * @return string Enhanced prompt
     */
    private function build_fallback_prompt(string $base_prompt, array $aggregated_context, array $variables): string {
        // Apply variable replacement manually
        $enhanced_prompt = str_replace(array_keys($variables), array_values($variables), $base_prompt);

        // Add basic context
        $enhanced_prompt .= "\n\nPipeline Context:\n";
        $enhanced_prompt .= "- Total content packets: " . ($aggregated_context['packet_count'] ?? 0) . "\n";
        $enhanced_prompt .= "- Source types: " . implode(', ', $aggregated_context['unique_sources'] ?? []) . "\n";
        $enhanced_prompt .= "- Total content length: " . ($aggregated_context['total_content_length'] ?? 0) . " characters\n";

        // Add content summary
        $enhanced_prompt .= "\nContent to process:\n";
        foreach ($aggregated_context['pipeline_data'] ?? [] as $index => $packet_data) {
            $enhanced_prompt .= "Source " . ($index + 1) . ":\n";
            if (!empty($packet_data['content']['title'])) {
                $enhanced_prompt .= "Title: " . $packet_data['content']['title'] . "\n";
            }
            if (!empty($packet_data['content']['body'])) {
                $enhanced_prompt .= "Content: " . $packet_data['content']['body'] . "\n";
            }
            $enhanced_prompt .= "---\n";
        }

        return $enhanced_prompt;
    }

    /**
     * Build fallback system prompt when ai-http-client PromptManager is not available
     * 
     * @param array $ai_step_config AI step configuration
     * @return string System prompt
     */
    private function build_fallback_system_prompt(array $ai_step_config): string {
        $system_prompt = "Current date and time: " . gmdate('Y-m-d H:i:s T') . "\n\n";
        
        // Add output directives if configured
        if (!empty($ai_step_config['output_type'])) {
            $system_prompt .= "Output format required: " . $ai_step_config['output_type'] . "\n";
        }
        
        if (!empty($ai_step_config['output_config'])) {
            $system_prompt .= "Output configuration: " . json_encode($ai_step_config['output_config']) . "\n";
        }
        
        return $system_prompt;
    }
}