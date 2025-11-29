<?php
/**
 * Dynamic Tool Provider Base Class
 *
 * Centralized pattern for engine-aware tool definition generation.
 * Ensures AI tools don't ask for parameters that already exist in engine data.
 *
 * @package DataMachine\Engine\AI\Tools
 * @since 0.4.1
 */

namespace DataMachine\Engine\AI\Tools;

defined('ABSPATH') || exit;

/**
 * Base class for dynamic tool parameter providers.
 *
 * Handlers extend this class to define which parameters should be excluded
 * from AI tool definitions when values already exist in engine data.
 */
abstract class DynamicToolProvider {

    /**
     * Get tool parameters, excluding any that already have values in engine data.
     *
     * @param array $handler_config Handler configuration
     * @param array $engine_data Engine data snapshot
     * @return array Tool parameter definitions
     */
    abstract public function getToolParameters(array $handler_config, array $engine_data): array;

    /**
     * Define which parameter keys should check engine data for existing values.
     *
     * @return array List of parameter keys that are engine-aware
     */
    abstract public function getEngineAwareParameters(): array;

    /**
     * Get all available tool parameters (without engine filtering).
     *
     * @return array Complete tool parameter definitions
     */
    abstract public function getAllParameters(): array;

    /**
     * Filter parameters based on engine data presence.
     *
     * Removes parameters from tool definition if their values already exist
     * in engine data, preventing AI from overriding system-provided values.
     *
     * @param array $parameters All available parameters
     * @param array $engine_data Engine data snapshot
     * @return array Filtered parameters
     */
    protected function filterByEngineData(array $parameters, array $engine_data): array {
        if (empty($engine_data)) {
            return $parameters;
        }

        $engine_aware = $this->getEngineAwareParameters();
        $filtered = [];

        foreach ($parameters as $key => $definition) {
            if (in_array($key, $engine_aware, true) && !empty($engine_data[$key])) {
                continue;
            }
            $filtered[$key] = $definition;
        }

        return $filtered;
    }

    /**
     * Check if a specific parameter has a value in engine data.
     *
     * @param string $parameter Parameter key
     * @param array $engine_data Engine data snapshot
     * @return bool True if value exists in engine data
     */
    protected function hasEngineValue(string $parameter, array $engine_data): bool {
        return !empty($engine_data[$parameter]);
    }

    /**
     * Check if any engine-aware parameters have values in engine data.
     *
     * @param array $engine_data Engine data snapshot
     * @return bool True if any engine-aware params have values
     */
    protected function hasAnyEngineValues(array $engine_data): bool {
        foreach ($this->getEngineAwareParameters() as $param) {
            if ($this->hasEngineValue($param, $engine_data)) {
                return true;
            }
        }
        return false;
    }
}
