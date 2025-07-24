<?php
/**
 * Handles file uploads for the file queue system.
 * 
 * This class is responsible for uploading files and associating them with specific modules.
 * It does NOT create jobs - that's handled by the existing pipeline.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/admin/projects
 * @since      NEXT_VERSION
 */

namespace DataMachine\Admin\Projects;

use DataMachine\Database\{Modules, Projects};
use DataMachine\Helpers\Logger;

/**
 * Handles file uploads and queue management for projects.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
class FileUploadHandler {

    /**
     * Database modules instance.
     *
     * @since    NEXT_VERSION
     * @access   private
     * @var      Modules
     */
    private $db_modules;

    /**
     * Database projects instance.
     *
     * @since    NEXT_VERSION
     * @access   private
     * @var      Projects
     */
    private $db_projects;

    /**
     * Logger instance.
     *
     * @since    NEXT_VERSION
     * @access   private
     * @var      Logger|null
     */
    private $logger;

    /**
     * Initialize the class and set its properties.
     *
     * @since    NEXT_VERSION
     * @param    Modules  $db_modules   Database modules instance.
     * @param    Projects $db_projects  Database projects instance.  
     * @param    Logger|null       $logger       Logger instance.
     */
    public function __construct(
        Modules $db_modules,
        Projects $db_projects,
        ?Logger $logger = null
    ) {
        $this->db_modules = $db_modules;
        $this->db_projects = $db_projects;
        $this->logger = $logger;

        // Register AJAX actions
        add_action( 'wp_ajax_dm_upload_files_to_queue', array( $this, 'handle_upload_files_ajax' ) );
    }

    /**
     * Process file uploads and associate them with the module.
     *
     * @since    NEXT_VERSION
     * @param    int    $project_id  Project ID.
     * @param    int    $module_id   Module ID to upload files for.
     * @param    array  $files       Files array from $_FILES.
     * @return   array  Result array with success/error information.
     */
    public function upload_files(int $project_id, int $module_id, array $files): array {
        $this->logger?->info('File Upload: Starting file upload.', [
            'project_id' => $project_id,
            'module_id' => $module_id,
            'file_count' => count($files)
        ]);

        try {
            // Validate project and module
            $user_id = get_current_user_id();
            $project = $this->db_projects->get_project($project_id, $user_id);
            if (!$project) {
                throw new Exception('Invalid project ID or access denied.');
            }

            $module = $this->db_modules->get_module($module_id, $user_id);
            if (!$module || $module->project_id != $project_id) {
                throw new Exception('Invalid module ID or module does not belong to project.');
            }

            // Verify this is a file module
            if ($module->data_source_type !== 'files') {
                throw new Exception('Module is not configured for file uploads.');
            }

            // Process each uploaded file
            $uploaded_files = [];
            $persistent_dir = $this->get_persistent_upload_directory();

            foreach ($files as $file_key => $file) {
                if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                    continue; // Skip empty file inputs
                }

                $uploaded_file = $this->process_single_file($file, $module_id, $persistent_dir);
                if ($uploaded_file) {
                    $uploaded_files[] = $uploaded_file;
                }
            }

            if (empty($uploaded_files)) {
                throw new Exception('No valid files were uploaded.');
            }

            // Add uploaded files to the module's file list
            $this->add_files_to_module($module_id, $uploaded_files);

            $this->logger?->info('File Upload: Successfully uploaded files.', [
                'module_id' => $module_id,
                'uploaded_count' => count($uploaded_files)
            ]);

            return [
                'success' => true,
                'message' => sprintf(
                    'Successfully uploaded %d file(s).',
                    count($uploaded_files)
                ),
                'uploaded_files' => $uploaded_files
            ];

        } catch (Exception $e) {
            $this->logger?->error('File Upload: Error uploading files.', [
                'project_id' => $project_id,
                'module_id' => $module_id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'File upload failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process a single uploaded file.
     *
     * @since    NEXT_VERSION
     * @param    array  $file           File array from $_FILES.
     * @param    int    $module_id      Module ID.
     * @param    string $persistent_dir Upload directory path.
     * @return   array|null File info array or null on failure.
     */
    private function process_single_file(array $file, int $module_id, string $persistent_dir): ?array {
        try {
            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception($this->get_upload_error_message($file['error']));
            }

            // Validate file security (reuse existing logic)
            $this->validate_file_security($file);

            // Create unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'queue_' . $module_id . '_' . time() . '_' . wp_generate_password(8, false) . '.' . sanitize_file_name($file_extension);
            $persistent_path = $persistent_dir . $new_filename;

            // Move file to persistent storage
            if (!move_uploaded_file($file['tmp_name'], $persistent_path)) {
                throw new Exception('Failed to move uploaded file to persistent storage.');
            }

            return [
                'original_name' => sanitize_file_name($file['name']),
                'persistent_path' => $persistent_path,
                'mime_type' => $file['type'],
                'size' => $file['size'],
                'uploaded_at' => current_time('mysql'),
                'status' => 'pending'
            ];

        } catch (Exception $e) {
            $this->logger?->error('File Upload: Error processing single file.', [
                'filename' => $file['name'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }


    /**
     * Get the persistent upload directory.
     *
     * @since    NEXT_VERSION
     * @return   string Upload directory path.
     */
    private function get_persistent_upload_directory(): string {
        $upload_dir = wp_upload_dir();
        $persistent_dir = $upload_dir['basedir'] . '/dm_persistent_jobs/';
        wp_mkdir_p($persistent_dir); // Ensure directory exists
        return $persistent_dir;
    }

    /**
     * Get upload error message.
     *
     * @since    NEXT_VERSION
     * @param    int $error_code PHP upload error code.
     * @return   string Error message.
     */
    private function get_upload_error_message(int $error_code): string {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
        ];

        return $error_messages[$error_code] ?? 'Unknown upload error.';
    }

    /**
     * Validate file security (reuse existing logic from Files Input Handler).
     *
     * @since    NEXT_VERSION
     * @param    array $file File array from $_FILES.
     * @throws   Exception If file fails security validation.
     */
    private function validate_file_security(array $file): void {
        // File size validation
        $max_size = 100 * 1024 * 1024; // 100MB limit
        if ($file['size'] > $max_size) {
            throw new Exception('File size exceeds 100MB limit.');
        }

        // File extension validation
        $allowed_extensions = ['txt', 'csv', 'json', 'pdf', 'docx', 'doc', 'jpg', 'jpeg', 'png', 'gif'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions, true)) {
            throw new Exception('File type not allowed. Allowed types: ' . implode(', ', $allowed_extensions));
        }

        // MIME type validation
        $allowed_mime_types = [
            'text/plain', 'text/csv', 'application/json', 
            'application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword', 'image/jpeg', 'image/jpg', 'image/png', 'image/gif'
        ];

        if (!in_array($file['type'], $allowed_mime_types, true)) {
            throw new Exception('MIME type not allowed: ' . $file['type']);
        }

        // Basic security scan for suspicious content
        $dangerous_patterns = ['<script', '<?php', '<%', 'javascript:', 'vbscript:'];
        $file_content_sample = file_get_contents($file['tmp_name'], false, null, 0, 1024); // Read first 1KB

        foreach ($dangerous_patterns as $pattern) {
            if (stripos($file_content_sample, $pattern) !== false) {
                throw new Exception('File contains potentially dangerous content.');
            }
        }
    }


    /**
     * Handle AJAX request to upload files.
     *
     * @since    NEXT_VERSION
     */
    public function handle_upload_files_ajax() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'dm_upload_files_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce.' );
            return;
        }

        // Check user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
            return;
        }

        // Validate required parameters
        $project_id = absint( $_POST['project_id'] ?? 0 );
        $module_id = absint( $_POST['module_id'] ?? 0 );

        if ( empty( $project_id ) || empty( $module_id ) ) {
            wp_send_json_error( 'Missing project ID or module ID.' );
            return;
        }

        // Check if files were uploaded
        if ( empty( $_FILES['file_uploads'] ) ) {
            wp_send_json_error( 'No files were uploaded.' );
            return;
        }

        try {
            // Process uploaded files
            $files = $this->normalize_files_array( $_FILES['file_uploads'] );
            
            $result = $this->upload_files( $project_id, $module_id, $files );

            if ( $result['success'] ) {
                wp_send_json_success( $result );
            } else {
                wp_send_json_error( $result['message'] );
            }

        } catch ( Exception $e ) {
            $this->logger?->error( 'File Upload AJAX: Error handling upload request.', [
                'project_id' => $project_id,
                'module_id' => $module_id,
                'error' => $e->getMessage()
            ] );

            wp_send_json_error( 'Upload failed: ' . $e->getMessage() );
        }
    }

    /**
     * Normalize the $_FILES array for multiple file uploads.
     *
     * PHP's $_FILES array for multiple files has a weird structure where
     * all the 'name' values are in one array, all 'type' values in another, etc.
     * This function restructures it to be more manageable.
     *
     * @since    NEXT_VERSION
     * @param    array $files_array The $_FILES array for the file input.
     * @return   array Normalized array of file information.
     */
    private function normalize_files_array( array $files_array ): array {
        $normalized = [];

        if ( ! isset( $files_array['name'] ) || ! is_array( $files_array['name'] ) ) {
            return $normalized;
        }

        $file_count = count( $files_array['name'] );

        for ( $i = 0; $i < $file_count; $i++ ) {
            // Skip empty file inputs
            if ( empty( $files_array['name'][$i] ) ) {
                continue;
            }

            $normalized[] = [
                'name'     => $files_array['name'][$i],
                'type'     => $files_array['type'][$i],
                'tmp_name' => $files_array['tmp_name'][$i],
                'error'    => $files_array['error'][$i],
                'size'     => $files_array['size'][$i]
            ];
        }

        return $normalized;
    }

    /**
     * Add uploaded files to the module's configuration.
     *
     * @since    NEXT_VERSION
     * @param    int   $module_id Module ID.
     * @param    array $uploaded_files Array of uploaded file info.
     * @return   bool True on success, false on failure.
     */
    private function add_files_to_module(int $module_id, array $uploaded_files): bool {
        if (empty($uploaded_files)) {
            return true;
        }

        // Get current module data
        $module = $this->db_modules->get_module($module_id);
        if (!$module) {
            $this->logger?->error('File Upload: Module not found.', ['module_id' => $module_id]);
            return false;
        }

        // Get current data source config
        $data_source_config = json_decode($module->data_source_config ?? '{}', true);
        if (!is_array($data_source_config)) {
            $data_source_config = [];
        }

        // Get current uploaded files or initialize empty array
        $existing_files = $data_source_config['uploaded_files'] ?? [];
        if (!is_array($existing_files)) {
            $existing_files = [];
        }

        // Add new files to the list
        foreach ($uploaded_files as $file_info) {
            $existing_files[] = $file_info;
        }

        // Update the data source config
        $data_source_config['uploaded_files'] = $existing_files;

        // Update the module in the database
        $success = $this->db_modules->update_module($module_id, [
            'data_source_config' => json_encode($data_source_config)
        ]);

        if ($success) {
            $this->logger?->info('File Upload: Added files to module.', [
                'module_id' => $module_id,
                'added_count' => count($uploaded_files),
                'total_files' => count($existing_files)
            ]);
        } else {
            $this->logger?->error('File Upload: Failed to add files to module.', [
                'module_id' => $module_id
            ]);
        }

        return $success;
    }
}