<?php
/**
 * Engine Data Object
 *
 * Encapsulates the "Engine Data" array which persists across the pipeline execution.
 * Provides standardized methods for applying this data (Source URLs, Images) to
 * various outputs (WordPress Posts, Social Media, etc.).
 *
 * @package DataMachine\Core
 * @since 0.2.1
 */

namespace DataMachine\Core;

if (!defined('ABSPATH')) {
    exit;
}

class EngineData {

    /**
     * The raw engine data array.
     *
     * @var array
     */
    private array $data;

    /**
     * The Job ID associated with this data.
     *
     * @var int|string|null
     */
    private $job_id;

    /**
     * Constructor.
     *
     * @param array $data Raw engine data array.
     * @param int|string|null $job_id Optional Job ID for context/logging.
     */
    public function __construct(array $data, $job_id = null) {
        $this->data = $data;
        $this->job_id = $job_id;
    }

    /**
     * Get a value from the engine data.
     *
     * @param string $key Data key.
     * @param mixed $default Default value if key not found.
     * @return mixed
     */
    public function get(string $key, $default = null) {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        $metadata = $this->data['metadata'] ?? [];
        if (is_array($metadata) && array_key_exists($key, $metadata)) {
            return $metadata[$key];
        }

        return $default;
    }

    /**
     * Get the Source URL.
     *
     * @return string|null Source URL or null.
     */
    public function getSourceUrl(): ?string {
        $url = $this->get('source_url');
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /**
     * Get the Image File Path (from Files Repository).
     *
     * @return string|null Absolute file path or null.
     */
    public function getImagePath(): ?string {
        return $this->get('image_file_path');
    }

    /**
     * Apply Source Attribution to Content.
     *
     * Appends the source URL to the content based on configuration.
     * Handles both Gutenberg Blocks and Plain Text.
     *
     * @param string $content The content to modify.
     * @param array $config Handler configuration (checks 'include_source').
     * @return string Modified content.
     */
    public function applySourceAttribution(string $content, array $config): string {
        // 1. Check Configuration
        if (empty($config['include_source'])) {
            return $content;
        }

        // 2. Get & Validate URL
        $source_url = $this->getSourceUrl();
        if (!$source_url) {
            return $content;
        }

        // 3. Apply based on Content Type
        if (has_blocks($content)) {
            return $content . $this->generateSourceBlock($source_url);
        }

        return $content . "\n\nSource: " . esc_url($source_url);
    }

    /**
     * Attach the Engine Image to a WordPress Post.
     *
     * Sideloads the image from the Files Repository into the Media Library
     * and sets it as the Featured Image.
     *
     * @param int $post_id The WordPress Post ID.
     * @param array $config Handler configuration (checks 'enable_images').
     * @return int|null Attachment ID on success, null on failure.
     */
    public function attachImageToPost(int $post_id, array $config): ?int {
        // 1. Check Configuration
        if (empty($config['enable_images'])) {
            return null;
        }

        // 2. Get Image Path
        $image_path = $this->getImagePath();
        if (!$image_path || !file_exists($image_path)) {
            $this->log('warning', 'EngineData: Image file not found for attachment', ['path' => $image_path]);
            return null;
        }

        // 3. Validate Image (Basic check)
        $file_type = wp_check_filetype($image_path);
        if (!str_starts_with($file_type['type'] ?? '', 'image/')) {
             $this->log('warning', 'EngineData: File is not a valid image', ['path' => $image_path, 'type' => $file_type['type']]);
             return null;
        }

        // 4. Sideload to Media Library
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $file_array = [
            'name' => basename($image_path),
            'tmp_name' => $image_path // media_handle_sideload moves this file
        ];

        // Note: media_handle_sideload expects the file to be in the uploads dir or temp dir.
        // Since our repo files are persistent, we should COPY it to a temp location first
        // so the original repo file isn't moved/deleted by WordPress.
        $temp_file = sys_get_temp_dir() . '/' . basename($image_path);
        if (!copy($image_path, $temp_file)) {
             $this->log('error', 'EngineData: Failed to copy image to temp dir', ['path' => $image_path]);
             return null;
        }
        $file_array['tmp_name'] = $temp_file;

        $attachment_id = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($attachment_id)) {
            $this->log('error', 'EngineData: Failed to create media attachment', [
                'error' => $attachment_id->get_error_message(),
                'post_id' => $post_id
            ]);
            @unlink($temp_file); // Cleanup if failed
            return null;
        }

        // 5. Set Featured Image
        if (set_post_thumbnail($post_id, $attachment_id)) {
            $this->log('debug', 'EngineData: Attached featured image', ['post_id' => $post_id, 'attachment_id' => $attachment_id]);
            return $attachment_id;
        }

        return null;
    }

    /**
     * Generate Gutenberg Source Block.
     *
     * @param string $url
     * @return string
     */
    private function generateSourceBlock(string $url): string {
        $sanitized_url = esc_url($url);
        $separator = "\n\n<!-- wp:separator --><hr class=\"wp-block-separator has-alpha-channel-opacity\"/><!-- /wp:separator -->\n\n";
        $paragraph = "<!-- wp:paragraph --><p>Source: <a href=\"{$sanitized_url}\">{$sanitized_url}</a></p><!-- /wp:paragraph -->";
        return $separator . $paragraph;
    }

    /**
     * Internal Logger Helper.
     */
    private function log(string $level, string $message, array $context = []): void {
        if ($this->job_id) {
            $context['job_id'] = $this->job_id;
        }
        do_action('datamachine_log', $level, $message, $context);
    }

    /**
     * Return the raw snapshot array.
     */
    public function all(): array {
        return $this->data;
    }

    /**
     * Get stored job context (flow_id, pipeline_id, etc.).
     */
    public function getJobContext(): array {
        return is_array($this->data['job'] ?? null) ? $this->data['job'] : [];
    }

    /**
     * Get full flow configuration snapshot.
     */
    public function getFlowConfig(): array {
        return is_array($this->data['flow_config'] ?? null) ? $this->data['flow_config'] : [];
    }

    /**
     * Get configuration for a specific flow step.
     */
    public function getFlowStepConfig(string $flow_step_id): array {
        $flow_config = $this->getFlowConfig();
        return $flow_config[$flow_step_id] ?? [];
    }

    /**
     * Get stored pipeline configuration snapshot.
     */
    public function getPipelineConfig(): array {
        return is_array($this->data['pipeline_config'] ?? null) ? $this->data['pipeline_config'] : [];
    }
}
