<?php
/**
 * Data Machine Engine - AI Integration Filters
 *
 * AI-specific backend processing filters that bridge Data Machine's pipeline system
 * with the AI HTTP Client library and 6-tier directive system for step-aware AI
 * configuration and conversation management.
 *
 * Key Filters:
 * - dm_ai_config: Provides step configuration from pipeline database
 * - dm_tool_success_message: Tool result message formatting customization
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register AI integration filters for 6-tier directive system support.
 * Provides step-aware AI configuration from pipeline database with support
 * for multi-turn conversation management and tool result formatting.
 */
function dm_register_ai_filters() {
    
    add_filter('dm_ai_config', function($default, $pipeline_step_id = null) {
        
        if (!$pipeline_step_id) {
            return [];
        }
        
        $all_databases = apply_filters('dm_db', []);
        $db_pipelines = $all_databases['pipelines'] ?? null;
        
        if (!$db_pipelines) {
            do_action('dm_log', 'error', 'AI Config: Pipeline database service not available');
            return [];
        }
        
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
            
            return [
                'selected_provider' => '',
                'system_prompt' => '',
                'model' => ''
            ];
        }
        
        $selected_provider = $step_config['provider'] ?? '';
        $system_prompt = $step_config['system_prompt'] ?? '';
        
        $config = [
            'selected_provider' => $selected_provider,
            'system_prompt' => $system_prompt,
            'model' => $step_config['model'] ?? '',
            'enabled_tools' => $step_config['enabled_tools'] ?? []
        ];
        
        
        return $config;
    }, 20, 2);
    
}

// Initialize AI filters on WordPress init
add_action('init', 'dm_register_ai_filters');