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

class Data_Machine_Output_Publish_Remote implements Data_Machine_Output_Handler_Interface {

    /**
     * Database handler for remote locations.
     * @var Data_Machine_Database_Remote_Locations
     */
    private $db_locations;

    /**
     * Constructor.
     * @param Data_Machine_Database_Remote_Locations $db_locations
     */
    public function __construct(Data_Machine_Database_Remote_Locations $db_locations) {
        $this->db_locations = $db_locations;
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
		// Get output config directly from the job config array
		$output_config = $module_job_config['output_config'] ?? [];
		if ( ! is_array( $output_config ) ) $output_config = array(); // Ensure it's an array

		// Access settings within the 'publish_remote' sub-array
		$config = $output_config['publish_remote'] ?? [];
		if ( ! is_array( $config ) ) $config = array(); // Ensure publish_remote sub-array exists

		// Get the selected remote location ID
		$location_id = absint($config['remote_location_id'] ?? 0);

		if (empty($location_id)) {
			return new WP_Error('remote_publish_config_missing', __('No Remote Location selected for this module.', 'data-machine'));
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
			// Try getting module owner? Requires DB Modules access... complex dependency.
			// For now, fail if user_id is missing in this context.
			return new WP_Error('remote_publish_user_context_missing', __('User context required to fetch remote location credentials.', 'data-machine'));
		}
		$location = $this->db_locations->get_location($location_id, $user_id, true); // Decrypt password

		if (!$location || empty($location->target_site_url) || empty($location->target_username) || !isset($location->password)) {
			return new WP_Error('remote_publish_location_fetch_failed', __('Could not retrieve details for the selected Remote Location.', 'data-machine'));
		}
		if ($location->password === false) { // Check decryption failure
			return new WP_Error('remote_publish_decrypt_failed', __('Failed to decrypt password for the selected Remote Location.', 'data-machine'));
		}

		// Use fetched credentials
		$remote_url = $location->target_site_url;
		$remote_user = $location->target_username;
		$remote_password = $location->password; // Decrypted password

		// Get publish settings from the specific $config sub-array
		$post_type   = $config['selected_remote_post_type'] ?? 'post';
		$post_status = $config['remote_post_status'] ?? 'publish';
		$category_id = $config['selected_remote_category_id'] ?? -1; // -1 = Model Decides
		$tag_id      = $config['selected_remote_tag_id'] ?? -1;      // -1 = Model Decides

		// Parse AI output string
		require_once DATA_MACHINE_PATH . 'includes/helpers/class-ai-response-parser.php';
		$parser = new Data_Machine_AI_Response_Parser( $ai_output_string );
		$parser->parse(); // Ensure parsing happens
		$parsed_data = [
			'title' => $parser->get_title(),
			'content' => $parser->get_content(),
			'category' => $parser->get_remote_category_directive(),
			'tags' => $parser->get_remote_tags_directive() ? array_map('trim', explode(',', $parser->get_remote_tags_directive())) : []
		];

		      // --- Convert Markdown content to HTML ---
		      require_once DATA_MACHINE_PATH . 'includes/helpers/class-markdown-converter.php';
		      // Call the static method directly
		      $html_content = Data_Machine_Markdown_Converter::convert_to_html($parsed_data['content']);
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
				error_log('Data Machine Publish Remote: Could not parse original_date_gmt: ' . $source_date_gmt_string);
			}
		}
		// If source date wasn't used or invalid, $post_date_iso remains null,
		// and the remote WordPress site will use its current time.
		// --- End Determine Post Date ---

		// Prepare data payload for the remote API
		$payload = array(
			'title'       => $parsed_data['title'] ?: __( 'Untitled Airdropped Post', 'data-machine' ),
			'content'     => $html_content, // Use converted HTML
			'post_type'   => $post_type,
			'status'      => $post_status, // Changed key to 'status' for REST API
			// Taxonomy keys will be added below based on mode
		);

		// Add date to payload if determined from source
		if ($post_date_iso) {
			$payload['date_gmt'] = $post_date_iso;
			$payload['date'] = $post_date_iso; // Sending both date and date_gmt is often needed
		}

		// Get synced remote site info if available (this comes from the top-level output_config)
		$remote_info = $output_config['remote_site_info'] ?? []; // Use $output_config
		$remote_cats = $remote_info['taxonomies']['category']['terms'] ?? [];
		$remote_tags = $remote_info['taxonomies']['post_tag']['terms'] ?? [];

		// Category Logic - Determine what to send
		if ( $category_id > 0 ) { // Manual selection: Send the ID
			$payload['category_id'] = $category_id;
			// Find the name locally for reporting back (optional, could get from response)
			foreach ($remote_cats as $cat) { // Use synced info if available
				if ($cat['term_id'] == $category_id) {
					$assigned_category_name = $cat['name'];
					break;
				}
			}
			$assigned_category_id = $category_id;
		} elseif ( $category_id === -1 && ! empty( $parsed_data['category'] ) ) { // Model decides: Send the name
			$payload['category_name'] = $parsed_data['category'];
			$assigned_category_name = $parsed_data['category']; // For reporting
			$assigned_category_id = null; // ID will be determined/created by receiver
		}

		// Tag Logic - Determine what to send
		if ( $tag_id > 0 ) { // Manual selection: Send the ID
			$payload['tag_ids'] = array( $tag_id ); // Send as array
			$assigned_tag_ids = array( $tag_id );
			// Find name locally for reporting back (optional)
			foreach ($remote_tags as $tag) {
				if ($tag['term_id'] == $tag_id) {
					$assigned_tag_names = [$tag['name']];
					break;
				}
			}
		} elseif ( $tag_id === -1 && ! empty( $parsed_data['tags'] ) ) { // Model decides: Send the names
			$payload['tag_names'] = $parsed_data['tags']; // Send array of names
			$assigned_tag_names = $parsed_data['tags']; // For reporting
			$assigned_tag_ids = []; // IDs will be determined/created by receiver
		}

		// Construct the target API URL
		$api_url = $remote_url . '/wp-json/airdrop/v1/receive'; // Correct endpoint from helper plugin

		// Prepare arguments for wp_remote_post
		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $remote_user . ':' . $remote_password ),
				'Content-Type'  => 'application/json; charset=utf-8',
			),
			'body'    => json_encode( $payload ),
			'timeout' => 60, // Increased timeout
		);

		// Make the API request to send post data
		$response = wp_remote_post( $api_url, $args );

		// --- Handle Publish Response ---
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'remote_publish_request_failed', __( 'Failed to send data to the remote site.', 'data-machine' ) . ' ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$decoded_body = json_decode( $body, true );

		if ( $response_code !== 201 ) { // Expect 201 Created
			$error_message = isset( $decoded_body['message'] ) ? $decoded_body['message'] : __( 'Unknown error occurred on the remote site during publishing.', 'data-machine' );
			return new WP_Error( 'remote_publish_failed', sprintf( __( 'Remote site returned an error (Code: %d).', 'data-machine' ), $response_code ), $error_message );
		}

		// Success
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
			'assigned_tag_ids'       => $assigned_tag_ids,
			'assigned_tag_names'     => $assigned_tag_names,
		);
	}

	/**
	 * Get settings fields for the Remote Publish output handler.
	 *
	 * @return array Associative array of field definitions.
	 */
	public static function get_settings_fields(): array {
		// Options will be populated by the Settings_Fields service
		$locations_options = ['' => '-- Select Location --'];
		/* -- REMOVED PHP Location Fetching Logic --
		if (class_exists('Data_Machine_Service_Locator') && class_exists('Data_Machine_Database_Remote_Locations')) {
			// Attempt to get locations if classes exist (might not during initial load/activation)
			try {
				// Note: Accessing locator statically or globally isn't ideal here.
				// This suggests the settings fields might need access to the locator instance.
				// For now, we'll assume a global or passed locator if possible, otherwise skip population.
				// A better approach might be to populate this via JS after the page loads.
				// Let's prepare for JS population.
			} catch (\Exception $e) {
				// Handle error if locator/service not ready
			}
		}
		*/

		return [
			// Location Selection
			'remote_location_id' => [
				'type' => 'select',
				'label' => __('Remote Location', 'data-machine'),
				'description' => __('Select a pre-configured remote publishing location. Manage locations <a href="' . admin_url('admin.php?page=adc-remote-locations') . '" target="_blank">here</a>.', 'data-machine'),
				'options' => $locations_options, // Will be populated by JS mainly
				'required' => true, // A location must be selected
				'default' => '',
			],
			// Publish Settings Section (Dependent on selected location)
			'selected_remote_post_type' => [
				'type' => 'select',
				'label' => __('Remote Post Type', 'data-machine'),
				'description' => __('Select the post type on the target site. Populated after selecting a location.', 'data-machine'),
				'options' => [ '' => '-- Select Location First --' ], // Default state
				'default' => '',
			],
			'remote_post_status' => [
				'type' => 'select',
				'label' => __('Remote Post Status', 'data-machine'),
				'description' => __('Select the desired status for the post created on the target site.', 'data-machine'),
				'options' => [
					'draft' => __('Draft'),
					'publish' => __('Publish'),
					'pending' => __('Pending Review'),
					'private' => __('Private'),
				],
				'default' => 'publish',
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
				'options' => [
				    ''   => '-- Select Location First --', // Default state
	                   '-1' => '-- Let Model Decide --',
	                   '0'  => '-- Instruct Model --'
	               ],
				'default' => -1,
			],
			'selected_remote_tag_id' => [
				'type' => 'select',
				'label' => __('Remote Tag', 'data-machine'),
				'description' => __('Select a single tag, let the AI choose, or instruct the AI using your prompt. Populated after selecting a location.', 'data-machine'),
				'options' => [
	                   ''   => '-- Select Location First --', // Default state
	                   '-1' => '-- Let Model Decide --',
	                   '0'  => '-- Instruct Model --'
	               ],
				'default' => -1,
			],		];
	}

	/**
	 * Get the user-friendly label for this handler.
	 *
	 * @return string
	 */
	public static function get_label(): string {
		return __( 'Publish Remotely', 'data-machine' );
	}

} // End class Data_Machine_Output_Publish_Remote
