<?php
/**
 * Configure Flow Steps Tool
 *
 * Configures handler settings or AI user messages on flow steps.
 * Supports both single-step and bulk pipeline-scoped operations.
 * Delegates to FlowStepAbilities for core logic.
 *
 * @package DataMachine\Api\Chat\Tools
 * @since 0.4.2
 */

namespace DataMachine\Api\Chat\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DataMachine\Abilities\FlowStepAbilities;
use DataMachine\Abilities\HandlerAbilities;
use DataMachine\Engine\AI\Tools\ToolRegistrationTrait;

class ConfigureFlowSteps {
	use ToolRegistrationTrait;

	public function __construct() {
		$this->registerTool( 'chat', 'configure_flow_steps', array( $this, 'getToolDefinition' ) );
	}

	/**
	 * Get tool definition.
	 * Called lazily when tool is first accessed to ensure translations are loaded.
	 *
	 * @return array Tool definition array
	 */
	public function getToolDefinition(): array {
		$handler_docs = HandlerDocumentation::buildAllHandlersSections();

		$description = 'Configure flow steps with handlers or AI user messages. Supports single-step or bulk operations.' . "\n\n"
			. 'MODES:' . "\n"
			. '- Single: Provide flow_step_id to configure one step' . "\n"
			. '- Bulk: Provide pipeline_id + filters to configure multiple flows at once' . "\n\n"
			. 'HANDLER SWITCHING:' . "\n"
			. '- Use target_handler_slug to switch handlers' . "\n"
			. '- field_map maps old fields to new fields (e.g. {"endpoint_url": "source_url"})' . "\n"
			. '- Fields with matching names auto-map without explicit field_map' . "\n\n"
			. 'PER-FLOW CONFIG (bulk mode):' . "\n"
			. '- flow_configs: [{flow_id: 9, handler_config: {source_url: "..."}}]' . "\n"
			. '- Per-flow config merges with shared handler_config (per-flow takes precedence)' . "\n\n"
			. 'BEFORE CONFIGURING:' . "\n"
			. '- Query existing flows to learn established patterns' . "\n"
			. '- Only use handler_config fields documented below - unknown fields are rejected' . "\n\n"
			. $handler_docs;

		return array(
			'class'       => self::class,
			'method'      => 'handle_tool_call',
			'description' => $description,
			'parameters'  => array(
				'flow_step_id'        => array(
					'type'        => 'string',
					'required'    => false,
					'description' => 'Flow step ID for single-step mode (format: {pipeline_step_id}_{flow_id})',
				),
				'pipeline_id'         => array(
					'type'        => 'integer',
					'required'    => false,
					'description' => 'Pipeline ID for bulk mode - applies to all matching steps across all flows',
				),
				'step_type'           => array(
					'type'        => 'string',
					'required'    => false,
					'description' => 'Filter by step type (fetch, publish, update, ai) - required for bulk mode unless handler_slug provided',
				),
				'handler_slug'        => array(
					'type'        => 'string',
					'required'    => false,
					'description' => 'Handler slug to set (single mode) or filter by existing handler (bulk mode)',
				),
				'target_handler_slug' => array(
					'type'        => 'string',
					'required'    => false,
					'description' => 'Handler to switch TO. When provided, handler_slug filters existing handlers (bulk) and target_handler_slug sets the new handler.',
				),
				'field_map'           => array(
					'type'        => 'object',
					'required'    => false,
					'description' => 'Field mappings when switching handlers, e.g. {"endpoint_url": "source_url"}. Fields with matching names auto-map by default.',
				),
				'handler_config'      => array(
					'type'        => 'object',
					'required'    => false,
					'description' => 'Handler-specific configuration to merge into existing config',
				),
				'flow_configs'        => array(
					'type'        => 'array',
					'required'    => false,
					'description' => 'Per-flow configurations for bulk mode. Array of {flow_id: int, handler_config: object}. Merged with shared handler_config (per-flow takes precedence).',
				),
				'user_message'        => array(
					'type'        => 'string',
					'required'    => false,
					'description' => 'User message/prompt for AI steps',
				),
			),
		);
	}

