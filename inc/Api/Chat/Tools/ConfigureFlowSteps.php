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

use DataMachine\Engine\AI\Tools\BaseTool;

class ConfigureFlowSteps extends BaseTool {

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

		$description = 'Configure flow steps with handlers or AI user messages. Supports single-step, bulk, or cross-pipeline operations.' . "\n\n"
			. 'MODES:' . "\n"
			. '- Single: Provide flow_step_id to configure one step' . "\n"
			. '- Bulk (same settings): Provide pipeline_id + filters to configure multiple flows in one pipeline' . "\n"
			. '- Cross-pipeline: Provide updates array to configure multiple flows across different pipelines with different settings' . "\n\n"
			. 'HANDLER SWITCHING:' . "\n"
			. '- Use target_handler_slug to switch handlers' . "\n"
			. '- field_map maps old fields to new fields (e.g. {"endpoint_url": "source_url"})' . "\n"
			. '- Fields with matching names auto-map without explicit field_map' . "\n\n"
			. 'PER-FLOW CONFIG (bulk mode):' . "\n"
			. '- flow_configs: [{flow_id: 9, handler_config: {source_url: "..."}}]' . "\n"
			. '- Per-flow config merges with shared handler_config (per-flow takes precedence)' . "\n\n"
			. 'CROSS-PIPELINE MODE:' . "\n"
			. '- updates: [{flow_id: 9, step_configs: {fetch: {handler_slug: "...", handler_config: {...}}}}]' . "\n"
			. '- shared_config applied first, then per-flow step_configs override' . "\n\n"
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
				'updates'             => array(
					'type'        => 'array',
					'required'    => false,
					'description' => 'Cross-pipeline mode: configure multiple flows with different settings. Each item: {flow_id, step_configs (keyed by step_type: {handler_slug?, handler_config?, user_message?})}',
				),
				'shared_config'       => array(
					'type'        => 'object',
					'required'    => false,
					'description' => 'Shared step config for cross-pipeline mode (keyed by step_type). Per-flow step_configs override these.',
				),
				'validate_only'       => array(
					'type'        => 'boolean',
					'required'    => false,
					'description' => 'Dry-run mode: validate configuration without executing. Returns what would be updated.',
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
		$updates             = $parameters['updates'] ?? array();
		$shared_config       = $parameters['shared_config'] ?? array();
		$validate_only       = ! empty( $parameters['validate_only'] );

		// Check for cross-pipeline mode
		if ( ! empty( $updates ) && is_array( $updates ) ) {
			return $this->handleCrossPipelineMode( $updates, $shared_config, $validate_only );
		}

		// Validation: One of flow_step_id OR pipeline_id required
		if ( empty( $flow_step_id ) && empty( $pipeline_id ) ) {
			return array(
				'success'   => false,
				'error'     => 'Either flow_step_id (single mode), pipeline_id (bulk mode), or updates array (cross-pipeline mode) is required',
				'tool_name' => 'configure_flow_steps',
			);
		}

		// Handle validate_only mode for bulk operations
		if ( $validate_only && ! empty( $pipeline_id ) ) {
			return $this->handleValidateOnly( $pipeline_id, $step_type, $handler_slug, $target_handler_slug, $handler_config, $flow_configs );
		}

		// Validation: target_handler_slug requires valid handler
		if ( ! empty( $target_handler_slug ) ) {
			$ability = wp_get_ability( 'datamachine/validate-handler' );
			if ( ! $ability ) {
				return array(
					'success'   => false,
					'error'     => 'Handler validation ability not available',
					'tool_name' => 'configure_flow_steps',
				);
			}
			$validation_result = $ability->execute( array( 'handler_slug' => $target_handler_slug ) );
			if ( is_wp_error( $validation_result ) || ! ( $validation_result['valid'] ?? false ) ) {
				return $this->buildErrorResponse( "Target handler '{$target_handler_slug}' not found", 'configure_flow_steps' );
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
		$ability = wp_get_ability( 'datamachine/update-flow-step' );
		if ( ! $ability ) {
			return array(
				'success'   => false,
				'error'     => 'Update flow step ability not available',
				'tool_name' => 'configure_flow_steps',
			);
		}

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

		$result = $ability->execute( $input );

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
		$ability = wp_get_ability( 'datamachine/configure-flow-steps' );
		if ( ! $ability ) {
			return array(
				'success'   => false,
				'error'     => 'Configure flow steps ability not available',
				'tool_name' => 'configure_flow_steps',
			);
		}

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

		$result = $ability->execute( $input );

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

	/**
	 * Handle validate_only mode - dry-run validation without execution.
	 */
	private function handleValidateOnly(
		int $pipeline_id,
		?string $step_type,
		?string $handler_slug,
		?string $target_handler_slug,
		array $handler_config,
		array $flow_configs
	): array {
		$ability = wp_get_ability( 'datamachine/validate-flow-steps-config' );
		if ( ! $ability ) {
			return array(
				'success'   => false,
				'error'     => 'Validate flow steps config ability not available',
				'tool_name' => 'configure_flow_steps',
			);
		}

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

		if ( ! empty( $handler_config ) ) {
			$input['handler_config'] = $handler_config;
		}

		if ( ! empty( $flow_configs ) ) {
			$input['flow_configs'] = $flow_configs;
		}

		$result              = $ability->execute( $input );
		$result['tool_name'] = 'configure_flow_steps';
		$result['mode']      = 'validate_only';

		return $result;
	}

	/**
	 * Handle cross-pipeline mode - configure multiple flows across different pipelines.
	 *
	 * @param array $updates Array of {flow_id, step_configs} objects.
	 * @param array $shared_config Shared step config applied before per-flow overrides.
	 * @param bool  $validate_only Whether to validate without executing.
	 * @return array Tool response.
	 */
	private function handleCrossPipelineMode( array $updates, array $shared_config, bool $validate_only ): array {
		$ability = wp_get_ability( 'datamachine/configure-flow-steps' );
		if ( ! $ability ) {
			return array(
				'success'   => false,
				'error'     => 'Configure flow steps ability not available',
				'tool_name' => 'configure_flow_steps',
			);
		}

		$result = $ability->execute(
			array(
				'updates'       => $updates,
				'shared_config' => $shared_config,
				'validate_only' => $validate_only,
			)
		);

		$result['tool_name'] = 'configure_flow_steps';

		if ( $result['success'] ?? false ) {
			if ( $validate_only ) {
				$result['data'] = array(
					'mode'         => 'validate_only',
					'would_update' => $result['would_update'] ?? array(),
					'message'      => $result['message'] ?? 'Validation passed.',
				);
				unset( $result['would_update'], $result['valid'], $result['mode'] );
			} else {
				$result['data'] = array(
					'flows_updated'  => $result['flows_updated'],
					'steps_modified' => $result['steps_modified'],
					'details'        => $result['updated_steps'] ?? array(),
					'errors'         => $result['errors'] ?? array(),
					'message'        => $result['message'] ?? 'Cross-pipeline configuration completed.',
					'mode'           => 'cross_pipeline',
				);
				unset( $result['flows_updated'], $result['steps_modified'], $result['updated_steps'], $result['errors'], $result['mode'] );
			}
		}

		return $result;
	}
}
