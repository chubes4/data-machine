<?php
/**
 * AI Conversation State Management
 * 
 * Centralized conversation history building, storage, and formatting for AI models.
 * Handles all conversation state management logic extracted from AIStep.php.
 *
 * @package DataMachine\Core\Steps\AI
 * @author Chris Huber <https://chubes.net>
 */

namespace DataMachine\Core\Steps\AI;

defined('ABSPATH') || exit;

/**
 * AI Conversation State Manager
 * 
 * Single responsibility: Manage conversation history and tool result formatting
 * for optimal AI model consumption and debugging.
 */
class AIConversationState {

    /**
     * Build conversation history from data packets with initial context preservation
     * 
     * Extracts conversation turns from accumulated data packets and preserves 
     * initial conversation context (system messages + user prompt) to prevent
     * AI from losing task awareness in multi-turn conversations.
     * 
     * @param array $data Cumulative data packet array
     * @param array $initial_messages Initial conversation context to preserve (system + user messages)
     * @return array Complete conversation messages array with preserved context
     */
    public static function buildFromDataPackets(array $data, array $initial_messages = []): array {
        $tool_result_messages = [];
        
        // Process data packets in reverse order (oldest to newest) 
        // Look for tool_result entries which contain formatted AI conversation messages
        $data_reversed = array_reverse($data);
        
        foreach ($data_reversed as $packet) {
            if ($packet['type'] === 'tool_result') {
                // Tool results are stored as formatted content ready for AI consumption
                $tool_result_messages[] = [
                    'role' => 'user',
                    'content' => $packet['content']['body'] ?? ''
                ];
            } elseif ($packet['type'] === 'user_message') {
                // Include original user message in conversation rebuilding
                $tool_result_messages[] = [
                    'role' => 'user',
                    'content' => $packet['content']['body'] ?? ''
                ];
            } elseif ($packet['type'] === 'ai_response') {
                // Include AI responses in conversation rebuilding (claude.json pattern)
                $tool_result_messages[] = [
                    'role' => 'assistant',
                    'content' => $packet['content']['body'] ?? ''
                ];
            }
        }
        
        // Merge initial context with tool results to preserve task awareness
        // Order: system messages → user prompt → tool results (chronological)
        $conversation = array_merge($initial_messages, $tool_result_messages);
        
        do_action('dm_log', 'info', 'AI Conversation: Built with context preservation', [
            'initial_context_messages' => count($initial_messages),
            'tool_result_messages' => count($tool_result_messages),
            'total_messages' => count($conversation),
            'data_packets_processed' => count($data_reversed)
        ]);
        
        return $conversation;
    }



    /**
     * Format tool result for optimal AI consumption
     * 
     * Converts tool result data into human-readable format instead of raw JSON
     * for better AI model understanding and processing.
     *
     * @param array $tool_result Tool result data
     * @return string Formatted tool result content
     */
    public static function formatToolResultForAI(array $tool_result): string {
        $tool_name = $tool_result['tool_name'] ?? 'unknown_tool';
        $tool_data = $tool_result['data'] ?? [];
        $parameters = $tool_result['parameters'] ?? [];

        // Handle different tool types with specific formatting
        switch ($tool_name) {
            case 'local_search':
                return self::formatLocalSearchResult($tool_data, $parameters);
            
            case 'google_search':
                return self::formatGoogleSearchResult($tool_data, $parameters);
            
            case 'wordpress_publish':
                return self::formatWordPressPublishResult($tool_data, $parameters);
            
            default:
                // Generic formatting for unknown tools
                return self::formatGenericToolResult($tool_name, $tool_data, $parameters);
        }
    }

