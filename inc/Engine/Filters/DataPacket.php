<?php
/**
 * Standardized data packet creation.
 *
 * @package DataMachine\Engine\Filters
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds packet to front of data array with type and timestamp.
 */
add_filter('datamachine_data_packet', function($dataPackets, $packet_data, $flow_step_id, $step_type) {
    $packet = array_merge($packet_data, [
        'type' => $packet_data['type'] ?? $step_type,
        'timestamp' => $packet_data['timestamp'] ?? time()
    ]);

    array_unshift($dataPackets, $packet);
    return $dataPackets;
}, 10, 4);