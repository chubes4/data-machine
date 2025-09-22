<?php
/**
 * Reddit Fetch Handler Settings
 *
 * Defines settings fields and sanitization for Reddit fetch handler.
 * Part of the modular handler architecture.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Fetch\Handlers\Reddit
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\Reddit;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class RedditSettings {

    public function __construct() {
    }

    /**
     * Get settings fields for Reddit fetch handler.
     *
    * @return array Associative array defining the settings fields.
    */
    public static function get_fields(): array {
        return [
            'subreddit' => [
                'type' => 'text',
                'label' => __('Subreddit Name', 'data-machine'),
                'description' => __('Enter the name of the subreddit (e.g., news, programming) without "r/".', 'data-machine'),
                'placeholder' => 'news',
            ],
            'sort_by' => [
                'type' => 'select',
                'label' => __('Sort By', 'data-machine'),
                'description' => __('Select how to sort the subreddit posts.', 'data-machine'),
                'options' => [
                    'hot' => 'Hot',
                    'new' => 'New',
                    'top' => 'Top',
                    'rising' => 'Rising',
                    'controversial' => 'Controversial',
                ],
            ],
            'timeframe_limit' => [
                'type' => 'select',
                'label' => __('Process Posts Within', 'data-machine'),
                'description' => __('Only consider posts created within this timeframe.', 'data-machine'),
                'options' => apply_filters('dm_timeframe_limit', [], null),
            ],
            'min_upvotes' => [
                'type' => 'number',
                'label' => __('Minimum Upvotes', 'data-machine'),
                'description' => __('Only process posts with at least this many upvotes (score). Set to 0 to disable filtering.', 'data-machine'),
                'min' => 0,
                'max' => 100000,
            ],
            'min_comment_count' => [
                'type' => 'number',
                'label' => __('Minimum Comment Count', 'data-machine'),
                'description' => __('Only process posts with at least this many comments. Set to 0 to disable filtering.', 'data-machine'),
                'min' => 0,
                'max' => 100000,
            ],
            'comment_count' => [
                'type' => 'number',
                'label' => __('Top Comments to Fetch', 'data-machine'),
                'description' => __('Number of top comments to fetch for each post. Set to 0 to disable fetching comments.', 'data-machine'),
                'min' => 0,
                'max' => 100,
            ],
            'search' => [
                'type' => 'text',
                'label' => __('Search Term Filter', 'data-machine'),
                'description' => __('Filter posts locally by keywords (comma-separated). Only posts containing at least one keyword in their title or content (selftext) will be considered.', 'data-machine'),
            ],
        ];
    }

    /**
     * Sanitize Reddit fetch handler settings.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public static function sanitize(array $raw_settings): array {
        $sanitized = [];
        $subreddit = sanitize_text_field($raw_settings['subreddit'] ?? '');
        $sanitized['subreddit'] = (preg_match('/^[a-zA-Z0-9_]+$/', $subreddit)) ? $subreddit : '';
        $valid_sorts = ['hot', 'new', 'top', 'rising', 'controversial'];
        $sort_by = sanitize_text_field($raw_settings['sort_by'] ?? 'hot');
        if (!in_array($sort_by, $valid_sorts)) {
            do_action('dm_log', 'error', 'Reddit Settings: Invalid sort parameter provided in settings.', ['sort_by' => $sort_by]);
            return [];
        }
        $sanitized['sort_by'] = $sort_by;
        $sanitized['timeframe_limit'] = sanitize_text_field($raw_settings['timeframe_limit'] ?? 'all_time');
        $min_upvotes = isset($raw_settings['min_upvotes']) ? absint($raw_settings['min_upvotes']) : 0;
        $sanitized['min_upvotes'] = max(0, $min_upvotes);
        $min_comment_count = isset($raw_settings['min_comment_count']) ? absint($raw_settings['min_comment_count']) : 0;
        $sanitized['min_comment_count'] = max(0, $min_comment_count);
        $comment_count = isset($raw_settings['comment_count']) ? absint($raw_settings['comment_count']) : 0;
        $sanitized['comment_count'] = max(0, $comment_count);
        $sanitized['search'] = sanitize_text_field($raw_settings['search'] ?? '');
        return $sanitized;
    }

}
