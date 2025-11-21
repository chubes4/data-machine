<?php
/**
 * Prompt Builder - Unified Directive Management for AI Requests
 *
 * Centralizes directive injection for AI requests with priority-based ordering.
 * Replaces separate global/agent filter application with a structured builder pattern.
 *
 * @package DataMachine\Engine\AI
 * @since 0.2.5
 */

namespace DataMachine\Engine\AI;

defined('ABSPATH') || exit;

/**
 * Prompt Builder Class
 *
 * Manages directive registration and application for building AI requests.
 * Ensures directives are applied in correct priority order for consistent prompt structure.
 */
class PromptBuilder {

	/**
	 * Registered directives
	 *
	 * @var array Array of directive configurations
	 */
	private array $directives = [];

	/**
	 * Initial messages
	 *
	 * @var array
	 */
	private array $messages = [];

	/**
	 * Available tools
	 *
	 * @var array
	 */
	private array $tools = [];

	/**
	 * Set initial messages
	 *
	 * @param array $messages Initial conversation messages
	 * @return self
	 */
	public function setMessages(array $messages): self {
		$this->messages = $messages;
		return $this;
	}

	/**
	 * Set available tools
	 *
	 * @param array $tools Available tools array
	 * @return self
	 */
	public function setTools(array $tools): self {
		$this->tools = $tools;
		return $this;
	}

	/**
	 * Add a directive to the builder
	 *
	 * @param string|object $directive Directive class name or instance
	 * @param int $priority Priority for ordering (lower = applied first)
	 * @param array $agentTypes Agent types this directive applies to ('all' for global)
	 * @return self
	 */
	public function addDirective($directive, int $priority, array $agentTypes = ['all']): self {
		$this->directives[] = [
			'directive' => $directive,
			'priority' => $priority,
			'agentTypes' => $agentTypes
		];
		return $this;
	}

	/**
	 * Build the final AI request with directives applied
	 *
	 * @param string $agentType Agent type ('pipeline', 'chat', etc.)
	 * @param string $provider AI provider name
	 * @param array $payload Request payload
	 * @return array Final request array with messages and tools
	 */
	public function build(string $agentType, string $provider, array $payload = []): array {
		// Sort directives by priority (ascending - lower priority applied first)
		usort($this->directives, function($a, $b) {
			return $a['priority'] <=> $b['priority'];
		});

		// Debug: Log directive order after sorting
		$sorted_priorities = array_column($this->directives, 'priority');
		do_action('datamachine_log', 'debug', 'PromptBuilder: Directives sorted', [
			'priorities' => $sorted_priorities,
			'expected_order' => 'ascending (low to high)'
		]);

		// Initialize request
		$request = [
			'messages' => $this->messages,
			'tools' => $this->tools
		];

		// Apply each applicable directive
		foreach ($this->directives as $index => $directiveConfig) {
			$directive_class = is_string($directiveConfig['directive']) ? $directiveConfig['directive'] : get_class($directiveConfig['directive']);
			do_action('datamachine_log', 'debug', 'PromptBuilder: Processing directive', [
				'index' => $index,
				'priority' => $directiveConfig['priority'],
				'class' => $directive_class,
				'agent_type' => $agentType
			]);

			$directive = $directiveConfig['directive'];
			$agentTypes = $directiveConfig['agentTypes'];

			// Check if directive applies to this agent type
			if (in_array('all', $agentTypes) || in_array($agentType, $agentTypes)) {
				$stepId = $payload['step_id'] ?? null;

				// Call directive injection method
				if (is_string($directive) && class_exists($directive)) {
					$request = $directive::inject($request, $provider, $this->tools, $stepId, $payload);
				} elseif (is_object($directive) && method_exists($directive, 'inject')) {
					$request = $directive->inject($request, $provider, $this->tools, $stepId, $payload);
				}
			}
		}

		return $request;
	}
}