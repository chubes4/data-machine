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
            'name' => __('Repurpose Content', 'data-machine'),
            'description' => __('Get content from anywhere → AI optimizes it → Share it elsewhere', 'data-machine'),
            'steps' => [
                ['type' => 'fetch'],
                ['type' => 'ai'],
                ['type' => 'publish']
            ]
        ];

        $templates['ai_publish'] = [
            'id' => 'ai_publish',
            'name' => __('Create Content', 'data-machine'),
            'description' => __('AI creates original content → Publish it anywhere', 'data-machine'),
            'steps' => [
                ['type' => 'ai'],
                ['type' => 'publish']
            ]
        ];

        $templates['fetch_ai_update'] = [
            'id' => 'fetch_ai_update',
            'name' => __('Refresh Content', 'data-machine'),
            'description' => __('Get existing content → AI refreshes it → Update the original', 'data-machine'),
            'steps' => [
                ['type' => 'fetch'],
                ['type' => 'ai'],
                ['type' => 'update']
            ]
        ];

        $templates['ai_update'] = [
            'id' => 'ai_update',
            'name' => __('Improve Content', 'data-machine'),
            'description' => __('AI improves content you already have → Updates it automatically', 'data-machine'),
            'steps' => [
                ['type' => 'ai'],
                ['type' => 'update']
            ]
        ];

        return $templates;
    });
}
dm_register_pipeline_templates();