<?php
/**
 * RemoteLocations database security operations component.
 *
 * Handles security, encryption, and validation operations for remote locations.
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

class RemoteLocationsSecurity {

    /**
     * Initialize the security component.
     */
    public function __construct() {
        // No initialization needed currently
    }

    /**
     * Get encryption helper service via filter
     *
     * @return object|null EncryptionHelper instance or null if not available
     */
    private function get_encryption_helper() {
        return apply_filters('dm_get_encryption_helper', null);
    }

    /**
     * Validate location data before insertion/update.
     *
     * @param array $data Location data to validate.
     * @return bool|string True if valid, error message string if invalid.
     */
    public function validate_location_data(array $data) {
        if (empty($data['location_name'])) {
            return 'Location name is required.';
        }

        if (empty($data['target_site_url'])) {
            return 'Target site URL is required.';
        }

        // Validate URL format
        if (!filter_var($data['target_site_url'], FILTER_VALIDATE_URL)) {
            return 'Target site URL must be a valid URL.';
        }

        if (empty($data['target_username'])) {
            return 'Target username is required.';
        }

        if (!isset($data['password'])) {
            return 'Password is required.';
        }

        return true;
    }

    /**
     * Encrypt a password for storage.
     *
     * @param string $password Plain text password.
     * @return string|false Encrypted password on success, false on failure.
     */
    public function encrypt_password(string $password) {
        $encryption_helper = $this->get_encryption_helper();
        if (!$encryption_helper) {
            return false;
        }
        return $encryption_helper->encrypt($password);
    }

    /**
     * Decrypt a password from storage.
     *
     * @param string $encrypted_password Encrypted password.
     * @return string|false Decrypted password on success, false on failure.
     */
    public function decrypt_password(string $encrypted_password) {
        $encryption_helper = $this->get_encryption_helper();
        if (!$encryption_helper) {
            return false;
        }
        return $encryption_helper->decrypt($encrypted_password);
    }

    /**
     * Sanitize location data for database storage.
     *
     * @param array $data Raw location data.
     * @return array Sanitized location data.
     */
    public function sanitize_location_data(array $data): array {
        $sanitized = array();

        if (isset($data['location_name'])) {
            $sanitized['location_name'] = sanitize_text_field($data['location_name']);
        }

        if (isset($data['target_site_url'])) {
            $sanitized['target_site_url'] = esc_url_raw(untrailingslashit($data['target_site_url']));
        }

        if (isset($data['target_username'])) {
            $sanitized['target_username'] = sanitize_text_field($data['target_username']);
        }

        if (isset($data['password'])) {
            // Password will be encrypted separately
            $sanitized['password'] = $data['password'];
        }

        if (isset($data['enabled_post_types'])) {
            $sanitized['enabled_post_types'] = $data['enabled_post_types']; // Already JSON encoded
        }

        if (isset($data['enabled_taxonomies'])) {
            $sanitized['enabled_taxonomies'] = $data['enabled_taxonomies']; // Already JSON encoded
        }

        return $sanitized;
    }

    /**
     * Check if a location name already exists (for uniqueness validation).
     *
     * @param string $location_name The location name to check.
     * @param int|null $exclude_id Optional location ID to exclude from check.
     * @return bool True if name exists, false otherwise.
     */
    public function location_name_exists(string $location_name, ?int $exclude_id = null): bool {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dm_remote_locations';

        if ($exclude_id) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE location_name = %s AND location_id != %d",
                $location_name,
                $exclude_id
            ));
        } else {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE location_name = %s",
                $location_name
            ));
        }

        return $count > 0;
    }
}