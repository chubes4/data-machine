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
     * Cached config fields by handler slug.
     *
     * @var array<string, array>
     */
    private static array $config_fields_cache = [];

    /**
     * Clear all cached data.
     * Call when handlers are dynamically registered.
     */
    public static function clearCache(): void {
        self::$handlers_cache = [];
        self::$settings_cache = [];
        self::$config_fields_cache = [];
    }

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
     * Get configuration fields for a handler (cached).
     *
     * @param string $handler_slug Handler slug
     * @return array Field definitions from the handler's settings class
     */
    public function getConfigFields(string $handler_slug): array {
        // Return cached if available
        if (isset(self::$config_fields_cache[$handler_slug])) {
            return self::$config_fields_cache[$handler_slug];
        }

        $settings_class = $this->getSettingsClass($handler_slug);

        if (!$settings_class || !method_exists($settings_class, 'get_fields')) {
            self::$config_fields_cache[$handler_slug] = [];
            return [];
        }

        $fields = $settings_class::get_fields();
        self::$config_fields_cache[$handler_slug] = $fields;

        return $fields;
    }

    /**
     * Apply handler defaults to configuration.
     *
     * Merges schema defaults with provided config. Provided values take precedence.
     * Keys not in schema are preserved for forward compatibility.
     *
     * @param string $handler_slug Handler identifier
     * @param array $config Provided configuration values
     * @return array Complete configuration with defaults applied
     */
    public function applyDefaults(string $handler_slug, array $config): array {
        $fields = $this->getConfigFields($handler_slug);

        if (empty($fields)) {
            return $config;
        }

        $complete_config = [];

        foreach ($fields as $key => $field_config) {
            if (array_key_exists($key, $config)) {
                $complete_config[$key] = $config[$key];
            } elseif (isset($field_config['default'])) {
                $complete_config[$key] = $field_config['default'];
            }
        }

        foreach ($config as $key => $value) {
            if (!isset($fields[$key])) {
                $complete_config[$key] = $value;
            }
        }

        return $complete_config;
    }
}
