<?php
/**
 * Instagram Input Handler (HTML Scraping Version)
 *
 * @package Data_Machine
 */

class Data_Machine_Input_Instagram implements Data_Machine_Input_Handler_Interface {

    use Data_Machine_Base_Input_Handler;

    /** @var Data_Machine_Database_Processed_Items */
    private $db_processed_items;

    /** @var Data_Machine_Database_Modules */
    private $db_modules;

    /** @var Data_Machine_Database_Projects */
    private $db_projects;

    /** @var ?Data_Machine_Logger */
    private $logger;

    /**
     * Constructor.
     *
     * @param Data_Machine_Database_Processed_Items $db_processed_items Processed items DB.
     * @param Data_Machine_Database_Modules $db_modules Modules DB.
     * @param Data_Machine_Database_Projects $db_projects Projects DB.
     * @param Data_Machine_Logger|null $logger Optional logger.
     */
    public function __construct(
        Data_Machine_Database_Processed_Items $db_processed_items,
        Data_Machine_Database_Modules $db_modules,
        Data_Machine_Database_Projects $db_projects,
        ?Data_Machine_Logger $logger = null
    ) {
        $this->db_processed_items = $db_processed_items;
        $this->db_modules = $db_modules;
        $this->db_projects = $db_projects;
        $this->logger = $logger;
    }

    public static function get_label(): string {
        return __('Instagram Profile (Public Scrape)', 'data-machine'); // Added i18n
    }

    public static function get_settings_fields(): array {
        return [
            'target_profiles' => [
                'type' => 'textarea',
                'label' => __('Target Instagram Profiles', 'data-machine'), // Added i18n
                'description' => __('Enter one Instagram profile handle per line to monitor (without @ symbol)', 'data-machine'), // Added i18n
                'rows' => 5,
                'required' => true,
            ],
            'post_limit' => [
                'type' => 'number',
                'label' => __('Post Limit Per Profile', 'data-machine'), // Added i18n & clarification
                'description' => __('Maximum number of recent posts to check per profile per run.', 'data-machine'), // Added i18n
                'default' => 5,
                'min' => 1,
                'max' => 20, // Keep reasonable limit for scraping
            ],
        ];
    }

