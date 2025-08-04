<?php
/**
 * ProcessedItems database core operations component.
 *
 * Handles basic database operations for processed items: tracking and duplicate checking.
 * Part of the modular ProcessedItems architecture following single responsibility principle.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/database/processed_items
 * @since      0.16.0
 */

namespace DataMachine\Core\Database\ProcessedItems;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ProcessedItemsOperations {

    /**
     * The name for the processed items table.
     * @var string
     */
    private $table_name;

    /**
     * Initialize the operations component.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dm_processed_items';
    }

    /**
     * Checks if a specific item has already been processed for a given flow and source type.
     *
     * @param int    $flow_id        The ID of the flow.
     * @param string $source_type    The type of the data source (e.g., 'rss', 'reddit').
     * @param string $item_identifier The unique identifier for the item (e.g., GUID, post ID).
     * @return bool True if the item has been processed, false otherwise.
     */
    public function has_item_been_processed( int $flow_id, string $source_type, string $item_identifier ): bool {
        global $wpdb;

        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE flow_id = %d AND source_type = %s AND item_identifier = %s",
            $flow_id,
            $source_type,
            $item_identifier
        ) );

        return $count > 0;
    }

    /**
     * Adds a record indicating an item has been processed.
     *
     * @param int    $flow_id        The ID of the flow.
     * @param string $source_type    The type of the data source.
     * @param string $item_identifier The unique identifier for the item.
     * @return bool True on successful insertion, false otherwise.
     */
    public function add_processed_item( int $flow_id, string $source_type, string $item_identifier ): bool {
        global $wpdb;

        // Check if it exists first to avoid unnecessary insert attempts and duplicate key errors
        if ($this->has_item_been_processed($flow_id, $source_type, $item_identifier)) {
            // Item already processed - return true to indicate success (idempotent behavior)
            $logger = apply_filters('dm_get_logger', null);
            if ($logger) {
                $logger->debug("Item already processed, skipping duplicate insert.", [
                    'flow_id' => $flow_id,
                    'source_type' => $source_type,
                    'item_identifier' => substr($item_identifier, 0, 100) . '...'
                ]);
            }
            return true;
        }

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'flow_id'         => $flow_id,
                'source_type'     => $source_type,
                'item_identifier' => $item_identifier,
                // processed_timestamp defaults to NOW()
            ),
            array(
                '%d', // flow_id
                '%s', // source_type
                '%s', // item_identifier
            )
        );

        if ($result === false) {
             // Log error - but check if it's a duplicate key error first
             $db_error = $wpdb->last_error;
             
             // If it's a duplicate key error, treat as success (race condition handling)
             if (strpos($db_error, 'Duplicate entry') !== false) {
                 $logger = apply_filters('dm_get_logger', null);
                 if ($logger) {
                     $logger->debug("Duplicate key detected during insert - item already processed by another process.", [
                         'flow_id' => $flow_id,
                         'source_type' => $source_type,
                         'item_identifier' => substr($item_identifier, 0, 100) . '...'
                     ]);
                 }
                 return true; // Treat duplicate as success
             }
             
             // Use Logger Service if available for actual errors
             $logger = apply_filters('dm_get_logger', null);
             if ($logger) {
                 $logger->error("Failed to insert processed item.", [
                     'flow_id' => $flow_id,
                     'source_type' => $source_type,
                     'item_identifier' => substr($item_identifier, 0, 100) . '...', // Avoid logging potentially huge identifiers
                     'db_error' => $db_error
                 ]);
             }
             return false;
        }

        return true;
    }
}