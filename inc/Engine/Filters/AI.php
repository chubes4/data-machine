<?php
/**
 * AI integration filters bridging pipeline system with AI HTTP Client.
 *
 * Provides step-aware AI configuration from pipeline database with support
 * for multi-turn conversation management and 6-tier directive system.
 *
 * @package DataMachine\Engine\Filters
 */

defined('WPINC') || exit;

/**
 * Register AI integration filters for pipeline-aware AI configuration.
 */
function dm_register_ai_filters() {
    
    add_filter('dm_ai_config', function($default, $pipeline_step_id = null) {
        if (!$pipeline_step_id) {
            return [];
        }

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

        return [
            'selected_provider' => $step_config['provider'] ?? '',
            'system_prompt' => $step_config['system_prompt'] ?? '',
            'model' => $step_config['model'] ?? '',
            'enabled_tools' => $step_config['enabled_tools'] ?? []
        ];
    }, 20, 2);
    
}

// Initialize AI filters on WordPress init
add_action('init', 'dm_register_ai_filters');