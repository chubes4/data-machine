<?php
/**
 * RemoteLocations database coordinator class - maintains public API while delegating to focused components.
 *
 * Follows handler-style modular architecture where the main class coordinates
 * between focused internal components for single responsibility compliance.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/database/remote_locations
 * @since      0.16.0
 */

namespace DataMachine\Core\Database\RemoteLocations;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class RemoteLocations {

    /**
     * Internal components for focused responsibilities.
     */
    private $operations;
    private $sync;
    private $security;

    /**
     * Initialize the coordinator and internal components.
     * Uses direct instantiation - no caching complexity.
     */
    public function __construct() {
        // Initialize focused components directly
        $this->operations = new RemoteLocationsOperations();
        $this->sync = new RemoteLocationsSync();
        $this->security = new RemoteLocationsSecurity();
    }

    // ========================================
    // CRUD Operations (delegated to RemoteLocationsOperations)
    // ========================================

    /**
     * Adds a new remote location.
     */
    public function add_location(array $data) {
        return $this->operations->add_location($data);
    }

    /**
     * Updates an existing remote location.
     */
    public function update_location(int $location_id, array $data) {
        return $this->operations->update_location($location_id, $data);
    }

    /**
     * Deletes a remote location.
     */
    public function delete_location(int $location_id) {
        return $this->operations->delete_location($location_id);
    }

    /**
     * Retrieves a single remote location by ID.
     */
    public function get_location(int $location_id, bool $decrypt_password = false): ?object {
        return $this->operations->get_location($location_id, $decrypt_password);
    }

    /**
     * Retrieves all remote locations.
     */
    public function get_all_locations(): array {
        return $this->operations->get_all_locations();
    }

    /**
     * Retrieves all remote locations for current user.
     */
    public function get_locations_for_current_user(): array {
        return $this->operations->get_locations_for_current_user();
    }

    // ========================================
    // Sync Operations (delegated to RemoteLocationsSync)
    // ========================================

    /**
     * Updates the synced site info and last sync time for a location.
     */
    public function update_synced_info(int $location_id, ?string $site_info_json): bool {
        return $this->sync->update_synced_info($location_id, $site_info_json);
    }

    // ========================================
    // Static Methods (table creation)
    // ========================================

    /**
     * Creates or updates the database table.
     */
    public static function create_table() {
        $wpdb = apply_filters('dm_get_wpdb_service', null);
        $table_name = $wpdb->prefix . 'dm_remote_locations';
        
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            location_id bigint unsigned NOT NULL auto_increment,
            location_name varchar(255) NOT NULL,
            target_site_url varchar(255) NOT NULL,
            target_username varchar(100) NOT NULL,
            encrypted_password text NOT NULL,
            synced_site_info longtext NULL,
            enabled_post_types longtext NULL,
            enabled_taxonomies longtext NULL,
            last_sync_time datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (location_id),
            KEY idx_location_name (location_name(191))
        ) {$charset_collate};";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}