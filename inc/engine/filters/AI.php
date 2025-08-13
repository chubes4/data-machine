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
    
    // AI Configuration System - Data Machine step-aware configuration
    // Provides configuration for AI HTTP Client by reading from pipeline database and WordPress options
    // Usage: Applied automatically when pipeline_step_id is provided in ai_request filter
    add_filter('ai_config', function($pipeline_step_id = null) {
        // Get shared API keys from WordPress options (library expects individual options)
        $providers = ['openai', 'anthropic', 'openrouter', 'google', 'grok'];
        $shared_api_keys = [];
        
        foreach ($providers as $provider) {
            $api_key = get_option($provider . '_api_key', '');
            if (!empty($api_key)) {
                $shared_api_keys[$provider] = ['api_key' => $api_key];
            }
        }
        
        // If no pipeline_step_id provided, only return shared API keys
        if (!$pipeline_step_id) {
            do_action('dm_log', 'debug', 'AI Config: Returning shared API keys only', [
                'providers_with_keys' => array_keys($shared_api_keys)
            ]);
            return $shared_api_keys;
        }
        
        // Get step configuration from pipeline database using pipeline_step_id
        // First, find which pipeline contains this step
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        
        if (!$db_pipelines) {
            do_action('dm_log', 'error', 'AI Config: Pipeline database service not available');
            return $shared_api_keys;
        }
        
        // Get all pipelines and search for the one containing this step
        $pipelines = $db_pipelines->get_all_pipelines();
        $step_config = null;
        
        foreach ($pipelines as $pipeline) {
            $step_configuration = is_string($pipeline['step_configuration']) 
                ? json_decode($pipeline['step_configuration'], true) 
                : ($pipeline['step_configuration'] ?? []);
                
            if (isset($step_configuration[$pipeline_step_id])) {
                $step_config = $step_configuration[$pipeline_step_id];
                break;
            }
        }
        
        if (empty($step_config)) {
            do_action('dm_log', 'debug', 'AI Config: No step configuration found in pipeline database', [
                'pipeline_step_id' => $pipeline_step_id
            ]);
            return $shared_api_keys;
        }
        
        // Extract AI configuration from step config
        $selected_provider = $step_config['provider'] ?? 'openai';
        $temperature = isset($step_config['temperature']) ? (float) $step_config['temperature'] : 0.7;
        $system_prompt = $step_config['system_prompt'] ?? '';
        
        // Build configuration structure that matches library expectations
        $config = [
            'selected_provider' => $selected_provider,
            'temperature' => $temperature,
            'system_prompt' => $system_prompt,
            'providers' => []
        ];
        
        // Add all providers with API keys and saved models
        foreach ($shared_api_keys as $provider_name => $provider_data) {
            $config['providers'][$provider_name] = [
                'api_key' => $provider_data['api_key']
            ];
            
            // Add saved model for this provider if exists in step config
            if (isset($step_config['providers'][$provider_name]['model'])) {
                $config['providers'][$provider_name]['model'] = $step_config['providers'][$provider_name]['model'];
            } elseif ($provider_name === $selected_provider && isset($step_config['model'])) {
                // Legacy: also check for model directly on step config
                $config['providers'][$provider_name]['model'] = $step_config['model'];
            }
        }
        
        // Add top-level model for currently selected provider (for template compatibility)
        if (isset($config['providers'][$selected_provider]['model'])) {
            $config['model'] = $config['providers'][$selected_provider]['model'];
        } elseif (isset($step_config['model'])) {
            $config['model'] = $step_config['model'];
        }
        
        do_action('dm_log', 'debug', 'AI Config: Retrieved step configuration from pipeline database', [
            'pipeline_step_id' => $pipeline_step_id,
            'selected_provider' => $selected_provider,
            'has_temperature' => ($temperature !== null),
            'has_system_prompt' => !empty($system_prompt),
            'has_model' => !empty($config['model']),
            'providers_count' => count($config['providers'])
        ]);
        
        return $config;
    }, 20, 1); // Priority 20 to override library's priority 5
    
    // save_ai_config action removed - Data Machine handles configuration saving directly in PipelineModalAjax.php
}

// Initialize AI filters on WordPress init
add_action('init', 'dm_register_ai_filters');