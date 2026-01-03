<?php
/**
 * Step Type Service
 *
 * Centralized step type discovery and lookup with request-level caching.
 * Single source of truth for step type data access throughout the codebase.
 *
 * @package DataMachine\Services
 * @since 0.6.25
 */

namespace DataMachine\Services;

defined('ABSPATH') || exit;

class StepTypeService {

    /**
     * Cached step types.
     *
     * @var array|null
     */
    private static ?array $cache = null;

    /**
     * Clear cached step types.
     * Call when step types are dynamically registered.
     */
    public static function clearCache(): void {
        self::$cache = null;
    }

    /**
     * Get all registered step types (cached).
     *
     * @return array Step types array keyed by slug
     */
    public function getAll(): array {
        if (self::$cache === null) {
            self::$cache = apply_filters('datamachine_step_types', []);
        }

        return self::$cache;
    }

    /**
     * Get a step type definition by slug.
     *
     * @param string $slug Step type slug
     * @return array|null Step type definition or null
     */
    public function get(string $slug): ?array {
        $step_types = $this->getAll();
        return $step_types[$slug] ?? null;
    }

    /**
     * Check if a step type exists.
     *
     * @param string $slug Step type slug
     * @return bool True if step type exists
     */
    public function exists(string $slug): bool {
        $step_types = $this->getAll();
        return isset($step_types[$slug]);
    }

    /**
     * Check if a step type uses handlers.
     *
     * @param string $slug Step type slug
     * @return bool True if step type uses handlers
     */
    public function usesHandler(string $slug): bool {
        $step_type = $this->get($slug);

        if (!$step_type) {
            return false;
        }

        return $step_type['uses_handler'] ?? true;
    }

    /**
     * Validate a step type slug.
     *
     * @param string $slug Step type slug to validate
     * @return array{valid: bool, error?: string}
     */
    public function validate(string $slug): array {
        if (empty($slug)) {
            return ['valid' => false, 'error' => 'step_type is required'];
        }

        if (!$this->exists($slug)) {
            $available = array_keys($this->getAll());
            return [
                'valid' => false,
                'error' => "Step type '{$slug}' not found. Available: " . implode(', ', $available)
            ];
        }

        return ['valid' => true];
    }
}
