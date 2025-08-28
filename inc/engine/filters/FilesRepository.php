<?php
/**
 * Universal Files Repository Management
 *
 * Central file repository system for handling file storage, retrieval, and cleanup operations.
 * Supports both regular file storage and data packet storage for Action Scheduler integration.
 * Focuses solely on file management - does not care where files come from.
 *
 * @package DataMachine
 * @subpackage Engine\Filters
 * @since 1.0.0
 */

namespace DataMachine\Engine;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Self-register repository implementation and cleanup integration
add_filter('dm_files_repository', function($repositories) {
    $repositories['files'] = new FilesRepository();
    return $repositories;
}, 5);

// Universal files repository cleanup integration
add_action('dm_cleanup_old_files', function() {
    $repositories = apply_filters('dm_files_repository', []);
    $repository = $repositories['files'] ?? null;
    if ($repository) {
        // Clean up regular files older than 7 days
        $deleted_count = $repository->cleanup_old_files(7);
        
        // Clean up old job data packets
        $job_data_deleted = 0;
        $cutoff_time = time() - (7 * DAY_IN_SECONDS);
        
        // Get all job_* directories
        $upload_dir = wp_upload_dir();
        $base_path = trailingslashit($upload_dir['basedir']) . 'dm-files';
        
        if (is_dir($base_path)) {
            $job_directories = glob($base_path . '/job_*', GLOB_ONLYDIR);
            
            foreach ($job_directories as $job_dir) {
                $job_namespace = basename($job_dir);
                $files = $repository->get_all_files($job_namespace);
                
                foreach ($files as $file) {
                    if (str_starts_with($file['filename'], 'data_packet_') && $file['modified'] < $cutoff_time) {
                        if ($repository->delete_file($file['filename'], $job_namespace)) {
                            $job_data_deleted++;
                        }
                    }
                }
                
                // Remove empty job directories
                if (empty($repository->get_all_files($job_namespace))) {
                    $job_dir_path = $repository->get_repository_path($job_namespace);
                    if (is_dir($job_dir_path)) {
                        rmdir($job_dir_path);
                    }
                }
            }
        }
        
    }
});

class FilesRepository {

    /**
     * Repository directory path
     */
    private const REPOSITORY_DIR = 'dm-files';

    /**
     * Constructor - parameter-less for filter-based architecture
     */
    public function __construct() {
        // No dependencies - all services accessed via filters
    }

    /**
     * Get the repository directory path
     *
     * @param string|null $flow_step_id Flow step ID for isolation (step_id_flow_id format)
     * @return string Full path to repository directory
     */
    public function get_repository_path(?string $flow_step_id = null): string {
        $upload_dir = wp_upload_dir();
        $base_path = trailingslashit($upload_dir['basedir']) . self::REPOSITORY_DIR;
        
        // If flow_step_id provided, create isolated subdirectory
        if ($flow_step_id) {
            $safe_flow_step_id = sanitize_file_name($flow_step_id);
            return trailingslashit($base_path) . $safe_flow_step_id;
        }
        
        return $base_path;
    }

    /**
     * Get the repository URL
     *
     * @param string|null $flow_step_id Flow step ID for isolation (step_id_flow_id format)
     * @return string URL to repository directory
     */
    public function get_repository_url(?string $flow_step_id = null): string {
        $upload_dir = wp_upload_dir();
        $base_url = trailingslashit($upload_dir['baseurl']) . self::REPOSITORY_DIR;
        
        // If flow_step_id provided, create isolated subdirectory URL
        if ($flow_step_id) {
            $safe_flow_step_id = sanitize_file_name($flow_step_id);
            return trailingslashit($base_url) . $safe_flow_step_id;
        }
        
        return $base_url;
    }

