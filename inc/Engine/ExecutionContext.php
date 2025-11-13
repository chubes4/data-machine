<?php
/**
 * Execution context for Data Machine pipeline execution.
 *
 * Provides access to execution-scoped data like job_id and flow_step_id
 * for use in contexts where direct parameters aren't available (e.g., error handling).
 *
 * @package DataMachine\Engine
 */

namespace DataMachine\Engine;

class ExecutionContext {
    /**
     * Current job ID during pipeline execution.
     *
     * @var int|null
     */
    public static $job_id = null;

    /**
     * Current flow step ID during pipeline execution.
     *
     * @var string|null
     */
    public static $flow_step_id = null;

    /**
     * Current data packet during pipeline execution.
     *
     * Contains the structured data flowing through the pipeline steps.
     *
     * @var array|null
     */
    public static $data = null;

    /**
     * Clear execution context.
     *
     * Should be called after pipeline execution completes.
     */
    public static function clear() {
        self::$job_id = null;
        self::$flow_step_id = null;
        self::$data = null;
    }
}