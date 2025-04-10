<?php
/**
 * Handles the 'Publish Local' output type.
 *
 * Creates a new post on the current WordPress site.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/output
 * @since      0.7.0
 */
class Data_Machine_Output_Publish_Local implements Data_Machine_Output_Handler_Interface {

	/**
	 * Handles publishing the AI output locally as a WordPress post.
	 *
	 * @param string $ai_output_string The finalized string from the AI.
	 * @param array $module_job_config Configuration specific to this output job (post_type, post_status, etc.).
	 * @param int|null $user_id The ID of the user to assign as post author.
	 * @param array $input_metadata Metadata from the original input source (e.g., original URL, timestamp).
	 * @return array|WP_Error Result array on success, WP_Error on failure.
	 */
	public function handle( string $ai_output_string, array $module_job_config, ?int $user_id, array $input_metadata ): array|WP_Error {
		// Get output config directly from the job config array
		$config = $module_job_config['output_config'] ?? [];
		if ( ! is_array( $config ) ) $config = array(); // Ensure it's an array

		// Get settings from config using the keys defined in get_settings_fields
		$post_type   = $config['post_type'] ?? 'post';
		$post_status = $config['post_status'] ?? 'draft';
		$category_id = $config['selected_local_category_id'] ?? -1;
		// $tag_mode removed
		$tag_id      = $config['selected_local_tag_id'] ?? -1; // Use singular key, default -1

		// Parse AI output string
		// Ensure the parser class is loaded
		require_once DATA_MACHINE_PATH . 'includes/helpers/class-ai-response-parser.php';
		$parser = new Data_Machine_AI_Response_Parser( $ai_output_string );
		// Explicitly call parse (now public) - though getter methods also call it implicitly
		$parser->parse();
		$parsed_data = [
			'title' => $parser->get_title(),
			'content' => $parser->get_content(),
			'category' => $parser->get_remote_category_directive(), // Assuming directive name matches
			'tags' => $parser->get_remote_tags_directive() ? explode(',', $parser->get_remote_tags_directive()) : [] // Assuming comma-separated
		];
		// Trim tag names
        $parsed_data['tags'] = array_map('trim', $parsed_data['tags']);

        // --- Convert Markdown content to HTML ---
        require_once DATA_MACHINE_PATH . 'includes/helpers/class-markdown-converter.php';
        // Call the static method directly
        $html_content = Data_Machine_Markdown_Converter::convert_to_html($parsed_data['content']);
        // --- End Markdown Conversion ---

		// --- Determine Post Date ---
		$post_date_source = $config['post_date_source'] ?? 'current_date'; // Get setting
		$post_date_gmt = null;
		$post_date = null;

		if ($post_date_source === 'source_date' && !empty($input_metadata['original_date_gmt'])) {
			$source_date_gmt_string = $input_metadata['original_date_gmt'];

			// Attempt to parse the GMT date string
			// Check if it's a valid format (YYYY-MM-DD HH:MM:SS)
			if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $source_date_gmt_string)) {
				$post_date_gmt = $source_date_gmt_string; // Use the valid string directly
				// Site's local time (adjusts for timezone offset)
				$post_date = get_date_from_gmt( $post_date_gmt ); 
			} else {
				// Log an error if the format is unexpected
				error_log('Data Machine Publish Local: Invalid original_date_gmt format received: ' . $source_date_gmt_string);
			}
		}
		// If source date wasn't used or invalid, $post_date and $post_date_gmt remain null,
		// and wp_insert_post will use the current time.
		// --- End Determine Post Date ---

		// Prepare post data
		$post_data = array(
			'post_title'   => $parsed_data['title'] ?: __( 'Untitled Post', 'data-machine' ), // Add fallback title
			'post_content' => $html_content, // Use converted HTML
			'post_status'  => $post_status,
			'post_author'  => $user_id ?: get_current_user_id(), // Use provided user ID or fallback
			'post_type'    => $post_type,
		);

		// Add post date if determined from source
		if ($post_date && $post_date_gmt) {
			$post_data['post_date'] = $post_date;
			$post_data['post_date_gmt'] = $post_date_gmt;
		}

		// Insert the post
		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error(
				'local_publish_failed',
				__( 'Failed to create local post:', 'data-machine' ) . ' ' . $post_id->get_error_message()
			);
		}

		// --- Taxonomy Handling ---
		$assigned_category_id = null;
		$assigned_category_name = null;
		$assigned_tag_ids = [];
		$assigned_tag_names = [];

		// Category Assignment
		if ( $category_id > 0 ) { // Manual selection
			$term = get_term( $category_id, 'category' );
			if ( $term && ! is_wp_error( $term ) ) {
				wp_set_post_terms( $post_id, array( $category_id ), 'category', false );
				$assigned_category_id = $category_id;
				$assigned_category_name = $term->name;
			}
		} elseif ( $category_id === -1 && ! empty( $parsed_data['category'] ) ) { // Model decides
			$term = get_term_by( 'name', $parsed_data['category'], 'category' );
			if ( $term ) {
				wp_set_post_terms( $post_id, array( $term->term_id ), 'category', false );
				$assigned_category_id = $term->term_id;
				$assigned_category_name = $term->name;
			} else {
				// Optionally create the category if it doesn't exist
				// $term_info = wp_insert_term( $parsed_data['category'], 'category' );
				// if (!is_wp_error($term_info)) { ... }
			}
		}

		// Tag Assignment
		// Tag Assignment Logic based on single $tag_id
		if ( $tag_id > 0 ) { // Manual selection: Assign the single selected tag
			$term = get_term( $tag_id, 'post_tag' );
			if ( $term && ! is_wp_error( $term ) ) {
				wp_set_post_terms( $post_id, array( $tag_id ), 'post_tag', false );
				$assigned_tag_ids = array( $tag_id );
				$assigned_tag_names = array( $term->name );
			}
		} elseif ( $tag_id === -1 && ! empty( $parsed_data['tags'] ) ) { // Model decides: Assign tags parsed from AI response
			$term_ids_to_assign = [];
			$term_names_to_assign = [];
			foreach ( $parsed_data['tags'] as $tag_name ) {
				if (empty(trim($tag_name))) continue;
				$term = get_term_by( 'name', $tag_name, 'post_tag' );
				if ( $term ) {
					$term_ids_to_assign[] = $term->term_id;
					$term_names_to_assign[] = $term->name;
				} else {
					// Optionally create tag
				}
			}
			if ( ! empty( $term_ids_to_assign ) ) {
				wp_set_post_terms( $post_id, $term_ids_to_assign, 'post_tag', false );
				$assigned_tag_ids = $term_ids_to_assign;
				$assigned_tag_names = $term_names_to_assign;
			}
		}
		// If $tag_id is -1 and $parsed_data['tags'] is empty, no tags are assigned.
		// --- End Taxonomy Handling ---

		// Success
		return array(
			'status'                 => 'success',
			'message'                => __( 'Post published locally successfully!', 'data-machine' ),
			'local_post_id'          => $post_id,
			'local_edit_link'        => get_edit_post_link( $post_id, 'raw' ),
			'local_view_link'        => get_permalink( $post_id ),
			'post_title'             => $parsed_data['title'],
			'final_output'           => $parsed_data['content'],
			'assigned_category_id'   => $assigned_category_id,
			'assigned_category_name' => $assigned_category_name,
			'assigned_tag_ids'       => $assigned_tag_ids,
			'assigned_tag_names'     => $assigned_tag_names,
		);
	}

	/**
	 * Get settings fields for the Local Publish output handler.
	 *
	 * @return array Associative array of field definitions.
	 */
	public static function get_settings_fields(): array {
		// Get users for author dropdown
		$users = get_users( array( 'fields' => array( 'ID', 'display_name' ), 'orderby' => 'display_name' ) );

		// Get available post types
		$post_type_options = [];
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$common_types = ['post' => 'Post', 'page' => 'Page'];
		foreach ($common_types as $slug => $label) {
			if (isset($post_types[$slug])) {
				$post_type_options[$slug] = $label;
				unset($post_types[$slug]);
			}
		}
		foreach ($post_types as $pt) {
			$post_type_options[$pt->name] = $pt->label;
		}

		// Get available categories
		$category_options = [
            '-1' => '-- Let Model Decide --',
            '0'  => '-- Instruct Model --' // Added Instruct Model
        ];
		$local_categories = get_terms(array('taxonomy' => 'category', 'hide_empty' => false));
		if (!is_wp_error($local_categories)) {
			foreach ($local_categories as $cat) {
				$category_options[$cat->term_id] = $cat->name;
			}
		}

		// Get available tags
        $tag_options = [
            '-1' => '-- Let Model Decide --',
            '0'  => '-- Instruct Model --' // Added Instruct Model
        ];
		$local_tags = get_terms(array('taxonomy' => 'post_tag', 'hide_empty' => false));
		if (!is_wp_error($local_tags)) {
			foreach ($local_tags as $tag) {
				$tag_options[$tag->term_id] = $tag->name;
			}
		}

		return [
			'post_type' => [
				'type' => 'select',
				'label' => __('Post Type', 'data-machine'),
				'description' => __('Select the post type for published content.', 'data-machine'),
				'options' => $post_type_options,
				'default' => 'post',
			],
			'post_status' => [
				'type' => 'select',
				'label' => __('Post Status', 'data-machine'),
				'description' => __('Select the status for the newly created post.', 'data-machine'),
				'options' => [
					'draft' => __('Draft'),
					'publish' => __('Publish'),
					'pending' => __('Pending Review'),
					'private' => __('Private'),
				],
				'default' => 'draft',
			],
			'post_date_source' => [
				'type' => 'select',
				'label' => __( 'Post Date Setting', 'data-machine' ),
				'description' => __( 'Choose whether to use the original date from the source (if available) or the current date when publishing. UTC timestamps will be converted to site time.', 'data-machine' ),
				'options' => [
					'current_date' => __( 'Use Current Date', 'data-machine' ),
					'source_date' => __( 'Use Source Date (if available)', 'data-machine' ),
				],
				'default' => 'current_date',
			],
			'selected_local_category_id' => [
				'type' => 'select',
				'label' => __('Category', 'data-machine'),
				'description' => __('Select a category, let the AI choose, or instruct the AI using your prompt.', 'data-machine'), // Updated description
				'options' => $category_options,
				'default' => -1,
			],
			// 'selected_local_tag_mode' field removed
			'selected_local_tag_id' => [ // Changed key to singular ID
				'type' => 'select', // Changed back to select
				'label' => __('Tag', 'data-machine'), // Changed label to singular
				'description' => __('Select a single tag, let the AI choose, or instruct the AI using your prompt.', 'data-machine'), // Updated description
				'options' => $tag_options,
				'default' => -1, // Default to Model Decides
			],
		];
	}

	/**
	 * Get the user-friendly label for this handler.
	 *
	 * @return string
	 */
	public static function get_label(): string {
		return __( 'Publish Locally', 'data-machine' );
	}

} // End class Data_Machine_Output_Publish_Local