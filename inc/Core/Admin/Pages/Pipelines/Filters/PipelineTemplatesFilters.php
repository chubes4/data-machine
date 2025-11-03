<?php
/**
 * Pipeline Templates Filters
 *
 * Registers core pipeline templates for guided pipeline creation.
 * Templates provide pre-configured step sequences for common workflows.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Filters
 * @since 1.0.0
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Register pipeline templates for guided creation
 */
function dm_register_pipeline_templates() {
    add_filter('dm_pipeline_templates', function($templates) {
        $templates['fetch_ai_publish'] = [
            'id' => 'fetch_ai_publish',
            'name' => __('Fetch → Process → Publish', 'data-machine'),
            'description' => __('Pull content from a source, process it with AI, then publish to a platform', 'data-machine'),
            'steps' => [
                ['type' => 'fetch'],
                ['type' => 'ai'],
                ['type' => 'publish']
            ]
        ];

        $templates['ai_publish'] = [
            'id' => 'ai_publish',
            'name' => __('Generate → Publish', 'data-machine'),
            'description' => __('AI generates new content, then publishes it to a platform', 'data-machine'),
            'steps' => [
                ['type' => 'ai'],
                ['type' => 'publish']
            ]
        ];

        $templates['fetch_ai_update'] = [
            'id' => 'fetch_ai_update',
            'name' => __('Fetch → Process → Update', 'data-machine'),
            'description' => __('Pull existing content, process it with AI, then update the WordPress post', 'data-machine'),
            'steps' => [
                ['type' => 'fetch'],
                ['type' => 'ai'],
                ['type' => 'update']
            ]
        ];

        $templates['ai_update'] = [
            'id' => 'ai_update',
            'name' => __('Process → Update', 'data-machine'),
            'description' => __('AI processes content in your system, then updates the WordPress post', 'data-machine'),
            'steps' => [
                ['type' => 'ai'],
                ['type' => 'update']
            ]
        ];

        return $templates;
    });
}
dm_register_pipeline_templates();