<?php
/**
 * File Repository Management
 *
 * Simple file repository system for handling uploaded files.
 * Focuses solely on file storage, retrieval, and cleanup operations.
 * Does not care where files come from - just manages the repository.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/handlers/input/files
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Input\Files;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

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
                $logger = apply_filters('dm_get_logger', null);
                $logger?->error('FilesRepository: Failed to create repository directory.', [
                    'path' => $repo_path
                ]);
                return false;
            }
        }

        // Add .htaccess for security
        $this->create_htaccess_protection();
        
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
            $logger = apply_filters('dm_get_logger', null);
            $logger?->error('FilesRepository: Source file not found.', [
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
            $logger = apply_filters('dm_get_logger', null);
            $logger?->error('FilesRepository: Failed to copy file to repository.', [
                'source' => $source_path,
                'destination' => $destination_path
            ]);
            return false;
        }

        $logger = apply_filters('dm_get_logger', null);
        $logger?->debug('FilesRepository: File stored successfully.', [
            'filename' => $safe_filename,
            'size' => filesize($destination_path)
        ]);

        return $destination_path;
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
                if (in_array($filename, ['.htaccess', 'index.php'])) {
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
        if ($deleted) {
            $logger = apply_filters('dm_get_logger', null);
            $logger?->debug('FilesRepository: File deleted successfully.', [
                'filename' => $safe_filename
            ]);
        }

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

        if ($deleted_count > 0) {
            $logger = apply_filters('dm_get_logger', null);
            $logger?->debug('FilesRepository: Cleanup completed.', [
                'deleted_count' => $deleted_count,
                'cutoff_days' => $days
            ]);
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

    /**
     * Create .htaccess file for security
     */
    private function create_htaccess_protection(): void {
        $htaccess_path = $this->get_repository_path() . '/.htaccess';
        
        if (!file_exists($htaccess_path)) {
            $htaccess_content = "# Data Machine Files Repository - Security Protection\n";
            $htaccess_content .= "# Prevent direct access to files\n";
            $htaccess_content .= "Options -Indexes\n";
            $htaccess_content .= "<Files ~ \"\\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)$\">\n";
            $htaccess_content .= "    deny from all\n";
            $htaccess_content .= "</Files>\n";
            
            file_put_contents($htaccess_path, $htaccess_content);
        }
    }

}