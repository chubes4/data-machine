<?php
/**
 * Documentation Builder for Execute Workflow Tool
 *
 * Dynamically builds tool documentation from registered handlers and their
 * settings schemas. Ensures tool description stays in sync with actual
 * available handlers and configuration options.
 *
 * @package DataMachine\Api\Chat\Tools\ExecuteWorkflow
 * @since 0.3.0
 */

namespace DataMachine\Api\Chat\Tools\ExecuteWorkflow;

use DataMachine\Core\WordPress\TaxonomyHandler;

if (!defined('ABSPATH')) {
    exit;
}

class DocumentationBuilder {

    /**
     * Build complete tool documentation from registered handlers.
     *
     * @return string Tool description for AI consumption
     */
    public static function build(): string {
        $doc = "Execute a content automation workflow.\n\n";
        $doc .= self::buildStepTypesSection();
        $doc .= self::buildFetchHandlersSection();
        $doc .= self::buildPublishHandlersSection();
        $doc .= self::buildUpdateHandlersSection();
        $doc .= self::buildTaxonomySection();
        $doc .= self::buildWorkflowPatternsSection();
        
        return $doc;
    }

    /**
     * Build step types documentation.
     *
     * @return string Step types section
     */
    private static function buildStepTypesSection(): string {
        return <<<'DOC'
STEP TYPES:
- fetch: Retrieve content from source (requires handler + config)
- ai: Process/transform content (uses default provider/model if not specified)
- publish: Send content to destination (requires handler + config)
- update: Modify existing content (requires handler + config)

DOC;
    }

    /**
     * Build fetch handlers documentation from registered handlers.
     *
     * @return string Fetch handlers section
     */
    private static function buildFetchHandlersSection(): string {
        $handlers = apply_filters('datamachine_handlers', [], 'fetch');
        
        if (empty($handlers)) {
            return "FETCH HANDLERS:\nNo fetch handlers registered.\n\n";
        }

        $doc = "FETCH HANDLERS:\n";
        foreach ($handlers as $slug => $handler) {
            $doc .= self::formatHandlerEntry($slug, $handler);
        }
        $doc .= "\n";
        
        return $doc;
    }

    /**
     * Build publish handlers documentation from registered handlers.
     *
     * @return string Publish handlers section
     */
    private static function buildPublishHandlersSection(): string {
        $handlers = apply_filters('datamachine_handlers', [], 'publish');
        
        if (empty($handlers)) {
            return "PUBLISH HANDLERS:\nNo publish handlers registered.\n\n";
        }

        $doc = "PUBLISH HANDLERS:\n";
        foreach ($handlers as $slug => $handler) {
            $doc .= self::formatHandlerEntry($slug, $handler);
        }
        $doc .= "\n";
        
        return $doc;
    }

    /**
     * Build update handlers documentation from registered handlers.
     *
     * @return string Update handlers section
     */
    private static function buildUpdateHandlersSection(): string {
        $handlers = apply_filters('datamachine_handlers', [], 'update');
        
        if (empty($handlers)) {
            return "";
        }

        $doc = "UPDATE HANDLERS:\n";
        foreach ($handlers as $slug => $handler) {
            $doc .= self::formatHandlerEntry($slug, $handler);
        }
        $doc .= "\n";
        
        return $doc;
    }

    /**
     * Format a single handler entry with config fields.
     *
     * @param string $slug Handler slug
     * @param array $handler Handler definition
     * @return string Formatted handler entry
     */
    private static function formatHandlerEntry(string $slug, array $handler): string {
        $description = $handler['description'] ?? 'No description';
        $requires_auth = $handler['requires_auth'] ?? false;
        
        $entry = "- {$slug}: {$description}\n";
        
        $config_fields = self::getHandlerConfigFields($slug);
        if (!empty($config_fields)) {
            $entry .= "  config: {" . implode(', ', $config_fields) . "}\n";
        }
        
        if ($requires_auth) {
            $entry .= "  auth: required\n";
        }
        
        return $entry;
    }

    /**
     * Get configuration fields for a handler from its settings class.
     *
     * @param string $handler_slug Handler slug
     * @return array Formatted field list (e.g., ["site_url (required)", "search?"])
     */
    private static function getHandlerConfigFields(string $handler_slug): array {
        $all_settings = apply_filters('datamachine_handler_settings', [], $handler_slug);
        $settings_class = $all_settings[$handler_slug] ?? null;
        
        if (!$settings_class || !method_exists($settings_class, 'get_fields')) {
            return [];
        }
        
        $fields = $settings_class::get_fields();
        $formatted = [];
        
        foreach ($fields as $key => $config) {
            $required = $config['required'] ?? false;
            
            if ($required) {
                $formatted[] = "{$key} (required)";
            } else {
                $formatted[] = "{$key}?";
            }
        }
        
        return $formatted;
    }

    /**
     * Build taxonomy configuration documentation.
     *
     * @return string Taxonomy section
     */
    private static function buildTaxonomySection(): string {
        return <<<'DOC'
TAXONOMY CONFIGURATION (wordpress_publish handler):
For each taxonomy, use key: taxonomy_{taxonomy_name}_selection
Values:
- "skip": Don't assign this taxonomy
- "ai_decides": AI assigns based on content at runtime
- "Term Name": Pre-select this term (use exact term name from site context)

Example: taxonomy_category_selection: "ai_decides"
Example: taxonomy_location_selection: "Charleston"

DOC;
    }

    /**
     * Build workflow patterns documentation.
     *
     * @return string Workflow patterns section
     */
    private static function buildWorkflowPatternsSection(): string {
        return <<<'DOC'
WORKFLOW PATTERNS:
- Content syndication: fetch → ai → publish
- Content enhancement: fetch → ai → update
- Multi-platform: fetch → ai → publish → ai → publish

STEP FORMAT (each step is an object, NOT a JSON string):
{
  "type": "fetch|ai|publish|update",
  "handler": "handler_slug",  // required for fetch/publish/update
  "config": {...},            // handler configuration
  "user_message": "...",      // for ai steps: instruction for AI
  "system_prompt": "..."      // for ai steps: optional system context
}

COMPLETE EXAMPLE INPUT:
steps: [
  {"type": "fetch", "handler": "rss", "config": {"feed_url": "https://example.com/feed"}},
  {"type": "ai", "user_message": "Summarize this content for social media"},
  {"type": "publish", "handler": "wordpress_publish", "config": {"post_type": "post", "post_status": "draft", "post_author": 1}}
]

IMPORTANT: Pass steps as an array of objects, not an array of JSON strings.
DOC;
    }
}
