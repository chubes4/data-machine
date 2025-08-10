<?php
/**
 * AI Step Filters Registration
 *
 * WordPress-Native AI Processing
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
 * - dm_steps: Register AI step for pipeline discovery * 
 * @since 1.0.0
 */
function dm_register_ai_step_filters() {
    
    /**
     * AI Step Registration
     * 
     * Register the AI step type for pipeline discovery via pure discovery mode.
     * This enables the AI step to be discovered and used in pipelines.
     * 
     * @param array $steps Current steps array
     * @return array Updated steps array
     */
    add_filter('dm_steps', function($steps) {
        $steps['ai'] = [
            'label' => __('AI Processing', 'data-machine'),
            'description' => __('Configure a custom prompt to process data through any LLM provider (OpenAI, Anthropic, Google, Grok, OpenRouter)', 'data-machine'),
            'class' => 'DataMachine\\Core\\Steps\\AI\\AIStep',
            'consume_all_packets' => true,
            'position' => 20
        ];
        return $steps;
    });
    
    
    // DataPacket conversion registration - AI step uses dedicated DataPacket class
    // DataPacket creation removed - engine uses universal DataPacket constructor
    // AI steps return properly formatted data for direct constructor usage
    
    // AI service registrations removed - AIStep now works directly with AI HTTP Client
    
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
    add_filter('dm_step_settings', function($configs) {
        $configs['ai'] = [
            'config_type' => 'ai_configuration',
            'modal_type' => 'configure-step', // Links to existing modal content registration
            'button_text' => __('Configure', 'data-machine'),
            'label' => __('AI Configuration', 'data-machine')
        ];
        return $configs;
    });
    
    // Modal registration removed - configure-step modal already registered in PipelinesFilters.php
    // The shared configure-step modal handles all step types via template switching
    
}

/**
 * Auto-register AI step filters when this file is loaded.
 * 
 * This follows the self-registration pattern established throughout Data Machine.
 * The dm_autoload_core_steps() function will load this file, and filters
 * will be automatically registered without any bootstrap modifications.
 */
dm_register_ai_step_filters();