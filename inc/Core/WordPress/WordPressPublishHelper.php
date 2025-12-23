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
            wp_delete_file($temp_file);
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
     * Strip AI-generated source attribution from content before system adds its own.
     *
     * Removes source attribution patterns that match the provided URL to prevent
     * duplicate source links when the system appends its own attribution.
     *
     * @param string $content The content to sanitize.
     * @param string|null $source_url Source URL to match for stripping.
     * @return string Sanitized content.
     */
    public static function stripDuplicateSourceAttribution(string $content, ?string $source_url): string {
        if (!$source_url || !filter_var($source_url, FILTER_VALIDATE_URL)) {
            return $content;
        }

        $escaped_url = preg_quote($source_url, '/');

        // Strip <p> tags containing "Source:" (case-insensitive) and matching URL
        $content = preg_replace(
            '/<p[^>]*>.*?source\s*:.*?' . $escaped_url . '.*?<\/p>\s*/is',
            '',
            $content
        );

        // Strip plain text "Source: URL" patterns (with optional link markup)
        $content = preg_replace(
            '/\n*source\s*:\s*(<a[^>]*>)?\s*' . $escaped_url . '\s*(<\/a>)?\s*\n*/is',
            '',
            $content
        );

        // Strip standalone URL on its own line at end of content
        $content = preg_replace(
            '/\n+' . $escaped_url . '\s*$/i',
            '',
            $content
        );

        return trim($content);
    }

    /**
     * Strip featured image references from content to prevent duplication.
     *
     * Removes image tags, figure blocks, and associated captions that reference
     * the featured image, since the system sets it separately.
     *
     * @param string $content The content to sanitize.
     * @param string|null $image_path Absolute path to the featured image.
     * @return string Sanitized content.
     */
    public static function stripFeaturedImageFromContent(string $content, ?string $image_path): string {
        if (!$image_path) {
            return $content;
        }

        $filename = basename($image_path);
        if (empty($filename)) {
            return $content;
        }

        $escaped_filename = preg_quote($filename, '/');

        // Strip <figure> blocks containing matching image
        $content = preg_replace(
            '/<figure[^>]*>.*?<img[^>]*' . $escaped_filename . '[^>]*>.*?<\/figure>\s*/is',
            '',
            $content
        );

        // Strip standalone <img> tags with matching filename
        $content = preg_replace(
            '/<img[^>]*' . $escaped_filename . '[^>]*>\s*/is',
            '',
            $content
        );

        // Strip markdown images with matching filename
        $content = preg_replace(
            '/!\[[^\]]*\]\([^)]*' . $escaped_filename . '[^)]*\)\s*/i',
            '',
            $content
        );

        // Strip caption paragraphs that immediately follow (contain "shared via" or similar patterns)
        $content = preg_replace(
            '/<p[^>]*>[^<]*\(shared via[^)]*\)[^<]*<\/p>\s*/is',
            '',
            $content
        );

        // Strip empty figure tags that might remain
        $content = preg_replace(
            '/<figure[^>]*>\s*<\/figure>\s*/is',
            '',
            $content
        );

        return trim($content);
    }

    /**
     * Apply source attribution to content.
     *
     * @param string $content The content to modify.
     * @param string|null $source_url Source URL to append.
     * @param array $config Handler configuration (checks 'link_handling').
     * @return string Modified content.
     */
    public static function applySourceAttribution(string $content, ?string $source_url, array $config): string {
        if (($config['link_handling'] ?? 'append') !== 'append') {
            return $content;
        }

        if (!$source_url || !filter_var($source_url, FILTER_VALIDATE_URL)) {
            return $content;
        }

        $sanitized_url = esc_url($source_url);
        return $content . "\n\n<p><strong>Source:</strong> <a href=\"{$sanitized_url}\">{$sanitized_url}</a></p>";
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
