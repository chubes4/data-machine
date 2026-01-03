<?php
/**
 * Handler Service
 *
 * Centralized handler discovery, validation, and lookup with request-level caching.
 * Single source of truth for handler data access throughout the codebase.
 *
 * @package DataMachine\Services
 * @since 0.6.25
 */

namespace DataMachine\Services;

defined('ABSPATH') || exit;

class HandlerService {

    /**
     * Cached handlers by step type.
     *
     * @var array<string, array>
     */
    private static array $handlers_cache = [];

    /**
     * Cached handler settings classes.
     *
     * @var array<string, object|null>
     */
    private static array $settings_cache = [];

    /**
     * Get all registered handlers, optionally filtered by step type (cached).
     *
     * @param string|null $step_type Step type filter (fetch, publish, update, etc.)
     * @return array Handlers array keyed by slug
     */
    public function getAll(?string $step_type = null): array {
        $cache_key = $step_type ?? '__all__';

        if (!isset(self::$handlers_cache[$cache_key])) {
            self::$handlers_cache[$cache_key] = apply_filters('datamachine_handlers', [], $step_type);
        }

        return self::$handlers_cache[$cache_key];
    }

    /**
     * Check if a handler slug exists.
     *
     * @param string $handler_slug Handler slug to check
     * @param string|null $step_type Optional step type constraint
     * @return bool True if handler exists
     */
    public function exists(string $handler_slug, ?string $step_type = null): bool {
        $handlers = $this->getAll($step_type);
        return isset($handlers[$handler_slug]);
    }

    /**
     * Validate handler slug.
     *
     * @param string $handler_slug Handler slug to validate
     * @param string|null $step_type Optional step type constraint
     * @return array{valid: bool, error?: string}
     */
    public function validate(string $handler_slug, ?string $step_type = null): array {
        if (empty($handler_slug)) {
            return ['valid' => false, 'error' => 'handler_slug is required'];
        }

        if ($step_type) {
            $handlers = $this->getAll($step_type);
            if (!isset($handlers[$handler_slug])) {
                return [
                    'valid' => false,
                    'error' => "Handler '{$handler_slug}' not found for step type '{$step_type}'"
                ];
            }
            return ['valid' => true];
        }

        $all_handlers = $this->getAll();
        if (!isset($all_handlers[$handler_slug])) {
            return [
                'valid' => false,
                'error' => "Handler '{$handler_slug}' not found"
            ];
        }
        return ['valid' => true];
    }

    /**
     * Get handler definition by slug.
     *
     * @param string $handler_slug Handler slug
     * @param string|null $step_type Optional step type filter for more targeted lookup
     * @return array|null Handler definition or null
     */
    public function get(string $handler_slug, ?string $step_type = null): ?array {
        $handlers = $this->getAll($step_type);
        return $handlers[$handler_slug] ?? null;
    }

    /**
     * Get settings class instance for a handler (cached).
     *
     * @param string $handler_slug Handler slug
     * @return object|null Settings class instance or null
     */
    public function getSettingsClass(string $handler_slug): ?object {
        if (!array_key_exists($handler_slug, self::$settings_cache)) {
            $all_settings = apply_filters('datamachine_handler_settings', [], $handler_slug);
            self::$settings_cache[$handler_slug] = $all_settings[$handler_slug] ?? null;
        }

        return self::$settings_cache[$handler_slug];
    }

    /**
     * Get configuration fields for a handler.
     *
     * @param string $handler_slug Handler slug
     * @return array Field definitions from the handler's settings class
     */
    public function getConfigFields(string $handler_slug): array {
        $settings_class = $this->getSettingsClass($handler_slug);

        if (!$settings_class || !method_exists($settings_class, 'get_fields')) {
            return [];
        }

        return $settings_class::get_fields();
    }
}