	public function handle_tool_call( array $parameters, array $tool_def = array() ): array {
		$flow_step_id        = $parameters['flow_step_id'] ?? null;
		$pipeline_id         = isset( $parameters['pipeline_id'] ) ? (int) $parameters['pipeline_id'] : null;
		$step_type           = $parameters['step_type'] ?? null;
		$handler_slug        = $parameters['handler_slug'] ?? null;
		$target_handler_slug = $parameters['target_handler_slug'] ?? null;
		$field_map           = $parameters['field_map'] ?? array();
		$handler_config      = $parameters['handler_config'] ?? array();
		$flow_configs        = $parameters['flow_configs'] ?? array();
		$user_message        = $parameters['user_message'] ?? null;

		// Validation: One of flow_step_id OR pipeline_id required
		if ( empty( $flow_step_id ) && empty( $pipeline_id ) ) {
			return array(
				'success'   => false,
				'error'     => 'Either flow_step_id (single mode) or pipeline_id (bulk mode) is required',
				'tool_name' => 'configure_flow_steps',
			);
		}

		// Validation: target_handler_slug requires valid handler
		if ( ! empty( $target_handler_slug ) ) {
			$handler_abilities = new HandlerAbilities();
			if ( ! $handler_abilities->handlerExists( $target_handler_slug ) ) {
				return array(
					'success'   => false,
					'error'     => "Target handler '{$target_handler_slug}' not found",
					'tool_name' => 'configure_flow_steps',
				);
			}
		}

		// Route to appropriate handler
		if ( ! empty( $flow_step_id ) ) {
			return $this->handleSingleMode( $flow_step_id, $handler_slug, $target_handler_slug, $field_map, $handler_config, $user_message );
		}

		return $this->handleBulkMode( $pipeline_id, $step_type, $handler_slug, $target_handler_slug, $field_map, $handler_config, $flow_configs, $user_message );
	}

	/**
	 * Handle single flow step configuration.
	 */
	private function handleSingleMode(
		string $flow_step_id,
		?string $handler_slug,
		?string $target_handler_slug,
		array $field_map,
		array $handler_config,
		?string $user_message
	): array {
		$abilities = new FlowStepAbilities();

		$effective_slug = $target_handler_slug ?? $handler_slug;

		$input = array( 'flow_step_id' => $flow_step_id );

		if ( ! empty( $effective_slug ) ) {
			$input['handler_slug'] = $effective_slug;
		}

		if ( ! empty( $handler_config ) ) {
			$input['handler_config'] = $handler_config;
		}

		if ( ! empty( $user_message ) ) {
			$input['user_message'] = $user_message;
		}

		$result = $abilities->executeUpdateFlowStep( $input );

		$result['tool_name'] = 'configure_flow_steps';

		if ( $result['success'] ) {
			$result['data'] = array(
				'flow_step_id' => $flow_step_id,
				'message'      => $result['message'] ?? 'Flow step configured successfully.',
			);
			if ( ! empty( $effective_slug ) ) {
				$result['data']['handler_slug']    = $effective_slug;
				$result['data']['handler_updated'] = true;
			}
			if ( ! empty( $user_message ) ) {
				$result['data']['user_message_updated'] = true;
			}
			unset( $result['message'] );
		}

		return $result;
	}

	/**
	 * Handle bulk pipeline-scoped configuration.
	 */
	private function handleBulkMode(
		int $pipeline_id,
		?string $step_type,
		?string $handler_slug,
		?string $target_handler_slug,
		array $field_map,
		array $handler_config,
		array $flow_configs,
		?string $user_message
	): array {
		$abilities = new FlowStepAbilities();

		$input = array( 'pipeline_id' => $pipeline_id );

		if ( ! empty( $step_type ) ) {
			$input['step_type'] = $step_type;
		}

		if ( ! empty( $handler_slug ) ) {
			$input['handler_slug'] = $handler_slug;
		}

		if ( ! empty( $target_handler_slug ) ) {
			$input['target_handler_slug'] = $target_handler_slug;
		}

		if ( ! empty( $field_map ) ) {
			$input['field_map'] = $field_map;
		}

		if ( ! empty( $handler_config ) ) {
			$input['handler_config'] = $handler_config;
		}

		if ( ! empty( $flow_configs ) ) {
			$input['flow_configs'] = $flow_configs;
		}

		if ( ! empty( $user_message ) ) {
			$input['user_message'] = $user_message;
		}

		$result = $abilities->executeConfigureFlowSteps( $input );

		$result['tool_name'] = 'configure_flow_steps';

		if ( $result['success'] ) {
			$result['data'] = array(
				'pipeline_id'    => $result['pipeline_id'],
				'flows_updated'  => $result['flows_updated'],
				'steps_modified' => $result['steps_modified'],
				'details'        => $result['updated_steps'] ?? array(),
				'message'        => $result['message'],
			);

			if ( ! empty( $result['errors'] ) ) {
				$result['data']['errors'] = $result['errors'];
			}

			if ( ! empty( $result['skipped'] ) ) {
				$result['data']['skipped'] = $result['skipped'];
			}

			unset( $result['pipeline_id'], $result['flows_updated'], $result['steps_modified'], $result['updated_steps'], $result['message'], $result['errors'], $result['skipped'] );
		}

		return $result;
	}
}
