<?php
/**
 * Handles file uploads as a data source.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/input
 * @since      0.7.0
 */
namespace DataMachine\Core\Handlers\Input\Files;

use Exception;

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
	 * Get service via filter system.
	 *
	 * @param string $service_name Service name.
	 * @return mixed Service instance.
	 */
	private function get_service(string $service_name) {
		return apply_filters('dm_get_' . $service_name, null);
	}

	/**
	 * Get repository instance via filter-based service discovery
	 *
	 * @return FilesRepository|null
	 */
	private function get_repository(): ?FilesRepository {
		return apply_filters('dm_get_files_repository', null);
	}

	/**
	 * Processes uploaded files and prepares input data.
	 *
     * @param object $module The full module object containing configuration and context.
     * @param array  $source_config Decoded data_source_config specific to this handler (flat array, not sub-array).
     * @return array Array with 'processed_items' key containing eligible items.
     * @throws Exception If file is missing, invalid, or cannot be processed.
	 */
	public function get_input_data(object $module, array $source_config): array {
        // Direct filter-based validation
        $module_id = isset($module->module_id) ? absint($module->module_id) : 0;
        if (empty($module_id)) {
            throw new Exception(esc_html__('Missing module ID.', 'data-machine'));
        }
        
        $logger = apply_filters('dm_get_logger', null);
        $repository = $this->get_repository();
        
        if (!$repository) {
            throw new Exception(esc_html__('Files repository service not available.', 'data-machine'));
        }

        // Create handler context for file isolation
        $flow_id = isset($module->flow_id) ? absint($module->flow_id) : 0;
        $step_id = isset($module->step_id) ? sanitize_text_field($module->step_id) : null;
        
        if (!$flow_id || !$step_id) {
            throw new Exception(esc_html__('Missing flow_id or step_id for file isolation.', 'data-machine'));
        }
        
        $handler_context = [
            'flow_id' => $flow_id,
            'step_id' => $step_id
        ];
        
        // Get uploaded files from repository or config
        $uploaded_files = $source_config['uploaded_files'] ?? [];
        
        // If no uploaded files in config, check repository for available files
        if (empty($uploaded_files)) {
            $repo_files = $repository->get_all_files($handler_context);
            if (empty($repo_files)) {
                $logger?->debug('Files Input: No files available in repository.', ['module_id' => $module_id, 'handler_context' => $handler_context]);
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
        $next_file = $this->find_next_unprocessed_file($module_id, ['uploaded_files' => $uploaded_files]);
        
        if (!$next_file) {
            $logger?->debug('Files Input: No unprocessed files available.', ['module_id' => $module_id]);
            return ['processed_items' => []];
        }

        // Check if file exists
        if (!file_exists($next_file['persistent_path'])) {
            throw new Exception("File not found: {$next_file['persistent_path']}");
        }

        // Create input_data_packet using the file path as the identifier
        $file_identifier = $next_file['persistent_path'];
        $mime_type = $next_file['mime_type'] ?? 'application/octet-stream';
        
        // Read text file content for supported text types
        $content_string = null;
        if ($this->is_text_file($mime_type)) {
            $content_string = $this->read_text_file_content($next_file['persistent_path'], $mime_type);
        }
        
        // Create standardized item data
        $item_data = [
            'content_string' => $content_string, // Text content for text files, null for other types
            'file_info' => [
                'original_name' => $next_file['original_name'],
                'mime_type' => $mime_type,
                'size' => $next_file['size'] ?? 0,
                'persistent_path' => $next_file['persistent_path']
            ],
            'source_type' => 'files',
            'item_identifier_to_log' => $file_identifier, // Used by processed items system
            'original_id' => $file_identifier,
            'original_title' => $next_file['original_name'],
            'original_date_gmt' => $next_file['uploaded_at'] ?? gmdate('Y-m-d H:i:s')
        ];

        $logger?->debug('Files Input: Found unprocessed file for processing.', [
            'module_id' => $module_id,
            'file_path' => $file_identifier
        ]);

        // Return single item in processed_items array
        return ['processed_items' => [$item_data]];
	}

    /**
     * Find the next unprocessed file for a module.
     *
     * @param int $module_id Module ID.
     * @param array $config Files configuration.
     * @return array|null File info or null if no unprocessed files.
     */
    private function find_next_unprocessed_file(int $module_id, array $config): ?array {
        $uploaded_files = $config['uploaded_files'] ?? [];
        
        if (empty($uploaded_files)) {
            return null;
        }

        // Get processed items service
        $db_processed_items = apply_filters('dm_get_database_service', null, 'processed_items');
        
        // If we don't have processed items service, return the first file
        if (!$db_processed_items) {
            return $uploaded_files[0];
        }

        // Find first file that hasn't been processed
        foreach ($uploaded_files as $file) {
            $file_identifier = $file['persistent_path'];
            
            if (!$db_processed_items->is_processed($module_id, 'files', $file_identifier)) {
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
     * Check if a MIME type represents a text file that should be read as content.
     * Simplified - let AI models handle most file types
     *
     * @param string $mime_type MIME type to check.
     * @return bool True if it's a text file type.
     */
    private function is_text_file(string $mime_type): bool {
        $text_mime_types = [
            'text/plain',
            'text/csv',
            'application/json',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        return in_array($mime_type, $text_mime_types);
    }

    /**
     * Read text content from a file.
     *
     * @param string $file_path Path to the file.
     * @param string $mime_type MIME type of the file.
     * @return string|null File content or null if reading failed.
     */
    private function read_text_file_content(string $file_path, string $mime_type): ?string {
        try {
            // Security check - ensure file exists and is readable
            if (!file_exists($file_path) || !is_readable($file_path)) {
                $logger = apply_filters('dm_get_logger', null);
                $logger?->error('Files Input: Cannot read file.', ['file_path' => $file_path]);
                return null;
            }

            // WordPress native memory management
            wp_raise_memory_limit('admin');
            
            // Basic file size check to prevent memory issues
            $file_size = filesize($file_path);
            if ($file_size === false) {
                $logger = apply_filters('dm_get_logger', null);
                $logger?->error('Files Input: Cannot determine file size.', ['file_path' => $file_path]);
                return null;
            }
            
            // Check if file is too large (conservative 32MB limit)
            $max_file_size = 32 * 1024 * 1024; // 32MB
            if ($file_size > $max_file_size) {
                $logger = apply_filters('dm_get_logger', null);
                $logger?->error('Files Input: File too large to process safely.', [
                    'file_path' => $file_path,
                    'file_size' => size_format($file_size),
                    'max_allowed' => size_format($max_file_size)
                ]);
                return null;
            }

            // Pass file directly to AI instead of pre-processing
            // Let AI providers handle text extraction with their specialized systems
            $content = [
                'file_path' => $file_path,
                'mime_type' => $mime_type,
                'filename' => basename($file_path),
                'file_size' => filesize($file_path)
            ];

            // File data is ready - no content validation needed since AI will handle it
            if (empty($content['file_path']) || !file_exists($content['file_path'])) {
                $logger = apply_filters('dm_get_logger', null);
                $logger?->error('Files Input: File not found or inaccessible.', ['file_path' => $file_path]);
                return null;
            }

            $logger = apply_filters('dm_get_logger', null);
            $logger?->debug('Files Input: Successfully prepared file for AI processing.', [
                'file_path' => $file_path,
                'mime_type' => $content['mime_type'],
                'file_size' => $content['file_size']
            ]);

            return $content;

        } catch (Exception $e) {
            $logger = apply_filters('dm_get_logger', null);
            $logger?->error('Files Input: Error reading text file.', [
                'file_path' => $file_path,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }


    
    /**
     * Sanitize settings for the Files input handler.
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
     * Basic file validation - keep only essential security checks
     *
     * @param string $file_path Path to file
     * @param string $filename Original filename
     * @throws Exception If file fails basic validation
     */
    private function validate_file_basic(string $file_path, string $filename): void {
        // Basic file size check to prevent memory issues
        $file_size = filesize($file_path);
        if ($file_size === false) {
            throw new Exception(esc_html__('Cannot determine file size.', 'data-machine'));
        }
        
        // Conservative 32MB limit
        $max_file_size = 32 * 1024 * 1024; // 32MB
        if ($file_size > $max_file_size) {
            throw new Exception(sprintf(
                /* translators: %1$s: actual file size, %2$s: maximum allowed size */
                __('File too large: %1$s. Maximum allowed size: %2$s', 'data-machine'),
                size_format($file_size),
                size_format($max_file_size)
            ));
        }

        // Only block obviously dangerous extensions
        $dangerous_extensions = ['php', 'exe', 'bat', 'cmd', 'scr'];
        $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($file_extension, $dangerous_extensions)) {
            throw new Exception(esc_html__('File type not allowed for security reasons.', 'data-machine'));
        }
    }
}

