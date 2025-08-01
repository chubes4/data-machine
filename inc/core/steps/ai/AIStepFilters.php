<?php
/**
 * AI Step Filters Registration
 *
 * GROUNDBREAKING AI INTEGRATION: WordPress-Native AI Processing
 * 
 * This file enables sophisticated AI workflows through comprehensive self-registration,
 * making AI step functionality completely modular and WordPress-native.
 * 
 * AI Innovation Features:
 * - Multi-provider AI client integration (OpenAI, Anthropic, Google, Grok, OpenRouter)
 * - Intelligent pipeline context management and processing
 * - Universal DataPacket conversion for AI workflows
 * - Self-contained AI component architecture
 * 
 * Implementation Pattern:
 * Components self-register via dedicated *Filters.php files, enabling:
 * - Modular functionality without bootstrap modifications
 * - Clean separation of AI logic from core architecture
 * - Template for extensible AI component development
 *
 * @package DataMachine\Core\Steps\AI
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\AI;

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register AI step-specific filters.
 * 
 * Called automatically when AI step components are loaded via dm_autoload_core_steps().
 * This maintains the self-registration pattern and keeps AI functionality self-contained.
 * 
 * Registered Filters:
 * - dm_get_steps: Register AI step for pipeline discovery
 * - dm_create_datapacket: AI-specific DataPacket creation
 * - Service filters: FluidContextBridge, AiResponseParser, PromptBuilder
 * 
 * @since 1.0.0
 */
function dm_register_ai_step_filters() {
    
    /**
     * AI Step Registration
     * 
     * Register the AI step type for pipeline discovery via parameter-based filter system.
     * This enables the AI step to be discovered and used in pipelines.
     * 
     * @param mixed $step_config Current step configuration (null if none)
     * @param string $step_type Step type being requested
     * @return array|mixed Step configuration or original value
     */
    add_filter('dm_get_steps', function($step_config, $step_type) {
        if ($step_type === 'ai') {
            return [
                'label' => __('AI Processing', 'data-machine'),
                'description' => __('Configure a custom prompt to process data through any LLM provider (OpenAI, Anthropic, Google, Grok, OpenRouter)', 'data-machine'),
                'class' => 'DataMachine\\Core\\Steps\\AI\\AIStep',
                'consume_all_packets' => true
            ];
        }
        return $step_config;
    }, 10, 2);
    
    
    // DataPacket conversion registration - AI step uses dedicated DataPacket class
    add_filter('dm_create_datapacket', function($datapacket, $source_data, $source_type, $context) {
        if ($source_type === 'ai') {
            return AIStepDataPacket::create($source_data, $context);
        }
        return $datapacket;
    }, 10, 4);
    
    // AI service registrations - components that belong with AI step logic
    
    // Fluid Context Bridge service - AI context management
    add_filter('dm_get_fluid_context_bridge', function($service) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        static $bridge_instance = null;
        if ($bridge_instance === null) {
            $bridge_instance = new FluidContextBridge();
        }
        return $bridge_instance;
    }, 10);
    
    // AI Response Parser service - AI response processing
    add_filter('dm_get_ai_response_parser', function($service) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        static $parser_instance = null;
        if ($parser_instance === null) {
            $parser_instance = new AiResponseParser();
        }
        return $parser_instance;
    }, 10);
    
    // Prompt Builder service - AI prompt construction
    add_filter('dm_get_prompt_builder', function($service) {
        if ($service !== null) {
            return $service; // External override provided
        }
        
        static $builder_instance = null;
        if ($builder_instance === null) {
            $builder_instance = new PromptBuilder();
            $builder_instance->register_all_sections();
        }
        return $builder_instance;
    }, 10);
    
    /**
     * AI Step Modal Content Registration
     * 
     * Register modal content for AI step configuration using AI HTTP Client components.
     * This enables step-level prompt configuration, model selection, and AI parameters.
     * 
     * @param mixed $content Current modal content (null if none)
     * @param string $modal_type Modal type identifier
     * @param array $context Modal context data
     * @return string|mixed Modal HTML content or original value
     */
    add_filter('dm_get_modal_content', function($content, $modal_type, $context = []) {
        // Handle AI step configuration modals
        if ($modal_type === 'ai_step_config' && $content === null) {
            
            // Extract step key from context for scoped configuration
            $step_key = $context['step_key'] ?? $context['job_id'] . '_ai_' . time(); 
            
            // Check if AI HTTP Client components are available
            if (class_exists('AI_HTTP_ProviderManager_Component')) {
                
                // Render AI HTTP Client configuration interface
                return AI_HTTP_ProviderManager_Component::render([
                    'plugin_context' => 'data-machine',
                    'ai_type' => 'llm',
                    'step_key' => $step_key,
                    'components' => [
                        'core' => ['provider_selector', 'api_key_input', 'model_selector'],
                        'extended' => ['system_prompt_field', 'temperature_slider']
                    ],
                    'show_test_connection' => true,
                    'compact_mode' => true // Optimized for modal display
                ]);
            }
            
            // Fallback if AI HTTP Client components unavailable
            return '<div class="dm-ai-config-fallback">' .
                   '<p>' . __('AI HTTP Client components not available. Please ensure the AI HTTP Client library is properly loaded.', 'data-machine') . '</p>' .
                   '</div>';
        }
        
        return $content;
    }, 10, 3);
    
    /**
     * AI Step Configuration Registration
     * 
     * Register AI step configuration capability using admin-defined dm_get_step_config filter.
     * This enables "Configure AI" button to appear in pipeline step cards and links to 
     * the existing ai_step_config modal content we already implemented.
     * 
     * @param mixed $config Current step configuration (null if none)
     * @param string $step_type Step type being requested
     * @param array $context Step context data
     * @return array|mixed Step configuration or original value
     */
    add_filter('dm_get_step_config', function($config, $step_type, $context) {
        if ($step_type === 'ai') {
            return [
                'config_type' => 'ai_configuration',
                'modal_type' => 'ai_step_config', // Links to existing modal content registration
                'button_text' => __('Configure AI', 'data-machine'),
                'label' => __('AI Configuration', 'data-machine')
            ];
        }
        return $config;
    }, 10, 3);
    
    // Future AI-specific filters can be added here following the same pattern
    // Examples:
    // - dm_validate_ai_output (for AI response validation)
    // - dm_process_ai_context (for context preprocessing)
}

/**
 * Auto-register AI step filters when this file is loaded.
 * 
 * This follows the self-registration pattern established throughout Data Machine.
 * The dm_autoload_core_steps() function will load this file, and filters
 * will be automatically registered without any bootstrap modifications.
 */
dm_register_ai_step_filters();