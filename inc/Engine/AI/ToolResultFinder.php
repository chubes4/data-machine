<?php

namespace DataMachine\Engine\AI;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Universal utility for finding AI tool execution results in data packets.
 *
 * Part of the engine infrastructure, providing reusable data packet interpretation
 * for all step types that participate in AI tool calling.
 *
 * @package DataMachine\Engine\AI
 */
class ToolResultFinder {

    /**
     * Find AI tool execution result by exact handler match.
     *
     * Searches data packet for tool_result or ai_handler_complete entries
     * matching the specified handler slug.
     *
     * @param array $data Data packet array from pipeline execution
     * @param string $handler Handler slug to match
     * @return array|null Tool result entry or null if no match found
     */
    public static function findHandlerResult(array $data, string $handler): ?array {
        foreach ($data as $entry) {
            $entry_type = $entry['type'] ?? '';

            if (in_array($entry_type, ['tool_result', 'ai_handler_complete'])) {
                $handler_tool = $entry['metadata']['handler_tool'] ?? '';
                if ($handler_tool === $handler) {
                    return $entry;
                }
            }
        }
        return null;
    }
}
