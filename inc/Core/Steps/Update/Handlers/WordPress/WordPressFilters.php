<?php
/**
 * @package DataMachine\Core\Steps\Update\Handlers\WordPress
 */

namespace DataMachine\Core\Steps\Update\Handlers\WordPress;

if (!defined('ABSPATH')) {
    exit;
}

function datamachine_register_wordpress_update_filters() {
    add_filter('datamachine_handlers', function($handlers, $step_type = null) {
        if ($step_type === null || $step_type === 'update') {
            $handlers['wordpress_update'] = [
                'type' => 'update',
                'class' => WordPress::class,
                'label' => __('WordPress Update', 'datamachine'),
                'description' => __('Update existing WordPress posts and pages', 'datamachine')
            ];
        }
        return $handlers;
    }, 10, 2);

    add_filter('datamachine_handler_settings', function($all_settings, $handler_slug = null) {
        if ($handler_slug === null || $handler_slug === 'wordpress_update') {
            $all_settings['wordpress_update'] = new WordPressSettings();
        }
        return $all_settings;
    }, 10, 2);

    add_filter('chubes_ai_tools', function($tools, $handler_slug = null, $handler_config = []) {
        if ($handler_slug === 'wordpress_update') {
            $tools['wordpress_update'] = datamachine_get_dynamic_wordpress_update_tool($handler_config);
        }
        return $tools;
    }, 10, 3);
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
    $wordpress_config = $handler_config['wordpress_update'] ?? $handler_config;

    $tool = datamachine_get_wordpress_update_base_tool($wordpress_config);
    $tool['handler_config'] = $wordpress_config;

    if (!is_array($handler_config)) {
        return $tool;
    }

    $sanitized_config = [];
    foreach ($wordpress_config as $key => $value) {
        if (is_string($key) && (is_string($value) || is_array($value))) {
            $sanitized_config[sanitize_key($key)] = is_string($value) ? sanitize_text_field($value) : $value;
        }
    }

    if (empty($sanitized_config)) {
        return $tool;
    }

    $taxonomies = get_taxonomies(['public' => true], 'objects');

    foreach ($taxonomies as $taxonomy) {
        $excluded = apply_filters('datamachine_wordpress_system_taxonomies', []);
        if (in_array($taxonomy->name, $excluded)) {
            continue;
        }

        $field_key = "taxonomy_{$taxonomy->name}_selection";
        $selection = $sanitized_config[$field_key] ?? 'skip';

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