<?php
/**
 * REST API Files Endpoint
 *
 * Unified file API supporting both flow-level files and pipeline-level context.
 *
 * @package DataMachine\Api
 */

namespace DataMachine\Api;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('WPINC')) {
    die;
}

class Files {

    /**
     * Register REST API routes.
     */
    public static function register() {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    /**
     * Register /datamachine/v1/files endpoints.
     */
    public static function register_routes() {
        // POST /files - Upload file (flow or pipeline context)
        register_rest_route('datamachine/v1', '/files', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'handle_upload'],
            'permission_callback' => [self::class, 'check_permission'],
            'args' => [
                'flow_step_id' => [
                    'required' => false,
                    'type' => 'string',
                    'description' => __('Flow step ID for flow-level files', 'datamachine'),
                    'sanitize_callback' => function($param) {
                        return sanitize_text_field($param);
                    }
                ],
                'pipeline_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'description' => __('Pipeline ID for pipeline context files', 'datamachine'),
                    'sanitize_callback' => function($param) {
                        return absint($param);
                    }
                ]
            ],
        ]);

        // GET /files - List files (flow or pipeline context)
        register_rest_route('datamachine/v1', '/files', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'list_files'],
            'permission_callback' => [self::class, 'check_permission'],
            'args' => [
                'flow_step_id' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => function($param) {
                        return sanitize_text_field($param);
                    }
                ],
                'pipeline_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => function($param) {
                        return absint($param);
                    }
                ]
            ],
        ]);

        // DELETE /files/{filename} - Delete file (flow or pipeline context)
        register_rest_route('datamachine/v1', '/files/(?P<filename>[^/]+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [self::class, 'delete_file'],
            'permission_callback' => [self::class, 'check_permission'],
            'args' => [
                'filename' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => function($param) {
                        return sanitize_file_name($param);
                    }
                ],
                'flow_step_id' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => function($param) {
                        return sanitize_text_field($param);
                    }
                ],
                'pipeline_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => function($param) {
                        return absint($param);
                    }
                ]
            ],
        ]);
    }

    /**
     * Check user permission
     */
    public static function check_permission($request) {
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to manage files.', 'datamachine'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Handle file upload for flow files or pipeline context
     */
    public static function handle_upload(WP_REST_Request $request) {
        $flow_step_id = $request->get_param('flow_step_id');
        $pipeline_id = $request->get_param('pipeline_id');

        // Validate: one or the other required
        if (!$flow_step_id && !$pipeline_id) {
            return new WP_Error(
                'missing_scope',
                __('Must provide either flow_step_id or pipeline_id.', 'datamachine'),
                ['status' => 400]
            );
        }

        if ($flow_step_id && $pipeline_id) {
            return new WP_Error(
                'conflicting_scope',
                __('Cannot provide both flow_step_id and pipeline_id.', 'datamachine'),
                ['status' => 400]
            );
        }

        $files = $request->get_file_params();
        if (empty($files['file'])) {
            return new WP_Error(
                'missing_file',
                __('File upload is required.', 'datamachine'),
                ['status' => 400]
            );
        }

        $uploaded = $files['file'];
        if (!is_array($uploaded)) {
            return new WP_Error(
                'invalid_file_structure',
                __('Invalid file upload payload.', 'datamachine'),
                ['status' => 400]
            );
        }

        $upload_error = intval($uploaded['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($upload_error !== UPLOAD_ERR_OK) {
            return new WP_Error(
                'upload_failed',
                __('File upload failed.', 'datamachine'),
                ['status' => 400]
            );
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tmp_name is validated below via is_uploaded_file()
        $tmp_name = $uploaded['tmp_name'] ?? '';
        if (empty($tmp_name) || !is_uploaded_file($tmp_name)) {
            return new WP_Error(
                'invalid_tmp_name',
                __('Invalid temporary file.', 'datamachine'),
                ['status' => 400]
            );
        }

        $file = [
            'name' => sanitize_file_name($uploaded['name'] ?? ''),
            'type' => sanitize_mime_type($uploaded['type'] ?? ''),
            'tmp_name' => $tmp_name,
            'error' => $upload_error,
            'size' => intval($uploaded['size'] ?? 0),
        ];

        try {
            self::validate_file($file);
        } catch (\Exception $e) {
            do_action('datamachine_log', 'error', 'File upload failed validation.', [
                'filename' => $file['name'],
                'error' => $e->getMessage(),
            ]);

            return new WP_Error(
                'file_validation_failed',
                $e->getMessage(),
                ['status' => 400]
            );
        }

        $storage = new \DataMachine\Core\FilesRepository\FileStorage();

        // PIPELINE CONTEXT
        if ($pipeline_id) {
            $db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
            $context_files = $db_pipelines->get_pipeline_context_files($pipeline_id);

            return rest_ensure_response([
                'success' => true,
                'files' => $context_files['uploaded_files'] ?? [],
                'scope' => 'pipeline'
            ]);
        }

        // FLOW FILES
        if ($flow_step_id) {
            $parts = apply_filters('datamachine_split_flow_step_id', null, $flow_step_id);
            if (!$parts || empty($parts['flow_id'])) {
                return new WP_Error(
                    'invalid_flow_step_id',
                    __('Invalid flow step ID format.', 'datamachine'),
                    ['status' => 400]
                );
            }

            $context = self::get_file_context($parts['flow_id']);
            $files = $storage ? $storage->get_all_files($context) : [];

            return rest_ensure_response([
                'success' => true,
                'files' => $files,
                'scope' => 'flow'
            ]);
        }
    }

    /**
     * Delete file (flow or pipeline context)
     */
    public static function delete_file(WP_REST_Request $request) {
        $filename = sanitize_file_name($request['filename']);
        $flow_step_id = $request->get_param('flow_step_id');
        $pipeline_id = $request->get_param('pipeline_id');

        if (!$flow_step_id && !$pipeline_id) {
            return new WP_Error(
                'missing_scope',
                __('Must provide either flow_step_id or pipeline_id.', 'datamachine'),
                ['status' => 400]
            );
        }

        $storage = new \DataMachine\Core\FilesRepository\FileStorage();

        // PIPELINE CONTEXT
        if ($pipeline_id) {
            $db_pipelines = new \DataMachine\Core\Database\Pipelines\Pipelines();
            $context_files = $db_pipelines->get_pipeline_context_files($pipeline_id);
            $uploaded_files = $context_files['uploaded_files'] ?? [];

            foreach ($uploaded_files as $index => $file) {
                if ($file['original_name'] === $filename) {
                    if (file_exists($file['persistent_path'])) {
                        wp_delete_file($file['persistent_path']);
                    }
                    unset($uploaded_files[$index]);
                    break;
                }
            }

            $context_files['uploaded_files'] = array_values($uploaded_files);
            $db_pipelines->update_pipeline_context_files($pipeline_id, $context_files);

            do_action('datamachine_log', 'debug', 'Pipeline context file deleted.', [
                'filename' => $filename,
                'pipeline_id' => $pipeline_id
            ]);

            return rest_ensure_response([
                'success' => true,
                'scope' => 'pipeline'
            ]);
        }

        // FLOW FILES
        if ($flow_step_id) {
            $parts = apply_filters('datamachine_split_flow_step_id', null, $flow_step_id);
            if (!$parts || empty($parts['flow_id'])) {
                return new WP_Error(
                    'invalid_flow_step_id',
                    __('Invalid flow step ID format.', 'datamachine'),
                    ['status' => 400]
                );
            }

            $context = self::get_file_context($parts['flow_id']);
            $deleted = $storage ? $storage->delete_file($filename, $context) : false;

            do_action('datamachine_log', 'debug', 'Flow file deleted.', [
                'filename' => $filename,
                'flow_step_id' => $flow_step_id,
                'success' => $deleted
            ]);

            return rest_ensure_response([
                'success' => $deleted,
                'scope' => 'flow'
            ]);
        }
    }

    /**
     * Get file context array from flow ID
     */
    public static function get_file_context(int $flow_id): array {
        $db_flows = new \DataMachine\Core\Database\Flows\Flows();
        $flow_data = $db_flows->get_flow($flow_id);

        if (!isset($flow_data['pipeline_id']) || empty($flow_data['pipeline_id'])) {
            throw new \InvalidArgumentException('Flow data missing required pipeline_id');
        }

        $pipeline_id = $flow_data['pipeline_id'];

        return [
            'pipeline_id' => $pipeline_id,
            'flow_id' => $flow_id
        ];
    }

    /**
     * Validate uploaded file
     *
     * @throws \Exception When validation fails
     */
    private static function validate_file(array $file): void {
        $file_size = filesize($file['tmp_name']);
        if ($file_size === false) {
            throw new \Exception(__('Cannot determine file size.', 'datamachine'));
        }

        $max_file_size = wp_max_upload_size();
        if ($file_size > $max_file_size) {
            throw new \Exception(sprintf(
                /* translators: %1$s: Current file size, %2$s: Maximum allowed file size */
                __('File too large: %1$s. Maximum allowed size: %2$s', 'datamachine'),
                size_format($file_size),
                size_format($max_file_size)
            ));
        }

        $dangerous_extensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'pht', 'phar', 'exe', 'bat', 'cmd', 'scr', 'com', 'pif', 'vbs', 'js', 'jar', 'msi', 'dll', 'sh', 'pl', 'py', 'rb', 'asp', 'aspx', 'jsp', 'htaccess'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($file_extension, $dangerous_extensions, true)) {
            throw new \Exception(__('File type not allowed for security reasons.', 'datamachine'));
        }

        if (strpos($file['name'], '..') !== false || strpos($file['name'], '/') !== false || strpos($file['name'], '\\') !== false) {
            throw new \Exception(__('Invalid file name detected.', 'datamachine'));
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected_mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $dangerous_mimes = [
                'application/x-executable',
                'application/x-dosexec',
                'application/x-msdownload',
                'application/x-php',
                'text/x-php',
                'application/php',
            ];

            if ($detected_mime && in_array($detected_mime, $dangerous_mimes, true)) {
                throw new \Exception(__('File content type not allowed for security reasons.', 'datamachine'));
            }
        }
    }
}
