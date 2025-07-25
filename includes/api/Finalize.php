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
     * @since    0.1.0 (Refactored to use AI HTTP Client library)
     * @param    string    $system_prompt            System instructions for finalization
     * @param    string    $user_message             User prompt containing all context
     * @param    string    $process_data_results     The initial processing results
     * @param    string    $fact_check_results       The fact-check results
     * @param    array     $module_job_config        Module configuration array for the job
     * @param    array     $input_metadata           Metadata from the original input data
     * @return   array                              Response data with 'status' and 'final_output'
     */
    public function finalize_response( $system_prompt, $user_message, $process_data_results, $fact_check_results, array $module_job_config, array $input_metadata = [] ) {
        // Get AI HTTP Client from global container
        global $data_machine_container;
        $ai_http_client = $data_machine_container['ai_http_client'] ?? null;
        
        if (!$ai_http_client) {
            return [
                'status' => 'error',
                'final_output' => 'AI HTTP Client not available in container'
            ];
        }

        $default_response = [
            'status' => 'error',
            'final_output' => ''
        ];

        try {
            // Build messages array - let library handle complexity
            $messages = [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_message]
            ];

            // Send step-aware request using AI HTTP Client library
            $response = $ai_http_client->send_step_request('finalize', [
                'messages' => $messages
            ]);

            // Use helper method to format response
            return $this->format_response($response);

        } catch (Exception $e) {
            $default_response['final_output'] = 'Finalization Exception: ' . $e->getMessage();
            return $default_response;
        }
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
                'final_output' => 'AI API Error: ' . ($response['error'] ?? 'Unknown error')
            ];
        }

        $content = $response['data']['content'] ?? '';
        
        if (empty($content)) {
            return [
                'status' => 'error',
                'final_output' => 'AI response was empty or invalid'
            ];
        }

        // Clean up potential markdown formatting from AI response
        $final_output = $this->clean_markdown_formatting($content);

        return [
            'status' => 'success',
            'final_output' => $final_output
        ];
    }

    /**
     * Clean markdown formatting from AI response
     *
     * @param string $content Raw AI response content
     * @return string Cleaned content
     */
    private function clean_markdown_formatting($content) {
        $cleaned = preg_replace('/^```json\s*/i', '', $content);
        $cleaned = preg_replace('/```$/i', '', $cleaned);
        return trim($cleaned);
    }
}
