<?php
/**
 * ProcessedItems database query operations component.
 *
 * Handles query operations for processed items: complex lookups and analytics.
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

class ProcessedItemsQueries {

    /**
     * The name for the processed items table.
     * @var string
     */
    private $table_name;

    /**
     * Initialize the queries component.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dm_processed_items';
    }

    /**
     * Checks if any item has been processed for a given flow ID.
     * Efficiently checks for at least one record.
     *
     * @param int $flow_id The ID of the flow.
     * @return bool True if any item has been processed, false otherwise.
     */
    public function has_any_processed_items_for_flow(int $flow_id): bool {
        global $wpdb;

        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE flow_id = %d LIMIT 1",
            $flow_id
        ) );

        return $count > 0;
    }

    /**
     * Get count of processed items for a specific flow.
     *
     * @param int $flow_id The ID of the flow.
     * @return int Number of processed items.
     */
    public function get_processed_items_count_for_flow(int $flow_id): int {
        global $wpdb;

        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE flow_id = %d",
            $flow_id
        ) );

        return (int) $count;
    }

    /**
     * Get count of processed items by source type.
     *
     * @param string $source_type The source type to count.
     * @return int Number of processed items for the source type.
     */
    public function get_processed_items_count_by_source_type(string $source_type): int {
        global $wpdb;

        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE source_type = %s",
            $source_type
        ) );

        return (int) $count;
    }

    /**
     * Get processed items for a specific flow with pagination.
     *
     * @param int $flow_id The ID of the flow.
     * @param int $limit Number of items to retrieve.
     * @param int $offset Offset for pagination.
     * @return array Array of processed item objects.
     */
    public function get_processed_items_for_flow(int $flow_id, int $limit = 50, int $offset = 0): array {
        global $wpdb;

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE flow_id = %d 
             ORDER BY processed_timestamp DESC 
             LIMIT %d OFFSET %d",
            $flow_id,
            $limit,
            $offset
        ) );

        return $results ?: [];
    }

    /**
     * Get recent processed items across all flows.
     *
     * @param int $limit Number of items to retrieve.
     * @return array Array of processed item objects.
     */
    public function get_recent_processed_items(int $limit = 100): array {
        global $wpdb;

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             ORDER BY processed_timestamp DESC 
             LIMIT %d",
            $limit
        ) );

        return $results ?: [];
    }
}