<?php
/**
 * Universal Files Repository Management
 *
 * Hierarchical file storage system: pipeline → flow → job structure.
 * Supports flow-level files, pipeline-level context, and job data packets.
 *
 * @package DataMachine
 * @subpackage Engine\Filters
 * @since 1.0.0
 */

namespace DataMachine\Engine;

if (!defined('ABSPATH')) {
    exit;
}

// Self-register repository implementation
add_filter('datamachine_files_repository', function($repositories) {
    $repositories['files'] = new FilesRepository();
    return $repositories;
}, 5);

// Universal files repository cleanup integration
add_action('datamachine_cleanup_old_files', function() {
    $repositories = apply_filters('datamachine_files_repository', []);
    $repository = $repositories['files'] ?? null;

    if ($repository) {
        $settings = datamachine_get_data_machine_settings();
        $retention_days = $settings['file_retention_days'] ?? 7;

        $deleted_count = $repository->cleanup_old_files($retention_days);

        do_action('datamachine_log', 'debug', 'FilesRepository: Cleanup completed', [
            'files_deleted' => $deleted_count,
            'retention_days' => $retention_days
        ]);
    }
});

// Schedule cleanup on WordPress init
add_action('init', function() {
    if (datamachine_files_should_schedule_cleanup() && !as_next_scheduled_action('datamachine_cleanup_old_files')) {
        as_schedule_recurring_action(
            time() + WEEK_IN_SECONDS,
            WEEK_IN_SECONDS,
            'datamachine_cleanup_old_files',
            [],
            'data-machine-files'
        );
    }
});

/**
 * Check if cleanup should be scheduled
 *
 * @return bool True if cleanup should be scheduled
 */
function datamachine_files_should_schedule_cleanup(): bool {
    return true;
}

class FilesRepository {

    /**
     * Repository directory name
     */
    private const REPOSITORY_DIR = 'data-machine-files';

    /**
     * Constructor - parameter-less for filter-based architecture
     */
    public function __construct() {
        // No dependencies - all services accessed via filters
    }

    /**
     * Get pipeline directory path
     *
     * @param int $pipeline_id Pipeline ID
     * @param string $pipeline_name Pipeline name
     * @return string Full path to pipeline directory
     */
    public function get_pipeline_directory(int $pipeline_id, string $pipeline_name): string {
        $upload_dir = wp_upload_dir();
        $base = trailingslashit($upload_dir['basedir']) . self::REPOSITORY_DIR;
        $safe_name = $this->sanitize_directory_name($pipeline_name);
        return "{$base}/pipeline-{$pipeline_id}-{$safe_name}";
    }

    /**
     * Get flow directory path
     *
     * @param int $pipeline_id Pipeline ID
     * @param string $pipeline_name Pipeline name
     * @param int $flow_id Flow ID
     * @param string $flow_name Flow name
     * @return string Full path to flow directory
     */
    public function get_flow_directory(int $pipeline_id, string $pipeline_name, int $flow_id, string $flow_name): string {
        $pipeline_dir = $this->get_pipeline_directory($pipeline_id, $pipeline_name);
        $safe_name = $this->sanitize_directory_name($flow_name);
        return "{$pipeline_dir}/flow-{$flow_id}-{$safe_name}";
    }

    /**
     * Get job directory path
     *
     * @param int $pipeline_id Pipeline ID
     * @param string $pipeline_name Pipeline name
     * @param int $flow_id Flow ID
     * @param string $flow_name Flow name
     * @param int $job_id Job ID
     * @return string Full path to job directory
     */
    public function get_job_directory(int $pipeline_id, string $pipeline_name, int $flow_id, string $flow_name, int $job_id): string {
        $flow_dir = $this->get_flow_directory($pipeline_id, $pipeline_name, $flow_id, $flow_name);
        return "{$flow_dir}/jobs/job-{$job_id}";
    }

    /**
     * Get pipeline context directory path
     *
     * @param int $pipeline_id Pipeline ID
     * @param string $pipeline_name Pipeline name
     * @return string Full path to pipeline context directory
     */
    public function get_pipeline_context_directory(int $pipeline_id, string $pipeline_name): string {
        $pipeline_dir = $this->get_pipeline_directory($pipeline_id, $pipeline_name);
        return "{$pipeline_dir}/context";
    }

    /**
     * Get flow files directory path
     *
     * @param int $pipeline_id Pipeline ID
     * @param string $pipeline_name Pipeline name
     * @param int $flow_id Flow ID
     * @param string $flow_name Flow name
     * @return string Full path to flow files directory
     */
    public function get_flow_files_directory(int $pipeline_id, string $pipeline_name, int $flow_id, string $flow_name): string {
        $flow_dir = $this->get_flow_directory($pipeline_id, $pipeline_name, $flow_id, $flow_name);
        return "{$flow_dir}/files";
    }

