<?php
/**
 * Handles file uploads as a data source.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/input
 * @since      0.7.0
 */
class Data_Machine_Input_Files implements Data_Machine_Input_Handler_Interface {

	use Data_Machine_Base_Input_Handler;

	/** @var Data_Machine_Database_Modules */
	private $db_modules;

	/** @var Data_Machine_Database_Projects */
	private $db_projects;

    /** @var ?Data_Machine_Logger */
    private $logger;

	/**
	 * Constructor. Dependencies are injected.
	 *
	 * @param Data_Machine_Database_Modules $db_modules Database modules handler.
     * @param Data_Machine_Database_Projects $db_projects Database projects handler.
     * @param Data_Machine_Logger|null $logger Optional logger.
	 */
	public function __construct(
        Data_Machine_Database_Modules $db_modules,
        Data_Machine_Database_Projects $db_projects,
        ?Data_Machine_Logger $logger = null
    ) {
	 $this->db_modules = $db_modules;
     $this->db_projects = $db_projects;
     $this->logger = $logger;
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
        $this->logger?->info('Files Input: Entering get_input_data.', ['module_id' => $module->module_id ?? null]);

        // Ensure user ID is valid (passed from context)
		if ( empty($user_id) ) {
            $this->logger?->error('Files Input: User ID not provided.', ['module_id' => $module->module_id ?? null]);
			throw new Exception(__( 'User ID not provided for file processing.', 'data-machine' ));
		}

		// Get module ID from the passed module object
		$module_id = isset($module->module_id) ? absint($module->module_id) : 0;
		if ( empty( $module_id ) ) {
            $this->logger?->error('Files Input: Module ID missing from module object.');
			throw new Exception(__( 'Missing module ID.', 'data-machine' ));
		}

        // Check if dependencies were injected correctly
		if (!$this->db_modules || !$this->db_projects) {
            $this->logger?->error('Files Input: Required database service not available.', ['module_id' => $module_id]);
			throw new Exception(__( 'Required database service not available in Files handler.', 'data-machine' ));
		}

		// Ownership check (using the trait method)
		$project = $this->get_module_with_ownership_check($module, $user_id, $this->db_projects);

        // Get the uploaded file data from the $_FILES superglobal
        // NOTE: This handler assumes the job is triggered in a context where $_FILES is populated.
        // Direct execution or background jobs might need different ways to access the file.
        if (empty($_FILES['file_upload'])) {
            $this->logger?->error('Files Input: No file data found in $_FILES[\'file_upload\'].', ['module_id' => $module_id]);
            throw new Exception(__( 'No file uploaded for processing. Check the upload mechanism.', 'data-machine' ));
        }

		// File handling logic starts here...
		$file = $_FILES['file_upload'];

		// Check for upload errors
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
            $error_message = $this->get_upload_error_message($file['error']);
            $this->logger?->error('Files Input: File upload error.', ['module_id' => $module_id, 'error_code' => $file['error'], 'error_message' => $error_message]);
			throw new Exception($error_message);
		}

		// Comprehensive file security validation
		$this->validate_file_security($file, $module_id);

		// Move uploaded file to a persistent location
		$upload_dir = wp_upload_dir();
		$persistent_dir = $upload_dir['basedir'] . '/dm_persistent_jobs/';
		wp_mkdir_p( $persistent_dir ); // Ensure directory exists

		// Create a unique filename
		$file_extension = pathinfo( $file['name'], PATHINFO_EXTENSION );
		$new_filename = 'job_' . $module_id . '_' . time() . '_' . wp_generate_password( 8, false ) . '.' . sanitize_file_name($file_extension);
		$persistent_path = $persistent_dir . $new_filename;

		if ( ! move_uploaded_file( $file['tmp_name'], $persistent_path ) ) {
            $this->logger?->error('Files Input: Failed to move uploaded file.', ['module_id' => $module_id, 'tmp_name' => $file['tmp_name'], 'destination' => $persistent_path]);
			throw new Exception(__( 'Failed to move uploaded file to persistent storage.', 'data-machine' ));
		}
        $this->logger?->info('Files Input: File moved to persistent storage.', ['module_id' => $module_id, 'path' => $persistent_path]);

		// Prepare packet (Content string is NOT included here, it will be read later by the orchestrator)
		$input_data_packet = [
			'data' => [
                 // Content string is intentionally omitted here
                 'content_string' => null, 
                 'file_info' => [
                     'original_name' => sanitize_file_name($file['name']),
                     'mime_type' => $file['type'],
                     'size' => $file['size'],
                     'persistent_path' => $persistent_path // Path for later reading and cleanup
                 ]
             ],
			'metadata' => [
				'source_type' => 'files',
                'item_identifier_to_log' => $persistent_path, // Use path as a unique identifier for processing log
                'original_id' => $persistent_path, // Use path as original ID
                'original_title' => sanitize_file_name($file['name']),
                'original_date_gmt' => gmdate('Y-m-d H:i:s'), // Use current time as upload time
                // Add other relevant metadata if needed
			]
		];

		// Return the packet wrapped in an array, as expected by the Job Executor
        $this->logger?->info('Files Input: Prepared data packet.', ['module_id' => $module_id, 'file_path' => $persistent_path]);
		return [$input_data_packet];
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
            throw new Exception($error_message);
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
            throw new Exception($error_message);
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
            throw new Exception(__('Unsupported file extension.', 'data-machine'));
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
            throw new Exception(__('File type does not match extension.', 'data-machine'));
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
                throw new Exception(__('File content does not match declared type.', 'data-machine'));
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
                throw new Exception(__('Filename contains unsafe characters or patterns.', 'data-machine'));
            }
        }

        // Check filename length
        if (strlen($filename) > 255) {
            throw new Exception(__('Filename too long. Maximum 255 characters allowed.', 'data-machine'));
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
            throw new Exception(__('Unable to read uploaded file for security scanning.', 'data-machine'));
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
                throw new Exception(__('Uploaded file contains suspicious content and cannot be processed.', 'data-machine'));
            }
        }
    }
}

