<?php
/**
 * AI conversation state management with turn tracking, duplicate detection, and temporal context.
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

    /**
     * Synchronizes data packet content in conversation messages with real-time updates.
     */"
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
        
        // Only expose raw tool result payloads to the model for non-handler tools.
        if (!$is_handler_tool && !empty($tool_result['data'])) {
            $success_message .= "\n\n" . json_encode($tool_result['data']);
        }
        
        return self::buildConversationMessage('user', $success_message);
    }

    public static function generateFailureMessage(string $tool_name, string $error_message): string {
        $tool_display = ucwords(str_replace('_', ' ', $tool_name));
        return "TOOL FAILED: {$tool_display} execution failed - {$error_message}. Please review the error and adjust your approach if needed.";
    }

    public static function logConversationAction(string $action, array $context = []): void {
        // Reserved for critical debugging only
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