    /**
     * Ensure repository directory exists
     *
     * @param string|null $flow_step_id Flow step ID for isolation
     * @return bool True if directory exists or was created
     */
    public function ensure_repository_exists(?string $flow_step_id = null): bool {
        $repo_path = $this->get_repository_path($flow_step_id);
        
        if (!file_exists($repo_path)) {
            $created = wp_mkdir_p($repo_path);
            if (!$created) {
                do_action('dm_log', 'error', 'FilesRepository: Failed to create repository directory.', [
                    'path' => $repo_path
                ]);
                return false;
            }
        }

        
        return true;
    }

    /**
     * Store a file in the repository
     *
     * @param string $source_path Path to source file
     * @param string $filename Original filename to use
     * @param string|null $flow_step_id Flow step ID for isolation
     * @return string|false Repository file path on success, false on failure
     */
    public function store_file(string $source_path, string $filename, ?string $flow_step_id = null) {
        if (!$this->ensure_repository_exists($flow_step_id)) {
            return false;
        }

        if (!file_exists($source_path)) {
            do_action('dm_log', 'error', 'FilesRepository: Source file not found.', [
                'source_path' => $source_path
            ]);
            return false;
        }

        // Sanitize filename for security
        $safe_filename = $this->sanitize_filename($filename);
        $destination_path = $this->get_repository_path($flow_step_id) . '/' . $safe_filename;

        // Copy file to repository
        $copied = copy($source_path, $destination_path);
        if (!$copied) {
            do_action('dm_log', 'error', 'FilesRepository: Failed to copy file to repository.', [
                'source' => $source_path,
                'destination' => $destination_path
            ]);
            return false;
        }


        return $destination_path;
    }

    /**
     * Store data packet as JSON file and return reference
     *
     * @param array $data Data packet array to store
     * @param int $job_id Job ID for namespace isolation
     * @param string $flow_step_id Flow step ID for additional context
     * @return array Reference object containing storage keys
     */
    public function store_data_packet(array $data, int $job_id, string $flow_step_id): array {
        // Create job-specific storage namespace
        $storage_namespace = "job_{$job_id}";
        
        // Single accumulating filename per job (like claude.json pattern)
        $filename = "job_{$job_id}_data.json";
        
        // Get existing file path to check if data packet already exists
        $existing_file_path = $this->get_repository_path($storage_namespace) . '/' . $filename;
        $accumulated_data = [];
        $file_existed_before = file_exists($existing_file_path);
        
        // Load existing data if file exists (accumulating pattern)
        if ($file_existed_before) {
            $existing_json = file_get_contents($existing_file_path);
            if ($existing_json !== false) {
                $accumulated_data = json_decode($existing_json, true);
                if (!is_array($accumulated_data)) {
                    $accumulated_data = []; // Reset if corrupted
                }
                
            }
        }
        
        // Merge new data with existing accumulated data (newest first with array_unshift pattern)
        if (!empty($data)) {
            // Step produced data entries - merge with existing accumulated data  
            $accumulated_data = array_merge($data, $accumulated_data);
        }
        
        // Serialize accumulated data to JSON
        $json_data = wp_json_encode($accumulated_data, JSON_UNESCAPED_UNICODE);
        if ($json_data === false) {
            do_action('dm_log', 'error', 'FilesRepository: Failed to encode accumulated data as JSON', [
                'job_id' => $job_id,
                'flow_step_id' => $flow_step_id,
                'data_entries' => count($accumulated_data)
            ]);
            return ['data' => $accumulated_data]; // Fallback to direct data passing
        }

        // Create temporary file and store in repository
        $temp_file = wp_tempnam($filename);
        if (file_put_contents($temp_file, $json_data) === false) {
            do_action('dm_log', 'error', 'FilesRepository: Failed to write temporary file', [
                'job_id' => $job_id,
                'temp_file' => $temp_file
            ]);
            return ['data' => $accumulated_data]; // Fallback to direct data passing
        }

        // Store in repository with job namespace (will overwrite existing file)
        $stored_path = $this->store_file($temp_file, $filename, $storage_namespace);
        
        // Clean up temporary file
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }

