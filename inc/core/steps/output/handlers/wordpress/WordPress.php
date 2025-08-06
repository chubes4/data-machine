<?php
/**
 * Unified WordPress output handler.
 *
 * Handles WordPress publishing to multiple destinations:
 * - Local WordPress (wp_insert_post)
 * - Remote WordPress (Airdrop Helper Plugin)
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/inc/core/steps/output/handlers
 * @since      1.0.0
 */

namespace DataMachine\Core\Handlers\Output\WordPress;

use DataMachine\Core\Database\RemoteLocations;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WordPress {

    /**
     * Constructor.
     * Pure filter-based architecture - no dependencies.
     */
    public function __construct() {
        // No constructor dependencies - all services accessed via filters
    }


    /**
     * Handles publishing the AI output to WordPress (local or remote).
     *
     * @param object $data_packet Universal DataPacket JSON object with all content and metadata.
     * @return array Result array on success or failure.
     */
    public function handle_output($data_packet): array {
        // Access structured content directly from DataPacket (no parsing needed)
        $content_title = $data_packet->content->title ?? '';
        $content_body = $data_packet->content->body ?? '';
        $content_tags = $data_packet->content->tags ?? [];
        $content_summary = $data_packet->content->summary ?? '';

        // Get output config from DataPacket
        $module_job_config = [
            'output_config' => $data_packet->output_config ?? []
        ];

        // Extract metadata from DataPacket
        $input_metadata = [
            'original_date_gmt' => $data_packet->metadata->date_created ?? null,
            'source_url' => $data_packet->metadata->source_url ?? null,
            'image_source_url' => !empty($data_packet->attachments->images) ? $data_packet->attachments->images[0]->url : null
        ];

        // Get output config directly from the job config array
        $config = $module_job_config['output_config'] ?? [];
        if (!is_array($config)) $config = array();

        // Access config from nested structure
        $wordpress_config = $config['wordpress'] ?? [];
        
        // Determine destination type
        $destination_type = $wordpress_config['destination_type'] ?? 'local';

        // Structure content data for handlers
        $structured_content = [
            'title' => $content_title,
            'body' => $content_body,
            'tags' => $content_tags,
            'summary' => $content_summary
        ];

        switch ($destination_type) {
            case 'local':
                return $this->publish_local($structured_content, $wordpress_config, $input_metadata);

            case 'remote':
                return $this->publish_remote($structured_content, $wordpress_config, $input_metadata, $module_job_config);

            default:
                return [
                    'success' => false,
                    'error' => __('Invalid WordPress destination type specified.', 'data-machine')
                ];
        }
    }


    /**
     * Publish content to local WordPress installation.
     *
     * @param array $structured_content Structured content from DataPacket.
     * @param array $config Configuration array.
     * @param array $input_metadata Input metadata.
     * @return array Result array.
     */
    private function publish_local(array $structured_content, array $config, array $input_metadata): array {
        // Get settings from config
        $post_type = $config['post_type'] ?? 'post';
        $post_status = $config['post_status'] ?? 'draft';
        $post_author = $config['post_author'] ?? get_current_user_id();
        $category_id = $config['selected_local_category_id'] ?? -1;
        $tag_id = $config['selected_local_tag_id'] ?? -1;

        // Use structured content directly from DataPacket (no parsing needed)
        $parsed_data = [
            'title' => $structured_content['title'],
            'content' => $structured_content['body'], 
            'category' => '', // Category will be determined by AI directives or config
            'tags' => is_array($structured_content['tags']) ? $structured_content['tags'] : []
        ];
        
        // Ensure tags are trimmed strings
        $parsed_data['tags'] = array_map('trim', array_filter($parsed_data['tags']));
        $parsed_data['custom_taxonomies'] = []; // Will be populated by AI directives

        // Prepare Content: Prepend Image, Append Source
        $final_content = $this->prepend_image_if_available($parsed_data['content'], $input_metadata);
        $final_content = $this->append_source_if_available($final_content, $input_metadata);

        // Create Gutenberg blocks directly from structured content
        $block_content = $this->create_gutenberg_blocks_from_content($final_content);

        // Determine Post Date
        $post_date_source = $config['post_date_source'] ?? 'current_date';
        $post_date_gmt = null;
        $post_date = null;

        if ($post_date_source === 'source_date' && !empty($input_metadata['original_date_gmt'])) {
            $source_date_gmt_string = $input_metadata['original_date_gmt'];

            // Attempt to parse the GMT date string
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $source_date_gmt_string)) {
                $post_date_gmt = $source_date_gmt_string;
                $post_date = get_date_from_gmt($post_date_gmt);
            }
        }

        // Prepare post data
        $post_data = array(
            'post_title' => $parsed_data['title'] ?: __('Untitled Post', 'data-machine'),
            'post_content' => $block_content,
            'post_status' => $post_status,
            'post_author' => $post_author,
            'post_type' => $post_type,
        );

        // Add post date if determined from source
        if ($post_date && $post_date_gmt) {
            $post_data['post_date'] = $post_date;
            $post_data['post_date_gmt'] = $post_date_gmt;
        }

        // Insert the post
        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return [
                'success' => false,
                'error' => __('Failed to create local post:', 'data-machine') . ' ' . $post_id->get_error_message()
            ];
        }

        // Taxonomy Handling
        $assigned_category_id = null;
        $assigned_category_name = null;
        $assigned_tag_ids = [];
        $assigned_tag_names = [];
        $assigned_custom_taxonomies = [];

        // Category Assignment
        if ($category_id > 0) { // Manual selection
            $term = get_term($category_id, 'category');
            if ($term && !is_wp_error($term)) {
                wp_set_post_terms($post_id, array($category_id), 'category', false);
                $assigned_category_id = $category_id;
                $assigned_category_name = $term->name;
            }
        } elseif ($category_id === 'instruct_model' && !empty($parsed_data['category'])) { // Instruct Model
            $term = get_term_by('name', $parsed_data['category'], 'category');
            if ($term) {
                wp_set_post_terms($post_id, array($term->term_id), 'category', false);
                $assigned_category_id = $term->term_id;
                $assigned_category_name = $term->name;
            } else {
                // Create the category if it doesn't exist
                $term_info = wp_insert_term($parsed_data['category'], 'category');
                if (!is_wp_error($term_info) && isset($term_info['term_id'])) {
                    wp_set_post_terms($post_id, array($term_info['term_id']), 'category', false);
                    $assigned_category_id = $term_info['term_id'];
                    $assigned_category_name = $parsed_data['category'];
                }
            }
        }

        // Tag Assignment
        if ($tag_id > 0) { // Manual selection
            $term = get_term($tag_id, 'post_tag');
            if ($term && !is_wp_error($term)) {
                wp_set_post_terms($post_id, array($tag_id), 'post_tag', false);
                $assigned_tag_ids = array($tag_id);
                $assigned_tag_names = array($term->name);
            }
        } elseif ((is_string($tag_id) && ($tag_id === 'instruct_model')) && !empty($parsed_data['tags'])) { // Instruct Model
            $term_ids_to_assign = [];
            $term_names_to_assign = [];
            $first_tag_processed = false;
            
            foreach ($parsed_data['tags'] as $tag_name) {
                if (empty(trim($tag_name))) continue;

                // Enforce single tag for instruct_model
                if ($first_tag_processed && ($tag_id === 'instruct_model')) {
                    continue;
                }

                $term = get_term_by('name', $tag_name, 'post_tag');
                if ($term) {
                    $term_ids_to_assign[] = $term->term_id;
                    $term_names_to_assign[] = $term->name;
                } else {
                    // Create tag if it doesn't exist
                    $term_info = wp_insert_term($tag_name, 'post_tag');
                    if (!is_wp_error($term_info) && isset($term_info['term_id'])) {
                        $term_ids_to_assign[] = $term_info['term_id'];
                        $term_names_to_assign[] = $tag_name;
                    }
                }
                $first_tag_processed = true;
            }
            
            if (!empty($term_ids_to_assign)) {
                wp_set_post_terms($post_id, $term_ids_to_assign, 'post_tag', false);
                $assigned_tag_ids = $term_ids_to_assign;
                $assigned_tag_names = $term_names_to_assign;
            }
        }

        // Custom Taxonomy Assignment
        if (!empty($parsed_data['custom_taxonomies']) && is_array($parsed_data['custom_taxonomies'])) {
            foreach ($parsed_data['custom_taxonomies'] as $tax_slug => $term_names) {
                if (!taxonomy_exists($tax_slug)) {
                    continue;
                }

                // Determine if this custom taxonomy is set to 'instruct_model'
                $tax_mode = 'manual';
                if (isset($config["rest_" . $tax_slug])) {
                    $mode_check = $config["rest_" . $tax_slug];
                    if (is_string($mode_check) && ($mode_check === 'instruct_model')) {
                        $tax_mode = $mode_check;
                    }
                }

                $term_ids_to_assign = [];
                $term_names_assigned = [];
                $first_term_processed = false;

                foreach ($term_names as $term_name) {
                    if (empty(trim($term_name))) continue;

                    // Enforce single term for instruct_model
                    if ($first_term_processed && ($tax_mode === 'instruct_model')) {
                        continue;
                    }

                    $term = get_term_by('name', $term_name, $tax_slug);

                    if ($term) {
                        $term_ids_to_assign[] = $term->term_id;
                        $term_names_assigned[] = $term->name;
                    } else {
                        // Term does not exist - create it
                        $term_info = wp_insert_term($term_name, $tax_slug);
                        if (!is_wp_error($term_info) && isset($term_info['term_id'])) {
                            $term_ids_to_assign[] = $term_info['term_id'];
                            $term_names_assigned[] = $term_name;
                        }
                    }
                    $first_term_processed = true;
                }

                // Assign the collected/created terms for this taxonomy
                if (!empty($term_ids_to_assign)) {
                    wp_set_post_terms($post_id, $term_ids_to_assign, $tax_slug, true);
                    $assigned_custom_taxonomies[$tax_slug] = $term_names_assigned;
                }
            }
        }

        // Success
        return array(
            'success' => true,
            'status' => 'success',
            'message' => __('Post published locally successfully!', 'data-machine'),
            'local_post_id' => $post_id,
            'local_edit_link' => get_edit_post_link($post_id, 'raw'),
            'local_view_link' => get_permalink($post_id),
            'post_title' => $parsed_data['title'],
            'final_output' => $parsed_data['content'],
            'assigned_category_id' => $assigned_category_id,
            'assigned_category_name' => $assigned_category_name,
            'assigned_tag_ids' => $assigned_tag_ids,
            'assigned_tag_names' => $assigned_tag_names,
            'assigned_custom_taxonomies' => $assigned_custom_taxonomies,
        );
    }

    /**
     * Publish content to remote WordPress installation via Airdrop.
     *
     * @param array $structured_content Structured content from DataPacket.
     * @param array $config Configuration array.
     * @param array $input_metadata Input metadata.
     * @param array $module_job_config Module job configuration.
     * @return array Result array.
     */
    private function publish_remote(array $structured_content, array $config, array $input_metadata, array $module_job_config): array {
        // Initialize variables to avoid undefined variable warnings
        $assigned_category_name = null;
        $assigned_category_id = null;
        $assigned_tag_ids = [];
        $assigned_tag_names = [];
        $assigned_custom_taxonomies = [];

        // Get the selected remote location ID
        $location_id = absint($config['location_id'] ?? 0);

        if (empty($location_id)) {
            $error_message = __('No Remote Location selected for this module.', 'data-machine');
            $logger = apply_filters('dm_get_logger', null);
            $logger && $logger->error($error_message, ['config' => $config]);
            return [
                'success' => false,
                'error' => $error_message
            ];
        }

        // Get remote locations database service
        $all_databases = apply_filters('dm_get_database_services', []);
        $db_locations = $all_databases['remote_locations'] ?? null;
        if (!$db_locations) {
            return [
                'success' => false,
                'error' => __('Remote Locations database service not available.', 'data-machine')
            ];
        }

        // Fetch location details (using system access for admin-only architecture)
        $location = $db_locations->get_location($location_id, null, true, true);

        if (!$location || empty($location->target_site_url) || empty($location->target_username) || !isset($location->password)) {
            $error_message = __('Could not retrieve details for the selected Remote Location.', 'data-machine');
            $logger = apply_filters('dm_get_logger', null);
            $logger && $logger->error($error_message, ['location_id' => $location_id]);
            return [
                'success' => false,
                'error' => $error_message
            ];
        }
        
        if ($location->password === false) { // Check decryption failure
            $error_message = __('Failed to decrypt password for the selected Remote Location.', 'data-machine');
            $logger = apply_filters('dm_get_logger', null);
            $logger && $logger->error($error_message, ['location_id' => $location_id]);
            return [
                'success' => false,
                'error' => $error_message
            ];
        }

        // Use fetched credentials
        $remote_url = $location->target_site_url;
        $remote_user = $location->target_username;
        $remote_password = $location->password; // Decrypted password

        // Get publish settings from the config
        $post_type = $config['selected_remote_post_type'] ?? 'post';
        $post_status = $config['remote_post_status'] ?? 'publish';
        $category_id = $config['selected_remote_category_id'] ?? '';
        $tag_id = $config['selected_remote_tag_id'] ?? '';

        // Use structured content directly from DataPacket (no parsing needed)
        $parsed_data = [
            'title' => $structured_content['title'],
            'content' => $structured_content['body'],
            'category' => '', // Category will be determined by AI directives or config
            'tags' => is_array($structured_content['tags']) ? $structured_content['tags'] : [],
            'custom_taxonomies' => [] // Will be populated by AI directives
        ];

        // Prepare Content: Prepend Image, Append Source
        $final_content = $this->prepend_image_if_available($parsed_data['content'], $input_metadata);
        $final_content = $this->append_source_if_available($final_content, $input_metadata);

        // Create Gutenberg blocks directly from structured content
        $block_content = $this->create_gutenberg_blocks_from_content($final_content);

        // Determine Post Date
        $post_date_source = $config['post_date_source'] ?? 'current_date';
        $post_date_iso = null;

        if ($post_date_source === 'source_date' && !empty($input_metadata['original_date_gmt'])) {
            $source_date_gmt_string = $input_metadata['original_date_gmt'];
            $timestamp = strtotime($source_date_gmt_string);

            if ($timestamp !== false) {
                $post_date_iso = gmdate('Y-m-d\TH:i:s', $timestamp);
            } else {
                $logger = apply_filters('dm_get_logger', null);
                $logger && $logger->warning('Could not parse original_date_gmt from input metadata.', ['original_date_gmt' => $source_date_gmt_string, 'metadata' => $input_metadata]);
            }
        }

        // Prepare data payload for the remote API
        $payload = array(
            'title' => $parsed_data['title'] ?: __('Untitled Airdropped Post', 'data-machine'),
            'content' => $block_content,
            'post_type' => $post_type,
            'status' => $post_status,
            // Initialize Taxonomy Keys
            'category_id' => null,
            'category_name' => null,
            'rest_category' => null,
            'tag_ids' => [],
            'tag_names' => [],
            'rest_post_tag' => null,
            'custom_taxonomies' => [],
        );

        // Add module_id for tracking
        if (!empty($module_job_config['module_id'])) {
            $payload['dm_module_id'] = intval($module_job_config['module_id']);
        }

        // Add date to payload if determined from source
        if ($post_date_iso) {
            $payload['date_gmt'] = $post_date_iso;
            $payload['date'] = $post_date_iso;
        }

        // Fetch remote site taxonomies for validation
        $remote_cats = [];
        $remote_tags = [];
        $site_supports_categories = false;
        $site_supports_tags = false;

        if (!empty($location_id)) {
            $location_info = $db_locations->get_location($location_id, null, false, true);
            if ($location_info && !empty($location_info->synced_site_info)) {
                $site_info = json_decode($location_info->synced_site_info, true);
                $remote_cats = $site_info['taxonomies']['category']['terms'] ?? [];
                $remote_tags = $site_info['taxonomies']['post_tag']['terms'] ?? [];
                $site_supports_categories = isset($site_info['taxonomies']['category']);
                $site_supports_tags = isset($site_info['taxonomies']['post_tag']);
            }
        }

        // Validate taxonomy usage against remote site capabilities
        if (!empty($category_id) && !$site_supports_categories) {
            $logger = apply_filters('dm_get_logger', null);
            $logger && $logger->warning('Remote site does not support categories - skipping category assignment', [
                'location_id' => $location_id,
                'category_id' => $category_id
            ]);
            $category_id = '';
        }

        if (!empty($tag_id) && !$site_supports_tags) {
            $logger = apply_filters('dm_get_logger', null);
            $logger && $logger->warning('Remote site does not support tags - skipping tag assignment', [
                'location_id' => $location_id,
                'tag_id' => $tag_id
            ]);
            $tag_id = '';
        }

        // Category Logic
        if (is_string($category_id) && ($category_id === 'instruct_model')) {
            if (!empty($parsed_data['category'])) {
                $payload['category_name'] = $parsed_data['category'];
                $assigned_category_name = $parsed_data['category'];
            }
            if ($category_id === 'instruct_model') {
                $payload['rest_category'] = 'instruct_model';
            }
            $assigned_category_id = null;
        } elseif (is_numeric($category_id) && $category_id > 0) {
            $payload['category_id'] = $category_id;
            foreach ($remote_cats as $cat) {
                if ($cat['term_id'] == $category_id) {
                    $assigned_category_name = $cat['name'];
                    break;
                }
            }
            $assigned_category_id = $category_id;
        }

        // Tag Logic
        if (is_string($tag_id) && ($tag_id === 'instruct_model')) {
            if (!empty($parsed_data['tags'])) {
                $first_tag_name = trim($parsed_data['tags'][0]);
                if (!empty($first_tag_name)) {
                    $payload['tag_names'] = [$first_tag_name];
                    $assigned_tag_names = [$first_tag_name];
                    if (count($parsed_data['tags']) > 1) {
                        $logger = apply_filters('dm_get_logger', null);
                        $logger && $logger->debug("Remote Publish: Instruct mode - Sending only first tag '{$first_tag_name}'. AI provided: " . implode(', ', $parsed_data['tags']), ['location_id' => $location_id]);
                    }
                } else {
                    $assigned_tag_names = [];
                }
            } else {
                $assigned_tag_names = [];
            }
            if ($tag_id === 'instruct_model') {
                $payload['rest_post_tag'] = 'instruct_model';
            }
            $assigned_tag_ids = [];
        } elseif (is_numeric($tag_id) && $tag_id > 0) {
            $payload['tag_ids'] = [$tag_id];
            foreach ($remote_tags as $tag) {
                if ($tag['term_id'] == $tag_id) {
                    $assigned_tag_names = [$tag['name']];
                    break;
                }
            }
            $assigned_tag_ids = [$tag_id];
        }

        // Custom Taxonomy Logic (simplified for remote)
        if (!empty($parsed_data['custom_taxonomies']) && is_array($parsed_data['custom_taxonomies'])) {
            foreach ($parsed_data['custom_taxonomies'] as $tax_slug => $term_names) {
                if (isset($config["rest_" . $tax_slug]) && $config["rest_" . $tax_slug] === 'instruct_model') {
                    $first_term = trim($term_names[0] ?? '');
                    if (!empty($first_term)) {
                        $payload['custom_taxonomies'][$tax_slug] = [$first_term];
                        $assigned_custom_taxonomies[$tax_slug] = [$first_term];
                    }
                }
            }
        }

        // Construct the API endpoint URL
        $api_url = trailingslashit($remote_url) . 'wp-json/airdrop/v1/receive';

        // Set up authentication
        $auth_header = 'Basic ' . base64_encode($remote_user . ':' . $remote_password);
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => $auth_header,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($payload),
            'timeout' => 30,
        );

        // Make the API request
        $response = wp_remote_request($api_url, $args);

        if (is_wp_error($response)) {
            $error_message = __('Failed to send data to remote WordPress site: ', 'data-machine') . $response->get_error_message();
            $logger = apply_filters('dm_get_logger', null);
            $logger && $logger->error($error_message, ['location_id' => $location_id, 'payload' => $payload]);
            return [
                'success' => false,
                'error' => $error_message
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code < 200 || $response_code >= 300) {
            $error_message = __('Remote WordPress site returned an error: ', 'data-machine') . $response_code . ' - ' . $response_body;
            $logger = apply_filters('dm_get_logger', null);
            $logger && $logger->error($error_message, ['location_id' => $location_id, 'response_code' => $response_code, 'response_body' => $response_body]);
            return [
                'success' => false,
                'error' => $error_message
            ];
        }

        // Parse the response
        $response_data = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = __('Failed to parse response from remote WordPress site.', 'data-machine');
            $logger = apply_filters('dm_get_logger', null);
            $logger && $logger->error($error_message, ['location_id' => $location_id, 'response_body' => $response_body]);
            return [
                'success' => false,
                'error' => $error_message
            ];
        }

        // Check if the remote operation was successful
        if (empty($response_data['success']) || $response_data['success'] !== true) {
            $error_message = $response_data['error'] ?? __('Unknown error from remote WordPress site.', 'data-machine');
            $logger = apply_filters('dm_get_logger', null);
            $logger && $logger->error($error_message, ['location_id' => $location_id, 'response_data' => $response_data]);
            return [
                'success' => false,
                'error' => $error_message
            ];
        }

        // Success - return detailed information
        return array(
            'success' => true,
            'status' => 'success',
            'message' => __('Post published to remote WordPress site successfully!', 'data-machine'),
            'remote_post_id' => $response_data['post_id'] ?? null,
            'remote_edit_link' => $response_data['edit_link'] ?? null,
            'remote_view_link' => $response_data['view_link'] ?? null,
            'post_title' => $parsed_data['title'],
            'final_output' => $parsed_data['content'],
            'assigned_category_id' => $assigned_category_id,
            'assigned_category_name' => $assigned_category_name,
            'assigned_tag_ids' => $assigned_tag_ids,
            'assigned_tag_names' => $assigned_tag_names,
            'assigned_custom_taxonomies' => $assigned_custom_taxonomies,
            'remote_response' => $response_data,
        );
    }


    /**
     * Get settings fields specific to local WordPress publishing.
     *
     * @return array Settings fields.
     */
    private static function get_local_fields(): array {
        // Get available post types
        $post_type_options = [];
        $post_types = get_post_types(array('public' => true), 'objects');
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
            'instruct_model' => '-- Instruct Model --'
        ];
        $local_categories = get_terms(array('taxonomy' => 'category', 'hide_empty' => false));
        if (!is_wp_error($local_categories)) {
            foreach ($local_categories as $cat) {
                $category_options[$cat->term_id] = $cat->name;
            }
        }

        // Get available tags
        $tag_options = [
            'instruct_model' => '-- Instruct Model --'
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
            ],
            'post_status' => [
                'type' => 'select',
                'label' => __('Post Status', 'data-machine'),
                'description' => __('Select the status for the newly created post.', 'data-machine'),
                'options' => [
                    'draft' => __('Draft', 'data-machine'),
                    'publish' => __('Publish', 'data-machine'),
                    'pending' => __('Pending Review', 'data-machine'),
                    'private' => __('Private', 'data-machine'),
                ],
            ],
            'selected_local_category_id' => [
                'type' => 'select',
                'label' => __('Category', 'data-machine'),
                'description' => __('Select a category, let the AI choose, or instruct the AI using your prompt.', 'data-machine'),
                'options' => $category_options,
            ],
            'selected_local_tag_id' => [
                'type' => 'select',
                'label' => __('Tag', 'data-machine'),
                'description' => __('Select a single tag, let the AI choose, or instruct the AI using your prompt.', 'data-machine'),
                'options' => $tag_options,
            ],
        ];
    }

    /**
     * Get settings fields specific to remote WordPress publishing.
     *
     * @param array $current_config Current configuration.
     * @return array Settings fields.
     */
    private static function get_remote_fields(array $current_config = []): array {
        // Get remote locations service via filter system
        $db_remote_locations = $all_databases['remote_locations'] ?? null;
        if (!$db_remote_locations) {
            throw new \Exception(esc_html__('Remote locations service not available. This indicates a core filter registration issue.', 'data-machine'));
        }
        $locations = $db_remote_locations->get_locations_for_current_user();

        $options = [0 => __('Select a Remote Location', 'data-machine')];
        foreach ($locations as $loc) {
            $options[$loc->location_id] = $loc->location_name . ' (' . $loc->target_site_url . ')';
        }

        $remote_post_types = ['post' => 'Posts', 'page' => 'Pages'];
        $remote_categories = ['instruct_model' => '-- Instruct Model --'];
        $remote_tags = ['instruct_model' => '-- Instruct Model --'];

        return [
            'location_id' => [
                'type' => 'select',
                'label' => __('Remote Location', 'data-machine'),
                'description' => __('Select the pre-configured remote WordPress site to publish to.', 'data-machine'),
                'options' => $options,
            ],
            'selected_remote_post_type' => [
                'type' => 'select',
                'label' => __('Post Type', 'data-machine'),
                'description' => __('Select the post type for the remote site.', 'data-machine'),
                'options' => $remote_post_types,
            ],
            'remote_post_status' => [
                'type' => 'select',
                'label' => __('Post Status', 'data-machine'),
                'description' => __('Select the status for the newly created post on the remote site.', 'data-machine'),
                'options' => [
                    'draft' => __('Draft', 'data-machine'),
                    'publish' => __('Publish', 'data-machine'),
                    'pending' => __('Pending Review', 'data-machine'),
                    'private' => __('Private', 'data-machine'),
                ],
            ],
            'selected_remote_category_id' => [
                'type' => 'select',
                'label' => __('Category', 'data-machine'),
                'description' => __('Select a category or let the AI choose based on your prompt.', 'data-machine'),
                'options' => $remote_categories,
            ],
            'selected_remote_tag_id' => [
                'type' => 'select',
                'label' => __('Tag', 'data-machine'),
                'description' => __('Select a tag or let the AI choose based on your prompt.', 'data-machine'),
                'options' => $remote_tags,
            ],
        ];
    }

    /**
     * Get common settings fields for all destination types.
     *
     * @return array Settings fields.
     */
    private static function get_common_fields(): array {
        return [
            'post_date_source' => [
                'type' => 'select',
                'label' => __('Post Date Setting', 'data-machine'),
                'description' => __('Choose whether to use the original date from the source (if available) or the current date when publishing.', 'data-machine'),
                'options' => [
                    'current_date' => __('Use Current Date', 'data-machine'),
                    'source_date' => __('Use Source Date (if available)', 'data-machine'),
                ],
            ],
        ];
    }

    /**
     * Sanitize settings for the unified WordPress output handler.
     *
     * @param array $raw_settings Raw settings array.
     * @return array Sanitized settings.
     */
    public function sanitize_settings(array $raw_settings): array {
        $sanitized = [];

        // Destination type is required
        $sanitized['destination_type'] = sanitize_text_field($raw_settings['destination_type'] ?? 'local');
        if (!in_array($sanitized['destination_type'], ['local', 'remote'])) {
            throw new InvalidArgumentException(esc_html__('Invalid destination type specified for WordPress handler.', 'data-machine'));
        }

        // Sanitize based on destination type
        switch ($sanitized['destination_type']) {
            case 'local':
                $sanitized = array_merge($sanitized, $this->sanitize_local_settings($raw_settings));
                break;

            case 'remote':
                $sanitized = array_merge($sanitized, $this->sanitize_remote_settings($raw_settings));
                break;
        }

        // Sanitize common fields
        $valid_date_sources = ['current_date', 'source_date'];
        $date_source = sanitize_text_field($raw_settings['post_date_source'] ?? 'current_date');
        if (!in_array($date_source, $valid_date_sources)) {
            throw new Exception(esc_html__('Invalid post date source parameter provided in settings.', 'data-machine'));
        }
        $sanitized['post_date_source'] = $date_source;

        return $sanitized;
    }

    /**
     * Sanitize local WordPress settings.
     *
     * @param array $raw_settings Raw settings array.
     * @return array Sanitized settings.
     */
    private function sanitize_local_settings(array $raw_settings): array {
        $sanitized = [
            'post_type' => sanitize_text_field($raw_settings['post_type'] ?? 'post'),
            'post_status' => sanitize_text_field($raw_settings['post_status'] ?? 'draft'),
        ];

        // Sanitize Category ID/Mode
        $cat_val = $raw_settings['selected_local_category_id'] ?? 'model_decides';
        if ($cat_val === 'model_decides' || $cat_val === 'instruct_model') {
            $sanitized['selected_local_category_id'] = $cat_val;
        } else {
            $sanitized['selected_local_category_id'] = intval($cat_val);
        }

        // Sanitize Tag ID/Mode
        $tag_val = $raw_settings['selected_local_tag_id'] ?? 'model_decides';
        if ($tag_val === 'model_decides' || $tag_val === 'instruct_model') {
            $sanitized['selected_local_tag_id'] = $tag_val;
        } else {
            $sanitized['selected_local_tag_id'] = intval($tag_val);
        }

        // Sanitize custom taxonomy fields (fields starting with 'rest_')
        foreach ($raw_settings as $key => $value) {
            if (strpos($key, 'rest_') === 0) {
                $tax_slug = substr($key, 5);
                if (taxonomy_exists($tax_slug)) {
                    $sanitized_value = sanitize_text_field($value);
                    if ($sanitized_value === 'instruct_model' || $sanitized_value === '') {
                        $sanitized[$key] = $sanitized_value;
                    } else {
                        $sanitized[$key] = intval($sanitized_value);
                    }
                }
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize remote WordPress settings.
     *
     * @param array $raw_settings Raw settings array.
     * @return array Sanitized settings.
     * @throws InvalidArgumentException If location ID is missing.
     */
    private function sanitize_remote_settings(array $raw_settings): array {
        $location_id = absint($raw_settings['location_id'] ?? 0);
        if (empty($location_id)) {
            throw new InvalidArgumentException(esc_html__('Remote Location is required for remote destination type.', 'data-machine'));
        }

        $sanitized = [
            'location_id' => $location_id,
            'selected_remote_post_type' => sanitize_text_field($raw_settings['selected_remote_post_type'] ?? 'post'),
            'remote_post_status' => sanitize_text_field($raw_settings['remote_post_status'] ?? 'draft'),
        ];

        // Sanitize Remote Category ID/Mode
        $cat_val = $raw_settings['selected_remote_category_id'] ?? 'instruct_model';
        if ($cat_val === 'instruct_model' || $cat_val === '') {
            $sanitized['selected_remote_category_id'] = $cat_val;
        } else {
            $sanitized['selected_remote_category_id'] = intval($cat_val);
        }

        // Sanitize Remote Tag ID/Mode
        $tag_val = $raw_settings['selected_remote_tag_id'] ?? 'instruct_model';
        if ($tag_val === 'instruct_model' || $tag_val === '') {
            $sanitized['selected_remote_tag_id'] = $tag_val;
        } else {
            $sanitized['selected_remote_tag_id'] = intval($tag_val);
        }

        // Sanitize custom taxonomy fields for remote
        foreach ($raw_settings as $key => $value) {
            if (strpos($key, 'rest_') === 0) {
                $sanitized_value = sanitize_text_field($value);
                if ($sanitized_value === 'instruct_model' || $sanitized_value === '') {
                    $sanitized[$key] = $sanitized_value;
                } else {
                    $sanitized[$key] = intval($sanitized_value);
                }
            }
        }

        return $sanitized;
    }

    /**
     * Prepend image to content if available in metadata.
     *
     * @param string $content Original content.
     * @param array $input_metadata Input metadata containing image information.
     * @return string Content with prepended image if available.
     */
    private function prepend_image_if_available(string $content, array $input_metadata): string {
        if (!empty($input_metadata['image_source_url'])) {
            $image_url = esc_url($input_metadata['image_source_url']);
            $content = "![Image]({$image_url})\n\n" . $content;
        }
        return $content;
    }

    /**
     * Append source information to content if available in metadata.
     *
     * @param string $content Original content.
     * @param array $input_metadata Input metadata containing source information.
     * @return string Content with appended source if available.
     */
    private function append_source_if_available(string $content, array $input_metadata): string {
        if (!empty($input_metadata['source_url'])) {
            $source_url = esc_url($input_metadata['source_url']);
            $content .= "\n\n---\n\n" . sprintf(
                /* translators: %s: source URL */
                __('Source: %s', 'data-machine'),
                "[{$source_url}]({$source_url})"
            );
        }
        return $content;
    }

    /**
     * Get the user-friendly label for this handler.
     *
     * @return string Handler label.
     */
    public static function get_label(): string {
        return __('WordPress', 'data-machine');
    }

    /**
     * Create Gutenberg blocks from structured content.
     * 
     * Converts structured content to proper Gutenberg blocks using WordPress native functions.
     * Uses serialize_blocks() to ensure proper block format and compatibility.
     *
     * @param string $content The content to convert to blocks.
     * @return string Properly formatted Gutenberg block content.
     */
    private function create_gutenberg_blocks_from_content(string $content): string {
        if (empty($content)) {
            return '';
        }

        // Sanitize HTML content using WordPress KSES
        $sanitized_html = wp_kses_post($content);

        // Check if content already contains blocks - if so, return as-is
        if (has_blocks($sanitized_html)) {
            return $sanitized_html;
        }

        // Convert HTML to WordPress block structure
        $blocks = $this->convert_html_to_blocks($sanitized_html);
        
        // Use WordPress native serialize_blocks() to convert block array to proper HTML
        return serialize_blocks($blocks);
    }

    /**
     * Convert HTML content to WordPress block structure.
     * 
     * Creates proper block arrays that WordPress can serialize correctly.
     *
     * @param string $html_content The HTML content to convert.
     * @return array Array of WordPress block structures.
     */
    private function convert_html_to_blocks(string $html_content): array {
        $blocks = [];
        
        // Split content by double line breaks to identify separate content blocks
        $paragraphs = preg_split('/\n\s*\n/', trim($html_content));
        
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) {
                continue;
            }

            // Check for heading tags
            if (preg_match('/^<h([1-6])[^>]*>(.*?)<\/h[1-6]>$/is', $paragraph, $matches)) {
                $level = (int) $matches[1];
                $content_text = trim($matches[2]);
                
                $blocks[] = [
                    'blockName' => 'core/heading',
                    'attrs' => [
                        'level' => $level
                    ],
                    'innerBlocks' => [],
                    'innerHTML' => sprintf('<h%d>%s</h%d>', $level, $content_text, $level),
                    'innerContent' => [sprintf('<h%d>%s</h%d>', $level, $content_text, $level)]
                ];
                
            } elseif (preg_match('/^<p[^>]*>(.*?)<\/p>$/is', $paragraph, $matches)) {
                $content_text = trim($matches[1]);
                
                $blocks[] = [
                    'blockName' => 'core/paragraph',
                    'attrs' => [],
                    'innerBlocks' => [],
                    'innerHTML' => sprintf('<p>%s</p>', $content_text),
                    'innerContent' => [sprintf('<p>%s</p>', $content_text)]
                ];
                
            } else {
                // Wrap other content in paragraph blocks
                $blocks[] = [
                    'blockName' => 'core/paragraph',
                    'attrs' => [],
                    'innerBlocks' => [],
                    'innerHTML' => sprintf('<p>%s</p>', $paragraph),
                    'innerContent' => [sprintf('<p>%s</p>', $paragraph)]
                ];
            }
        }
        
        return $blocks;
    }
}


