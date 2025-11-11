<?php
/**
 * ProcessedItems database service - prevents duplicate processing at flow step level.
 *
 * Simple, focused service that tracks processed items by flow_step_id to prevent
 * duplicate processing. Core responsibility: duplicate prevention only.
 *
 * @package    Data_Machine
 * @subpackage Core\Database\ProcessedItems
 * @since      0.16.0
 */

namespace DataMachine\Core\Database\ProcessedItems;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ProcessedItems {

    private $table_name;
    private $wpdb;


    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $this->wpdb->prefix . 'dm_processed_items';
    }


    /**
     * Checks if a specific item has already been processed for a given flow step and source type.
     *
     * @param string $flow_step_id   The ID of the flow step (composite: pipeline_step_id_flow_id).
     * @param string $source_type    The type of the data source (e.g., 'rss', 'reddit').
     * @param string $item_identifier The unique identifier for the item (e.g., GUID, post ID).
     * @return bool True if the item has been processed, false otherwise.
     */
    public function has_item_been_processed( string $flow_step_id, string $source_type, string $item_identifier ): bool {

        $cache_key = $this->get_processed_item_cache_key( $flow_step_id, $source_type, $item_identifier );
        $cached_result = get_transient( $cache_key );

        if ( false !== $cached_result ) {
            return $cached_result > 0;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $count = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE flow_step_id = %s AND source_type = %s AND item_identifier = %s", $this->table_name, $flow_step_id, $source_type, $item_identifier ) );

        set_transient( $cache_key, $count, 0 );

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

        // Check if it exists first to avoid unnecessary insert attempts and duplicate key errors
        if ($this->has_item_been_processed($flow_step_id, $source_type, $item_identifier)) {
            // Item already processed - return true to indicate success (idempotent behavior)
            return true;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $this->wpdb->insert(
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

        // Clear cache for this processed item after successful insertion
        if ( $result !== false ) {
            $cache_key = $this->get_processed_item_cache_key( $flow_step_id, $source_type, $item_identifier );
            delete_transient( $cache_key );
        }

        if ($result === false) {
             // Log error - but check if it's a duplicate key error first
             $db_error = $this->wpdb->last_error;
             
             // If it's a duplicate key error, treat as success (race condition handling)
             if (strpos($db_error, 'Duplicate entry') !== false) {
                 return true; // Treat duplicate as success
             }
             
             // Use Logger Service if available for actual errors
             do_action('datamachine_log', 'error', "Failed to insert processed item.", [
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
        
        if (empty($criteria)) {
            do_action('datamachine_log', 'warning', 'No criteria provided for processed items deletion');
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
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $result = $this->wpdb->query( $this->wpdb->prepare( "DELETE FROM %i WHERE flow_step_id LIKE %s", $this->table_name, $pattern ) );

            // Clear processed items cache after deletion
            if ( $result !== false ) {
                $this->clear_cache_pattern( 'datamachine_processed_*' );
            }
        } 
        // Handle pipeline_id (get all flows for pipeline and delete their processed items)
        else if (!empty($criteria['pipeline_id']) && empty($criteria['flow_step_id'])) {
            // Get all flows for this pipeline using the existing filter
            $pipeline_flows = apply_filters('datamachine_get_pipeline_flows', [], $criteria['pipeline_id']);
            $flow_ids = array_column($pipeline_flows, 'flow_id');
            
            if (empty($flow_ids)) {
                do_action('datamachine_log', 'debug', 'No flows found for pipeline, nothing to delete', [
                    'pipeline_id' => $criteria['pipeline_id']
                ]);
                return 0;
            }
            
            // Build IN clause for multiple flow IDs
            $flow_patterns = array_map(function($flow_id) {
                return '%_' . $flow_id;
            }, $flow_ids);
            
            // Execute individual DELETE queries for each pattern
            $total_deleted = 0;
            foreach ($flow_patterns as $pattern) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $deleted = $this->wpdb->query( $this->wpdb->prepare( "DELETE FROM %i WHERE flow_step_id LIKE %s", $this->table_name, $pattern ) );
                if ($deleted !== false) {
                    $total_deleted += $deleted;
                }
            }
            $result = $total_deleted;

            // Clear processed items cache after deletion
            if ( $result !== false ) {
                $this->clear_cache_pattern( 'datamachine_processed_*' );
            }
        }
        else if (!empty($where)) {
            // Build cache key and log what we're about to delete
            $cache_key = 'datamachine_count_processed_' . md5(serialize($where));
            $cached_count = get_transient( $cache_key );

            if ( false === $cached_count ) {
                // Handle specific WHERE combinations without dynamic SQL building
                if (isset($where['job_id']) && isset($where['flow_step_id']) && isset($where['source_type'])) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                    $count = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE job_id = %d AND flow_step_id = %s AND source_type = %s", $this->table_name, $where['job_id'], $where['flow_step_id'], $where['source_type'] ) );
                } elseif (isset($where['job_id']) && isset($where['flow_step_id'])) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                    $count = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE job_id = %d AND flow_step_id = %s", $this->table_name, $where['job_id'], $where['flow_step_id'] ) );
                } elseif (isset($where['job_id']) && isset($where['source_type'])) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                    $count = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE job_id = %d AND source_type = %s", $this->table_name, $where['job_id'], $where['source_type'] ) );
                } elseif (isset($where['flow_step_id']) && isset($where['source_type'])) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                    $count = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE flow_step_id = %s AND source_type = %s", $this->table_name, $where['flow_step_id'], $where['source_type'] ) );
                } elseif (isset($where['job_id'])) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                    $count = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE job_id = %d", $this->table_name, $where['job_id'] ) );
                } elseif (isset($where['flow_step_id'])) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                    $count = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE flow_step_id = %s", $this->table_name, $where['flow_step_id'] ) );
                } elseif (isset($where['source_type'])) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                    $count = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE source_type = %s", $this->table_name, $where['source_type'] ) );
                } else {
                    $count = 0;
                }
                set_transient( $cache_key, $count, 300 ); // 5 minute cache for counts
            } else {
                $count = $cached_count;
            }

            do_action('datamachine_log', 'debug', 'Processed items deletion query analysis', [
                'where_conditions' => $where,
                'where_format' => $where_format,
                'items_to_delete' => $count,
                'table_name' => $this->table_name
            ]);

            // Standard delete with WHERE conditions
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $this->wpdb->delete($this->table_name, $where, $where_format);

            // Clear processed items cache after deletion
            if ( $result !== false ) {
                $this->clear_cache_pattern( 'datamachine_processed_*' );
            }
        } else {
            do_action('datamachine_log', 'warning', 'No valid criteria provided for processed items deletion');
            return false;
        }
        
        // Log the operation
        do_action('datamachine_log', 'debug', 'Deleted processed items', [
            'criteria' => $criteria,
            'items_deleted' => $result !== false ? $result : 0,
            'success' => $result !== false
        ]);
        
        return $result;
    }

    /**
     * Generate cache key for processed items
     *
     * @param string $flow_step_id Flow step ID
     * @param string $source_type Source type
     * @param string $item_identifier Item identifier
     * @return string Cache key
     */
    private function get_processed_item_cache_key( $flow_step_id, $source_type, $item_identifier ) {
        return 'datamachine_processed_' . $flow_step_id . '_' . $source_type . '_' . md5( $item_identifier );
    }

    /**
     * Clear all processed items cache entries
     *
     * Called by datamachine_clear_all_cache action to ensure processed items cache
     * is included in system-wide cache clearing operations.
     *
     * @return int Number of cache entries cleared
     */
    public function clear_all_processed_cache() {
        return $this->clear_cache_pattern( 'datamachine_processed_*' );
    }

    /**
     * Clear multiple cache entries matching a pattern
     *
     * @param string $pattern Pattern to match (using SQL LIKE syntax with %)
     * @return int Number of cache entries cleared
     */
    private function clear_cache_pattern( $pattern ) {

        // Get all transient keys matching the pattern
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $transient_keys = $this->wpdb->get_col( $this->wpdb->prepare( "SELECT option_name FROM %i WHERE option_name LIKE %s", $this->wpdb->options, '_transient_' . $pattern ) );

        $cleared_count = 0;
        foreach ( $transient_keys as $transient_key ) {
            // Remove the '_transient_' prefix to get the actual cache key
            $cache_key = str_replace( '_transient_', '', $transient_key );
            if ( delete_transient( $cache_key ) ) {
                $cleared_count++;
            }
        }

        return $cleared_count;
    }

    /**
     * Creates or updates the database table schema.
     * Should be called on plugin activation.
     */
    public function create_table() {
        $charset_collate = $this->wpdb->get_charset_collate();

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
        do_action('datamachine_log', 'debug', 'Created processed items database table', [
            'table_name' => $this->table_name,
            'action' => 'create_table'
        ]);
    }
}