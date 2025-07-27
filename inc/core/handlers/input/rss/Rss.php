<?php
/**
 * RSS Input Handler
 *
 * Fetches and processes RSS feed data for the Data Machine pipeline.
 * This handler is responsible for fetching RSS/Atom feeds, parsing them,
 * and converting them into standardized DataPackets for processing.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/input
 * @since      0.11.0
 */

namespace DataMachine\Core\Handlers\Input\Rss;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Rss {

    /**
     * Parameter-less constructor for pure filter-based architecture.
     */
    public function __construct() {
        // No parameters needed - all services accessed via filters
    }

    /**
     * Get service via filter system.
     *
     * @param string $service_name Service name.
     * @return mixed Service instance.
     */
    private function get_service(string $service_name) {
        return apply_filters('dm_get_' . $service_name, null);
    }

    /**
     * Fetches and prepares input data packets from an RSS feed.
     *
     * @param object $module The full module object containing configuration and context.
     * @param array  $source_config Decoded data_source_config specific to this handler.
     * @param int    $user_id The ID of the user initiating the process (for ownership/context checks).
     * @return array Array containing 'processed_items' key with standardized data packets for RSS items.
     * @throws Exception If data cannot be retrieved or is invalid.
     */
    public function get_input_data(object $module, array $source_config, int $user_id): array {
        // Direct filter-based validation
        $module_id = isset($module->module_id) ? absint($module->module_id) : 0;
        if (empty($module_id)) {
            throw new Exception(esc_html__('Missing module ID.', 'data-machine'));
        }
        
        // Validate user ID
        if (empty($user_id)) {
            throw new Exception(esc_html__('User ID not provided.', 'data-machine'));
        }

        $logger = apply_filters('dm_get_logger', null);
        $logger?->info('RSS Input: Starting RSS feed processing.', ['module_id' => $module_id]);

        // Access config from nested structure
        $config = $source_config['rss'] ?? [];
        
        // Configuration validation
        $feed_url = trim($config['feed_url'] ?? '');
        if (empty($feed_url)) {
            throw new Exception(esc_html__('RSS feed URL is required.', 'data-machine'));
        }
        
        if (!filter_var($feed_url, FILTER_VALIDATE_URL)) {
            throw new Exception(esc_html__('Invalid RSS feed URL format.', 'data-machine'));
        }

        $process_limit = max(1, absint($config['item_count'] ?? 1));
        $timeframe_limit = $config['timeframe_limit'] ?? 'all_time';
        $search_term = trim($config['search'] ?? '');
        $search_keywords = [];
        if (!empty($search_term)) {
            $search_keywords = array_map('trim', explode(',', $search_term));
            $search_keywords = array_filter($search_keywords); // Remove empty keywords
        }

        // Calculate cutoff timestamp
        $cutoff_timestamp = null;
        if ($timeframe_limit !== 'all_time') {
            $interval_map = [
                '24_hours' => '-24 hours',
                '72_hours' => '-72 hours',
                '7_days'   => '-7 days',
                '30_days'  => '-30 days'
            ];
            if (isset($interval_map[$timeframe_limit])) {
                $cutoff_timestamp = strtotime($interval_map[$timeframe_limit], current_time('timestamp'));
            }
        }

        // Fetch the RSS feed
        $logger?->info('RSS Input: Fetching RSS feed.', ['feed_url' => $feed_url, 'module_id' => $module_id]);
        
        // Use HTTP service for feed fetching
        $http_service = apply_filters('dm_get_http_service', null);
        if (!$http_service) {
            throw new Exception(esc_html__('HTTP service not available.', 'data-machine'));
        }

        $args = [
            'timeout' => 30,
            'user-agent' => 'DataMachine WordPress Plugin/' . DATA_MACHINE_VERSION
        ];

        $response = $http_service->get($feed_url, $args, 'RSS Feed');
        if (is_wp_error($response)) {
            throw new Exception(sprintf(
                /* translators: %s: error message */
                esc_html__('Failed to fetch RSS feed: %s', 'data-machine'),
                esc_html($response->get_error_message())
            ));
        }

        $feed_content = $response['body'];
        if (empty($feed_content)) {
            throw new Exception(esc_html__('RSS feed content is empty.', 'data-machine'));
        }

        // Parse the RSS feed
        $logger?->info('RSS Input: Parsing RSS feed content.', ['module_id' => $module_id]);
        
        // Disable WordPress automatic feed parsing errors
        libxml_use_internal_errors(true);
        
        $xml = simplexml_load_string($feed_content);
        if ($xml === false) {
            $errors = libxml_get_errors();
            $error_messages = array_map(function($error) {
                return trim($error->message);
            }, $errors);
            
            throw new Exception(sprintf(
                /* translators: %s: XML parsing errors */
                esc_html__('Failed to parse RSS feed XML: %s', 'data-machine'),
                esc_html(implode(', ', $error_messages))
            ));
        }

        // Determine feed type and extract items
        $items = [];
        
        if (isset($xml->channel->item)) {
            // RSS 2.0 format
            $items = $xml->channel->item;
            $logger?->debug('RSS Input: Detected RSS 2.0 format.', ['item_count' => count($items), 'module_id' => $module_id]);
        } elseif (isset($xml->item)) {
            // RSS 1.0 format
            $items = $xml->item;
            $logger?->debug('RSS Input: Detected RSS 1.0 format.', ['item_count' => count($items), 'module_id' => $module_id]);
        } elseif (isset($xml->entry)) {
            // Atom format
            $items = $xml->entry;
            $logger?->debug('RSS Input: Detected Atom format.', ['item_count' => count($items), 'module_id' => $module_id]);
        } else {
            throw new Exception(esc_html__('Unsupported feed format or no items found in feed.', 'data-machine'));
        }

        if (empty($items)) {
            $logger?->info('RSS Input: No items found in RSS feed.', ['module_id' => $module_id]);
            return ['processed_items' => []];
        }

        // Process items
        $eligible_items_packets = [];
        $total_checked = 0;
        $processed_items_manager = apply_filters('dm_get_processed_items_manager', null);

        foreach ($items as $item) {
            $total_checked++;
            
            // Extract basic item data
            $title = $this->extract_item_title($item);
            $description = $this->extract_item_description($item);
            $link = $this->extract_item_link($item);
            $pub_date = $this->extract_item_date($item);
            $guid = $this->extract_item_guid($item, $link);
            
            if (empty($guid)) {
                $logger?->warning('RSS Input: Skipping item without GUID.', ['title' => $title, 'module_id' => $module_id]);
                continue;
            }

            // Check if already processed
            if ($processed_items_manager && $processed_items_manager->is_processed($module_id, $guid, 'rss')) {
                $logger?->debug('RSS Input: Skipping already processed item.', ['guid' => $guid, 'module_id' => $module_id]);
                continue;
            }

            // Check timeframe limit
            if ($cutoff_timestamp !== null && $pub_date) {
                $item_timestamp = strtotime($pub_date);
                if ($item_timestamp !== false && $item_timestamp < $cutoff_timestamp) {
                    $logger?->debug('RSS Input: Skipping item outside timeframe.', [
                        'guid' => $guid,
                        'pub_date' => $pub_date,
                        'cutoff' => date('Y-m-d H:i:s', $cutoff_timestamp),
                        'module_id' => $module_id
                    ]);
                    continue;
                }
            }

            // Check search keywords
            if (!empty($search_keywords)) {
                $text_to_search = $title . ' ' . $description;
                $found_keyword = false;
                foreach ($search_keywords as $keyword) {
                    if (mb_stripos($text_to_search, $keyword) !== false) {
                        $found_keyword = true;
                        break;
                    }
                }
                if (!$found_keyword) {
                    $logger?->debug('RSS Input: Skipping item not matching search keywords.', ['guid' => $guid, 'module_id' => $module_id]);
                    continue;
                }
            }

            // Item is eligible - create standardized packet
            $logger?->debug('RSS Input: Found eligible RSS item.', ['guid' => $guid, 'title' => $title, 'module_id' => $module_id]);
            
            // Extract additional metadata
            $author = $this->extract_item_author($item);
            $categories = $this->extract_item_categories($item);
            $enclosure_url = $this->extract_item_enclosure($item);
            
            // Build content string
            $content_string = "Source: RSS Feed\n\nTitle: " . $title . "\n\n";
            if (!empty($description)) {
                $content_string .= "Content:\n" . $description . "\n";
            }
            if (!empty($link)) {
                $content_string .= "\nSource URL: " . $link;
            }

            // Create metadata
            $metadata = [
                'source_type' => 'rss',
                'item_identifier_to_log' => $guid,
                'original_id' => $guid,
                'source_url' => $link,
                'original_title' => $title,
                'original_date_gmt' => $pub_date ? gmdate('Y-m-d\TH:i:s\Z', strtotime($pub_date)) : null,
                'author' => $author,
                'categories' => $categories,
                'feed_url' => $feed_url,
                'enclosure_url' => $enclosure_url
            ];

            // Detect file info from enclosure
            $file_info = null;
            if (!empty($enclosure_url)) {
                $file_info = [
                    'url' => $enclosure_url,
                    'type' => $this->guess_mime_type_from_url($enclosure_url),
                    'mime_type' => $this->guess_mime_type_from_url($enclosure_url)
                ];
            }

            $input_data_packet = [
                'data' => [
                    'content_string' => $content_string,
                    'file_info' => $file_info
                ],
                'metadata' => $metadata
            ];
            
            $eligible_items_packets[] = $input_data_packet;
            
            if (count($eligible_items_packets) >= $process_limit) {
                $logger?->debug('RSS Input: Reached process limit.', ['limit' => $process_limit, 'module_id' => $module_id]);
                break;
            }
        }

        $found_count = count($eligible_items_packets);
        $logger?->info('RSS Input: Finished processing RSS feed.', [
            'found_count' => $found_count,
            'total_checked' => $total_checked,
            'module_id' => $module_id
        ]);

        return ['processed_items' => $eligible_items_packets];
    }

