<?php

/**
 * Handles content finalization using unified AI HTTP Client library
 * Supports multiple AI providers for final output processing
 *
 * @link       PLUGIN_URL
 * @since      0.1.0
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/includes/api
 */

namespace DataMachine\Api;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Finalize {

    /**
     * Constructor. No dependencies needed - uses AI HTTP Client from global container.
     */
    public function __construct() {
        // AI HTTP Client is available via global container
    }

    /**
     * Finalize content using unified AI HTTP Client library
     *
     * @since    0.1.0
     * @param    string    $system_prompt            System instructions for finalization
     * @param    string    $user_message             User prompt containing all context
     * @param    string    $process_data_results     The initial processing results
     * @param    string    $fact_check_results       The fact-check results
     * @param    array     $module_job_config        Module configuration array for the job
     * @param    array     $input_metadata           Metadata from the original input data
     * @return   array|WP_Error                       Response data or WP_Error on failure
     */
    public function finalize_response( $system_prompt, $user_message, $process_data_results, $fact_check_results, array $module_job_config, array $input_metadata = [] ) {
        // Get AI HTTP Client from global container
        global $data_machine_container;
        $ai_http_client = $data_machine_container['ai_http_client'] ?? null;
        
        if (!$ai_http_client) {
            return new \WP_Error('ai_client_unavailable', 'AI HTTP Client not available in container');
        }

        // Build messages array
        $messages = [];
        
        if (!empty($system_prompt)) {
            $messages[] = [
                'role' => 'system',
                'content' => $system_prompt
            ];
        }
        
        $messages[] = [
            'role' => 'user',
            'content' => $user_message
        ];

        // Send step-aware request using AI HTTP Client library
        $response = $ai_http_client->send_step_request('finalize', [
            'messages' => $messages
        ]);

        // Handle response
        if (!$response['success']) {
            return new \WP_Error(
                'ai_api_error', 
                'AI API error: ' . ($response['error'] ?? 'Unknown error'),
                $response
            );
        }

        $content = $response['data']['content'] ?? '';
        
        if (empty($content)) {
            return new \WP_Error(
                'ai_response_empty', 
                'AI response was empty or invalid',
                $response
            );
        }

        // Clean up potential markdown formatting from AI response
        $final_output = preg_replace('/^```json\s*/i', '', $content);
        $final_output = preg_replace('/```$/i', '', $final_output);
        $final_output = trim($final_output);

        return [
            'status' => 'success',
            'final_output' => $final_output
        ];
    }
}
