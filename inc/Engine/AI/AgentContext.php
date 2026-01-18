<?php
/**
 * Agent Context
 *
 * Runtime tracking of the current agent type executing.
 * Used by the logging system to route logs to the correct file
 * when agent_type is not explicitly passed in log context.
 *
 * @package DataMachine\Engine\AI
 * @since 0.7.2
 */

namespace DataMachine\Engine\AI;

if ( ! defined( 'WPINC' ) ) {
	die;
}

final class AgentContext {

	private static ?string $currentAgentType = null;

	/**
	 * Set the current agent type context.
	 *
	 * @param string $agentType Agent type (use AgentType constants)
	 */
	public static function set( string $agentType ): void {
		self::$currentAgentType = $agentType;
	}

	/**
	 * Get the current agent type context.
	 *
	 * @return string|null Current agent type or null if not set
	 */
	public static function get(): ?string {
		return self::$currentAgentType;
	}

	/**
	 * Clear the current agent type context.
	 */
	public static function clear(): void {
		self::$currentAgentType = null;
	}
}
