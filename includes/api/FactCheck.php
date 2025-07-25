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
     * @since    0.1.0 (Refactored to use AI HTTP Client library)
     * @param    string    $system_prompt    System instructions for fact-checking
     * @param    string    $enhanced_prompt  Enhanced prompt from PromptBuilder
     * @param    string    $content_to_check Content data to fact-check
     * @return   array                      Response data with 'status' and 'fact_check_results'
     */
    public function fact_check_response( $system_prompt, $enhanced_prompt, $content_to_check ) {
        // Get AI HTTP Client from global container
        global $data_machine_container;
        $ai_http_client = $data_machine_container['ai_http_client'] ?? null;
        
        if (!$ai_http_client) {
            return [
                'status' => 'error',
                'fact_check_results' => 'AI HTTP Client not available in container'
            ];
        }

        $default_response = [
            'status' => 'error',
            'fact_check_results' => ''
        ];

        try {
            // Simple helper method constructs user message
            $user_message = $this->build_fact_check_message($enhanced_prompt, $content_to_check);

            // Build messages array - let library handle complexity
            $messages = [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_message]
            ];

            // Get web search tools for fact-checking
            $tools = $this->get_web_search_tools();

            // Send step-aware request using AI HTTP Client library
            $response = $ai_http_client->send_step_request('factcheck', [
                'messages' => $messages,
                'tools' => $tools
            ]);

            // Use helper method to format response
            return $this->format_response($response);

        } catch (Exception $e) {
            $default_response['fact_check_results'] = 'Fact-checking Exception: ' . $e->getMessage();
            return $default_response;
        }
    }

    /**
     * Build fact-check user message with content to check
     *
     * @param string $enhanced_prompt Enhanced prompt from PromptBuilder
     * @param string $content_to_check Content data to fact-check
     * @return string Formatted user message
     */
    private function build_fact_check_message($enhanced_prompt, $content_to_check) {
        $user_message = $enhanced_prompt;
        
        if (!empty($content_to_check)) {
            $user_message .= "\n\nContent to Fact Check:\n" . $content_to_check;
        }
        
        return $user_message;
    }

    /**
     * Get web search tools for fact-checking
     *
     * @return array Web search tool definitions
     */
    private function get_web_search_tools() {
        return [
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
                'fact_check_results' => 'AI API Error: ' . ($response['error'] ?? 'Unknown error')
            ];
        }

        $content = $response['data']['content'] ?? '';
        
        if (empty($content)) {
            return [
                'status' => 'error',
                'fact_check_results' => 'AI response was empty or invalid'
            ];
        }

        return [
            'status' => 'success',
            'fact_check_results' => trim($content)
        ];
    }
}
