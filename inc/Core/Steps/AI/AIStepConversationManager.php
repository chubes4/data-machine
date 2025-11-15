<?php
/**
 * Pipeline-specific AI conversation management with duplicate detection and data packet updates.
 *
 * Extends universal ConversationManager with pipeline-specific functionality.
 *
 * @package DataMachine\Core\Steps\AI
 */

namespace DataMachine\Core\Steps\AI;

use DataMachine\Engine\AI\ConversationManager;

defined('ABSPATH') || exit;

class AIStepConversationManager {

    /**
     * Generate success or failure message from tool result.
     *
     * @param string $tool_name Tool identifier
     * @param array $tool_result Tool execution result
     * @param array $tool_parameters Original tool parameters
     * @return string Human-readable success/failure message
     */
    public static function generateSuccessMessage(string $tool_name, array $tool_result, array $tool_parameters): string {
        return ConversationManager::generateSuccessMessage($tool_name, $tool_result, $tool_parameters);
    }

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
     * @param string $role Role identifier (user, assistant, system)
     * @param string $content Message content
     * @return array Message array with role and content
     */
    public static function buildConversationMessage(string $role, string $content): array {
        return ConversationManager::buildConversationMessage($role, $content);
    }

    /**
     * Format tool call as conversation message with turn tracking.
     *
     * @param string $tool_name Tool identifier
     * @param array $tool_parameters Tool call parameters
     * @param int $turn_count Current conversation turn
     * @return array Formatted assistant message
     */
    public static function formatToolCallMessage(string $tool_name, array $tool_parameters, int $turn_count): array {
        return ConversationManager::formatToolCallMessage($tool_name, $tool_parameters, $turn_count);
    }

    /**
     * Format tool execution result as conversation message.
     *
     * @param string $tool_name Tool identifier
     * @param array $tool_result Tool execution result
     * @param array $tool_parameters Original tool parameters
     * @param bool $is_handler_tool Whether tool is handler-specific
     * @param int $turn_count Current conversation turn
     * @return array Formatted user message
     */
    public static function formatToolResultMessage(string $tool_name, array $tool_result, array $tool_parameters, bool $is_handler_tool = false, int $turn_count = 0): array {
        return ConversationManager::formatToolResultMessage($tool_name, $tool_result, $tool_parameters, $is_handler_tool, $turn_count);
    }

    /**
     * Generate standardized failure message.
     *
     * @param string $tool_name Tool identifier
     * @param string $error_message Error details
     * @return string Formatted failure message
     */
    public static function generateFailureMessage(string $tool_name, string $error_message): string {
        return ConversationManager::generateFailureMessage($tool_name, $error_message);
    }

    public static function logConversationAction(string $action, array $context = []): void {
    }

    public static function validateToolCall(string $tool_name, array $tool_parameters, array $conversation_messages): array {
        if (empty($conversation_messages)) {
            return ['is_duplicate' => false, 'message' => ''];
        }

        $previous_tool_call = null;
        for ($i = count($conversation_messages) - 1; $i >= 0; $i--) {
            $message = $conversation_messages[$i];

            if ($message['role'] === 'assistant' &&
                isset($message['content']) &&
                is_string($message['content']) &&
                strpos($message['content'], 'AI ACTION') === 0) {

                $previous_tool_call = self::extractToolCallFromMessage($message);
                break;
            }
        }

        if (!$previous_tool_call) {
            return ['is_duplicate' => false, 'message' => ''];
        }

        $is_duplicate = ($previous_tool_call['tool_name'] === $tool_name) &&
                       ($previous_tool_call['parameters'] === $tool_parameters);

        if ($is_duplicate) {

            $correction_message = "You just called the {$tool_name} tool with the exact same parameters as your previous action. Please try a different approach or use different parameters instead.";
            return ['is_duplicate' => true, 'message' => $correction_message];
        }

        return ['is_duplicate' => false, 'message' => ''];
    }

    public static function extractToolCallFromMessage(array $message): ?array {
        if ($message['role'] !== 'assistant' || !isset($message['content'])) {
            return null;
        }

        $content = $message['content'];

        if (!preg_match('/AI ACTION \(Turn \d+\): Executing (.+?)(?: with parameters: (.+))?$/', $content, $matches)) {
            return null;
        }

        $tool_display_name = trim($matches[1]);
        $tool_name = strtolower(str_replace(' ', '_', $tool_display_name));

        $parameters = [];
        if (isset($matches[2]) && !empty($matches[2])) {
            $params_string = $matches[2];

            $param_pairs = explode(', ', $params_string);
            foreach ($param_pairs as $pair) {
                if (strpos($pair, ': ') !== false) {
                    list($key, $value) = explode(': ', $pair, 2);
                    $key = trim($key);
                    $value = trim($value);

                    if (substr($value, -3) === '...') {
                        $value = substr($value, 0, -3) . '_truncated_' . time();
                    }

                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $parameters[$key] = $decoded;
                    } else {
                        $parameters[$key] = $value;
                    }
                }
            }
        }

        return [
            'tool_name' => $tool_name,
            'parameters' => $parameters
        ];
    }

    public static function generateDuplicateToolCallMessage(string $tool_name): array {
        $tool_display = ucwords(str_replace('_', ' ', $tool_name));
        $message = "You just called the {$tool_display} tool with the exact same parameters as your previous action. Please try a different approach or use different parameters instead.";

        return self::buildConversationMessage('user', $message);
    }
}