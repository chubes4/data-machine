<?php
/**
 * Handles file uploads as a data source.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/input
 * @since      0.7.0
 */
namespace DataMachine\Handlers\Input;

use DataMachine\Database\{Modules, Projects};
use DataMachine\Engine\ProcessedItemsManager;
use DataMachine\Helpers\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Files extends BaseInputHandler {

	/**
	 * Constructor. Dependencies are injected.
	 * Calls parent constructor to set up common dependencies.
	 *
	 * @param Modules $db_modules Database modules handler.
     * @param Projects $db_projects Database projects handler.
     * @param ProcessedItemsManager $processed_items_manager Processed items manager.
     * @param Logger|null $logger Optional logger.
	 */
	public function __construct(
        Modules $db_modules,
        Projects $db_projects,
        ProcessedItemsManager $processed_items_manager,
        ?Logger $logger = null
    ) {
        // Call parent constructor with required dependencies
        parent::__construct($db_modules, $db_projects, $processed_items_manager, $logger);
	}

	/**
	 * Processes uploaded files and prepares input data packets.
	 *
     * @param object $module The full module object containing configuration and context.
     * @param array  $source_config Decoded data_source_config specific to this handler (flat array, not sub-array).
     * @param int    $user_id The ID of the user initiating the process (for ownership/context checks).
     * @return array An array containing a single standardized input data packet for the uploaded file.
     * @throws Exception If file is missing, invalid, or cannot be processed.
	 */
	public function get_input_data(object $module, array $source_config, int $user_id): array {
        // Use base class validation - replaces ~20 lines of duplicated code
        $validated = $this->validate_basic_requirements($module, $user_id);
        $module_id = $validated['module_id'];
        $project = $validated['project'];

        // Find the next unprocessed uploaded file
        $next_file = $this->find_next_unprocessed_file($source_config);
        
        if (!$next_file) {
            $this->logger?->info('Files Input: No unprocessed files available.', ['module_id' => $module_id]);
            return []; // No data to process
        }

        // Validate the file still exists
        if (!file_exists($next_file['persistent_path'])) {
            $this->logger?->error('Files Input: File no longer exists on disk.', [
                'module_id' => $module_id,
                'file_path' => $next_file['persistent_path']
            ]);
            // Remove invalid file and try again
            $this->remove_invalid_file($module_id, $next_file['persistent_path']);
            return $this->get_input_data($module, $source_config, $user_id);
        }

        // Create input_data_packet using the file path as the identifier
        $file_identifier = $next_file['persistent_path'];
        $mime_type = $next_file['mime_type'] ?? 'text/plain';
        
        // Read text file content for supported text types
        $content_string = null;
        if ($this->is_text_file($mime_type)) {
            $content_string = $this->read_text_file_content($next_file['persistent_path'], $mime_type);
        }
        
        // Use base class method to create standardized packet
        $data = [
            'content_string' => $content_string, // Text content for text files, null for PDFs/images
            'file_info' => [
                'original_name' => $next_file['original_name'],
                'mime_type' => $mime_type,
                'size' => $next_file['size'] ?? 0,
                'persistent_path' => $next_file['persistent_path']
            ]
        ];
        
        $metadata = [
            'source_type' => 'files',
            'item_identifier_to_log' => $file_identifier, // Used by processed items system
            'original_id' => $file_identifier,
            'original_title' => $next_file['original_name'],
            'original_date_gmt' => $next_file['uploaded_at'] ?? gmdate('Y-m-d H:i:s')
        ];
        
        $input_data_packet = $this->create_input_data_packet($data, $metadata);

        $this->logger?->info('Files Input: Found unprocessed file for processing.', [
            'module_id' => $module_id,
            'file_path' => $file_identifier
        ]);

        // Return single packet directly for "one coin, one operation" model
        return $input_data_packet;
	}

    /**
     * Find the next unprocessed file for a module.
     *
     * @param int $module_id Module ID.
     * @return array|null File info or null if no unprocessed files.
     */
    private function find_next_unprocessed_file(array $source_config): ?array {
        // Get uploaded files from nested config
        $config = $source_config['files'] ?? [];
        $uploaded_files = $config['uploaded_files'] ?? [];
        
        if (empty($uploaded_files)) {
            return null;
        }

        // If we don't have processed items service, return the first file
        if (!$this->db_processed_items) {
            return $uploaded_files[0];
        }

        // Find first file that hasn't been processed using base class method
        foreach ($uploaded_files as $file) {
            $file_identifier = $file['persistent_path'];
            
            if (!$this->check_if_processed($module_id, 'files', $file_identifier)) {
                return $file;
            }
        }

        return null; // All files have been processed
    }

    /**
     * Get uploaded files from module configuration.
     *
     * @param int $module_id Module ID.
     * @return array Array of uploaded files.
     */
    private function get_uploaded_files(int $module_id): array {
        $module = $this->db_modules->get_module($module_id);
        if (!$module) {
            return [];
        }

        $data_source_config = json_decode($module->data_source_config ?? '{}', true);
        return $data_source_config['uploaded_files'] ?? [];
    }

    /**
     * Remove invalid file from module configuration.
     *
     * @param int $module_id Module ID.
     * @param string $file_path File path to remove.
     */
    private function remove_invalid_file(int $module_id, string $file_path): void {
        $uploaded_files = $this->get_uploaded_files($module_id);
        
        // Filter out the invalid file
        $uploaded_files = array_filter($uploaded_files, function($file) use ($file_path) {
            return ($file['persistent_path'] ?? '') !== $file_path;
        });

        // Reindex array
        $uploaded_files = array_values($uploaded_files);

        // Update module config
        $module = $this->db_modules->get_module($module_id);
        if ($module) {
            $data_source_config = json_decode($module->data_source_config ?? '{}', true);
            $data_source_config['uploaded_files'] = $uploaded_files;
            
            $this->db_modules->update_module($module_id, [
                'data_source_config' => json_encode($data_source_config)
            ]);
        }
    }

    /**
     * Check if a MIME type represents a text file that should be read as content.
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
                $this->logger?->error('Files Input: Cannot read file.', ['file_path' => $file_path]);
                return null;
            }

            // Memory guard check
            if (class_exists('\DataMachine\Helpers\MemoryGuard')) {
                $memory_guard = new \DataMachine\Helpers\MemoryGuard();
                if (!$memory_guard->can_load_file($file_path, 2.0)) {
                    $this->logger?->error('Files Input: File too large to read safely.', ['file_path' => $file_path]);
                    return null;
                }
            }

            // Handle different file types
            switch ($mime_type) {
                case 'text/plain':
                case 'text/csv':
                case 'application/json':
                    // Simple text files - read directly
                    $content = file_get_contents($file_path);
                    break;

                case 'application/msword':
                case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                    // Word documents - extract text (simplified)
                    $content = $this->extract_word_document_text($file_path, $mime_type);
                    break;

                default:
                    $this->logger?->error('Files Input: Unsupported text file type.', ['mime_type' => $mime_type]);
                    return null;
            }

            if ($content === false || $content === null) {
                $this->logger?->error('Files Input: Failed to read file content.', ['file_path' => $file_path]);
                return null;
            }

            // Basic content sanitization
            $content = trim($content);
            if (empty($content)) {
                $this->logger?->warning('Files Input: File appears to be empty.', ['file_path' => $file_path]);
                return null;
            }

            $this->logger?->info('Files Input: Successfully read text file.', [
                'file_path' => $file_path,
                'content_length' => strlen($content)
            ]);

            return $content;

        } catch (Exception $e) {
            $this->logger?->error('Files Input: Error reading text file.', [
                'file_path' => $file_path,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Extract text from Word documents.
     *
     * @param string $file_path Path to the Word document.
     * @param string $mime_type MIME type of the document.
     * @return string|null Extracted text or null if extraction failed.
     */
    private function extract_word_document_text(string $file_path, string $mime_type): ?string {
        // For .docx files (Office Open XML), we can try a simple approach
        if ($mime_type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            try {
                // .docx files are ZIP archives - try to extract text from document.xml
                $zip = new ZipArchive();
                if ($zip->open($file_path) === TRUE) {
                    $xml_content = $zip->getFromName('word/document.xml');
                    $zip->close();
                    
                    if ($xml_content) {
                        // Simple XML parsing to extract text content
                        $xml_content = preg_replace('/<[^>]*>/', ' ', $xml_content);
                        $xml_content = html_entity_decode($xml_content);
                        $xml_content = preg_replace('/\s+/', ' ', $xml_content);
                        return trim($xml_content);
                    }
                }
            } catch (Exception $e) {
                $this->logger?->error('Files Input: Error extracting .docx content.', [
                    'file_path' => $file_path,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // For .doc files (legacy format), extraction is more complex
        // For now, return a helpful message
        if ($mime_type === 'application/msword') {
            $this->logger?->warning('Files Input: Legacy .doc format not fully supported. Please use .docx or plain text.', [
                'file_path' => $file_path
            ]);
            return "Note: Legacy .doc file uploaded but text extraction not available. Please convert to .docx or .txt format for full content processing.";
        }

        return null;
    }

	/**
	 * Get settings fields for the Files input handler.
	 *
	 * @return array Associative array of field definitions (empty for this handler).
	 */
	public static function get_settings_fields(): array {
		// This handler currently has no specific settings.
		return [];
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
     * Comprehensive file security validation.
     *
     * @param array $file The uploaded file array from $_FILES
     * @param int $module_id Module ID for logging context
     * @throws Exception If file fails security validation
     */
    private function validate_file_security(array $file, int $module_id): void {
        // 1. File size validation
        $max_file_size = $this->get_max_file_size();
        if ($file['size'] > $max_file_size) {
            $error_message = sprintf(
                /* translators: %1$s: actual file size, %2$s: maximum allowed size */
                __('File too large: %1$s. Maximum allowed size: %2$s', 'data-machine'),
                size_format($file['size']),
                size_format($max_file_size)
            );
            $this->logger?->error('Files Input: File too large.', [
                'module_id' => $module_id,
                'file_size' => $file['size'],
                'max_size' => $max_file_size
            ]);
            throw new Exception(esc_html($error_message));
        }

        // 2. File extension validation
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = $this->get_allowed_file_extensions();
        if (!in_array($file_extension, $allowed_extensions)) {
            $error_message = sprintf(
                /* translators: %1$s: invalid file extension, %2$s: list of allowed extensions */
                __('Invalid file extension: %1$s. Allowed extensions: %2$s', 'data-machine'),
                $file_extension,
                implode(', ', $allowed_extensions)
            );
            $this->logger?->error('Files Input: Invalid file extension.', [
                'module_id' => $module_id,
                'extension' => $file_extension,
                'allowed' => $allowed_extensions
            ]);
            throw new Exception(esc_html($error_message));
        }

        // 3. MIME type validation with magic byte checking
        $this->validate_mime_type($file, $file_extension, $module_id);

        // 4. Filename sanitization check
        $this->validate_filename($file['name'], $module_id);

        // 5. Content scanning for basic malware patterns
        $this->scan_file_content($file['tmp_name'], $module_id);
    }

    /**
     * Get maximum allowed file size in bytes.
     *
     * @return int Maximum file size in bytes
     */
    private function get_max_file_size(): int {
        // Default 10MB, but respect PHP limits
        $default_max = 10 * 1024 * 1024; // 10MB
        $php_max = wp_max_upload_size();
        return min($default_max, $php_max);
    }

    /**
     * Get allowed file extensions.
     *
     * @return array Array of allowed file extensions
     */
    private function get_allowed_file_extensions(): array {
        return [
            'txt',
            'csv',
            'json',
            'pdf',
            'doc',
            'docx'
        ];
    }

    /**
     * Validate MIME type against file extension using magic bytes.
     *
     * @param array $file The uploaded file array
     * @param string $extension File extension
     * @param int $module_id Module ID for logging
     * @throws Exception If MIME type validation fails
     */
    private function validate_mime_type(array $file, string $extension, int $module_id): void {
        // Get expected MIME types for this extension
        $extension_mime_map = [
            'txt' => ['text/plain'],
            'csv' => ['text/csv', 'text/plain', 'application/csv'],
            'json' => ['application/json', 'text/plain'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        ];

        if (!isset($extension_mime_map[$extension])) {
            throw new Exception(esc_html__('Unsupported file extension.', 'data-machine'));
        }

        // Check reported MIME type
        $reported_mime = $file['type'];
        $expected_mimes = $extension_mime_map[$extension];
        
        if (!in_array($reported_mime, $expected_mimes)) {
            $this->logger?->error('Files Input: MIME type mismatch.', [
                'module_id' => $module_id,
                'reported_mime' => $reported_mime,
                'expected_mimes' => $expected_mimes
            ]);
            throw new Exception(esc_html__('File type does not match extension.', 'data-machine'));
        }

        // Magic byte validation using fileinfo
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $actual_mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if ($actual_mime && !in_array($actual_mime, $expected_mimes)) {
                $this->logger?->error('Files Input: Magic byte MIME type mismatch.', [
                    'module_id' => $module_id,
                    'actual_mime' => $actual_mime,
                    'expected_mimes' => $expected_mimes
                ]);
                throw new Exception(esc_html__('File content does not match declared type.', 'data-machine'));
            }
        }
    }

    /**
     * Validate and sanitize filename.
     *
     * @param string $filename Original filename
     * @param int $module_id Module ID for logging
     * @throws Exception If filename contains dangerous patterns
     */
    private function validate_filename(string $filename, int $module_id): void {
        // Check for dangerous patterns
        $dangerous_patterns = [
            '/\.php$/i',
            '/\.exe$/i',
            '/\.bat$/i',
            '/\.cmd$/i',
            '/\.scr$/i',
            '/\.htaccess$/i',
            '/\.\./i',  // Path traversal
            '/[<>:"|?*]/i'  // Invalid filename characters
        ];

        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $filename)) {
                $this->logger?->error('Files Input: Dangerous filename pattern detected.', [
                    'module_id' => $module_id,
                    'filename' => $filename,
                    'pattern' => $pattern
                ]);
                throw new Exception(esc_html__('Filename contains unsafe characters or patterns.', 'data-machine'));
            }
        }

        // Check filename length
        if (strlen($filename) > 255) {
            throw new Exception(esc_html__('Filename too long. Maximum 255 characters allowed.', 'data-machine'));
        }
    }

    /**
     * Basic content scanning for malware patterns.
     *
     * @param string $file_path Path to uploaded file
     * @param int $module_id Module ID for logging
     * @throws Exception If suspicious content is detected
     */
    private function scan_file_content(string $file_path, int $module_id): void {
        // Only scan first 8KB for performance
        $content = file_get_contents($file_path, false, null, 0, 8192);
        if ($content === false) {
            throw new Exception(esc_html__('Unable to read uploaded file for security scanning.', 'data-machine'));
        }

        // Basic malware patterns (extend as needed)
        $suspicious_patterns = [
            '/<script[^>]*>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',  // onload, onclick, etc.
            '/eval\s*\(/i',
            '/base64_decode\s*\(/i',
            '/shell_exec\s*\(/i',
            '/system\s*\(/i',
            '/exec\s*\(/i'
        ];

        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $this->logger?->error('Files Input: Suspicious content pattern detected.', [
                    'module_id' => $module_id,
                    'pattern' => $pattern
                ]);
                throw new Exception(esc_html__('Uploaded file contains suspicious content and cannot be processed.', 'data-machine'));
            }
        }
    }
}

