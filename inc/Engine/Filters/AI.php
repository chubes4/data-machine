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
        
        // Use existing filter instead of manual pipeline search
        $step_config = apply_filters('dm_get_pipeline_step_config', [], $pipeline_step_id);
        
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