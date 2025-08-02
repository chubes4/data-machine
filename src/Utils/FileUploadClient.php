<?php
/**
 * AI HTTP Client - File Upload Client
 * 
 * Single Responsibility: Handle file uploads to AI provider APIs
 * Based on Data Machine's working PDF upload implementation
 *
 * @package AIHttpClient\Utils
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_File_Upload_Client {

    /**
     * Upload file to provider's file API
     * Based on Data Machine's PDF upload patterns
     *
     * Note: This function uses cURL for multipart/form-data file uploads because
     * WordPress HTTP API does not support multipart file uploads as of WordPress 6.4.
     * This is a legitimate use case where cURL is required and cannot be replaced
     * with wp_remote_post() or wp_remote_get().
     *
     * @param string $file_path Local file path to upload
     * @param string $upload_url Provider's file upload endpoint
     * @param array $headers Authentication headers
     * @param string $purpose Purpose for the file (e.g., 'assistants', 'fine-tune')
     * @param int $timeout Request timeout in seconds
     * @return array Upload response with file ID
     * @throws Exception If upload fails
     */
    public static function upload_file($file_path, $upload_url, $headers, $purpose = 'assistants', $timeout = 120) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init -- Required for multipart file uploads
        if (!function_exists('curl_init')) {
            throw new Exception('cURL is required for file uploads');
        }

        if (!file_exists($file_path)) {
            throw new Exception('File not found');
        }

        if (!is_readable($file_path)) {
            throw new Exception('File not readable');
        }

        $file_size = filesize($file_path);
        if ($file_size === false) {
            throw new Exception('Could not determine file size');
        }

        // Convert headers array to cURL format
        $curl_headers = array();
        foreach ($headers as $key => $value) {
            $curl_headers[] = $key . ': ' . $value;
        }

        // Create multipart form data
        $post_fields = array(
            // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_file_create -- Required for multipart file uploads
            'file' => curl_file_create($file_path, self::get_mime_type($file_path), basename($file_path)),
            'purpose' => $purpose
        );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init -- Required for multipart file uploads
        $ch = curl_init($upload_url);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array -- Required for multipart file uploads
        curl_setopt_array($ch, array(
            CURLOPT_HTTPHEADER => $curl_headers,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $post_fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => false, // Security: Disable redirects for file uploads
            CURLOPT_MAXREDIRS => 0,          // Security: No redirects allowed
            CURLOPT_USERAGENT => 'AI-HTTP-Client/' . (defined('AI_HTTP_CLIENT_VERSION') ? AI_HTTP_CLIENT_VERSION : '1.0')
        ));

        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec -- Required for multipart file uploads
        $response = curl_exec($ch);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo -- Required for multipart file uploads
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error -- Required for multipart file uploads
        $error = curl_error($ch);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close -- Required for multipart file uploads
        curl_close($ch);

        if ($response === false) {
            throw new Exception('File upload error occurred');
        }

        if ($http_code >= 400) {
            throw new Exception('HTTP file upload error occurred');
        }

        $decoded_response = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from file upload');
        }

        return $decoded_response;
    }

    /**
     * Upload file from WordPress media library
     * WordPress-specific wrapper for file uploads
     *
     * @param int $attachment_id WordPress attachment ID
     * @param string $upload_url Provider's file upload endpoint
     * @param array $headers Authentication headers
     * @param string $purpose Purpose for the file
     * @param int $timeout Request timeout in seconds
     * @return array Upload response with file ID
     * @throws Exception If attachment not found or upload fails
     */
    public static function upload_attachment($attachment_id, $upload_url, $headers, $purpose = 'assistants', $timeout = 120) {
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path) {
            throw new Exception('Attachment not found');
        }

        return self::upload_file($file_path, $upload_url, $headers, $purpose, $timeout);
    }

    /**
     * Get MIME type for file
     *
     * @param string $file_path File path
     * @return string MIME type
     */
    private static function get_mime_type($file_path) {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        $mime_types = array(
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'json' => 'application/json',
            'csv' => 'text/csv',
            'md' => 'text/markdown',
            'html' => 'text/html',
            'htm' => 'text/html',
            'xml' => 'text/xml'
        );

        return isset($mime_types[$extension]) ? $mime_types[$extension] : 'application/octet-stream';
    }

    /**
     * Check if file size is suitable for upload vs embedding
     * Based on Data Machine's size thresholds
     *
     * @param string $file_path File path
     * @param int $max_embed_size Maximum size for embedding (default 5MB like Data Machine)
     * @return bool True if file should be uploaded, false if it can be embedded
     */
    public static function should_upload_file($file_path, $max_embed_size = 5242880) {
        if (!file_exists($file_path)) {
            return false;
        }

        $file_size = filesize($file_path);
        return $file_size === false || $file_size > $max_embed_size;
    }

    /**
     * Encode file to base64 for embedding
     * For smaller files that don't need upload API
     *
     * @param string $file_path File path
     * @return string Base64 encoded file content
     * @throws Exception If file cannot be read
     */
    public static function encode_file_base64($file_path) {
        if (!file_exists($file_path)) {
            throw new Exception('File not found');
        }

        $content = file_get_contents($file_path);
        if ($content === false) {
            throw new Exception('Could not read file');
        }

        $mime_type = self::get_mime_type($file_path);
        return 'data:' . $mime_type . ';base64,' . base64_encode($content);
    }

    /**
     * Delete uploaded file from provider
     *
     * Note: This function uses cURL for DELETE requests because WordPress HTTP API
     * does not reliably support custom HTTP methods like DELETE in all environments.
     * This is a legitimate use case where cURL provides better compatibility.
     *
     * @param string $file_id File ID from upload response
     * @param string $delete_url Provider's file delete endpoint
     * @param array $headers Authentication headers
     * @param int $timeout Request timeout in seconds
     * @return bool True if deletion successful
     */
    public static function delete_file($file_id, $delete_url, $headers, $timeout = 30) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init -- Required for DELETE method reliability
        if (!function_exists('curl_init')) {
            return false;
        }

        $url = rtrim($delete_url, '/') . '/' . $file_id;
        
        // Convert headers array to cURL format
        $curl_headers = array();
        foreach ($headers as $key => $value) {
            $curl_headers[] = $key . ': ' . $value;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init -- Required for DELETE method reliability
        $ch = curl_init($url);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array -- Required for DELETE method reliability
        curl_setopt_array($ch, array(
            CURLOPT_HTTPHEADER => $curl_headers,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => false, // Security: Disable redirects for delete operations
            CURLOPT_MAXREDIRS => 0,          // Security: No redirects allowed
            CURLOPT_USERAGENT => 'AI-HTTP-Client/' . (defined('AI_HTTP_CLIENT_VERSION') ? AI_HTTP_CLIENT_VERSION : '1.0')
        ));

        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec -- Required for DELETE method reliability
        $response = curl_exec($ch);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo -- Required for DELETE method reliability
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close -- Required for DELETE method reliability
        curl_close($ch);

        return $response !== false && $http_code >= 200 && $http_code < 300;
    }
}