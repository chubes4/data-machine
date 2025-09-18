<?php
/**
 * Data Packet Management Filter
 *
 * Centralized data packet creation with standardized structure.
 * Adds timestamp and type fields while preserving all packet data.
 *
 * @package DataMachine\Engine\Filters
 * @since 1.0.0
 */

// Standard WordPress security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add packet to data array with standardized structure.
 *
 * @param array $data Current data packet array
 * @param array $packet_data Packet data to add
 * @param string $flow_step_id Flow step identifier
 * @param string $step_type Step type
 * @return array Modified data array with new packet
 */
add_filter('dm_data_packet', function($data, $packet_data, $flow_step_id, $step_type) {
    // Preserve all fields, add missing type/timestamp
    $packet = array_merge($packet_data, [
        'type' => $packet_data['type'] ?? $step_type,
        'timestamp' => $packet_data['timestamp'] ?? time()
    ]);

    array_unshift($data, $packet);
    return $data;
}, 10, 4);