    /**
     * Fetches and prepares input data packets from specified Instagram profiles.
     *
     * @param object $module The full module object containing configuration and context.
     * @param array  $source_config Decoded data_source_config specific to this handler.
     * @param int    $user_id The ID of the user initiating the process (for ownership/context checks).
     * @return array An array of standardized input data packets, or an array indicating no new items (e.g., ['status' => 'no_new_items']).
     * @throws Exception If data cannot be retrieved or is invalid.
     */
    public function get_input_data(object $module, array $source_config, int $user_id): array {
        $this->logger?->info('Instagram Input: Entering get_input_data.', ['module_id' => $module->module_id ?? null]);

        // --- Standard Checks ---
        $module_id = isset($module->module_id) ? absint($module->module_id) : 0;
        if ( empty( $module_id ) || empty( $user_id ) ) {
            $this->logger?->error('Instagram Input: Missing module ID or user ID.', ['module_id' => $module_id, 'user_id' => $user_id]);
            throw new Exception(__( 'Missing module ID or user ID provided to Instagram handler.', 'data-machine' ));
        }
        if (!$this->db_processed_items || !$this->db_modules || !$this->db_projects) {
             $this->logger?->error('Instagram Input: Required database service dependency missing.', ['module_id' => $module_id]);
            throw new Exception(__( 'Required database service not available in Instagram handler.', 'data-machine' ));
        }
        // Ownership check (using the trait method)
        $project = $this->get_module_with_ownership_check($module, $user_id, $this->db_projects);
        // --- End Standard Checks ---

        // --- Configuration ---
        if (empty($source_config['target_profiles'])) {
             $this->logger?->error('Instagram Input: No target profiles specified in configuration.', ['module_id' => $module_id]);
            throw new Exception(__( 'No target Instagram profiles specified in module configuration.', 'data-machine' ));
        }
        $profiles = array_filter(array_map('trim', explode("\n", $source_config['target_profiles'])));
        $post_limit_per_profile = isset($source_config['post_limit']) ? intval($source_config['post_limit']) : 5;
        // --- End Configuration ---

        $eligible_items_packets = [];
        $total_processed_count = 0;

        foreach ($profiles as $profile) {
            $this->logger?->info('Instagram Input: Processing profile.', ['profile' => $profile, 'module_id' => $module_id]);
            try {
                $post_urls = $this->fetch_recent_post_urls($profile, $post_limit_per_profile);
                $this->logger?->debug('Instagram Input: Found post URLs.', ['profile' => $profile, 'count' => count($post_urls), 'module_id' => $module_id]);

                foreach ($post_urls as $url) {
                    // Extract shortcode to use as a unique identifier
                    $shortcode = basename(rtrim($url, '/')); 
                    if (empty($shortcode)) {
                         $this->logger?->warning('Instagram Input: Could not extract shortcode from URL.', ['url' => $url, 'profile' => $profile, 'module_id' => $module_id]);
                        continue;
                    }

                    // Check if already processed
                    if ($this->db_processed_items->has_item_been_processed($module_id, 'instagram', $shortcode)) {
                        $this->logger?->debug('Instagram Input: Skipping item (already processed).', ['shortcode' => $shortcode, 'profile' => $profile, 'module_id' => $module_id]);
                        continue;
                    }

                    $post_data = $this->scrape_post_data($url);

                    if ($post_data) {
                        $this->logger?->debug('Instagram Input: Scraped post data successfully.', ['shortcode' => $shortcode, 'profile' => $profile, 'module_id' => $module_id]);
                        // --- Item is Eligible --- 
                        $total_processed_count++;

                        // Create data packet
                        $content_string = "Instagram Post from: {$profile}\n";
                        $content_string .= "Post URL: {$url}\n";
                        $content_string .= "Caption: " . ($post_data['caption'] ?? '[No Caption Found]') . "\n";

                        $metadata = [
                            'source_type' => 'instagram',
                            'item_identifier_to_log' => $shortcode,
                            'original_id' => $shortcode,
                            'source_url' => $url,
                            'original_title' => 'Instagram Post by ' . $profile . ' (' . $shortcode . ')',
                            'original_date_gmt' => gmdate('Y-m-d H:i:s'), // Use fetch time as creation time (scraping limitations)
                            'image_source_url' => $post_data['image_url'] ?? null,
                            'profile_handle' => $profile,
                            'raw_caption' => $post_data['caption'] ?? null,
                        ];

                        $input_data_packet = [
                            'data' => [
                                'content_string' => $content_string,
                                'file_info' => $post_data['image_url'] ? [
                                    'url' => $post_data['image_url'],
                                    // We don't know mime/filename without downloading
                                    'filename' => $shortcode . '.jpg', // Guess filename
                                    'mime_type' => 'image/jpeg' // Guess mime type
                                ] : null
                            ],
                            'metadata' => $metadata
                        ];
                        $eligible_items_packets[] = $input_data_packet;

                        // Note: Unlike other handlers, we don't have a strict item_count *limit*.
                        // The limit here is per profile checked. We collect all *new* items up to that check limit.

                    } else {
                         $this->logger?->warning('Instagram Input: Failed to scrape post data.', ['url' => $url, 'profile' => $profile, 'module_id' => $module_id]);
                    }
                    // Add a small delay to avoid rate limiting
                    usleep(500000); // 0.5 seconds
                }
            } catch (\Exception $e) {
                // Log errors per profile but continue with others
                 $this->logger?->error('Instagram Input: Error processing profile.', ['profile' => $profile, 'error' => $e->getMessage(), 'module_id' => $module_id]);
            }
             // Add a longer delay between profiles
             sleep(1);
        } // End foreach profile

        $this->logger?->info('Instagram Input: Finished processing profiles.', ['eligible_count' => count($eligible_items_packets), 'module_id' => $module_id]);

        if (empty($eligible_items_packets)) {
            return ['status' => 'no_new_items', 'message' => __('No new Instagram posts found matching the criteria.', 'data-machine')];
        }

        return $eligible_items_packets;
    }

