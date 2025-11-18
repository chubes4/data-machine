<?php
/**
 * Handles file uploads as a data source.
 *
 * @package    Data_Machine
 * @subpackage Core\Steps\Fetch\Handlers\Files
 * @since      0.7.0
 */
namespace DataMachine\Core\Steps\Fetch\Handlers\Files;


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Files {

    /**
     * Get file storage instance via filter discovery
     */
	private function get_file_storage(): ?\DataMachine\Core\FilesRepository\FileStorage {
		return apply_filters('datamachine_get_file_storage', null);
	}

	/**
	 * Process uploaded files with universal image handling.
	 * For images: stores image_url via datamachine_engine_data filter.
	 */
	public function get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array {
        $storage = $this->get_file_storage();
        $flow_step_id = $handler_config['flow_step_id'] ?? null;
        $config = $handler_config['files'] ?? [];
        $uploaded_files = $config['uploaded_files'] ?? [];

        if (empty($uploaded_files)) {
            $flow_id = $handler_config['flow_id'] ?? 0;

            // Build context with fallback names (no database queries)
            $context = [
                'pipeline_id' => $pipeline_id,
                'pipeline_name' => "pipeline-{$pipeline_id}",
                'flow_id' => $flow_id,
                'flow_name' => "flow-{$flow_id}"
            ];

            $repo_files = $storage->get_all_files($context);
            if (empty($repo_files)) {
                do_action('datamachine_log', 'debug', 'Files Input: No files available in repository.', [
                    'pipeline_id' => $pipeline_id,
                    'flow_step_id' => $flow_step_id
                ]);
                return ['processed_items' => []];
            }

            $uploaded_files = array_map(function($file) {
                return [
                    'original_name' => $file['filename'],
                    'persistent_path' => $file['path'],
                    'size' => $file['size'],
                    'mime_type' => $this->get_mime_type_from_file($file['path']),
                    'uploaded_at' => gmdate('Y-m-d H:i:s', $file['modified'])
                ];
            }, $repo_files);
        }

        $next_file = $this->find_next_unprocessed_file($flow_step_id, ['uploaded_files' => $uploaded_files], $job_id);

        if (!$next_file) {
            do_action('datamachine_log', 'debug', 'Files Input: No unprocessed files available.', ['pipeline_id' => $pipeline_id]);
            return ['processed_items' => []];
        }

        if (!file_exists($next_file['persistent_path'])) {
            do_action('datamachine_log', 'error', 'Files Input: File not found.', ['pipeline_id' => $pipeline_id, 'file_path' => $next_file['persistent_path']]);
            return ['processed_items' => []];
        }

        $file_identifier = $next_file['persistent_path'];
        $mime_type = $next_file['mime_type'] ?? 'application/octet-stream';

        $content_data = [
            'title' => $next_file['original_name'],
            'content' => 'File: ' . $next_file['original_name'] . "\nType: " . $mime_type . "\nSize: " . ($next_file['size'] ?? 0) . ' bytes'
        ];

        $file_info = [
            'file_path' => $next_file['persistent_path'],
            'file_name' => $next_file['original_name'],
            'mime_type' => $mime_type,
            'file_size' => $next_file['size'] ?? 0
        ];

        $metadata = [
            'source_type' => 'files',
            'item_identifier_to_log' => $file_identifier,
            'original_id' => $file_identifier,
            'original_title' => $next_file['original_name'],
            'original_date_gmt' => $next_file['uploaded_at'] ?? gmdate('Y-m-d H:i:s')
        ];

        // Create clean data packet for AI processing
        $item_data = [
            'data' => array_merge($content_data, ['file_info' => $file_info]),
            'metadata' => $metadata
        ];

        // Generate public URL for image files (for WordPress featured images, etc.)
        $file_url = '';
        if (strpos($mime_type, 'image/') === 0) {
            // For image files, convert file path to public URL
            $upload_dir = wp_upload_dir();
            $file_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $next_file['persistent_path']);
        }

        // Store URLs in engine_data via centralized filter
        if ($job_id) {
            apply_filters('datamachine_engine_data', null, $job_id, [
                'source_url' => '',
                'image_url' => $file_url // Image URL for images only
            ]);
        }

        do_action('datamachine_log', 'debug', 'Files Input: Found unprocessed file for processing.', [
            'pipeline_id' => $pipeline_id,
            'flow_step_id' => $flow_step_id,
            'file_path' => $file_identifier,
            'is_image' => !empty($file_url),
            'public_url' => $file_url
        ]);

        return [
            'processed_items' => [$item_data]
        ];
	}

    /**
     * Find the next unprocessed file for a flow step.
     */
    private function find_next_unprocessed_file(?string $flow_step_id, array $config, ?string $job_id = null): ?array {
        $uploaded_files = $config['uploaded_files'] ?? [];
        
        if (empty($uploaded_files)) {
            return null;
        }

        foreach ($uploaded_files as $file) {
            $file_identifier = $file['persistent_path'];
            
            $is_processed = apply_filters('datamachine_is_item_processed', false, $flow_step_id, 'files', $file_identifier);
            
            do_action('datamachine_log', 'debug', 'Files Input: Checking file processed status', [
                'flow_step_id' => $flow_step_id,
                'file_identifier' => basename($file_identifier),
                'is_processed' => $is_processed
            ]);
            
            if (!$is_processed) {
                do_action('datamachine_mark_item_processed', $flow_step_id, 'files', $file_identifier, $job_id);
                return $file;
            }
        }

        return null;
    }


    private function get_mime_type_from_file(string $file_path): string {
        $file_info = wp_check_filetype($file_path);
        return $file_info['type'] ?? 'application/octet-stream';
    }
    
    /**
     * Sanitize handler settings.
     *
     * @param array $raw_settings Raw settings from user input
     * @return array Sanitized settings array
     */
    public function sanitize_settings(array $raw_settings): array {
        return [];
    }

    /**
     * Get the display label for the Files handler.
     *
     * @return string Localized handler label
     */
    public static function get_label(): string {
        return __('File Upload', 'datamachine');
    }
}
