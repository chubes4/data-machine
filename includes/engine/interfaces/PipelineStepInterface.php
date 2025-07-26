<?php
/**
 * Pipeline Step Interface - Closed-Door Philosophy
 * 
 * Defines the contract for pipeline steps in the closed-door architecture.
 * Each step operates on DataPacket from previous step only, with no backward
 * looking or complex data retrieval. This enables clean, sequential data flow.
 * 
 * Steps must:
 * - Input Steps: Collect from external sources, return DataPacket
 * - AI Steps: Transform DataPacket from previous step, return DataPacket  
 * - Output Steps: Publish DataPacket from previous step, return result
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
     * Execute the pipeline step for a given job (Closed-Door Philosophy).
     * 
     * Each step operates independently on DataPacket from previous step only.
     * No backward looking or complex data retrieval allowed.
     *
     * @param int $job_id The job ID to process.
     * @return bool True on success, false on failure.
     */
    public function execute(int $job_id): bool;
    
    /**
     * Get prompt field definitions for this pipeline step.
     * 
     * Returns an array of field definitions that will be used to generate
     * the module configuration form dynamically. Each field definition
     * should include 'type', 'label', 'description', and other metadata.
     *
     * @return array Array of prompt field definitions, or empty array if no prompts needed.
     * 
     * @example
     * return [
     *     'process_prompt' => [
     *         'type' => 'textarea',
     *         'label' => 'Processing Instructions',
     *         'description' => 'Instructions for AI processing',
     *         'required' => true,
     *         'rows' => 5,
     *         'placeholder' => 'Enter processing instructions...'
     *     ]
     * ];
     */
    public static function get_prompt_fields(): array;
}