    /**
     * Sanitize directory name for filesystem
     *
     * @param string $name Original name
     * @return string Sanitized name
     */
    private function sanitize_directory_name(string $name): string {
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9-_]/', '-', $name);
        $name = preg_replace('/-+/', '-', $name);
        $name = trim($name, '-');
        return substr($name, 0, 50);
    }

    /**
     * Ensure directory exists
     *
     * @param string $directory Directory path
     * @return bool True if exists or was created
     */
    public function ensure_directory_exists(string $directory): bool {
        if (!file_exists($directory)) {
            $created = wp_mkdir_p($directory);
            if (!$created) {
                do_action('datamachine_log', 'error', 'FilesRepository: Failed to create directory.', [
                    'path' => $directory
                ]);
                return false;
            }
        }
        return true;
    }

    /**
     * Store file in flow files directory
     *
     * @param string $source_path Source file path
     * @param string $filename Original filename
     * @param array $context Context array with pipeline/flow metadata
     * @return string|false Repository file path on success, false on failure
     */
    public function store_file(string $source_path, string $filename, array $context) {
        $directory = $this->get_flow_files_directory(
            $context['pipeline_id'],
            $context['pipeline_name'],
            $context['flow_id'],
            $context['flow_name']
        );

        if (!$this->ensure_directory_exists($directory)) {
            return false;
        }

        if (!file_exists($source_path)) {
            do_action('datamachine_log', 'error', 'FilesRepository: Source file not found.', [
                'source_path' => $source_path
            ]);
            return false;
        }

        $safe_filename = sanitize_file_name($filename);
        $destination = "{$directory}/{$safe_filename}";

        if (!copy($source_path, $destination)) {
            do_action('datamachine_log', 'error', 'FilesRepository: Failed to copy file.', [
                'source' => $source_path,
                'destination' => $destination
            ]);
            return false;
        }

        return $destination;
    }

    /**
     * Store pipeline context file
     *
     * @param int $pipeline_id Pipeline ID
     * @param string $pipeline_name Pipeline name
     * @param array $file_data File data array (source_path, original_name)
     * @return array|false File information on success, false on failure
     */
    public function store_pipeline_file(int $pipeline_id, string $pipeline_name, array $file_data) {
        $directory = $this->get_pipeline_context_directory($pipeline_id, $pipeline_name);

        if (!$this->ensure_directory_exists($directory)) {
            return false;
        }

        $source_path = $file_data['source_path'] ?? '';
        $filename = $file_data['original_name'] ?? '';

        if (empty($source_path) || empty($filename)) {
            do_action('datamachine_log', 'error', 'FilesRepository: Missing required parameters for pipeline context.', [
                'pipeline_id' => $pipeline_id
            ]);
            return false;
        }

        if (!file_exists($source_path)) {
            do_action('datamachine_log', 'error', 'FilesRepository: Source file not found.', [
                'source_path' => $source_path
            ]);
            return false;
        }

        $safe_filename = sanitize_file_name($filename);
        $destination = "{$directory}/{$safe_filename}";

        if (!copy($source_path, $destination)) {
            do_action('datamachine_log', 'error', 'FilesRepository: Failed to store pipeline context file.', [
                'source' => $source_path,
                'destination' => $destination
            ]);
            return false;
        }

        return [
            'original_name' => $filename,
            'persistent_path' => $destination,
            'size' => filesize($destination),
            'mime_type' => mime_content_type($destination),
            'uploaded_at' => current_time('mysql')
        ];
    }

    /**
     * Store remote file in flow files directory
     *
     * @param string $url Remote file URL
     * @param string $filename Desired filename
     * @param array $context Context array with pipeline/flow metadata
     * @param array $options Additional options (timeout, user_agent)
     * @return array|null File information on success, null on failure
     */
    public function store_remote_file(string $url, string $filename, array $context, array $options = []): ?array {
        if (empty($url) || empty($filename)) {
            do_action('datamachine_log', 'error', 'FilesRepository: Missing required parameters for remote file.', [
                'has_url' => !empty($url),
                'has_filename' => !empty($filename)
            ]);
            return null;
        }

        $timeout = $options['timeout'] ?? 30;
        $user_agent = $options['user_agent'] ?? 'php:DataMachineWPPlugin:v' . DATA_MACHINE_VERSION . ' (WordPress File Repository)';

        try {
            $response = wp_remote_get($url, [
                'timeout' => $timeout,
                'user-agent' => $user_agent
            ]);

            if (is_wp_error($response)) {
                do_action('datamachine_log', 'error', 'FilesRepository: Failed to download remote file.', [
                    'url' => $url,
                    'error' => $response->get_error_message()
                ]);
                return null;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                do_action('datamachine_log', 'error', 'FilesRepository: HTTP error downloading remote file.', [
                    'url' => $url,
                    'response_code' => $response_code
                ]);
                return null;
            }

            $file_data = wp_remote_retrieve_body($response);
            if (empty($file_data)) {
                do_action('datamachine_log', 'error', 'FilesRepository: Downloaded file is empty.', [
                    'url' => $url
                ]);
                return null;
            }

            $directory = $this->get_flow_files_directory(
                $context['pipeline_id'],
                $context['pipeline_name'],
                $context['flow_id'],
                $context['flow_name']
            );

            if (!$this->ensure_directory_exists($directory)) {
                return null;
            }

            $safe_filename = sanitize_file_name($filename);
            $destination = "{$directory}/{$safe_filename}";

            $written = file_put_contents($destination, $file_data);
            if ($written === false) {
                do_action('datamachine_log', 'error', 'FilesRepository: Failed to write remote file.', [
                    'url' => $url,
                    'destination' => $destination
                ]);
                return null;
            }

            $upload_dir = wp_upload_dir();
            $base_url = trailingslashit($upload_dir['baseurl']) . self::REPOSITORY_DIR;
            $relative_path = str_replace(
                trailingslashit($upload_dir['basedir']) . self::REPOSITORY_DIR,
                '',
                $destination
            );
            $file_url = $base_url . $relative_path;

            return [
                'path' => $destination,
                'filename' => $safe_filename,
                'size' => filesize($destination),
                'url' => $file_url,
                'source_url' => $url
            ];

        } catch (\Exception $e) {
            do_action('datamachine_log', 'error', 'FilesRepository: Exception downloading remote file.', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Store data packet as JSON file
     *
     * @param array $data Data packet array
     * @param int $job_id Job ID
     * @param array $context Context array with pipeline/flow metadata
     * @return array Reference object on success, false on failure
     */
    public function store_data_packet(array $data, int $job_id, array $context) {
        $directory = $this->get_job_directory(
            $context['pipeline_id'],
            $context['pipeline_name'],
            $context['flow_id'],
            $context['flow_name'],
            $job_id
        );

        if (!$this->ensure_directory_exists($directory)) {
            do_action('datamachine_log', 'error', 'FilesRepository: Failed to create job directory.', [
                'job_id' => $job_id
            ]);
            return false;
        }

        $file_path = "{$directory}/data.json";

        // Load existing data for accumulation
        $accumulated_data = [];
        if (file_exists($file_path)) {
            $existing_json = file_get_contents($file_path);
            if ($existing_json !== false) {
                $accumulated_data = json_decode($existing_json, true);
                if (!is_array($accumulated_data)) {
                    $accumulated_data = [];
                }
            }
        }

        // Merge new data (newest first)
        if (!empty($data)) {
            $accumulated_data = array_merge($data, $accumulated_data);
        }

        // Serialize and write
        $json_data = wp_json_encode($accumulated_data, JSON_UNESCAPED_UNICODE);
        if ($json_data === false) {
            do_action('datamachine_log', 'error', 'FilesRepository: Failed to encode data packet.', [
                'job_id' => $job_id
            ]);
            return false;
        }

        if (file_put_contents($file_path, $json_data) === false) {
            do_action('datamachine_log', 'error', 'FilesRepository: Failed to write data packet.', [
                'job_id' => $job_id,
                'file_path' => $file_path
            ]);
            return false;
        }

        return [
            'is_data_reference' => true,
            'job_id' => $job_id,
            'file_path' => $file_path,
            'stored_at' => time()
        ];
    }

    /**
     * Retrieve data packet from JSON file
     *
     * @param array $reference Reference object from store_data_packet()
     * @return array|null Retrieved data or null on failure
     */
    public function retrieve_data_packet(array $reference): ?array {
        if (!isset($reference['is_data_reference']) || !$reference['is_data_reference']) {
            return $reference['data'] ?? $reference;
        }

        $file_path = $reference['file_path'] ?? '';

        if (empty($file_path) || !file_exists($file_path)) {
            do_action('datamachine_log', 'error', 'FilesRepository: Data packet file not found.', [
                'file_path' => $file_path
            ]);
            return null;
        }

        $json_data = file_get_contents($file_path);
        if ($json_data === false) {
            do_action('datamachine_log', 'error', 'FilesRepository: Failed to read data packet.', [
                'file_path' => $file_path
            ]);
            return null;
        }

        $data = json_decode($json_data, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            do_action('datamachine_log', 'error', 'FilesRepository: Failed to decode data packet.', [
                'json_error' => json_last_error_msg()
            ]);
            return null;
        }

        return $data;
    }

    /**
     * Get all files in flow files directory
     *
     * @param array $context Context array with pipeline/flow metadata
     * @return array Array of file information
     */
    public function get_all_files(array $context): array {
        $directory = $this->get_flow_files_directory(
            $context['pipeline_id'],
            $context['pipeline_name'],
            $context['flow_id'],
            $context['flow_name']
        );

        if (!is_dir($directory)) {
            return [];
        }

        $files = glob("{$directory}/*");
        $file_list = [];

        foreach ($files as $file_path) {
            if (is_file($file_path)) {
                $filename = basename($file_path);

                if ($filename === 'index.php') {
                    continue;
                }

                $file_list[] = [
                    'filename' => $filename,
                    'path' => $file_path,
                    'size' => filesize($file_path),
                    'modified' => filemtime($file_path)
                ];
            }
        }

        return $file_list;
    }

    /**
     * Delete file from flow files directory
     *
     * @param string $filename Filename to delete
     * @param array $context Context array with pipeline/flow metadata
     * @return bool True on success, false on failure
     */
    public function delete_file(string $filename, array $context): bool {
        $directory = $this->get_flow_files_directory(
            $context['pipeline_id'],
            $context['pipeline_name'],
            $context['flow_id'],
            $context['flow_name']
        );

        $safe_filename = sanitize_file_name($filename);
        $file_path = "{$directory}/{$safe_filename}";

        if (!file_exists($file_path)) {
            return false;
        }

        return wp_delete_file($file_path);
    }

    /**
     * Clean up job data packets for a specific job
     *
     * @param int $job_id Job ID
     * @param array $context Context array with pipeline/flow metadata
     * @return int Number of directories deleted (0 or 1)
     */
    public function cleanup_job_data_packets(int $job_id, array $context): int {
        $job_dir = $this->get_job_directory(
            $context['pipeline_id'],
            $context['pipeline_name'],
            $context['flow_id'],
            $context['flow_name'],
            $job_id
        );

        if (!is_dir($job_dir)) {
            return 0;
        }

        if (!function_exists('WP_Filesystem')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        if (WP_Filesystem()) {
            global $wp_filesystem;
            if ($wp_filesystem->rmdir($job_dir, true)) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * Clean up old files (hierarchical traversal)
     *
     * @param int $retention_days Files older than this many days will be deleted
     * @return int Number of files deleted
     */
    public function cleanup_old_files(int $retention_days = 7): int {
        $upload_dir = wp_upload_dir();
        $base = trailingslashit($upload_dir['basedir']) . self::REPOSITORY_DIR;
        $cutoff_time = time() - ($retention_days * DAY_IN_SECONDS);
        $deleted_count = 0;

        if (!is_dir($base)) {
            return 0;
        }

        // Traverse: pipeline → flow → files
        $pipeline_dirs = glob("{$base}/pipeline-*", GLOB_ONLYDIR);

        foreach ($pipeline_dirs as $pipeline_dir) {
            $flow_dirs = glob("{$pipeline_dir}/flow-*", GLOB_ONLYDIR);

            foreach ($flow_dirs as $flow_dir) {
                // Clean up flow files (not context!)
                $files_dir = "{$flow_dir}/files";

                if (is_dir($files_dir)) {
                    $files = glob("{$files_dir}/*");
                    foreach ($files as $file) {
                        if (is_file($file) && filemtime($file) < $cutoff_time) {
                            if (wp_delete_file($file)) {
                                $deleted_count++;
                            }
                        }
                    }

                    // Remove empty files directory
                    if (empty(glob("{$files_dir}/*"))) {
                        rmdir($files_dir);
                    }
                }

                // Clean up old job directories
                $jobs_dir = "{$flow_dir}/jobs";

                if (is_dir($jobs_dir)) {
                    $job_dirs = glob("{$jobs_dir}/job-*", GLOB_ONLYDIR);
                    foreach ($job_dirs as $job_dir) {
                        $files = glob("{$job_dir}/*");
                        $all_old = true;

                        foreach ($files as $file) {
                            if (is_file($file) && filemtime($file) >= $cutoff_time) {
                                $all_old = false;
                                break;
                            }
                        }

                        if ($all_old && !empty($files)) {
                            if (!function_exists('WP_Filesystem')) {
                                require_once(ABSPATH . 'wp-admin/includes/file.php');
                            }
                            if (WP_Filesystem()) {
                                global $wp_filesystem;
                                $wp_filesystem->rmdir($job_dir, true);
                            }
                        }
                    }

                    // Remove empty jobs directory
                    if (empty(glob("{$jobs_dir}/*"))) {
                        rmdir($jobs_dir);
                    }
                }
            }
        }

        return $deleted_count;
    }

    /**
     * Check if argument is a data reference
     *
     * @param mixed $argument Argument to check
     * @return bool True if data reference
     */
    public function is_data_reference($argument): bool {
        return is_array($argument) &&
               isset($argument['is_data_reference']) &&
               $argument['is_data_reference'] === true;
    }
}
