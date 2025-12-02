<?php
/**
 * Handler Documentation Builder
 *
 * Shared utility for building dynamic handler documentation from the registry.
 * Used by chat tools to provide accurate handler configuration schemas to AI agents.
 *
 * @package DataMachine\Api\Chat\Tools
 * @since 0.3.1
 */

namespace DataMachine\Api\Chat\Tools;

if (!defined('ABSPATH')) {
    exit;
}

class HandlerDocumentation {

    /**
     * Build documentation for all handlers grouped by step type.
     *
     * @return string Complete handler reference documentation
     */
    public static function buildAllHandlersSections(): string {
        $doc = '';
        $doc .= self::buildStepTypesSection();
        $doc .= self::buildHandlersSection('fetch', 'FETCH HANDLERS');
        $doc .= self::buildHandlersSection('publish', 'PUBLISH HANDLERS');
        $doc .= self::buildHandlersSection('update', 'UPDATE HANDLERS');
        
        // Include any custom step types that have handlers
        $step_types = apply_filters('datamachine_step_types', []);
        $core_types = ['fetch', 'publish', 'update', 'ai'];
        
        foreach ($step_types as $slug => $config) {
            if (!in_array($slug, $core_types, true)) {
                $uses_handler = $config['uses_handler'] ?? true;
                if ($uses_handler) {
                    $label = strtoupper(str_replace('_', ' ', $slug)) . ' HANDLERS';
                    $doc .= self::buildHandlersSection($slug, $label);
                }
            }
        }
        
        return $doc;
    }

    /**
     * Build step types documentation from registered step types.
     *
     * @return string Step types section
     */
    public static function buildStepTypesSection(): string {
        $step_types = apply_filters('datamachine_step_types', []);

        if (empty($step_types)) {
            return "STEP TYPES:\nNo step types registered.\n\n";
        }

        $doc = "STEP TYPES:\n";
        foreach ($step_types as $slug => $config) {
            $description = $config['description'] ?? 'No description';
            $uses_handler = $config['uses_handler'] ?? true;
            $handler_note = $uses_handler ? ' (requires handler_slug + handler_config)' : '';
            $doc .= "- {$slug}: {$description}{$handler_note}\n";
        }
        $doc .= "\n";

        return $doc;
    }

    /**
     * Build documentation for handlers of a specific step type.
     *
     * @param string $step_type Step type slug
     * @param string $section_title Section title for output
     * @return string Handler section documentation
     */
    public static function buildHandlersSection(string $step_type, string $section_title): string {
        $handlers = apply_filters('datamachine_handlers', [], $step_type);
        
        if (empty($handlers)) {
            return "";
        }

        $doc = "{$section_title}:\n";
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
    public static function formatHandlerEntry(string $slug, array $handler): string {
        $description = $handler['description'] ?? 'No description';
        $requires_auth = $handler['requires_auth'] ?? false;
        
        $entry = "- {$slug}: {$description}\n";
        
        $config_fields = self::getHandlerConfigFields($slug);
        if (!empty($config_fields)) {
            $entry .= "  handler_config: {" . implode(', ', $config_fields) . "}\n";
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
     * @return array Formatted field list (e.g., ["field (required)", "field?"])
     */
    public static function getHandlerConfigFields(string $handler_slug): array {
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
}