    /**
     * Fetch recent post URLs from a public Instagram profile.
     * Uses basic scraping, prone to breaking.
     *
     * @param string $username
     * @param int $limit
     * @return array Array of post URLs
     * @throws Exception If scraping fails fundamentally.
     */
    private function fetch_recent_post_urls(string $username, int $limit = 5): array {
        $profile_url = "https://www.instagram.com/{$username}/";
        $this->logger?->debug('Instagram Input: Fetching profile HTML', ['url' => $profile_url]);

        $response = wp_remote_get($profile_url, ['timeout' => 15]);

        if (is_wp_error($response)) {
            $this->logger?->error('Instagram Input: Failed to fetch profile URL (wp_remote_get error)', ['url' => $profile_url, 'error' => $response->get_error_message()]);
            throw new Exception('Failed to fetch Instagram profile page: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $html = wp_remote_retrieve_body($response);

        if ($response_code !== 200 || empty($html)) {
             $this->logger?->error('Instagram Input: Failed to fetch profile URL (non-200 response or empty body)', ['url' => $profile_url, 'code' => $response_code]);
             throw new Exception("Failed to fetch Instagram profile page (Status: {$response_code}). Profile might be private or URL incorrect.");
        }

        // Look for window.__additionalDataLoaded('/username/', {...}); pattern
        // Updated regex based on potential structure change
        if (preg_match('#<script type="text/javascript">window\.__additionalDataLoaded\([^)]+\);</script>#', $html, $matches) || 
            preg_match('#<script type="text/javascript">window\._sharedData = (.*);</script>#', $html, $matches)) 
        {
             $this->logger?->debug('Instagram Input: Found _sharedData or __additionalDataLoaded pattern.', ['profile' => $username]);
             // Note: This simplified regex for __additionalDataLoaded doesn't capture the JSON directly anymore.
             // Need to adjust logic below if this pattern matches, or improve regex further.
             // For now, primarily relying on the _sharedData pattern for JSON extraction.
            $json = isset($matches[1]) ? json_decode($matches[1], true) : null; // Only decode if _sharedData matched
            $edges = [];
            // Try common paths for timeline media
            $paths_to_try = [
                ['graphql', 'user', 'edge_owner_to_timeline_media', 'edges'], // Newer structure?
                ['entry_data', 'ProfilePage', 0, 'graphql', 'user', 'edge_owner_to_timeline_media', 'edges'] // Older structure
            ];

            foreach ($paths_to_try as $path) {
                $temp_edges = $json;
                foreach ($path as $key) {
                    if (isset($temp_edges[$key])) {
                        $temp_edges = $temp_edges[$key];
                    } else {
                        $temp_edges = null;
                        break;
                    }
                }
                if ($temp_edges !== null && is_array($temp_edges)) {
                    $edges = $temp_edges;
                    $this->logger?->debug('Instagram Input: Extracted edges using path.', ['profile' => $username, 'path_used' => implode('.', $path), 'edge_count' => count($edges)]);
                    break;
                }
            }

            if (empty($edges)) {
                 $this->logger?->warning('Instagram Input: Could not find media edges in JSON data.', ['profile' => $username]);
                 // Dont throw exception here, try regex fallback
            }

            $urls = [];
            foreach ($edges as $edge) {
                if (isset($edge['node']['shortcode'])) {
                    $urls[] = 'https://www.instagram.com/p/' . $edge['node']['shortcode'] . '/';
                    if (count($urls) >= $limit) break;
                }
            }
             $this->logger?->debug('Instagram Input: Extracted URLs from JSON.', ['profile' => $username, 'count' => count($urls)]);
            if (!empty($urls)) return $urls; // Return if found via JSON
        }

        // Fallback: Try to extract post URLs with regex (less reliable)
         $this->logger?->debug('Instagram Input: JSON extraction failed or yielded no URLs, attempting regex fallback.', ['profile' => $username]);
        preg_match_all('/"shortcode":"(.*?)"/', $html, $matches);
        if (empty($matches[1])) {
             $this->logger?->warning('Instagram Input: Regex fallback failed to find any shortcodes.', ['profile' => $username]);
            return []; // No URLs found
        }
        $shortcodes = array_unique($matches[1]);
        $urls = [];
        foreach ($shortcodes as $code) {
            $urls[] = 'https://www.instagram.com/p/' . $code . '/';
            if (count($urls) >= $limit) break;
        }
         $this->logger?->debug('Instagram Input: Extracted URLs via regex fallback.', ['profile' => $username, 'count' => count($urls)]);
        return $urls;
    }

    /**
     * Scrape caption and image from a public Instagram post URL.
     * Uses basic scraping, prone to breaking.
     *
     * @param string $post_url
     * @return array|false Array with 'caption', 'image_url', 'post_url' or false on failure.
     */
    private function scrape_post_data(string $post_url): array|false {
        $this->logger?->debug('Instagram Input: Scraping post data', ['url' => $post_url]);
        $response = wp_remote_get($post_url, ['timeout' => 15]);

        if (is_wp_error($response)) {
             $this->logger?->error('Instagram Input: Failed to fetch post URL (wp_remote_get error)', ['url' => $post_url, 'error' => $response->get_error_message()]);
            return false;
        }
        $response_code = wp_remote_retrieve_response_code($response);
        $html = wp_remote_retrieve_body($response);

        if ($response_code !== 200 || empty($html)) {
             $this->logger?->error('Instagram Input: Failed to fetch post URL (non-200 response or empty body)', ['url' => $post_url, 'code' => $response_code]);
            return false;
        }

        // Extract caption from meta property (more robust might be needed)
        $caption = '';
        if (preg_match('/<meta property="og:description" content="(.*?)"/s', $html, $matches)) { // Use /s modifier
            $caption = html_entity_decode($matches[1]);
            // Clean up common Instagram additions
            $caption = preg_replace('/^\d+ Likes, \d+ Comments - .*? on Instagram: "/', '', $caption); // Remove likes/comments prefix
            $caption = preg_replace('/"$/', '', $caption); // Remove trailing quote
            $caption = trim($caption);
        }

        // Extract image URL from meta property
        $image_url = '';
        if (preg_match('/<meta property="og:image" content="([^"]+)"/', $html, $matches)) {
            $image_url = html_entity_decode($matches[1]); // Use html_entity_decode here too
        }

        $this->logger?->debug('Instagram Input: Scraped data results', ['url' => $post_url, 'caption_found' => !empty($caption), 'image_found' => !empty($image_url)]);

        return [
            'caption' => $caption,
            'image_url' => $image_url,
            'post_url' => $post_url,
        ];
    }

    /**
     * Sanitize settings for the Instagram input handler.
     *
     * @param array $raw_settings
     * @return array
     */
    public function sanitize_settings(array $raw_settings): array {
        $sanitized = [];
        $profiles = explode("\n", $raw_settings['target_profiles'] ?? '');
        // Sanitize each profile handle: allow letters, numbers, periods, underscores
        $sanitized_profiles = [];
        foreach ($profiles as $profile) {
            $clean_profile = trim($profile);
            if (!empty($clean_profile)) {
                 // Remove leading @ if present
                 $clean_profile = ltrim($clean_profile, '@');
                 // Basic validation for allowed characters
                 if (preg_match('/^[a-zA-Z0-9._]+$/', $clean_profile)) {
                     $sanitized_profiles[] = $clean_profile;
                 }
            }
        }
        $sanitized['target_profiles'] = implode("\n", array_unique($sanitized_profiles)); // Ensure unique profiles
        $sanitized['post_limit'] = min(20, max(1, absint($raw_settings['post_limit'] ?? 5)));
        return $sanitized;
    }
}