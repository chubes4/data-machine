<?php
/**
 * Universal AI conversation message building utilities.
 *
 * Provides standardized message formatting for all AI agents (pipeline and chat).
 * All methods are static with no state management.
 *
 * @package DataMachine\Engine\AI
 * @since 0.2.1
 */

namespace DataMachine\Engine\AI;

defined('ABSPATH') || exit;

class ConversationManager {

    /**
     * Build standardized conversation message structure.
     *
     * @param string $role Role identifier (user, assistant, system)
     * @param string $content Message content
     * @return array Message array with role and content
     */
    public static function buildConversationMessage(string $role, string $content): array {
        return [
            'role' => $role,
            'content' => $content
        ];
    }

    /**
     * Format tool call as conversation message with turn tracking.
     *
     * @param string $tool_name Tool identifier
     * @param array $tool_parameters Tool call parameters
     * @param int $turn_count Current conversation turn (0 = no turn display)
     * @return array Formatted assistant message
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
     * Format tool execution result as conversation message.
     *
     * @param string $tool_name Tool identifier
     * @param array $tool_result Tool execution result
     * @param array $tool_parameters Original tool parameters
     * @param bool $is_handler_tool Whether tool is handler-specific (affects data inclusion)
     * @param int $turn_count Current conversation turn (0 = no turn display)
     * @return array Formatted user message
     */
    public static function formatToolResultMessage(string $tool_name, array $tool_result, array $tool_parameters, bool $is_handler_tool = false, int $turn_count = 0): array {
        $success_message = self::generateSuccessMessage($tool_name, $tool_result, $tool_parameters);

        if ($turn_count > 0) {
            $success_message = "TOOL RESPONSE (Turn {$turn_count}): " . $success_message;
        }

        if (!$is_handler_tool && !empty($tool_result['data'])) {
            $success_message .= "\n\n" . json_encode($tool_result['data']);
        }

        return self::buildConversationMessage('user', $success_message);
    }

    /**
     * Generate success or failure message from tool result.
     *
     * @param string $tool_name Tool identifier
     * @param array $tool_result Tool execution result
     * @param array $tool_parameters Original tool parameters
     * @return string Human-readable success/failure message
     */
    public static function generateSuccessMessage(string $tool_name, array $tool_result, array $tool_parameters): string {
        $success = $tool_result['success'] ?? false;
        $data = $tool_result['data'] ?? [];

        if (!$success) {
            $error = $tool_result['error'] ?? 'Unknown error occurred';
            return "TOOL FAILED: {$tool_name} execution failed - {$error}";
        }

        $default_message = "SUCCESS: " . ucwords(str_replace('_', ' ', $tool_name)) . " completed successfully. The requested operation has been finished as requested.";

        return apply_filters('datamachine_tool_success_message', $default_message, $tool_name, $tool_result, $tool_parameters);
    }

    /**
     * Generate standardized failure message.
     *
     * @param string $tool_name Tool identifier
     * @param string $error_message Error details
     * @return string Formatted failure message
     */
    public static function generateFailureMessage(string $tool_name, string $error_message): string {
        $tool_display = ucwords(str_replace('_', ' ', $tool_name));
        return "TOOL FAILED: {$tool_display} execution failed - {$error_message}. Please review the error and adjust your approach if needed.";
    }

    /**
     * Validate if a tool call is a duplicate of the previous tool call.
     *
     * @param string $tool_name Tool name to validate
     * @param array $tool_parameters Tool parameters to validate
     * @param array $conversation_messages Conversation history
     * @return array Validation result with is_duplicate and message
     */
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

    /**
     * Extract tool call details from a conversation message.
     *
     * @param array $message Conversation message
     * @return array|null Tool call details or null if not a tool call message
     */
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

    /**
     * Generate a user message for duplicate tool call prevention.
     *
     * @param string $tool_name Tool name that was duplicated
     * @return array Formatted user message
     */
    public static function generateDuplicateToolCallMessage(string $tool_name): array {
        $tool_display = ucwords(str_replace('_', ' ', $tool_name));
        $message = "You just called the {$tool_display} tool with the exact same parameters as your previous action. Please try a different approach or use different parameters instead.";

        return self::buildConversationMessage('user', $message);
    }
}
