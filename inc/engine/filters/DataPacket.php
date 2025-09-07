<?php
/**
 * Data Machine Engine - Data Packet Management Filter
 *
 * Centralizes data packet creation and management across all step types.
 * Provides unified structure, logging, and validation for pipeline data flow.
 *
 * @package DataMachine\Engine\Filters
 * @since 1.0.0
 */

// Standard WordPress security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Data Packet Filter Handler
 * 
 * Centralizes data packet creation with consistent structure and logging.
 * Used by all step types (fetch, ai, update, publish) for uniform data management.
 *
 * @param array $data Current data packet array
 * @param array $packet_data JSON object with packet information to add
 * @param string $flow_step_id Flow step ID for logging context  
 * @param string $step_type Step type ('fetch', 'ai', 'update', 'publish')
 * @return array Modified data packet array with new packet added
 */
add_filter('dm_data_packet', function($data, $packet_data, $flow_step_id, $step_type) {
    // Build standardized packet structure - preserve all fields, add timestamp
    $packet = array_merge($packet_data, [
        'type' => $packet_data['type'] ?? $step_type,
        'timestamp' => $packet_data['timestamp'] ?? time()
    ]);
    
    // Add to front of array (newest first - established pattern)
    array_unshift($data, $packet);
    
    // Centralized logging for all data packet operations
    do_action('dm_log', 'debug', "DataPacket: Added {$packet['type']} packet", [
        'flow_step_id' => $flow_step_id,
        'step_type' => $step_type,
        'packet_type' => $packet['type'],
        'total_packets' => count($data)
    ]);
    
    return $data;
}, 10, 4);