    /**
     * Extract title from RSS/Atom item.
     *
     * @param object $item SimpleXML item object.
     * @return string Item title.
     */
    private function extract_item_title($item): string {
        if (isset($item->title)) {
            return (string) $item->title;
        }
        return 'Untitled';
    }

    /**
     * Extract description/content from RSS/Atom item.
     *
     * @param object $item SimpleXML item object.
     * @return string Item description/content.
     */
    private function extract_item_description($item): string {
        // Try various content fields
        if (isset($item->description)) {
            return wp_strip_all_tags((string) $item->description);
        }
        if (isset($item->summary)) {
            return wp_strip_all_tags((string) $item->summary);
        }
        if (isset($item->content)) {
            return wp_strip_all_tags((string) $item->content);
        }
        // Handle content:encoded (common in WordPress feeds)
        $content_ns = $item->children('http://purl.org/rss/1.0/modules/content/');
        if (isset($content_ns->encoded)) {
            return wp_strip_all_tags((string) $content_ns->encoded);
        }
        return '';
    }

    /**
     * Extract link from RSS/Atom item.
     *
     * @param object $item SimpleXML item object.
     * @return string Item link.
     */
    private function extract_item_link($item): string {
        if (isset($item->link)) {
            $link = $item->link;
            if (is_object($link) && isset($link['href'])) {
                // Atom format
                return (string) $link['href'];
            }
            return (string) $link;
        }
        return '';
    }

