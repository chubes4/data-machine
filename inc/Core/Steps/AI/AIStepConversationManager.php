<?php
/**
 * AI Step Conversation Manager
 * 
 * Centralized conversation state and message formatting for AI steps.
 *
 * @package DataMachine\Core\Steps\AI
 */

namespace DataMachine\Core\Steps\AI;

defined('ABSPATH') || exit;

class AIStepConversationManager {

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

    public static function buildConversationMessage(string $role, string $content): array {
        return [
            'role' => $role,
            'content' => $content
        ];
    }

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

    public static function generateFailureMessage(string $tool_name, string $error_message): string {
        $tool_display = ucwords(str_replace('_', ' ', $tool_name));
        return "TOOL FAILED: {$tool_display} execution failed - {$error_message}. Please review the error and adjust your approach if needed.";
    }

    public static function logConversationAction(string $action, array $context = []): void {
        do_action('dm_log', 'debug', "ConversationManager: {$action}", $context);
    }
}