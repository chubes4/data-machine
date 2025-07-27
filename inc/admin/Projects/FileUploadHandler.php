<?php
/**
 * REMOVED: FileUploadHandler.php - Dead code cleanup Phase 2
 * 
 * This file contained file upload functionality for the deprecated file queue system.
 * File uploads are now handled directly through the pipeline system and Files input handler.
 * 
 * Removed during Phase 2 conservative dead code cleanup:
 * - Confirmed dead: File upload queue system deprecated
 * - No active usage: Class not referenced in filter system or AJAX handlers
 * - Migration complete: File handling moved to Files input handler
 * 
 * @package    Data_Machine
 * @subpackage Data_Machine/cleanup
 * @since      NEXT_VERSION
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// This file has been removed as part of the dead code cleanup.
// FileUploadHandler functionality has been replaced by the Files input handler.
// 
// Previous functionality:
// - File upload processing and validation
// - File queue management for modules
// - AJAX file upload handling
// 
// New location of functionality:
// - File processing: Files input handler (inc/core/handlers/input/files/)
// - File validation: Built into Files handler sanitize_settings method
// - Upload handling: Direct integration with pipeline system
// 
// This file remains as a placeholder to document the migration and prevent
// accidental recreation of deprecated functionality.
/**
 * REMOVED: FileUploadHandler class
 * File upload functionality moved to Files input handler
 */
class FileUploadHandler {
    // REMOVED: All properties moved to Files input handler

    /**
     * REMOVED: Constructor and initialization
     * File upload functionality moved to Files input handler
     */
    public function __construct() {
        // REMOVED: All functionality moved to Files input handler
        // This constructor is preserved to prevent fatal errors if this class
        // is somehow still referenced, but functionality has been moved to:
        // - Files input handler: inc/core/handlers/input/files/Files.php
        // - Pipeline system: Direct file processing through input steps
    }

    /**
     * REMOVED: File upload processing
     * Functionality moved to Files input handler
     */
    public function upload_files(int $project_id, int $module_id, array $files): array {
        // REMOVED: File upload processing moved to Files input handler
        // This method is preserved to prevent fatal errors if somehow still called
        return [
            'success' => false,
            'message' => 'File upload functionality has been moved to Files input handler'
        ];
    }

    /**
     * REMOVED: Single file processing
     * Functionality moved to Files input handler
     */
    private function process_single_file(array $file, int $module_id, string $persistent_dir): ?array {
        // REMOVED: File processing moved to Files input handler
        return null;
    }


    /**
     * REMOVED: All file processing helper methods
     * File processing functionality moved to Files input handler
     */
    private function get_persistent_upload_directory(): string {
        // REMOVED: Directory management moved to Files input handler
        return '';
    }

    private function get_upload_error_message(int $error_code): string {
        // REMOVED: Error handling moved to Files input handler
        return 'Upload functionality moved to Files input handler';
    }

    private function validate_file_security(array $file): void {
        // REMOVED: File validation moved to Files input handler
    }


    /**
     * REMOVED: All AJAX and file management methods
     * File upload functionality moved to Files input handler
     */
    public function handle_upload_files_ajax() {
        // REMOVED: AJAX handling moved to Files input handler
        wp_send_json_error('File upload functionality has been moved to Files input handler');
    }

    private function normalize_files_array( array $files_array ): array {
        // REMOVED: File array normalization moved to Files input handler
        return [];
    }

    private function add_files_to_module(int $module_id, array $uploaded_files): bool {
        // REMOVED: Module file management moved to Files input handler
        return false;
    }
}