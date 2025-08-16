<?php
/**
 * ProcessedItems database service - prevents duplicate processing at flow step level.
 *
 * Simple, focused service that tracks processed items by flow_step_id to prevent
 * duplicate processing. Core responsibility: duplicate prevention only.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/database/processed_items
 * @since      0.16.0
 */

namespace DataMachine\Core\Database\ProcessedItems;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ProcessedItems {

    /**
     * The name for the processed items table.
     * @var string
     */
    private $table_name;


    /**
     * Initialize the database service.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dm_processed_items';
    }

    // ========================================
    // Core Operations - Essential duplicate prevention functionality
    // ========================================

    /**
     * Checks if a specific item has already been processed for a given flow step and source type.
     *
     * @param string $flow_step_id   The ID of the flow step (composite: pipeline_step_id_flow_id).
     * @param string $source_type    The type of the data source (e.g., 'rss', 'reddit').
     * @param string $item_identifier The unique identifier for the item (e.g., GUID, post ID).
     * @return bool True if the item has been processed, false otherwise.
     */
    public function has_item_been_processed( string $flow_step_id, string $source_type, string $item_identifier ): bool {
        global $wpdb;

        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE flow_step_id = %s AND source_type = %s AND item_identifier = %s",
            $flow_step_id,
            $source_type,
            $item_identifier
        ) );

        return $count > 0;
    }

    /**
     * Adds a record indicating an item has been processed.
     *
     * @param string $flow_step_id   The ID of the flow step (composite: pipeline_step_id_flow_id).
     * @param string $source_type    The type of the data source.
     * @param string $item_identifier The unique identifier for the item.
     * @param int $job_id The ID of the job that processed this item.
     * @return bool True on successful insertion, false otherwise.
     */
    public function add_processed_item( string $flow_step_id, string $source_type, string $item_identifier, int $job_id ): bool {
        global $wpdb;

        // Check if it exists first to avoid unnecessary insert attempts and duplicate key errors
        if ($this->has_item_been_processed($flow_step_id, $source_type, $item_identifier)) {
            // Item already processed - return true to indicate success (idempotent behavior)
            do_action('dm_log', 'debug', "Item already processed, skipping duplicate insert.", [
                'flow_step_id' => $flow_step_id,
                'source_type' => $source_type,
                'item_identifier' => substr($item_identifier, 0, 100) . '...',
                'job_id' => $job_id
            ]);
            return true;
        }

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'flow_step_id'    => $flow_step_id,
                'source_type'     => $source_type,
                'item_identifier' => $item_identifier,
                'job_id'          => $job_id,
                // processed_timestamp defaults to NOW()
            ),
            array(
                '%s', // flow_step_id
                '%s', // source_type
                '%s', // item_identifier
                '%d', // job_id
            )
        );

        if ($result === false) {
             // Log error - but check if it's a duplicate key error first
             $db_error = $wpdb->last_error;
             
             // If it's a duplicate key error, treat as success (race condition handling)
             if (strpos($db_error, 'Duplicate entry') !== false) {
                 do_action('dm_log', 'debug', "Duplicate key detected during insert - item already processed by another process.", [
                     'flow_step_id' => $flow_step_id,
                     'source_type' => $source_type,
                     'item_identifier' => substr($item_identifier, 0, 100) . '...',
                     'job_id' => $job_id
                 ]);
                 return true; // Treat duplicate as success
             }
             
             // Use Logger Service if available for actual errors
             do_action('dm_log', 'error', "Failed to insert processed item.", [
                 'flow_step_id' => $flow_step_id,
                 'source_type' => $source_type,
                 'item_identifier' => substr($item_identifier, 0, 100) . '...', // Avoid logging potentially huge identifiers
                 'job_id' => $job_id,
                 'db_error' => $db_error
             ]);
             return false;
        }

        return true;
    }

    /**
     * Delete processed items based on various criteria.
     * 
     * Provides flexible deletion of processed items by job_id, flow_id, 
     * source_type, or flow_step_id. Used for cleanup operations and 
     * maintenance tasks.
     *
     * @param array $criteria Deletion criteria with keys:
     *                        - job_id: Delete by job ID
     *                        - flow_id: Delete by flow ID  
     *                        - source_type: Delete by source type
     *                        - flow_step_id: Delete by flow step ID
     * @return int|false Number of rows deleted or false on error
     */
    public function delete_processed_items(array $criteria = []): int|false {
        global $wpdb;
        
        if (empty($criteria)) {
            do_action('dm_log', 'warning', 'No criteria provided for processed items deletion');
            return false;
        }
        
        $where = [];
        $where_format = [];
        
        // Build WHERE conditions based on criteria
        if (!empty($criteria['job_id'])) {
            $where['job_id'] = $criteria['job_id'];
            $where_format[] = '%d';
        }
        
        if (!empty($criteria['flow_step_id'])) {
            $where['flow_step_id'] = $criteria['flow_step_id'];
            $where_format[] = '%s';
        }
        
        if (!empty($criteria['source_type'])) {
            $where['source_type'] = $criteria['source_type'];
            $where_format[] = '%s';
        }
        
        // Handle flow_id (needs LIKE query since flow_step_id contains it)
        if (!empty($criteria['flow_id']) && empty($criteria['flow_step_id'])) {
            $pattern = '%_' . $criteria['flow_id'];
            $sql = $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE flow_step_id LIKE %s",
                $pattern
            );
            $result = $wpdb->query($sql);
        } 
        // Handle pipeline_id (get all flows for pipeline and delete their processed items)
        else if (!empty($criteria['pipeline_id']) && empty($criteria['flow_step_id'])) {
            // Get all flows for this pipeline using the existing filter
            $pipeline_flows = apply_filters('dm_get_pipeline_flows', [], $criteria['pipeline_id']);
            $flow_ids = array_column($pipeline_flows, 'flow_id');
            
            if (empty($flow_ids)) {
                do_action('dm_log', 'debug', 'No flows found for pipeline, nothing to delete', [
                    'pipeline_id' => $criteria['pipeline_id']
                ]);
                return 0;
            }
            
            // Build IN clause for multiple flow IDs
            $flow_patterns = array_map(function($flow_id) {
                return '%_' . $flow_id;
            }, $flow_ids);
            
            $placeholders = implode(' OR flow_step_id LIKE ', array_fill(0, count($flow_patterns), '%s'));
            $sql = $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE flow_step_id LIKE {$placeholders}",
                ...$flow_patterns
            );
            $result = $wpdb->query($sql);
        } 
        else if (!empty($where)) {
            // Debug: Check what would be matched before deletion
            $count_query = "SELECT COUNT(*) FROM {$this->table_name} WHERE " . implode(' AND ', array_map(function($key) { return "$key = %s"; }, array_keys($where)));
            $count = $wpdb->get_var($wpdb->prepare($count_query, array_values($where)));
            
            do_action('dm_log', 'debug', 'Processed items deletion query analysis', [
                'where_conditions' => $where,
                'where_format' => $where_format,
                'items_to_delete' => $count,
                'table_name' => $this->table_name
            ]);
            
            // Standard delete with WHERE conditions
            $result = $wpdb->delete($this->table_name, $where, $where_format);
        } else {
            do_action('dm_log', 'warning', 'No valid criteria provided for processed items deletion');
            return false;
        }
        
        // Log the operation
        do_action('dm_log', 'debug', 'Deleted processed items', [
            'criteria' => $criteria,
            'items_deleted' => $result !== false ? $result : 0,
            'success' => $result !== false
        ]);
        
        return $result;
    }

    // ========================================
    // Static Methods (table creation)
    // ========================================

    /**
     * Creates or updates the database table schema.
     * Should be called on plugin activation.
     */
    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Use dbDelta for proper table creation/updates
        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            flow_step_id VARCHAR(255) NOT NULL,
            source_type VARCHAR(50) NOT NULL,
            item_identifier VARCHAR(255) NOT NULL,
            job_id BIGINT(20) UNSIGNED NOT NULL,
            processed_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY `flow_source_item` (flow_step_id, source_type, item_identifier(191)),
            KEY `flow_step_id` (flow_step_id),
            KEY `source_type` (source_type),
            KEY `job_id` (job_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
        
        // Log table creation
        do_action('dm_log', 'debug', 'Created processed items database table', [
            'table_name' => $this->table_name,
            'action' => 'create_table'
        ]);
    }
}