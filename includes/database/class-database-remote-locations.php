<?php
/**
 * Handles database operations for the Remote Locations feature.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/database
 * @since      0.16.0 // Or current version
 */

// Ensure the helper class is available
require_once dirname(__FILE__) . '/../helpers/class-data-machine-encryption-helper.php';

class Data_Machine_Database_Remote_Locations {

    /**
     * Service Locator instance.
     * @var Data_Machine_Service_Locator|null
     */
    private $locator;

    /**
     * Constructor.
     *
     * @param Data_Machine_Service_Locator|null $locator Service Locator instance.
     */
    public function __construct(Data_Machine_Service_Locator $locator = null) {
        $this->locator = $locator;
    }

    /**
     * Creates or updates the database table.
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dm_remote_locations';
        
        // Check if the table already exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
        
        // Only proceed if the table doesn't exist
        if (!$table_exists) {
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE {$table_name} (
                location_id bigint unsigned NOT NULL auto_increment,
                user_id bigint unsigned NOT NULL,
                location_name varchar(255) NOT NULL,
                target_site_url varchar(255) NOT NULL,
                target_username varchar(100) NOT NULL,
                encrypted_password text NOT NULL,
                synced_site_info longtext NULL,
                last_sync_time datetime NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                updated_at datetime NOT NULL,
                
                PRIMARY KEY  (location_id),
                KEY idx_user_id (user_id),
                KEY idx_user_location_name (user_id,location_name(191))
            )
            {$charset_collate}";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        }
    }

    /**
     * Adds a new remote location.
     *
     * @param int $user_id The ID of the user adding the location.
     * @param array $data Location data: ['location_name', 'target_site_url', 'target_username', 'password'].
     * @return int|false The new location ID on success, false on failure.
     */
    public function add_location(int $user_id, array $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dm_remote_locations';

        if (empty($user_id) || empty($data['location_name']) || empty($data['target_site_url']) || empty($data['target_username']) || !isset($data['password'])) {
            return false; // Basic validation
        }

        // Use the Encryption Helper
        $encrypted_password = Data_Machine_Encryption_Helper::encrypt($data['password']);
        if ($encrypted_password === false) {
            error_log('DM Remote Location Error: Failed to encrypt password using helper.');
            return false;
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'location_name' => sanitize_text_field($data['location_name']),
                'target_site_url' => esc_url_raw(untrailingslashit($data['target_site_url'])),
                'target_username' => sanitize_text_field($data['target_username']),
                'encrypted_password' => $encrypted_password,
                'created_at' => current_time('mysql', 1),
                'updated_at' => current_time('mysql', 1),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s') // Data formats
        );

        if ($result === false) {
            error_log('DM Remote Location Error: Failed to insert location. WPDB Error: ' . $wpdb->last_error);
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Updates an existing remote location.
     *
     * @param int $location_id The ID of the location to update.
     * @param int $user_id The ID of the user performing the update (for ownership check).
     * @param array $data Data to update. Can include 'location_name', 'target_site_url', 'target_username', 'password'.
     * @return bool True on success, false on failure or if permission denied.
     */
    public function update_location(int $location_id, int $user_id, array $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dm_remote_locations';

        // Verify ownership first
        $owner_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $table_name WHERE location_id = %d", $location_id));
        if (!$owner_id || (int)$owner_id !== $user_id) {
            return false; // Permission denied or location not found
        }

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
            $encrypted_password = Data_Machine_Encryption_Helper::encrypt($data['password']);
            if ($encrypted_password === false) {
                 error_log('DM Remote Location Error: Failed to encrypt password during update using helper.');
                 return false;
            }
            $update_data['encrypted_password'] = $encrypted_password;
            $update_formats[] = '%s';
        }

        if (empty($update_data)) {
            return false; // Nothing to update
        }

        // Add updated_at timestamp
        $update_data['updated_at'] = current_time('mysql', 1);
        $update_formats[] = '%s';

        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('location_id' => $location_id, 'user_id' => $user_id), // WHERE clause
            $update_formats, // Format of data being updated
            array('%d', '%d') // Format of WHERE clause
        );

        // $wpdb->update returns number of rows affected or false on error.
        return $result !== false;
    }

    /**
     * Deletes a remote location.
     *
     * @param int $location_id The ID of the location to delete.
     * @param int $user_id The ID of the user performing the deletion (for ownership check).
     * @return bool True on success, false on failure or if permission denied.
     */
    public function delete_location(int $location_id, int $user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dm_remote_locations';

        // Optional: Verify ownership before deleting (good practice)
        $owner_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $table_name WHERE location_id = %d", $location_id));
        if (!$owner_id || (int)$owner_id !== $user_id) {
            return false; // Permission denied or location not found
        }

        $result = $wpdb->delete(
            $table_name,
            array('location_id' => $location_id, 'user_id' => $user_id), // WHERE clause
            array('%d', '%d') // Format of WHERE clause
        );

        // $wpdb->delete returns number of rows affected or false on error.
        return $result !== false;
    }

    /**
     * Retrieves a single remote location by ID.
     *
     * @param int $location_id The location ID.
     * @param int $user_id The user ID for ownership check.
     * @param bool $decrypt_password Whether to decrypt the password.
     * @return object|null Location object on success, null if not found or permission denied.
     */
    public function get_location(int $location_id, int $user_id, bool $decrypt_password = false): ?object {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dm_remote_locations';

        $location = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE location_id = %d AND user_id = %d",
            $location_id,
            $user_id
        ));

        if (!$location) {
            return null;
        }

        if ($decrypt_password && isset($location->encrypted_password)) {
            // Use the Encryption Helper
            $location->password = Data_Machine_Encryption_Helper::decrypt($location->encrypted_password);
            if ($location->password === false) {
                error_log("DM Remote Location Error: Failed to decrypt password using helper for location ID {$location_id}.");
                // Optionally handle decryption failure, e.g., set password to null or an error indicator
                unset($location->password);
            }
        }

        // Unset encrypted version if decrypted or not requested
        unset($location->encrypted_password);

        return $location;
    }

    /**
     * Retrieves all remote locations for a specific user.
     * Passwords are NOT included.
     *
     * @param int $user_id The user ID.
     * @return array Array of location objects.
     */
    public function get_locations_for_user(int $user_id): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dm_remote_locations';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT location_id, user_id, location_name, target_site_url, target_username, last_sync_time, created_at, updated_at
             FROM $table_name WHERE user_id = %d ORDER BY location_name ASC",
            $user_id
        ));

        return $results ?: [];
    }

    /**
     * Updates the synced site info and last sync time for a location.
     *
     * @param int $location_id The location ID.
     * @param int $user_id The user ID for ownership check.
     * @param string|null $site_info_json JSON string of the site info, or null to clear.
     * @return bool True on success, false on failure.
     */
    public function update_synced_info(int $location_id, int $user_id, ?string $site_info_json): bool {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dm_remote_locations';

        // Verify ownership first
        $owner_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $table_name WHERE location_id = %d", $location_id));
        if (!$owner_id || (int)$owner_id !== $user_id) {
            return false; // Permission denied or location not found
        }

        $update_data = array(
            'synced_site_info' => $site_info_json, // Store JSON as string
            'last_sync_time' => $site_info_json !== null ? current_time('mysql', 1) : null, // Update time only if info is set
            'updated_at' => current_time('mysql', 1)
        );
        $update_formats = array('%s', '%s', '%s');

        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('location_id' => $location_id, 'user_id' => $user_id), // WHERE clause
            $update_formats,
            array('%d', '%d') // Format of WHERE clause
        );

        return $result !== false;
    }

} // End class