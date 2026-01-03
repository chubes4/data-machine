<?php
/**
 * Handler Documentation Builder
 *
 * Shared utility for building dynamic handler documentation from the registry.
 * Used by chat tools to provide accurate handler configuration schemas to AI agents.
 *
 * Documentation is cached after first build and invalidated when handlers or
 * step types are dynamically registered.
 *
 * @package DataMachine\Api\Chat\Tools
 * @since 0.3.1
 */

namespace DataMachine\Api\Chat\Tools;

use DataMachine\Services\HandlerService;
use DataMachine\Services\StepTypeService;

if (!defined('ABSPATH')) {
    exit;
}

class HandlerDocumentation {

    /**
     * Cached service instances.
     */
    private static ?HandlerService $handler_service = null;
    private static ?StepTypeService $step_type_service = null;

    /**
     * Cached complete documentation string.
     *
     * @var string|null
     */
    private static ?string $cached_all_handlers = null;

    /**
     * Cached per-step-type documentation.
     *
     * @var array<string, string>
     */
    private static array $cached_by_step_type = [];

    /**
     * Handler slugs at time of last cache build.
     * Used to detect when cache should be invalidated.
     *
     * @var array<string>
     */
    private static array $cached_handler_slugs = [];

    /**
     * Clear all cached documentation.
     * Call when handlers or step types are dynamically registered.
     */
    public static function clearCache(): void {
        self::$cached_all_handlers = null;
        self::$cached_by_step_type = [];
        self::$cached_handler_slugs = [];
        self::$handler_service = null;
        self::$step_type_service = null;
    }

    /**
     * Get HandlerService instance.
     */
    private static function getHandlerService(): HandlerService {
        if (self::$handler_service === null) {
            self::$handler_service = new HandlerService();
        }
        return self::$handler_service;
    }

    /**
     * Get StepTypeService instance.
     */
    private static function getStepTypeService(): StepTypeService {
        if (self::$step_type_service === null) {
            self::$step_type_service = new StepTypeService();
        }
        return self::$step_type_service;
    }

    /**
     * Get current handler slugs for cache validation.
     *
     * @return array<string> Current handler slugs
     */
    private static function getCurrentHandlerSlugs(): array {
        $handlers = self::getHandlerService()->getAll();
        return array_keys($handlers);
    }

    /**
     * Check if cache is still valid.
     *
     * @return bool True if cache is valid
     */
    private static function isCacheValid(): bool {
        if (self::$cached_all_handlers === null) {
            return false;
        }

        $current_slugs = self::getCurrentHandlerSlugs();
        return self::$cached_handler_slugs === $current_slugs;
    }

    /**
     * Build documentation for all handlers grouped by step type.
     *
     * Results are cached after first build.
     *
     * @return string Complete handler reference documentation
     */
    public static function buildAllHandlersSections(): string {
        // Return cached if valid
        if (self::isCacheValid()) {
            return self::$cached_all_handlers;
        }

        $doc = '';
        $doc .= self::buildStepTypesSection();

        $step_types = self::getStepTypeService()->getAll();
        foreach ($step_types as $slug => $config) {
            $uses_handler = $config['uses_handler'] ?? true;
            if (!$uses_handler) {
                continue;
            }

            $label = $config['label'] ?? $slug;
            $section_title = strtoupper($label) . ' HANDLERS (step_type: ' . $slug . ')';
            $doc .= self::buildHandlersSection($slug, $section_title);
        }

        // Cache results
        self::$cached_all_handlers = $doc;
        self::$cached_handler_slugs = self::getCurrentHandlerSlugs();

        return $doc;
    }

    /**
     * Build step types documentation from registered step types.
     *
     * @return string Step types section
     */
    public static function buildStepTypesSection(): string {
        $step_types = self::getStepTypeService()->getAll();

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
        // Check per-step-type cache
        if (isset(self::$cached_by_step_type[$step_type])) {
            return self::$cached_by_step_type[$step_type];
        }

        $handlers = self::getHandlerService()->getAll($step_type);

        if (empty($handlers)) {
            self::$cached_by_step_type[$step_type] = "";
            return "";
        }

        $doc = "{$section_title}:\n";
        foreach ($handlers as $slug => $handler) {
            $doc .= self::formatHandlerEntry($slug, $handler);
        }
        $doc .= "\n";

        self::$cached_by_step_type[$step_type] = $doc;

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
            $entry .= "  handler_config:\n";
            foreach ($config_fields as $field_info) {
                $entry .= "    {$field_info}\n";
            }
        }

        if ($requires_auth) {
            $entry .= "  auth: required\n";
        }

        return $entry;
    }

    /**
     * Get configuration fields for a handler, formatted for documentation.
     *
     * @param string $handler_slug Handler slug
     * @return array Formatted field list with types and descriptions
     */
    public static function getHandlerConfigFields(string $handler_slug): array {
        $fields = self::getHandlerService()->getConfigFields($handler_slug);

        if (empty($fields)) {
            return [];
        }

        $formatted = [];

        foreach ($fields as $key => $config) {
            $required = $config['required'] ?? false;
            $type = $config['type'] ?? 'text';
            $desc = $config['description'] ?? '';

            // Truncate description if too long
            if (strlen($desc) > 80) {
                $desc = substr($desc, 0, 77) . '...';
            }

            $required_marker = $required ? ' (required)' : '';

            if (!empty($desc)) {
                $formatted[] = "{$key}{$required_marker} ({$type}): {$desc}";
            } else {
                $formatted[] = "{$key}{$required_marker} ({$type})";
            }
        }

        return $formatted;
    }
}
