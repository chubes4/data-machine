<?php
/**
 * Handles raw API communication with OpenAI API
 */
class Auto_Data_Collection_API_OpenAI {


    private $plugin;
    private $timeout = 80;

        /**
     * Constructor to receive plugin instance
     */
    public function __construct(Auto_Data_Collection $plugin) {
        $this->plugin = $plugin;
    }

/**
 * Upload a PDF file to OpenAI
 */
/**
 * Upload a PDF file to OpenAI
 */
public function upload_pdf_to_openai($api_key, $pdf_file) {
    $endpoint = 'https://api.openai.com/v1/files';

    // For PDF file inputs, use purpose "user_data" (per docs)
    $purpose = 'user_data';
    $filename = basename($pdf_file['name']);
    $file_contents = file_get_contents($pdf_file['tmp_name']);
    $mime_type = 'application/pdf';

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
        'timeout' => 80,
    ];

    $response = wp_remote_post($endpoint, $args);
    return $this->parse_response($response);
}



/**
 * Create a response using the uploaded file and a prompt.
 */
public function create_response_with_file($api_key, $file_id, $prompt) {
    $endpoint = 'https://api.openai.com/v1/responses';
    $payload = [
        'model' => 'gpt-4o-mini', // or another supported model
        'input' => [
            [
                'role'    => 'user',
                'content' => [
                    [
                        'type'    => 'input_file',
                        'file_id' => $file_id
                    ],
                    [
                        'type' => 'input_text',
                        'text' => $prompt
                    ]
                ]
            ]
        ]
    ];
    
    $args = [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body'    => json_encode($payload),
        'timeout' => 80,
    ];

    $response = wp_remote_post($endpoint, $args);
    $decoded = $this->parse_response($response);

    if (is_wp_error($decoded)) {
        return $decoded; // Return WP_Error object immediately
    }
    
    // Extract the aggregated text output.
    $json_output = '';
    if (isset($decoded['output_text']) && !empty($decoded['output_text'])) {
        $json_output = $decoded['output_text'];
    } elseif (isset($decoded['output']) && is_array($decoded['output'])) {
        foreach ($decoded['output'] as $item) {
            if (isset($item['content']) && is_array($item['content'])) {
                foreach ($item['content'] as $contentItem) {
                    if (isset($contentItem['type']) && $contentItem['type'] === 'output_text') {
                        $json_output .= $contentItem['text'];
                    }
                }
            }
        }
    }
    
    // Remove potential backticks and "json" marker from the API response
    $json_output = preg_replace('/^```json\s*/i', '', $json_output);
    $json_output = preg_replace('/```$/i', '', $json_output);
    $json_output = trim($json_output);

    // Optionally attach the cleaned, aggregated output to the response object.
    $decoded['output_text'] = $json_output;
    
    return $decoded;
}


    /**
     * Parse API responses
     */
    private function parse_response($response) {
       $error_message = ''; // Initialize error message
       $error_data = array(); // Initialize error data
        if (is_wp_error($response)) {
            $error_message = 'OpenAI API Error: ' . $response->get_error_message();
           $error_data = array(
               'error_code'    => $response->get_error_code(),
               'error_message' => $response->get_error_message(),
           );
           $this->plugin->log_error( $error_message, $error_data ); // Log detailed error info
           return $response;
        }
 
        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
 
        if ($status !== 200) {
           $error_message = "API request failed with status $status";
           $this->plugin->log_error( $error_message, array( 'status_code' => $status, 'body' => $body ) );
            return new WP_Error('api_error', "API request failed with status $status");
        }

        return json_decode($body, true);
    }
}