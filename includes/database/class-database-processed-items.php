<?php
/**
 * Manages the database table for tracking processed items to prevent duplicates.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/database
 * @since      0.16.0 // Or appropriate version
 */
class Data_Machine_Database_Processed_Items {

    /**
     * The name for the processed items table.
     * @var string
     */
    private $table_name;

    /**
     * Logger instance (optional).
     * @var Data_Machine_Logger|null
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param Data_Machine_Logger|null $logger Logger Service instance (optional).
     */
    public function __construct( ?Data_Machine_Logger $logger = null ) {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dm_processed_items';
        $this->logger = $logger; // Store logger if provided
    }

    /**
     * Creates or updates the database table schema.
     * Should be called on plugin activation.
     */
    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Check if table exists before trying to create it
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $this->table_name ) ) != $this->table_name ) {

            $sql = "CREATE TABLE {$this->table_name} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                module_id BIGINT(20) UNSIGNED NOT NULL,
                source_type VARCHAR(50) NOT NULL,
                item_identifier VARCHAR(255) NOT NULL,
                processed_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY `module_source_item` (module_id, source_type, item_identifier(191)), -- Index for efficient checking, limit identifier length
                KEY `module_id` (module_id),
                KEY `source_type` (source_type)
            ) $charset_collate;";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );

            // Log table creation (optional)
    

        } else {
             // Optional: Check for schema updates needed if table already exists
 
             // You could add dbDelta() call here as well to handle updates if needed.
             // require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
             // dbDelta( $sql ); // dbDelta is safe to run even if table exists
        }
    }

    /**
     * Checks if a specific item has already been processed for a given module and source type.
     *
     * @param int    $module_id      The ID of the module.
     * @param string $source_type    The type of the data source (e.g., 'rss', 'reddit').
     * @param string $item_identifier The unique identifier for the item (e.g., GUID, post ID).
     * @return bool True if the item has been processed, false otherwise.
     */
    public function has_item_been_processed( int $module_id, string $source_type, string $item_identifier ): bool {
        global $wpdb;

        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE module_id = %d AND source_type = %s AND item_identifier = %s",
            $module_id,
            $source_type,
            $item_identifier
        ) );

        return $count > 0;
    }

 /**
  * Checks if any item has been processed for a given module ID.
  * Efficiently checks for at least one record.
  *
  * @param int $module_id The ID of the module.
  * @return bool True if any item has been processed, false otherwise.
  */
 public function has_any_processed_items_for_module(int $module_id): bool {
  global $wpdb;

  $count = $wpdb->get_var( $wpdb->prepare(
   "SELECT COUNT(*) FROM {$this->table_name} WHERE module_id = %d LIMIT 1",
   $module_id
  ) );

  return $count > 0;
 }

    /**
     * Adds a record indicating an item has been processed.
     *
     * @param int    $module_id      The ID of the module.
     * @param string $source_type    The type of the data source.
     * @param string $item_identifier The unique identifier for the item.
     * @return bool True on successful insertion, false otherwise.
     */
    public function add_processed_item( int $module_id, string $source_type, string $item_identifier ): bool {
        global $wpdb;

        // Optional: Check if it exists first to avoid unnecessary insert attempts if called redundantly
        // if ($this->has_item_been_processed($module_id, $source_type, $item_identifier)) {
        //     return true; // Or false, depending on desired behavior for duplicates
        // }

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'module_id'       => $module_id,
                'source_type'     => $source_type,
                'item_identifier' => $item_identifier,
                // processed_timestamp defaults to NOW()
            ),
            array(
                '%d', // module_id
                '%s', // source_type
                '%s', // item_identifier
            )
        );

        if ($result === false) {
             // Log error
             $db_error = $wpdb->last_error;
             // Use Logger Service if available
             if ($this->logger) {
                 $this->logger->error("Failed to insert processed item.", [
                     'module_id' => $module_id,
                     'source_type' => $source_type,
                     'item_identifier' => substr($item_identifier, 0, 100) . '...', // Avoid logging potentially huge identifiers
                     'db_error' => $db_error
                 ]);
             } else {
                 // Fallback if logger not injected
                  // Error logging removed for production
             }
             return false;
        }

        return true;
    }

} // End class 