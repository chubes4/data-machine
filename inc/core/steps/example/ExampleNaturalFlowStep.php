<?php
/**
 * Example Step demonstrating Natural Data Flow implementation
 * 
 * This example shows how to implement the new natural data flow pattern
 * while maintaining backward compatibility with existing step patterns.
 * 
 * NEW NATURAL FLOW:
 * - Receives DataPacket directly as method parameter
 * - Can access full pipeline context via dm_get_context filter
 * - No need to manually retrieve DataPackets from database
 * 
 * BACKWARD COMPATIBILITY:
 * - Falls back to legacy pattern if step doesn't support natural flow
 * - Existing steps continue to work without modification
 * - Gradual migration path for all steps
 * 
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/core/steps/example
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Steps\Example;

use DataMachine\Core\DataPacket;

if (!defined('ABSPATH')) {
    exit;
}

class ExampleNaturalFlowStep {

    /**
     * Execute step with natural data flow - NEW SIGNATURE
     * 
     * This method demonstrates the new natural data flow pattern where:
     * 1. Latest DataPacket is passed directly as parameter (no database queries needed)
     * 2. Full pipeline context available via dm_get_context filter
     * 3. ProcessingOrchestrator handles all data retrieval automatically
     * 
     * @param int $job_id The job ID to process
     * @param DataPacket|null $data_packet Latest DataPacket from previous step (null for first step)
     * @return bool True on success, false on failure
     */
    public function execute(int $job_id, ?DataPacket $data_packet = null): bool {
        $logger = apply_filters('dm_get_logger', null);
        
        try {
            $logger->info('ExampleNaturalFlowStep: Starting with natural data flow', [
                'job_id' => $job_id,
                'data_packet_available' => $data_packet !== null,
                'flow_type' => 'natural'
            ]);

            // NATURAL FLOW: DataPacket passed directly - no database queries needed!
            if ($data_packet) {
                $logger->info('Processing DataPacket from natural flow', [
                    'job_id' => $job_id,
                    'source_type' => $data_packet->metadata['source_type'] ?? 'unknown',
                    'content_length' => $data_packet->getContentLength(),
                    'title' => $data_packet->content['title'] ?? 'No title'
                ]);
            } else {
                $logger->info('No DataPacket available (first step or no previous data)', [
                    'job_id' => $job_id
                ]);
            }

            // ACCESS FULL PIPELINE CONTEXT: Available via dm_get_context filter
            $context = apply_filters('dm_get_context', null);
            if ($context) {
                $logger->info('Full pipeline context available via filter', [
                    'job_id' => $job_id,
                    'current_position' => $context['current_step_position'] ?? 'unknown',
                    'previous_packets_count' => count($context['all_previous_packets'] ?? []),
                    'pipeline_progress' => $context['pipeline_summary']['progress_percent'] ?? 0
                ]);

                // Example: Access all previous DataPackets for comprehensive processing
                $all_previous = $context['all_previous_packets'] ?? [];
                if (!empty($all_previous)) {
                    $logger->info('Processing context from all previous steps', [
                        'job_id' => $job_id,
                        'total_previous_packets' => count($all_previous),
                        'source_types' => array_unique(array_map(function($packet) {
                            return $packet->metadata['source_type'] ?? 'unknown';
                        }, $all_previous))
                    ]);
                }
            }

            // STEP PROCESSING: Your actual step logic here
            $processed_data_packet = $this->process_data($job_id, $data_packet, $context);
            
            if (!$processed_data_packet) {
                return $this->fail_job($job_id, 'Failed to process data in example step');
            }

            // STORE RESULT: Save processed DataPacket for next step
            $success = $this->store_step_data_packet($job_id, $processed_data_packet);

            if ($success) {
                $logger->info('ExampleNaturalFlowStep: Processing completed successfully', [
                    'job_id' => $job_id,
                    'output_content_length' => $processed_data_packet->getContentLength(),
                    'flow_type' => 'natural'
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            $logger->error('ExampleNaturalFlowStep: Exception during processing', [
                'job_id' => $job_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->fail_job($job_id, 'Example step failed: ' . $e->getMessage());
        }
    }

    /**
     * Process data with natural flow inputs.
     * 
     * @param int $job_id Job ID
     * @param DataPacket|null $data_packet Latest DataPacket
     * @param array|null $context Full pipeline context
     * @return DataPacket|null Processed DataPacket or null on failure
     */
    private function process_data(int $job_id, ?DataPacket $data_packet, ?array $context): ?DataPacket {
        // Example processing logic
        if ($data_packet) {
            // Process existing DataPacket
            $processed_packet = clone $data_packet;
            $processed_packet->content['body'] = '[PROCESSED] ' . $processed_packet->content['body'];
            $processed_packet->addProcessingStep('example_natural_flow');
            return $processed_packet;
        } else {
            // Create new DataPacket (for first step)
            return new DataPacket(
                'Example Step Output',
                'This is example content created by ExampleNaturalFlowStep',
                'example_step'
            );
        }
    }

    /**
     * Store data packet for current step.
     *
     * @param int $job_id The job ID.
     * @param DataPacket $data_packet The data packet to store.
     * @return bool True on success, false on failure.
     */
    private function store_step_data_packet(int $job_id, DataPacket $data_packet): bool {
        $pipeline_context = apply_filters('dm_get_pipeline_context', null);
        if (!$pipeline_context) {
            return false;
        }
        
        $current_step = $pipeline_context->get_current_step_name($job_id);
        if (!$current_step) {
            return false;
        }
        
        $db_jobs = apply_filters('dm_get_database_service', null, 'jobs');
        $json_data = $data_packet->toJson();
        
        return $db_jobs->update_step_data_by_name($job_id, $current_step, $json_data);
    }

    /**
     * Fail a job with an error message.
     *
     * @param int $job_id The job ID.
     * @param string $message The error message.
     * @return bool Always returns false for easy return usage.
     */
    private function fail_job(int $job_id, string $message): bool {
        $job_status_manager = apply_filters('dm_get_job_status_manager', null);
        $logger = apply_filters('dm_get_logger', null);
        if ($job_status_manager) {
            $job_status_manager->fail($job_id, $message);
        }
        if ($logger) {
            $logger->error($message, ['job_id' => $job_id]);
        }
        return false;
    }

    /**
     * Define configuration fields for this step.
     * 
     * @return array Field definitions for UI configuration
     */
    public static function get_prompt_fields(): array {
        return [
            'title' => [
                'type' => 'text',
                'label' => 'Step Title',
                'description' => 'A descriptive name for this example step',
                'required' => true,
                'placeholder' => 'e.g., "Example Processing Step"'
            ],
            'processing_note' => [
                'type' => 'textarea',
                'label' => 'Processing Instructions',
                'description' => 'Instructions for how this step should process the data',
                'placeholder' => 'Describe what this step should do with the incoming DataPacket...',
                'rows' => 4
            ]
        ];
    }
}