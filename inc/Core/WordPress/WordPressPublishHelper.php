<?php
/**
 * WordPress Publish Helper
 *
 * WordPress-specific publishing operations including media attachment and content modification.
 * Provides centralized utilities for WordPress publishing handlers.
 *
 * @package DataMachine\Core\WordPress
 * @since 0.2.8
 */

namespace DataMachine\Core\WordPress;

if (!defined('ABSPATH')) {
    exit;
}

class WordPressPublishHelper {

    /**
     * Attach image to WordPress post as featured image.
     *
     * Sideloads the image from the Files Repository into the Media Library
     * and sets it as the Featured Image.
     *
     * @param int $post_id The WordPress Post ID.
     * @param string|null $image_path Absolute path to image file.
     * @param array $config Handler configuration (checks 'include_images').
     * @return int|null Attachment ID on success, null on failure.
     */
    public static function attachImageToPost(int $post_id, ?string $image_path, array $config): ?int {
        // 1. Check Configuration
        if (empty($config['include_images'])) {
            return null;
        }

        // 2. Validate Image Path
        if (!$image_path || !file_exists($image_path)) {
            self::log('warning', 'WordPressPublishHelper: Image file not found for attachment', ['path' => $image_path]);
            return null;
        }

        // 3. Validate Image Type
        $file_type = wp_check_filetype($image_path);
        if (!str_starts_with($file_type['type'] ?? '', 'image/')) {
             self::log('warning', 'WordPressPublishHelper: File is not a valid image', ['path' => $image_path, 'type' => $file_type['type']]);
             return null;
        }

        // 4. Sideload to Media Library
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $file_array = [
            'name' => basename($image_path),
            'tmp_name' => $image_path
        ];

        // Copy to temp location so original repo file isn't moved/deleted by WordPress
        $temp_file = sys_get_temp_dir() . '/' . basename($image_path);
        if (!copy($image_path, $temp_file)) {
             self::log('error', 'WordPressPublishHelper: Failed to copy image to temp dir', ['path' => $image_path]);
             return null;
        }
        $file_array['tmp_name'] = $temp_file;

        $attachment_id = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($attachment_id)) {
            self::log('error', 'WordPressPublishHelper: Failed to create media attachment', [
                'error' => $attachment_id->get_error_message(),
                'post_id' => $post_id
            ]);
            @unlink($temp_file);
            return null;
        }

        // 5. Set Featured Image
        if (set_post_thumbnail($post_id, $attachment_id)) {
            self::log('debug', 'WordPressPublishHelper: Attached featured image', ['post_id' => $post_id, 'attachment_id' => $attachment_id]);
            return $attachment_id;
        }

        return null;
    }

    /**
     * Apply source attribution to content.
     *
     * Appends the source URL to the content based on configuration.
     * Handles both Gutenberg Blocks and Plain Text.
     *
     * @param string $content The content to modify.
     * @param string|null $source_url Source URL to append.
     * @param array $config Handler configuration (checks 'link_handling').
     * @return string Modified content.
     */
    public static function applySourceAttribution(string $content, ?string $source_url, array $config): string {
        // 1. Check Configuration
        $link_handling = $config['link_handling'] ?? 'none';
        if ($link_handling !== 'append') {
            return $content;
        }

        // 2. Validate URL
        if (!$source_url || !filter_var($source_url, FILTER_VALIDATE_URL)) {
            return $content;
        }

        // 3. Apply based on Content Type
        if (has_blocks($content)) {
            return $content . self::generateSourceBlock($source_url);
        }

        return $content . "\n\nSource: " . esc_url($source_url);
    }

    /**
     * Generate Gutenberg Source Block.
     *
     * @param string $url Source URL
     * @return string Gutenberg block HTML
     */
    private static function generateSourceBlock(string $url): string {
        $sanitized_url = esc_url($url);
        $separator = "\n\n<!-- wp:separator --><hr class=\"wp-block-separator has-alpha-channel-opacity\"/><!-- /wp:separator -->\n\n";
        $paragraph = "<!-- wp:paragraph --><p>Source: <a href=\"{$sanitized_url}\">{$sanitized_url}</a></p><!-- /wp:paragraph -->";
        return $separator . $paragraph;
    }

    /**
     * Internal logger helper.
     *
     * @param string $level Log level (debug, info, warning, error)
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private static function log(string $level, string $message, array $context = []): void {
        do_action('datamachine_log', $level, $message, $context);
    }
}
