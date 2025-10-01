<?php
/**
 * RSS/Atom feed handler with timeframe and keyword filtering.
 *
 * @package DataMachine\Core\Steps\Fetch\Handlers\Rss
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\Rss;


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rss {

    public function __construct() {
    }


    /**
     * Fetch RSS/Atom content with timeframe and keyword filtering.
     * Engine data (source_url, image_url) stored via dm_engine_data filter.
     */
    public function get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array {

        $flow_step_id = $handler_config['flow_step_id'] ?? null;
        $config = $handler_config['rss'] ?? [];
        $feed_url = trim($config['feed_url'] ?? '');

        $timeframe_limit = $config['timeframe_limit'] ?? 'all_time';
        $search = trim($config['search'] ?? '');

        $cutoff_timestamp = apply_filters('dm_timeframe_limit', null, $timeframe_limit);

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

            // Apply keyword search filter
            $search_text = $title . ' ' . wp_strip_all_tags($description);
            $matches = apply_filters('dm_keyword_search_match', false, $search_text, $search);
            if (!$matches) {
                continue; // Skip items that don't match search keywords
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

            // Create structured content data for AI processing
            $content_data = [
                'title' => $title,
                'content' => $description
            ];

            // Create clean metadata for AI consumption (URLs removed)
            $metadata = [
                'source_type' => 'rss',
                'item_identifier_to_log' => $guid,
                'original_id' => $guid,
                'original_title' => $title,
                'original_date_gmt' => $pub_date ? gmdate('Y-m-d\TH:i:s\Z', strtotime($pub_date)) : null,
                'author' => $author,
                'categories' => $categories
            ];

            // Download remote image from enclosure if present
            $file_info = null;
            if (!empty($enclosure_url)) {
                $mime_type = $this->guess_mime_type_from_url($enclosure_url);

                // Only download if it's an image that AI can process
                if (strpos($mime_type, 'image/') === 0 && in_array($mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                    $repositories = apply_filters('dm_files_repository', []);
                    $files_repo = $repositories['files'] ?? null;

                    if ($files_repo && $flow_step_id) {
                        $filename = 'rss_image_' . time() . '_' . sanitize_file_name(basename(wp_parse_url($enclosure_url, PHP_URL_PATH)));
                        $download_result = $files_repo->store_remote_file($enclosure_url, $filename, $flow_step_id);

                        if ($download_result) {
                            $file_info = [
                                'file_path' => $download_result['path'],
                                'mime_type' => $mime_type,
                                'file_size' => $download_result['size']
                            ];

                            do_action('dm_log', 'debug', 'RSS Input: Downloaded remote image for AI processing', [
                                'guid' => $guid,
                                'source_url' => $enclosure_url,
                                'local_path' => $download_result['path'],
                                'file_size' => $download_result['size']
                            ]);
                        } else {
                            do_action('dm_log', 'warning', 'RSS Input: Failed to download remote image', [
                                'guid' => $guid,
                                'enclosure_url' => $enclosure_url
                            ]);

                            // Fall back to type info only
                            $file_info = [
                                'type' => $mime_type,
                                'mime_type' => $mime_type
                            ];
                        }
                    } else {
                        // Fall back to type info only if no repository or flow_step_id
                        $file_info = [
                            'type' => $mime_type,
                            'mime_type' => $mime_type
                        ];
                    }
                } else {
                    // Non-image or unsupported image format - keep original behavior
                    $file_info = [
                        'type' => $mime_type,
                        'mime_type' => $mime_type
                    ];
                }
            }

            // Create clean data packet for AI processing
            $input_data = [
                'data' => array_merge($content_data, ['file_info' => $file_info]),
                'metadata' => $metadata
            ];

            // Store URLs in engine_data via centralized filter
            if ($job_id) {
                apply_filters('dm_engine_data', null, $job_id, $link ?: '', $enclosure_url ?: '');
            }

            // Return clean data packet (no URLs in metadata for AI)
            return ['processed_items' => [$input_data]];
        }

        // No eligible items found
        do_action('dm_log', 'debug', 'RSS Input: No eligible items found in RSS feed.', [
            'total_checked' => $total_checked,
            'pipeline_id' => $pipeline_id
        ]);

        return ['processed_items' => []];
    }

    private function extract_item_title($item): string {
        if (isset($item->title)) {
            return (string) $item->title;
        }
        return 'Untitled';
    }

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

    private function extract_item_guid($item, string $item_link): string {
        if (isset($item->guid)) {
            return (string) $item->guid;
        }
        if (isset($item->id)) {
            return (string) $item->id;
        }
        return $item_link;
    }

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

    private function extract_item_enclosure($item): ?string {
        if (isset($item->enclosure) && isset($item->enclosure['url'])) {
            return (string) $item->enclosure['url'];
        }
        return null;
    }

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

    public static function get_label(): string {
        return __('RSS/Atom Feed', 'data-machine');
    }
}
