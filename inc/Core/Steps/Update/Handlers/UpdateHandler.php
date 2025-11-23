<?php
/**
 * Base class for Update handlers providing standardized engine data access.
 *
 * @package DataMachine\Core\Steps\Update\Handlers
 */

namespace DataMachine\Core\Steps\Update\Handlers;

use DataMachine\Core\EngineData;

if (!defined('ABSPATH')) {
    exit;
}

abstract class UpdateHandler {

    /**
     * Get all engine data for the current job.
     *
     * @param int $job_id Job identifier
     * @return array Engine data with source_url, image_file_path, etc.
     */
    protected function getEngineData(int $job_id): array {
        if (!$job_id) {
            return [
                'source_url' => null,
                'image_file_path' => null,
                'image_url' => null
            ];
        }
        return datamachine_get_engine_data($job_id);
    }

    /**
     * Get source URL from engine data.
     *
     * @param int $job_id Job identifier
     * @return string|null Source URL
     */
    protected function getSourceUrl(int $job_id): ?string {
        $engine_data = $this->getEngineData($job_id);
        return $engine_data['source_url'] ?? null;
    }

    /**
     * Execute update operation.
     *
     * @param array $parameters Tool parameters including job_id
     * @param array $handler_config Handler configuration
     * @return array Success/failure response
     */
    abstract protected function executeUpdate(array $parameters, array $handler_config): array;

    /**
     * Handle tool call with job_id validation.
     *
     * @param array $parameters Tool parameters
     * @param array $tool_def Tool definition
     * @return array Tool call result
     */
    final public function handle_tool_call(array $parameters, array $tool_def = []): array {
        $job_id = (int) ($parameters['job_id'] ?? null);
        if (!$job_id) {
            return $this->errorResponse('job_id parameter is required for update operations');
        }

        // Get engine_data for update operations
        $engine_data = $this->getEngineData($job_id);
        $engine = new EngineData($engine_data, $job_id);

        // Enhance parameters for subclasses
        $parameters['job_id'] = $job_id;
        $parameters['engine'] = $engine;

        $handler_config = $tool_def['handler_config'] ?? [];
        return $this->executeUpdate($parameters, $handler_config);
    }

    /**
     * Create standardized error response.
     *
     * @param string $message Error message
     * @param array $context Additional context
     * @param string $level Log level
     * @return array Error response
     */
    protected function errorResponse(string $message, array $context = [], string $level = 'error'): array {
        do_action('datamachine_log', $level, 'Update Handler Error: ' . $message, $context);
        return [
            'success' => false,
            'error' => $message,
            'tool_name' => static::class
        ];
    }
}