<?php
/**
 * Engine Data Object
 *
 * Encapsulates the "Engine Data" array which persists across the pipeline execution.
 * Provides platform-agnostic data access methods for source URLs, images, and metadata.
 *
 * @package DataMachine\Core
 * @since 0.2.1
 */

namespace DataMachine\Core;

if (!defined('ABSPATH')) {
    exit;
}

class EngineData {

    /**
     * The raw engine data array.
     *
     * @var array
     */
    private array $data;

    /**
     * The Job ID associated with this data.
     *
     * @var int|string|null
     */
    private $job_id;

    /**
     * Constructor.
     *
     * @param array $data Raw engine data array.
     * @param int|string|null $job_id Optional Job ID for context/logging.
     */
    public function __construct(array $data, $job_id = null) {
        $this->data = $data;
        $this->job_id = $job_id;
    }

    /**
     * Get a value from the engine data.
     *
     * @param string $key Data key.
     * @param mixed $default Default value if key not found.
     * @return mixed
     */
    public function get(string $key, $default = null) {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        $metadata = $this->data['metadata'] ?? [];
        if (is_array($metadata) && array_key_exists($key, $metadata)) {
            return $metadata[$key];
        }

        return $default;
    }

    /**
     * Get the Source URL.
     *
     * @return string|null Source URL or null.
     */
    public function getSourceUrl(): ?string {
        $url = $this->get('source_url');
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /**
     * Get the Image File Path (from Files Repository).
     *
     * @return string|null Absolute file path or null.
     */
    public function getImagePath(): ?string {
        return $this->get('image_file_path');
    }

    /**
     * Return the raw snapshot array.
     */
    public function all(): array {
        return $this->data;
    }

    /**
     * Get stored job context (flow_id, pipeline_id, etc.).
     */
    public function getJobContext(): array {
        return is_array($this->data['job'] ?? null) ? $this->data['job'] : [];
    }

    /**
     * Get full flow configuration snapshot.
     */
    public function getFlowConfig(): array {
        return is_array($this->data['flow_config'] ?? null) ? $this->data['flow_config'] : [];
    }

    /**
     * Get configuration for a specific flow step.
     */
    public function getFlowStepConfig(string $flow_step_id): array {
        $flow_config = $this->getFlowConfig();
        return $flow_config[$flow_step_id] ?? [];
    }

    /**
     * Get stored pipeline configuration snapshot.
     */
    public function getPipelineConfig(): array {
        return is_array($this->data['pipeline_config'] ?? null) ? $this->data['pipeline_config'] : [];
    }

    /**
     * Get configuration for a specific pipeline step.
     *
     * Pipeline step config contains AI provider settings (provider, model, system_prompt)
     * while flow step config contains flow-level overrides (handler_slug, handler_config, user_message).
     *
     * @param string $pipeline_step_id Pipeline step identifier.
     * @return array Step configuration array or empty array.
     */
    public function getPipelineStepConfig(string $pipeline_step_id): array {
        $pipeline_config = $this->getPipelineConfig();
        return $pipeline_config[$pipeline_step_id] ?? [];
    }
}
