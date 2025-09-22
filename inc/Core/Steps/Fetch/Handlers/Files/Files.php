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

	public function __construct() {
	}


    /**
     * Get repository instance via filter discovery
     */
	private function get_repository(): ?\DataMachine\Engine\FilesRepository {
		$repositories = apply_filters('dm_files_repository', []);
		return $repositories['files'] ?? null;
	}

	/**
	 * Fetch file data with clean content for AI processing.
	 * Returns processed items while storing engine data (image_url for images) in database.
	 *
	 * @param int $pipeline_id Pipeline ID for logging context.
	 * @param array $handler_config Handler configuration including flow_step_id and file settings.
	 * @param string|null $job_id Job ID for deduplication tracking.
	 * @return array Array with 'processed_items' containing clean data for AI processing.
	 *               Engine parameters (image_url for images) are stored in database via store_engine_data().
	 */
	public function get_fetch_data(int $pipeline_id, array $handler_config, ?string $job_id = null): array {
        $repository = $this->get_repository();
        $flow_step_id = $handler_config['flow_step_id'] ?? null;
        $config = $handler_config['files'] ?? [];
        $uploaded_files = $config['uploaded_files'] ?? [];

        if (empty($uploaded_files)) {
            $repo_files = $repository->get_all_files($flow_step_id);
            if (empty($repo_files)) {
                do_action('dm_log', 'debug', 'Files Input: No files available in repository.', [
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
            do_action('dm_log', 'debug', 'Files Input: No unprocessed files available.', ['pipeline_id' => $pipeline_id]);
            return ['processed_items' => []];
        }

        if (!file_exists($next_file['persistent_path'])) {
            do_action('dm_log', 'error', 'Files Input: File not found.', ['pipeline_id' => $pipeline_id, 'file_path' => $next_file['persistent_path']]);
            return ['processed_items' => []];
        }

        $file_identifier = $next_file['persistent_path'];
        $mime_type = $next_file['mime_type'] ?? 'application/octet-stream';

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

        // Generate public URL for image files (for WordPress featured images, etc.)
        $file_url = '';
        if (strpos($mime_type, 'image/') === 0) {
            // For image files, provide public URL for publish handlers
            $repositories = apply_filters('dm_files_repository', []);
            $file_repository = $repositories['files'] ?? null;
            if ($file_repository && $flow_step_id) {
                $file_url = trailingslashit($file_repository->get_repository_url($flow_step_id)) . $next_file['original_name'];
            }
        }

        // Store URLs in engine_data for centralized access via dm_engine_data filter
        if ($job_id) {
            $engine_data = [
                'source_url' => '', // No source URL for local files
                'image_url' => $file_url // Public URL for images, empty for non-images
            ];

            // Store engine_data via database service
            $all_databases = apply_filters('dm_db', []);
            $db_jobs = $all_databases['jobs'] ?? null;
            if ($db_jobs) {
                $db_jobs->store_engine_data($job_id, $engine_data);
                do_action('dm_log', 'debug', 'Files: Stored URLs in engine_data', [
                    'job_id' => $job_id,
                    'has_image_url' => !empty($engine_data['image_url']),
                    'file_name' => $next_file['original_name']
                ]);
            }
        }

        do_action('dm_log', 'debug', 'Files Input: Found unprocessed file for processing.', [
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
            
            $is_processed = apply_filters('dm_is_item_processed', false, $flow_step_id, 'files', $file_identifier);
            
            do_action('dm_log', 'debug', 'Files Input: Checking file processed status', [
                'flow_step_id' => $flow_step_id,
                'file_identifier' => basename($file_identifier),
                'is_processed' => $is_processed
            ]);
            
            if (!$is_processed) {
                do_action('dm_mark_item_processed', $flow_step_id, 'files', $file_identifier, $job_id);
                return $file;
            }
        }

        return null;
    }


    private function get_mime_type_from_file(string $file_path): string {
        $file_info = wp_check_filetype($file_path);
        return $file_info['type'] ?? 'application/octet-stream';
    }
    
    public function sanitize_settings(array $raw_settings): array {
        return [];
    }

    public static function get_label(): string {
        return __('File Upload', 'data-machine');
    }

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
     * Security validation blocking dangerous executable files.
     */
    private function validate_file_basic(string $file_path, string $filename): bool {
        $dangerous_extensions = ['php', 'exe', 'bat', 'cmd', 'scr', 'com', 'pif', 'vbs', 'js'];
        $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($file_extension, $dangerous_extensions)) {
            do_action('dm_log', 'error', 'Files Input: File type not allowed for security reasons.', ['file_extension' => $file_extension]);
            return false;
        }

        if (!file_exists($file_path) || !is_readable($file_path)) {
            do_action('dm_log', 'error', 'Files Input: File is not accessible.', ['file_path' => $file_path]);
            return false;
        }

        return true;
    }
}

