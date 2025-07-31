<?php
/**
 * ProcessedItems database coordinator class - maintains public API while delegating to focused components.
 *
 * Follows handler-style modular architecture where the main class coordinates
 * between focused internal components for single responsibility compliance.
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
     * Internal components for focused responsibilities.
     */
    private $operations;
    private $queries;
    private $cleanup;

    /**
     * Initialize the coordinator and internal components.
     * Uses direct instantiation - no caching complexity.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dm_processed_items';
        
        // Initialize focused components directly
        $this->operations = new ProcessedItemsOperations();
        $this->queries = new ProcessedItemsQueries();
        $this->cleanup = new ProcessedItemsCleanup();
    }

    // ========================================
    // Core Operations (delegated to ProcessedItemsOperations)
    // ========================================

    /**
     * Adds a record indicating an item has been processed.
     */
    public function add_processed_item( int $flow_id, string $source_type, string $item_identifier ): bool {
        return $this->operations->add_processed_item($flow_id, $source_type, $item_identifier);
    }

    /**
     * Checks if a specific item has already been processed.
     */
    public function has_item_been_processed( int $flow_id, string $source_type, string $item_identifier ): bool {
        return $this->operations->has_item_been_processed($flow_id, $source_type, $item_identifier);
    }

    // ========================================
    // Query Operations (delegated to ProcessedItemsQueries)
    // ========================================

    /**
     * Checks if any item has been processed for a given flow ID.
     */
    public function has_any_processed_items_for_flow(int $flow_id): bool {
        return $this->queries->has_any_processed_items_for_flow($flow_id);
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
            flow_id BIGINT(20) UNSIGNED NOT NULL,
            source_type VARCHAR(50) NOT NULL,
            item_identifier VARCHAR(255) NOT NULL,
            processed_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY `flow_source_item` (flow_id, source_type, item_identifier(191)),
            KEY `flow_id` (flow_id),
            KEY `source_type` (source_type)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
        
        // Log table creation
        $logger = apply_filters('dm_get_logger', null);
        if ( $logger ) {
            $logger->info( 'Created processed items database table', [
                'table_name' => $this->table_name,
                'action' => 'create_table'
            ] );
        }
    }
}