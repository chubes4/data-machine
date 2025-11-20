<?php
/**
 * WordPress publish handler filter registration.
 *
 * @package DataMachine
 * @subpackage Core\Steps\Publish\Handlers\WordPress
 * @since 0.1.0
 */

namespace DataMachine\Core\Steps\Publish\Handlers\WordPress;

use DataMachine\Core\Steps\HandlerRegistrationTrait;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress publish handler registration and configuration.
 *
 * Uses HandlerRegistrationTrait to provide standardized handler registration
 * for creating WordPress posts and pages.
 * Preserves custom success message logic.
 *
 * @since 0.2.2
 */
class WordPressFilters {
    use HandlerRegistrationTrait;

    /**
     * Register WordPress publish handler with all required filters.
     */
    public static function register(): void {
        self::registerHandler(
            'wordpress_publish',
            'publish',
            WordPress::class,
            __('WordPress', 'datamachine'),
            __('Create WordPress posts and pages', 'datamachine'),
            false,
            null,
            WordPressSettings::class,
            function($tools, $handler_slug, $handler_config) {
                if ($handler_slug === 'wordpress_publish') {
                    $tools['wordpress_publish'] = datamachine_get_dynamic_wordpress_tool($handler_config);
                }
                return $tools;
            }
        );

        // Custom success message logic (preserved)
        add_filter('datamachine_tool_success_message', function($default_message, $tool_name, $tool_result) {
            if ($tool_name === 'wordpress_publish' && !empty($tool_result['data']['post_title'])) {
                $title = $tool_result['data']['post_title'];
                $url = $tool_result['data']['post_url'] ?? '';
                $post_id = $tool_result['data']['post_id'] ?? '';

                if (!empty($url)) {
                    return "WordPress post published successfully. Title: '{$title}' at {$url} (ID: {$post_id}).";
                } else {
                    return "WordPress post created successfully. Title: '{$title}' (ID: {$post_id}).";
                }
            }
            return $default_message;
        }, 10, 4);
    }
}

/**
 * Register WordPress publish handler filters.
 *
 * @since 0.1.0
 */
function datamachine_register_wordpress_publish_filters() {
    WordPressFilters::register();
}

/**
 * Get base WordPress tool definition.
 *
 * @return array Base WordPress tool configuration.
 */
function datamachine_get_wordpress_base_tool(): array {
    return [
        'class' => 'DataMachine\\Core\\Steps\\Publish\\Handlers\\WordPress\\WordPress',
        'method' => 'handle_tool_call',
        'handler' => 'wordpress_publish',
        'description' => 'Publish content to WordPress using Gutenberg block format',
        'parameters' => [
            'title' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Post title (page h1 element)'
            ],
            'content' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Post content MUST be valid Gutenberg block HTML.

STRUCTURE:
1) Every block uses comment wrappers. Pattern: <!-- wp:block {"attrs":...} -->[inner HTML]<!-- /wp:block -->
   - JSON lives on the opening line, uses straight quotes, no trailing commas.
   - Always include the closing comment, even for single-line blocks.
2) Only use core blocks from this palette: heading, paragraph, list, quote, separator, image.
3) No Markdown or raw HTML wrappers outside blocks. All links use <a href="URL">Text</a>.
4) Do not repeat the post title. Do not embed source links or featured images; the system injects them.
5) Only output attributes explicitly documented below for each block.

CORE BLOCK DETAILS:
- heading: default H2. Add {"level":3} for H3, {"level":4} for H4. Inner HTML must keep class="wp-block-heading".
- paragraph: wrap prose in <p>…</p>. Keep inline elements valid HTML.
- list: <!-- wp:list --> for unordered, add {"ordered":true} for ordered. Use class="wp-block-list" on <ul>/<ol>; no extra list attributes.
- quote: include citation text in <cite> when needed.
- separator: <!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->.
- image: only when supplied. Opening JSON must include url, alt when known, optionally caption. Inner HTML should be <figure class="wp-block-image"><img src="..." alt="..."/><figcaption>…</figcaption></figure>.

INLINE FORMATTING:
- Use standard HTML tags inside headings/paragraphs: <strong>, <em>, <code>, <sup>, <sub>, <a>.
- Tags must be balanced inside the same block and never wrap entire blocks.

VALIDATION CHECKLIST:
- Confirm every opening comment has a matching closing comment.
- JSON braces/quotes balanced and closed on the same line.
- Inline tags open and close properly; no dangling markup.
'
            ]
        ]
    ];
}

/**
 * Generate dynamic WordPress tool based on enabled taxonomies.
 *
 * @param array $handler_config Handler configuration containing taxonomy selections.
 * @return array Dynamic tool configuration with taxonomy parameters.
 */
function datamachine_get_dynamic_wordpress_tool(array $handler_config): array {
    // handler_config is ALWAYS flat structure - no nesting
    $wordpress_config = $handler_config;

    // Start with base tool
    $tool = datamachine_get_wordpress_base_tool();

    // Store handler configuration for execution
    $tool['handler_config'] = $wordpress_config;

    // Get all public taxonomies
    $taxonomies = get_taxonomies(['public' => true], 'objects');

    foreach ($taxonomies as $taxonomy) {
        // Skip built-in formats and other non-content taxonomies using centralized filter
        $excluded = apply_filters('datamachine_wordpress_system_taxonomies', []);
        if (in_array($taxonomy->name, $excluded)) {
            continue;
        }

        $field_key = "taxonomy_{$taxonomy->name}_selection";
        $selection = $wordpress_config[$field_key] ?? 'skip';

        if ($selection === 'ai_decides') {
            $parameter_name = $taxonomy->name === 'category' ? 'category' :
                             ($taxonomy->name === 'post_tag' ? 'tags' : $taxonomy->name);

            // AI Decides - include parameter with required flag
            if ($taxonomy->hierarchical) {
                $tool['parameters'][$parameter_name] = [
                    'type' => 'string',
                    'required' => true,
                    'description' => "Select most appropriate {$taxonomy->name} based on content"
                ];
            } else {
                $tool['parameters'][$parameter_name] = [
                    'type' => 'array',
                    'required' => true,
                    'description' => "Choose one or more relevant {$taxonomy->name} for the content"
                ];
            }

        } else {
            // Skip and Specific Selection: NOT included in tool parameters
            // These are handled automatically during publishing via publish_config
        }
    }

    return $tool;
}


// Auto-register when file loads - achieving complete self-containment
datamachine_register_wordpress_publish_filters();