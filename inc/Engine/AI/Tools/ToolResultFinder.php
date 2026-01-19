<?php

namespace DataMachine\Engine\AI\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Universal utility for finding AI tool execution results in data packets.
 *
 * Part of the engine infrastructure, providing reusable data packet interpretation
 * for all step types that participate in AI tool calling.
 *
 * @package DataMachine\Engine\AI\Tools
 * @since 0.2.1
 */
class ToolResultFinder {

	/**
	 * Find AI tool execution result by exact handler match.
	 *
	 * Searches data packet for tool_result or ai_handler_complete entries
	 * matching the specified handler slug. Logs error when no match found.
	 *
	 * @param array  $dataPackets Data packet array from pipeline execution
	 * @param string $handler Handler slug to match
	 * @param string $flow_step_id Flow step ID for error logging context
	 * @return array|null Tool result entry or null if no match found
	 */
	public static function findHandlerResult( array $dataPackets, string $handler, string $flow_step_id ): ?array {
		foreach ( $dataPackets as $entry ) {
			$entry_type = $entry['type'] ?? '';

			if ( in_array( $entry_type, array( 'tool_result', 'ai_handler_complete' ), true ) ) {
				$handler_tool = $entry['metadata']['handler_tool'] ?? '';
				if ( $handler_tool === $handler ) {
					return $entry;
				}
			}
		}

		// Log error when not found
		do_action(
			'datamachine_log',
			'error',
			'AI did not execute handler tool',
			array(
				'agent_type'   => 'system',
				'handler'      => $handler,
				'flow_step_id' => $flow_step_id,
			)
		);

		return null;
	}
}
