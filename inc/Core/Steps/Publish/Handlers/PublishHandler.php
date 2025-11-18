<?php
/**
 * Base class for all publish handlers.
 *
 * Provides common functionality for publish handlers including:
 * - Engine data retrieval
 * - Image validation
 * - Standardized response formatting
 * - Centralized logging
 *
 * @package DataMachine\Core\Steps\Publish\Handlers
 */

namespace DataMachine\Core\Steps\Publish\Handlers;

defined('ABSPATH') || exit;

abstract class PublishHandler {

    /** @var string Handler type for logging and responses */
    protected string $handler_type;

    public function __construct(string $handler_type) {
        $this->handler_type = $handler_type;
    }

    /**
     * Implemented by each handler to execute publishing.
     *
     * @param array $parameters Tool parameters including content
     * @param array $handler_config Handler-specific configuration
     * @return array Result with success, data/error, and tool_name
     */
    abstract protected function executePublish(array $parameters, array $handler_config): array;

    /**
     * Public entry point called by AI tool executor.
     *
     * @param array $parameters Tool parameters
     * @param array $tool_def Tool definition with handler config
     * @return array Result array
     */
    final public function handle_tool_call(array $parameters, array $tool_def = []): array {
        $handler_config = $tool_def['handler_config'] ?? [];
        return $this->executePublish($parameters, $handler_config);
    }

    /**
     * Get all engine data for the current job.
     *
     * @param string|null $job_id Job identifier
     * @return array Engine data with source_url, image_file_path, etc.
     */
    protected function getEngineData(?string $job_id): array {
        if (!$job_id) {
            return [
                'source_url' => null,
                'image_file_path' => null,
                'image_url' => null
            ];
        }
        return apply_filters('datamachine_engine_data', [], $job_id);
    }

    /**
     * Get source URL from engine data.
     *
     * @param string|null $job_id Job identifier
     * @return string|null Source URL
     */
    protected function getSourceUrl(?string $job_id): ?string {
        $engine_data = $this->getEngineData($job_id);
        return $engine_data['source_url'] ?? null;
    }

    /**
     * Get image file path from engine data.
     *
     * @param string|null $job_id Job identifier
     * @return string|null Image file path
     */
    protected function getImageFilePath(?string $job_id): ?string {
        $engine_data = $this->getEngineData($job_id);
        return $engine_data['image_file_path'] ?? null;
    }

    /**
     * Validate repository image file.
     *
     * @param string $image_file_path Path to image file
     * @return array Validation result with valid, errors, mime_type, size
     */
    protected function validateImage(string $image_file_path): array {
        $image_validator = apply_filters('datamachine_get_image_validator', null);

        if (!$image_validator) {
            return [
                'valid' => false,
                'errors' => ['Image validator not available'],
                'mime_type' => null,
                'size' => 0
            ];
        }

        $validation = $image_validator->validate_repository_file($image_file_path);

        return [
            'valid' => $validation['valid'] ?? false,
            'errors' => $validation['errors'] ?? [],
            'mime_type' => $validation['mime_type'] ?? null,
            'size' => $validation['size'] ?? 0
        ];
    }

    /**
     * Create standardized success response.
     *
     * @param array $data Result data (post_id, post_url, etc.)
     * @return array Success response
     */
    protected function successResponse(array $data): array {
        return [
            'success' => true,
            'data' => $data,
            'tool_name' => "{$this->handler_type}_publish"
        ];
    }

    /**
     * Create standardized error response.
     *
     * @param string $error_message Error message
     * @param array|null $context Optional context for logging
     * @return array Error response
     */
    protected function errorResponse(string $error_message, ?array $context = null): array {
        $this->log('error', $error_message, $context ?? []);

        return [
            'success' => false,
            'error' => $error_message,
            'tool_name' => "{$this->handler_type}_publish"
        ];
    }

    /**
     * Centralized logging with handler context.
     *
     * @param string $level Log level (debug, info, warning, error)
     * @param string $message Log message
     * @param array $context Additional context data
     */
    protected function log(string $level, string $message, array $context = []): void {
        do_action('datamachine_log', $level, $message, array_merge([
            'handler' => $this->handler_type
        ], $context));
    }
}
