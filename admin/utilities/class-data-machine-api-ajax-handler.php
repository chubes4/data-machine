<?php
/**
 * Handles API-related AJAX operations.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/utilities
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Data_Machine_API_AJAX_Handler {

    /**
     * Service Locator instance.
     * @var Data_Machine_Service_Locator
     */
    private $locator;

    /**
     * Initialize the class and set its properties.
     *
     * @param Data_Machine_Service_Locator $locator Service Locator instance.
     */
    public function __construct(Data_Machine_Service_Locator $locator) {
        $this->locator = $locator;
    }

    /**
     * Initialize hooks for API AJAX handlers.
     */
    public function init_hooks() {
        // Register AJAX handlers
        add_action('wp_ajax_dm_sync_public_api_info', array($this, 'sync_public_api_info_ajax_handler'));
    }

    /**
     * AJAX handler to fetch site info from a public WP REST API endpoint.
     *
     * @since 0.13.0
     */
    public function sync_public_api_info_ajax_handler() {
        // TODO: Add nonce check specific to this action
        // check_ajax_referer('dm_sync_public_api_nonce', 'nonce');

        $api_base_url = isset($_POST['endpoint_url']) ? esc_url_raw(untrailingslashit($_POST['endpoint_url'])) : '';

        if (empty($api_base_url)) {
            wp_send_json_error(['message' => __('Missing API Endpoint URL.', 'data-machine')]);
            return;
        }

        // --- Fetch Post Types ---
        $types_url = $api_base_url . '/types?context=edit'; // Use context=edit if possible for more types
        $types_response = wp_remote_get($types_url, ['timeout' => 15]);
        $post_types = [];
        if (!is_wp_error($types_response) && wp_remote_retrieve_response_code($types_response) === 200) {
            $types_data = json_decode(wp_remote_retrieve_body($types_response), true);
            if (is_array($types_data)) {
                foreach ($types_data as $slug => $details) {
                    // Filter out non-public or irrelevant types if needed
                    if (isset($details['viewable']) && $details['viewable'] && isset($details['name']) && isset($details['rest_base'])) {
                        $post_types[$details['rest_base']] = $details['name']; // Use rest_base as key, name as label
                    }
                }
            }
        } else {
            // Log error but continue, maybe it's not a WP site or types endpoint is disabled
            error_log('Data Machine: Failed to fetch post types from public API: ' . $types_url); // Use variable
        }

        // --- Fetch Taxonomies (Categories & Tags specifically for now) ---
        $taxonomies_data = [
            'category' => ['name' => 'Categories', 'terms' => []],
            'post_tag' => ['name' => 'Tags', 'terms' => []],
        ];

        foreach (['categories', 'tags'] as $tax_slug) {
            $terms_url = $api_base_url . "/{$tax_slug}?per_page=100&orderby=name&order=asc&context=view"; // Fetch up to 100 terms
            $terms_response = wp_remote_get($terms_url, ['timeout' => 20]);

            if (!is_wp_error($terms_response) && wp_remote_retrieve_response_code($terms_response) === 200) {
                $terms = json_decode(wp_remote_retrieve_body($terms_response), true);
                if (is_array($terms)) {
                    $key = ($tax_slug === 'categories') ? 'category' : 'post_tag';
                    foreach ($terms as $term) {
                        if (isset($term['id']) && isset($term['name'])) {
                            $taxonomies_data[$key]['terms'][] = [
                                'id' => $term['id'],
                                'name' => $term['name'],
                            ];
                        }
                    }
                }
            } else {
                // Log error but continue
                error_log("Data Machine: Failed to fetch {$tax_slug} from public API: " . $terms_url);
            }
        }

        // --- Prepare Response ---
        // Only include data if found
        $site_info = [];
        if (!empty($post_types)) {
            $site_info['post_types'] = $post_types;
        }
        if (!empty($taxonomies_data['category']['terms']) || !empty($taxonomies_data['post_tag']['terms'])) {
            $site_info['taxonomies'] = $taxonomies_data;
        }

        if (empty($site_info)) {
            wp_send_json_error(['message' => __('Could not retrieve standard WP REST API info from the endpoint.', 'data-machine')]);
        } else {
            wp_send_json_success([
                'message' => __('Public API info retrieved successfully!', 'data-machine'),
                'public_site_info' => $site_info // Use a different key to avoid conflicts?
            ]);
        }
    } // End sync_public_api_info_ajax_handler
} 