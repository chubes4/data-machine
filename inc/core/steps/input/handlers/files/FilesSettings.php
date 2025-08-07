<?php
/**
 * Files Input Handler Settings
 *
 * Defines settings fields and sanitization for Files input handler.
 * Part of the modular handler architecture.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/handlers/input/files
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Input\Files;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class FilesSettings {

    /**
     * Constructor.
     * Pure filter-based architecture - no dependencies.
     */
    public function __construct() {
        // No constructor dependencies - all services accessed via filters
    }

    /**
     * Get settings fields for Files input handler.
     *
     * @param array $current_config Current configuration values for this handler.
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(array $current_config = []): array {
        return [
            'file_upload_section' => [
                'type' => 'section',
                'label' => __('File Upload', 'data-machine'),
                'description' => __('Upload files to be processed by this handler.', 'data-machine'),
            ],
            'auto_cleanup_enabled' => [
                'type' => 'checkbox',
                'label' => __('Auto-cleanup old files', 'data-machine'),
                'description' => __('Automatically delete processed files older than 7 days to save disk space.', 'data-machine'),
            ],
        ];
    }

    /**
     * Sanitize Files input handler settings.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public static function sanitize(array $raw_settings): array {
        $sanitized = [];
        
        // Auto-cleanup setting
        $sanitized['auto_cleanup_enabled'] = isset($raw_settings['auto_cleanup_enabled']) && $raw_settings['auto_cleanup_enabled'] == '1';
        
        // Uploaded files (if provided through file upload interface)
        if (isset($raw_settings['uploaded_files']) && is_array($raw_settings['uploaded_files'])) {
            $sanitized['uploaded_files'] = array_map(function($file) {
                return [
                    'original_name' => sanitize_file_name($file['original_name'] ?? ''),
                    'persistent_path' => sanitize_text_field($file['persistent_path'] ?? ''),
                    'size' => absint($file['size'] ?? 0),
                    'mime_type' => sanitize_text_field($file['mime_type'] ?? ''),
                    'uploaded_at' => sanitize_text_field($file['uploaded_at'] ?? gmdate('Y-m-d H:i:s'))
                ];
            }, $raw_settings['uploaded_files']);
        } else {
            $sanitized['uploaded_files'] = [];
        }
        
        return $sanitized;
    }

    /**
     * Get default values for all settings.
     *
     * @return array Default values for all settings.
     */
    public static function get_defaults(): array {
        return [
            'auto_cleanup_enabled' => true,
            'uploaded_files' => [],
        ];
    }
}