    /**
     * Format local search results for AI consumption
     *
     * @param array $tool_data Tool result data
     * @param array $parameters Original search parameters
     * @return string Formatted search results
     */
    private static function formatLocalSearchResult(array $tool_data, array $parameters): string {
        $query = $parameters['query'] ?? 'unknown';
        $results_count = $tool_data['results_count'] ?? 0;
        $total_available = $tool_data['total_available'] ?? 0;
        $results = $tool_data['results'] ?? [];

        if ($results_count === 0) {
            return "LOCAL SEARCH RESULTS for '{$query}': No results found.";
        }

        $content = "LOCAL SEARCH RESULTS for '{$query}': Found {$results_count} results";
        if ($total_available > $results_count) {
            $content .= " (showing {$results_count} of {$total_available} total)";
        }
        $content .= "\n\n";

        foreach ($results as $index => $result) {
            $title = $result['title'] ?? 'Untitled';
            $link = $result['link'] ?? '';
            $excerpt = $result['excerpt'] ?? '';
            $post_type = $result['post_type'] ?? 'post';
            
            $content .= ($index + 1) . ". **{$title}** ({$post_type})\n";
            $content .= "   URL: {$link}\n";
            if (!empty($excerpt)) {
                $content .= "   Summary: {$excerpt}\n";
            }
            $content .= "\n";
        }

        return trim($content);
    }

    /**
     * Format Google search results for AI consumption
     *
     * @param array $tool_data Tool result data
     * @param array $parameters Original search parameters
     * @return string Formatted search results
     */
    private static function formatGoogleSearchResult(array $tool_data, array $parameters): string {
        $query = $parameters['query'] ?? 'unknown';
        $results = $tool_data['results'] ?? [];

        if (empty($results)) {
            return "GOOGLE SEARCH RESULTS for '{$query}': No results found.";
        }

        $content = "GOOGLE SEARCH RESULTS for '{$query}': Found " . count($results) . " results\n\n";

        foreach ($results as $index => $result) {
            $title = $result['title'] ?? 'Untitled';
            $link = $result['link'] ?? '';
            $snippet = $result['snippet'] ?? '';
            
            $content .= ($index + 1) . ". **{$title}**\n";
            $content .= "   URL: {$link}\n";
            if (!empty($snippet)) {
                $content .= "   Summary: {$snippet}\n";
            }
            $content .= "\n";
        }

        return trim($content);
    }

    /**
     * Format WordPress publish results for AI consumption
     *
     * @param array $tool_data Tool result data
     * @param array $parameters Original publish parameters
     * @return string Formatted publish result
     */
    private static function formatWordPressPublishResult(array $tool_data, array $parameters): string {
        $post_id = $tool_data['post_id'] ?? null;
        $post_url = $tool_data['post_url'] ?? '';
        $title = $parameters['title'] ?? 'Untitled';

        if (!$post_id) {
            return "WORDPRESS PUBLISH RESULT: Failed to publish '{$title}'.";
        }

        $content = "WORDPRESS PUBLISH RESULT: Successfully published '{$title}' to WordPress\n";
        $content .= "Post ID: {$post_id}\n";
        $content .= "URL: {$post_url}";

        // Include taxonomy results if available
        $taxonomy_results = $tool_data['taxonomy_results'] ?? [];
        if (!empty($taxonomy_results)) {
            $content .= "\n\nTaxonomies assigned:";
            foreach ($taxonomy_results as $taxonomy => $result) {
                if ($result['success'] ?? false) {
                    $content .= "\n- {$taxonomy}: " . ($result['term_count'] ?? 0) . " terms";
                }
            }
        }

        return $content;
    }

    /**
     * Format generic tool results for AI consumption
     *
     * @param string $tool_name Name of the tool
     * @param array $tool_data Tool result data
     * @param array $parameters Original parameters
     * @return string Formatted tool result
     */
    private static function formatGenericToolResult(string $tool_name, array $tool_data, array $parameters): string {
        $tool_display_name = strtoupper(str_replace('_', ' ', $tool_name));
        $content = "{$tool_display_name} RESULT: Tool executed";
        
        // Add basic parameter info if available
        if (!empty($parameters)) {
            $param_count = count($parameters);
            $content .= " with {$param_count} parameter(s)";
        }
        
        $content .= "\n\nResult data:\n";
        $content .= json_encode($tool_data, JSON_PRETTY_PRINT);
        
        return $content;
    }
}