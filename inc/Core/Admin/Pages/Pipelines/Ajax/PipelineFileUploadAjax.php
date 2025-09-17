<?php
/**
 * Pipeline File Upload AJAX Handler
 *
 * Handles secure file upload operations with flow-isolated storage.
 * Implements comprehensive security validation, size limits, and dangerous file type blocking.
 *
 * @package DataMachine\Core\Admin\Pages\Pipelines\Ajax
 * @since 1.0.0
 */

namespace DataMachine\Core\Admin\Pages\Pipelines\Ajax;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class PipelineFileUploadAjax
{
    /**
     * Register pipeline file upload AJAX handlers.
     */
    public static function register() {
        $instance = new self();

        // File upload AJAX action
        add_action('wp_ajax_dm_upload_file', [$instance, 'handle_upload_file']);
    }

    /**
     * Process file uploads with flow-isolated storage.
     *
     * Validates file security (size limits, dangerous extensions) and stores
     * files using flow_step_id isolation via the files repository service.
     * Implements 32MB size limit and blocks executable file types.
     */
    public function handle_upload_file()
    {
        check_ajax_referer('dm_ajax_actions', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'data-machine')]);
        }

        // Check if file was uploaded
        if (!isset($_FILES['file']) || !isset($_FILES['file']['error']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => __('File upload failed.', 'data-machine')]);
            return;
        }

        // Validate file upload
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            wp_send_json_error(['message' => __('No file uploaded or invalid file structure.', 'data-machine')]);
        }

        // Sanitize and validate file upload data
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tmp_name is system-generated path, validated with is_uploaded_file()
        $tmp_name = $_FILES['file']['tmp_name'] ?? '';

        // Validate tmp_name is a valid uploaded file
        if (empty($tmp_name) || !is_uploaded_file($tmp_name)) {
            wp_send_json_error(['message' => __('Invalid temporary file.', 'data-machine')]);
            return;
        }

        $file = array(
            'name' => sanitize_file_name($_FILES['file']['name'] ?? ''),
            'type' => sanitize_mime_type($_FILES['file']['type'] ?? ''),
            'tmp_name' => $tmp_name,
            'error' => intval($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => intval($_FILES['file']['size'] ?? 0)
        );

        // Extract flow_step_id from request for proper file isolation
        // Use the flow_step_id provided by the frontend form
        $flow_step_id = sanitize_text_field(wp_unslash($_POST['flow_step_id'] ?? ''));

        if (empty($flow_step_id)) {
            wp_send_json_error(['message' => __('Missing flow step ID from form data.', 'data-machine')]);
            return;
        }

        try {
            // Basic validation - file size and dangerous extensions
            $file_size = filesize($file['tmp_name']);
            if ($file_size === false) {
                throw new \Exception(__('Cannot determine file size.', 'data-machine'));
            }

            // 32MB limit
            $max_file_size = 32 * 1024 * 1024;
            if ($file_size > $max_file_size) {
                throw new \Exception(sprintf(
                    /* translators: %1$s: Current file size, %2$s: Maximum allowed file size */
                    __('File too large: %1$s. Maximum allowed size: %2$s', 'data-machine'),
                    size_format($file_size),
                    size_format($max_file_size)
                ));
            }

            // Block dangerous extensions with comprehensive list
            $dangerous_extensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'pht', 'phar', 'exe', 'bat', 'cmd', 'scr', 'com', 'pif', 'vbs', 'js', 'jar', 'msi', 'dll', 'sh', 'pl', 'py', 'rb', 'asp', 'aspx', 'jsp', 'htaccess'];
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (in_array($file_extension, $dangerous_extensions, true)) {
                throw new \Exception(__('File type not allowed for security reasons.', 'data-machine'));
            }

            // Validate file name contains no directory traversal attempts
            if (strpos($file['name'], '..') !== false || strpos($file['name'], '/') !== false || strpos($file['name'], '\\') !== false) {
                throw new \Exception(__('Invalid file name detected.', 'data-machine'));
            }

            // Additional MIME type validation for common file types
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected_mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                // Block executable MIME types
                $dangerous_mimes = [
                    'application/x-executable',
                    'application/x-dosexec',
                    'application/x-msdownload',
                    'application/x-php',
                    'text/x-php',
                    'application/php'
                ];

                if (in_array($detected_mime, $dangerous_mimes, true)) {
                    throw new \Exception(__('File content type not allowed for security reasons.', 'data-machine'));
                }
            }

            // Use repository to store file with handler context
            $repositories = apply_filters('dm_files_repository', []);
            $repository = $repositories['files'] ?? null;
            if (!$repository) {
                wp_send_json_error(['message' => __('File repository service not available.', 'data-machine')]);
                return;
            }

            $stored_path = $repository->store_file($file['tmp_name'], $file['name'], $flow_step_id);

            if (!$stored_path) {
                wp_send_json_error(['message' => __('Failed to store file.', 'data-machine')]);
                return;
            }

            // Get file info for response
            $file_info = $repository->get_file_info(basename($stored_path));

            do_action('dm_log', 'debug', 'File uploaded successfully via AJAX.', [
                'filename' => $file['name'],
                'stored_path' => $stored_path,
                'flow_step_id' => $flow_step_id
            ]);

            wp_send_json_success([
                'file_info' => $file_info,
                /* translators: %s: Uploaded file name */
                'message' => sprintf(__('File "%s" uploaded successfully.', 'data-machine'), $file['name'])
            ]);

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'File upload failed.', [
                'filename' => $file['name'],
                'error' => $e->getMessage(),
                'flow_step_id' => $flow_step_id
            ]);

            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}