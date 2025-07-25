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
	 * @param    string    $system_prompt       System instructions for processing
	 * @param    string    $user_prompt         User prompt instructing the model
	 * @param    array     $input_data_packet   Standardized input data packet
	 * @return   array                           API response data including 'status', 'message', and 'json_output'
	 */
	public function process_data($system_prompt, $user_prompt, $input_data_packet) {
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

			// Use standardized structure: look under 'data' for file_info and content_string
			$file_info = $input_data_packet['file_info'] ?? ($input_data_packet['data']['file_info'] ?? null);
			$content_string = $input_data_packet['content_string'] ?? ($input_data_packet['data']['content_string'] ?? null);

			$has_file_path = !empty($file_info['persistent_path']);
			$has_file_url = !empty($file_info['url']);

			// Build messages array
			$messages = [];
			
			if (!empty($system_prompt)) {
				$messages[] = [
					'role' => 'system',
					'content' => $system_prompt
				];
			}

			if (!empty($file_info) && is_array($file_info) && ($has_file_path || $has_file_url)) {
				// --- Handle File Input (Image or PDF using path or URL) ---
				$user_message = $user_prompt;

				// Append text content if available (e.g., title, comments)
				if (!empty($content_string)) {
					$user_message .= "\n\nAssociated Text Content:\n" . $content_string;
				}

				// Handle different file types using unified approach
				$mime_type = $file_info['mime_type'] ?? $file_info['type'] ?? null;
				
				if (empty($mime_type) && !empty($file_info['persistent_path']) && file_exists($file_info['persistent_path'])) {
					$mime_type = mime_content_type($file_info['persistent_path']);
				}

				if ($mime_type === 'application/pdf') {
					// For PDFs
					$file_path = $file_info['persistent_path'] ?? null;
					if ($file_path && file_exists($file_path)) {
						$messages[] = [
							'role' => 'user',
							'content' => [
								[
									'type' => 'text',
									'text' => $user_message
								],
								[
									'type' => 'document',
									'document_url' => 'file://' . $file_path,
									'filename' => basename($file_info['original_name'] ?? $file_path)
								]
							]
						];
					} else {
						$messages[] = [
							'role' => 'user',
							'content' => $user_message
						];
					}
				} elseif (in_array($mime_type, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'])) {
					// Handle images - use URL if available, otherwise file path
					$image_source = !empty($file_info['url']) ? $file_info['url'] : 'file://' . $file_info['persistent_path'];
					
					$messages[] = [
						'role' => 'user',
						'content' => [
							[
								'type' => 'text',
								'text' => $user_message
							],
							[
								'type' => 'image_url',
								'image_url' => [
									'url' => $image_source
								]
							]
						]
					];
				} else {
					// Fallback to text-only
					$messages[] = [
						'role' => 'user',
						'content' => $user_message
					];
				}
			} else {
				// --- Handle Text-Only Input ---
				$user_message = $user_prompt;
				if (!empty($content_string)) {
					$user_message .= "\n\nPost Content:\n" . $content_string;
				}
				
				$messages[] = [
					'role' => 'user',
					'content' => $user_message
				];
			}

			// Send step-aware request using AI HTTP Client library
			$response = $ai_http_client->send_step_request('process', [
				'messages' => $messages
			]);

			if (!$response['success']) {
				$default_response['message'] = 'AI API Error: ' . ($response['error'] ?? 'Unknown error');
				return $default_response;
			}

			$content = $response['data']['content'] ?? '';
			
			if (empty($content)) {
				$default_response['message'] = 'AI response was empty or invalid';
				return $default_response;
			}

			return [
				'status' => 'success',
				'message' => 'Data processed successfully.',
				'json_output' => trim($content)
			];

		} catch (Exception $e) {
			$default_response['message'] = 'Processing Exception: ' . $e->getMessage();
			return $default_response;
		}
	}

} // End class \\DataMachine\\Engine\\ProcessData
