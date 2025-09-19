<?php
/**
 * Modular featured image processing for WordPress publish operations.
 *
 * Implements configuration hierarchy where system defaults override handler config,
 * WordPress media library integration with comprehensive error handling,
 * and automatic temporary file cleanup.
 *
 * @package DataMachine
 * @subpackage Core\Steps\Publish\Handlers\WordPress
 */

namespace DataMachine\Core\Steps\Publish\Handlers\WordPress;

if (!defined('ABSPATH')) {
    exit;
}

class FeaturedImageHandler {

    /**
     * Process featured image for WordPress post with configuration hierarchy validation.
     *
     * @param int $post_id WordPress post ID
     * @param array $parameters Tool parameters including image_url
     * @param array $handler_config Handler configuration
     * @return array|null Processing result with attachment details or null if skipped/disabled
     */
    public function processImage(int $post_id, array $parameters, array $handler_config): ?array {
        if (!$this->isImageHandlingEnabled($handler_config)) {
            return null;
        }

        $image_url = $parameters['image_url'] ?? null;
        if (empty($image_url) || !$this->validateImageUrl($image_url)) {
            return null;
        }

        return $this->downloadAndAttach($post_id, $image_url);
    }

    /**
     * Check if image handling is enabled based on configuration hierarchy.
     * System defaults always override handler config when set.
     *
     * @param array $handler_config Handler configuration
     * @return bool True if image handling is enabled
     */
    public function isImageHandlingEnabled(array $handler_config): bool {
        $all_settings = get_option('data_machine_settings', []);
        $wp_settings = $all_settings['wordpress_settings'] ?? [];

        // System default overrides handler config when set
        if (isset($wp_settings['default_enable_images'])) {
            return (bool) $wp_settings['default_enable_images'];
        }

        // Fallback to handler config (default to false if not provided)
        return (bool) ($handler_config['enable_images'] ?? false);
    }

    private function validateImageUrl(string $image_url): bool {
        return filter_var($image_url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Download image and create WordPress attachment using media_handle_sideload().
     * Includes comprehensive error handling and automatic temporary file cleanup.
     *
     * @param int $post_id WordPress post ID
     * @param string $image_url Image URL to download
     * @return array Processing result with success status, attachment_id, and attachment_url
     */
    private function downloadAndAttach(int $post_id, string $image_url): array {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $temp_file = download_url($image_url);
        if (is_wp_error($temp_file)) {
            $this->logImageOperation('warning', 'WordPress Featured Image: Failed to download image', [
                'image_url' => $image_url,
                'error' => $temp_file->get_error_message()
            ]);
            return ['success' => false, 'error' => 'Failed to download image'];
        }

        $file_array = [
            'name' => basename($image_url),
            'tmp_name' => $temp_file
        ];

        $attachment_id = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($attachment_id)) {
            $this->cleanupTempFiles($temp_file);
            $this->logImageOperation('warning', 'WordPress Featured Image: Failed to create attachment', [
                'image_url' => $image_url,
                'error' => $attachment_id->get_error_message()
            ]);
            return ['success' => false, 'error' => 'Failed to create media attachment'];
        }

        $result = $this->setFeaturedImage($post_id, $attachment_id);

        if (!$result) {
            $this->logImageOperation('warning', 'WordPress Featured Image: Failed to set featured image', [
                'post_id' => $post_id,
                'attachment_id' => $attachment_id
            ]);
            return ['success' => false, 'error' => 'Failed to set featured image'];
        }

        $this->logImageOperation('debug', 'WordPress Featured Image: Successfully set featured image', [
            'post_id' => $post_id,
            'attachment_id' => $attachment_id,
            'image_url' => $image_url
        ]);

        return [
            'success' => true,
            'attachment_id' => $attachment_id,
            'attachment_url' => wp_get_attachment_url($attachment_id)
        ];

    }

    private function setFeaturedImage(int $post_id, int $attachment_id): bool {
        return set_post_thumbnail($post_id, $attachment_id);
    }

    private function cleanupTempFiles(string $temp_file): void {
        if (file_exists($temp_file)) {
            wp_delete_file($temp_file);
        }
    }

    private function logImageOperation(string $level, string $message, array $context): void {
        do_action('dm_log', $level, $message, $context);
    }
}