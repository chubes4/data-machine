<?php
/**
 * Handles raw API communication with OpenAI API
 */
class Data_Machine_API_OpenAI {

    private $timeout = 120;

        /**
     * Constructor. Dependencies are injected.
     */
    // Let's accept specific dependencies needed, rather than the whole plugin instance
    public function __construct() { // No direct dependencies needed for now, uses get_option
        // No dependencies needed in constructor for now
    }


/**
 * Upload a file to OpenAI
 */

 // NOTE: Currently only PDF files are supported by OpenAI for file uploads.
// Keeping function name generic (`upload_file_to_openai`) for future compatibility.

public function upload_file_to_openai($api_key, $data_file) {
    $endpoint = 'https://api.openai.com/v1/files';

    // For file inputs, use purpose "user_data" (per docs)
    $purpose = 'user_data';
    $filename = basename($data_file['name']);
    // Use the persistent path from the file_info array
    $file_path = $data_file['persistent_path'] ?? null;
    if (empty($file_path) || !file_exists($file_path)) {
        return new WP_Error('missing_persistent_file_for_upload', 'Persistent file path is missing or file does not exist for upload.');
    }
    $file_contents = file_get_contents($file_path);
    // Use the MIME type passed in the file_info array
    $mime_type = $data_file['type'] ?? null;
    if (empty($mime_type)) {
        // Fallback to detecting from persistent path if not provided
        $mime_type = mime_content_type($file_path);
    }

    // Create a boundary string
    $boundary = '----WebKitFormBoundary' . md5(time());
    $eol = "\r\n";

    // Build the multipart form-data body manually
    $body = '';
    // Add the purpose field
    $body .= "--" . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="purpose"' . $eol . $eol;
    $body .= $purpose . $eol;
    // Add the file field
    $body .= "--" . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="file"; filename="' . $filename . '"' . $eol;
    $body .= 'Content-Type: ' . $mime_type . $eol . $eol;
    $body .= $file_contents . $eol;
    $body .= "--" . $boundary . "--" . $eol;

    // Set up headers without setting a manual Content-Type header earlier
    $headers = [
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
    ];

    $args = [
        'headers' => $headers,
        'body'    => $body,
        'timeout' => 120,
    ];

    $response = wp_remote_post($endpoint, $args);
    return $this->parse_response($response);
}



/**
 * Dynamically handle and process file uploads (PDF and image files)
 */
public function create_response_with_file($api_key, $file, $prompt) {
    $endpoint = 'https://api.openai.com/v1/responses';
    
    // Use the persistent path passed from the job data
    $file_path = $file['persistent_path'] ?? null;

    if (empty($file_path) || !file_exists($file_path)) {
        // error_log('File check failed: Path empty or file does not exist.'); // Keep commented if needed later
        return new WP_Error('missing_persistent_file', 'Persistent file path is missing or file does not exist for job. Path checked: ' . $file_path); // Add path to error
    }
    // Use the MIME type passed in the file_info array
    $mime_type = $file['type'] ?? null;
    if (empty($mime_type)) {
         // Fallback to detecting from persistent path if not provided
         $mime_type = mime_content_type($file_path);
    }
error_log('MIME type: ' . $mime_type); // Debugging line
    // Initialize payload variable
    $payload = [];
    $pdf_size_threshold = 5 * 1024 * 1024; // 5MB threshold for switching to file upload


    // Handle PDF files
    if ($mime_type === 'application/pdf') {
        $file_size = filesize($file_path);
        $filename = basename($file['name']); // Get the original filename
        $pdf_content_payload = [];

        if ($file_size <= $pdf_size_threshold) {
            // Use Base64 for smaller PDFs
            $pdf_data = file_get_contents($file_path);
            if ($pdf_data === false) {
                return new WP_Error('pdf_read_error', 'Failed to read PDF file content.');
            }
            $base64_pdf = base64_encode($pdf_data);
            $pdf_content_payload = [
                'type' => 'input_file',
                'filename' => $filename,
                'file_data' => "data:application/pdf;base64,{$base64_pdf}"
            ];
        } else {
            // Use File Upload for larger PDFs
            $upload_response = $this->upload_file_to_openai($api_key, $file); // Pass the original $file array

            if (is_wp_error($upload_response)) {
                // Propagate the error from the upload function
                return $upload_response;
            }
            if (empty($upload_response['id'])) {
                return new WP_Error('file_upload_error', 'Failed to upload PDF file or retrieve file ID.', $upload_response);
            }
            $file_id = $upload_response['id'];
            $pdf_content_payload = [
                'type' => 'input_file',
                'file_id' => $file_id
            ];
        }

        // Construct the final payload using the determined PDF content method
        $payload = [
            'model' => 'gpt-4o-mini', // Or gpt-4o if needed for PDFs
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        $pdf_content_payload, // Use either file_data or file_id payload
                        ['type' => 'input_text', 'text' => $prompt]
                    ]
                ]
            ]
        ];
    }
    // Handle image files directly with Base64 encoding
    elseif (in_array($mime_type, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'])) {
        $base64_image = base64_encode(file_get_contents($file_path));


        $payload = [
            'model' => 'gpt-4o',
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => $prompt],
                        ['type' => 'input_image', 'image_url' => "data:$mime_type;base64,$base64_image"]
                    ]
                ]
            ]
        ];
    }
    else {
        return new WP_Error('unsupported_file_type', 'Unsupported file type: ' . $mime_type);
    }

    // Send payload to OpenAI
    $args = [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body'    => json_encode($payload),
        'timeout' => 120,
    ];

    $response = wp_remote_post($endpoint, $args);
    $decoded = $this->parse_response($response);

    // Extract the aggregated text output.
    $output_text = '';
    if (!empty($decoded['output_text'])) {
        $output_text = $decoded['output_text'];
    } elseif (!empty($decoded['output']) && is_array($decoded['output'])) {
        foreach ($decoded['output'] as $item) {
            if (!empty($item['content']) && is_array($item['content'])) {
                foreach ($item['content'] as $contentItem) {
                    if (isset($contentItem['type']) && $contentItem['type'] === 'output_text') {
                        $output_text .= $contentItem['text'];
                    }
                }
            }
        }
    }

    // --- Log raw content before trimming ---
    error_log('OpenAI API: Raw content received: [START]' . $output_text . '[END]');
    // --- End Log ---

    // Clean formatting: Remove potential backticks and "json" markers.
    $output_text = preg_replace('/^```json\s*/i', '', $output_text);
    $output_text = preg_replace('/```$/i', '', $output_text);
    $output_text = trim($output_text);

    $decoded['output_text'] = $output_text;

    return $decoded;
}


	/**
	 * Create a completion based on text content using the Chat Completions endpoint.
	 *
	 * @param string $api_key        OpenAI API Key.
	 * @param string $content_string The text content to process.
	 * @param string $prompt         The prompt to guide the model.
	 * @return array|WP_Error Parsed API response or WP_Error on failure.
	 */
	public function create_completion_from_text($api_key, $content_string, $prompt) {
		$endpoint = 'https://api.openai.com/v1/chat/completions';

		// Basic check for empty content
		if (empty(trim($content_string))) {
			return new WP_Error('empty_content', 'Input content string is empty.');
		}

		// Construct the payload for Chat Completions
		// Combine prompt and content. Using a system prompt and user content is common.
		$payload = [
			'model' => 'gpt-4o-mini', // Or another suitable text model
			'messages' => [
				[
					'role' => 'system',
					'content' => $prompt // System message defines the task
				],
				[
					'role' => 'user',
					'content' => $content_string // User message provides the data
				]
			],
			// Add other parameters like temperature, max_tokens if needed
			// 'temperature' => 0.7,
			// 'max_tokens' => 1500,
		];

		// Send payload to OpenAI
		$args = [
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			],
			'body'    => json_encode($payload),
			'timeout' => 300, // Increased timeout to 5 minutes for testing
		];

		// --- Logging before API call ---
		error_log('OpenAI API: Calling chat completions endpoint. Timeout: 300s');
		// --- End Logging ---

		$response = wp_remote_post($endpoint, $args);

		// --- Logging after API call ---
		$response_log_details = [
			'is_wp_error' => is_wp_error($response),
			'wp_error_code' => is_wp_error($response) ? $response->get_error_code() : null,
			'response_code' => !is_wp_error($response) ? wp_remote_retrieve_response_code($response) : null,
		];
		error_log('OpenAI API: Received response from chat completions endpoint. Details: ' . print_r($response_log_details, true));
		// --- End Logging ---

		$decoded = $this->parse_response($response);

		// --- Restore extraction logic --- 
		// Extract text content specifically for Chat Completions format
		$output_text = '';
		if (isset($decoded['choices'][0]['message']['content'])) {
			$output_text = $decoded['choices'][0]['message']['content'];
		} elseif (!empty($decoded['output_text'])) {
             // Fallback if parse_response somehow added it differently (should not happen with current parse_response)
             $output_text = $decoded['output_text'];
        }

		// --- Log raw content before trimming ---
		error_log('OpenAI API: Raw content received: [START]' . $output_text . '[END]');
		// --- End Log ---

		// Clean formatting (optional, but good practice)
		$output_text = preg_replace('/^```json\s*/i', '', $output_text);
		$output_text = preg_replace('/```$/i', '', $output_text);
		$output_text = trim($output_text);

		// Ensure the final output text is stored consistently
		$decoded['output_text'] = $output_text;
		// --- End restore extraction logic ---

		return $decoded;
	}



    /**
     * Parse API responses
     */
    private function parse_response($response) {
        $decoded_response = array( 'is_error' => false, 'output_text' => '' ); // Initialize with no error
        $error_message = '';
        $error_data = array();

        if (is_wp_error($response)) {
            $error_message = 'OpenAI API Error (WP_Error): ' . $response->get_error_message();
            $error_data = array(
                'error_code'    => $response->get_error_code(),
                'error_message' => $response->get_error_message(),
            );
            error_log($error_message . ' | Details: ' . print_r($error_data, true)); // Basic error log
            $decoded_response['is_error'] = true; // Set error flag
            $decoded_response['error_message'] = $error_message; // Include error message in response
            return $decoded_response; // Return with error flag set

        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status !== 200) {
            $error_message = "API request failed with status $status";
            error_log($error_message . ' | Status: ' . $status . ' | Body: ' . $body); // Basic error log
            $decoded_response['is_error'] = true; // Set error flag
            $decoded_response['error_message'] = $error_message; // Include error message
            $decoded_response['status_code'] = $status; // Include status code
            $decoded_response['body'] = $body; // Include body for debugging
            return $decoded_response; // Return with error flag set
        }

        $decoded_body = json_decode($body, true);
        if (is_array($decoded_body)) {
            return array_merge($decoded_response, $decoded_body); // Merge decoded body into response
        } else {
            $decoded_response['is_error'] = true; // Indicate JSON decode error
            $decoded_response['error_message'] = 'Failed to decode JSON response body';
            error_log($decoded_response['error_message'] . ' | Body: ' . $body); // Basic error log
            return $decoded_response;
        }
    }
}