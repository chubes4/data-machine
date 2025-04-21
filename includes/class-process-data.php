<?php

/**
 * Handles Data processing workflow using OpenAI API.
 *
 * This class determines the input type (file upload or structured data)
 * and calls the appropriate OpenAI API method.
 *
 * @since    0.1.0
 */
class Data_Machine_process_data {

	/**
	 * OpenAI API client instance.
	 * @since    0.9.0
	 * @var      Data_Machine_API_OpenAI    $openai_api    OpenAI API client instance.
	 */
	private $openai_api;

	// Removed logger dependency property

	/**
	 * Initialize the class and set its properties.
	 * Dependencies are injected.
	 *
	 * @since    0.1.0 (Refactored 0.9.0)
	 * @param    Data_Machine_API_OpenAI $openai_api OpenAI API client instance.
	 */
	public function __construct(Data_Machine_API_OpenAI $openai_api) { // Removed $logger argument
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
		// Use the injected OpenAI API instance
		$api = $this->openai_api;
		$default_response = [
			'status' => 'error',
			'message' => '',
			'json_output' => null
		];

		try {
			$response = null;

			// --- Add detailed logging here ---
			if (!is_array($input_data_packet)) {
				error_log('Data Machine - process_data: Received input_data_packet is not an array. Content: ' . print_r($input_data_packet, true));
			} else {
				// Check if file_info exists and indicates a file (either path or URL is present)
				$has_file_path = !empty($input_data_packet['file_info']['persistent_path']);
				$has_file_url = !empty($input_data_packet['file_info']['url']);

				if (!empty($input_data_packet['file_info']) && is_array($input_data_packet['file_info']) && ($has_file_path || $has_file_url)) {
					// --- Handle File Input (Image or PDF using path or URL) ---
					$file_info = $input_data_packet['file_info'];
					$mime_type = $file_info['type'] ?? 'application/octet-stream'; // Get mime type

					// Construct base user message from the module prompt
					$user_message = $user_prompt;

					// --- Add image-specific instruction if applicable --- 
					if (str_starts_with($mime_type, 'image/')) {
						$image_directive = "IMPORTANT INSTRUCTION: An image has been provided. Analyze the visual content of the image carefully. Prioritize information directly observed in the image, especially for identifying people, objects, or specific visual details, over potentially conflicting information in the text below.";
						$user_message = $image_directive . "\n\n---\n\n" . $user_message; // Prepend directive
					}
					// --- End image instruction ---

					// Append text content if available (e.g., title, comments)
					if (!empty($input_data_packet['content_string'])) {
						$user_message .= "\n\nAssociated Text Content:\n" . $input_data_packet['content_string'];
					}

					// Call the API method designed for file handling (path or URL)
					error_log("--- DM Process Data Debug: File Input --- Module Config Prompts:\nSystem: " . print_r($system_prompt, true) . "\nUser (base): " . print_r($user_prompt, true) . "\nUser (final with content): " . print_r($user_message, true));
					$response = $api->create_response_with_file($api_key, $file_info, $user_message);

				} else {
					// --- Handle Text-Only Input ---
					// Construct prompt for text analysis
					$user_message = $user_prompt;
					// Append text content
					if (!empty($input_data_packet['content_string'])) {
						$user_message .= "\n\nPost Content:\n" . $input_data_packet['content_string'];
					} elseif (!empty($input_data_packet['content'])) { // Fallback to 'content'
						$user_message .= "\n\nPost Content:\n" . $input_data_packet['content'];
					}
					
					error_log("--- DM Process Data Debug: Text Input --- Module Config Prompts:\nSystem: " . print_r($system_prompt, true) . "\nUser (base): " . print_r($user_prompt, true) . "\nUser (final with content): " . print_r($user_message, true));
					// Call the API method for text completion
					$response = $api->create_completion_from_text($api_key, $user_message, $system_prompt);
				}
			}
				// --- End detailed logging ---

			if (is_wp_error($response)) {
				$error_message = 'OpenAI API Error: ' . $response->get_error_message();
				$default_response['message'] = $error_message;
				// Use basic error_log instead of injected logger
				error_log($error_message . ' | Context: ' . print_r(['error_code' => $response->get_error_code(), 'metadata' => $input_data_packet['metadata'] ?? []], true));
				return $default_response;
			}

			if (!empty($response['is_error'])) {
				$error_message = $response['error_message'] ?? 'Unknown API error occurred';
				$default_response['message'] = $error_message;
				$log_details = array_intersect_key($response, array_flip(['status_code', 'body']));
				$log_details['metadata'] = $input_data_packet['metadata'] ?? [];
				// Use basic error_log
				error_log($error_message . ' | Context: ' . print_r($log_details, true));
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
			// Use basic error_log
			error_log($error_message . ' | Context: ' . print_r($log_details, true));
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
			// Use basic error_log
			error_log($error_message . ' | Context: ' . print_r($log_details, true));
			return $default_response;
		}
	} // End process_data method

} // End class Data_Machine_process_data
