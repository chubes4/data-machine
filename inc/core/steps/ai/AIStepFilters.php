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
    add_filter('dm_get_steps', function($step_config, $step_type = null) {
        // Discovery mode: return all steps when no type specified
        if (empty($step_type)) {
            return array_merge($step_config ?: [], [
                'ai' => [
                    'label' => __('AI Processing', 'data-machine'),
                    'description' => __('Configure a custom prompt to process data through any LLM provider (OpenAI, Anthropic, Google, Grok, OpenRouter)', 'data-machine'),
                    'class' => 'DataMachine\\Core\\Steps\\AI\\AIStep',
                    'consume_all_packets' => true,
                    'position' => 20
                ]
            ]);
        }
        
        // Specific mode: return step config for matching type
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
     * AI Step Configuration Registration
     * 
     * Register AI step configuration capability so the pipeline step card shows Configure button.
     * This tells the step-card template that AI steps have configurable options.
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
                'modal_type' => 'configure-step', // Links to existing modal content registration
                'button_text' => __('Configure', 'data-machine'),
                'label' => __('AI Configuration', 'data-machine')
            ];
        }
        return $config;
    }, 10, 3);
    
    /**
     * AI Step Configuration Modal Content Registration
     * 
     * Register modal content for ai_step_config template using the AI HTTP Client
     * ProviderManagerComponent. This provides a complete AI configuration interface
     * with provider selection, API keys, model selection, and advanced settings.
     * 
     * @param mixed $content Current modal content (null if none)
     * @param string $template Modal template being requested
     * @return string|mixed Modal HTML content or original value
     */
    add_filter('dm_get_modal', function($content, $template) {
        if ($template === 'configure-step') {
            // Get context from $_POST directly (like templates access other data)
            $context_raw = wp_unslash($_POST['context'] ?? '{}');
            $context = json_decode($context_raw, true);
            $step_type = $context['step_type'] ?? 'unknown';
            
            // Only handle AI step configuration
            if ($step_type !== 'ai') {
                return $content;
            }
            
            // Generate consistent step key aligned with AIStep implementation
            $pipeline_id = $context['pipeline_id'] ?? null;
            $current_step = $context['step_name'] ?? $context['current_step'] ?? 'ai_processing';
            $step_key = $pipeline_id ? "pipeline_{$pipeline_id}_step_{$current_step}" : "temp_ai_step_" . time();
            
            // Use AI HTTP Client ProviderManagerComponent for complete AI configuration
            if (class_exists('AI_HTTP_ProviderManager_Component')) {
                return \AI_HTTP_ProviderManager_Component::render([
                    'plugin_context' => 'data-machine',
                    'ai_type' => 'llm',
                    'title' => __('AI Step Configuration', 'data-machine'),
                    'components' => [
                        'core' => ['provider_selector', 'api_key_input', 'model_selector'],
                        'extended' => ['temperature_slider', 'system_prompt_field']
                    ],
                    'show_test_connection' => false,
                    'wrapper_class' => 'ai-http-provider-manager dm-ai-step-config',
                    'step_key' => $step_key, // Step-aware configuration
                    'component_configs' => [
                        'temperature_slider' => [
                            'min' => 0,
                            'max' => 1,
                            'step' => 0.1,
                            'default_value' => 0.7
                        ],
                        'system_prompt_field' => [
                            'placeholder' => __('Enter system prompt for this AI step...', 'data-machine'),
                            'rows' => 4
                        ]
                    ]
                ]);
            }
            
            // Fallback if AI HTTP Client is not available
            return '<div class="dm-ai-config-error">
                <h4>' . __('AI Configuration Unavailable', 'data-machine') . '</h4>
                <p>' . __('The AI HTTP Client library is required for AI step configuration. Please ensure the library is properly loaded.', 'data-machine') . '</p>
                <p><em>' . __('Expected class: AI_HTTP_ProviderManager_Component', 'data-machine') . '</em></p>
            </div>';
        }
        return $content;
    }, 10, 2);
    
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