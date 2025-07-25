<?php
/**
 * Handles local WordPress posts as a data source.
 *
 * Fetches posts from the current WordPress site using WP_Query,
 * allowing filtering by post type, category, tags, status, and date ranges.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/input
 * @since      0.1.0
 */

namespace DataMachine\Handlers\Input;

use DataMachine\Database\{Modules, Projects};
use DataMachine\Engine\ProcessedItemsManager;
use DataMachine\Helpers\Logger;
use Exception;
use InvalidArgumentException;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class LocalWordPress extends BaseInputHandler {

	/**
	 * Fetches and prepares local WordPress posts into a standardized format.
	 *
	 * @param object $module The full module object containing configuration and context.
	 * @param array  $source_config Decoded data_source_config for the specific module run.
	 * @param int    $user_id The ID of the user context.
	 * @return array The standardized input data packet.
	 * @throws Exception If input data is invalid or cannot be retrieved.
	 */
	public function get_input_data(object $module, array $source_config, int $user_id): array {
		// Get module ID from the module object
		$module_id = isset($module->module_id) ? absint($module->module_id) : 0;
		
		// Use the passed $user_id for validation
		if (empty($module_id) || empty($user_id)) {
			throw new Exception(esc_html__('Missing module ID or user ID.', 'data-machine'));
		}

		// Get dependencies
		$db_modules = $this->db_modules;
		$db_projects = $this->db_projects;
		if (!$this->processed_items_manager || !$db_modules || !$db_projects) {
			throw new Exception(esc_html__('Required database service not available.', 'data-machine'));
		}

		// Need to check ownership via project
		if (!$module || !isset($module->project_id)) {
			throw new Exception(esc_html__('Invalid module or project association missing.', 'data-machine'));
		}
		$project = $db_projects->get_project($module->project_id, $user_id);
		if (!$project) {
			throw new Exception(esc_html__('Permission denied for this module.', 'data-machine'));
		}

		// --- Configuration ---
		// Access config from nested structure
		$config = $source_config['local_wordpress'] ?? [];
		$post_type = sanitize_text_field($config['post_type'] ?? 'post');
		$post_status = sanitize_text_field($config['post_status'] ?? 'publish');
		$category_id = absint($config['category_id'] ?? 0);
		$tag_id = absint($config['tag_id'] ?? 0);
		$orderby = sanitize_text_field($config['orderby'] ?? 'date');
		$order = sanitize_text_field($config['order'] ?? 'DESC');
		$search = sanitize_text_field($config['search'] ?? '');
		$process_limit = max(1, absint($config['item_count'] ?? 1));
		$timeframe_limit = sanitize_text_field($config['timeframe_limit'] ?? 'all_time');
		
		// Calculate date query parameters
		$date_query = [];
		if ($timeframe_limit !== 'all_time') {
			$interval_map = [
				'24_hours' => '-24 hours',
				'72_hours' => '-72 hours',
				'7_days'   => '-7 days',
				'30_days'  => '-30 days'
			];
			if (isset($interval_map[$timeframe_limit])) {
				$cutoff_timestamp = strtotime($interval_map[$timeframe_limit], current_time('timestamp', true));
				$date_query = [
					[
						'after' => date('Y-m-d H:i:s', $cutoff_timestamp),
						'inclusive' => true,
					]
				];
			}
		}
		// --- End Configuration ---

		// Build WP_Query arguments
		$query_args = [
			'post_type' => $post_type,
			'post_status' => $post_status,
			'posts_per_page' => $process_limit * 2, // Fetch more to account for already processed items
			'orderby' => $orderby,
			'order' => $order,
			'no_found_rows' => true, // Performance optimization
			'update_post_meta_cache' => false, // Performance optimization
			'update_post_term_cache' => false, // Performance optimization
		];

		// Add category filter if specified
		if ($category_id > 0) {
			$query_args['cat'] = $category_id;
		}

		// Add tag filter if specified
		if ($tag_id > 0) {
			$query_args['tag_id'] = $tag_id;
		}

		// Add search term if specified
		if (!empty($search)) {
			$query_args['s'] = $search;
		}

		// Add date query if specified
		if (!empty($date_query)) {
			$query_args['date_query'] = $date_query;
		}

		// Execute query
		$wp_query = new WP_Query($query_args);
		$posts = $wp_query->posts;

		if (empty($posts)) {
			throw new Exception(__('No posts found matching the criteria.', 'data-machine'));
		}

		// Find first unprocessed post
		$eligible_items_packets = [];
		foreach ($posts as $post) {
			if (count($eligible_items_packets) >= $process_limit) {
				break;
			}

			$post_id = $post->ID;
			if ($this->check_if_processed($module_id, 'local_wordpress', $post_id)) {
				continue;
			}

			$title = $post->post_title ?: 'N/A';
			$content = $post->post_content ?: '';
			$source_link = get_permalink($post_id);
			$featured_image_id = get_post_thumbnail_id($post_id);
			$image_url = $featured_image_id ? wp_get_attachment_image_url($featured_image_id, 'full') : null;

			// --- Fallback: Try to get the first image from content if no featured image ---
			if (empty($image_url) && !empty($content)) {
				if (preg_match('/<img.*?src=[\'"](.*?)[\'"].*?>/i', $content, $matches)) {
					$first_image_src = $matches[1];
					// Basic validation - check if it looks like a URL
					if (filter_var($first_image_src, FILTER_VALIDATE_URL)) {
						$image_url = $first_image_src;
						$this->logger?->debug('Local WordPress Input: Using first image from content as fallback.', ['found_url' => $image_url, 'post_id' => $post_id]);
					}
				}
			}
			// --- End Fallback ---

			// Extract source name
			$site_name = get_bloginfo('name');
			$source_name = $site_name ?: 'Local WordPress';
			$content_string = "Source: " . $source_name . "\n\nTitle: " . $title . "\n\n" . $content;
			
			$input_data_packet = [
				'data' => [
					'content_string' => $content_string,
					'file_info' => null
				],
				'metadata' => [
					'source_type' => 'local_wordpress',
					'item_identifier_to_log' => $post_id,
					'original_id' => $post_id,
					'source_url' => $source_link,
					'original_title' => $title,
					'image_source_url' => $image_url,
					'original_date_gmt' => $post->post_date_gmt
				]
			];
			
			$eligible_items_packets[] = $input_data_packet;
		}

		if (empty($eligible_items_packets)) {
			throw new Exception(__('No new posts found matching the criteria.', 'data-machine'));
		}

		// Return only the first item for "one coin, one operation" model
		return $eligible_items_packets[0];
	}

	/**
	 * Get settings fields specific to the Local WordPress handler.
	 *
	 * @param array $current_config The current configuration values for the module.
	 * @return array An array defining the settings fields for this input handler.
	 */
	public static function get_settings_fields(array $current_config = []): array {
		// Get available post types
		$post_types = get_post_types(['public' => true], 'objects');
		$post_type_options = [];
		foreach ($post_types as $post_type) {
			$post_type_options[$post_type->name] = $post_type->label;
		}

		// Get categories
		$categories = get_categories(['hide_empty' => false]);
		$category_options = [0 => __('All Categories', 'data-machine')];
		foreach ($categories as $category) {
			$category_options[$category->term_id] = $category->name;
		}

		// Get tags
		$tags = get_tags(['hide_empty' => false]);
		$tag_options = [0 => __('All Tags', 'data-machine')];
		foreach ($tags as $tag) {
			$tag_options[$tag->term_id] = $tag->name;
		}

		return [
			'post_type' => [
				'type' => 'select',
				'label' => __('Post Type', 'data-machine'),
				'description' => __('Select the post type to fetch from the local site.', 'data-machine'),
				'options' => $post_type_options,
				'default' => 'post',
			],
			'post_status' => [
				'type' => 'select',
				'label' => __('Post Status', 'data-machine'),
				'description' => __('Select the post status to fetch.', 'data-machine'),
				'options' => [
					'publish' => __('Published', 'data-machine'),
					'draft' => __('Draft', 'data-machine'),
					'pending' => __('Pending', 'data-machine'),
					'private' => __('Private', 'data-machine'),
					'any' => __('Any', 'data-machine'),
				],
				'default' => 'publish',
			],
			'category_id' => [
				'type' => 'select',
				'label' => __('Category', 'data-machine'),
				'description' => __('Optional: Filter by a specific category.', 'data-machine'),
				'options' => $category_options,
				'default' => 0,
			],
			'tag_id' => [
				'type' => 'select',
				'label' => __('Tag', 'data-machine'),
				'description' => __('Optional: Filter by a specific tag.', 'data-machine'),
				'options' => $tag_options,
				'default' => 0,
			],
			'orderby' => [
				'type' => 'select',
				'label' => __('Order By', 'data-machine'),
				'description' => __('Select the field to order results by.', 'data-machine'),
				'options' => [
					'date' => __('Date', 'data-machine'),
					'modified' => __('Modified Date', 'data-machine'),
					'title' => __('Title', 'data-machine'),
					'ID' => __('ID', 'data-machine'),
				],
				'default' => 'date',
			],
			'order' => [
				'type' => 'select',
				'label' => __('Order', 'data-machine'),
				'description' => __('Select the order direction.', 'data-machine'),
				'options' => [
					'DESC' => __('Descending', 'data-machine'),
					'ASC' => __('Ascending', 'data-machine'),
				],
				'default' => 'DESC',
			],
			'item_count' => [
				'type' => 'number',
				'label' => __('Items to Process', 'data-machine'),
				'description' => __('Maximum number of *new* items to process per run.', 'data-machine'),
				'default' => 1,
				'min' => 1,
				'max' => 100,
			],
			'timeframe_limit' => [
				'type' => 'select',
				'label' => __('Process Items Within', 'data-machine'),
				'description' => __('Only consider items published within this timeframe.', 'data-machine'),
				'options' => [
					'all_time' => __('All Time', 'data-machine'),
					'24_hours' => __('Last 24 Hours', 'data-machine'),
					'72_hours' => __('Last 72 Hours', 'data-machine'),
					'7_days'   => __('Last 7 Days', 'data-machine'),
					'30_days'  => __('Last 30 Days', 'data-machine'),
				],
				'default' => 'all_time',
			],
			'search' => [
				'type' => 'text',
				'label' => __('Search Term Filter', 'data-machine'),
				'description' => __('Optional: Filter posts using a search term.', 'data-machine'),
				'default' => '',
			],
		];
	}

	/**
	 * Sanitize settings for the Local WordPress input handler.
	 *
	 * @param array $raw_settings
	 * @return array
	 */
	public function sanitize_settings(array $raw_settings): array {
		$sanitized = [];
		
		$sanitized['post_type'] = sanitize_text_field($raw_settings['post_type'] ?? 'post');
		$sanitized['post_status'] = sanitize_text_field($raw_settings['post_status'] ?? 'publish');
		$sanitized['category_id'] = absint($raw_settings['category_id'] ?? 0);
		$sanitized['tag_id'] = absint($raw_settings['tag_id'] ?? 0);
		$sanitized['orderby'] = sanitize_text_field($raw_settings['orderby'] ?? 'date');
		$sanitized['order'] = sanitize_text_field($raw_settings['order'] ?? 'DESC');
		$sanitized['item_count'] = max(1, absint($raw_settings['item_count'] ?? 1));
		$sanitized['timeframe_limit'] = sanitize_text_field($raw_settings['timeframe_limit'] ?? 'all_time');
		$sanitized['search'] = sanitize_text_field($raw_settings['search'] ?? '');

		return $sanitized;
	}


	/**
	 * Get the user-friendly label for this handler.
	 *
	 * @return string
	 */
	public static function get_label(): string {
		return __('Local WordPress', 'data-machine');
	}
}