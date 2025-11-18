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

use DataMachine\Core\Steps\SettingsHandler;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class RedditSettings extends SettingsHandler {

    /**
     * Get settings fields for Reddit fetch handler.
     *
    * @return array Associative array defining the settings fields.
    */
    public static function get_fields(): array {
        return [
            'subreddit' => [
                'type' => 'text',
                'label' => __('Subreddit Name', 'datamachine'),
                'description' => __('Enter the name of the subreddit (e.g., news, programming) without "r/".', 'datamachine'),
                'placeholder' => 'news',
            ],
            'sort_by' => [
                'type' => 'select',
                'label' => __('Sort By', 'datamachine'),
                'description' => __('Select how to sort the subreddit posts.', 'datamachine'),
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
                'label' => __('Process Posts Within', 'datamachine'),
                'description' => __('Only consider posts created within this timeframe.', 'datamachine'),
                'options' => apply_filters('datamachine_timeframe_limit', [], null),
            ],
            'min_upvotes' => [
                'type' => 'number',
                'label' => __('Minimum Upvotes', 'datamachine'),
                'description' => __('Only process posts with at least this many upvotes (score). Set to 0 to disable filtering.', 'datamachine'),
                'min' => 0,
                'max' => 100000,
            ],
            'min_comment_count' => [
                'type' => 'number',
                'label' => __('Minimum Comment Count', 'datamachine'),
                'description' => __('Only process posts with at least this many comments. Set to 0 to disable filtering.', 'datamachine'),
                'min' => 0,
                'max' => 100000,
            ],
            'comment_count' => [
                'type' => 'number',
                'label' => __('Top Comments to Fetch', 'datamachine'),
                'description' => __('Number of top comments to fetch for each post. Set to 0 to disable fetching comments.', 'datamachine'),
                'min' => 0,
                'max' => 100,
            ],
            'search' => [
                'type' => 'text',
                'label' => __('Search Term Filter', 'datamachine'),
                'description' => __('Filter posts locally by keywords (comma-separated). Only posts containing at least one keyword in their title or content (selftext) will be considered.', 'datamachine'),
            ],
        ];
    }

    /**
     * Sanitize Reddit fetch handler settings.
     *
     * Uses parent auto-sanitization for most fields, adds custom regex validation for subreddit.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public static function sanitize(array $raw_settings): array {
        // Let parent handle most fields (select, numbers, text)
        $sanitized = parent::sanitize($raw_settings);

        // Custom regex validation for subreddit name
        $subreddit = sanitize_text_field($raw_settings['subreddit'] ?? '');
        $sanitized['subreddit'] = (preg_match('/^[a-zA-Z0-9_]+$/', $subreddit)) ? $subreddit : '';

        return $sanitized;
    }
}
