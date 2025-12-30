<?php
/**
 * Handler Service
 *
 * Centralized handler discovery, validation, and lookup.
 *
 * @package DataMachine\Services
 * @since 0.6.19
 */

namespace DataMachine\Services;

defined('ABSPATH') || exit;

class HandlerService {

    /**
     * Get all registered handlers, optionally filtered by step type.
     *
     * @param string|null $step_type Step type filter (fetch, publish, update, etc.)
     * @return array Handlers array keyed by slug
     */
    public function getAll(?string $step_type = null): array {
        return apply_filters('datamachine_handlers', [], $step_type);
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
     * @return array|null Handler definition or null
     */
    public function get(string $handler_slug): ?array {
        $handlers = $this->getAll();
        return $handlers[$handler_slug] ?? null;
    }
}
