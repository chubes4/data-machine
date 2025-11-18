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

    /**
     * Fetch RSS/Atom content with timeframe and keyword filtering.
     * Engine data (source_url, image_url) stored via datamachine_engine_data filter.
     */
    public function get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array {

        $flow_step_id = $handler_config['flow_step_id'] ?? null;
        $flow_id = $handler_config['flow_id'] ?? 0;
        $config = $handler_config['rss'] ?? [];
        $feed_url = trim($config['feed_url'] ?? '');

        $timeframe_limit = $config['timeframe_limit'] ?? 'all_time';
        $search = trim($config['search'] ?? '');

        $cutoff_timestamp = apply_filters('datamachine_timeframe_limit', null, $timeframe_limit);

        $args = [
            'user-agent' => 'DataMachine WordPress Plugin/' . DATAMACHINE_VERSION
        ];

        $result = apply_filters('datamachine_request', null, 'GET', $feed_url, $args, 'RSS Feed');

        if (!$result['success']) {
            do_action('datamachine_log', 'error', 'RSS Input: Failed to fetch RSS feed.', [
                'pipeline_id' => $pipeline_id,
                'error' => $result['error'],
                'feed_url' => $feed_url
            ]);
            return ['processed_items' => []];
        }

        $feed_content = $result['data'];
        if (empty($feed_content)) {
            do_action('datamachine_log', 'error', 'RSS Input: RSS feed content is empty.', ['pipeline_id' => $pipeline_id, 'feed_url' => $feed_url]);
            return ['processed_items' => []];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($feed_content);
        if ($xml === false) {
            $errors = libxml_get_errors();
            $error_messages = array_map(function($error) {
                return trim($error->message);
            }, $errors);
            
            do_action('datamachine_log', 'error', 'RSS Input: Failed to parse RSS feed XML.', [
                'pipeline_id' => $pipeline_id,
                'feed_url' => $feed_url,
                'xml_errors' => implode(', ', $error_messages)
            ]);
            return ['processed_items' => []];
        }

        $items = [];

        if (isset($xml->channel->item)) {
            $items = $xml->channel->item;
        } elseif (isset($xml->item)) {
            $items = $xml->item;
        } elseif (isset($xml->entry)) {
            $items = $xml->entry;
        } else {
            do_action('datamachine_log', 'error', 'RSS Input: Unsupported feed format or no items found in feed.', ['pipeline_id' => $pipeline_id, 'feed_url' => $feed_url]);
            return ['processed_items' => []];
        }

        if (empty($items)) {
            return ['processed_items' => []];
        }

        $total_checked = 0;

        foreach ($items as $item) {
            $total_checked++;

            $title = $this->extract_item_title($item);
            $description = $this->extract_item_description($item);
            $link = $this->extract_item_link($item);
            $pub_date = $this->extract_item_date($item);
            $guid = $this->extract_item_guid($item, $link);
            
            if (empty($guid)) {
                do_action('datamachine_log', 'warning', 'RSS Input: Skipping item without GUID.', ['title' => $title, 'pipeline_id' => $pipeline_id]);
                continue;
            }

            $is_processed = apply_filters('datamachine_is_item_processed', false, $flow_step_id, 'rss', $guid);
            if ($is_processed) {
                continue;
            }

            if ($cutoff_timestamp !== null && $pub_date) {
                $item_timestamp = strtotime($pub_date);
                if ($item_timestamp !== false && $item_timestamp < $cutoff_timestamp) {
                    do_action('datamachine_log', 'debug', 'RSS Input: Skipping item outside timeframe.', [
                        'guid' => $guid,
                        'pub_date' => $pub_date,
                        'cutoff' => gmdate('Y-m-d H:i:s', $cutoff_timestamp),
                        'pipeline_id' => $pipeline_id
                    ]);
                    continue;
                }
            }

            $search_text = $title . ' ' . wp_strip_all_tags($description);
            $matches = apply_filters('datamachine_keyword_search_match', false, $search_text, $search);
            if (!$matches) {
                continue;
            }

            if ($flow_step_id) {
                do_action('datamachine_mark_item_processed', $flow_step_id, 'rss', $guid, $job_id);
            }

            $author = $this->extract_item_author($item);
            $categories = $this->extract_item_categories($item);
            $enclosure_url = $this->extract_item_enclosure($item);

            $content_data = [
                'title' => $title,
                'content' => $description
            ];

            $metadata = [
                'source_type' => 'rss',
                'item_identifier_to_log' => $guid,
                'original_id' => $guid,
                'original_title' => $title,
                'original_date_gmt' => $pub_date ? gmdate('Y-m-d\TH:i:s\Z', strtotime($pub_date)) : null,
                'author' => $author,
                'categories' => $categories
            ];

            $file_info = null;
            if (!empty($enclosure_url)) {
                $file_check = wp_check_filetype($enclosure_url);
                $mime_type = $file_check['type'] ?: 'application/octet-stream';

                if (strpos($mime_type, 'image/') === 0 && in_array($mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                    $downloader = apply_filters('datamachine_get_remote_downloader', null);

                    if ($downloader && $flow_step_id) {
                        $filename = 'rss_image_' . time() . '_' . sanitize_file_name(basename(wp_parse_url($enclosure_url, PHP_URL_PATH)));

                        // Build context with fallback names (no database queries)
                        $context = [
                            'pipeline_id' => $pipeline_id,
                            'pipeline_name' => "pipeline-{$pipeline_id}",
                            'flow_id' => $flow_id,
                            'flow_name' => "flow-{$flow_id}"
                        ];

                        $download_result = $downloader->download_remote_file($enclosure_url, $filename, $context);

                        if ($download_result) {
                            $file_info = [
                                'file_path' => $download_result['path'],
                                'mime_type' => $mime_type,
                                'file_size' => $download_result['size']
                            ];

                            do_action('datamachine_log', 'debug', 'RSS Input: Downloaded remote image for AI processing', [
                                'guid' => $guid,
                                'source_url' => $enclosure_url,
                                'local_path' => $download_result['path'],
                                'file_size' => $download_result['size']
                            ]);
                        } else {
                            do_action('datamachine_log', 'warning', 'RSS Input: Failed to download remote image', [
                                'guid' => $guid,
                                'enclosure_url' => $enclosure_url
                            ]);

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

            if ($job_id) {
                apply_filters('datamachine_engine_data', null, $job_id, [
                    'source_url' => $link ?: '',
                    'image_url' => $enclosure_url ?: ''
                ]);
            }

            return ['processed_items' => [$input_data]];
        }

        do_action('datamachine_log', 'debug', 'RSS Input: No eligible items found in RSS feed.', [
            'total_checked' => $total_checked,
            'pipeline_id' => $pipeline_id
        ]);

        return ['processed_items' => []];
    }

    /**
     * Extract title from RSS item.
     *
     * @param object $item SimpleXML item object
     * @return string Item title or 'Untitled' if not found
     */
    private function extract_item_title($item): string {
        if (isset($item->title)) {
            return (string) $item->title;
        }
        return 'Untitled';
    }

    /**
     * Extract description/content from RSS item.
     *
     * Checks multiple possible fields: description, summary, content, encoded.
     *
     * @param object $item SimpleXML item object
     * @return string Stripped content text
     */
    private function extract_item_description($item): string {
        if (isset($item->description)) {
            return wp_strip_all_tags((string) $item->description);
        }
        if (isset($item->summary)) {
            return wp_strip_all_tags((string) $item->summary);
        }
        if (isset($item->content)) {
            return wp_strip_all_tags((string) $item->content);
        }
        $content_ns = $item->children('http://purl.org/rss/1.0/modules/content/');
        if (isset($content_ns->encoded)) {
            return wp_strip_all_tags((string) $content_ns->encoded);
        }
        return '';
    }

    /**
     * Extract link URL from RSS item.
     *
     * @param object $item SimpleXML item object
     * @return string Item link URL
     */
    private function extract_item_link($item): string {
        if (isset($item->link)) {
            $link = $item->link;
            if (is_object($link) && isset($link['href'])) {
                return (string) $link['href'];
            }
            return (string) $link;
        }
        return '';
    }

    /**
     * Extract publication date from RSS item.
     *
     * Checks multiple date fields: pubDate, published, updated, dc:date.
     *
     * @param object $item SimpleXML item object
     * @return string|null Publication date string or null if not found
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
        $dc_ns = $item->children('http://purl.org/dc/elements/1.1/');
        if (isset($dc_ns->date)) {
            return (string) $dc_ns->date;
        }
        return null;
    }

    /**
     * Extract GUID/unique identifier from RSS item.
     *
     * Falls back to item link if no GUID found.
     *
     * @param object $item SimpleXML item object
     * @param string $item_link Fallback link if no GUID
     * @return string Unique identifier for the item
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
     * Extract author name from RSS item.
     *
     * Checks author field and dc:creator namespace.
     *
     * @param object $item SimpleXML item object
     * @return string|null Author name or null if not found
     */
    private function extract_item_author($item): ?string {
        if (isset($item->author)) {
            $author = $item->author;
            if (is_object($author) && isset($author->name)) {
                return (string) $author->name;
            }
            return (string) $author;
        }
        $dc_ns = $item->children('http://purl.org/dc/elements/1.1/');
        if (isset($dc_ns->creator)) {
            return (string) $dc_ns->creator;
        }
        return null;
    }

    /**
     * Extract categories/tags from RSS item.
     *
     * @param object $item SimpleXML item object
     * @return array Array of category names
     */
    private function extract_item_categories($item): array {
        $categories = [];

        if (isset($item->category)) {
            foreach ($item->category as $category) {
                if (isset($category['term'])) {
                    $categories[] = (string) $category['term'];
                } else {
                    $categories[] = (string) $category;
                }
            }
        }
        
        return $categories;
    }

    /**
     * Extract enclosure URL from RSS item.
     *
     * @param object $item SimpleXML item object
     * @return string|null Enclosure URL or null if not found
     */
    private function extract_item_enclosure($item): ?string {
        if (isset($item->enclosure) && isset($item->enclosure['url'])) {
            return (string) $item->enclosure['url'];
        }
        return null;
    }

    /**
     * Get the display label for the RSS handler.
     *
     * @return string Localized handler label
     */
    public static function get_label(): string {
        return __('RSS/Atom Feed', 'datamachine');
    }
}