        if (!$stored_path) {
            do_action('dm_log', 'error', 'FilesRepository: Failed to store accumulated data packet in repository', [
                'job_id' => $job_id,
                'filename' => $filename
            ]);
            return ['data' => $accumulated_data]; // Fallback to direct data passing
        }


        // Return lightweight reference object (always points to same file per job)
        return [
            'is_data_reference' => true,
            'job_id' => $job_id,
            'storage_namespace' => $storage_namespace,
            'filename' => $filename,
            'flow_step_id' => $flow_step_id,
            'stored_at' => time()
        ];
    }

    /**
     * Retrieve data packet from JSON file using reference
     *
     * @param array $reference Reference object from store_data_packet()
     * @return array|null Retrieved data packet or null on failure
     */
    public function retrieve_data_packet(array $reference): ?array {
        // Check if this is actually a reference object
        if (!isset($reference['is_data_reference']) || !$reference['is_data_reference']) {
            // This is direct data, return as-is
            return $reference['data'] ?? $reference;
        }

        // Extract reference information
        $job_id = $reference['job_id'] ?? 0;
        $storage_namespace = $reference['storage_namespace'] ?? '';
        $filename = $reference['filename'] ?? '';

        if (empty($storage_namespace) || empty($filename)) {
            do_action('dm_log', 'error', 'FilesRepository: Invalid data packet reference', [
                'reference' => $reference
            ]);
            return null;
        }

        // Get file path
        $file_path = $this->get_repository_path($storage_namespace) . '/' . $filename;
        
        if (!file_exists($file_path)) {
            do_action('dm_log', 'error', 'FilesRepository: Data packet file not found', [
                'job_id' => $job_id,
                'file_path' => $file_path
            ]);
            return null;
        }

        // Read and decode JSON data
        $json_data = file_get_contents($file_path);
        if ($json_data === false) {
            do_action('dm_log', 'error', 'FilesRepository: Failed to read data packet file', [
                'job_id' => $job_id,
                'file_path' => $file_path
            ]);
            return null;
        }

        $data = json_decode($json_data, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            do_action('dm_log', 'error', 'FilesRepository: Failed to decode JSON data packet', [
                'job_id' => $job_id,
                'json_error' => json_last_error_msg()
            ]);
            return null;
        }


        return $data;
    }

    /**
     * Clean up stored data packets for a specific job
     *
     * @param int $job_id Job ID to clean up
     * @return int Number of files deleted
     */
    public function cleanup_job_data_packets(int $job_id): int {
        $storage_namespace = "job_{$job_id}";
        $deleted_count = 0;

        // Single file per job pattern - delete the specific job data file
        $filename = "job_{$job_id}_data.json";
        
        // Check if the job data file exists and delete it
        $file_path = $this->get_repository_path($storage_namespace) . '/' . $filename;
        if (file_exists($file_path)) {
            if ($this->delete_file($filename, $storage_namespace)) {
                $deleted_count++;
            }
        }
        
        // Also clean up any legacy data_packet_ files (backward compatibility)
        $files = $this->get_all_files($storage_namespace);
        foreach ($files as $file) {
            if (str_starts_with($file['filename'], 'data_packet_')) {
                if ($this->delete_file($file['filename'], $storage_namespace)) {
                    $deleted_count++;
                }
            }
        }


        return $deleted_count;
    }

    /**
     * Check if argument is a data reference or actual data
     *
     * @param mixed $argument Argument to check
     * @return bool True if it's a data reference, false if it's actual data
     */
    public function is_data_reference($argument): bool {
        return is_array($argument) && 
               isset($argument['is_data_reference']) && 
               $argument['is_data_reference'] === true;
    }

    /**
     * Get all files in the repository
     *
     * @param string|null $flow_step_id Flow step ID for isolation
     * @return array Array of file information
     */
    public function get_all_files(?string $flow_step_id = null): array {
        if (!$this->ensure_repository_exists($flow_step_id)) {
            return [];
        }

        $repo_path = $this->get_repository_path($flow_step_id);
        $files = glob($repo_path . '/*');
        $file_list = [];

        foreach ($files as $file_path) {
            if (is_file($file_path)) {
                $filename = basename($file_path);
                
                // Skip system files
                if ($filename === 'index.php') {
                    continue;
                }

                $file_list[] = [
                    'filename' => $filename,
                    'path' => $file_path,
                    'size' => filesize($file_path),
                    'modified' => filemtime($file_path),
                    'url' => $this->get_repository_url($flow_step_id) . '/' . $filename
                ];
            }
        }

        return $file_list;
    }

    /**
     * Get file information by filename
     *
     * @param string $filename Filename to look for
     * @return array|null File information or null if not found
     */
    public function get_file_info(string $filename): ?array {
        $safe_filename = $this->sanitize_filename($filename);
        $file_path = $this->get_repository_path() . '/' . $safe_filename;

        if (!file_exists($file_path)) {
            return null;
        }

        return [
            'filename' => $safe_filename,
            'path' => $file_path,
            'size' => filesize($file_path),
            'modified' => filemtime($file_path),
            'url' => $this->get_repository_url() . '/' . $safe_filename
        ];
    }

    /**
     * Delete a file from the repository
     *
     * @param string $filename Filename to delete
     * @param string|null $flow_step_id Flow step ID for isolation
     * @return bool True on success, false on failure
     */
    public function delete_file(string $filename, ?string $flow_step_id = null): bool {
        $safe_filename = $this->sanitize_filename($filename);
        $file_path = $this->get_repository_path($flow_step_id) . '/' . $safe_filename;

        if (!file_exists($file_path)) {
            return false;
        }

        $deleted = unlink($file_path);

        return $deleted;
    }

    /**
     * Clean up old files (older than specified days)
     *
     * @param int $days Files older than this many days will be deleted
     * @param string|null $flow_step_id Flow step ID for isolation
     * @return int Number of files deleted
     */
    public function cleanup_old_files(int $days = 7, ?string $flow_step_id = null): int {
        if (!$this->ensure_repository_exists($flow_step_id)) {
            return 0;
        }

        $cutoff_time = time() - ($days * DAY_IN_SECONDS);
        $files = $this->get_all_files($flow_step_id);
        $deleted_count = 0;

        foreach ($files as $file) {
            if ($file['modified'] < $cutoff_time) {
                if ($this->delete_file($file['filename'], $flow_step_id)) {
                    $deleted_count++;
                }
            }
        }


        return $deleted_count;
    }

    /**
     * Get repository statistics
     *
     * @param string|null $flow_step_id Flow step ID for isolation
     * @return array Repository statistics
     */
    public function get_repository_stats(?string $flow_step_id = null): array {
        $files = $this->get_all_files($flow_step_id);
        $total_size = 0;
        $oldest_file = null;
        $newest_file = null;

        foreach ($files as $file) {
            $total_size += $file['size'];
            
            if ($oldest_file === null || $file['modified'] < $oldest_file) {
                $oldest_file = $file['modified'];
            }
            
            if ($newest_file === null || $file['modified'] > $newest_file) {
                $newest_file = $file['modified'];
            }
        }

        return [
            'file_count' => count($files),
            'total_size' => $total_size,
            'total_size_formatted' => size_format($total_size),
            'oldest_file' => $oldest_file,
            'newest_file' => $newest_file
        ];
    }

    /**
     * Sanitize filename for security
     *
     * @param string $filename Original filename
     * @return string Sanitized filename
     */
    private function sanitize_filename(string $filename): string {
        // Use WordPress sanitize_file_name function
        return sanitize_file_name($filename);
    }

}