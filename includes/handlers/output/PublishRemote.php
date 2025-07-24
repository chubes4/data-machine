<?php
/**
 * Handles the 'Publish Remote' output type.
 *
 * Sends processed data to a remote WordPress site via a custom endpoint
 * provided by the Data Machine Airdrop helper plugin.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/output
 * @since      0.7.0
 */

namespace DataMachine\Handlers\Output;

use DataMachine\Engine\Filters\{AiResponseParser, MarkdownConverter};
use DataMachine\Database\RemoteLocations;
use DataMachine\Helpers\{HttpService, Logger};

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class PublishRemote extends BaseOutputHandler {

    /**
     * Database handler for remote locations.
     * @var RemoteLocations
     */
    private $db_locations;


    /** @var HttpService */
    private $http_service;

    /**
     * Constructor.
     * @param RemoteLocations $db_locations
     * @param HttpService $http_service
     * @param Logger $logger
     */
    public function __construct(
        RemoteLocations $db_locations,
        HttpService $http_service,
        Logger $logger,
    ) {
        parent::__construct($logger);
        $this->db_locations = $db_locations;
        $this->http_service = $http_service;
    }
	/**
	 * Handles publishing the AI output to a remote WordPress site via REST API.
	 *
	 * @param string $ai_output_string The finalized string from the AI.
	 * @param array $module_job_config Configuration specific to this output job (credentials, post_type, etc.).
	 * @param int|null $user_id The ID of the user context (not directly used for remote publish author).
	 * @param array $input_metadata Metadata from the original input source (e.g., original URL, timestamp).
	 * @return array|WP_Error Result array on success, WP_Error on failure.
	 */
	public function handle( string $ai_output_string, array $module_job_config, ?int $user_id, array $input_metadata ): array|WP_Error {
		// Initialize variables to avoid undefined variable warnings
		$assigned_category_name = null;
		$assigned_category_id = null;
		$assigned_tag_ids = [];
		$assigned_tag_names = [];
		$assigned_custom_taxonomies = []; // Store assigned custom terms for reporting
		// Get output config directly from the job config array
		$output_config = $module_job_config['output_config'] ?? [];
		if ( ! is_array( $output_config ) ) $output_config = array(); // Ensure it's an array

		// Access settings within the 'publish_remote' sub-array (matching saved config key)
		$config = $output_config['publish_remote'] ?? [];
		if ( ! is_array( $config ) ) $config = array(); // Ensure publish_remote sub-array exists

		// --- Map selected_custom_taxonomy_values to rest_{tax_slug} keys for handler compatibility ---
		if (isset($config['selected_custom_taxonomy_values']) && is_array($config['selected_custom_taxonomy_values'])) {
			foreach ($config['selected_custom_taxonomy_values'] as $tax_slug => $value) {
				$config['rest_' . $tax_slug] = $value;
			}
		}
		// --- End mapping ---

		// Get the selected remote location ID
		$location_id = absint($config['location_id'] ?? 0);

		if (empty($location_id)) {
			$error_message = __('No Remote Location selected for this module.', 'data-machine');
			$this->logger->error($error_message, ['module_job_config' => $module_job_config]);
			return new WP_Error('remote_publish_config_missing', $error_message);
		}

		// Fetch location details (including decrypted password)
		// We need the user ID of the *owner* of the location, not necessarily the user running the job.
		// This is a potential issue - how do we get the owner ID here?
		// Option 1: Assume the $user_id passed to handle() IS the owner (might be incorrect).
		// Option 2: Store owner_id in module config (redundant).
		// Option 3: Modify get_location to not require user_id if called internally (less secure).
		// Option 4: Look up module owner, assume they own the location (best guess for now).
		// Let's proceed with Option 1 for now, acknowledging the limitation.
		if (empty($user_id)) {
			// If job runs via cron, $user_id might be null. We need a user context.
			// Since this is an internal system operation, we'll try to get the location
			// without user verification - this is acceptable for automated jobs
			$this->logger->warning('No user context available for remote publish, attempting system access', ['location_id' => $location_id]);
			$location = $this->db_locations->get_location($location_id, null, true, true); // Allow system access
		} else {
			$location = $this->db_locations->get_location($location_id, $user_id, true); // Normal user-verified access
		}

		if (!$location || empty($location->target_site_url) || empty($location->target_username) || !isset($location->password)) {
			$error_message = __('Could not retrieve details for the selected Remote Location.', 'data-machine');
			$this->logger->error($error_message, ['location_id' => $location_id, 'user_id' => $user_id]);
			return new WP_Error('remote_publish_location_fetch_failed', $error_message);
		}
		if ($location->password === false) { // Check decryption failure
			$error_message = __('Failed to decrypt password for the selected Remote Location.', 'data-machine');
			$this->logger->error($error_message, ['location_id' => $location_id, 'user_id' => $user_id]);
			return new WP_Error('remote_publish_decrypt_failed', $error_message);
		}

		// Use fetched credentials
		$remote_url = $location->target_site_url;
		$remote_user = $location->target_username;
		$remote_password = $location->password; // Decrypted password

		// Get publish settings from the specific $config sub-array
		$post_type   = $config['selected_remote_post_type'] ?? 'post';
		$post_status = $config['remote_post_status'] ?? 'publish';
		$category_id = $config['selected_remote_category_id'] ?? '';              // No fallback - empty means no category
		$tag_id      = $config['selected_remote_tag_id'] ?? '';                    // No fallback - empty means no tags

		// Parse AI output string using autoloaded class
		$parser = new AiResponseParser( $ai_output_string );
		$parser->parse(); // Ensure parsing happens
		$parsed_data = [
			'title' => $parser->get_title(),
			'content' => $parser->get_content(),
			'category' => $parser->get_publish_category(),
			'tags' => $parser->get_publish_tags() ? array_map('trim', explode(',', $parser->get_publish_tags())) : [],
			'custom_taxonomies' => $parser->get_custom_taxonomies() // Get custom taxonomies
		];

		// --- Prepare Content: Prepend Image, Append Source --- 
		$final_content = $this->prepend_image_if_available($parsed_data['content'], $input_metadata);
		$final_content = $this->append_source_if_available($final_content, $input_metadata);
		// --- End Prepare Content --- 

		// --- Convert Markdown content to HTML ---
		// Check if Gutenberg blocks should be used
		$use_gutenberg = ($config['use_gutenberg_blocks'] ?? '1') === '1';
		// Call the static method directly using autoloaded class
		$html_content = MarkdownConverter::convert_to_html($final_content, $use_gutenberg);
		// --- End Markdown Conversion ---

		// --- Determine Post Date ---
		$post_date_source = $config['post_date_source'] ?? 'current_date'; // Get setting from $config
		$post_date_iso = null;

		if ($post_date_source === 'source_date' && !empty($input_metadata['original_date_gmt'])) {
			$source_date_gmt_string = $input_metadata['original_date_gmt'];

			// Attempt to parse the date string, accepting common formats like ISO 8601
			$timestamp = strtotime($source_date_gmt_string);

			// Check if strtotime was successful
			if ($timestamp !== false) {
				// Format the timestamp into ISO 8601 format for the REST API (GMT/UTC)
				$post_date_iso = gmdate('Y-m-d\TH:i:s', $timestamp);
			} else {
				// Log an error if the format couldn't be parsed
				$this->logger->warning('Could not parse original_date_gmt from input metadata.', ['original_date_gmt' => $source_date_gmt_string, 'metadata' => $input_metadata]);
			}
		}
		// If source date wasn't used or invalid, $post_date_iso remains null,
		// and the remote WordPress site will use its current time.
		// --- End Determine Post Date ---

		// Prepare data payload for the remote API
		$payload = array(
			'title'       => $parsed_data['title'] ?: __( 'Untitled Airdropped Post', 'data-machine' ),
			'content'     => $html_content, // Use processed HTML content
			'post_type'   => $post_type,
			'status'      => $post_status, // Changed key to 'status' for REST API
            // --- Initialize Taxonomy Keys ---
            'category_id' => null,
            'category_name' => null,
            'rest_category' => null,
            'tag_ids' => [],
            'tag_names' => [],
            'rest_post_tag' => null,
            'custom_taxonomies' => [],
            // rest_{slug} keys for manual custom taxonomies are added dynamically later
            // --- End Initialize ---
		);
		// Add module_id for tracking
		if (!empty($module_job_config['module_id'])) {
			$payload['dm_module_id'] = intval($module_job_config['module_id']);
		}

		// Add date to payload if determined from source
		if ($post_date_iso) {
			$payload['date_gmt'] = $post_date_iso;
			$payload['date'] = $post_date_iso; // Sending both date and date_gmt is often needed
		}

		// Fetch remote site taxonomies directly from location (instead of carrying in job args)
		$remote_cats = [];
		$remote_tags = [];
		if (!empty($location_id) && !empty($user_id)) {
			$location = $this->db_locations->get_location($location_id, $user_id, false);
			if ($location && !empty($location->synced_site_info)) {
				$site_info = json_decode($location->synced_site_info, true);
				$remote_cats = $site_info['taxonomies']['category']['terms'] ?? [];
				$remote_tags = $site_info['taxonomies']['post_tag']['terms'] ?? [];
			}
		}

		// Category Logic - Determine what to send
		if ( is_string( $category_id ) && ($category_id === 'instruct_model') ) {
			// Model decides or Instruct Model: Send the name parsed from AI, if any
			if (!empty($parsed_data['category'])) {
				$payload['category_name'] = $parsed_data['category'];
				$assigned_category_name = $parsed_data['category']; // For reporting
			}
			// Additionally send the mode if instructed
			if ($category_id === 'instruct_model') {
				$payload['rest_category'] = 'instruct_model';
			}
			$assigned_category_id = null; // ID will be determined/created by receiver

		} elseif ( is_numeric( $category_id ) && $category_id > 0 ) { // Manual selection: Send the ID
			$payload['category_id'] = $category_id;
			// Find the name locally for reporting back (optional, could get from response)
			foreach ($remote_cats as $cat) { // Use synced info if available
				if ($cat['term_id'] == $category_id) {
					$assigned_category_name = $cat['name'];
					break;
				}
			}
			$assigned_category_id = $category_id;
		}

		// Tag Logic - Determine what to send
		if ( is_string( $tag_id ) && ($tag_id === 'instruct_model') ) {
			// Model decides or Instruct Model: Send the names parsed from AI, if any
			if (!empty($parsed_data['tags'])) {
				// --- ENFORCE SINGLE TAG FOR instruct_model/model_decides --- 
				$first_tag_name = trim($parsed_data['tags'][0]); // Get the first tag
				if (!empty($first_tag_name)) {
					$payload['tag_names'] = [$first_tag_name]; // Send only the first tag name in an array
					$assigned_tag_names = [$first_tag_name]; // For reporting
					if (count($parsed_data['tags']) > 1) {
						$this->logger->info("Remote Publish: Instruct/Model mode - Sending only first tag '{$first_tag_name}'. AI provided: " . implode(', ', $parsed_data['tags']), ['location_id' => $location_id]);
					}
				} else {
					$assigned_tag_names = [];
				}
				// --- END ENFORCEMENT ---
			} else {
				$assigned_tag_names = [];
			}
			// Additionally send the mode if instructed
			if ($tag_id === 'instruct_model') {
				$payload['rest_post_tag'] = 'instruct_model';
			}
			$assigned_tag_ids = []; // IDs will be determined/created by receiver

		} elseif ( is_numeric( $tag_id ) && $tag_id > 0 ) { // Manual selection: Send the ID
			$payload['tag_ids'] = array( $tag_id );
			$assigned_tag_ids = array( $tag_id );
			// Find name locally for reporting back (optional)
			foreach ($remote_tags as $tag) {
				if ($tag['term_id'] == $tag_id) {
					$assigned_tag_names = [$tag['name']];
					break;
				}
			}
		}

		// Custom Taxonomy Logic
		// --- MODIFIED: Iterate directly over configured custom tax values ---
		$selected_custom_tax_values = $config['selected_custom_taxonomy_values'] ?? [];

		// Custom taxonomy processing

		// Iterate over the taxonomies actually configured for this module job
		foreach ( $selected_custom_tax_values as $tax_slug => $tax_value ) {
			// Basic validation
			if ( empty($tax_slug) || empty($tax_value) ) {
				continue;
			}

			// Determine what to send for this custom taxonomy
			if ( is_string( $tax_value ) && ( $tax_value === 'instruct_model' ) ) {
				// Instruct Model: Send the names parsed from AI for this taxonomy, if any
				$term_names = $parsed_data['custom_taxonomies'][$tax_slug] ?? [];
				if (!empty($term_names)) {
					// --- RE-APPLY SINGLE TERM Enforcement ---
					$first_term_name = trim($term_names[0]); // Get the first term name
					if (!empty($first_term_name)) {
						// Send only first term, using the key expected by airdrop receiver
						$payload['custom_taxonomies'][$tax_slug] = [$first_term_name];
						$assigned_custom_taxonomies[$tax_slug] = [$first_term_name]; // For reporting
					} else {
						$assigned_custom_taxonomies[$tax_slug] = []; // Ensure key exists if no terms parsed for reporting
					}
				} else {
					$assigned_custom_taxonomies[$tax_slug] = []; // Ensure key exists if no terms parsed for reporting
				}
			} elseif ( is_numeric( $tax_value ) ) {
				// Specific Term ID selected: Send the term ID
				$term_id = intval( $tax_value );
				$payload['custom_taxonomies'][$tax_slug] = [$term_id]; // Send as ID
				// Attempt to get term name for reporting (best effort)
				$term = get_term($term_id);
				$assigned_custom_taxonomies[$tax_slug] = $term ? [$term->name] : ["ID: {$term_id}"];
			} else {
				// Handle other cases or log warning if value is unexpected
				$this->logger->warning("Unexpected value type for custom taxonomy in selected_custom_taxonomy_values.", ['tax_slug' => $tax_slug, 'value' => $tax_value]);
			}
		} // End foreach selected_custom_tax_values

		// End custom taxonomy processing

		// Construct the target API URL
		$api_url = $remote_url . '/wp-json/airdrop/v1/receive'; // Correct endpoint from helper plugin

		// Log essential payload info only
		$this->logger->info('Remote publish payload prepared', [
			'api_url' => $api_url,
			'title' => $payload['title'] ?? 'No title',
			'post_type' => $payload['post_type'] ?? 'post',
			'content_length' => strlen($payload['content'] ?? ''),
			'has_category' => !empty($payload['category_id']) || !empty($payload['category_name']),
			'has_tags' => !empty($payload['tag_ids']) || !empty($payload['tag_names'])
		]);

		// Prepare arguments for HTTP service
		$args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $remote_user . ':' . $remote_password ),
				'Content-Type'  => 'application/json; charset=utf-8',
			),
            'body'    => json_encode($payload),
			'timeout' => 180, // Increased timeout for slow remote sites
		);

		// Use HTTP service - replaces duplicated HTTP code
		$response = $this->http_service->post( $api_url, [], $args, 'Remote Publishing API' );

		// --- Handle Publish Response ---
		if ( is_wp_error( $response ) ) {
			$error_message = __( 'Failed to send data to the remote site.', 'data-machine' ) . ' ' . $response->get_error_message();
			$this->logger->error( $error_message, [
				'api_url' => $api_url, 
				'error' => $response->get_error_message()
			]);
			return new WP_Error( 'remote_publish_request_failed', $error_message );
		}

		$response_code = $response['status_code'];
		$body = $response['body'];

		// Parse JSON response with error handling
		$decoded_body = $this->http_service->parse_json( $body, 'Remote Publishing API' );
		if ( is_wp_error( $decoded_body ) ) {
			$this->logger->error( 'Failed to parse remote publishing response JSON.', [
				'api_url' => $api_url,
				'error' => $decoded_body->get_error_message()
			]);
			return $decoded_body;
		}

		if ( $response_code !== 201 ) { // Expect 201 Created
			$error_message_detail = isset( $decoded_body['message'] ) ? $decoded_body['message'] : __( 'Unknown error occurred on the remote site during publishing.', 'data-machine' );
			/* translators: %d: HTTP response code */
			$error_message = sprintf( __( 'Remote site returned an error (Code: %d).', 'data-machine' ), $response_code );
			$this->logger->error( $error_message, [
				'api_url' => $api_url, 
				'response_code' => $response_code, 
				'response_body' => $body, 
				'error_detail' => $error_message_detail
			]);
			return new WP_Error( 'remote_publish_failed', $error_message, $error_message_detail );
		}

		// Return success data from the remote site's response
		return array(
			'status'                 => 'success',
			'message'                => $decoded_body['message'] ?? __( 'Post published remotely successfully!', 'data-machine' ),
			'remote_post_id'         => $decoded_body['post_id'] ?? null,
			'remote_edit_link'       => $decoded_body['edit_link'] ?? null,
			'remote_view_link'       => $decoded_body['view_link'] ?? null,
			'post_title'             => $payload['title'], // Return what was sent
			'final_output'           => $payload['content'], // Return what was sent
			'assigned_category_id'   => $assigned_category_id,
			'assigned_category_name' => $assigned_category_name,
			'assigned_tag_ids'       => is_array($assigned_tag_ids) ? $assigned_tag_ids : [],
			'assigned_tag_names'     => is_array($assigned_tag_names) ? $assigned_tag_names : [],
			'assigned_custom_taxonomies' => $assigned_custom_taxonomies, // Add custom tax info to result
		);
	}


	/**
	 * Get settings fields for the Remote Publish output handler.
	 *
	 * @return array Associative array of field definitions.
	 */
	public function get_settings_fields(array $current_config = []): array {
		$locations_options = ['' => '-- Select Location --'];
		$post_type_options = [ '' => '-- Select Location First --' ];
		$category_options = [ '' => '-- Select Location First --', 'model_decides' => '-- Let Model Decide --', 'instruct_model' => '-- Instruct Model --' ];
		$tag_options = [ '' => '-- Select Location First --', 'model_decides' => '-- Let Model Decide --', 'instruct_model' => '-- Instruct Model --' ];

		$selected_post_type = $current_config['selected_remote_post_type'] ?? '';
		// Use string keys for default mode
		$selected_category_id = $current_config['selected_remote_category_id'] ?? 'model_decides';
		$selected_tag_id = $current_config['selected_remote_tag_id'] ?? 'model_decides';

		// --- NEW: Retrieve site_info using injected db_locations ---
		$site_info = [];
		$location_id = $current_config['location_id'] ?? null;
		if ($location_id && $this->db_locations) { // Check if location_id is set and db_locations is injected
			try {
				$user_id = get_current_user_id(); // Need user context to get location
				if ($user_id) {
					$location = $this->db_locations->get_location($location_id, $user_id);
					if ($location && !empty($location->synced_site_info)) {
						$decoded_info = json_decode($location->synced_site_info, true);
						// Verify decoding was successful and it's an array
						if (is_array($decoded_info)) {
							$site_info = $decoded_info;
						} else {
							$this->logger->warning('Failed to decode synced_site_info for location.', ['location_id' => $location_id, 'synced_info' => $location->synced_site_info]);
						}
					}
				} else {
					$this->logger->warning('Cannot retrieve site_info in get_settings_fields without user context.', ['location_id' => $location_id]);
				}
			} catch (\Exception $e) {
				$this->logger->error('Error retrieving location or site_info in get_settings_fields.', ['location_id' => $location_id, 'exception' => $e]);
			}
		}
		// --- End site_info retrieval ---

		// --- Build base fields ---
		$fields = [
			'location_id' => [
				'type' => 'select',
				'label' => __('Remote Location', 'data-machine'),
				'description' => sprintf(
					/* translators: %s: URL to manage remote locations */
					__('Select a pre-configured remote publishing location. Manage locations <a href="%s" target="_blank">here</a>.', 'data-machine'),
					admin_url('admin.php?page=dm-remote-locations')
				),
				'options' => $locations_options,
				'required' => true,
				'default' => '',
			],
			'selected_remote_post_type' => [
				'type' => 'select',
				'label' => __('Remote Post Type', 'data-machine'),
				'description' => __('Select the post type on the target site. Populated after selecting a location.', 'data-machine'),
				'options' => $post_type_options,
				'default' => $selected_post_type,
			],
			'remote_post_status' => [
				'type' => 'select',
				'label' => __('Remote Post Status', 'data-machine'),
				'description' => __('Select the desired status for the post created on the target site.', 'data-machine'),
				'options' => [
					'draft' => __('Draft', 'data-machine'),
					'publish' => __('Publish', 'data-machine'),
					'pending' => __('Pending Review', 'data-machine'),
					'private' => __('Private', 'data-machine'),
				],
				'default' => 'publish',
			],
			'use_gutenberg_blocks' => [
				'type' => 'select',
				'label' => __('Editor Format', 'data-machine'),
				'description' => __('Choose whether to format content for Gutenberg block editor or classic editor on the remote site.', 'data-machine'),
				'options' => [
					'1' => __('Gutenberg Block Editor (Recommended)', 'data-machine'),
					'0' => __('Classic Editor', 'data-machine'),
				],
				'default' => '1',
			],
			'post_date_source' => [
				'type' => 'select',
				'label' => __( 'Post Date Setting', 'data-machine' ),
				'description' => __( 'Choose whether to use the original date from the source (if available) or the current date when publishing remotely. UTC timestamps will be used.', 'data-machine' ),
				'options' => [
					'current_date' => __( 'Use Current Date', 'data-machine' ),
					'source_date' => __( 'Use Source Date (if available)', 'data-machine' ),
				],
				'default' => 'current_date',
			],
			'selected_remote_category_id' => [
				'type' => 'select',
				'label' => __('Remote Category', 'data-machine'),
				'description' => __('Select a category, let the AI choose, or instruct the AI using your prompt. Populated after selecting a location.', 'data-machine'),
				'options' => $category_options,
				'default' => $selected_category_id, // Will be string or int ID
			],
			'selected_remote_tag_id' => [
				'type' => 'select',
				'label' => __('Remote Tag', 'data-machine'),
				'description' => __('Select a single tag, let the AI choose, or instruct the AI using your prompt. Populated after selecting a location.', 'data-machine'),
				'options' => $tag_options,
				'default' => $selected_tag_id, // Will be string or int ID
			],
		];

		// --- NEW: Add dynamic custom taxonomy fields if site_info is available ---
		if (!empty($site_info['taxonomies']) && is_array($site_info['taxonomies'])) {
			foreach ($site_info['taxonomies'] as $tax_slug => $tax_data) {
				if (in_array($tax_slug, ['category', 'post_tag'])) {
					continue; // Already handled above
				}
				
				
				$tax_options = [
					'' => '-- Select ' . ($tax_data['label'] ?? ucfirst($tax_slug)) . ' --',
					'model_decides' => '-- Let Model Decide --',
					'instruct_model' => '-- Instruct Model --'
				];
				if (!empty($tax_data['terms']) && is_array($tax_data['terms'])) {
					foreach ($tax_data['terms'] as $term) {
						if (isset($term['term_id']) && isset($term['name'])) {
							$tax_options[$term['term_id']] = $term['name'];
						}
					}
				}
				if (count($tax_options) > 1) {
					$fields['rest_' . $tax_slug] = [
						'type' => 'select',
						'label' => $tax_data['label'] ?? ucfirst($tax_slug),
						'options' => $tax_options,
						'post_types' => $tax_data['post_types'] ?? [],
						'description' => 'Select a ' . ($tax_data['label'] ?? $tax_slug) . ' for this post.',
						'default' => $current_config['rest_' . $tax_slug] ?? 'model_decides' // Default custom tax to string key
					];
					
				}
			}
		}

		return $fields;
	}
