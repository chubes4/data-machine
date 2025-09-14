<?php
/**
 * RSS/Atom feed handler with filtering and deduplication
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\Rss;


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rss {

    public function __construct() {
    }


    /**
     * Fetch RSS feed items with timeframe and search filtering
     */
    public function get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array {

        $flow_step_id = $handler_config['flow_step_id'] ?? null;
        $config = $handler_config['rss'] ?? [];
        $feed_url = trim($config['feed_url'] ?? '');

        $timeframe_limit = $config['timeframe_limit'] ?? 'all_time';
        $search_term = trim($config['search'] ?? '');
        $search_keywords = [];
        if (!empty($search_term)) {
            $search_keywords = array_map('trim', explode(',', $search_term));
            $search_keywords = array_filter($search_keywords);
        }

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

        $args = [
            'user-agent' => 'DataMachine WordPress Plugin/' . DATA_MACHINE_VERSION
        ];

        $result = apply_filters('dm_request', null, 'GET', $feed_url, $args, 'RSS Feed');
        
        if (!$result['success']) {
            do_action('dm_log', 'error', 'RSS Input: Failed to fetch RSS feed.', [
                'pipeline_id' => $pipeline_id,
                'error' => $result['error'],
                'feed_url' => $feed_url
            ]);
            return ['processed_items' => []];
        }

        $feed_content = $result['data'];
        if (empty($feed_content)) {
            do_action('dm_log', 'error', 'RSS Input: RSS feed content is empty.', ['pipeline_id' => $pipeline_id, 'feed_url' => $feed_url]);
            return ['processed_items' => []];
        }

        // Parse the RSS feed
        
        // Disable WordPress automatic feed parsing errors
        libxml_use_internal_errors(true);
        
        $xml = simplexml_load_string($feed_content);
        if ($xml === false) {
            $errors = libxml_get_errors();
            $error_messages = array_map(function($error) {
                return trim($error->message);
            }, $errors);
            
            do_action('dm_log', 'error', 'RSS Input: Failed to parse RSS feed XML.', [
                'pipeline_id' => $pipeline_id,
                'feed_url' => $feed_url,
                'xml_errors' => implode(', ', $error_messages)
            ]);
            return ['processed_items' => []];
        }

        // Determine feed type and extract items
        $items = [];
        
        if (isset($xml->channel->item)) {
            // RSS 2.0 format
            $items = $xml->channel->item;
        } elseif (isset($xml->item)) {
            // RSS 1.0 format
            $items = $xml->item;
        } elseif (isset($xml->entry)) {
            // Atom format
            $items = $xml->entry;
        } else {
            do_action('dm_log', 'error', 'RSS Input: Unsupported feed format or no items found in feed.', ['pipeline_id' => $pipeline_id, 'feed_url' => $feed_url]);
            return ['processed_items' => []];
        }

        if (empty($items)) {
            return ['processed_items' => []];
        }

        // Process items - find first eligible item only
        $total_checked = 0;

        foreach ($items as $item) {
            $total_checked++;
            
            // Extract basic item data
            $title = $this->extract_item_title($item);
            $description = $this->extract_item_description($item);
            $link = $this->extract_item_link($item);
            $pub_date = $this->extract_item_date($item);
            $guid = $this->extract_item_guid($item, $link);
            
            if (empty($guid)) {
                do_action('dm_log', 'warning', 'RSS Input: Skipping item without GUID.', ['title' => $title, 'pipeline_id' => $pipeline_id]);
                continue;
            }

            // Check if already processed
            $is_processed = apply_filters('dm_is_item_processed', false, $flow_step_id, 'rss', $guid);
            if ($is_processed) {
                continue;
            }

            // Check timeframe limit
            if ($cutoff_timestamp !== null && $pub_date) {
                $item_timestamp = strtotime($pub_date);
                if ($item_timestamp !== false && $item_timestamp < $cutoff_timestamp) {
                    do_action('dm_log', 'debug', 'RSS Input: Skipping item outside timeframe.', [
                        'guid' => $guid,
                        'pub_date' => $pub_date,
                        'cutoff' => gmdate('Y-m-d H:i:s', $cutoff_timestamp),
                        'pipeline_id' => $pipeline_id
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
                    continue;
                }
            }

            // Found first eligible item - create standardized packet and return
            
            // Mark item as processed immediately after confirming eligibility
            if ($flow_step_id) {
                do_action('dm_mark_item_processed', $flow_step_id, 'rss', $guid, $job_id);
            }
            
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

            $input_data = [
                'data' => [
                    'content_string' => $content_string,
                    'file_info' => $file_info
                ],
                'metadata' => $metadata
            ];
            
            // Return first eligible item immediately
            return ['processed_items' => [$input_data]];
        }

        // No eligible items found
        do_action('dm_log', 'debug', 'RSS Input: No eligible items found in RSS feed.', [
            'total_checked' => $total_checked,
            'pipeline_id' => $pipeline_id
        ]);

        return ['processed_items' => []];
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
     * @param string $item_link Item link to use as GUID.
     * @return string Item GUID.
     */
    private function extract_item_guid($item, string $item_link): string {
        if (isset($item->guid)) {
            return (string) $item->guid;
        }
        if (isset($item->id)) {
            return (string) $item->id;
        }
        return $item_link;
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
     * Get the user-friendly label for this handler.
     *
     * @return string Handler label.
     */
    public static function get_label(): string {
        return __('RSS/Atom Feed', 'data-machine');
    }
}

