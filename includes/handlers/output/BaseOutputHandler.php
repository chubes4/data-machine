<?php
/**
 * Base class for Data Machine output handlers.
 * Consolidates common functionality and patterns shared across all output handlers.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/handlers/output
 * @since      0.15.0
 */

namespace DataMachine\Handlers\Output;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class BaseOutputHandler {
    
    // Common dependencies accessed via service locator
    protected $logger;
    
    /**
     * Constructor using service locator pattern.
     * No manual dependency injection needed.
     */
    public function __construct() {
        $this->init_dependencies();
    }
    
    /**
     * Initialize dependencies via service locator.
     */
    protected function init_dependencies() {
        global $data_machine_container;
        
        // Get dependencies from global container with better error handling
        $this->logger = $data_machine_container['logger'] ?? null;
        
        // Log if container is not available for debugging
        if (empty($data_machine_container) && WP_DEBUG) {
            error_log('Data Machine: Global container not available in BaseOutputHandler');
        }
    }
    
    /**
     * Validate user ID context required by most handlers.
     *
     * @param int $user_id User ID to validate
     * @param string $handler_context Handler name for logging context
     * @return bool True if valid
     * @throws WP_Error If user ID is invalid
     */
    protected function validate_user_context($user_id, $handler_context = 'Output Handler') {
        if (empty($user_id)) {
            $this->logger && $this->logger->error($handler_context . ': User ID context is missing.');
            throw new WP_Error('handler_missing_user_id', __('Cannot process without user account context.', 'data-machine'));
        }
        
        return true;
    }
    
    /**
     * Validate content is not empty after AI processing.
     *
     * @param string $content Content to validate
     * @param string $handler_context Handler name for logging context
     * @param int $user_id User ID for logging context
     * @return bool True if content is valid
     * @throws WP_Error If content is empty
     */
    protected function validate_content_not_empty($content, $handler_context = 'Output Handler', $user_id = 0) {
        $content = trim($content);
        if (empty($content)) {
            $this->logger && $this->logger->warning($handler_context . ': Parsed content is empty.', ['user_id' => $user_id]);
            throw new WP_Error('handler_empty_content', __('Cannot process empty content. Check AI response and parsing.', 'data-machine'));
        }
        
        return true;
    }
    
    /**
     * Load and instantiate AI Response Parser with error handling.
     * Common pattern used across 7 out of 8 handlers.
     *
     * @param string $ai_output_string Raw AI response to parse
     * @param string $handler_context Handler name for logging context
     * @return \DataMachine\Engine\Filters\AiResponseParser|WP_Error Parser instance or error
     */
    protected function load_ai_response_parser($ai_output_string, $handler_context = 'Output Handler') {
        // Check if class exists (should be autoloaded via PSR-4)
        if (!class_exists('\DataMachine\Engine\Filters\AiResponseParser')) {
            $this->logger && $this->logger->error($handler_context . ': AI Response Parser class not found - check autoloader.');
            return new WP_Error('parser_not_found', __('AI Response Parser not available.', 'data-machine'));
        }
        
        try {
            $parser = new \DataMachine\Engine\Filters\AiResponseParser($ai_output_string);
            $parser->parse();
            
            $this->logger && $this->logger->info($handler_context . ': Successfully loaded and parsed AI response.');
            return $parser;
            
        } catch (Exception $e) {
            $this->logger && $this->logger->error($handler_context . ': Failed to parse AI response.', [
                'error' => $e->getMessage()
            ]);
            return new WP_Error('parser_execution_failed', __('Failed to parse AI response: ', 'data-machine') . $e->getMessage());
        }
    }
    
    /**
     * Build standard success response structure.
     * Used by all handlers with consistent format.
     *
     * @param string $message Success message
     * @param string $output_url URL to view/access the published content
     * @param array $additional_data Handler-specific response data
     * @return array Standard success response
     */
    protected function build_success_response($message, $output_url, $additional_data = []) {
        $response = [
            'status' => 'success',
            'message' => $message,
            'output_url' => $output_url
        ];
        
        // Merge any handler-specific data (post_id, tweet_id, etc.)
        return array_merge($response, $additional_data);
    }
    
    /**
     * Build standard error response structure.
     * Converts WP_Error to consistent array format.
     *
     * @param WP_Error $error Error object
     * @param string $handler_context Handler name for logging
     * @return array Standard error response
     */
    protected function build_error_response($error, $handler_context = 'Output Handler') {
        $this->logger && $this->logger->error($handler_context . ': ' . $error->get_error_message(), [
            'error_code' => $error->get_error_code(),
            'error_data' => $error->get_error_data()
        ]);
        
        return [
            'status' => 'error',
            'message' => $error->get_error_message(),
            'output_url' => '',
            'error_code' => $error->get_error_code()
        ];
    }
    
    /**
     * Truncate content to character limit with ellipsis.
     * Common pattern for social media handlers with character limits.
     *
     * @param string $content Content to truncate
     * @param int $limit Character limit
     * @param int $url_length Reserved characters for URLs (platform-specific)
     * @return string Truncated content
     */
    protected function truncate_content_with_ellipsis($content, $limit, $url_length = 0) {
        $available_length = $limit - $url_length;
        
        if (mb_strlen($content) <= $available_length) {
            return $content;
        }
        
        // Reserve 3 characters for ellipsis
        $truncate_length = $available_length - 3;
        
        if ($truncate_length <= 0) {
            return '...'; // Content too long even for ellipsis
        }
        
        return mb_substr($content, 0, $truncate_length) . '...';
    }
    
    /**
     * Get common settings field patterns used across handlers.
     * Child classes can use these as building blocks.
     *
     * @return array Common settings field definitions
     */
    protected function get_common_settings_fields() {
        return [
            'include_source' => [
                'type' => 'checkbox',
                'label' => __('Include Source Link', 'data-machine'),
                'description' => __('Append a link to the original content source.', 'data-machine'),
                'default' => false
            ],
            'enable_images' => [
                'type' => 'checkbox', 
                'label' => __('Include Images', 'data-machine'),
                'description' => __('Include images from the original content when available.', 'data-machine'),
                'default' => true
            ]
        ];
    }
    
    /**
     * Sanitize common settings fields.
     * Child classes should call this and merge with handler-specific sanitization.
     *
     * @param array $raw_settings Raw form input
     * @return array Sanitized common settings
     */
    protected function get_common_sanitized_settings($raw_settings) {
        return [
            'include_source' => !empty($raw_settings['include_source']),
            'enable_images' => !empty($raw_settings['enable_images'])
        ];
    }
    
    /**
     * Standard method to check if a WP_Error was returned and handle it consistently.
     *
     * @param mixed $result Result to check
     * @param string $operation_context Description of the operation for logging
     * @return array Error response if $result is WP_Error, otherwise returns $result
     */
    protected function handle_wp_error_result($result, $operation_context = 'operation') {
        if (is_wp_error($result)) {
            return $this->build_error_response($result, $operation_context);
        }
        
        return $result;
    }
    
    /**
     * Append an image to the content if available in metadata.
     * Moved from trait - common content formatting.
     *
     * @param string $content
     * @param array $input_metadata
     * @return string
     */
    protected function prepend_image_if_available($content, $input_metadata) {
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
     * Moved from trait - common content formatting.
     *
     * @param string $content
     * @param array $input_metadata
     * @return string
     */
    protected function append_source_if_available($content, $input_metadata) {
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
}