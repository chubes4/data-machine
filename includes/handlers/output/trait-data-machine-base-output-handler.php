<?php
/**
 * Trait for shared logic among Data Machine output handlers.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/output
 * @since      0.14.0
 */
trait Data_Machine_Base_Output_Handler {
    /**
     * Standardized output data packet structure:
     * [
     *   'status' => (string), // 'success' or 'error'
     *   'message' => (string),
     *   ...handler-specific keys (e.g., post_id, tweet_id, etc.)
     * ]
     */

    /**
     * Append an image to the content if available in metadata.
     *
     * @param string $content
     * @param array $input_metadata
     * @return string
     */
    protected function prepend_image_if_available(string $content, array $input_metadata): string {
        if (!empty($input_metadata['image_source_url'])) {
            $image_url = esc_url($input_metadata['image_source_url']);
            $alt_text = !empty($input_metadata['original_title']) ? esc_attr($input_metadata['original_title']) : esc_attr('Source Image');
            $image_tag = sprintf('<img src="%s" alt="%s" /><br /><br />', $image_url, $alt_text);
            return $image_tag . $content;
        }
        return $content;
    }

    /**
     * Append a source link to the content if available in metadata.
     *
     * @param string $content
     * @param array $input_metadata
     * @return string
     */
    protected function append_source_if_available(string $content, array $input_metadata): string {
        if (!empty($input_metadata['source_url'])) {
            $source_url = esc_url($input_metadata['source_url']);
            $source_name = esc_html($input_metadata['original_title'] ?? 'Original Source');
            if (!empty($input_metadata['subreddit'])) {
                $source_name = 'r/' . esc_html($input_metadata['subreddit']);
            }
            $source_link_string = sprintf('Source: <a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', $source_url, $source_name);
            $content .= "\n\n" . $source_link_string;
        }
        return $content;
    }

    /**
     * Helper for taxonomy assignment (category/tag/custom) for local/remote publish.
     * This is a stub for now; actual logic should be implemented in the handler and can call this for shared steps.
     *
     * @param int $post_id
     * @param array $parsed_data
     * @param array $config
     * @return array Assigned taxonomy info for reporting
     */
    protected function assign_taxonomies($post_id, $parsed_data, $config): array {
        // This is a stub. Actual logic should be implemented in the handler.
        // Return structure for reporting:
        return [
            'assigned_category_id' => null,
            'assigned_category_name' => null,
            'assigned_tag_ids' => [],
            'assigned_tag_names' => [],
            'assigned_custom_taxonomies' => [],
        ];
    }
} 