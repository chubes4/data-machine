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
 * - dm_get_pipeline_prompt: Retrieve pipeline-level prompts for AI steps
 * - dm_save_pipeline_prompt: Save pipeline-level prompts (future)
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
                'description' => __('Process content using AI models', 'data-machine'),
                'class' => 'DataMachine\\Core\\Steps\\AI\\AIStep',
                'consume_all_packets' => true
            ];
        }
        return $step_config;
    }, 10, 2);
    
    /**
     * Pipeline Prompt System - AI Step Exclusive
     * 
     * Provides pipeline-level prompts that apply to all AI steps in a pipeline.
     * This was the valuable "project prompt" concept adapted for modern pipeline architecture.
     * 
     * Usage: $prompts = apply_filters('dm_get_pipeline_prompt', null, $pipeline_id);
     * 
     * Expected Return Format:
     * [
     *     'ai' => 'Pipeline-level prompt for AI steps',
     *     // Future: additional step names can be supported
     * ]
     * 
     * @param mixed $prompts Existing prompts (null if none)
     * @param int $pipeline_id Pipeline ID
     * @return array|null Pipeline prompts or null if not found
     */
    add_filter('dm_get_pipeline_prompt', function($prompts, $pipeline_id) {
        if ($prompts !== null) {
            return $prompts; // External override provided
        }
        
        if (empty($pipeline_id)) {
            return null;
        }
        
        // Use existing pipelines database service
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        if (!$db_pipelines) {
            return null;
        }
        
        // Get pipeline configuration
        $pipeline = $db_pipelines->get_pipeline($pipeline_id);
        if (!$pipeline) {
            return null;
        }
        
        // Extract pipeline prompt from configuration
        // For now, use a simple option-based storage
        // Future: integrate with pipeline configuration JSON
        $pipeline_prompt_key = "dm_pipeline_prompt_{$pipeline_id}";
        $pipeline_prompt = get_option($pipeline_prompt_key, '');
        
        if (empty($pipeline_prompt)) {
            return null;
        }
        
        // Return in format expected by existing AI step code
        // AIStep calls: get_step_configuration($job_id, 'ai')
        // So it expects: $step_prompts['ai'] = $prompt_text
        // FluidContextBridge expects: array_keys($pipeline_prompts) for logging
        
        return [
            'ai' => $pipeline_prompt // Matches AIStep expectation
        ];
        
    }, 10, 2);
    
    /**
     * Save Pipeline Prompt Filter
     * 
     * Allows saving pipeline-level prompts. Currently uses WordPress options
     * but can be enhanced to integrate with pipeline configuration storage.
     * 
     * Usage: $result = apply_filters('dm_save_pipeline_prompt', null, $pipeline_id, $prompt);
     * 
     * @param mixed $result Current save result (null if not handled)
     * @param int $pipeline_id Pipeline ID
     * @param string $prompt Prompt text to save
     * @return bool Save success status
     */
    add_filter('dm_save_pipeline_prompt', function($result, $pipeline_id, $prompt) {
        if ($result !== null) {
            return $result; // External override provided
        }
        
        if (empty($pipeline_id)) {
            return false;
        }
        
        // Validate pipeline exists
        $db_pipelines = apply_filters('dm_get_database_service', null, 'pipelines');
        if (!$db_pipelines || !$db_pipelines->get_pipeline($pipeline_id)) {
            return false;
        }
        
        // Save pipeline prompt
        $pipeline_prompt_key = "dm_pipeline_prompt_{$pipeline_id}";
        return update_option($pipeline_prompt_key, sanitize_textarea_field($prompt));
        
    }, 10, 3);
    
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
    
    // Future AI-specific filters can be added here following the same pattern
    // Examples:
    // - dm_get_ai_step_config (if we need step-specific AI configuration)
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