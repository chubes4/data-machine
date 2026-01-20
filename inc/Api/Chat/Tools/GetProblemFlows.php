<?php
/**
 * Get Problem Flows Tool
 *
 * Identifies flows with issues that may need attention:
 * - Consecutive failures (something is broken)
 * - Consecutive no-items (source is slow/exhausted, consider lowering interval)
 *
 * Uses the problem_flow_threshold setting by default.
 * Delegates to JobAbilities for core logic.
 *
 * @package DataMachine\Api\Chat\Tools
 */

namespace DataMachine\Api\Chat\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DataMachine\Abilities\JobAbilities;
use DataMachine\Core\PluginSettings;
use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;

class GetProblemFlows {
	use ToolRegistrationTrait;

	private ?JobAbilities $abilities = null;

	public function __construct() {
		$this->registerTool( 'chat', 'get_problem_flows', array( $this, 'getToolDefinition' ) );
	}

	/**
	 * Get tool definition.
	 *
	 * @return array Tool definition array
	 */
	public function getToolDefinition(): array {
		$default_threshold = PluginSettings::get( 'problem_flow_threshold', 3 );

		return array(
			'class'       => self::class,
			'method'      => 'handle_tool_call',
			'description' => "Identify flows with issues: consecutive failures (broken) or consecutive no-items runs (source exhausted). Default threshold: {$default_threshold}.",
			'parameters'  => array(
				'threshold' => array(
					'type'        => 'integer',
					'required'    => false,
					'description' => "Minimum consecutive count to report (default: {$default_threshold} from settings)",
				),
			),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $parameters Tool call parameters
	 * @param array $tool_def Tool definition
	 * @return array Tool execution result
	 */
	public function handle_tool_call( array $parameters, array $tool_def = array() ): array {
		if ( null === $this->abilities ) {
			$this->abilities = new JobAbilities();
		}

		$input = array(
			'threshold' => $parameters['threshold'] ?? null,
		);

		$result = $this->abilities->executeGetProblemFlows( $input );

		if ( ! $result['success'] ) {
			return array(
				'success'   => false,
				'error'     => $result['error'] ?? 'Failed to get problem flows',
				'tool_name' => 'get_problem_flows',
			);
		}

		return array(
			'success'   => true,
			'data'      => array(
				'problem_flows' => array_merge( $result['failing'], $result['idle'] ),
				'total'         => $result['count'],
				'failing_count' => count( $result['failing'] ),
				'idle_count'    => count( $result['idle'] ),
				'threshold'     => $result['threshold'],
				'message'       => $result['message'],
			),
			'tool_name' => 'get_problem_flows',
		);
	}
}