/**
 * Sanitize settings for the Publish Remote output handler.
 *
 * @param array $raw_settings
 * @return array
 */
public function sanitize_settings(array $raw_settings): array {
	$sanitized = [];
	$sanitized['location_id'] = absint($raw_settings['location_id'] ?? 0);
	$sanitized['selected_remote_post_type'] = sanitize_text_field($raw_settings['selected_remote_post_type'] ?? '');
	$sanitized['remote_post_status'] = sanitize_text_field($raw_settings['remote_post_status'] ?? 'publish');
	$use_gutenberg_value = $raw_settings['use_gutenberg_blocks'] ?? '1';
	$sanitized['use_gutenberg_blocks'] = in_array($use_gutenberg_value, ['0', '1']) ? $use_gutenberg_value : '1';
	$valid_date_sources = ['current_date', 'source_date'];
	$date_source = sanitize_text_field($raw_settings['post_date_source'] ?? 'current_date');
	$sanitized['post_date_source'] = in_array($date_source, $valid_date_sources) ? $date_source : 'current_date';

	// Handle category/tag modes as string keys or integers
	$cat_val = $raw_settings['selected_remote_category_id'] ?? 'model_decides';
	if ($cat_val === 'model_decides' || $cat_val === 'instruct_model') {
		$sanitized['selected_remote_category_id'] = $cat_val;
	} else {
		$sanitized['selected_remote_category_id'] = intval($cat_val);
	}
	$tag_val = $raw_settings['selected_remote_tag_id'] ?? 'model_decides';
	if ($tag_val === 'model_decides' || $tag_val === 'instruct_model') {
		$sanitized['selected_remote_tag_id'] = $tag_val;
	} else {
		$sanitized['selected_remote_tag_id'] = intval($tag_val);
	}

	// --- NEW: Handle Custom Taxonomy Values ---
    $sanitized['selected_custom_taxonomy_values'] = []; // Initialize the key
    if (isset($raw_settings['selected_custom_taxonomy_values']) && is_array($raw_settings['selected_custom_taxonomy_values'])) {
        foreach ($raw_settings['selected_custom_taxonomy_values'] as $tax_slug => $value) {
            $clean_slug = sanitize_key($tax_slug); // Sanitize the slug just in case
            if (empty($clean_slug)) continue; // Skip if slug is invalid

            $sanitized_value = null;
            // Handle both string options and numeric IDs
            if (is_string($value)) {
                $trimmed_value = strtolower(trim($value));
                if ($trimmed_value === 'model_decides' || $trimmed_value === 'instruct_model' || $trimmed_value === '') {
                     // Allow modes or empty selection
                    $sanitized_value = $trimmed_value;
                }
            }
            // If not a valid mode or empty string, treat as numeric ID (or default to 0/null if invalid)
            if ($sanitized_value === null) {
                $sanitized_value = absint($value);
            }

            $sanitized['selected_custom_taxonomy_values'][$clean_slug] = $sanitized_value;
        }
    }
    // --- End Custom Taxonomy Handling ---

	return $sanitized;
}

/**
 * Get the user-friendly label for this handler.
 *
 * @return string
 */
public static function get_label(): string {
	return 'Publish Remotely';
}

} // End class Data_Machine_Output_Publish_Remote
