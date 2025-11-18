<?php
/**
 * Directory path management for hierarchical file storage.
 *
 * Provides pipeline → flow → job directory structure with WordPress-native
 * path operations. All paths use wp_upload_dir() as base with organized
 * subdirectory hierarchy.
 *
 * @package DataMachine\Core\FilesRepository
 * @since 0.2.1
 */

namespace DataMachine\Core\FilesRepository;

if (!defined('ABSPATH')) {
    exit;
}

class DirectoryManager {

    /**
     * Repository directory name
     */
    private const REPOSITORY_DIR = 'datamachine-files';

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
     * Get flow files directory path
     *
     * @param int $pipeline_id Pipeline ID
     * @param string $pipeline_name Pipeline name
     * @param int|string $flow_id Flow ID or 'ephemeral' sentinel
     * @param string $flow_name Flow name
     * @return string Full path to flow files directory
     */
    public function get_flow_files_directory(int $pipeline_id, string $pipeline_name, int|string $flow_id, string $flow_name): string {
        // Handle ephemeral workflows with temp directory
        if ($pipeline_id === 0 || $flow_id === 0) {
            $temp_dir = sys_get_temp_dir();
            $ephemeral_id = uniqid('datamachine-ephemeral-', true);
            return "{$temp_dir}/{$ephemeral_id}/files";
        }

        $flow_dir = $this->get_flow_directory($pipeline_id, $pipeline_name, $flow_id, $flow_name);
        return "{$flow_dir}/files";
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
                do_action('datamachine_log', 'error', 'DirectoryManager: Failed to create directory.', [
                    'path' => $directory
                ]);
                return false;
            }
        }
        return true;
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
}
