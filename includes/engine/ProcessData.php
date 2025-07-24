<?php

/**
 * Handles Data processing workflow using OpenAI API.
 *
 * This class determines the input type (file upload or structured data)
 * and calls the appropriate OpenAI API method.
 *
 * @since    0.1.0
 */

namespace DataMachine\Engine;

use DataMachine\Api\OpenAi;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ProcessData {

	/**
	 * OpenAI API client instance.
	 * @since    0.9.0
	 * @var      OpenAi    $openai_api    OpenAI API client instance.
	 */
	private $openai_api;

	/**
	 * Initialize the class and set its properties.
	 * Dependencies are injected.
	 *
	 * @since    0.1.0 (Refactored 0.9.0)
	 * @param    OpenAi $openai_api OpenAI API client instance.
	 */
	public function __construct(OpenAi $openai_api) { // Removed $logger argument
		$this->openai_api = $openai_api;
		// $this->logger = $logger; // Removed logger assignment
	}

	/**
	 * Process Data using OpenAI API.
	 *
	 * Checks the input data packet and calls the relevant OpenAI API method.
	 *
	 * @since    0.1.0 (Refactored in 0.8.0)
	 * @param    string    $api_key             OpenAI API Key.
	 * @param    string    $process_data_prompt Prompt instructing the model how to process the data.
	 * @param    array     $input_data_packet   Standardized input data packet from the Input Handler.
	 * @return   array                           API response data including 'status', 'message', and 'json_output'.
	 */
	public function process_data($api_key, $system_prompt, $user_prompt, $input_data_packet) {
		// Log the full input_data_packet for debugging
		// Use the injected OpenAI API instance
		$api = $this->openai_api;
		$default_response = [
			'status' => 'error',
			'message' => '',
			'json_output' => null
		];

		try {
			$response = null;

			if (!is_array($input_data_packet)) {
				        // Error logging removed for production
			} else {
				// Use standardized structure: look under 'data' for file_info and content_string
				$file_info = $input_data_packet['file_info'] ?? ($input_data_packet['data']['file_info'] ?? null);
				$content_string = $input_data_packet['content_string'] ?? ($input_data_packet['data']['content_string'] ?? null);

				$has_file_path = !empty($file_info['persistent_path']);
				$has_file_url = !empty($file_info['url']);

				if (!empty($file_info) && is_array($file_info) && ($has_file_path || $has_file_url)) {
					// --- Handle File Input (Image or PDF using path or URL) ---
					// Construct base user message from the module prompt
					$user_message = $user_prompt;

					// NOTE: Image analysis instructions are now handled by the centralized PromptBuilder class.
					// The user_message parameter already contains any necessary image-specific instructions.

					// Append text content if available (e.g., title, comments)
					if (!empty($content_string)) {
						$user_message .= "\n\nAssociated Text Content:\n" . $content_string;
					}

					// Log only once for file input
					$response = $api->create_response_with_file($api_key, $file_info, $user_message);

				} else {
					// --- Handle Text-Only Input ---
					$user_message = $user_prompt;
					if (!empty($content_string)) {
						$user_message .= "\n\nPost Content:\n" . $content_string;
					}
					// Log only once for text input
					$response = $api->create_completion_from_text($api_key, $user_message, $system_prompt);
				}
			}

			if (is_wp_error($response)) {
				$error_message = 'OpenAI API Error: ' . $response->get_error_message();
				$default_response['message'] = $error_message;
				// Error logging removed for production
				        // Error logging removed for production
				return $default_response;
			}

			if (!empty($response['is_error'])) {
				$error_message = $response['error_message'] ?? 'Unknown API error occurred';
				$default_response['message'] = $error_message;
				$log_details = array_intersect_key($response, array_flip(['status_code', 'body']));
				$log_details['metadata'] = $input_data_packet['metadata'] ?? [];
				// Error logging removed for production
				return $default_response;
			}

			if (isset($response['output_text'])) {
				return [
					'status' => 'success',
					'message' => 'Data processed successfully.',
					'json_output' => trim($response['output_text'])
				];
			}

			$error_message = 'API response parsed successfully but contained no output text.';
			$default_response['message'] = $error_message;
			$log_details = $response;
			$log_details['metadata'] = $input_data_packet['metadata'] ?? [];
			// Error logging removed for production
			return $default_response;

		} catch (Exception $e) {
			$error_message = 'Processing Exception: ' . $e->getMessage();
			$default_response['message'] = $error_message;
			$log_details = ['trace' => $e->getTraceAsString()];
			// Attempt to log metadata or the problematic packet
			if (isset($input_data_packet['metadata']) && is_array($input_data_packet['metadata'])) {
				 $log_details['metadata'] = $input_data_packet['metadata'];
			} elseif (is_array($input_data_packet)) { // Only log if it's an array but failed checks
				 $log_details['problematic_packet_keys'] = array_keys($input_data_packet); // Log keys if it's an array
			} else {
				 $log_details['problematic_packet_type'] = gettype($input_data_packet); // Log type if not array
			}
			// Error logging removed for production
			return $default_response;
		}
	} // End process_data method

} // End class \\DataMachine\\Engine\\ProcessData
