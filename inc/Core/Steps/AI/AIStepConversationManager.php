<?php
/**
 * AI Conversation State Manager - Centralized conversation management for multi-turn AI workflows.
 *
 * Handles chronological message ordering, turn tracking, temporal context preservation,
 * and standardized tool result formatting across AI agent conversations.
 *
 * @package DataMachine
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\AI;

defined('ABSPATH') || exit;

class AIStepConversationManager {

    /**
     * Generate contextual success messages for tool execution results.
     *
     * @param string $tool_name Tool identifier
     * @param array $tool_result Tool execution result containing 'success' and 'data' keys
     * @param array $tool_parameters Tool parameters passed to tool
     * @return string Formatted success message with error handling
     */
    public static function generateSuccessMessage(string $tool_name, array $tool_result, array $tool_parameters): string {
        $success = $tool_result['success'] ?? false;
        $data = $tool_result['data'] ?? [];
        
        if (!$success) {
            $error = $tool_result['error'] ?? 'Unknown error occurred';
            return "TOOL FAILED: {$tool_name} execution failed - {$error}";
        }
        
        $default_message = "SUCCESS: " . ucwords(str_replace('_', ' ', $tool_name)) . " completed successfully. The requested operation has been finished as requested.";
        
        return apply_filters('dm_tool_success_message', $default_message, $tool_name, $tool_result, $tool_parameters);
    }

    /**
     * Update data packet messages in conversation for multi-turn context preservation.
     *
     * @param array $conversation_messages Existing conversation messages
     * @param array $data Current data packet array
     * @return array Updated conversation messages
     */
    public static function updateDataPacketMessages(array $conversation_messages, array $data): array {
        if (empty($conversation_messages) || empty($data)) {
            return $conversation_messages;
        }

        foreach ($conversation_messages as $index => $message) {
            if ($message['role'] === 'user' && 
                isset($message['content']) && 
                is_string($message['content']) &&
                strpos($message['content'], '"data_packets"') !== false) {
                
                $conversation_messages[$index]['content'] = json_encode(
                    ['data_packets' => $data], 
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                );
                break;
            }
        }

        return $conversation_messages;
    }

    /**
     * Build standardized conversation message structure.
     *
     * @param string $role Message role (user|assistant|system)
     * @param string $content Message content
     * @return array Conversation message array
     */
    public static function buildConversationMessage(string $role, string $content): array {
        return [
            'role' => $role,
            'content' => $content
        ];
    }

    /**
     * Format AI tool call action record with temporal context.
     *
     * @param string $tool_name Tool identifier
     * @param array $tool_parameters Tool parameters
     * @param int $turn_count Current turn number
     * @return array Formatted tool call message
     */
    public static function formatToolCallMessage(string $tool_name, array $tool_parameters, int $turn_count): array {
        $tool_display = ucwords(str_replace('_', ' ', $tool_name));
        $message = "AI ACTION (Turn {$turn_count}): Executing {$tool_display}";
        
        if (!empty($tool_parameters)) {
            $params_str = [];
            foreach ($tool_parameters as $key => $value) {
                if (is_string($value) && strlen($value) > 50) {
                    $params_str[] = "{$key}: " . substr($value, 0, 50) . "...";
                } else {
                    $params_str[] = "{$key}: " . (is_string($value) ? $value : json_encode($value));
                }
            }
            $message .= " with parameters: " . implode(', ', $params_str);
        }
        
        return self::buildConversationMessage('assistant', $message);
    }

    /**
     * Format tool execution results with temporal context for AI feedback.
     *
     * @param string $tool_name Tool identifier
     * @param array $tool_result Tool execution result
     * @param array $tool_parameters Tool parameters
     * @param bool $is_handler_tool Whether this is a handler tool
     * @param int $turn_count Current turn number
     * @return array Formatted tool result message
     */
    public static function formatToolResultMessage(string $tool_name, array $tool_result, array $tool_parameters, bool $is_handler_tool = false, int $turn_count = 0): array {
        $success_message = self::generateSuccessMessage($tool_name, $tool_result, $tool_parameters);
        
        if ($turn_count > 0) {
            $success_message = "TOOL RESPONSE (Turn {$turn_count}): " . $success_message;
        }
        
        if (!empty($tool_result['data'])) {
            $success_message .= "\n\n" . json_encode($tool_result['data']);
        }
        
        return self::buildConversationMessage('user', $success_message);
    }

    /**
     * Generate standardized failure messages for tool execution errors.
     *
     * @param string $tool_name Tool identifier
     * @param string $error_message Error message
     * @return string Formatted failure message
     */
    public static function generateFailureMessage(string $tool_name, string $error_message): string {
        $tool_display = ucwords(str_replace('_', ' ', $tool_name));
        return "TOOL FAILED: {$tool_display} execution failed - {$error_message}. Please review the error and adjust your approach if needed.";
    }

    /**
     * Log conversation management actions for debugging.
     *
     * @param string $action Action being logged
     * @param array $context Additional context data
     */
    public static function logConversationAction(string $action, array $context = []): void {
        do_action('dm_log', 'debug', "ConversationManager: {$action}", $context);
    }
}