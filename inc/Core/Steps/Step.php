<?php
/**
 * Abstract base class for all Data Machine step types.
 *
 * Provides common functionality for payload handling, validation, logging,
 * and exception handling across all step implementations.
 *
 * @package DataMachine\Core\Steps
 */

namespace DataMachine\Core\Steps;

if (!defined('ABSPATH')) {
    exit;
}

abstract class Step {

    /**
     * Step type identifier.
     *
     * @var string
     */
    protected string $step_type;

    /**
     * Job ID from payload.
     *
     * @var int
     */
    protected int $job_id;

    /**
     * Flow step ID from payload.
     *
     * @var string
     */
    protected string $flow_step_id;

    /**
     * Data packets array from payload.
     *
     * @var array
     */
    protected array $dataPackets;

    /**
     * Flow step configuration from payload.
     *
     * @var array
     */
    protected array $flow_step_config;

    /**
     * Engine data from payload.
     *
     * @var array
     */
    protected array $engine_data;

    /**
     * Initialize step with type identifier.
     *
     * @param string $step_type Step type identifier (fetch, ai, publish, update)
     */
    public function __construct(string $step_type) {
        $this->step_type = $step_type;
    }

    /**
     * Execute step with unified payload handling.
     *
     * @param array $payload Unified step payload (job_id, flow_step_id, data, flow_step_config, engine_data)
     * @return array Updated data packet array
     */
    public function execute(array $payload): array {
        try {
            // Destructure payload to properties
            $this->destructurePayload($payload);

            // Log execution start
            $this->logStart();

            // Validate common configuration
            if (!$this->validateCommonConfiguration()) {
                return $this->dataPackets;
            }

            // Validate step-specific configuration
            if (!$this->validateStepConfiguration()) {
                return $this->dataPackets;
            }

            // Execute step-specific logic
            return $this->executeStep();

        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Execute step-specific logic.
     * Called after payload destructuring and common validation.
     *
     * @return array Updated data packet array
     */
    abstract protected function executeStep(): array;

    /**
     * Validate step-specific configuration requirements.
     * Default implementation checks for handler_slug. Override for custom validation.
     *
     * @return bool True if configuration is valid, false otherwise
     */
    protected function validateStepConfiguration(): bool {
        $handler = $this->getHandlerSlug();

        if (empty($handler)) {
            $this->logConfigurationError('Step requires handler configuration', [
                'available_flow_step_config' => array_keys($this->flow_step_config)
            ]);
            return false;
        }

        return true;
    }

    /**
     * Extract and store payload fields to class properties.
     *
     * @param array $payload Unified step payload
     * @return void
     */
    protected function destructurePayload(array $payload): void {
        $this->job_id = $payload['job_id'] ?? 0;
        $this->flow_step_id = $payload['flow_step_id'] ?? '';
        $this->dataPackets = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $this->flow_step_config = $payload['flow_step_config'] ?? [];
        $this->engine_data = $payload['engine_data'] ?? [];
    }

    /**
     * Centralized logging with consistent context.
     *
     * @param string $level Log level (debug, info, warning, error)
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    protected function log(string $level, string $message, array $context = []): void {
        $full_context = array_merge([
            'flow_step_id' => $this->flow_step_id,
            'step_type' => $this->step_type
        ], $context);

        do_action('datamachine_log', $level, $message, $full_context);
    }

    /**
     * Log step execution start with standard context.
     *
     * @return void
     */
    protected function logStart(): void {
        $this->log('debug', $this->step_type . ': Starting execution');
    }

    /**
     * Log configuration errors with consistent formatting.
     *
     * @param string $message Error message
     * @param array $additional_context Additional context beyond flow_step_id
     * @return void
     */
    protected function logConfigurationError(string $message, array $additional_context = []): void {
        $this->log('error', $this->step_type . ': ' . $message, $additional_context);
    }



    /**
     * Get handler slug from flow step configuration.
     *
     * @return string|null Handler slug or null if not set
     */
    protected function getHandlerSlug(): ?string {
        return $this->flow_step_config['handler_slug'] ?? null;
    }

    /**
     * Get handler configuration from flow step configuration.
     *
     * @return array Handler configuration array
     */
    protected function getHandlerConfig(): array {
        return $this->flow_step_config['handler_config'] ?? [];
    }

    /**
     * Handle exceptions with consistent logging and data packet return.
     *
     * @param \Exception $e Exception instance
     * @param string $context Context where exception occurred
     * @return array Data packet array (unchanged on exception)
     */
    protected function handleException(\Exception $e, string $context = 'execution'): array {
        $this->log('error', $this->step_type . ': Exception during ' . $context, [
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return $this->dataPackets;
    }

    /**
     * Validate common configuration requirements shared by all steps.
     *
     * @return bool True if common validation passes, false otherwise
     */
    protected function validateCommonConfiguration(): bool {
        if (empty($this->flow_step_config)) {
            $this->logConfigurationError('No step configuration provided');
            return false;
        }

        return true;
    }
}
