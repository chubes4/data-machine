<?php

namespace DataMachine\Core\Steps\Receiver;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Receiver Step - Framework for webhook reception
 * 
 * Minimal step implementation that provides framework for webhook handling.
 * Non-functional until handlers are implemented.
 * 
 * @package DataMachine\Core\Steps\Receiver
 * @since 0.1.0
 */
class ReceiverStep {

    /**
     * Execute webhook reception (stub implementation)
     * 
     * @param int $job_id The job ID to process
     * @param array $data_packet Array of data packets from previous steps
     * @param array $step_config Step configuration for receiver step
     * @return array Returns empty array - no handlers implemented yet
     */
    public function execute(int $job_id, array $data_packet = [], array $step_config = []): array {
        do_action('dm_log', 'error', 'Receiver Step: No handlers implemented yet', [
            'job_id' => $job_id,
            'step_type' => 'receiver'
        ]);
        
        // Return empty array - step cannot complete without handlers
        return [];
    }
}