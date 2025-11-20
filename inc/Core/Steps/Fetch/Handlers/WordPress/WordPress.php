<?php
/**
 * WordPress local content fetch handler with timeframe and keyword filtering.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Fetch\Handlers\WordPress
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\WordPress;

use DataMachine\Core\Steps\Fetch\Handlers\FetchHandler;
use DataMachine\Core\Steps\HandlerRegistrationTrait;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WordPress extends FetchHandler {

	use HandlerRegistrationTrait;

	public function __construct() {
		parent::__construct( 'wordpress_local' );

		// Self-register with filters
		self::registerHandler(
			'wordpress_posts',
			'fetch',
			self::class,
			__('Local WordPress Posts', 'datamachine'),
			__('Fetch posts and pages from this WordPress installation', 'datamachine'),
			false,
			null,
			WordPressSettings::class,
			null
		);
	}

	/**
	 * Fetch WordPress posts with timeframe and keyword filtering.
	 * Engine data (source_url, image_file_path) stored via datamachine_engine_data filter.
	 */
	protected function executeFetch(
		int $pipeline_id,
		array $config,
		?string $flow_step_id,
		int $flow_id,
		?string $job_id
	): array {
		if (empty($pipeline_id)) {
			$this->log('error', 'Missing pipeline ID.', ['pipeline_id' => $pipeline_id]);
			return [];
		}

		if ($flow_step_id === null) {
			$this->log('debug', 'WordPress fetch called without flow_step_id - processed items tracking disabled');
		}

		$user_id = get_current_user_id();
		return $this->fetch_local_data($pipeline_id, $config, $user_id, $flow_step_id, $job_id);
	}

    /**
     * Fetch WordPress content with convergence pattern for URL-specific and query-based access.
     */
    private function fetch_local_data(int $pipeline_id, array $config, int $user_id, ?string $flow_step_id = null, ?string $job_id = null): array {
        $source_url = sanitize_url($config['source_url'] ?? '');

        // URL-specific access
        if (!empty($source_url)) {
            $post_id = url_to_postid($source_url);
            if ($post_id > 0) {
                return $this->process_single_post($post_id, $flow_step_id, $job_id);
            } else {
                $this->log('warning', 'Could not extract post ID from URL', [
                    'source_url' => $source_url
                ]);
                return [];
            }
        }
        $post_type = sanitize_text_field($config['post_type'] ?? 'post');
        $post_status = sanitize_text_field($config['post_status'] ?? 'publish');

        $randomize = !empty($config['randomize_selection']);
        $orderby = $randomize ? 'rand' : 'modified';
        $order = $randomize ? 'ASC' : 'DESC';

        $timeframe_limit = $config['timeframe_limit'] ?? 'all_time';
        $search = trim($config['search'] ?? '');
        $date_query = [];

        // Build date query from timeframe using base class helper
        $cutoff_timestamp = apply_filters('datamachine_timeframe_limit', null, $timeframe_limit);
        if ($cutoff_timestamp !== null) {
            $date_query = [
                [
                    'after' => gmdate('Y-m-d H:i:s', $cutoff_timestamp),
                    'inclusive' => true,
                ]
            ];
        }

        $query_args = [
            'post_type' => $post_type,
            'post_status' => $post_status,
            'posts_per_page' => 10,
            'orderby' => $orderby,
            'order' => $order,
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];

        $tax_query = [];
        foreach ($config as $field_key => $field_value) {
            if (strpos($field_key, 'taxonomy_') === 0 && strpos($field_key, '_filter') !== false) {
                $term_id = intval($field_value);
                if ($term_id > 0) {
                    $taxonomy_slug = str_replace(['taxonomy_', '_filter'], '', $field_key);
                    $tax_query[] = [
                        'taxonomy' => $taxonomy_slug,
                        'field'    => 'term_id',
                        'terms'    => [$term_id],
                    ];
                }
            }
        }
        if (!empty($tax_query)) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            $query_args['tax_query'] = $tax_query;
        }

        $use_client_side_search = false;
        if (!empty($search)) {
            if (strpos($search, ',') !== false) {
                $use_client_side_search = true;
            } else {
                $query_args['s'] = $search;
            }
        }

        if (!empty($date_query)) {
            $query_args['date_query'] = $date_query;
        }

        $wp_query = new WP_Query($query_args);
        $posts = $wp_query->posts;

        if (empty($posts)) {
            return [];
        }
        foreach ($posts as $post) {
            $post_id = $post->ID;
            if ($this->isItemProcessed((string) $post_id, $flow_step_id)) {
                continue;
            }

            if ($use_client_side_search && !empty($search)) {
                $search_text = $post->post_title . ' ' . wp_strip_all_tags($post->post_content . ' ' . $post->post_excerpt);
                if (!$this->applyKeywordSearch($search_text, $search)) {
                    continue;
                }
            }

            return $this->process_single_post($post_id, $flow_step_id, $job_id);
        }
        return [];
    }


    /**
     * Process single post with engine data storage via datamachine_engine_data filter.
     */
    private function process_single_post(int $post_id, ?string $flow_step_id, ?string $job_id): array {
        $post = get_post($post_id);

        if (!$post || $post->post_status === 'trash') {
            $this->log('warning', 'Post not found or trashed', [
                'post_id' => $post_id,
                'flow_step_id' => $flow_step_id
            ]);
            return [];
        }

        $this->markItemProcessed((string) $post_id, $flow_step_id, $job_id);

        $title = $post->post_title ?: 'N/A';
        $content = $post->post_content ?: '';
        $image_url = $this->extract_image_url($post_id);
        $site_name = get_bloginfo('name') ?: 'Local WordPress';

        // Include featured image file_info if present for AI vision analysis
        $file_info = null;
        $featured_image_id = get_post_thumbnail_id($post_id);
        if ($featured_image_id) {
            $file_path = get_attached_file($featured_image_id);
            if ($file_path && file_exists($file_path)) {
                $file_size = filesize($file_path);
                $mime_type = get_post_mime_type($featured_image_id) ?: 'image/jpeg';

                $file_info = [
                    'file_path' => $file_path,
                    'mime_type' => $mime_type,
                    'file_size' => $file_size
                ];

                $this->log('debug', 'Including featured image file_info for AI processing', [
                    'post_id' => $post_id,
                    'featured_image_id' => $featured_image_id,
                    'file_path' => $file_path,
                    'file_size' => $file_size
                ]);
            }
        }

        $content_data = [
            'title' => $title,
            'content' => $content,
            'excerpt' => $post->post_excerpt ?: ''
        ];

        // Add file_info if featured image is available
        if ($file_info) {
            $content_data['file_info'] = $file_info;
        }

        $metadata = [
            'source_type' => 'wordpress_local',
            'item_identifier_to_log' => $post_id,
            'original_id' => $post_id,
            'original_title' => $title,
            'original_date_gmt' => $post->post_date_gmt,
            'post_type' => $post->post_type,
            'post_status' => $post->post_status,
            'site_name' => $site_name
        ];

        // Prepare raw data for DataPacket creation
        $raw_data = [
            'title' => $content_data['title'],
            'content' => $content_data['content'],
            'metadata' => $metadata
        ];

        // Add excerpt if present
        if (!empty($content_data['excerpt'])) {
            $raw_data['content'] .= "\n\nExcerpt: " . $content_data['excerpt'];
        }

        // Add file_info if featured image is available
        if ($file_info) {
            $raw_data['file_info'] = $file_info;
        }

        // Store URLs and file path in engine_data via centralized filter
        $image_file_path = '';
        if ($file_info) {
            $image_file_path = $file_info['file_path'];
        }

        $this->storeEngineData($job_id, [
            'source_url' => get_permalink($post_id) ?: '',
            'image_file_path' => $image_file_path
        ]);

        return $raw_data;
    }

    /**
     * Extract featured image URL from post.
     */
    private function extract_image_url(int $post_id): ?string {
        $featured_image_id = get_post_thumbnail_id($post_id);
        return $featured_image_id ? wp_get_attachment_image_url($featured_image_id, 'full') : null;
    }


    public static function get_label(): string {
        return __('Local WordPress Posts', 'datamachine');
    }
}

