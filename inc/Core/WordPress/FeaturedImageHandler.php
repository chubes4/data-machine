<?php
/**
 * Modular featured image processing for WordPress publish operations.
 *
 * Handles downloading, validating, and attaching featured images to WordPress posts.
 * Supports configuration hierarchy with system defaults and handler-specific settings.
 *
 * @package DataMachine\Core\Steps\Publish\Handlers\WordPress
 */

namespace DataMachine\Core\WordPress;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Featured Image Handler
 *
 * Processes and attaches featured images to WordPress posts during publishing.
 * Downloads images from URLs, validates them, and attaches to posts.
 */
class FeaturedImageHandler {

    /**
     * Process and attach featured image to post.
      *
      * Uses repository image file from engine data, validates it, and attaches
      * as featured image to the specified post.
      *
      * @param int $post_id WordPress post ID
      * @param array $engine_data Engine data containing image_file_path
      * @param array $handler_config Handler configuration settings
      * @return array|null {
      *     @type int $attachment_id WordPress attachment ID
      *     @type string $url Image URL
      * } or null if processing failed
     */
    public function processImage(int $post_id, array $engine_data, array $handler_config): ?array {
        if (!$this->isImageHandlingEnabled($handler_config)) {
            return null;
        }

        $image_file_path = $engine_data['image_file_path'] ?? null;
        if (empty($image_file_path)) {
            return null;
        }

        $image_validator = apply_filters('datamachine_get_image_validator', null);
        $validation = $image_validator->validate_repository_file($image_file_path);

        if (!$validation['valid']) {
            $this->logImageOperation('error', 'WordPress Featured Image: Repository image validation failed', [
                'image_file_path' => $image_file_path,
                'errors' => $validation['errors']
            ]);
            return ['success' => false, 'error' => implode(', ', $validation['errors'])];
        }

        return $this->attachRepositoryFile($post_id, $image_file_path);
    }

    /**
     * Check if image handling is enabled for this handler.
     *
     * Uses configuration hierarchy: system defaults override handler config.
     *
     * @param array $handler_config Handler-specific configuration
     * @return bool True if image handling is enabled
     */
    public function isImageHandlingEnabled(array $handler_config): bool {
        $all_settings = get_option('datamachine_settings', []);
        $wp_settings = $all_settings['wordpress_settings'] ?? [];

        if (isset($wp_settings['default_enable_images'])) {
            return (bool) $wp_settings['default_enable_images'];
        }

        return (bool) ($handler_config['enable_images'] ?? false);
    }

    /**
     * Validate image URL format.
     *
     * @param string $image_url URL to validate
     * @return bool True if URL is valid
     */
    private function validateImageUrl(string $image_url): bool {
        return filter_var($image_url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Attach repository image file as featured image to post.
     *
     * Uses existing repository file, creates WordPress media attachment,
     * and sets it as the featured image for the post.
     *
     * @param int $post_id WordPress post ID
     * @param string $image_file_path Repository image file path
     * @return array {
     *     @type bool $success Whether operation succeeded
     *     @type string $error Error message if failed
     *     @type int $attachment_id Attachment ID if successful
     * }
     */
    private function attachRepositoryFile(int $post_id, string $image_file_path): array {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $file_array = [
            'name' => basename($image_file_path),
            'tmp_name' => $image_file_path
        ];

        $attachment_id = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($attachment_id)) {
            $this->logImageOperation('warning', 'WordPress Featured Image: Failed to create attachment from repository file', [
                'image_file_path' => $image_file_path,
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

        $this->logImageOperation('debug', 'WordPress Featured Image: Successfully set featured image from repository file', [
            'post_id' => $post_id,
            'attachment_id' => $attachment_id,
            'image_file_path' => $image_file_path
        ]);

        return [
            'success' => true,
            'attachment_id' => $attachment_id,
            'attachment_url' => wp_get_attachment_url($attachment_id)
        ];

    }

    /**
     * Set attachment as featured image for post.
     *
     * @param int $post_id WordPress post ID
     * @param int $attachment_id WordPress attachment ID
     * @return bool True on success
     */
    private function setFeaturedImage(int $post_id, int $attachment_id): bool {
        return set_post_thumbnail($post_id, $attachment_id);
    }

    /**
     * Clean up temporary downloaded files.
     *
     * @param string $temp_file Path to temporary file
     */
    private function cleanupTempFiles(string $temp_file): void {
        if (file_exists($temp_file)) {
            wp_delete_file($temp_file);
        }
    }

    /**
     * Log image processing operations.
     *
     * @param string $level Log level (debug, info, warning, error)
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private function logImageOperation(string $level, string $message, array $context): void {
        do_action('datamachine_log', $level, $message, $context);
    }
}