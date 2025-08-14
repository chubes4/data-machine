<?php
/**
 * Data Machine Engine - AI Integration Filters
 *
 * AI-specific backend processing filters that bridge Data Machine's pipeline system
 * with the AI HTTP Client library for step-aware AI configuration.
 * 
 * Key Filters:
 * - ai_config: Provides step configuration from pipeline database
 * - save_ai_config: Handles AI configuration persistence
 *
 * @package DataMachine
 * @subpackage Engine\Filters
 * @since 0.1.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register AI integration filters.
 * 
 * Provides Data Machine's implementation of AI HTTP Client interfaces,
 * enabling step-aware AI configuration stored in pipeline database.
 * 
 * @since 0.1.0
 */
function dm_register_ai_filters() {
    
    // Data Machine AI Configuration System - Pipeline step-aware configuration
    // Provides configuration for Data Machine AI steps by reading from pipeline database and WordPress options
    // Usage: $config = apply_filters('dm_ai_config', $default, $pipeline_step_id);
    add_filter('dm_ai_config', function($default, $pipeline_step_id = null) {
        // Data Machine AI config only returns pipeline step configuration
        // API keys are handled entirely by the AI HTTP Client library
        
        if (!$pipeline_step_id) {
            return []; // No step ID means no configuration
        }
        
        // Get step configuration from pipeline database using pipeline_step_id
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        
        if (!$db_pipelines) {
            do_action('dm_log', 'error', 'AI Config: Pipeline database service not available');
            return [];
        }
        
        // Get all pipelines and search for the one containing this step
        $pipelines = $db_pipelines->get_all_pipelines();
        $step_config = null;
        
        foreach ($pipelines as $pipeline) {
            $pipeline_config = is_string($pipeline['pipeline_config']) 
                ? json_decode($pipeline['pipeline_config'], true) 
                : ($pipeline['pipeline_config'] ?? []);
                
            if (!empty($pipeline_step_id) && is_scalar($pipeline_step_id) && isset($pipeline_config[$pipeline_step_id])) {
                $step_config = $pipeline_config[$pipeline_step_id];
                break;
            }
        }
        
        if (empty($step_config)) {
            do_action('dm_log', 'debug', 'AI Config: No step configuration found in pipeline database', [
                'pipeline_step_id' => $pipeline_step_id
            ]);
            
            // Return empty config for unconfigured steps - no API keys needed
            return [
                'selected_provider' => '',
                'temperature' => 0.7,
                'system_prompt' => '',
                'model' => ''
            ];
        }
        
        // Extract AI configuration from step config
        $selected_provider = $step_config['provider'] ?? '';
        $temperature = isset($step_config['temperature']) ? (float) $step_config['temperature'] : 0.7;
        $system_prompt = $step_config['system_prompt'] ?? '';
        
        // Build clean configuration structure - no API keys needed
        $config = [
            'selected_provider' => $selected_provider,
            'temperature' => $temperature,
            'system_prompt' => $system_prompt,
            'model' => $step_config['model'] ?? ''
        ];
        
        // Simple debug logging - no API key info needed
        do_action('dm_log', 'debug', 'AI Config: Retrieved step configuration from pipeline database', [
            'pipeline_step_id' => $pipeline_step_id,
            'selected_provider' => $selected_provider,
            'has_temperature' => ($temperature !== null),
            'has_system_prompt' => !empty($system_prompt),
            'has_model' => !empty($config['model'])
        ]);
        
        return $config;
    }, 20, 2);
}

// Initialize AI filters on WordPress init
add_action('init', 'dm_register_ai_filters');