<?php
/**
 * Pipeline Step Interface
 * 
 * Defines the contract that all pipeline steps must implement.
 * This enables the extensible pipeline architecture where third-party
 * plugins can create custom steps that integrate seamlessly.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/engine/interfaces
 * @since      NEXT_VERSION
 */

namespace DataMachine\Engine\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

interface PipelineStepInterface {
    
    /**
     * Execute the pipeline step for a given job.
     *
     * @param int $job_id The job ID to process.
     * @return bool True on success, false on failure.
     */
    public function execute(int $job_id): bool;
}