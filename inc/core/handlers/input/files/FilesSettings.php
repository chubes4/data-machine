<?php
/**
 * Files Input Handler Settings Module
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
            'max_file_size' => [
                'type' => 'select',
                'label' => __('Maximum File Size', 'data-machine'),
                'description' => __('Maximum allowed file size for uploads.', 'data-machine'),
                'options' => [
                    '1048576' => __('1 MB', 'data-machine'), // 1 * 1024 * 1024
                    '5242880' => __('5 MB', 'data-machine'), // 5 * 1024 * 1024
                    '10485760' => __('10 MB', 'data-machine'), // 10 * 1024 * 1024
                    '20971520' => __('20 MB', 'data-machine'), // 20 * 1024 * 1024
                ],
            ],
            'allowed_file_types' => [
                'type' => 'multiselect',
                'label' => __('Allowed File Types', 'data-machine'),
                'description' => __('Select which file types are allowed for upload.', 'data-machine'),
                'options' => [
                    'txt' => __('Text Files (.txt)', 'data-machine'),
                    'csv' => __('CSV Files (.csv)', 'data-machine'),
                    'json' => __('JSON Files (.json)', 'data-machine'),
                    'pdf' => __('PDF Files (.pdf)', 'data-machine'),
                    'doc' => __('Word Documents (.doc)', 'data-machine'),
                    'docx' => __('Word Documents (.docx)', 'data-machine'),
                ],
            ],
            'enable_content_scanning' => [
                'type' => 'checkbox',
                'label' => __('Enable Content Scanning', 'data-machine'),
                'description' => __('Scan uploaded files for suspicious content patterns.', 'data-machine'),
            ],
            'auto_process_files' => [
                'type' => 'checkbox',
                'label' => __('Auto-Process Files', 'data-machine'),
                'description' => __('Automatically process uploaded files in order they were uploaded.', 'data-machine'),
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
        
        // Max file size
        $valid_sizes = ['1048576', '5242880', '10485760', '20971520'];
        $max_size = sanitize_text_field($raw_settings['max_file_size'] ?? '10485760');
        if (!in_array($max_size, $valid_sizes)) {
            throw new Exception(esc_html__('Invalid max file size parameter provided in settings.', 'data-machine'));
        }
        $sanitized['max_file_size'] = $max_size;
        
        // Allowed file types
        $valid_types = ['txt', 'csv', 'json', 'pdf', 'doc', 'docx'];
        $allowed_types = $raw_settings['allowed_file_types'] ?? ['txt', 'csv', 'json', 'pdf', 'docx'];
        if (is_array($allowed_types)) {
            $sanitized['allowed_file_types'] = array_intersect($allowed_types, $valid_types);
        } else {
            $sanitized['allowed_file_types'] = ['txt', 'csv', 'json', 'pdf', 'docx'];
        }
        
        // Ensure at least one file type is allowed
        if (empty($sanitized['allowed_file_types'])) {
            $sanitized['allowed_file_types'] = ['txt'];
        }
        
        // Boolean settings
        $sanitized['enable_content_scanning'] = isset($raw_settings['enable_content_scanning']) && $raw_settings['enable_content_scanning'] == '1';
        $sanitized['auto_process_files'] = isset($raw_settings['auto_process_files']) && $raw_settings['auto_process_files'] == '1';
        
        return $sanitized;
    }

    /**
     * Get default values for all settings.
     *
     * @return array Default values for all settings.
     */
    public static function get_defaults(): array {
        return [
            'max_file_size' => '10485760', // 10MB
            'allowed_file_types' => ['txt', 'csv', 'json', 'pdf', 'docx'],
            'enable_content_scanning' => true,
            'auto_process_files' => true,
        ];
    }
}
