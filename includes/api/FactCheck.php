<?php

/**
 * Handles fact-checking using unified AI HTTP Client library
 * Supports multiple AI providers with web search capabilities
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

class FactCheck {

    /**
     * Constructor. No dependencies needed - uses AI HTTP Client from global container.
     */
    public function __construct() {
        // AI HTTP Client is available via global container
    }

    /**
     * Fact-check content using unified AI HTTP Client library with web search capabilities
     *
     * @since    0.1.0
     * @param    string    $system_prompt    System instructions for fact-checking
     * @param    string    $user_prompt      User prompt/instructions
     * @param    string    $content_to_check Content data to fact-check
     * @return   array|WP_Error              Response data or WP_Error on failure
     */
    public function fact_check_response( $system_prompt, $user_prompt, $content_to_check ) {
        // Get AI HTTP Client from global container
        global $data_machine_container;
        $ai_http_client = $data_machine_container['ai_http_client'] ?? null;
        
        if (!$ai_http_client) {
            return new \WP_Error('ai_client_unavailable', 'AI HTTP Client not available in container');
        }

        // Construct the user message by combining the module prompt and the content to check
        $user_message = $user_prompt;
        if (!empty($content_to_check)) {
            $user_message .= "\n\nContent to Fact Check:\n" . $content_to_check;
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

        // Define web search tools for fact-checking
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'web_search',
                    'description' => 'Search the web to verify facts and gather current information',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'The search query to verify facts'
                            ]
                        ],
                        'required' => ['query']
                    ]
                ]
            ]
        ];

        // Send step-aware request using AI HTTP Client library
        $response = $ai_http_client->send_step_request('factcheck', [
            'messages' => $messages,
            'tools' => $tools
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

        return [
            'status' => 'success',
            'fact_check_results' => trim($content)
        ];
    }
}
