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
                    'description' => __('Flow step ID for flow-level files', 'data-machine'),
                    'sanitize_callback' => function($param) {
                        return sanitize_text_field($param);
                    }
                ],
                'pipeline_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'description' => __('Pipeline ID for pipeline context files', 'data-machine'),
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
                __('You do not have permission to manage files.', 'data-machine'),
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
                __('Must provide either flow_step_id or pipeline_id.', 'data-machine'),
                ['status' => 400]
            );
        }

        if ($flow_step_id && $pipeline_id) {
            return new WP_Error(
                'conflicting_scope',
                __('Cannot provide both flow_step_id and pipeline_id.', 'data-machine'),
                ['status' => 400]
            );
        }

        $files = $request->get_file_params();
        if (empty($files['file'])) {
            return new WP_Error(
                'missing_file',
                __('File upload is required.', 'data-machine'),
                ['status' => 400]
            );
        }

        $uploaded = $files['file'];
        if (!is_array($uploaded)) {
            return new WP_Error(
                'invalid_file_structure',
                __('Invalid file upload payload.', 'data-machine'),
                ['status' => 400]
            );
        }

        $upload_error = intval($uploaded['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($upload_error !== UPLOAD_ERR_OK) {
            return new WP_Error(
                'upload_failed',
                __('File upload failed.', 'data-machine'),
                ['status' => 400]
            );
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tmp_name is validated below via is_uploaded_file()
        $tmp_name = $uploaded['tmp_name'] ?? '';
        if (empty($tmp_name) || !is_uploaded_file($tmp_name)) {
            return new WP_Error(
                'invalid_tmp_name',
                __('Invalid temporary file.', 'data-machine'),
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

        $repositories = apply_filters('datamachine_files_repository', []);
        $repository = $repositories['files'] ?? null;
        if (!$repository) {
            return new WP_Error(
                'files_repository_unavailable',
                __('File repository service not available.', 'data-machine'),
                ['status' => 500]
            );
        }

        // PIPELINE CONTEXT
        if ($pipeline_id) {
            $pipeline = apply_filters('datamachine_get_pipelines', [], $pipeline_id);
            if (empty($pipeline)) {
                return new WP_Error(
                    'pipeline_not_found',
                    __('Pipeline not found.', 'data-machine'),
                    ['status' => 404]
                );
            }

            $pipeline_name = $pipeline['pipeline_name'] ?? "pipeline-{$pipeline_id}";

            $file_info = $repository->store_pipeline_file($pipeline_id, $pipeline_name, [
                'source_path' => $file['tmp_name'],
                'original_name' => $file['name']
            ]);

            if (!$file_info) {
                return new WP_Error(
                    'pipeline_file_store_failed',
                    __('Failed to store pipeline context file.', 'data-machine'),
                    ['status' => 500]
                );
            }

            // Update database
            $context_files = apply_filters('datamachine_get_pipeline_context_files', [], $pipeline_id);
            $context_files['uploaded_files'][] = $file_info;
            apply_filters('datamachine_update_pipeline_context_files', null, $pipeline_id, $context_files);

            do_action('datamachine_log', 'debug', 'Pipeline context file uploaded successfully.', [
                'filename' => $file['name'],
                'pipeline_id' => $pipeline_id
            ]);

            $response = rest_ensure_response([
                'success' => true,
                'file' => $file_info,
                'scope' => 'pipeline',
                /* translators: %s: Uploaded file name */
                'message' => sprintf(__('Pipeline context file "%s" uploaded successfully.', 'data-machine'), $file['name']),
            ]);
            $response->set_status(201);
            return $response;
        }

        // FLOW FILES
        if ($flow_step_id) {
            $parts = apply_filters('datamachine_split_flow_step_id', null, $flow_step_id);
            if (!$parts || empty($parts['flow_id'])) {
                return new WP_Error(
                    'invalid_flow_step_id',
                    __('Invalid flow step ID format.', 'data-machine'),
                    ['status' => 400]
                );
            }

            $context = self::get_file_context($parts['flow_id']);

            $stored_path = $repository->store_file($file['tmp_name'], $file['name'], $context);
            if (!$stored_path) {
                return new WP_Error(
                    'files_repository_store_failed',
                    __('Failed to store file.', 'data-machine'),
                    ['status' => 500]
                );
            }

            $file_info = [
                'filename' => basename($stored_path),
                'path' => $stored_path,
                'size' => filesize($stored_path),
                'modified' => filemtime($stored_path)
            ];

            do_action('datamachine_log', 'debug', 'Flow file uploaded successfully.', [
                'filename' => $file['name'],
                'flow_step_id' => $flow_step_id
            ]);

            $response = rest_ensure_response([
                'success' => true,
                'file_info' => $file_info,
                'scope' => 'flow',
                /* translators: %s: Uploaded file name */
                'message' => sprintf(__('File "%s" uploaded successfully.', 'data-machine'), $file['name']),
            ]);
            $response->set_status(201);
            return $response;
        }
    }

    /**
     * List files (flow or pipeline context)
     */
    public static function list_files(WP_REST_Request $request) {
        $flow_step_id = $request->get_param('flow_step_id');
        $pipeline_id = $request->get_param('pipeline_id');

        if (!$flow_step_id && !$pipeline_id) {
            return new WP_Error(
                'missing_scope',
                __('Must provide either flow_step_id or pipeline_id.', 'data-machine'),
                ['status' => 400]
            );
        }

        $repositories = apply_filters('datamachine_files_repository', []);
        $repository = $repositories['files'] ?? null;

        // PIPELINE CONTEXT
        if ($pipeline_id) {
            $context_files = apply_filters('datamachine_get_pipeline_context_files', [], $pipeline_id);

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
                    __('Invalid flow step ID format.', 'data-machine'),
                    ['status' => 400]
                );
            }

            $context = self::get_file_context($parts['flow_id']);
            $files = $repository ? $repository->get_all_files($context) : [];

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
                __('Must provide either flow_step_id or pipeline_id.', 'data-machine'),
                ['status' => 400]
            );
        }

        $repositories = apply_filters('datamachine_files_repository', []);
        $repository = $repositories['files'] ?? null;

        // PIPELINE CONTEXT
        if ($pipeline_id) {
            $context_files = apply_filters('datamachine_get_pipeline_context_files', [], $pipeline_id);
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
            apply_filters('datamachine_update_pipeline_context_files', null, $pipeline_id, $context_files);

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
                    __('Invalid flow step ID format.', 'data-machine'),
                    ['status' => 400]
                );
            }

            $context = self::get_file_context($parts['flow_id']);
            $deleted = $repository ? $repository->delete_file($filename, $context) : false;

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
    private static function get_file_context(int $flow_id): array {
        $flow = apply_filters('datamachine_get_flow_config', [], $flow_id);
        $pipeline_id = $flow['pipeline_id'] ?? 0;
        $pipeline = apply_filters('datamachine_get_pipelines', [], $pipeline_id);

        return [
            'pipeline_id' => $pipeline_id,
            'pipeline_name' => $pipeline['pipeline_name'] ?? "pipeline-{$pipeline_id}",
            'flow_id' => $flow_id,
            'flow_name' => $flow['flow_name'] ?? "flow-{$flow_id}"
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
            throw new \Exception(__('Cannot determine file size.', 'data-machine'));
        }

        $max_file_size = 32 * 1024 * 1024; // 32MB
        if ($file_size > $max_file_size) {
            throw new \Exception(sprintf(
                /* translators: %1$s: Current file size, %2$s: Maximum allowed file size */
                __('File too large: %1$s. Maximum allowed size: %2$s', 'data-machine'),
                size_format($file_size),
                size_format($max_file_size)
            ));
        }

        $dangerous_extensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'pht', 'phar', 'exe', 'bat', 'cmd', 'scr', 'com', 'pif', 'vbs', 'js', 'jar', 'msi', 'dll', 'sh', 'pl', 'py', 'rb', 'asp', 'aspx', 'jsp', 'htaccess'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($file_extension, $dangerous_extensions, true)) {
            throw new \Exception(__('File type not allowed for security reasons.', 'data-machine'));
        }

        if (strpos($file['name'], '..') !== false || strpos($file['name'], '/') !== false || strpos($file['name'], '\\') !== false) {
            throw new \Exception(__('Invalid file name detected.', 'data-machine'));
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
                throw new \Exception(__('File content type not allowed for security reasons.', 'data-machine'));
            }
        }
    }
}
