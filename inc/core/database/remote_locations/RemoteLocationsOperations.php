<?php
/**
 * RemoteLocations database CRUD operations component.
 *
 * Handles basic database operations for remote locations: create, read, update, delete.
 * Part of the modular RemoteLocations architecture following single responsibility principle.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/database/remote_locations
 * @since      0.16.0
 */

namespace DataMachine\Core\Database\RemoteLocations;

use DataMachine\Admin\EncryptionHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class RemoteLocationsOperations {

    /**
     * The name of the remote locations database table.
     * @var string
     */
    private $table_name;

    /**
     * Initialize the operations component.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dm_remote_locations';
    }

    /**
     * Adds a new remote location.
     *
     * @param array $data Location data: ['location_name', 'target_site_url', 'target_username', 'password'].
     * @return int|false The new location ID on success, false on failure.
     */
    public function add_location(array $data) {
        global $wpdb;

        if (empty($data['location_name']) || empty($data['target_site_url']) || empty($data['target_username']) || !isset($data['password'])) {
            return false; // Basic validation
        }

        // Use the Encryption Helper
        $encrypted_password = EncryptionHelper::encrypt($data['password']);
        if ($encrypted_password === false) {
            return false;
        }

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'location_name' => sanitize_text_field($data['location_name']),
                'target_site_url' => esc_url_raw(untrailingslashit($data['target_site_url'])),
                'target_username' => sanitize_text_field($data['target_username']),
                'encrypted_password' => $encrypted_password,
                'created_at' => current_time('mysql', 1),
                'updated_at' => current_time('mysql', 1),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s') // Data formats
        );

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Updates an existing remote location.
     *
     * @param int $location_id The ID of the location to update.
     * @param array $data Data to update. Can include 'location_name', 'target_site_url', 'target_username', 'password'.
     * @return bool True on success, false on failure.
     */
    public function update_location(int $location_id, array $data) {
        global $wpdb;

        $update_data = array();
        $update_formats = array();

        if (!empty($data['location_name'])) {
            $update_data['location_name'] = sanitize_text_field($data['location_name']);
            $update_formats[] = '%s';
        }
        if (!empty($data['target_site_url'])) {
            $update_data['target_site_url'] = esc_url_raw(untrailingslashit($data['target_site_url']));
            $update_formats[] = '%s';
        }
        if (!empty($data['target_username'])) {
            $update_data['target_username'] = sanitize_text_field($data['target_username']);
            $update_formats[] = '%s';
        }
        if (isset($data['password'])) { // Allow updating with an empty password if intended
            // Use the Encryption Helper
            $encrypted_password = EncryptionHelper::encrypt($data['password']);
            if ($encrypted_password === false) {
                 return false;
            }
            $update_data['encrypted_password'] = $encrypted_password;
            $update_formats[] = '%s';
        }
        // Add enabled post types if present in $data
        if (isset($data['enabled_post_types'])) {
            // Assume it's already JSON encoded by the handler
            $update_data['enabled_post_types'] = $data['enabled_post_types'];
            $update_formats[] = '%s';
        }
        // Add enabled taxonomies if present in $data
        if (isset($data['enabled_taxonomies'])) {
             // Assume it's already JSON encoded by the handler
            $update_data['enabled_taxonomies'] = $data['enabled_taxonomies'];
            $update_formats[] = '%s';
        }

        if (empty($update_data)) {
            return false; // Nothing to update
        }

        // Add updated_at timestamp
        $update_data['updated_at'] = current_time('mysql', 1);
        $update_formats[] = '%s';

        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array('location_id' => $location_id), // WHERE clause
            $update_formats, // Format of data being updated
            array('%d') // Format of WHERE clause
        );

        // $wpdb->update returns number of rows affected or false on error.
        return $result !== false;
    }

    /**
     * Deletes a remote location.
     *
     * @param int $location_id The ID of the location to delete.
     * @return bool True on success, false on failure.
     */
    public function delete_location(int $location_id) {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table_name,
            array('location_id' => $location_id), // WHERE clause
            array('%d') // Format of WHERE clause
        );

        // $wpdb->delete returns number of rows affected or false on error.
        return $result !== false;
    }

    /**
     * Retrieves a single remote location by ID.
     *
     * @param int $location_id The location ID.
     * @param bool $decrypt_password Whether to decrypt the password.
     * @return object|null Location object on success, null if not found.
     */
    public function get_location(int $location_id, bool $decrypt_password = false): ?object {
        global $wpdb;

        $location = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE location_id = %d",
            $location_id
        ));

        if (!$location) {
            return null;
        }

        if ($decrypt_password && isset($location->encrypted_password)) {
            // Use the Encryption Helper
            $location->password = EncryptionHelper::decrypt($location->encrypted_password);
            if ($location->password === false) {
                // Optionally handle decryption failure, e.g., set password to null or an error indicator
                unset($location->password);
            }
        }

        // Unset encrypted version if decrypted or not requested
        unset($location->encrypted_password);

        return $location;
    }

    /**
     * Retrieves all remote locations.
     * Passwords are NOT included.
     *
     * @return array Array of location objects.
     */
    public function get_all_locations(): array {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT location_id, location_name, target_site_url, target_username, last_sync_time, created_at, updated_at
             FROM {$this->table_name} ORDER BY location_name ASC"
        );

        // Check if results is null (indicates DB error) or empty
        if (is_null($results)) {
            // Log the WPDB error
            return []; // Return empty on error
        }
        if (empty($results)) {
             return []; // Return empty if no locations found
        }

        // Return the results directly (array of objects by default)
        return $results;
    }

    /**
     * Retrieves all remote locations.
     * Convenience method for admin interface.
     *
     * @return array Array of location objects.
     */
    public function get_locations_for_current_user(): array {
        return $this->get_all_locations();
    }
}