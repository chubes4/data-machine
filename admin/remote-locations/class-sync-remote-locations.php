<?php
/**
 * Service to handle syncing data from remote locations.
 *
 * Centralizes the logic for syncing post types, taxonomies, and terms
 * from remote WordPress sites via their REST API.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/module-config/remote-locations
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Data_Machine_Sync_Remote_Locations {

    /** @var Data_Machine_Database_Remote_Locations */
    private $db_locations;

    /** @var ?Data_Machine_Logger */
    private $logger;

    /**
     * Initialize with dependencies.
     *
     * @param Data_Machine_Database_Remote_Locations $db_locations Database handler for remote locations.
     * @param Data_Machine_Logger|null $logger Logger service (optional).
     */
    public function __construct(
        Data_Machine_Database_Remote_Locations $db_locations,
        ?Data_Machine_Logger $logger = null
    ) {
        $this->db_locations = $db_locations;
        $this->logger = $logger;
    }

    /**
     * Sync site data from a remote location.
     *
     * @param int $location_id The ID of the remote location to sync.
     * @param int $user_id The user ID performing the sync.
     * @return array Result array: ['success' => bool, 'message' => string, 'data' => array|null]
     */
    public function sync_location_data(int $location_id, int $user_id): array {
        // Get location with decrypted password
        $location = $this->db_locations->get_location($location_id, $user_id, true);

        if (!$location || empty($location->target_site_url) || empty($location->target_username) || !isset($location->password)) {
            return [
                'success' => false,
                'message' => __('Location not found or missing credentials.', 'data-machine'),
                'data' => null
            ];
        }

        if ($location->password === false) {
            return [
                'success' => false,
                'message' => __('Failed to decrypt password for location.', 'data-machine'),
                'data' => null
            ];
        }

        // Make API request to remote site
        $api_url = rtrim($location->target_site_url, '/') . '/wp-json/dma/v1/site-info';
        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($location->target_username . ':' . $location->password)
            ),
            'timeout' => 30,
        );

        $response = wp_remote_get($api_url, $args);

        if (is_wp_error($response)) {
            $error_message = sprintf(
                __('Failed to connect to remote site: %s', 'data-machine'),
                $response->get_error_message()
            );
            $this->logger?->add_admin_error($error_message, ['location_id' => $location_id]);
            return [
                'success' => false,
                'message' => $error_message,
                'data' => null
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['message']) ? $error_data['message'] : __('Unknown error occurred on the remote site.', 'data-machine');
            $full_error = sprintf(
                __('Remote site returned an error (Code: %d): %s', 'data-machine'),
                $response_code,
                $error_message
            );
            $this->logger?->add_admin_error($full_error, ['location_id' => $location_id, 'response_code' => $response_code]);
            return [
                'success' => false,
                'message' => $full_error,
                'data' => null
            ];
        }

        $decoded_data = json_decode($body, true);
        
        // Enhanced debugging for sync issues
        $this->logger?->debug('Sync API Response', [
            'location_id' => $location_id,
            'response_code' => $response_code,
            'raw_body' => substr($body, 0, 500), // First 500 chars
            'decoded_data_keys' => $decoded_data ? array_keys($decoded_data) : null,
            'has_post_types' => isset($decoded_data['post_types']),
            'has_taxonomies' => isset($decoded_data['taxonomies'])
        ]);
        
        if (empty($decoded_data) || !isset($decoded_data['post_types']) || !isset($decoded_data['taxonomies'])) {
            $error_message = __('Received invalid data format from the remote site.', 'data-machine');
            $detailed_error = sprintf(
                'Expected post_types and taxonomies keys. Received: %s',
                $decoded_data ? implode(', ', array_keys($decoded_data)) : 'null/empty response'
            );
            $this->logger?->add_admin_error($error_message . ' ' . $detailed_error, ['location_id' => $location_id, 'raw_response' => $body]);
            return [
                'success' => false,
                'message' => $error_message . ' Check logs for details.',
                'data' => null
            ];
        }

        // Process the data
        $filtered_data = $this->process_sync_data($decoded_data, $location);

        $site_info_json = wp_json_encode($filtered_data);
        if ($site_info_json === false) {
            $error_message = __('Failed to process data received from the remote site (JSON encoding failed).', 'data-machine');
            $this->logger?->add_admin_error($error_message, ['location_id' => $location_id]);
            return [
                'success' => false,
                'message' => $error_message,
                'data' => null
            ];
        }

        // Update the database
        $updated = $this->db_locations->update_synced_info($location_id, $user_id, $site_info_json);

        if ($updated) {
            $success_message = __('Remote site data synced successfully!', 'data-machine');
            $this->logger?->add_admin_success($success_message, ['location_id' => $location_id]);
            return [
                'success' => true,
                'message' => $success_message,
                'data' => $filtered_data
            ];
        } else {
            $error_message = __('Failed to save synced data to the database.', 'data-machine');
            $this->logger?->add_admin_error($error_message, ['location_id' => $location_id]);
            return [
                'success' => false,
                'message' => $error_message,
                'data' => null
            ];
        }
    }

    /**
     * Process and filter the synced data based on enabled taxonomies.
     *
     * @param array $decoded_data Raw data from remote API.
     * @param object $location Location object with enabled settings.
     * @return array Filtered data ready for storage.
     */
    private function process_sync_data(array $decoded_data, object $location): array {
        // Get enabled taxonomies for this location (as slugs)
        $enabled_taxonomies = [];
        if (!empty($location->enabled_taxonomies)) {
            $enabled_taxonomies = json_decode($location->enabled_taxonomies, true);
            if (!is_array($enabled_taxonomies)) {
                $enabled_taxonomies = [];
            }
        }

        // Debug: Log what we received from the API
        $this->logger?->debug('Processing sync data', [
            'location_id' => $location->location_id ?? 'unknown',
            'post_types_count' => count($decoded_data['post_types'] ?? []),
            'post_types_keys' => array_keys($decoded_data['post_types'] ?? []),
            'taxonomies_count' => count($decoded_data['taxonomies'] ?? []),
            'enabled_taxonomies' => $enabled_taxonomies
        ]);

        // Always keep all post types and taxonomies (names/labels)
        // For taxonomies, only keep terms for enabled taxonomies (or all if none enabled)
        $filtered_taxonomies = [];
        foreach ($decoded_data['taxonomies'] as $slug => $tax_data) {
            $filtered = [
                'label' => $tax_data['label'] ?? $slug,
                'post_types' => $tax_data['post_types'] ?? [],
            ];
            // Only include terms if this taxonomy is enabled, or if no taxonomies are enabled (first sync)
            if (empty($enabled_taxonomies) || in_array($slug, $enabled_taxonomies, true)) {
                if (isset($tax_data['terms'])) {
                    $filtered['terms'] = $tax_data['terms'];
                }
            }
            $filtered_taxonomies[$slug] = $filtered;
        }

        return [
            'post_types' => $decoded_data['post_types'],
            'taxonomies' => $filtered_taxonomies,
        ];
    }
}