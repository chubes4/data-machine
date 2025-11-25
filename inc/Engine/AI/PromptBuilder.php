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

		// Initialize request
		$request = [
			'messages' => $this->messages,
			'tools' => $this->tools
		];

		// Track applied directives for consolidated logging
		$applied_directives = [];

		// Apply each applicable directive
		foreach ($this->directives as $index => $directiveConfig) {
			$directive = $directiveConfig['directive'];
			$agentTypes = $directiveConfig['agentTypes'];

			// Check if directive applies to this agent type
			if (in_array('all', $agentTypes) || in_array($agentType, $agentTypes)) {
				$stepId = $payload['step_id'] ?? null;
				$directive_class = is_string($directive) ? $directive : get_class($directive);
				$directive_name = substr($directive_class, strrpos($directive_class, '\\') + 1);

				// Call directive injection method
				if (is_string($directive) && class_exists($directive)) {
					$request = $directive::inject($request, $provider, $this->tools, $stepId, $payload);
				} elseif (is_object($directive) && method_exists($directive, 'inject')) {
					$request = $directive->inject($request, $provider, $this->tools, $stepId, $payload);
				}

				// Track applied directive
				$applied_directives[] = [
					'name' => $directive_name,
					'priority' => $directiveConfig['priority']
				];
			}
		}

		// Consolidated log of applied directives
		if (!empty($applied_directives)) {
			$directive_list = array_map(function($dir) {
				return "{$dir['name']} (priority {$dir['priority']})";
			}, $applied_directives);
			
			do_action('datamachine_log', 'debug', 'PromptBuilder: Applied directives', [
				'agent_type' => $agentType,
				'provider' => $provider,
				'directives_applied' => $directive_list,
				'total_directives' => count($applied_directives),
				'total_messages' => count($request['messages'])
			]);
		}

		return $request;
	}
}