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
    add_filter('ai_config', function($default, $pipeline_step_id = null) {
        // Get shared API keys via unified filter
        $shared_api_keys = apply_filters('ai_provider_api_keys', null);
        $shared_api_keys = is_array($shared_api_keys) ? $shared_api_keys : [];

        // If no pipeline_step_id provided, only return shared API keys in expected structure
        if (!$pipeline_step_id) {
            do_action('dm_log', 'debug', 'AI Config: Returning shared API keys only', [
                'providers_with_keys' => array_keys($shared_api_keys)
            ]);
            return array_map(function($key) { return ['api_key' => $key]; }, $shared_api_keys);
        }
        
        // Get step configuration from pipeline database using pipeline_step_id
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        
        if (!$db_pipelines) {
            do_action('dm_log', 'error', 'AI Config: Pipeline database service not available');
            // Return shared keys in expected structure
            return array_map(function($key) { return ['api_key' => $key]; }, $shared_api_keys);
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
            
            // Return properly structured default config for unconfigured steps
            return [
                'selected_provider' => '',
                'providers' => array_map(function($key) { return ['api_key' => $key]; }, $shared_api_keys),
                'temperature' => 0.7,
                'system_prompt' => '',
                'model' => ''
            ];
        }
        
        // Extract AI configuration from step config
        $selected_provider = $step_config['provider'] ?? '';
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
        foreach ($shared_api_keys as $provider_name => $api_key_value) {
            $config['providers'][$provider_name] = [
                'api_key' => $api_key_value
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
        if ($selected_provider && isset($config['providers'][$selected_provider]['model'])) {
            $config['model'] = $config['providers'][$selected_provider]['model'];
        } elseif (isset($step_config['model'])) {
            $config['model'] = $step_config['model'];
        } else {
            $config['model'] = '';
        }
        
        // Compute additional debug info before returning
        $providers_with_keys = array();
        foreach ($config['providers'] as $pname => $pdata) {
            if (!empty($pdata['api_key'])) {
                $providers_with_keys[] = $pname;
            }
        }
        $selected_provider_has_key = $selected_provider && !empty($config['providers'][$selected_provider]['api_key'] ?? '');
        $selected_provider_model_present = $selected_provider && isset($config['providers'][$selected_provider]['model']) && !empty($config['providers'][$selected_provider]['model']);
        
        do_action('dm_log', 'debug', 'AI Config: Retrieved step configuration from pipeline database', [
            'pipeline_step_id' => $pipeline_step_id,
            'selected_provider' => $selected_provider,
            'has_temperature' => ($temperature !== null),
            'has_system_prompt' => !empty($system_prompt),
            'has_model' => !empty($config['model']),
            'selected_provider_has_key' => $selected_provider_has_key,
            'selected_provider_model_present' => $selected_provider_model_present,
            'providers_with_keys' => $providers_with_keys,
            'providers_count' => count($config['providers'])
        ]);
        
        return $config;
    }, 20, 2);
}

// Initialize AI filters on WordPress init
add_action('init', 'dm_register_ai_filters');