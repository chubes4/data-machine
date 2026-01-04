<?php
/**
 * Get Handler Defaults Tool
 *
 * Retrieves site-wide handler defaults for configuration reference.
 * Helps the agent understand current site standards before configuring flows.
 *
 * @package DataMachine\Api\Chat\Tools
 */

namespace DataMachine\Api\Chat\Tools;

if (!defined('ABSPATH')) {
    exit;
}

use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;
use DataMachine\Services\HandlerService;

class GetHandlerDefaults {
    use ToolRegistrationTrait;

    public function __construct() {
        $this->registerTool('chat', 'get_handler_defaults', [$this, 'getToolDefinition']);
    }

    public function getToolDefinition(): array {
        return [
            'class' => self::class,
            'method' => 'handle_tool_call',
            'description' => 'Get site-wide handler defaults. Use before configuring flows to learn the established configuration standards for this site. Returns defaults for a specific handler or all handlers.',
            'parameters' => [
                'handler_slug' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Handler slug to get defaults for (e.g., upsert_event, eventbrite). If omitted, returns defaults for all handlers.'
                ]
            ]
        ];
    }

    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        $handler_slug = $parameters['handler_slug'] ?? null;
        $handler_service = new HandlerService();
        $site_defaults = $handler_service->getSiteDefaults();

        // If specific handler requested
        if (!empty($handler_slug)) {
            $handler_slug = sanitize_key($handler_slug);

            // Validate handler exists
            $handler_info = $handler_service->get($handler_slug);
            if (!$handler_info) {
                return [
                    'success' => false,
                    'error' => "Handler '{$handler_slug}' not found",
                    'tool_name' => 'get_handler_defaults'
                ];
            }

            $defaults = $site_defaults[$handler_slug] ?? [];
            $fields = $handler_service->getConfigFields($handler_slug);

            return [
                'success' => true,
                'data' => [
                    'handler_slug' => $handler_slug,
                    'label' => $handler_info['label'] ?? $handler_slug,
                    'defaults' => $defaults,
                    'available_fields' => array_keys($fields),
                    'message' => empty($defaults)
                        ? "No site defaults configured for '{$handler_slug}'. Schema defaults will be used."
                        : "Site defaults for '{$handler_slug}'. These values are applied when fields are not explicitly set."
                ],
                'tool_name' => 'get_handler_defaults'
            ];
        }

        // Return all defaults
        $all_handlers = $handler_service->getAll();
        $summary = [];

        foreach ($all_handlers as $slug => $info) {
            $handler_defaults = $site_defaults[$slug] ?? [];
            if (!empty($handler_defaults)) {
                $summary[$slug] = [
                    'label' => $info['label'] ?? $slug,
                    'defaults' => $handler_defaults
                ];
            }
        }

        return [
            'success' => true,
            'data' => [
                'handlers_with_defaults' => $summary,
                'total_handlers' => count($all_handlers),
                'handlers_with_custom_defaults' => count($summary),
                'message' => count($summary) > 0
                    ? 'Site-wide defaults are configured for ' . count($summary) . ' handler(s). Use these values as reference when configuring flows.'
                    : 'No site-wide defaults configured. Schema defaults will be used for all handlers.'
            ],
            'tool_name' => 'get_handler_defaults'
        ];
    }
}
