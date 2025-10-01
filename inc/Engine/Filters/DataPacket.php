<?php
/**
 * Centralized data packet creation with standardized structure.
 *
 * @package DataMachine\Engine\Filters
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add packet to data array with standardized structure.
 *
 * Preserves all packet fields while ensuring type and timestamp exist.
 * Adds new packet to front of data array for chronological workflow history.
 *
 * @param array $data Current data packet array
 * @param array $packet_data Packet data to add
 * @param string $flow_step_id Flow step identifier
 * @param string $step_type Step type
 * @return array Modified data array with new packet at front
 */
add_filter('dm_data_packet', function($data, $packet_data, $flow_step_id, $step_type) {
    $packet = array_merge($packet_data, [
        'type' => $packet_data['type'] ?? $step_type,
        'timestamp' => $packet_data['timestamp'] ?? time()
    ]);

    array_unshift($data, $packet);
    return $data;
}, 10, 4);