    /**
     * Extract publication date from RSS/Atom item.
     *
     * @param object $item SimpleXML item object.
     * @return string|null Item publication date.
     */
    private function extract_item_date($item): ?string {
        if (isset($item->pubDate)) {
            return (string) $item->pubDate;
        }
        if (isset($item->published)) {
            return (string) $item->published;
        }
        if (isset($item->updated)) {
            return (string) $item->updated;
        }
        // Handle dc:date (Dublin Core)
        $dc_ns = $item->children('http://purl.org/dc/elements/1.1/');
        if (isset($dc_ns->date)) {
            return (string) $dc_ns->date;
        }
        return null;
    }

    /**
     * Extract GUID from RSS/Atom item.
     *
     * @param object $item SimpleXML item object.
     * @param string $fallback_link Fallback link to use as GUID.
     * @return string Item GUID.
     */
    private function extract_item_guid($item, string $fallback_link): string {
        if (isset($item->guid)) {
            return (string) $item->guid;
        }
        if (isset($item->id)) {
            return (string) $item->id;
        }
        // Use link as fallback GUID
        return $fallback_link;
    }

    /**
     * Extract author from RSS/Atom item.
     *
     * @param object $item SimpleXML item object.
     * @return string|null Item author.
     */
    private function extract_item_author($item): ?string {
        if (isset($item->author)) {
            $author = $item->author;
            if (is_object($author) && isset($author->name)) {
                // Atom format
                return (string) $author->name;
            }
            return (string) $author;
        }
        // Handle dc:creator (Dublin Core)
        $dc_ns = $item->children('http://purl.org/dc/elements/1.1/');
        if (isset($dc_ns->creator)) {
            return (string) $dc_ns->creator;
        }
        return null;
    }

