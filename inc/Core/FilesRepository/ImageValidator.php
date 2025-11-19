<?php
/**
 * Image validation utilities for Data Machine.
 *
 * Validates images from repository files (not URLs) to ensure they can be
 * processed by publish handlers. Centralizes image validation logic.
 *
 * @package DataMachine\Core\FilesRepository
 * @since 0.2.1
 */

namespace DataMachine\Core\FilesRepository;

if (!defined('ABSPATH')) {
    exit;
}

class ImageValidator {

    /**
     * Supported image MIME types
     */
    private const SUPPORTED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ];

    /**
     * Maximum file size (uses WordPress upload limit)
     */
    private const MAX_FILE_SIZE = null; // Will be set dynamically

    /**
     * Validate repository image file
     *
     * @param string $file_path Path to image file in repository
     * @return array Validation result with 'valid', 'mime_type', 'size', 'errors'
     */
    public function validate_repository_file(string $file_path): array {
        $result = [
            'valid' => false,
            'mime_type' => null,
            'size' => 0,
            'errors' => []
        ];

        if (!file_exists($file_path)) {
            $result['errors'][] = 'Image file not found in repository';
            return $result;
        }

        if (!is_readable($file_path)) {
            $result['errors'][] = 'Image file not readable';
            return $result;
        }

        // Check file size
        $file_size = filesize($file_path);
        $max_file_size = wp_max_upload_size();
        if ($file_size > $max_file_size) {
            $result['errors'][] = 'Image file too large';
            return $result;
        }

        // Check MIME type
        $mime_type = $this->get_file_mime_type($file_path);
        if (!$mime_type) {
            $result['errors'][] = 'Could not determine MIME type';
            return $result;
        }

        if (!in_array($mime_type, self::SUPPORTED_MIME_TYPES)) {
            $result['errors'][] = 'Unsupported image format';
            return $result;
        }

        // Success
        $result['valid'] = true;
        $result['mime_type'] = $mime_type;
        $result['size'] = $file_size;

        return $result;
    }

    /**
     * Get MIME type of file using multiple methods
     *
     * @param string $file_path File path
     * @return string|null MIME type or null on failure
     */
    private function get_file_mime_type(string $file_path): ?string {
        // Method 1: finfo (most reliable)
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $file_path);
                finfo_close($finfo);
                if ($mime && strpos($mime, '/') !== false) {
                    return $mime;
                }
            }
        }

        // Method 2: WordPress wp_check_filetype
        $filetype = wp_check_filetype($file_path);
        if (!empty($filetype['type'])) {
            return $filetype['type'];
        }

        // Method 3: mime_content_type (if available)
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($file_path);
            if ($mime && strpos($mime, '/') !== false) {
                return $mime;
            }
        }

        return null;
    }

    /**
     * Check if MIME type is supported
     *
     * @param string $mime_type MIME type to check
     * @return bool True if supported
     */
    public function is_supported_mime_type(string $mime_type): bool {
        return in_array($mime_type, self::SUPPORTED_MIME_TYPES);
    }
}