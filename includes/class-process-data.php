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
				// Check if file_info exists and contains a persistent_path (indicating a file to process)
				if (!empty($input_data_packet['file_info']) && is_array($input_data_packet['file_info']) && !empty($input_data_packet['file_info']['persistent_path'])) {
					// --- Handle File Input (Image or PDF using persistent_path) ---
					$file_info = $input_data_packet['file_info'];
					// Construct prompt specifically for file analysis
					$user_message = $user_prompt; // Start with the base module prompt
					// Optionally append text content if available (e.g., extracted text or title)
					if (!empty($input_data_packet['content_string'])) {
						$user_message .= "\n\nAdditional Text Content:\n" . $input_data_packet['content_string'];
					}

					// Call the API method designed for file handling using the file_info array
					// create_response_with_file uses 'persistent_path' from $file_info
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
