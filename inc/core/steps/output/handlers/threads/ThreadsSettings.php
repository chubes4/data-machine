<?php
/**
 * Threads Output Handler Settings
 *
 * Defines settings fields and sanitization for Threads output handler.
 * Part of the modular handler architecture.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/handlers/output/threads
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Output\Threads;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class ThreadsSettings {

    /**
     * Constructor.
     * Pure filter-based architecture - no dependencies.
     */
    public function __construct() {
        // No constructor dependencies - all services accessed via filters
    }

    /**
     * Get settings fields for Threads output handler.
     *
     * @param array $current_config Current configuration values for this handler.
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(array $current_config = []): array {
        return [
            'threads_char_limit' => [
                'type' => 'number',
                'label' => __('Character Limit Override', 'data-machine'),
                'description' => __('Set a custom character limit for Threads posts. Text will be truncated if necessary.', 'data-machine'),
                'min' => 50,
                'max' => 500, // Threads standard limit
            ],
            'threads_include_images' => [
                'type' => 'checkbox',
                'label' => __('Include Images', 'data-machine'),
                'description' => __('Attempt to find and include an image from the source data (if available).', 'data-machine'),
            ],
            'threads_include_title' => [
                'type' => 'checkbox',
                'label' => __('Include Title in Content', 'data-machine'),
                'description' => __('Prepend the parsed title to the content if it\'s not already included.', 'data-machine'),
            ],
        ];
    }

    /**
     * Sanitize Threads handler settings.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public static function sanitize(array $raw_settings): array {
        $sanitized = [];
        $sanitized['threads_char_limit'] = min(500, max(50, absint($raw_settings['threads_char_limit'] ?? 500)));
        $sanitized['threads_include_images'] = isset($raw_settings['threads_include_images']) && $raw_settings['threads_include_images'] == '1';
        $sanitized['threads_include_title'] = isset($raw_settings['threads_include_title']) && $raw_settings['threads_include_title'] == '1';
        return $sanitized;
    }

    /**
     * Get default values for all settings.
     *
     * @return array Default values for all settings.
     */
    public static function get_defaults(): array {
        return [
            'threads_char_limit' => 500,
            'threads_include_images' => true,
            'threads_include_title' => true,
        ];
    }
}
