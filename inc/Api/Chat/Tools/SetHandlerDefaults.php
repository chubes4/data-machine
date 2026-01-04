<?php
/**
 * Set Handler Defaults Tool
 *
 * Updates site-wide handler defaults via conversation.
 * Allows the agent to configure default values that apply to new flows.
 *
 * @package DataMachine\Api\Chat\Tools
 */

namespace DataMachine\Api\Chat\Tools;

if (!defined('ABSPATH')) {
    exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;
use DataMachine\Services\HandlerService;

class SetHandlerDefaults {
    use ToolRegistrationTrait;

    const HANDLER_DEFAULTS_OPTION = 'datamachine_handler_defaults';

    public function __construct() {
        $this->registerTool('chat', 'set_handler_defaults', [$this, 'getToolDefinition']);
    }

    public function getToolDefinition(): array {
        return [
            'class' => self::class,
            'method' => 'handle_tool_call',
            'description' => 'Set site-wide handler defaults. Use to establish standard configuration values that apply to all new flows. For example, setting post_author and include_images defaults for upsert_event.',
            'parameters' => [
                'handler_slug' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Handler slug to set defaults for (e.g., upsert_event, eventbrite)'
                ],
                'defaults' => [
                    'type' => 'object',
                    'required' => true,
                    'description' => 'Default configuration values to set. Keys should match handler config fields.'
                ]
            ]
        ];
    }

    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        $handler_slug = $parameters['handler_slug'] ?? null;
        $defaults = $parameters['defaults'] ?? null;

        // Validate handler_slug
        if (empty($handler_slug) || !is_string($handler_slug)) {
            return [
                'success' => false,
                'error' => 'handler_slug is required and must be a non-empty string',
                'tool_name' => 'set_handler_defaults'
            ];
        }

        $handler_slug = sanitize_key($handler_slug);
        $handler_service = new HandlerService();

        // Validate handler exists
        $handler_info = $handler_service->get($handler_slug);
        if (!$handler_info) {
            return [
                'success' => false,
                'error' => "Handler '{$handler_slug}' not found",
                'tool_name' => 'set_handler_defaults'
            ];
        }

        // Validate defaults
        if (empty($defaults) || !is_array($defaults)) {
            return [
                'success' => false,
                'error' => 'defaults is required and must be an object with configuration values',
                'tool_name' => 'set_handler_defaults'
            ];
        }

        // Get available fields for validation feedback
        $fields = $handler_service->getConfigFields($handler_slug);
        $valid_keys = array_keys($fields);
        $provided_keys = array_keys($defaults);
        $unrecognized_keys = array_diff($provided_keys, $valid_keys);

        // Sanitize defaults
        $sanitized_defaults = $this->sanitizeDefaults($defaults);

        // Get existing defaults and merge
        $all_defaults = get_option(self::HANDLER_DEFAULTS_OPTION, []);
        $all_defaults[$handler_slug] = $sanitized_defaults;

        // Save
        $updated = update_option(self::HANDLER_DEFAULTS_OPTION, $all_defaults);

        // Clear cache so new defaults take effect immediately
        HandlerService::clearSiteDefaultsCache();

        if (!$updated && get_option(self::HANDLER_DEFAULTS_OPTION) !== $all_defaults) {
            return [
                'success' => false,
                'error' => 'Failed to save handler defaults',
                'tool_name' => 'set_handler_defaults'
            ];
        }

        $response_data = [
            'handler_slug' => $handler_slug,
            'label' => $handler_info['label'] ?? $handler_slug,
            'defaults' => $sanitized_defaults,
            'message' => "Defaults updated for '{$handler_slug}'. New flows will use these values when fields are not explicitly set."
        ];

        // Warn about unrecognized keys
        if (!empty($unrecognized_keys)) {
            $response_data['warning'] = 'Some keys are not recognized handler fields: ' . implode(', ', $unrecognized_keys);
        }

        return [
            'success' => true,
            'data' => $response_data,
            'tool_name' => 'set_handler_defaults'
        ];
    }

    /**
     * Sanitize defaults array recursively.
     *
     * @param array $defaults Defaults to sanitize
     * @return array Sanitized defaults
     */
    private function sanitizeDefaults(array $defaults): array {
        $sanitized = [];

        foreach ($defaults as $key => $value) {
            $sanitized_key = sanitize_key($key);

            if (is_array($value)) {
                $sanitized[$sanitized_key] = $this->sanitizeDefaults($value);
            } elseif (is_bool($value)) {
                $sanitized[$sanitized_key] = (bool) $value;
            } elseif (is_numeric($value)) {
                $sanitized[$sanitized_key] = is_float($value) ? (float) $value : (int) $value;
            } else {
                $sanitized[$sanitized_key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }
}
