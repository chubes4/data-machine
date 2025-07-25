<?php

/**
 * Handles data processing workflow using unified AI HTTP Client library
 *
 * This class determines the input type (file upload or structured data)
 * and calls the appropriate AI provider via the unified library.
 *
 * @since    0.1.0
 */

namespace DataMachine\Engine;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ProcessData {

	/**
	 * Constructor. No dependencies needed - uses AI HTTP Client from global container.
	 */
	public function __construct() {
		// AI HTTP Client is available via global container
	}

	/**
	 * Process data using unified AI HTTP Client library.
	 *
	 * Checks the input data packet and calls the appropriate AI provider method.
	 *
	 * @since    0.1.0 (Refactored to use AI HTTP Client library)
	 * @param    string    $system_prompt         System instructions (project context + directives)
	 * @param    string    $enhanced_module_prompt Enhanced module prompt from PromptBuilder (used as user message)
	 * @param    array     $input_data_packet     Standardized input data packet
	 * @return   array                             API response data including 'status', 'message', and 'json_output'
	 */
	public function process_data($system_prompt, $enhanced_module_prompt, $input_data_packet) {
		// Get AI HTTP Client from global container
		global $data_machine_container;
		$ai_http_client = $data_machine_container['ai_http_client'] ?? null;
		
		if (!$ai_http_client) {
			return [
				'status' => 'error',
				'message' => 'AI HTTP Client not available in container',
				'json_output' => null
			];
		}

		$default_response = [
			'status' => 'error',
			'message' => '',
			'json_output' => null
		];

		try {
			if (!is_array($input_data_packet)) {
				$default_response['message'] = 'Invalid input data packet format';
				return $default_response;
			}

			// Simple helper methods extract what we need
			$image_urls = $this->extract_image_urls($input_data_packet);
			$content_text = $this->extract_content_text($input_data_packet);
			$user_message = $enhanced_module_prompt . $content_text;

			// Build messages array - let library handle multimodal complexity
			$messages = [
				['role' => 'system', 'content' => $system_prompt],
				[
					'role' => 'user',
					'content' => $user_message,
					'image_urls' => $image_urls  // Library handles this automatically
				]
			];

			// Send step-aware request using AI HTTP Client library
			$response = $ai_http_client->send_step_request('process', [
				'messages' => $messages
			]);

			// Use helper method to format response
			return $this->format_response($response);

		} catch (Exception $e) {
			$default_response['message'] = 'Processing Exception: ' . $e->getMessage();
			return $default_response;
		}
	}

	/**
	 * Extract image URLs from input data packet for multimodal processing
	 *
	 * @param array $input_data_packet Input data packet
	 * @return array Array of image URLs
	 */
	private function extract_image_urls($input_data_packet) {
		$file_info = $input_data_packet['file_info'] ?? ($input_data_packet['data']['file_info'] ?? null);
		
		if (empty($file_info)) {
			return [];
		}
		
		// Handle image URLs
		if (!empty($file_info['url']) && $this->is_image_file($file_info['url'])) {
			return [$file_info['url']];
		}
		
		// Handle local image files (convert to data URLs)
		if (!empty($file_info['persistent_path']) && $this->is_image_file($file_info['persistent_path'])) {
			$data_url = $this->convert_to_data_url($file_info['persistent_path']);
			if ($data_url) {
				return [$data_url];
			}
		}
		
		return [];
	}

	/**
	 * Extract content text from input data packet
	 *
	 * @param array $input_data_packet Input data packet
	 * @return string Formatted content text
	 */
	private function extract_content_text($input_data_packet) {
		$content_string = $input_data_packet['content_string'] ?? ($input_data_packet['data']['content_string'] ?? '');
		
		if (empty($content_string)) {
			return '';
		}
		
		return "\n\nContent to Process:\n" . $content_string;
	}

	/**
	 * Format AI response to standard Data Machine format
	 *
	 * @param array $response AI HTTP Client response
	 * @return array Formatted response
	 */
	private function format_response($response) {
		if (!$response['success']) {
			return [
				'status' => 'error',
				'message' => 'AI API Error: ' . ($response['error'] ?? 'Unknown error'),
				'json_output' => null
			];
		}

		$content = $response['data']['content'] ?? '';
		
		if (empty($content)) {
			return [
				'status' => 'error',
				'message' => 'AI response was empty or invalid',
				'json_output' => null
			];
		}

		return [
			'status' => 'success',
			'message' => 'Data processed successfully.',
			'json_output' => trim($content)
		];
	}

	/**
	 * Convert local image file to data URL
	 *
	 * @param string $file_path Local file path
	 * @return string|false Data URL or false on failure
	 */
	private function convert_to_data_url($file_path) {
		if (!file_exists($file_path) || !is_readable($file_path)) {
			return false;
		}

		$mime_type = mime_content_type($file_path);
		if (!$mime_type || !str_starts_with($mime_type, 'image/')) {
			return false;
		}

		$file_data = file_get_contents($file_path);
		if ($file_data === false) {
			return false;
		}

		return 'data:' . $mime_type . ';base64,' . base64_encode($file_data);
	}

	/**
	 * Check if a file path represents an image file
	 *
	 * @param string $file_path The file path to check
	 * @return bool True if it's an image file
	 */
	private function is_image_file(string $file_path): bool {
		$image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
		$extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
		return in_array($extension, $image_extensions);
	}

} // End class \\DataMachine\\Engine\\ProcessData
