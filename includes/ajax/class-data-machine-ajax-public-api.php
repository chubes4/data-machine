<?php
/**
 * Handles AJAX requests related to the Public REST API input handler.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/ajax
 * @since      NEXT_VERSION
 */
class Data_Machine_Ajax_Public_API {

    /**
     * Service Locator instance.
     * @var Data_Machine_Service_Locator
     */
    private $locator;

    /**
     * Constructor.
     *
     * @param Data_Machine_Service_Locator $locator Service Locator instance.
     */
    public function __construct(Data_Machine_Service_Locator $locator) {
        $this->locator = $locator;
    }

    /**
     * Initialize hooks.
     */
    public function init_hooks() {
        // Register AJAX handlers
        add_action('wp_ajax_dm_sync_public_api', array($this, 'handle_sync_public_api_ajax'));
    }

    /**
     * Handles the AJAX request to sync site info from a public REST API endpoint.
     */
    public function handle_sync_public_api_ajax() {
        // Verify nonce (use the one passed from JS)
        check_ajax_referer('dm_sync_public_api_nonce', 'nonce');

        $api_base_url_input = isset($_POST['endpoint_url']) ? esc_url_raw(untrailingslashit($_POST['endpoint_url'])) : '';
        
        $logger = $this->locator->get('logger');
        if (empty($api_base_url_input)) {
        	$logger && $logger->add_admin_error(__('Missing API Endpoint URL.', 'data-machine'));
        	wp_send_json_error(['message' => __('Missing API Endpoint URL.', 'data-machine')]);
        	return;
        }
        
        // Ensure the base URL includes /wp-json/
        if (strpos($api_base_url_input, '/wp-json') === false) {
        	$api_base_url = trailingslashit($api_base_url_input) . 'wp-json';
        } else {
        	// If it already contains /wp-json, use it as is (after untrailingslashit)
        	$api_base_url = $api_base_url_input;
        }
        // Ensure no double slashes if /wp-json was already present with a trailing slash
        $api_base_url = untrailingslashit($api_base_url);

        // --- Discover Endpoints ---
        $discovered_endpoints = [];
        $standard_post_type_names = [];
      
        // 1. Fetch /wp-json/ index
        $index_url = $api_base_url . '/';
        $index_args = [
        	'timeout' => 20,
        	'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' )
        ];
        $index_response = wp_remote_get($index_url, $index_args);
      
        if (is_wp_error($index_response) || wp_remote_retrieve_response_code($index_response) !== 200) {
        	$error_message = __('Failed to fetch REST API index. Cannot discover endpoints.', 'data-machine');
        	$error_context = ['url' => $index_url, 'error' => is_wp_error($index_response) ? $index_response->get_error_message() : wp_remote_retrieve_response_code($index_response)];
        	$logger && $logger->add_admin_error($error_message, $error_context);
        	wp_send_json_error(['message' => $error_message]);
        	return;
        }
      
        $index_data = json_decode(wp_remote_retrieve_body($index_response), true);
      
        // 2. Try fetching standard post type names from /wp/v2/types
        $types_url = $api_base_url . '/wp/v2/types?context=edit';
        $types_args = [
        	'timeout' => 15,
        	'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' )
        ];
        $types_response = wp_remote_get($types_url, $types_args);
        if (!is_wp_error($types_response) && wp_remote_retrieve_response_code($types_response) === 200) {
        	$types_data = json_decode(wp_remote_retrieve_body($types_response), true);
        	if (is_array($types_data)) {
        		foreach ($types_data as $slug => $details) {
        			if (isset($details['viewable']) && $details['viewable'] && isset($details['name']) && isset($details['rest_base'])) {
        				// Store mapping of rest_base to friendly name
        				$standard_post_type_names[$details['rest_base']] = $details['name'];
        			}
        		}
        	}
        } else {
        	$logger && $logger->add_admin_warning(__('Could not fetch standard post type names from /wp/v2/types. Endpoint labels might be less descriptive.', 'data-machine'), ['url' => $types_url]);
        }
      
        // 3. Parse routes from index to find all GET endpoints
        if (isset($index_data['routes']) && is_array($index_data['routes'])) {
        	foreach ($index_data['routes'] as $route => $route_data) {
        		if (isset($route_data['endpoints']) && is_array($route_data['endpoints'])) {
        			foreach ($route_data['endpoints'] as $endpoint) {
        				if (isset($endpoint['methods']) && in_array('GET', $endpoint['methods'])) {
        					$label = '';
        					$namespace = $route_data['namespace'] ?? 'unknown';
      
        					// Create a more informative label based on namespace and route
        					if (strpos($route, '/wp/v2/') === 0 && preg_match('/^\/wp\/v2\/([a-zA-Z0-9_-]+)$/', $route, $matches)) {
        						// Standard WP post type or taxonomy collection endpoint
        						$rest_base = $matches[1];
        						$friendly_name = $standard_post_type_names[$rest_base] ?? ucfirst(str_replace('_', ' ', $rest_base));
        						$label = sprintf('Standard: %s (%s)', $friendly_name, $route);
        					} elseif (strpos($route, '/wp/v') === 0) {
        						$label = sprintf('WP Core: %s', $route);
        					} elseif (strpos($route, '/jb-api/') === 0 || strpos($route, '/artist/') === 0 || strpos($route, '/tunein/') === 0 || strpos($route, '/cot/') === 0) {
        						$label = sprintf('Custom (%s): %s', $namespace, $route);
        					} else {
        						// Generic label for other namespaces
        						$label = sprintf('Other (%s): %s', $namespace, $route);
        					}
        					
        					// Add the endpoint to the list if it has a label
        					if (!empty($label)) {
        						$discovered_endpoints[$route] = $label;
        					}
        					
        					// Only add one entry per route even if multiple GET endpoints exist (e.g., with/without ID param)
        					break;
        				}
        			}
        		}
        	}
        }
        
        // Sort endpoints alphabetically by label for better UI presentation
        asort($discovered_endpoints);
      
        // --- Prepare Response ---
        $site_info = [];
        if (!empty($discovered_endpoints)) {
        	$site_info['available_endpoints'] = $discovered_endpoints; // Key changed
        }

        // Check if any endpoints were discovered
        if (empty($discovered_endpoints)) {
        	$error_message = __('Could not discover any usable GET endpoints from the REST API index. Please check the URL and ensure the REST API is accessible.', 'data-machine');
        	$logger && $logger->add_admin_error($error_message, ['url' => $index_url, 'index_data' => $index_data]); // Log the index data for debugging
        	wp_send_json_error(['message' => $error_message]);
        } else {
        	// Return the discovered endpoints (and potentially taxonomy info if needed later)
        	$site_info['available_endpoints'] = $discovered_endpoints;
        	// Taxonomy data is no longer fetched in this simplified discovery process.
      
        	wp_send_json_success([
        		'message' => __('API Endpoints discovered successfully!', 'data-machine'),
        		'site_info' => $site_info // Keep 'site_info' key for consistency with JS
        	]);
        }
    } // End handle_sync_public_api_ajax

} // End class Data_Machine_Ajax_Public_API