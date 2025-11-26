<?php
/**
 * Workflow Validator for Execute Workflow Tool
 *
 * Validates workflow step structure before execution. Checks step types,
 * handler existence, and required configuration fields.
 *
 * @package DataMachine\Api\Chat\Tools\ExecuteWorkflow
 * @since 0.3.0
 */

namespace DataMachine\Api\Chat\Tools\ExecuteWorkflow;

if (!defined('ABSPATH')) {
    exit;
}

class WorkflowValidator {

    /**
     * Valid step types.
     */
    private const VALID_STEP_TYPES = ['fetch', 'ai', 'publish', 'update'];

    /**
     * Validate workflow steps array.
     *
     * @param array $steps Workflow steps to validate
     * @return array Validation result: ['valid' => bool, 'error' => string|null]
     */
    public static function validate(array $steps): array {
        if (empty($steps)) {
            return self::error('Workflow must contain at least one step');
        }

        foreach ($steps as $index => $step) {
            if (!is_array($step)) {
                return self::error("Step " . ($index + 1) . ": Invalid format. Step must be an object/array, got " . gettype($step));
            }

            $result = self::validateStep($step, $index);
            if (!$result['valid']) {
                return $result;
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate a single step.
     *
     * @param array $step Step configuration
     * @param int $index Step index for error messages
     * @return array Validation result
     */
    private static function validateStep(array $step, int $index): array {
        $step_num = $index + 1;

        if (!isset($step['type'])) {
            return self::error("Step {$step_num}: Missing required 'type' field");
        }

        $type = $step['type'];
        if (!in_array($type, self::VALID_STEP_TYPES, true)) {
            $valid_types = implode(', ', self::VALID_STEP_TYPES);
            return self::error("Step {$step_num}: Invalid type '{$type}'. Valid types: {$valid_types}");
        }

        if ($type !== 'ai') {
            if (!isset($step['handler'])) {
                return self::error("Step {$step_num}: {$type} step requires 'handler' field");
            }

            $handler_result = self::validateHandler($step['handler'], $type, $step_num);
            if (!$handler_result['valid']) {
                return $handler_result;
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate handler exists and matches step type.
     *
     * @param string $handler_slug Handler slug
     * @param string $step_type Step type
     * @param int $step_num Step number for error messages
     * @return array Validation result
     */
    private static function validateHandler(string $handler_slug, string $step_type, int $step_num): array {
        $handlers = apply_filters('datamachine_handlers', [], $step_type);

        if (!isset($handlers[$handler_slug])) {
            $available = !empty($handlers) ? implode(', ', array_keys($handlers)) : 'none';
            return self::error("Step {$step_num}: Handler '{$handler_slug}' not found for {$step_type} step. Available: {$available}");
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Create error result.
     *
     * @param string $message Error message
     * @return array Error result
     */
    private static function error(string $message): array {
        return ['valid' => false, 'error' => $message];
    }
}
