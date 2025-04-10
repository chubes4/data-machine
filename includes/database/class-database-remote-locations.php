<?php
/**
 * Handles database operations for the Remote Locations feature.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/database
 * @since      0.16.0 // Or current version
 */
class Data_Machine_Database_Remote_Locations {

    /**
     * Service Locator instance.
     * @var Data_Machine_Service_Locator|null
     */
    private $locator;

    /**
     * Encryption key (Should be defined securely, e.g., in wp-config.php).
     * Example: define('dm_ENCRYPTION_KEY', 'your-secure-random-32-byte-key');
     */
    private const ENCRYPTION_METHOD = 'aes-256-cbc';

    /**
     * Get the encryption key, preferring dm_ENCRYPTION_KEY, falling back to AUTH_KEY.
     *
     * @return string|false The encryption key (32 bytes) or false if unavailable.
     */
    private function _get_encryption_key() {
        $key = false;
        if (defined('dm_ENCRYPTION_KEY')) {
            $key = dm_ENCRYPTION_KEY;
        } elseif (defined('AUTH_KEY')) {
            $key = AUTH_KEY;
        }

        if (!$key) {
            error_log('ADC Encryption Error: Neither dm_ENCRYPTION_KEY nor AUTH_KEY is defined.');
            return false;
        }

        // Ensure key length is appropriate for aes-256-cbc (32 bytes)
        // Use sha256 hash and take the first 32 bytes.
        return substr(hash('sha256', $key, true), 0, 32);
    }

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
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            location_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            location_name VARCHAR(255) NOT NULL,
            target_site_url VARCHAR(255) NOT NULL,
            target_username VARCHAR(100) NOT NULL,
            encrypted_password TEXT NOT NULL,
            synced_site_info LONGTEXT NULL,
            last_sync_time DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            
            PRIMARY KEY (location_id),
            INDEX idx_user_id (user_id),
            INDEX idx_user_location_name (user_id, location_name)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
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

        $encrypted_password = $this->_encrypt_password($data['password']);
        if ($encrypted_password === false) {
            error_log('ADC Remote Location Error: Failed to encrypt password.');
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
            error_log('ADC Remote Location Error: Failed to insert location. WPDB Error: ' . $wpdb->last_error);
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
            $encrypted_password = $this->_encrypt_password($data['password']);
            if ($encrypted_password === false) {
                 error_log('ADC Remote Location Error: Failed to encrypt password during update.');
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
            $location->password = $this->_decrypt_password($location->encrypted_password);
            if ($location->password === false) {
                error_log("ADC Remote Location Error: Failed to decrypt password for location ID {$location_id}.");
                // Decide how to handle - return null, or return object without password?
                // Returning object without password might be safer.
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

    /**
     * Encrypts a password.
     * IMPORTANT: Requires a securely defined dm_ENCRYPTION_KEY constant.
     *
     * @param string $password Plaintext password.
     * @return string|false Encrypted string (base64 encoded) or false on failure.
     */
    private function _encrypt_password(string $password) {
        $key = $this->_get_encryption_key();
        if ($key === false || !function_exists('openssl_encrypt')) {
             if ($key === false) {
                // Error already logged in _get_encryption_key()
             } else {
                 error_log('ADC Encryption Error: OpenSSL extension is not available.');
             }
             return false; // Cannot encrypt
        }

        $iv_length = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
        if ($iv_length === false) {
             error_log('ADC Encryption Error: Could not get IV length for ' . self::ENCRYPTION_METHOD);
             return false;
        }
        $iv = openssl_random_pseudo_bytes($iv_length);
        $encrypted = openssl_encrypt($password, self::ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            error_log('ADC Encryption Error: openssl_encrypt failed.');
            return false;
        }

        // Prepend IV to the encrypted string for use during decryption
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypts a password.
     * IMPORTANT: Requires a securely defined dm_ENCRYPTION_KEY constant.
     *
     * @param string $encrypted_password Base64 encoded encrypted string (with IV prepended).
     * @return string|false Plaintext password or false on failure.
     */
    private function _decrypt_password(string $encrypted_password) {
        $key = $this->_get_encryption_key();
        if ($key === false || !function_exists('openssl_decrypt')) {
            if ($key === false) {
               // Error already logged in _get_encryption_key()
            } else {
                error_log('ADC Decryption Error: OpenSSL extension is not available.');
            }
            return false; // Cannot decrypt
        }

        $data = base64_decode($encrypted_password);
        if ($data === false) {
            error_log('ADC Decryption Error: base64_decode failed.');
            return false;
        }

        $iv_length = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
         if ($iv_length === false) {
             error_log('ADC Decryption Error: Could not get IV length for ' . self::ENCRYPTION_METHOD);
             return false;
        }
        if (strlen($data) < $iv_length) {
             error_log('ADC Decryption Error: Encrypted data is too short to contain IV.');
             return false;
        }

        $iv = substr($data, 0, $iv_length);
        $encrypted_text = substr($data, $iv_length);

        $decrypted = openssl_decrypt($encrypted_text, self::ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            error_log('ADC Decryption Error: openssl_decrypt failed. Check key or data integrity.');
            return false;
        }

        return $decrypted;
    }

} // End class