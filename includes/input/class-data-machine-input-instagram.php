<?php
/**
 * Instagram Input Handler (HTML Scraping Version)
 *
 * @package Data_Machine
 */

class Data_Machine_Input_Instagram implements Data_Machine_Input_Handler_Interface {

    public static function get_label() {
        return 'Instagram Profile (Public Scrape)';
    }

    public static function get_settings_fields() {
        return [
            'target_profiles' => [
                'type' => 'textarea',
                'label' => 'Target Instagram Profiles',
                'description' => 'Enter one Instagram profile handle per line to monitor (without @ symbol)',
                'rows' => 5,
                'required' => true,
            ],
            'post_limit' => [
                'type' => 'number',
                'label' => 'Post Limit',
                'description' => 'Maximum number of recent posts to fetch per profile',
                'default' => 5,
                'min' => 1,
                'max' => 20,
            ],
        ];
    }

    /**
     * Fetch recent post URLs from a public Instagram profile.
     * @param string $username
     * @param int $limit
     * @return array Array of post URLs
     */
    private function fetch_recent_post_urls($username, $limit = 5) {
        $profile_url = "https://www.instagram.com/{$username}/";
        $html = @file_get_contents($profile_url);
        if (!$html) return [];

        // Look for window._sharedData = {...};
        if (preg_match('/<script type="text\/javascript">window\._sharedData = (.*);<\/script>/', $html, $matches)) {
            $json = json_decode($matches[1], true);
            $edges = $json['entry_data']['ProfilePage'][0]['graphql']['user']['edge_owner_to_timeline_media']['edges'] ?? [];
            $urls = [];
            foreach ($edges as $edge) {
                if (isset($edge['node']['shortcode'])) {
                    $urls[] = 'https://www.instagram.com/p/' . $edge['node']['shortcode'] . '/';
                    if (count($urls) >= $limit) break;
                }
            }
            return $urls;
        }

        // Fallback: Try to extract post URLs with regex (less reliable)
        preg_match_all('/"shortcode":"(.*?)"/', $html, $matches);
        $shortcodes = array_unique($matches[1]);
        $urls = [];
        foreach ($shortcodes as $code) {
            $urls[] = 'https://www.instagram.com/p/' . $code . '/';
            if (count($urls) >= $limit) break;
        }
        return $urls;
    }

    /**
     * Scrape caption and image from a public Instagram post URL.
     * @param string $post_url
     * @return array|false
     */
    private function scrape_post_data($post_url) {
        $html = @file_get_contents($post_url);
        if (!$html) return false;

        // Extract caption from meta property
        $caption = '';
        if (preg_match('/<meta property="og:description" content="([^"]+)"/', $html, $matches)) {
            $caption = html_entity_decode($matches[1]);
        }

        // Extract image URL from meta property
        $image_url = '';
        if (preg_match('/<meta property="og:image" content="([^"]+)"/', $html, $matches)) {
            $image_url = $matches[1];
        }

        return [
            'caption' => $caption,
            'image_url' => $image_url,
            'post_url' => $post_url,
        ];
    }

    /**
     * Main data fetching logic.
     * @param array $config
     * @return array
     */
    public function execute($config) {
        if (empty($config['target_profiles'])) {
            return new WP_Error('missing_profiles', 'No target profiles specified');
        }
        $profiles = array_filter(array_map('trim', explode("\n", $config['target_profiles'])));
        $post_limit = isset($config['post_limit']) ? intval($config['post_limit']) : 5;

        $results = [];
        foreach ($profiles as $profile) {
            $post_urls = $this->fetch_recent_post_urls($profile, $post_limit);
            $posts = [];
            foreach ($post_urls as $url) {
                $data = $this->scrape_post_data($url);
                if ($data) $posts[] = $data;
            }
            $results[$profile] = [
                'posts' => $posts,
                'profile_url' => "https://www.instagram.com/{$profile}/",
            ];
        }
        return [
            'data_export' => $results,
            'timestamp' => current_time('mysql'),
            'source' => 'instagram',
        ];
    }

    /**
     * Prepare the collected data into a standardized input packet for processing.
     * @param array $collected_data
     * @return array
     */
    public function prepare_data_packet($collected_data) {
        if (is_wp_error($collected_data)) {
            return [
                'content_string' => 'Error fetching Instagram data: ' . $collected_data->get_error_message(),
                'metadata' => [
                    'source' => 'instagram',
                    'timestamp' => current_time('mysql'),
                    'error_code' => $collected_data->get_error_code()
                ]
            ];
        }
        $content = "Instagram Data Fetch Results:\n";
        $content .= "Timestamp: " . $collected_data['timestamp'] . "\n\n";
        $profiles_processed = [];
        foreach ($collected_data['data_export'] as $profile => $profile_data) {
            $profiles_processed[] = $profile;
            $content .= "--- Profile: " . esc_html($profile) . " ---\n";
            $content .= "Profile URL: " . esc_url($profile_data['profile_url']) . "\n";
            $content .= "Posts Found: " . count($profile_data['posts']) . "\n\n";
            if (empty($profile_data['posts'])) {
                $content .= "No new posts found for this profile.\n\n";
            } else {
                foreach ($profile_data['posts'] as $post) {
                    $content .= "Post URL: " . esc_url($post['post_url'] ?? '') . "\n";
                    $content .= "Caption: " . esc_html($post['caption'] ?? '') . "\n";
                    $content .= "Image URL: " . esc_url($post['image_url'] ?? '') . "\n";
                    $content .= "--------------------\n";
                }
            }
            $content .= "\n";
        }
        return [
            'content_string' => $content,
            'metadata' => [
                'source' => 'instagram',
                'profiles' => $profiles_processed,
                'timestamp' => $collected_data['timestamp'],
            ]
        ];
    }

    public function get_input_data(array $post_data, array $file_data, array $config, int $user_id): array {
        $collected_data = $this->execute($config['instagram'] ?? []);
        $data_packet = $this->prepare_data_packet($collected_data);
        return [$data_packet];
    }

    public function sanitize_settings(array $raw_settings): array {
        $sanitized = [];
        $profiles = explode("\n", $raw_settings['target_profiles'] ?? '');
        $sanitized_profiles = array_filter(array_map('sanitize_text_field', array_map('trim', $profiles)));
        $sanitized['target_profiles'] = implode("\n", $sanitized_profiles);
        $sanitized['post_limit'] = min(20, max(1, absint($raw_settings['post_limit'] ?? 5)));
        return $sanitized;
    }
}