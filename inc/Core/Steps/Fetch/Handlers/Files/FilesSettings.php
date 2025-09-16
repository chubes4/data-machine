<?php
/**
 * Files Fetch Handler Settings
 *
 * Defines settings fields and sanitization for Files fetch handler.
 * Part of the modular handler architecture.
 *
 * @package DataMachine
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\Fetch\Handlers\Files;

defined('ABSPATH') || exit;

class FilesSettings {

    public function __construct() {
    }

    /**
     * Get settings fields for Files fetch handler.
     *
     * @param array $current_config Current configuration values for this handler.
     * @return array Associative array defining the settings fields.
     */
    public static function get_fields(array $current_config = []): array {
        return [
            'file_upload_section' => [
                'type' => 'section',
                'label' => __('File Upload', 'data-machine'),
                'description' => __('Upload any file type - the pipeline will handle compatibility.', 'data-machine'),
            ],
            'auto_cleanup_enabled' => [
                'type' => 'checkbox',
                'label' => __('Auto-cleanup old files', 'data-machine'),
                'description' => __('Automatically delete processed files older than 7 days to save disk space.', 'data-machine'),
            ],
        ];
    }

    /**
     * Sanitize Files fetch handler settings.
     *
     * @param array $raw_settings Raw settings input.
     * @return array Sanitized settings.
     */
    public static function sanitize(array $raw_settings): array {
        $sanitized = [];
        
        // Auto-cleanup setting
        $sanitized['auto_cleanup_enabled'] = isset($raw_settings['auto_cleanup_enabled']) && $raw_settings['auto_cleanup_enabled'] == '1';
        
        // Accept any uploaded files without type validation
        if (isset($raw_settings['uploaded_files']) && is_array($raw_settings['uploaded_files'])) {
            $sanitized['uploaded_files'] = array_map(function($file) {
                return [
                    'original_name' => sanitize_file_name($file['original_name'] ?? ''),
                    'persistent_path' => sanitize_text_field($file['persistent_path'] ?? ''),
                    'size' => absint($file['size'] ?? 0),
                    'mime_type' => sanitize_text_field($file['mime_type'] ?? 'application/octet-stream'),
                    'uploaded_at' => sanitize_text_field($file['uploaded_at'] ?? gmdate('Y-m-d H:i:s'))
                ];
            }, $raw_settings['uploaded_files']);
        } else {
            $sanitized['uploaded_files'] = [];
        }
        
        return $sanitized;
    }

}
