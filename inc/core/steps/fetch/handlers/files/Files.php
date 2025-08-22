<?php
/**
 * Handles file uploads as a data source.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/core/handlers/fetch
 * @since      0.7.0
 */
namespace DataMachine\Core\Steps\Fetch\Handlers\Files;


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Files {

    /**
     * Parameter-less constructor for pure filter-based architecture.
     */
	public function __construct() {
		// No parameters needed - all services accessed via filters
	}


    /**
     * Get repository instance via filter discovery
     *
     * @return \DataMachine\Engine\FilesRepository|null
     */
	private function get_repository(): ?\DataMachine\Engine\FilesRepository {
		$repositories = apply_filters('dm_files_repository', []);
		return $repositories['files'] ?? null;
	}

    /**
     * Processes uploaded files and prepares fetch data.
     *
     * @param int $pipeline_id Pipeline ID for context.
     * @param array  $handler_config Handler configuration array.
     * @param string|null $job_id The job ID for processed items tracking.
     * @return array Array with 'processed_items' key containing eligible items.
     * @throws Exception If file is missing, invalid, or cannot be processed.
     */
	public function get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array {
        // Validate pipeline ID
        if (empty($pipeline_id)) {
            do_action('dm_log', 'error', 'Files Input: Missing pipeline ID.', ['pipeline_id' => $pipeline_id]);
            return ['processed_items' => []];
        }
        
        $repository = $this->get_repository();
        
        if (!$repository) {
            do_action('dm_log', 'error', 'Files Input: Repository service not available.', ['pipeline_id' => $pipeline_id]);
            return ['processed_items' => []];
        }

        // Extract flow_step_id for proper file isolation
        $flow_step_id = $handler_config['flow_step_id'] ?? null;
        
        // Access config from handler config structure  
        $config = $handler_config['files'] ?? [];
        
        // Get uploaded files from handler config
        $uploaded_files = $config['uploaded_files'] ?? [];
        
        // If no uploaded files in config, check repository for available files with proper isolation
        if (empty($uploaded_files)) {
            $repo_files = $repository->get_all_files($flow_step_id);
            if (empty($repo_files)) {
                do_action('dm_log', 'debug', 'Files Input: No files available in repository.', [
                    'pipeline_id' => $pipeline_id,
                    'flow_step_id' => $flow_step_id
                ]);
                return ['processed_items' => []];
            }
            
            // Convert repository files to expected format
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
        
        // Find the next unprocessed uploaded file
        $next_file = $this->find_next_unprocessed_file($flow_step_id, ['uploaded_files' => $uploaded_files], $job_id);
        
        if (!$next_file) {
            do_action('dm_log', 'debug', 'Files Input: No unprocessed files available.', ['pipeline_id' => $pipeline_id]);
            return ['processed_items' => []];
        }

        // Check if file exists
        if (!file_exists($next_file['persistent_path'])) {
            do_action('dm_log', 'error', 'Files Input: File not found.', ['pipeline_id' => $pipeline_id, 'file_path' => $next_file['persistent_path']]);
            return ['processed_items' => []];
        }

        // Create input_data using the file path as the identifier
        $file_identifier = $next_file['persistent_path'];
        $mime_type = $next_file['mime_type'] ?? 'application/octet-stream';
        
        // Pass file directly to engine - let downstream steps handle file type compatibility
        
        // Create simple file data packet - let engine handle file processing
        $item_data = [
            'file_path' => $next_file['persistent_path'],
            'file_name' => $next_file['original_name'], 
            'mime_type' => $mime_type,
            'file_size' => $next_file['size'] ?? 0,
            'source_type' => 'files',
            'item_identifier_to_log' => $file_identifier,
            'original_id' => $file_identifier,
            'original_title' => $next_file['original_name'],
            'original_date_gmt' => $next_file['uploaded_at'] ?? gmdate('Y-m-d H:i:s')
        ];

        do_action('dm_log', 'debug', 'Files Input: Found unprocessed file for processing.', [
            'pipeline_id' => $pipeline_id,
            'flow_step_id' => $flow_step_id,
            'file_path' => $file_identifier
        ]);

        // Return single item in processed_items array
        return ['processed_items' => [$item_data]];
	}

    /**
     * Find the next unprocessed file for a flow step.
     *
     * @param string|null $flow_step_id Flow step ID for granular processed items tracking.
     * @param array $config Files configuration.
     * @param string|null $job_id Job ID for processed items tracking.
     * @return array|null File info or null if no unprocessed files.
     */
    private function find_next_unprocessed_file(?string $flow_step_id, array $config, ?string $job_id = null): ?array {
        $uploaded_files = $config['uploaded_files'] ?? [];
        
        if (empty($uploaded_files)) {
            return null;
        }

        // Find first file that hasn't been processed using centralized hook
        foreach ($uploaded_files as $file) {
            $file_identifier = $file['persistent_path'];
            
            $is_processed = apply_filters('dm_is_item_processed', false, $flow_step_id, 'files', $file_identifier);
            
            do_action('dm_log', 'debug', 'Files Input: Checking file processed status', [
                'flow_step_id' => $flow_step_id,
                'file_identifier' => basename($file_identifier),
                'is_processed' => $is_processed
            ]);
            
            if (!$is_processed) {
                // Mark file as processed immediately after confirming eligibility
                do_action('dm_mark_item_processed', $flow_step_id, 'files', $file_identifier, $job_id);
                return $file;
            }
        }

        return null; // All files have been processed
    }


    /**
     * Get MIME type from file path using WordPress
     *
     * @param string $file_path Path to file
     * @return string MIME type
     */
    private function get_mime_type_from_file(string $file_path): string {
        $file_info = wp_check_filetype($file_path);
        return $file_info['type'] ?? 'application/octet-stream';
    }




    
    /**
     * Sanitize settings for the Files fetch handler.
     * This handler currently has no specific settings.
     *
     * @param array $raw_settings
     * @return array
     */
    public function sanitize_settings(array $raw_settings): array {
        // No settings to sanitize
        return [];
    }

    /**
     * Get the user-friendly label for this handler.
     *
     * @return string The label.
     */
    public static function get_label(): string {
        return __('File Upload', 'data-machine');
    }

    /**
     * Convert PHP upload error codes to human-readable messages.
     *
     * @param int $error_code The PHP UPLOAD_ERR_* constant.
     * @return string The error message.
     */
    private function get_upload_error_message(int $error_code): string {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                $message = __( "The uploaded file exceeds the upload_max_filesize directive in php.ini.", 'data-machine' );
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $message = __( "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.", 'data-machine' );
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = __( "The uploaded file was only partially uploaded.", 'data-machine' );
                break;
            case UPLOAD_ERR_NO_FILE:
                $message = __( "No file was uploaded.", 'data-machine' );
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $message = __( "Missing a temporary folder.", 'data-machine' );
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $message = __( "Failed to write file to disk.", 'data-machine' );
                break;
            case UPLOAD_ERR_EXTENSION:
                $message = __( "A PHP extension stopped the file upload.", 'data-machine' );
                break;
            default:
                $message = __( "Unknown upload error.", 'data-machine' );
                break;
        }
        return $message;
    }

    /**
     * Basic security validation - only block dangerous executable files
     *
     * @param string $file_path Path to file
     * @param string $filename Original filename
     * @return bool True if file passes validation, false otherwise
     */
    private function validate_file_basic(string $file_path, string $filename): bool {
        // Only block obviously dangerous executable extensions for security
        $dangerous_extensions = ['php', 'exe', 'bat', 'cmd', 'scr', 'com', 'pif', 'vbs', 'js'];
        $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($file_extension, $dangerous_extensions)) {
            do_action('dm_log', 'error', 'Files Input: File type not allowed for security reasons.', ['file_extension' => $file_extension]);
            return false;
        }
        
        // Verify file exists and is readable
        if (!file_exists($file_path) || !is_readable($file_path)) {
            do_action('dm_log', 'error', 'Files Input: File is not accessible.', ['file_path' => $file_path]);
            return false;
        }
        
        return true;
    }
}

