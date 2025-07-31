<?php
/**
 * ProcessedItems database cleanup operations component.
 *
 * Handles cleanup and maintenance operations for processed items.
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

class ProcessedItemsCleanup {

    /**
     * The name for the processed items table.
     * @var string
     */
    private $table_name;

    /**
     * Initialize the cleanup component.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dm_processed_items';
    }

    /**
     * Clean up old processed items to keep table size manageable.
     *
     * @param int $days_to_keep Days to keep processed item records (default 90).
     * @return int Number of items deleted.
     */
    public function cleanup_old_processed_items(int $days_to_keep = 90): int {
        global $wpdb;

        $deleted = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->table_name} 
             WHERE processed_timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days_to_keep
        ) );

        $logger = apply_filters('dm_get_logger', null);
        if ($logger && $deleted > 0) {
            $logger->info('Cleaned up old processed items', [
                'deleted_count' => $deleted,
                'days_to_keep' => $days_to_keep,
                'table' => $this->table_name
            ]);
        }

        return $deleted !== false ? $deleted : 0;
    }

    /**
     * Clean up processed items for a specific flow.
     *
     * @param int $flow_id The ID of the flow.
     * @return int Number of items deleted.
     */
    public function cleanup_processed_items_for_flow(int $flow_id): int {
        global $wpdb;

        $deleted = $wpdb->delete(
            $this->table_name,
            array('flow_id' => $flow_id),
            array('%d')
        );

        $logger = apply_filters('dm_get_logger', null);
        if ($logger && $deleted > 0) {
            $logger->info('Cleaned up processed items for flow', [
                'flow_id' => $flow_id,
                'deleted_count' => $deleted,
                'table' => $this->table_name
            ]);
        }

        return $deleted !== false ? $deleted : 0;
    }

    /**
     * Clean up processed items by source type.
     *
     * @param string $source_type The source type to clean up.
     * @return int Number of items deleted.
     */
    public function cleanup_processed_items_by_source_type(string $source_type): int {
        global $wpdb;

        $deleted = $wpdb->delete(
            $this->table_name,
            array('source_type' => $source_type),
            array('%s')
        );

        $logger = apply_filters('dm_get_logger', null);
        if ($logger && $deleted > 0) {
            $logger->info('Cleaned up processed items by source type', [
                'source_type' => $source_type,
                'deleted_count' => $deleted,
                'table' => $this->table_name
            ]);
        }

        return $deleted !== false ? $deleted : 0;
    }

    /**
     * Get cleanup statistics for monitoring.
     *
     * @return array Cleanup statistics.
     */
    public function get_cleanup_statistics(): array {
        global $wpdb;

        $stats = array();

        // Total processed items count
        $stats['total_items'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name}"
        );

        // Items older than 30 days
        $stats['items_older_than_30_days'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE processed_timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // Items older than 90 days
        $stats['items_older_than_90_days'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE processed_timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );

        // Items by source type
        $source_type_counts = $wpdb->get_results(
            "SELECT source_type, COUNT(*) as count 
             FROM {$this->table_name} 
             GROUP BY source_type"
        );

        $stats['by_source_type'] = array();
        foreach ($source_type_counts as $row) {
            $stats['by_source_type'][$row->source_type] = (int) $row->count;
        }

        return $stats;
    }

    /**
     * Optimize the processed items table.
     *
     * @return bool True on success, false on failure.
     */
    public function optimize_table(): bool {
        global $wpdb;

        $result = $wpdb->query("OPTIMIZE TABLE {$this->table_name}");

        $logger = apply_filters('dm_get_logger', null);
        if ($logger) {
            if ($result !== false) {
                $logger->info('Optimized processed items table', [
                    'table' => $this->table_name
                ]);
            } else {
                $logger->warning('Failed to optimize processed items table', [
                    'table' => $this->table_name,
                    'error' => $wpdb->last_error
                ]);
            }
        }

        return $result !== false;
    }
}