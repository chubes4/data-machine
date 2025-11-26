<?php
/**
 * Defaults Injector for Execute Workflow Tool
 *
 * Injects default values from plugin settings into workflow steps.
 * Handles provider, model, and post_author defaults.
 *
 * @package DataMachine\Api\Chat\Tools\ExecuteWorkflow
 * @since 0.3.0
 */

namespace DataMachine\Api\Chat\Tools\ExecuteWorkflow;

use DataMachine\Core\PluginSettings;

if (!defined('ABSPATH')) {
    exit;
}

class DefaultsInjector {

    /**
     * Inject default values into workflow steps.
     *
     * @param array $steps Workflow steps
     * @return array Steps with defaults injected
     */
    public static function inject(array $steps): array {
        $injected_steps = [];

        foreach ($steps as $step) {
            $injected_steps[] = self::injectStepDefaults($step);
        }

        return $injected_steps;
    }

    /**
     * Inject defaults into a single step.
     *
     * @param array $step Step configuration
     * @return array Step with defaults injected
     */
    private static function injectStepDefaults(array $step): array {
        $type = $step['type'] ?? '';

        if ($type === 'ai') {
            $step = self::injectAIDefaults($step);
        }

        if ($type === 'publish') {
            $step = self::injectPublishDefaults($step);
        }

        return $step;
    }

    /**
     * Inject AI step defaults (provider, model).
     *
     * @param array $step AI step configuration
     * @return array Step with AI defaults
     */
    private static function injectAIDefaults(array $step): array {
        if (empty($step['provider'])) {
            $step['provider'] = PluginSettings::get('default_provider', 'anthropic');
        }

        if (empty($step['model'])) {
            $step['model'] = PluginSettings::get('default_model', 'claude-sonnet-4-20250514');
        }

        return $step;
    }

    /**
     * Inject publish step defaults (post_author for wordpress_publish).
     *
     * @param array $step Publish step configuration
     * @return array Step with publish defaults
     */
    private static function injectPublishDefaults(array $step): array {
        $handler = $step['handler'] ?? '';

        if ($handler === 'wordpress_publish') {
            $config = $step['config'] ?? [];

            if (empty($config['post_author'])) {
                $config['post_author'] = get_current_user_id() ?: 1;
            }

            if (empty($config['post_status'])) {
                $config['post_status'] = 'draft';
            }

            $step['config'] = $config;
        }

        return $step;
    }
}
