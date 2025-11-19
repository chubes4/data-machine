<?php
/**
 * @package DataMachine\Core\Steps\Update\Handlers\WordPress
 */

namespace DataMachine\Core\Steps\Update\Handlers\WordPress;

use DataMachine\Core\Steps\HandlerRegistrationTrait;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress Update handler registration and configuration.
 *
 * Uses HandlerRegistrationTrait to provide standardized handler registration
 * for updating existing WordPress posts and pages.
 *
 * @since 0.2.2
 */
class WordPressFilters {
    use HandlerRegistrationTrait;

    /**
     * Register WordPress Update handler with all required filters.
     */
    public static function register(): void {
        self::registerHandler(
            'wordpress_update',
            'update',
            WordPress::class,
            __('WordPress Update', 'datamachine'),
            __('Update existing WordPress posts and pages', 'datamachine'),
            false,
            null,
            WordPressSettings::class,
            function($tools, $handler_slug, $handler_config) {
                if ($handler_slug === 'wordpress_update') {
                    $tools['wordpress_update'] = datamachine_get_dynamic_wordpress_update_tool($handler_config);
                }
                return $tools;
            }
        );
    }
}

/**
 * Register WordPress Update handler filters.
 *
 * @since 0.1.0
 */
function datamachine_register_wordpress_update_filters() {
    WordPressFilters::register();
}

/**
 * Base tool configuration with conditional parameters based on settings.
 */
function datamachine_get_wordpress_update_base_tool(array $handler_config = []): array {
    $tool = [
        'class' => 'DataMachine\\Core\\Steps\\Update\\Handlers\\WordPress\\WordPress',
        'method' => 'handle_tool_call',
        'handler' => 'wordpress_update',
        'description' => 'Make surgical updates to WordPress posts using find-and-replace operations. Preserves all images, blocks, and formatting.',
        'parameters' => [
            'updates' => [
                'type' => 'array',
                'required' => false,
                'description' => 'Array of surgical find-and-replace operations: [{"find": "old text", "replace": "new text"}]. Use for precise content changes without affecting rest of post.'
            ],
            'block_updates' => [
                'type' => 'array',
                'required' => false,
                'description' => 'Array of block-specific updates: [{"block_index": 0, "find": "old text", "replace": "new text"}]. Target specific Gutenberg blocks by index.'
            ]
        ]
    ];

    // Conditionally add title parameter
    if ($handler_config['allow_title_updates'] ?? true) {
        $tool['parameters']['title'] = [
            'type' => 'string',
            'required' => false,
            'description' => 'New post title (leave empty to keep existing)'
        ];
    }

    if ($handler_config['allow_content_updates'] ?? true) {
        $tool['parameters']['content'] = [
            'type' => 'string',
            'required' => false,
            'description' => 'LEGACY: Complete replacement content (use "updates" array for surgical changes instead)'
        ];
    }

    return $tool;
}

/**
 * Generate dynamic WordPress update tool with taxonomy parameters based on AI Decides selections.
 */
function datamachine_get_dynamic_wordpress_update_tool(array $handler_config): array {
    // handler_config is ALWAYS flat structure - no nesting
    $tool = datamachine_get_wordpress_update_base_tool($handler_config);
    $tool['handler_config'] = $handler_config;

    $taxonomies = get_taxonomies(['public' => true], 'objects');

    foreach ($taxonomies as $taxonomy) {
        $excluded = apply_filters('datamachine_wordpress_system_taxonomies', []);
        if (in_array($taxonomy->name, $excluded)) {
            continue;
        }

        $field_key = "taxonomy_{$taxonomy->name}_selection";
        $selection = $handler_config[$field_key] ?? 'skip';

        if ($selection === 'ai_decides') {
            $parameter_name = $taxonomy->name === 'category' ? 'category' :
                             ($taxonomy->name === 'post_tag' ? 'tags' : $taxonomy->name);

            if ($taxonomy->hierarchical) {
                $tool['parameters'][$parameter_name] = [
                    'type' => 'string',
                    'required' => false,
                    'description' => "Update {$taxonomy->name} based on content (leave empty to keep existing)"
                ];
            } else {
                $tool['parameters'][$parameter_name] = [
                    'type' => 'array',
                    'required' => false,
                    'description' => "Update {$taxonomy->name} for the content (leave empty to keep existing)"
                ];
            }
        }
    }

    return $tool;
}

datamachine_register_wordpress_update_filters();