    /**
     * Extract categories from RSS/Atom item.
     *
     * @param object $item SimpleXML item object.
     * @return array Item categories.
     */
    private function extract_item_categories($item): array {
        $categories = [];
        
        if (isset($item->category)) {
            foreach ($item->category as $category) {
                if (isset($category['term'])) {
                    // Atom format
                    $categories[] = (string) $category['term'];
                } else {
                    // RSS format
                    $categories[] = (string) $category;
                }
            }
        }
        
        return $categories;
    }

    /**
     * Extract enclosure URL from RSS item.
     *
     * @param object $item SimpleXML item object.
     * @return string|null Enclosure URL.
     */
    private function extract_item_enclosure($item): ?string {
        if (isset($item->enclosure) && isset($item->enclosure['url'])) {
            return (string) $item->enclosure['url'];
        }
        return null;
    }

    /**
     * Guess MIME type from URL extension.
     *
     * @param string $url File URL.
     * @return string Guessed MIME type.
     */
    private function guess_mime_type_from_url(string $url): string {
        $extension = strtolower(pathinfo(wp_parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        
        $mime_map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip'
        ];
        
        return $mime_map[$extension] ?? 'application/octet-stream';
    }

    /**
     * Get settings fields for the RSS input handler.
     *
     * @deprecated Settings are now integrated into handler registration. Use 'settings_class' key in dm_register_input_handlers filter.
     * @return array Associative array of field definitions.
     */
    public static function get_settings_fields(): array {
        return [
            'feed_url' => [
                'type' => 'url',
                'label' => __('RSS Feed URL', 'data-machine'),
                'description' => __('Enter the full URL of the RSS or Atom feed (e.g., https://example.com/feed).', 'data-machine'),
                'required' => true,
                'default' => '',
            ],
            'item_count' => [
                'type' => 'number',
                'label' => __('Items to Process', 'data-machine'),
                'description' => __('Maximum number of *new* RSS items to process per run.', 'data-machine'),
                'default' => 1,
                'min' => 1,
                'max' => 50,
            ],
            'timeframe_limit' => [
                'type' => 'select',
                'label' => __('Process Items Within', 'data-machine'),
                'description' => __('Only consider RSS items published within this timeframe.', 'data-machine'),
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
                'description' => __('Optional: Filter RSS items by keywords (comma-separated). Only items containing at least one keyword in their title or content will be processed.', 'data-machine'),
                'default' => '',
            ],
        ];
    }

    /**
     * Sanitize settings for the RSS input handler.
     *
     * @param array $raw_settings Raw settings array.
     * @return array Sanitized settings.
     */
    public function sanitize_settings(array $raw_settings): array {
        $sanitized = [];
        
        // Feed URL is required
        $feed_url = esc_url_raw($raw_settings['feed_url'] ?? '');
        if (empty($feed_url)) {
            throw new \InvalidArgumentException(esc_html__('RSS Feed URL is required.', 'data-machine'));
        }
        $sanitized['feed_url'] = $feed_url;
        
        // Item count
        $sanitized['item_count'] = max(1, min(50, absint($raw_settings['item_count'] ?? 1)));
        
        // Timeframe limit
        $valid_timeframes = ['all_time', '24_hours', '72_hours', '7_days', '30_days'];
        $timeframe = sanitize_text_field($raw_settings['timeframe_limit'] ?? 'all_time');
        $sanitized['timeframe_limit'] = in_array($timeframe, $valid_timeframes) ? $timeframe : 'all_time';
        
        // Search terms
        $sanitized['search'] = sanitize_text_field($raw_settings['search'] ?? '');
        
        return $sanitized;
    }

    /**
     * Get the user-friendly label for this handler.
     *
     * @return string Handler label.
     */
    public static function get_label(): string {
        return __('RSS/Atom Feed', 'data-machine');
    }
}

// Self-register via filter with integrated settings
add_filter('dm_register_input_handlers', function($handlers) {
    $handlers['rss'] = [
        'class' => 'DataMachine\\Core\\Handlers\\Input\\Rss\\Rss',
        'label' => __('RSS/Atom Feed', 'data-machine'),
        'settings_class' => 'DataMachine\\Core\\Handlers\\Input\\Rss\\RssSettings'
    ];
    return $handlers;
});
