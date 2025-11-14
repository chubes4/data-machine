<?php
/**
 * AI integration filters bridging pipeline system with AI HTTP Client.
 *
 * Provides step-aware AI configuration from pipeline database with support
 * for multi-turn conversation management and 5-tier directive system.
 *
 * @package DataMachine\Engine\Filters
 */

defined('WPINC') || exit;

/**
 * Register AI integration filters for pipeline-aware AI configuration.
 */
function datamachine_register_ai_filters() {

    add_filter('datamachine_ai_config', function($default, $pipeline_step_id = null, array $context = []) {
        if (!$pipeline_step_id) {
            return [];
        }

        $job_id = $context['job_id'] ?? null;

        if (!$job_id && empty($context['engine_data'])) {
            do_action('datamachine_log', 'debug', 'AI Config: No execution context available', [
                'pipeline_step_id' => $pipeline_step_id
            ]);
            return [
                'selected_provider' => '',
                'system_prompt' => '',
                'model' => ''
            ];
        }

        $engine_data = $context['engine_data'] ?? apply_filters('datamachine_engine_data', [], $job_id);
        $pipeline_config = $engine_data['pipeline_config'] ?? [];

        $step_config = $pipeline_config[$pipeline_step_id] ?? [];

        if (empty($step_config)) {
            do_action('datamachine_log', 'debug', 'AI Config: No step configuration found in engine data', [
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
    }, 20, 3);

}

// Initialize AI filters on WordPress init
add_action('init', 'datamachine_register_ai_filters');