<?php
/**
 * RemoteLocations database sync operations component.
 *
 * Handles synchronization operations for remote locations.
 * Part of the modular RemoteLocations architecture following single responsibility principle.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/database/remote_locations
 * @since      0.16.0
 */

namespace DataMachine\Core\Database\RemoteLocations;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class RemoteLocationsSync {

    /**
     * The name of the remote locations database table.
     * @var string
     */
    private $table_name;

    /**
     * Initialize the sync component.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dm_remote_locations';
    }

    /**
     * Updates the synced site info and last sync time for a location.
     *
     * @param int $location_id The location ID.
     * @param string|null $site_info_json JSON string of the site info, or null to clear.
     * @return bool True on success, false on failure.
     */
    public function update_synced_info(int $location_id, ?string $site_info_json): bool {
        global $wpdb;

        $update_data = array(
            'synced_site_info' => $site_info_json, // Store JSON as string
            'last_sync_time' => $site_info_json !== null ? current_time('mysql') : null, // Update time only if info is set
            'updated_at' => current_time('mysql', 1)
        );
        $update_formats = array('%s', '%s', '%s');

        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array('location_id' => $location_id), // WHERE clause
            $update_formats,
            array('%d') // Format of WHERE clause
        );

        return $result !== false;
    }
}