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
    $filename = basename($data_file['original_name']);
    // Use the persistent path from the file_info array
    $file_path = $data_file['persistent_path'] ?? null;
    if (empty($file_path) || !file_exists($file_path)) {
        return new WP_Error('missing_persistent_file_for_upload', 'Persistent file path is missing or file does not exist for upload.');
    }
    
    // Check memory safety before loading file
    if (class_exists('Data_Machine_Memory_Guard')) {
        $memory_guard = new Data_Machine_Memory_Guard();
        if (!$memory_guard->can_load_file($file_path, 2.0)) {
            return new WP_Error('memory_limit', 'File too large to upload safely. Please reduce file size or increase server memory limit.');
        }
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

    // --- Adjusted Check: Only check file_exists if a persistent_path was actually provided --- 
    if (!empty($file_path) && !file_exists($file_path)) {
        // This error should now only trigger for inputs that *do* provide a persistent_path (like file uploads, large PDFs) but the file is missing.
        return new WP_Error('missing_persistent_file', 'Persistent file path provided but file does not exist. Path checked: ' . $file_path);
    }
    // --- End Adjusted Check ---

    // Use the MIME type passed in the file_info array - Use 'mime_type' key
    $mime_type = $file['mime_type'] ?? null;
    // Retrieve the image URL if available (provided by Reddit handler now)
    $image_url = $file['url'] ?? null; 

    // If MIME type is missing and we have a persistent path, try detecting it (e.g., for uploaded files)
    if (empty($mime_type) && !empty($file_path) && file_exists($file_path)) { 
        $mime_type = mime_content_type($file_path);
    }
    // If MIME type is still missing, we cannot proceed reliably (applies to both path and URL inputs)
    if (empty($mime_type)) {
                    // Error logging removed for production
        return new WP_Error('mime_type_missing', 'Could not determine the MIME type for the provided file input.');
    }

            // Debug logging removed for production
    // Initialize payload variable
    $payload = [];
    $pdf_size_threshold = 5 * 1024 * 1024; // 5MB threshold for switching to file upload


    // Handle PDF files
    if ($mime_type === 'application/pdf') {
        $file_size = filesize($file_path);
        $filename = basename($file['original_name']); // Use original_name
        $pdf_content_payload = [];

        if ($file_size <= $pdf_size_threshold) {
            // Check memory safety before loading file
            if (class_exists('Data_Machine_Memory_Guard')) {
                $memory_guard = new Data_Machine_Memory_Guard();
                if (!$memory_guard->can_load_file($file_path, 2.5)) {
                    return new WP_Error('memory_limit', 'File too large to process safely. Consider increasing memory limit or using file upload method.');
                }
            }
            
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
            'model' => Data_Machine_Constants::AI_MODEL_INITIAL,
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
    // Handle image files - check for URL first, then persistent path
    elseif (in_array($mime_type, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'])) {
        
        if (!empty($image_url)) {
             // --- Handle image via URL (e.g., from Reddit) --- 
            $payload = [
                'model' => Data_Machine_Constants::AI_MODEL_INITIAL, // Use vision model
                'input' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'input_text', 'text' => $prompt],
                            [
                                'type' => 'input_image',
                                'image_url' => $image_url
                            ]
                        ]
                    ]
                ]
            ];
            // --- End URL Handling ---

        } elseif (!empty($file_path)) {
            // --- Handle image via persistent path (e.g., from File Upload) --- 
            // Ensure file still exists (redundant check, but safe)
            if (!file_exists($file_path)) {
                return new WP_Error('missing_persistent_file_image', 'Persistent file path for image provided but file does not exist. Path checked: ' . $file_path);
            }
            // Read content and encode to Base64
            $image_content = file_get_contents($file_path);
            if ($image_content === false) {
                return new WP_Error('image_read_error', 'Failed to read image file content from persistent path.', ['path' => $file_path]);
            }
            $base64_image = base64_encode($image_content);
            unset($image_content); // Free memory

            $payload = [
                'model' => Data_Machine_Constants::AI_MODEL_INITIAL, // Use vision model
                'input' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'input_text', 'text' => $prompt],
                            // Send Base64 encoded data
                            ['type' => 'input_image', 'image_url' => "data:$mime_type;base64,$base64_image"]
                        ]
                    ]
                ]
            ];
             // --- End Base64 Handling ---
        } else {
            // Neither URL nor Path provided for image type - Error
            // Error logging removed for production
            return new WP_Error('image_input_source_missing', 'Image input is missing both a URL and a file path.');
        }
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

    // Debug logging removed for production

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
	public function create_completion_from_text($api_key, $user_message, $system_prompt) {
		$endpoint = 'https://api.openai.com/v1/chat/completions';

		// Basic check for empty user message
		if (empty(trim($user_message))) {
			return new WP_Error('empty_content', 'Input user message is empty.');
		}

		// Construct the payload for Chat Completions
		// Use system prompt and user message as separate roles.
		$payload = [
			'model' => Data_Machine_Constants::AI_MODEL_INITIAL,
			'messages' => [
				[
					'role' => 'system',
					'content' => $system_prompt // System message defines the project-level context
				],
				[
					'role' => 'user',
					'content' => $user_message // User message provides the module/task-specific prompt and data
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

		// Debug logging removed for production

		$response = wp_remote_post($endpoint, $args);

		// Debug logging removed for production

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

		// Debug logging removed for production

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
            // Error logging removed for production
            $decoded_response['is_error'] = true; // Set error flag
            $decoded_response['error_message'] = $error_message; // Include error message in response
            return $decoded_response; // Return with error flag set

        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status !== 200) {
            $error_message = "API request failed with status $status";
            // Error logging removed for production
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
            // Error logging removed for production
            return $decoded_response;
        }
    }
}